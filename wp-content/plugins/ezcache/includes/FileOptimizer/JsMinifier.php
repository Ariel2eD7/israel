<?php
namespace Upress\EzCache\FileOptimizer;

use MatthiasMullie\Minify\JS;
use Upress\EzCache\Cache;

class JsMinifier extends BaseFileOptimizer {
	static $FILE_TYPE = 'js';

	protected $settings;
	protected $minify_base_path;
	protected $minify_base_url;

	function __construct( $cache_dir, $cache_url ) {
		$this->minify_base_path = $cache_dir;
		$this->minify_base_url  = $cache_url;
	}

	/**
	 * Minifies CSS files
	 *
	 * @param string $html HTML content.
	 *
	 * @return string
	 */
	public function optimize( $html ) {
		$scripts = $this->find( '<script\s+([^>]+[\s\'"])?src\s*=\s*[\'"]\s*?([^\'"]+\.js(?:\?[^\'"]*)?)\s*?[\'"]([^>]+)?\/?>', $html );

		if ( ! $scripts ) {
			return $html;
		}

		foreach ( $scripts as $script ) {
			global $wp_scripts;

			if ( preg_match( '/[-.]min\.js/uiU', $script[2] ) ) {
				continue;
			}

			if ( preg_match( '/^\/\//ui', $script[2] ) ) {
				$script[2] = 'http' . (is_ssl() ? 's' : '') . ':' . $script[2];
			}

			if ( $this->is_external_file( $script[2] ) ) {
				continue;
			}

			if ( $this->is_minify_excluded_file( $script ) ) {
				continue;
			}

			// Don't minify jQuery included in WP core since it's already minified but without .min in the filename.
			if ( ! empty( $wp_scripts->registered['jquery-core']->src ) && false !== strpos( $script[2], $wp_scripts->registered['jquery-core']->src ) ) {
				continue;
			}

			$minify_url = $this->replace_url( $script[2] );

			if ( ! $minify_url ) {
				continue;
			}

			$replace_script = str_replace( $script[2], $minify_url, $script[0] );
			$replace_script = str_replace( '<script', '<script data-minify="1"', $replace_script );
			$html           = str_replace( $script[0], $replace_script, $html );
		}

		return $html;
	}

	/**
	 * Creates the minify URL if the minification is successful
	 *
	 * @param string $url Original file URL.

	 * @return string|bool The minify URL if successful, false otherwise
	 */
	private function replace_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		$file_path = $this->get_file_path( $url );

		if ( ! $file_path ) {
			return false;
		}

		$minified_content = $this->minify( $file_path );

		if ( ! $minified_content ) {
			return false;
		}

		$unique_id = md5( $minified_content );
		$uri_path = $this->get_url_component( $url, PHP_URL_PATH );
		$realpath = ltrim( Cache::realpath( $uri_path ), '/' );
		$filename  = preg_replace( '/\.js$/', '-' . $unique_id . '.js', $realpath );

		$minified_file = $this->minify_base_path . $filename;
		$minified_url  = $this->minify_base_url . $filename;

		if ( file_exists( $minified_file ) ) {
			touch( $minified_file );
			return $minified_url;
		}

		wp_mkdir_p( dirname( $minified_file ) );
		$save_minify_file = file_put_contents( $minified_file, $minified_content );

		if ( ! $save_minify_file ) {
			return false;
		}

		return $minified_url;
	}

	/**
	 * Minifies the content
	 *
	 * @param string|array $file     File to minify.
	 *
	 * @return string|bool Minified content, false if empty
	 */
	protected function minify( $file ) {
		$file_content = file_get_contents( $file );

		if ( ! $file_content ) {
			return false;
		}

		$minifier         = new JS( $file_content );
		$minified_content = $minifier->minify();

		if ( empty( $minified_content ) ) {
			return false;
		}

		return $minified_content;
	}
}
