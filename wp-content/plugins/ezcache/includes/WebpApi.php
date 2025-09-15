<?php
namespace Upress\EzCache;

use ErrorException;
use ParagonIE\Sodium\Core\Poly1305\State;
use Upress\EzCache\Utilities\Logger;
use WP_Error;

class WebpApi {
	protected $domain;
	protected $license_key;
	protected $api_url = 'https://api.ezcache.app';

	function __construct( $key ) {
		$this->domain = str_replace( [ 'https://', 'http://' ], '', get_bloginfo( 'wpurl' ) );
		$this->license_key = $key;
	}

	public function convert( $image_path ) {
		wp_raise_memory_limit( 'image' );

		$url = $this->api_url . '?' . http_build_query( [ 'licence_key' => $this->license_key, 'domain' => $this->domain ] );

		$file = @fopen( $image_path, 'r' );
		$file_size = filesize( $image_path );
		$file_data = fread( $file, $file_size );

		if ( false === $file_data ) {
			return new WP_Error( 'invalid_input_file', 'Unable to read source image file' );
		}

		$response = wp_remote_post( $url, [
			'headers' => [
				'content-type' => 'application/binary',
				'X-Requested-By' => 'wp_remote_request'
			],
			'body' => $file_data,
			'timeout' => 900,
			'user-agent' => 'ezCache WebP Converter',
		] );

		@fclose( $file );

		if ( is_wp_error( $response ) ) {
			Logger::log( 'ezCache API Error: ' . $response->get_error_message() );
			return $response;
		}

		$info = wp_remote_retrieve_headers( $response );
		$result = wp_remote_retrieve_body( $response );

		return [ 'info' => $info, 'data' => $result ];
	}
}
