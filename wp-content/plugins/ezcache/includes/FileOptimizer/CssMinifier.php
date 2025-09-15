<?php
namespace Upress\EzCache\FileOptimizer;

use MatthiasMullie\Minify\CSS;
use Upress\EzCache\Cache;

class CssMinifier extends BaseFileOptimizer {
	static $FILE_TYPE = 'css';

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
		$styles = $this->find( '<link\s+([^>]+[\s"\'])?href\s*=\s*[\'"]\s*?([^\'"]+\.css(?:\?[^\'"]*)?)\s*?[\'"]([^>]+)?\/?>', $html );

		if ( ! $styles ) {
			return $html;
		}

		foreach ( $styles as $style ) {
			if ( preg_match( '/(?:-|\.)min.css/iuU', $style[2] ) ) {
				continue;
			}

			if ( preg_match( '/^\/\//i', $style[2] ) ) {
				$style[2] = 'http' . (is_ssl() ? 's' : '') . ':' . $style[2];
			}

			if ( $this->is_external_file( $style[2] ) ) {
				continue;
			}

			if ( $this->is_minify_excluded_file( $style ) ) {
				continue;
			}

			$minify_url = $this->replace_url( $style[2] );

			if ( ! $minify_url ) {
				continue;
			}

			$replace_style = str_replace( $style[2], $minify_url, $style[0] );
			$replace_style = str_replace( '<link', '<link data-minify="1"', $replace_style );
			$html          = str_replace( $style[0], $replace_style, $html );
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
		$filename  = preg_replace( '/\.(css)$/', '-' . $unique_id . '.css', ltrim( Cache::realpath( $this->get_url_component( $url, PHP_URL_PATH ) ), '/' ) );

		$minified_file = $this->minify_base_path . $filename;
		$minify_url    = $this->minify_base_url . $filename;

		if ( file_exists( $minified_file ) ) {
			touch( $minified_file );
			return $minify_url;
		}

		wp_mkdir_p( dirname( $minified_file ) );
		$save_minify_file = file_put_contents( $minified_file, $minified_content );

		if ( ! $save_minify_file ) {
			return false;
		}

		return $minify_url;
	}

	/**
	 * Minifies the content
	 *
	 * @param string|array $file     File to minify.
	 * @return string|bool Minified content, false if empty
	 */
	protected function minify( $file ) {
		$file_content = file_get_contents( $file );

		if ( ! $file_content ) {
			return false;
		}

		$file_content     = $this->rewrite_paths( $file, $file_content );
		$minifier         = new CSS( $file_content );
		$minified_content = $minifier->minify();

		if ( empty( $minified_content ) ) {
			return false;
		}

		return $minified_content;
	}
}
