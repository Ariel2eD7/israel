<?php
/*
 * ezCache Advanced Cache
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'NO direct access!' );
}

// require utilities functions from the plugin
/** @noinspection PhpIncludeInspection */
include_once __DIR__ . '/plugins/ezcache/vendor/autoload.php';

if ( class_exists( '\Upress\EzCache\Cache' ) && ! function_exists( 'wp_cache_postload' ) ) {
	global $ezcache;

	/** @noinspection PhpFullyQualifiedNameUsageInspection */
	$ezcache = \Upress\EzCache\Cache::instance();
	$ezcache->maybe_serve_cached_data();

	// this needs to run before wp_cache_postload but after checking a cache file exists
	$ezcache->do_frontend_optimizations();

	/**
	 * Start saving the cache file
	 * This function is called at wp-settings.php when WP_CACHE is true in wp-config.php
	 */
	function wp_cache_postload() {
		global $ezcache;

		$ezcache->maybe_write_cache_file();
	}
}
