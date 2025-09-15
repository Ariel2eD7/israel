<?php
namespace Upress\EzCache\FileOptimizer;

use MatthiasMullie\Minify\CSS;
use Upress\EzCache\Cache;

class CssCombiner extends BaseFileOptimizer {
	static $FILE_TYPE = 'css';

	protected $minifier;
	protected $minify_base_path;
	protected $minify_base_url;
	protected $minify_footer;


	function __construct( $cache_dir, $cache_url, $minify_footer = false ) {
		$this->minifier = new CSS();
		$this->minify_base_path = $cache_dir;
		$this->minify_base_url  = $cache_url;
		$this->minify_footer  = $minify_footer;
	}

	/**
	 * Minifies and combines all CSS files into one
	 *
	 * @param string $html HTML content.
	 *
	 * @return string
	 */
	public function optimize( $html ) {
		$styles = $this->find( '<link\s+([^>]+[\s\'"])?href\s*=\s*[\'"]\s*?([^\'"]+\.css(?:\?[^\'"]*)?)\s*?[\'"]([^>]+)?\/?>', $html );

		if ( ! $styles ) {
			return $html;
		}

		$files  = [];
		$styles = array_map( function( $style ) use ( &$files ) {
			if ( preg_match( '/^\/\//ui', $style[2] ) ) {
				$style[2] = 'http' . (is_ssl() ? 's' : '') . ':' . $style[2];
			}

			if ( $this->is_external_file( $style[2] ) ) {
				return '';
			}

			if ( $this->is_minify_excluded_file( $style ) ) {
				return '';
			}

			$style_filepath = $this->get_file_path( $style[2] );

			if ( ! $style_filepath ) {
				return '';
			}

			$files[] = $style_filepath;

			return $style;
		}, $styles );

		if ( empty( $styles ) ) {
			return $html;
		}

		$minify_url = $this->combine( $files );

		if ( ! $minify_url ) {
			return $html;
		}

		$minify_link_tag = '<link rel="stylesheet" href="' . $minify_url . '" data-minify="1" />';

		if ( $this->minify_footer ) {
			$html = str_replace( '</body>', "{$minify_link_tag}\n</body>", $html );
		} else {
			$html = str_replace( '</title>', "</title>\n{$minify_link_tag}", $html );
		}

		$styles = array_filter( $styles );
		foreach ( $styles as $style ) {
			$html = str_replace( $style[0], '', $html );
		}

		return $html;
	}

	/**
	 * Creates the minify URL if the minification is successful
	 *
	 * @param array $files Files to minify.

	 * @return string|bool The minify URL if successful, false otherwise
	 */
	protected function combine( $files ) {
		if ( empty( $files ) ) {
			return false;
		}

		$minified_content = $this->minify( $files );

		if ( ! $minified_content ) {
			return false;
		}

		$filename  = md5( $minified_content ) . '.css';
		$minified_file = $this->minify_base_path . $filename;

		if ( file_exists( $minified_file ) ) {
			touch( $minified_file );
		} else {
			wp_mkdir_p( dirname( $minified_file ) );
			$minify_filepath = file_put_contents( $minified_file, $minified_content );

			if ( ! $minify_filepath ) {
				return false;
			}
		}

		return $this->minify_base_url . $filename;
	}

	/**
	 * Minifies the content
	 *
	 * @param string|array $files     Files to minify.
	 * @return string|bool Minified content, false if empty
	 */
	protected function minify( $files ) {
		foreach ( $files as $file ) {
			$file_content = file_get_contents( $file );
			$file_content = $this->rewrite_paths( $file, $file_content );

			$this->minifier->add( $file_content );
		}

		$minified_content = $this->minifier->minify();

		if ( empty( $minified_content ) ) {
			return false;
		}

		return $minified_content;
	}
}
