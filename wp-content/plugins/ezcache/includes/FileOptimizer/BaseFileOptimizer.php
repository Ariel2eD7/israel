<?php
namespace Upress\EzCache\FileOptimizer;

use Upress\EzCache\Cache;
use Upress\EzCache\Settings;
use Upress\EzCache\ThirdParty\Minify_CSS_UriRewriter;

abstract class BaseFileOptimizer {
	static $FILE_TYPE = '';

	function optimize( $html ) {
		return $html;
	}

	/**
	 * Check if the files is on a different host
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	function is_external_file( $url ) {
		$file = wp_parse_url( $url );
		$site = wp_parse_url( site_url() );

		// URL has domain and domain is not part of the internal domains.
		if ( isset( $file['host'] ) && ! empty( $file['host'] ) && $file['host'] != $site['host'] ) {
			return true;
		}

		if ( ! isset( $site['path'] ) ) {
			$site['path'] = '';
		}

		// URL has no domain and doesn't contain the WP_CONTENT path or wp-includes.
		if ( ! isset( $file['host'] ) && ! preg_match( '#(' . $site['path'] . '|wp-includes)#', $file['path'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get a path to the file by URL ignoring the query string
	 *
	 * @param string $url
	 *
	 * @return bool|string
	 */
	function get_file_path( $url ) {
		return Cache::url_to_path( strtok( $url, '?' ) );
	}

	/**
	 * Determines if it is a file excluded from minification
	 *
	 * @param array $tag Tag corresponding to a JS file.
	 *
	 * @return bool True if it is a file excluded, false otherwise
	 */
	function is_minify_excluded_file( $tag ) {
		// File should not be minified.
		if ( false !== strpos( $tag[0], 'data-minify=' ) || false !== strpos( $tag[0], 'data-no-minify=' ) ) {
			return true;
		}

		$file_path = $this->get_url_component( $tag[2], PHP_URL_PATH );

		if ( static::$FILE_TYPE && pathinfo( $file_path, PATHINFO_EXTENSION ) !== static::$FILE_TYPE ) {
			return true;
		}

		$excluded_files = Settings::get_settings()->excluded_minify_files;
		$excluded_files = preg_split( "/\\r\\n|\\r|\\n/u", trim( $excluded_files ), -1, PREG_SPLIT_NO_EMPTY );
		$excluded_files = array_filter( $excluded_files );
		$excluded_files = array_merge( $excluded_files, ['elementor/css/post-'] );
		$excluded_files = array_unique( $excluded_files );
		$excluded_files = apply_filters( 'ezcache_excluded_minify_files', $excluded_files );
		foreach ( $excluded_files as $file ) {
			if ( preg_match( '/' . preg_quote( $file, '/' ) . '/', $file_path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Finds nodes matching the pattern in the HTML
	 *
	 * @param string $pattern Pattern to match.
	 * @param string $html HTML content.
	 *
	 * @return bool|array
	 */
	protected function find( $pattern, $html ) {
		$html_nocomments = preg_replace( '/<!--(.*)-->/uUis', '', $html );
		preg_match_all( '/' . $pattern . '/uUmsi', $html_nocomments, $matches, PREG_SET_ORDER );

		if ( empty( $matches ) ) {
			return false;
		}

		return $matches;
	}

	/**
	 * Rewrites the paths inside the CSS file content
	 *
	 * @param string $file    File path.
	 * @param string $content File content.
	 *
	 * @return string
	 */
	public function rewrite_paths( $file, $content ) {
		return Minify_CSS_UriRewriter::rewrite( $content, dirname( $file ) );
	}

	public function get_url_component( $url, $component = -1 ) {
		return _get_component_from_parsed_url_array( wp_parse_url( $url ), $component );
	}
}
