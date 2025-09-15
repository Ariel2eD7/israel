<?php

namespace Upress\EzCache;

use WP_Admin_Bar;
use wpdb;

class Admin {
	/** @var Plugin */
	private $plugin;
	protected static $svg = '<svg viewBox="0 0 480 480" version="1.1" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" xmlns:serif="http://www.serif.com/" fill-rule="evenodd" clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2">
<g id="Camada-1" serif:id="Camada 1" fill-rule="nonzero">
    <path d="M206.088 138.266l-90.584 127.353h115.3l-67.078 160.647 201.096-213.077H254.006l53.292-74.923h-101.21z" fill="#fc0"/>
    <path d="M205.088 138.266l-90.585 127.352h115.3l-67.078 160.647L285.68 239.266H180.327l75.364-101h-50.603z" fill="url(#_Linear1)"/>
  </g>
  <defs>
    <linearGradient id="_Linear1" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(171.176 0 0 -171.176 114.504 282.266)">
      <stop offset="0" stop-color="#deb203" stop-opacity="1"/>
      <stop offset="1" stop-color="#fc0" stop-opacity="1"/>
    </linearGradient>
  </defs>
</svg> ';

	function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_filter( 'show_admin_bar', [ $this, 'maybe_show_admin_bar' ], 100 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'plugin_action_links_' . plugin_basename( $this->plugin->plugin_file ), [
			$this,
			'plugin_action_links',
		] );
		add_action( 'admin_notices', [ $this, 'maybe_show_advanced_cache_notice' ] );

		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_button' ], 999 );
		add_action( 'admin_post_wpb_clear_cache', [ $this, 'admin_clear_cache' ] );
		add_action( 'delete_attachment', [ $this, 'delete_webp_image' ] );

		add_action( 'init', [ $this, 'register_post_meta' ] );
		add_action( 'add_meta_boxes', [ $this, 'post_cache_metabox' ] );
		add_action( 'save_post', [ $this, 'save_post_cache_metabox' ] );
		add_action( 'pre_post_update', [ $this, 'maybe_clear_cache_on_post_update' ], 10, 2 );
	}

	/**
	 * Delete the webp versions when the image is deleted
	 *
	 * @param int $attachment_id
	 */
	function delete_webp_image( $attachment_id ) {
		global $wpdb;

		$upload_dir = wp_upload_dir()['basedir'];

		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! $meta || ! isset( $meta['file'] ) ) {
			return;
		}

		if ( ! isset( $meta['sizes'] ) ) {
			$meta['sizes'] = [];
		}

		$meta['sizes'][] = [ 'file' => $meta['file'] ];

		$hashes = [];
		foreach ( $meta['sizes'] as $size ) {
			$image_path      = trailingslashit( $upload_dir ) . $meta['file'];
			$ext             = pathinfo( $image_path, PATHINFO_EXTENSION );
			$image_webp_path = preg_replace( '/^(.+)\.' . preg_quote( $ext, '/' ) . '$/u', '$1.webp', $image_path );

			$hashes[] = sha1( $image_path );

			if ( file_exists( $image_webp_path ) ) {
				unlink( $image_webp_path );
			}
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$wpdb->prefix}ezcache_webp_images` WHERE `uid` IN (" . substr( str_repeat( "%d, ", count( $hashes ) ), 0, - 2 ) . ")",
				$hashes
			)
		);

		$wpdb->query( "OPTIMIZE TABLE `{$wpdb->prefix}ezcache_webp_images`" );
	}

	/**
	 * Register the post meta fields
	 */
	function register_post_meta() {
		if ( function_exists( 'register_post_meta' ) ) {
			register_post_meta( 'post', '_ezcache_do_not_cache_post', [
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'bool',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			] );
		}
	}

	/**
	 * Register the meta box
	 */
	function post_cache_metabox() {
		global $post_type;

		if ( 'attachement' == $post_type ) {
			return;
		}

		add_meta_box(
			'ezcache_metabox',
			__( 'Caching', 'ezcache' ),
			[ $this, 'post_cache_metabox_output' ],
			null,
			'side'
		);
	}

	/**
	 * Render the post meta box
	 *
	 * @param $post
	 */
	function post_cache_metabox_output( $post ) {
		global $post_type_object;

		$value = get_post_meta( $post->ID, '_ezcache_do_not_cache_post', true );
		wp_nonce_field( 'ezcache_metabox', '_eznonce' );
		?>
        <div class="components-panel__row">
            <div class="components-base-control">
                <input type="checkbox"
                       id="ezcache_no_cache"
                       name="ezcache_no_cache"
                       class="components-checkbox-control__input"
					<?php checked( $value ); ?>
                >
                <label class="components-checkbox-control__label" for="ezcache_no_cache">
					<?php echo 'page' == $post_type_object->capability_type ? esc_html__( 'Do not cache this page', 'ezcache' ) : esc_html__( 'Do not cache this post', 'ezcache' ); ?>
                </label>
            </div>
        </div>
		<?php
	}

	/**
	 * Save the meta box settings
	 *
	 * @param $post_id
	 */
	function save_post_cache_metabox( $post_id ) {
		if ( ! isset( $_POST['_eznonce'] ) || ! wp_verify_nonce( $_POST['_eznonce'], 'ezcache_metabox' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['ezcache_no_cache'] ) ) {
			update_post_meta( $post_id, '_ezcache_do_not_cache_post', true );
			Cache::instance()->clear_cache_single( $post_id );
		} else {
			delete_post_meta( $post_id, '_ezcache_do_not_cache_post' );
		}
	}

	/**
	 * Runs when a post is created, updated, or a comment is left on it
	 *
	 * @param int $post_id Post ID
	 * @param array $data Array of unslashed post data
	 */
	function maybe_clear_cache_on_post_update( $post_id, $data ) {
		$settings = Settings::get_settings();

		if ( $settings->cache_clear_on_post_edit ) {
			$this->plugin->ezcache->clear_cache_single( $post_id );

			return;
		}

		if ( $settings->cache_clear_home_on_post_edit ) {
			$this->plugin->ezcache->clear_cache_url( '/' );

			$blog_page_url = $this->plugin->ezcache->get_relative_url( get_post_type_archive_link( 'post' ) );
			$this->plugin->ezcache->clear_cache_url( $blog_page_url );
		}
	}

	/**
	 * Add settings link to the plugin action links
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	function plugin_action_links( $links ) {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=ezcache' ),
			__( 'Settings' )
		);

		return $links;
	}

	/**
	 * Add the menu to the admin bar
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	function add_admin_bar_button( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$referer = rawurlencode( wp_unslash( remove_query_arg( 'fl_builder', $_SERVER['REQUEST_URI'] ) ) );
		$svg     = preg_replace( '/<svg(\s+?)(?:(?:style=[\'"]([\s\S]+?);?[\'"]([\s\S]*?))|([\s\S]*?))>/i', '<svg class="ab-icon" style="height:1em;width:auto;color:#a0a5aa;color:rgba(240,245,250,.6);$2" $3$4>', self::$svg );

		$wp_admin_bar->add_menu( [
			'id'    => 'ezcache',
			'title' => $svg . __( 'ezCache', 'ezcache' ),
			'href'  => admin_url( 'admin.php?page=ezcache' ),
		] );

		$wp_admin_bar->add_menu( [
			'id'     => 'ezcache-settings',
			'parent' => 'ezcache',
			'title'  => __( 'Settings', 'ezcache' ),
			'href'   => admin_url( 'admin.php?page=ezcache' ),
		] );

		$wp_admin_bar->add_menu( [
			'id'     => 'ezcache-clear-cache',
			'parent' => 'ezcache',
			'title'  => __( 'Clear Cache', 'ezcache' ),
			'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=wpb_clear_cache&_wp_http_referer=' . $referer ), 'wpb-clear-cache' ),
		] );
	}

	function register_settings() {
		register_setting( $this->plugin->plugin_settings_key . '_group', $this->plugin->plugin_settings_key );
	}

	/**
	 * Add the menu under the 'Settings' sidebar item
	 */
	function register_menu() {
		global $submenu;

		add_menu_page(
			__( 'ezCache' ),
			__( 'ezCache' ),
			'manage_options',
			'ezcache',
			[ $this, 'settings_page' ],
			'data:image/svg+xml;base64,' . base64_encode( self::$svg )
		);

		$urls = [
			__( 'Statistics', 'ezcache' )        => '/',
			__( 'Settings', 'ezcache' )          => '/settings',
			__( 'Advanced Settings', 'ezcache' ) => '/advanced',
			__( 'License', 'ezcache' )           => '/license',
		];

		if ( ! isset( $submenu['ezcache'] ) ) {
			$submenu['ezcache'] = [];
		}

		foreach ( $urls as $title => $url ) {
			$submenu['ezcache'][] = [ $title, 'manage_options', admin_url( 'admin.php?page=ezcache' ) . '#' . $url ];
		}
	}

	/**
	 * Render the settings page
	 */
	function settings_page() {
		$trans = $this->getJsTraslations();
		?>
        <div class="wrap"><h1 aria-hidden="true"><!-- WordPress notices trap --></h1></div>
        <div id="ezcache-options" class="wrap ezcache-options">
            <!--suppress HtmlUnknownTag -->
            <ezc-options>
                <div class="ezcache-options--preload" aria-hidden="true">
                    <header class="ezcache-header">
                        <h1>
                            <svg viewBox="0 0 360 68" version="1.1" xmlns="http://www.w3.org/2000/svg"
                                 xml:space="preserve" fill-rule="evenodd"
                                 clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" class="logo">
                                <g id="Camada-1" fill-rule="nonzero">
                                    <path d="M51.154 53.752H13.691V37.239h22.056l9.144-12.855h-31.2V13.378H52.72L62.235 0H0v67.338h45.91l5.244-13.586z"
                                          fill="#1e1e1e"></path>
                                    <path d="M72.596 24.521h21.219L53.38 67.338h58.228V54.797H80.976l39.09-41.42H80.389l-7.794 11.144z"
                                          fill="#1e1e1e"></path>
                                    <path d="M57.073 13.378L40.101 37.239h21.603l-12.568 30.1 37.678-39.923H66.051l9.985-14.038H57.073z"
                                          fill="#fc0"></path>
                                    <path d="M57.074 13.378L40.102 37.239h21.602L49.136 67.338l23.038-35.036H52.435l14.12-18.924h-9.481z"
                                          fill="url(#_Linear1)"></path>
                                    <path d="M200.202 47.707h-19.221l9.611-22.993 9.61 22.993zm8.279 19.631h10.645l-23.212-53.965h-10.645l-23.213 53.965h10.572l4.435-10.163h27.056l4.362 10.163z"
                                          fill="gray"></path>
                                    <path d="M313.541 67.338V13.373h-9.684V36.29h-25.43V13.373h-9.758v53.965h9.758v-22.03h25.43v22.03h9.684z"
                                          fill="gray"></path>
                                    <path d="M360 57.728h-29.053V44.865h16.255l3.911-9.092h-20.166V22.835h24.983l4.07-9.461h-38.736v53.964H360v-9.61z"
                                          fill="gray"></path>
                                    <path d="M143.812 13.373h15.746v9.314h-15.746c-12.493 0-17.89 9.315-17.815 17.964.073 8.575 5.026 17.52 17.815 17.52h15.659l-3.943 9.167H143.812c-19.22 0-27.352-13.233-27.426-26.687-.073-13.528 8.723-27.278 27.426-27.278z"
                                          fill="gray"></path>
                                    <path d="M245.201 13.373h15.746v9.314H245.201c-12.493 0-17.89 9.315-17.816 17.964.074 8.575 5.027 17.52 17.816 17.52h15.746v9.167H245.201c-19.221 0-27.352-13.233-27.426-26.687-.074-13.528 8.723-27.278 27.426-27.278"
                                          fill="gray"></path>
                                </g>
                                <defs>
                                    <linearGradient id="_Linear1" x1="0" y1="0" x2="1" y2="0"
                                                    gradientUnits="userSpaceOnUse"
                                                    gradientTransform="matrix(32.072 0 0 -32.072 40.101 40.358)">
                                        <stop offset="0" stop-color="#deb203" stop-opacity="1"></stop>
                                        <stop offset="1" stop-color="#fc0" stop-opacity="1"></stop>
                                    </linearGradient>
                                </defs>
                            </svg>
                        </h1>
                    </header>
                    <div class="row">
                        <div class="col-12 order-first col-sm-4 col-md-3 col-xl-2">
                            <nav class="ezcache-nav">
                                <a href="#" class="nav-item">
									<?php echo str_repeat( '■', mb_strlen( $trans['stats'] ) ); ?>
                                </a>
                                <a href="#" class="nav-item">
									<?php echo str_repeat( '■', mb_strlen( $trans['settings'] ) ); ?>
                                </a>
                                <a href="#" class="nav-item">
									<?php echo str_repeat( '■', mb_strlen( $trans['advanced_settings'] ) ); ?>
                                </a>
                                <a href="#" class="nav-item">
									<?php echo str_repeat( '■', mb_strlen( $trans['license'] ) ); ?>
                                </a>
                            </nav>
                        </div>
                        <div class="col-12 col-sm-4 order-sm-last col-md-3 col-xl-2 mb-4">
                            <div class="ezcache-nav mt-1">
                                <button type="button" class="wpb-button" disabled>
                                    <svg viewBox="0 0 24 24">
                                        <path d="M19,8L15,12H18A6,6 0 0,1 12,18C11,18 10.03,17.75 9.2,17.3L7.74,18.76C8.97,19.54 10.43,20 12,20A8,8 0 0,0 20,12H23M6,12A6,6 0 0,1 12,6C13,6 13.97,6.25 14.8,6.7L16.26,5.24C15.03,4.46 13.57,4 12,4A8,8 0 0,0 4,12H1L5,16L9,12"></path>
                                    </svg>
									<?php echo str_repeat( '■', mb_strlen( $trans['clear_cache'] ) ); ?>
                                </button>
                                <a href="#" target="_blank" class="mt-2 wpb-button wpb-button-outlined disabled">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M12,16A3,3 0 0,1 9,13C9,11.88 9.61,10.9 10.5,10.39L20.21,4.77L14.68,14.35C14.18,15.33 13.17,16 12,16M12,3C13.81,3 15.5,3.5 16.97,4.32L14.87,5.53C14,5.19 13,5 12,5A8,8 0 0,0 4,13C4,15.21 4.89,17.21 6.34,18.65H6.35C6.74,19.04 6.74,19.67 6.35,20.06C5.96,20.45 5.32,20.45 4.93,20.07V20.07C3.12,18.26 2,15.76 2,13A10,10 0 0,1 12,3M22,13C22,15.76 20.88,18.26 19.07,20.07V20.07C18.68,20.45 18.05,20.45 17.66,20.06C17.27,19.67 17.27,19.04 17.66,18.65V18.65C19.11,17.2 20,15.21 20,13C20,12 19.81,11 19.46,10.1L20.67,8C21.5,9.5 22,11.18 22,13Z"></path>
                                    </svg>
									<?php echo str_repeat( '■', mb_strlen( $trans['run_speed_test'] ) ); ?>
                                </a>
                            </div>
                            <div class="side-links">
                                <a href="#" target="_blank" class="side-links-item disabled">
									<?php echo str_repeat( '■', mb_strlen( $trans['documentation'] ) ); ?>
                                </a>
                                <a href="#" target="_blank" class="side-links-item disabled">
									<?php echo str_repeat( '■', mb_strlen( $trans['knowledgebase'] ) ); ?>
                                </a>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4 order-sm-first col-md-6 col-xl-8">
                            <section class="ezcache-main">
                                <div>
                                    <header class="ezcache-screen-header">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        </svg>
										<?php echo str_repeat( '■', mb_strlen( $trans['stats'] ) ); ?>
                                    </header>
                                    <div class="row">
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
                <noscript>
                    <p class="text-center">
                        <strong><?php esc_html_e( 'This application requires JavaScript to run', 'ezcache' ); ?></strong>
                    </p>
                </noscript>
            </ezc-options>
        </div>
		<?php
	}

	/**
	 * Queue up the options page scripts
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		if ( 'toplevel_page_ezcache' != $screen->id ) {
			return;
		}

		$ver = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->plugin->plugin_version;

		wp_enqueue_script( 'ezcache-options', $this->plugin->plugin_url . '/assets/dist/js/options.js', [], $ver, true );
		wp_enqueue_style( 'ezcache-options', $this->plugin->plugin_url . '/assets/dist/css/options.css', [], $ver );

		wp_localize_script( 'ezcache-options', 'ezcache', [
			'assets_url' => esc_url_raw( EZCACHE_URL . '/assets' ),
			'ajax_url'   => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'rest_url'   => esc_url_raw( rest_url() ),
			'ajax_nonce' => wp_create_nonce( 'ezcache-options' ),
			'rest_nonce' => wp_create_nonce( 'wp_rest' ),
			'is_rtl'     => is_rtl(),
			'site_url'   => esc_url_raw( untrailingslashit( site_url() ) ),
			'trans'      => $this->getJsTraslations(),
			'is_https_2' => $this->check_https_2_support(),
			'is_elementor_installed' => is_plugin_active( 'elementor/elementor.php' ),
		] );
	}

	/**
	 * Check if current website supports HTTPS/2
	 *
	 * @return bool
	 */
	protected function check_https_2_support() {
		if ( ! is_ssl() ) {
			return false;
		}

		$supports = get_transient( 'ezcache_https_2_support' );

		if ( ! $supports ) {
			$response = wp_safe_remote_head( home_url(), [
				'httpversion' => '2.0',
			] );

			$supports = false;
			if ( ! is_wp_error( $response ) ) {
				$response = $response['http_response']->get_response_object();
				$supports = $response->protocol_version == 2;
			}

			set_transient( 'ezcache_https_2_support', [ 'has_support' => $supports ], DAY_IN_SECONDS );
		}

		return isset( $supports['has_support'] ) ? $supports['has_support'] : false;
	}

	/**
	 * Get the trnaslations required for the javascript frontend
	 *
	 * @return array
	 */
	protected function getJsTraslations() {
		return [
			'is_rtl' => is_rtl(),

			'select_all'             => __( 'Select All', 'ezcache' ),
			'select_none'            => __( 'Select None', 'ezcache' ),
			'ezcache'                => __( 'ezCache', 'ezcache' ),
			'enabled'                => __( 'Enabled', 'ezcache' ),
			'disabled'               => __( 'Disabled', 'ezcache' ),
			'run_speed_test'         => __( 'Run a Speed Test', 'ezcache' ),
			'save_settings'          => __( 'Save Settings', 'ezcache' ),
			'reset'                  => __( 'Reset', 'ezcache' ),
			'reset_settings'         => __( 'Reset Settings', 'ezcache' ),
			'confirm_reset_settings' => __( 'Are you sure you want to reset the settings to their default values?', 'ezcache' ),
			'settings_saved'         => __( 'Settings updated.', 'ezcache' ),
			'error_saving_settings'  => __( 'Could not save settings.', 'ezcache' ),
			'clear_cache'            => __( 'Clear Cache', 'ezcache' ),
			'cache_cleared'          => __( 'Cache has been cleared.', 'ezcache' ),
			'documentation'          => __( 'Documentation', 'ezcache' ),
			'knowledgebase'          => __( 'Knowledgebase', 'ezcache' ),
			'settings'               => __( 'Settings', 'ezcache' ),
			'advanced_settings'      => __( 'Advanced Settings', 'ezcache' ),
			'stats'                  => __( 'Statistics', 'ezcache' ),
			'license'                => __( 'License', 'ezcache' ),
			'recommended'            => __( 'Recommended', 'ezcache' ),
			'basic_settings'         => __( 'Basic Settings', 'ezcache' ),
			'confirm'                => __( 'OK', 'ezcache' ),
			'cancel'                 => __( 'Cancel', 'ezcache' ),
			'delete'                 => __( 'Delete', 'ezcache' ),

			'no_cache_known_users'                      => __( 'Don\'t cache pages for known users', 'ezcache' ),
			'no_cache_known_users_description'          => __( 'This disables cache for logged in users.', 'ezcache' ),
			'no_cache_known_users_admin_bar_notice'     => __( 'The admin bar will be hidden for all users to prevent it being saved into the cache.', 'ezcache' ),
			'no_cache_comment_authors'                  => __( 'Don\'t cache pages for comment authors', 'ezcache' ),
			'no_cache_comment_authors_description'      => __( 'Do not save cache for users who saved their name and email for the next time they comment.', 'ezcache' ),
			'cache_expiry'                              => __( 'Cache Expiry', 'ezcache' ),
			'cache_lifetime'                            => __( 'Cache Timeout', 'ezcache' ),
			'cache_lifetime_description'                => __( 'How long to keep the cached data, the recommended and effective starting point is one day.', 'ezcache' ),
			'cache_expiry_interval'                     => __( 'Check for stale cache every', 'ezcache' ),
			'cache_expiry_interval_description'         => __( 'Automatically check for stale cache files and delete them at a set interval.', 'ezcache' ),
			'interval'                                  => __( 'Interval', 'ezcache' ),
			'every'                                     => __( 'Every', 'ezcache' ),
			'seconds'                                   => __( 'Seconds', 'ezcache' ),
			'cache_schedule_type_time'                  => __( 'At Time', 'ezcache' ),
			'cache_schedule_interval'                   => __( 'Interval', 'ezcache' ),
			'weekly'                                    => __( 'Weekly', 'ezcache' ),
			'daily'                                     => __( 'Daily', 'ezcache' ),
			'twicedaily'                                => __( 'Twice Daily', 'ezcache' ),
			'hourly'                                    => __( 'Hourly', 'ezcache' ),
			'at'                                        => __( 'At', 'ezcache' ),
			'cache_bypass'                              => __( 'Cache Bypass', 'ezcache' ),
			'no_cache_query_params'                     => __( 'Don\'t cache pages where the URLs contain parameters in the query string', 'ezcache' ),
			'no_cache_query_params_description'         => __( 'Don\'t save cache when the URL contains query string parameters, such as ?page_id=15 at the end of the URL.', 'ezcache' ),
			'separate_mobile_cache'                     => __( 'Separate cache for mobile devices', 'ezcache' ),
			'separate_mobile_cache_description'         => __( 'Save separate cache files for mobile browsers and a separate file for desktop browsers, you should only enable this option if your theme is not responsive and you use a theme or a plugin that produces a separate mobile version of the website.', 'ezcache' ),
			'cache_clear_on_post_edit'                  => __( 'Clear cache when a post or page is published or updated', 'ezcache' ),
			'cache_clear_on_post_edit_description'      => __( 'Keep your posts up to date even when they are updated by clearing their cache.', 'ezcache' ),
			'cache_clear_home_on_post_edit'             => __( 'Clear homepage cache when a post or page is published or updated', 'ezcache' ),
			'cache_clear_home_on_post_edit_description' => __( 'Make sure your visitors read the latest posts by clearing the cache when you update or publish a post.', 'ezcache' ),
			'bypass_cache_title'                        => __( 'Disable caching for the following pages', 'ezcache' ),
			'bypass_cache_single'                       => __( 'Single Posts', 'ezcache' ),
			'bypass_cache_pages'                        => __( 'Pages', 'ezcache' ),
			'bypass_cache_frontpage'                    => __( 'Front Page', 'ezcache' ),
			'bypass_cache_home'                         => __( 'Home', 'ezcache' ),
			'bypass_cache_archives'                     => __( 'Archives', 'ezcache' ),
			'bypass_cache_tag'                          => __( 'Tags', 'ezcache' ),
			'bypass_cache_category'                     => __( 'Categories', 'ezcache' ),
			'bypass_cache_feed'                         => __( 'Feeds', 'ezcache' ),
			'bypass_cache_search'                       => __( 'Search Results', 'ezcache' ),
			'bypass_cache_author'                       => __( 'Author Pages', 'ezcache' ),
			'rejected_uri'                              => __( 'Links (URLs) which will never get cached', 'ezcache' ),
			'rejected_uri_description'                  => __( 'Do not serve cached content if the URL matches any link in the following list', 'ezcache' ),
			'rejected_uri_wildcard'                     => __( 'The domain part of the URL will be stripped automatically. Use * wildcard character to match multiple characters at this position (eg. /product/*).', 'ezcache' ),
			'rejected_user_agent'                       => __( 'User Agents which will never receive cached data', 'ezcache' ),
			'rejected_user_agent_description'           => __( 'Do not serve cached content to the following useragents', 'ezcache' ),
			'rejected_cookies'                          => __( 'Cookies which will prevent pages from getting cached', 'ezcache' ),
			'rejected_cookies_description'              => __( 'Specify the cookies that when set in the visitor\'s browser, should prevent a page from getting cached (one per line)', 'ezcache' ),
			'rejected_cookies_placeholder'              => _x( 'wordpress_logged_in_', 'rejected cookies example', 'ezcache' ),
			'n_minutes'                                 => __( '%s Minutes', 'ezcache' ),
			'n_hours'                                   => __( '%s Hours', 'ezcache' ),
			'n_days'                                    => __( '%s Days', 'ezcache' ),
			'never_expire'                              => __( 'Never Expire', 'ezcache' ),
			'cache_disabled'                            => __( 'WP_CACHE is disabled in your wp-config.php, caching will not work.', 'ezcache' ),
			'adv_cache_not_exists'                      => __( 'advanced-cache.php is missing from your wp-content folder, caching will not work.', 'ezcache' ),
			'plugin_badly_installed'                    => __( 'Plugin is not installed properly, please reinstall, caching will not work.', 'ezcache' ),
			'desktop'                                   => _x( 'Desktop', 'statistics block title', 'ezcache' ),
			'mobile'                                    => _x( 'Mobile', 'statistics block title', 'ezcache' ),
			'expired'                                   => _x( 'Expire Cache', 'statistics block title', 'ezcache' ),
			'javascript'                                => _x( 'JavaScript Files', 'statistics block title', 'ezcache' ),
			'css'                                       => _x( 'CSS Files', 'statistics block title', 'ezcache' ),
			'webp_images'                               => _x( 'WebP Images', 'statistics block title', 'ezcache' ),
			'cache_usage'                               => __( 'ezCache Cache Usage', 'ezcache' ),
			'webp_stats_description'                    => __( 'Images optimized with WebP.', 'ezcache' ),
			'cache_bypass_settings'                     => _x( 'Cache Bypass', 'settings block title', 'ezcache' ),
			'cache_settings'                            => _x( 'Caching', 'settings block title', 'ezcache' ),
			'performance_settings'                      => _x( 'Performance', 'settings block title', 'ezcache' ),
			'cache_expiry_settings'                     => _x( 'Cache Expiration', 'settings block title', 'ezcache' ),
			'optimize_google_fonts'                     => __( 'Optimize Google fonts', 'ezcache' ),
			'optimize_google_fonts_description'         => __( 'Combine multiple Google Fonts declerations into one.', 'ezcache' ),
			'minify_html'                               => __( 'Minify HTML', 'ezcache' ),
			'minify_html_description'                   => __( 'Minify the cached HTML to make cached files smaller.', 'ezcache' ),
			'minify_inline_js'                          => __( 'Minify Inline JavaScript', 'ezcache' ),
			'minify_inline_js_description'              => __( 'Include JavaScript embedded in the HTML in the minification process.', 'ezcache' ),
			'minify_inline_css'                         => __( 'Minify Inline CSS', 'ezcache' ),
			'minify_inline_css_description'             => __( 'Include CSS embedded in the HTML in the minification process.', 'ezcache' ),
			'minify_js'                                 => __( 'Minify JavaScript', 'ezcache' ),
			'minify_js_description'                     => __( 'Minify JavaScript files to reduce their size.', 'ezcache' ),
			'minify_html_comments'                      => __( 'Remove HTML comments', 'ezcache' ),
			'minify_html_comments_description'          => __( 'Remove embeded comments from minified HTML.', 'ezcache' ),
			'combine_head_js'                           => __( 'Combine JavaScript in Head', 'ezcache' ),
			'combine_head_js_description'               => __( 'Combine multiple JavaScript files found in the head section of the HTML into one file.', 'ezcache' ),
			'combine_body_js'                           => __( 'Combine JavaScript in Body', 'ezcache' ),
			'combine_body_js_description'               => __( 'Combine multiple JavaScript files found in the body section of the HTML into one file.', 'ezcache' ),
			'combine_head_inline_js'                    => __( 'Combine Inline JavaScript in Head', 'ezcache' ),
			'combine_head_inline_js_description'        => __( 'Combine JavaScript scripts found in the head section of the HTML into the combined JS file.', 'ezcache' ),
			'combine_body_inline_js'                    => __( 'Combine Inline JavaScript in Body', 'ezcache' ),
			'combine_body_inline_js_description'        => __( 'Combine JavaScript scripts found in the body section of the HTML into the combined JS file.', 'ezcache' ),
			'combine_js_elementor_notice'               => __( 'Elementor generates a unique JS file for each post/page which can cause the cache to use large amounts of disk space, to fix this make sure to exclude Elementor JS files (`elementor/js/post-*`) from optimizations.', 'ezcache' ),
			'minify_css'                                => __( 'Minify CSS', 'ezcache' ),
			'minify_css_description'                    => __( 'Minify CSS files to reduce their size.', 'ezcache' ),
			'combine_css'                               => __( 'Combine CSS', 'ezcache' ),
			'combine_css_description'                   => __( 'Combine multiple CSS files into one to reduce HTTP requests.', 'ezcache' ),
			'combine_css_elementor_notice'              => __( 'Elementor generates a unique CSS file for each post/page which can cause the cache to use large amounts of disk space, to fix this make sure to exclude Elementor CSS files (`elementor/css/post-*`) from optimizations.', 'ezcache' ),
			'disable_wp_emoji'                          => __( 'Disable WordPress Emoji', 'ezcache' ),
			'disable_wp_emoji_description'              => __( 'Remove extra code related to Emoji from WordPress which was added recently to support Emoji in an older browsers.', 'ezcache' ),
			'not_recommended_https2'                    => __( 'Your server supports HTTP/2 which benefits from having this option kept disabled', 'ezcache' ),
			'enable_webp_support'                       => __( 'Optimize Images With WebP', 'ezcache' ),
			/* xgettext:no-php-format */
			'enable_webp_support_description'           => __( 'WebP is a modern image format that provides superior lossless and lossy compression for images on the web. WebP lossless images are 26% smaller in size compared to PNGs, and 25-34% smaller than comparable JPEG images.', 'ezcache' ),
			'requries_premium_license'                  => __( 'Requires a premium license', 'ezcache' ),
			'rejected_user_agent_placeholder'           => _x( 'Googlebot', 'user agent example', 'ezcache' ),
			'rejected_uri_placeholder'                  => _x( '/products/*', 'rejected uri example', 'ezcache' ),
			'css_stats_description'                     => __( 'Optimized and cached CSS files.', 'ezcache' ),
			'javascript_stats_description'              => __( 'Optimized and cached JavaScript files.', 'ezcache' ),
			'expired_stats_description'                 => __( 'Cache data that has expired and waiting to be cleaned up.', 'ezcache' ),
			'mobile_stats_description'                  => __( 'Cache data for pages viewed from mobile devices.', 'ezcache' ),
			'desktop_stats_description'                 => __( 'Cache data for pages viewed from desktop computers.', 'ezcache' ),
			'general_advanced_settings'                 => __( 'General', 'ezcache' ),

			'combine_css_footer'                             => __( 'Move combined CSS to footer', 'ezcache' ),
			'combine_css_footer_description'                 => __( 'Eliminate render blocking CSS by moving the combined CSS file to the footer section.', 'ezcache' ),
			'critical_css'                                   => __( 'Critical CSS', 'ezcache' ),
			'critical_css_description'                       => __( "Critical CSS is the minimum set of blocking CSS required to render the first screen's worth of content to the user in order to render content to the user as fast as possible.\nThe Critical CSS can (and should) be manually created to fit the website perfectly, however there are tools that can automatically generate the Critical CSS for you.", 'ezcache' ),
			'critical_css_more_information'                  => __( 'More information about Critical CSS', 'ezcache' ),
			'critical_css_online_tool'                       => __( 'Online Critical CSS Generator', 'ezcache' ),
			'combine_css_footer_requires_combine_css_notice' => __( 'Moving combined CSS to footer requires the Combine CSS option enabled.', 'ezcache' ),
			'excluded_minify_files'                          => __( 'Exclude JS/CSS files from optimization', 'ezcache' ),
			'excluded_minify_files_description'              => __( 'List filenames or paths to files which should be excluded from minification or combining.', 'ezcache' ),
			'excluded_minify_files_placeholder'              => __( "jquery.js\nrecaptcha/api.js\ngoogleadservices.com", 'ezcache' ),

			'advanced_tools' => __( 'Advanced Tools', 'ezcache' ),
			'delete_webp_images' => __( 'Clear WebP Images Cache', 'ezcache' ),
			'confirm_delete_webp_images' => __( "Are you sure that you want to delete all of the cached WebP images?\nTheses images will have to be converted again which may take some time.", 'ezcache' ),
			'error_delete_webp_images' => __( "Error clearing WebP image cache", 'ezcache' ),
			'schedule_webp_images_process_started' => __( "Scheduled task queued", 'ezcache' ),
			'error_schedule_webp_images_process' => __( "Error scheduling WebP process", 'ezcache' ),
			'schedule_webp_images_process' => __( "Re-Schedule WebP Process Scheduled Task", 'ezcache' ),

			'license_key'                   => __( 'License Key', 'ezcache' ),
			'license_key_description'       => __( 'ezCache pro license will allow you to use the WebP image optimization without limits.', 'ezcache' ),
			'deactivate_license'            => __( 'Deactivate License', 'ezcache' ),
			'activate_license'              => __( 'Activate License', 'ezcache' ),
			'license_valid'                 => __( 'License is valid', 'ezcache' ),
			'license_invalid'               => __( 'License is invalid', 'ezcache' ),
			'license_expires_at'            => __( 'License expires at %s', 'ezcache' ),
			'unknown'                       => __( 'Unknown', 'ezcache' ),
			'license_status'                => __( 'Status', 'ezcache' ),
			'license_details'               => __( 'License Details', 'ezcache' ),
			'license_type'                  => __( 'License Type', 'ezcache' ),
			'trial_license'                 => _x( 'Trial', 'license type', 'ezcache' ),
			'regular_license'               => _x( 'Pro', 'license type', 'ezcache' ),
			'expires_in'                    => __( 'Expires In', 'ezcache' ),
			'expires_at'                    => __( 'Expires At', 'ezcache' ),
			'uses_left'                     => __( 'Convertions Left', 'ezcache' ),
			'trial_not_started'             => __( '%s days after first usage', 'ezcache' ),
			'purchase_license'              => __( 'Get a Pro License', 'ezcache' ),
			'license_expired'               => __( 'Expired', 'ezcache' ),
			'no_expiry_while_upress_client' => __( 'Will not expire while hosted on uPress', 'ezcache' ),

			'upress_ad'                  => __( 'uPress Premium WordPress Hosting', 'ezcache' ),
			'upress_domain'              => __( 'www.upress.io', 'ezcache' ),
			'upress_ad_link'             => __( 'https://www.upress.io/?utm_source=wordpress&utm_medium=cpc&utm_campaign=ezcache', 'ezcache' ),
			'speed_test_url'             => __( 'https://speedom.net/?url=%s&location=US-NY&utm_source=wordpress&utm_medium=cpc&utm_campaign=ezcache', 'ezcache' ),
			'ezcache_docs_link'          => __( 'https://ezcache.app/documentation?utm_source=wordpress&utm_medium=cpc&utm_campaign=ezcache', 'ezcache' ),
			'ezcache_knowledgebase_link' => __( 'https://ezcache.app/knowledgebase?utm_source=wordpress&utm_medium=cpc&utm_campaign=ezcache', 'ezcache' ),
			'ezcache_pricing_link'       => __( 'https://ezcache.app/pricing?utm_source=wordpress&utm_medium=cpc&utm_campaign=ezcache', 'ezcache' ),
		];
	}

	/**
	 * Show cache status admin notices when needed
	 */
	function maybe_show_advanced_cache_notice() {
		$webp_queue = get_site_option( 'ezcache_convert_images_to_webp_reprocess_queue' );
		if ( ! $webp_queue ) {
			$webp_queue = [];
		}
		$count_pending = array_reduce( $webp_queue, function ( $count, $images ) {
			return $count + count( $images );
		}, 0 );

		if ( $count_pending > 0 ) {
			echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'ezCache', 'ezcache' ) . ':</strong> ' . sprintf( esc_html__( 'WebP Processing is currently running, %d images remaining.', 'ezcache' ), $count_pending ) . '</p></div>';
		}

		if ( ! get_site_option( 'ezcache_first_run', false ) ) {
			update_site_option( 'ezcache_first_run', 1 );
		}
	}

	function admin_clear_cache() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'wpb-clear-cache' ) ) {
			wp_nonce_ays( 'wpb-clear-cache' );
		}

		$this->plugin->ezcache->clear_cache();

		wp_redirect( wp_get_referer() );
		exit;
	}

	function maybe_show_admin_bar( $show_admin_bar ) {
		$settings = Settings::get_settings();

		if ( ! $settings->no_cache_known_users ) {
			return false;
		}

		return $show_admin_bar;
	}
}
