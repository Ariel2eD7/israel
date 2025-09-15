<?php
namespace Upress\EzCache\FileOptimizer;

/**
 * Combine Google Fonts
 *
 * @since 3.1
 * @author Remy Perona
 */
class CombineGoogleFonts extends BaseFileOptimizer {
	protected $fonts;
	protected $subsets;

	public function __construct() {
		$this->fonts   = [];
		$this->subsets = [];
	}

	/**
	 * Combines multiple Google Fonts links into one
	 *
	 * @param string $html HTML content.
	 *
	 * @return string
	 */
	public function optimize( $html ) {
		$fonts = $this->find( '<link(?:\s+(?:(?!href\s*=\s*)[^>])+)?(?:\s+href\s*=\s*([\'"])((?:https?:)?\/\/fonts\.googleapis\.com\/css(?:(?!\1).)+)\1)(?:\s+[^>]*)?>', $html );

		if ( ! $fonts ) {
			return $html;
		}

		$this->parse( $fonts );

		if ( empty( $this->fonts ) ) {
			return $html;
		}

		$html = str_replace( '</title>', '</title>' . $this->get_combine_tag(), $html );

		foreach ( $fonts as $font ) {
			$html = str_replace( $font[0], '', $html );
		}

		return $html;
	}

	/**
	 * Finds links to Google fonts
	 *
	 * @param string $pattern Pattern to search for.
	 * @param string $html HTML content.
	 *
	 * @return bool|array
	 */
	protected function find( $pattern, $html ) {
		preg_match_all( '/' . $pattern . '/uUmsi', $html, $matches, PREG_SET_ORDER );

		if ( count( $matches ) <= 1 ) {
			return false;
		}

		return $matches;
	}

	/**
	 * Parses found matches to extract fonts and subsets
	 *
	 * @param array $matches Found matches for the pattern.
	 */
	protected function parse( $matches ) {
		foreach ( $matches as $match ) {
			$query = $this->get_url_component( $match[2], PHP_URL_QUERY );

			if ( ! isset( $query ) ) {
				return;
			}

			$query = html_entity_decode( $query );
			$font  = wp_parse_args( $query );

			// Add font to the collection.
			$this->fonts[] = rawurlencode( htmlentities( $font['family'] ) );

			// Add subset to collection.
			$this->subsets[] = isset( $font['subset'] ) ? rawurlencode( htmlentities( $font['subset'] ) ) : '';
		}

		// Concatenate fonts tag.
		$this->subsets = ( $this->subsets ) ? '&subset=' . implode( ',', array_filter( array_unique( $this->subsets ) ) ) : '';
		$this->fonts   = implode( '|', array_filter( array_unique( $this->fonts ) ) );
		$this->fonts   = str_replace( '|', '%7C', $this->fonts );
	}

	/**
	 * Returns the combined Google fonts link tag
	 *
	 * @return string
	 */
	protected function get_combine_tag() {
		return '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=' . $this->fonts . $this->subsets . '" />';
	}
}
