<?php
namespace Upress\EzCache\Rest;

use Exception;
use Upress\EzCache\BackgroundProcesses\ConvertWebpProcess;
use Upress\EzCache\Cache;
use WP_REST_Request;

class WebpController {
	function process() {
		$process = new ConvertWebpProcess();
		$process->schedule();
	}
	/**
	 * @param WP_REST_Request $request
	 */
	function destroy() {
		Cache::instance()->clear_cache( true );

		return wp_send_json_success();
	}

}
