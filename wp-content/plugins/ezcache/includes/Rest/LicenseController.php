<?php
namespace Upress\EzCache\Rest;

use Upress\EzCache\LicenseApi;
use WP_REST_Request;

class LicenseController {
	function show() {
		$manager = new LicenseApi();
		$key = $manager->get_masked_license_key();
		$license_data = $manager->get_license_data( false );

		return wp_send_json_success( array_merge( (array) $license_data, [ 'key' => $key ] ) );
	}

	/**
	 * @param WP_REST_Request $request
	 */
	function update( $request ) {
		$input = $request->get_json_params();
		$key = isset( $input['licenseKey'] ) ? trim( strtoupper( $input['licenseKey'] ) ) : '';

		$manager = new LicenseApi();
		$license_data = $manager->update_license( $key );
		if ( is_wp_error( $license_data ) ) {
			return wp_send_json_error( $license_data );
		}
		$key = $manager->get_masked_license_key();

		return wp_send_json_success( array_merge( (array) $license_data, [ 'key' => $key ] ) );
	}

	function destroy() {
		$manager = new LicenseApi();
		$response = $manager->clear_license();

		if ( is_wp_error( $response ) ) {
			return wp_send_json_error( $response );
		}

		return wp_send_json_success( $response );
	}
}
