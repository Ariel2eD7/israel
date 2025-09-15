<?php
namespace Upress\EzCache\Rest;

use Upress\EzCache\Cache;

class StatusController {

	function show() {
		return wp_send_json_success( Cache::instance()->get_status() );
	}

}
