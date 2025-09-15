<?php
namespace Upress\EzCache\Rest;

use Exception;
use Upress\EzCache\Cache;
use WP_REST_Request;

class CacheController {

	function show() {
		try {
			$stats = Cache::instance()->get_cache_stats();
		} catch( Exception $ex) {
			return wp_send_json_error( [ 'error' => $ex->getMessage() ] );
		}

		return wp_send_json_success( $stats );
	}

	/**
	 * @param WP_REST_Request $request
	 */
	function destroy( $request ) {
		$input = $request->get_json_params();

		Cache::instance()->clear_cache();

		return wp_send_json_success( [ 'input' => $input ] );
	}

}
