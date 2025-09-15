<?php

namespace Upress\EzCache\BackgroundProcesses;

use Upress\EzCache\LicenseApi;
use Upress\EzCache\Settings;
use Upress\EzCache\Utilities\Logger;
use Upress\EzCache\WebpApi;
use \WP_Background_Process;

class ConvertWebpProcess {
	protected $action = 'ezcache_convert_images_to_webp';
	protected $hook;
	protected $convert_queue;

	public function __construct() {
		$this->hook          = $this->action . '_hook';
		$this->convert_queue = $this->action . '_convert_queue';

		add_action( $this->hook, [ $this, 'process_batch' ] );
	}

	public function schedule() {
		$settings = Settings::get_settings();

		if ( $settings->enable_webp_support && ! wp_next_scheduled( $this->hook ) ) {
			wp_schedule_single_event( time(), $this->hook );

			wp_safe_remote_get( add_query_arg( [ 'doing_wp_cron' => time() ], site_url( '/wp-cron.php' ) ), [
				'user-agent' => 'ezCache Background WebP Processor Cron Dispatcher',
				'timeout'    => 0.1,
			] );
		}
	}

	public function add_to_queue( $image_id, $cache_file ) {
		if ( ! $cache_file ) {
			return false;
		}

		$queue = get_site_option( $this->convert_queue, [] );

		$key = 'i' . $image_id;

		if ( ! isset( $queue[ $key ] ) ) {
			$queue[ $key ] = [
				'image_id'    => $image_id,
				'cache_files' => [],
			];
		}

		if ( in_array( $cache_file, $queue[ $key ]['cache_files'] ) ) {
			return true;
		}

		$queue[ $key ]['cache_files'][] = $cache_file;

		update_site_option( $this->convert_queue, $queue );
	}

	protected function shift_queue( $count ) {
		$queue = get_site_option( $this->convert_queue, [] );
		if ( ! count( $queue ) ) {
			return [];
		}

		$to_run = array_splice( $queue, 0, $count );

		update_site_option( $this->convert_queue, $queue );

		return $to_run;
	}

	protected function is_queue_empty() {
		$queue = get_site_option( $this->convert_queue, [] );

		return count( $queue ) == 0;
	}


	public function process_batch() {
		global $wpdb;

		$queue = $this->shift_queue( 15 );
		if ( 0 >= count( $queue ) ) {
			return;
		}

		$image_ids = array_column( $queue, 'image_id' );
		$images = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}ezcache_webp_images` WHERE `id` IN (" . rtrim( str_repeat(  '%d,', count( $image_ids ) ), ',' ) . ") LIMIT 15",
				$image_ids
			)
		);
		$precached_images = [];
		foreach ( $images as $image ) {
			$precached_images[ $image->id ] = $image;
		}

		foreach ( $queue as $data ) {
			$image_id    = $data['image_id'];
			$cache_files = $data['cache_files'];

			try {
				$this->convert_image( $image_id, $cache_files, $precached_images );
			} catch ( \Exception $ex ) {
				Logger::log( "ezCache ConvertWebpProcess::convert_image error: {$ex->getMessage()}\n{$ex->getTraceAsString()}" );
			}
		}

		if ( ! $this->is_queue_empty() ) {
			$this->schedule();

			return;
		}

		$wpdb->query( "OPTIMIZE TABLE `{$wpdb->prefix}ezcache_webp_images`" );
	}

	protected function convert_image( $image_id, $cache_files, $precached_images = [] ) {
		global $wpdb;

		$image = isset( $precached_images[$image_id] ) ? $precached_images[$image_id] : $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}ezcache_webp_images` WHERE `id` = %s LIMIT 1",
				[ $image_id ]
			)
		);

		if ( ! $image ) {
			Logger::log( "ezCache WebP Background Processor: image with ID {$image_id} was not found." );

			return;
		}

		if ( 'completed' == $image->status ) {
			$this->replace_links( $cache_files, $image->url, $image->webp_url );

			return;
		}

		// only download the file if we don't have it locally
		if ( ! file_exists( $image->webp_path ) || filesize( $image->webp_path ) <= 2 || stripos( file_get_contents( $image->webp_path ), '"success":false' ) ) {
			$license   = new LicenseApi();
			if ( ! $license->is_license_valid() ) {
				Logger::log( "ezCache WebP Background Processor: image with ID {$image_id} skipped, license not valid." );
				return;
			}

			$converter = new WebpApi( $license->get_license_key() );
			$response  = $converter->convert( $image->path );

			if ( is_wp_error( $response ) || stripos( $response['info']['content-type'], 'json' ) || stripos( $response['data'], '"success":false' ) ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE `{$wpdb->prefix}ezcache_webp_images` SET `status` = %s WHERE `id` = %d",
						[ 'failed', $image_id ]
					)
				);
				Logger::log( "ezCache WebP Background Processor: " . ( is_wp_error( $response ) ? $response->get_error_message() : $response['data'] ) );

				// delete the broken file
				if ( file_exists( $image->webp_path ) ) {
					unlink( $image->webp_path );
				}

				return;
			}

			file_put_contents( $image->webp_path, $response['data'] );
		}

		$original_size = filesize( $image->path );
		$webp_size     = filesize( $image->webp_path );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$wpdb->prefix}ezcache_webp_images` SET `status` = %s, `original_size` = %d, `webp_size` = %d WHERE `id` = %d",
				[ 'completed', $original_size, $webp_size, $image_id ]
			)
		);

		$this->replace_links( $cache_files, $image->url, $image->webp_url );
	}

	protected function replace_links( $cache_files, $image_url, $webp_url ) {
		foreach ( $cache_files as $cache_file ) {
			if ( ! file_exists( $cache_file ) ) {
				// File has been deleted? maybe the cache got cleared before the process finished
				continue;
			}

			$handle = @fopen( $cache_file, 'c+' );

			if ( $handle && @flock( $handle, LOCK_EX ) ) {
				$contents = gzdecode( fread( $handle, filesize( $cache_file ) ) );

				$contents = preg_replace(
					'/' . preg_quote( $image_url, '/' ) . '(?!\.webp)/i',
					$webp_url,
					$contents
				);

				ftruncate( $handle, 0 );
				fseek( $handle, 0 );
				fwrite( $handle, gzencode( $contents, 6, FORCE_GZIP ) );
				flock( $handle, LOCK_UN );
			} else {
				Logger::log( "ezCache WebP Background Processor: Could not get a lock on {$cache_file}, failed updating WebP image URLs" );
			}

			if ( $handle ) {
				fclose( $handle );
			}
		}
	}
}
