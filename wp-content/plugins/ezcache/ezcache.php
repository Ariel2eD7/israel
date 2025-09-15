<?php
/*
	Plugin Name: ezCache
	Description: ezCache is an easy and innovative cache plugin that will help you significantly improve your site speed.
	Plugin URI: https://ezcache.app
	Version: 1.6.6
	Author: uPress
	Author URI: https://www.upress.io
	Text Domain: ezcache
	Domain Path: /languages/
	License: GPLv2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		die( 'NO direct access!' );
	}

	define( 'EZCACHE_DIR', __DIR__ );
	define( 'EZCACHE_FILE', __FILE__ );
	define( 'EZCACHE_URL', plugin_dir_url( __FILE__ ) );
	define( 'EZCACHE_BASEBANE', basename( __FILE__ ) );
	define( 'EZCACHE_VERSION', '1.6.6' );
	define( 'EZCACHE_SETTINGS_KEY', 'ezcache' );

	register_activation_hook( EZCACHE_FILE, 'upress_ezcache_activation_hook' );
	register_deactivation_hook( EZCACHE_FILE, 'upress_ezcache_deactivation_hook' );

	function upress_ezcache_activation_hook() {
		$ezcache = Upress\EzCache\Plugin::initialize();
		$ezcache->activation_hook();
	}

	function upress_ezcache_deactivation_hook() {
		$ezcache = Upress\EzCache\Plugin::initialize();
		$ezcache->deactivation_hook();
	}
}

namespace Upress\EzCache {
	class Plugin {
		private static $instance;
		public $plugin_dir;
		public $plugin_file;
		public $plugin_url;
		public $plugin_basename;
		public $plugin_version;
		public $plugin_settings_key;
		/** @var Cache $ezcache */
		public $ezcache;

		/**
		 * @return Plugin
		 */
		public static function initialize() {
			if ( ! self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}


		private function __construct() {
			$this->plugin_dir          = EZCACHE_DIR;
			$this->plugin_file         = EZCACHE_FILE;
			$this->plugin_url          = EZCACHE_URL;
			$this->plugin_basename     = EZCACHE_BASEBANE;
			$this->plugin_version      = EZCACHE_VERSION;
			$this->plugin_settings_key = EZCACHE_SETTINGS_KEY;

			$this->load_dependencies();
			$this->initialize_plugin();
		}

		/**
		 * Import required files
		 */
		protected function load_dependencies() {
			require_once EZCACHE_DIR . '/vendor/autoload.php';
		}

		/**
		 * Init all the plugin parts
		 */
		protected function initialize_plugin() {
			$this->ezcache = Cache::instance();

			add_action( 'init', [ $this, 'load_translation' ] );
			add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );
			add_action( 'init', [ $this, 'maybe_repair_installation' ] );

			new RestApi( $this );
			new Admin( $this );

			add_action( 'ezcache_clear_expired_cache', [ $this->ezcache, 'clear_expired_cache' ] );
		}

		function load_translation() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'ezcache' );
			load_textdomain( 'ezcache', EZCACHE_DIR . "/languages/ezcache-{$locale}.mo" );
		}

		function get_wp_config_path() {
			if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
				return ABSPATH . 'wp-config.php';
			}

			return dirname( ABSPATH ) . '/wp-config.php';
		}

		function activation_hook() {
			set_transient( 'ezcache_activating', true );

			// copy advanced-cache
			copy( EZCACHE_DIR . '/advanced-cache.php', WP_CONTENT_DIR . '/advanced-cache.php' );

			if ( ! wp_next_scheduled( 'ezcache_clear_expired_cache' ) ) {
				Settings::maybe_update_cronjobs( Settings::get_settings() );
			}
			Updater::upgrade();

			$this->update_wp_config_const( [ 'WP_CACHE' => true ] );

			$this->ezcache->preload_homepage();

			delete_transient( 'ezcache_activating' );
		}

		function deactivation_hook() {
			set_transient( 'ezcache_deactivating', true );

			// delete advanced-cache
			if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
				unlink( WP_CONTENT_DIR . '/advanced-cache.php' );
			}

			if ( wp_next_scheduled( 'ezcache_clear_expired_cache' ) ) {
				wp_clear_scheduled_hook( 'ezcache_clear_expired_cache' );
			}

			delete_site_option( 'ezcache_first_run' );

			$this->update_wp_config_const( [ 'WP_CACHE' => false ] );

			$this->ezcache->clear_cache();

			delete_transient( 'ezcache_deactivating' );
		}

		/**
		 * Update the wp-config.php file with the specified values
		 *
		 * @param array $args Assoc array of key-value, key is the define to update with the value
		 */
		function update_wp_config_const( $args ) {
			if ( ! is_array( $args ) ) {
				return;
			}

			$wp_config_path = $this->get_wp_config_path();

			copy( $wp_config_path, $wp_config_path . '.backup' );

			$contents = file_get_contents( $wp_config_path );
			$contents = preg_replace( "/\r\n|\r|\n/", "\n", $contents ); // normalize line-breaks

			// update wp-config define
			foreach ( $args as $const => $value ) {
				if ( is_string( $value ) ) {
					$value = "'" . str_replace( "'", "\'", $value ) . "'";
				} elseif ( is_bool( $value ) ) {
					$value = $value ? 'true' : 'false'; // simply casting to string will result in '1' and ''
				} else {
					continue;
				}

				if ( preg_match( '/define\s*?\(\s*?[\']' . $const . '[\'"]/', $contents ) ) {
					$contents = preg_replace( '/define\s*?\(\s*?([\'"])' . $const . '\1,\s*(([\'"]).+\3|.+?)\s*\)/', "define( '{$const}', {$value} )", $contents );
				} else {
					$contents = preg_replace( '/(<\?php)/', "$1\ndefine( '{$const}', {$value} );", $contents );
				}
			}

			if ( "\n" !== PHP_EOL ) {
				// update line-breaks to platform defaults
				$contents = preg_replace( "/\n/", PHP_EOL, $contents );
			}

			file_put_contents( $wp_config_path, trim( $contents ) );

			if ( filesize( $wp_config_path ) <= 0 ) {
				// operation failed, revert the file
				copy( $wp_config_path . '.backup', $wp_config_path );
			}

			unlink( $wp_config_path . '.backup' );
		}

		function add_cron_schedules( $schedules ) {
			if ( ! isset( $schedules['every_10_minutes'] ) ) {
				$schedules['every_10_minutes'] = [
					'interval' => 600,
					'display'  => __( 'Every 10 Minutes', 'ezcache' ),
				];
			}
			if ( ! isset( $schedules['hourly'] ) ) {
				$schedules['hourly'] = [
					'interval' => 3600,
					'display'  => __( 'Hourly', 'ezcache' ),
				];
			}
			if ( ! isset( $schedules['every_3_hours'] ) ) {
				$schedules['every_3_hours'] = [
					'interval' => 10800,
					'display'  => __( 'Every 3 Hours', 'ezcache' ),
				];
			}
			if ( ! isset( $schedules['every_6_hours'] ) ) {
				$schedules['every_6_hours'] = [
					'interval' => 21600,
					'display'  => __( 'Every 6 Hours', 'ezcache' ),
				];
			}
			if ( ! isset( $schedules['every_12_hours'] ) ) {
				$schedules['every_12_hours'] = [
					'interval' => 43200,
					'display'  => __( 'Every 12 Hours', 'ezcache' ),
				];
			}
			if ( ! isset( $schedules['daily'] ) ) {
				$schedules['daily'] = [
					'interval' => 86400,
					'display'  => __( 'Daily', 'ezcache' ),
				];
			}
			if ( ! isset( $schedules['every_3_days'] ) ) {
				$schedules['every_3_days'] = [
					'interval' => 259200,
					'display'  => __( 'Every 3 Days', 'ezcache' ),
				];
			}
			if ( ! isset( $schedules['every_5_days'] ) ) {
				$schedules['every_5_days'] = [
					'interval' => 432000,
					'display'  => __( 'Every 5 Days', 'ezcache' ),
				];
			}
			if ( ! isset( $schedules['every_7_days'] ) ) {
				$schedules['every_7_days'] = [
					'interval' => 604800,
					'display'  => __( 'Every 7 Days', 'ezcache' ),
				];
			}

			return $schedules;
		}

		/**
		 * Repaire the plugin installation when POST variable is set
		 */
		function maybe_repair_installation() {
			$repaired = get_transient( 'ezcache_repaired' );
			delete_transient( 'ezcache_repaired' );

			if ( $repaired || get_transient( 'ezcache_deactivating' ) || get_transient( 'ezcache_activating' ) ) {
				return;
			}

			if ( ! wp_next_scheduled( 'ezcache_clear_expired_cache' ) ) {
				Settings::maybe_update_cronjobs( Settings::get_settings() );
			}

			$status = $this->ezcache->get_status();
			if ( $status['cache_enabled'] && $status['adv_cache_exists'] && $status['adv_cache_exists'] && $status['correct_cache_exists'] && $status['webp_table_exists'] ) {
				return;
			}

			$this->activation_hook();

			set_transient( 'ezcache_repaired', true );
		}
	}

	add_action( 'plugins_loaded', '\Upress\EzCache\Plugin::initialize' );
}
