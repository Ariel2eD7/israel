<?php
namespace Upress\EzCache\Rest;

use Upress\EzCache\Cache;
use Upress\EzCache\Settings;
use WP_REST_Request;

class SettingsController {

	function show() {
		$settings = Settings::get_settings();

		return wp_send_json_success( $settings );
	}

	/**
	 * @param WP_REST_Request $request
	 */
	function update( $request ) {
		$default_settings = (array) Settings::get_default_settings();

		$input = (array) $request->get_json_params();
		$updated_settings = $this->sanitize_settings( $input, $default_settings );

		Settings::set_settings( $updated_settings );
		Cache::instance()->clear_cache();

		return wp_send_json_success();
	}

	function destroy() {
		$default_settings = (array) Settings::get_default_settings();
		Settings::set_settings( $default_settings );
		Cache::instance()->clear_cache();

		return wp_send_json_success( $default_settings );
	}

	/**
	 * Sanitize the settings based on the predefined $default_settings
	 *
	 * @param array $settings
	 * @param array $default_settings
	 *
	 * @return array
	 */
	protected function sanitize_settings( $settings, $default_settings ) {
		$sanitized = [];

		foreach( $settings as $key => $value ) {
			if ( ! isset( $default_settings[ $key ] ) ) {
				continue;
			}

			$type = gettype( $default_settings[ $key ] );

			if ( 'array' === $type || 'object' === $type ) {
				$value = $this->sanitize_settings( ((array) $value), $default_settings[ $key ] );
			} elseif ( method_exists( $this, "sanitize_{$type}" ) ) {
				$value = call_user_func( [ $this, "sanitize_{$type}" ], $value );
			} else {
				continue;
			}

			$sanitized[ $key ] = $value;
		}

		return $sanitized;
	}

	/**
	 * @param mixed $bool
	 *
	 * @return bool
	 */
	protected function sanitize_boolean( $bool ) {
		return !! $bool;
	}

	/**
	 * @param mixed $int
	 *
	 * @return int
	 */
	protected function sanitize_integer( $int ) {
		return intval( $int );
	}

	/**
	 * @param mixed $double
	 *
	 * @return float
	 */
	protected function sanitize_double( $double ) {
		return doubleval( $double );
	}

	/**
	 * @param mixed $string
	 *
	 * @return string
	 */
	protected function sanitize_string( $string ) {
		return sanitize_textarea_field( $string );
	}
}
