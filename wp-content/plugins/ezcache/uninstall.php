<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die( 'NO direct access!' );
}

include_once __DIR__ . '/vendor/autoload.php';

if ( class_exists( '\Upress\EzCache\Plugin' ) ) {
	$ezcache = Upress\EzCache\Plugin::initialize();
	$ezcache->deactivation_hook();
}

if ( class_exists( '\Upress\EzCache\Updater' ) ) {
	\Upress\EzCache\Updater::uninstall();
}
