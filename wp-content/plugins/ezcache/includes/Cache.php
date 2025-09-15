<?php

namespace Upress\EzCache;

use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use UnexpectedValueException;
use Upress\EzCache\BackgroundProcesses\ConvertWebpProcess;
use Upress\EzCache\FileOptimizer\CombineGoogleFonts;
use Upress\EzCache\FileOptimizer\CssMinifier;
use Upress\EzCache\FileOptimizer\CssCombiner;
use Upress\EzCache\FileOptimizer\JsMinifier;
use Upress\EzCache\FileOptimizer\JsCombiner;
use Upress\EzCache\FileOptimizer\WebpConverter;
use Upress\EzCache\ThirdParty\Minify_HTML;
use Upress\EzCache\Utilities\Logger;

class Cache {
	protected static $instance;
	protected $settings;
	protected $cache_start_time;
	protected $webp_processor;
	protected $root_cache_dir = WP_CONTENT_DIR . '/cache/ezcache/';

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->settings         = Settings::get_settings();
		$this->webp_processor   = new ConvertWebpProcess();
		$this->cache_start_time = microtime( true );
	}

	/**
	 * @return string
	 */
	public function get_default_cache_path() {
		$hostname = preg_replace( '/:.*$/', '', $this->get_http_host() );

		return $this->root_cache_dir . $hostname . '/';
	}

	/**
	 * Get the HTTP host
	 *
	 * @return string
	 */
	public function get_http_host() {
		if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
			$host = function_exists( 'mb_strtolower' ) ? mb_strtolower( $_SERVER['HTTP_HOST'] ) : strtolower( $_SERVER['HTTP_HOST'] );

			return htmlentities( $host );
		} elseif ( function_exists( 'get_option' ) ) {
			return (string) parse_url( get_option( 'home' ), PHP_URL_HOST );
		}

		return '';
	}

	/**
	 * Check if the current user has a log in cookie set (ie. the user is logged in)
	 * @return bool
	 */
	public function has_login_cookie() {
		$cookiehash = '';
		if ( defined( 'COOKIEHASH' ) ) {
			$cookiehash = preg_quote( constant( 'COOKIEHASH' ), '|' );
		}

		$regex = "|^wordpress_logged_in_{$cookiehash}|";
		if ( defined( 'LOGGED_IN_COOKIE' ) ) {
			$regex = "|^" . preg_quote( constant( 'LOGGED_IN_COOKIE' ), '|' ) . '|';
		}

		foreach ( $_COOKIE as $key => $value ) {
			if ( preg_match( $regex, $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a cookie is set to show that the current user has left comments and saved their data
	 * @return bool
	 */
	public function has_comment_author_cookie() {
		$cookiehash = '';
		if ( defined( 'COOKIEHASH' ) ) {
			$cookiehash = preg_quote( constant( 'COOKIEHASH' ) );
		}

		$regex = "/^wp-postpass_{$cookiehash}|^comment_author_{$cookiehash}/";

		foreach ( $_COOKIE as $key => $value ) {
			if ( preg_match( $regex, $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the request supports gzip compression
	 *
	 * @return bool
	 */
	public function gzip_accepted() {
		if ( defined( 'EZCACHE_DISABLE_GZIP' ) && EZCACHE_DISABLE_GZIP ) {
			return false;
		}

		return isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) && false !== strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' );
	}

	/**
	 * Check if the request supports webp images
	 *
	 * @return bool
	 */
	public function webp_accepted() {
		return isset( $_SERVER['HTTP_ACCEPT'] ) && false !== strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' );
	}

	/**
	 * Check if the request comes from the backend
	 *
	 * @return bool
	 */
	public function is_backend() {
		if ( is_admin() ) {
			return true;
		}

		$script = isset( $_SERVER['PHP_SELF'] ) ? basename( $_SERVER['PHP_SELF'] ) : '';
		if ( $script !== 'index.php' ) {
			if ( in_array( $script, [ 'wp-login.php', 'xmlrpc.php', 'wp-cron.php' ] ) ) {
				return true;
			} elseif ( defined( 'DOING_CRON' ) && DOING_CRON ) {
				return true;
			} elseif ( PHP_SAPI == 'cli' || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return the relative URL based on the url provided or false if the url is not on this website
	 * @param string $url
	 *
	 * @return string|bool
	 */
	public function get_relative_url( $url ) {
		$site_url  = site_url();
		if ( false === strpos( $url, $site_url ) ) {
			if ( preg_match( '`^(https?:)?//([^/]+)(/.*)?$`i', $url, $matches ) ) {
				$url = isset( $matches[3] ) ? $matches[3] : '';
			}
		} else {
			$url = str_replace( $site_url, '', $url );
			if ( 0 !== strpos( $url, '/' ) ) {
				$url = '/' . $url;
			}
		}

		if ( preg_match( '/^https?:\/\//i', $url ) ) {
			return false;
		}

		return $url;
	}

	/**
	 * Should we serve the cached file
	 *
	 * @return bool
	 */
	public function should_serve_cached_data() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( defined( 'JSON_REQUEST' ) && JSON_REQUEST ) {
			return false;
		}
		if ( defined( 'WC_API_REQUEST' ) && WC_API_REQUEST ) {
			return false;
		}
		if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) {
			return false;
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}
		if ( defined( 'WP_USE_THEMES' ) && false === WP_USE_THEMES ) {
			return false;
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || ( isset( $_SERVER['REQUEST_METHOD'] ) && in_array( $_SERVER['REQUEST_METHOD'], [
					'HEAD',
					'POST',
					'PUT',
					'PATCH',
					'DELETE',
				] ) ) || isset( $_GET['customize_changeset_uuid'] ) || isset( $_POST['wp_customize'] ) ) {
			return false;
		}

		$settings = $this->settings;

		if ( $settings->no_cache_known_users && $this->has_login_cookie() ) {
			return false;
		}

		if ( $settings->no_cache_comment_authors && $this->has_comment_author_cookie() ) {
			return false;
		}

		if ( $this->is_backend() ) {
			return false;
		}

		// Don't cache with variables but the cache is enabled if the visitor comes from an RSS feed, a Facebook action or Google Adsense tracking
		if (
			(
				$settings->no_cache_query_params
				&& ! empty( $_GET )
				&& ! (
					isset( $_GET['utm_source'], $_GET['utm_medium'], $_GET['utm_campaign'] )
					|| isset( $_GET['utm_expid'] )
					|| isset( $_GET['fb_action_ids'], $_GET['fb_action_types'], $_GET['fb_source'] )
					|| isset( $_GET['gclid'] )
				)
			) || (
				isset( $_GET['permalink_name'] )
				|| isset( $_GET['lp-variation-id'] )
				|| isset( $_GET['lang'] )
				|| isset( $_GET['s'] )
				|| isset( $_GET['age-verified'] )
				|| isset( $_GET['ao_noptimize'] )
				|| isset( $_GET['usqp'] )
				|| isset( $_GET['woo_ajax'] )
			)
		) {

			return false;
		}

		// Don't cache pages where the rejected cookies are defined
		if ( ! empty( $settings->rejected_cookies ) ) {
			$rejected_cookies = preg_split( "/\\r\\n|\\r|\\n/u", trim( $settings->rejected_cookies ), - 1, PREG_SPLIT_NO_EMPTY );
			$rejected_cookies = array_filter( $rejected_cookies );

			if ( preg_match( '#(' . implode( '|', $rejected_cookies ) . ')#', var_export( $_COOKIE, true ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Should we save the cache files.
	 * Most of the checks here have to be run after the page was rendered as they require WordPress.
	 *
	 * @return bool
	 */
	public function should_save_cache() {
		global $wp_query;

		if ( ! $this->should_serve_cached_data() ) {
			return false;
		}

		$settings = $this->settings;

		// check if we have any errors or otherwise settings preventing caching
		$error = error_get_last();
		if ( null !== $error && ( $error['type'] & ( E_ERROR | E_CORE_ERROR | E_PARSE | E_COMPILE_ERROR | E_USER_ERROR ) ) ) {
			return false;
		}

		if ( function_exists( 'http_response_code' ) && http_response_code() > 300 ) {
			return false;
		}

		if ( is_404() ) {
			return false;
		}

		if ( $settings->bypass_cache->single && is_single() ) {
			return false;
		}
		if ( $settings->bypass_cache->pages && is_page() ) {
			return false;
		}
		if ( $settings->bypass_cache->frontpage && is_front_page() ) {
			return false;
		}
		if ( $settings->bypass_cache->home && is_home() ) {
			return false;
		}
		if ( $settings->bypass_cache->archives && is_archive() ) {
			return false;
		}
		if ( $settings->bypass_cache->tag && is_tag() ) {
			return false;
		}
		if ( $settings->bypass_cache->category && is_category() ) {
			return false;
		}
		if ( $settings->bypass_cache->feed && is_feed() ) {
			return false;
		}
		if ( $settings->bypass_cache->search && is_search() ) {
			return false;
		}
		if ( $settings->bypass_cache->author && is_author() ) {
			return false;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return false;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return false;
		}

		if ( is_null( $wp_query ) || is_robots() || get_query_var( 'sitemap' ) || get_query_var( 'xsl' ) || get_query_var( 'xml_sitemap' ) ) {
			return false;
		}

		if ( isset( $_GET['preview'] ) || isset( $_POST['wp_customize'] ) ) {
			return false;
		}

		if ( get_post_meta( get_the_ID(), '_ezcache_do_not_cache_post', true ) ) {
			return false;
		}

		// check useragent
		$rejected_useragents = preg_split( "/\\r\\n|\\r|\\n/u", trim( $settings->rejected_user_agent ), - 1, PREG_SPLIT_NO_EMPTY );
		$rejected_useragents = array_filter( $rejected_useragents );
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			foreach ( $rejected_useragents as $ua ) {
				if ( empty( $ua ) ) {
					continue;
				}

				if ( false !== strpos( $_SERVER['HTTP_USER_AGENT'], trim( $ua ) ) ) {
					return false;
				}
			}
		}

		// check URL
		$rejected_uris = preg_split( "/\\r\\n|\\r|\\n/u", trim( $settings->rejected_uri ), - 1, PREG_SPLIT_NO_EMPTY );
		$rejected_uris = array_filter( $rejected_uris );
		$domain        = untrailingslashit( home_url() );
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			foreach ( $rejected_uris as $url ) {
				$url = str_replace( $domain, '', $url );
				$url = '/' . trim( $url, '/' );
				$url = str_replace( [ '\/*', '*' ], [ '\/?.*?', '.*?' ], preg_quote( $url, '/' ) );
				$url = str_replace( '\/\.*?', '\/.*?', $url );
				if ( @preg_match( "/^{$url}\/?$/u", urldecode( $_SERVER['REQUEST_URI'] ) ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Get the mobile browser name
	 *
	 * @return string
	 */
	public function detect_mobile() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		$mobile_browsers = apply_filters( 'ezcache_mobile_browsers', [
			'2.0 MMP',
			'240x320',
			'400X240',
			'AvantGo',
			'BlackBerry',
			'Blazer',
			'Cellphone',
			'Danger',
			'DoCoMo',
			'Elaine/3.0',
			'EudoraWeb',
			'Googlebot-Mobile',
			'hiptop',
			'IEMobile',
			'KYOCERA/WX310K',
			'LG/U990',
			'MIDP-2.',
			'MMEF20',
			'MOT-V',
			'NetFront',
			'Newt',
			'Nintendo Wii',
			'Nitro',
			'Nokia',
			'Opera Mini',
			'Palm',
			'PlayStation Portable',
			'portalmmm',
			'Proxinet',
			'ProxiNet',
			'SHARP-TQ-GX10',
			'SHG-i900',
			'Small',
			'SonyEricsson',
			'Symbian OS',
			'SymbianOS',
			'TS21i-10',
			'UP.Browser',
			'UP.Link',
			'webOS',
			'Windows CE',
			'WinWAP',
			'YahooSeeker/M1A1-R2D2',
			'iPhone',
			'iPod',
			'iPad',
			'Android',
			'BlackBerry9530',
			'LG-TU915 Obigo',
			'LGE VX',
			'webOS',
			'Nokia5800',
		] );
		$user_agent      = strtolower( $_SERVER['HTTP_USER_AGENT'] );
		foreach ( $mobile_browsers as $browser ) {
			if ( strstr( $user_agent, trim( strtolower( $browser ) ) ) ) {
				return $user_agent;
			}
		}

		if ( isset( $_SERVER['HTTP_X_WAP_PROFILE'] ) ) {
			return $_SERVER['HTTP_X_WAP_PROFILE'];
		}

		if ( isset( $_SERVER['HTTP_PROFILE'] ) ) {
			return $_SERVER['HTTP_PROFILE'];
		}

		$browser_prefixes = apply_filters( 'ezcache_mobile_browser_prefixes', [
			'w3c',
			'w3c-',
			'acs-',
			'alav',
			'alca',
			'amoi',
			'audi',
			'avan',
			'benq',
			'bird',
			'blac',
			'blaz',
			'brew',
			'cell',
			'cldc',
			'cmd-',
			'dang',
			'doco',
			'eric',
			'hipt',
			'htc_',
			'inno',
			'ipaq',
			'ipod',
			'jigs',
			'kddi',
			'keji',
			'leno',
			'lg-c',
			'lg-d',
			'lg-g',
			'lge-',
			'lg/u',
			'maui',
			'maxo',
			'midp',
			'mits',
			'mmef',
			'mobi',
			'mot-',
			'moto',
			'mwbp',
			'nec-',
			'newt',
			'noki',
			'palm',
			'pana',
			'pant',
			'phil',
			'play',
			'port',
			'prox',
			'qwap',
			'sage',
			'sams',
			'sany',
			'sch-',
			'sec-',
			'send',
			'seri',
			'sgh-',
			'shar',
			'sie-',
			'siem',
			'smal',
			'smar',
			'sony',
			'sph-',
			'symb',
			't-mo',
			'teli',
			'tim-',
			'tosh',
			'tsm-',
			'upg1',
			'upsi',
			'vk-v',
			'voda',
			'wap-',
			'wapa',
			'wapi',
			'wapp',
			'wapr',
			'webc',
			'winw',
			'winw',
			'xda',
			'xda-',
		] );
		foreach ( $browser_prefixes as $prefix ) {
			if ( substr( $user_agent, 0, 4 ) == $prefix ) {
				return $prefix;
			}
		}

		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? strtolower( $_SERVER['HTTP_ACCEPT'] ) : '';
		if ( strpos( $accept, 'wap' ) !== false ) {
			return 'wap';
		}

		if ( isset( $_SERVER['ALL_HTTP'] ) && false !== strpos( strtolower( $_SERVER['ALL_HTTP'] ), 'operamini' ) ) {
			return 'operamini';
		}

		return '';
	}

	/**
	 * Search & replace in a string
	 *
	 * @param string|string[] $search
	 * @param string $subject
	 *
	 * @return string
	 */
	public function deep_replace( $search, $subject ) {
		$subject = (string) $subject;

		$count = 1;
		while ( $count ) {
			$subject = str_replace( $search, '', $subject, $count );
		}

		return $subject;
	}

	/**
	 * Get the cache directory URL for the current post
	 *
	 * @param int $post_id
	 *
	 * @param null|string $url
	 *
	 * @return mixed|string
	 */
	public function get_current_url_cache_dir( $post_id = 0, $url = null ) {
		static $url_cache_dir = [];

		if ( isset( $url_cache_dir[ $post_id ] ) ) {
			return $url_cache_dir[ $post_id ];
		}

		$uri = strtolower( $url ? ( '/' . ltrim( $url, '/' ) ) : $_SERVER['REQUEST_URI'] );

		$DONOTREMEMBER = 0;
		if ( 0 !== $post_id ) {
			$site_url  = site_url();
			$permalink = get_permalink( $post_id );
			if ( false === strpos( $permalink, $site_url ) ) {
				$DONOTREMEMBER = 1;
				if ( preg_match( '`^(https?:)?//([^/]+)(/.*)?$`i', $permalink, $matches ) ) {
					$uri = isset( $matches[3] ) ? $matches[3] : '';
				} elseif ( preg_match( '`^/([^/]+)(/.*)?$`i', $permalink, $matches ) ) {
					$uri = $permalink;
				} else {
					$uri = '';
				}
			} else {
				$uri = str_replace( $site_url, '', $permalink );
				if ( 0 !== strpos( $uri, '/' ) ) {
					$uri = '/' . $uri;
				}
			}
		}

		$uri = $this->deep_replace(
			[
				'..',
				'\\',
				'index.php',
			],
			preg_replace(
				'/[ <>\'\"\r\n\t()]/',
				'',
				preg_replace( "/(\?.*)?(#.*)?$/", '', $uri )
			)
		);

		$uri = md5( $uri );
		$dir = str_replace( '..', '', str_replace( '//', '/', $uri . '/' ) );

		if ( $DONOTREMEMBER == 0 ) {
			$url_cache_dir[ $post_id ] = $dir;
		}

		return $dir;
	}

	/**
	 * Get the cache directory path
	 *
	 * @param int $postid
	 *
	 * @param null|string $url
	 *
	 * @return string
	 */
	public function get_real_cache_dir( $postid = 0, $url = null ) {
		return $this->get_default_cache_path() . $this->get_current_url_cache_dir( $postid, $url );
	}

	/**
	 * Get the full cache file path
	 *
	 * @param int $postid
	 *
	 * @param null|string $url
	 *
	 * @return string
	 */
	public function get_cache_file_path( $postid = 0, $url = null ) {
		return $this->get_real_cache_dir( $postid, $url ) . $this->get_cache_filename();
	}

	/**
	 * Get the filename for the cached file
	 *
	 * @return string
	 */
	public function get_cache_filename() {
		$settings = $this->settings;

		// Add support for https and http caching
		// also supports https requests coming from an nginx reverse proxy
		$is_https  = ( ( isset( $_SERVER['HTTPS'] ) && 'on' == strtolower( $_SERVER['HTTPS'] ) ) || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' == strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) );
		$extra_str = $is_https ? '-https' : '';

		if ( $settings->separate_mobile_cache ) {
			$mobile_ua = $this->detect_mobile();
			if ( ! empty( $mobile_ua ) ) {
				$extra_str .= '-mobile';
			}
		}

		if ( $settings->enable_webp_support && $this->webp_accepted() ) {
			$extra_str .= '-webp';
		}

		$filename = 'index';
		if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
			$filename = md5( $_SERVER['QUERY_STRING'] );
		}

		return $filename . $extra_str . '.html';
	}

	/**
	 * Check if we have a cached file and serve it
	 */
	public function maybe_serve_cached_data() {
		if ( ! $this->should_serve_cached_data() ) {
			return;
		}

		$cache_file    = $this->get_cache_file_path();
		$gzip_accepted = $this->gzip_accepted();

		$cache_file = $cache_file . '.gz';
		$filesize   = file_exists( $cache_file ) ? @filesize( $cache_file ) : false;

		if ( ! $filesize ) {
			// the file is empty, we have nothing to serve
			return;
		}

		header( "X-Cached-With: ezCache" );
		header( "Vary: Accept-Encoding, Cookie" );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $cache_file ) ) . ' GMT' );

		// Getting If-Modified-Since headers sent by the client.
		if ( function_exists( 'apache_request_headers' ) ) {
			$headers                = apache_request_headers();
			$http_if_modified_since = ( isset( $headers['If-Modified-Since'] ) ) ? $headers['If-Modified-Since'] : '';
		} else {
			$http_if_modified_since = ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : '';
		}

		// Checking if the client is validating his cache and if it is current.
		if ( $http_if_modified_since && ( strtotime( $http_if_modified_since ) === @filemtime( $cache_file ) ) ) {
			// Client's cache is current, so we just respond '304 Not Modified'.
			header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
			exit;
		}

		// Serve the cache if file isn't store in the client browser cache.
		// if the browser does not support gzip read the file and output it without gzip encoding
		if ( ! $gzip_accepted ) {
			readgzfile( $cache_file );
			exit;
		}

		// otherwise output the gzipped file as-is
		header( "Content-Length: {$filesize}" );
		header( "Content-Encoding: gzip" );
		readfile( $cache_file );
		exit;
	}

	public function do_frontend_optimizations() {
		if ( ! $this->should_serve_cached_data() ) {
			return;
		}

		$settings = $this->settings;

		if ( isset( $settings->disable_wp_emoji ) && $settings->disable_wp_emoji ) {
			add_action( 'init', function () {
				remove_action( 'admin_print_styles', 'print_emoji_styles' );
				remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
				remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
				remove_action( 'wp_print_styles', 'print_emoji_styles' );
				remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
				remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
				remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
				add_filter( 'emoji_svg_url', '__return_false' );
			}, 999 );
		}


		if ( ! empty( $settings->critical_css ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_critical_css' ], PHP_INT_MAX );
		}
	}

	public function enqueue_critical_css() {
		wp_register_style( 'ezcache-critical-css', false );
		wp_enqueue_style( 'ezcache-critical-css' );
		wp_add_inline_style( 'ezcache-critical-css', $this->settings->critical_css );
	}

	/**
	 * Write cache file if we need to
	 * @noinspection PhpUnused
	 */
	public function maybe_write_cache_file() {
		if ( ! $this->should_serve_cached_data() ) {
			return;
		}

		ob_start( [ $this, 'optimize_and_write_cache_file' ] );
	}

	/**
	 * Optimize output and write the buffer to the cache file
	 *
	 * @param string $buffer
	 *
	 * @return string
	 */
	public function optimize_and_write_cache_file( $buffer ) {
		global $wpdb;

		// we need these check to run after WordPress is finished preparing the page
		if ( ! $this->should_save_cache() ) {
			return $buffer;
		}

		$real_cache_dir  = $this->get_real_cache_dir();
		$cache_file      = $this->get_cache_file_path() . '.gz';
		$asset_cache_dir = $this->get_default_cache_path() . 'min/';
		$asset_cache_url = trailingslashit( trailingslashit( get_site_url() ) . trim( str_replace( dirname( WP_CONTENT_DIR ), '', $asset_cache_dir ), '/' ) );
		$settings        = $this->settings;

		if ( $settings->optimize_google_fonts ) {
			$optimizer = new CombineGoogleFonts();
			$buffer    = $optimizer->optimize( $buffer );
		}

		if ( $settings->minify_css ) {
			if ( $settings->combine_css ) {
				$optimizer = new CssCombiner( $asset_cache_dir, $asset_cache_url, $settings->combine_css_footer );
			} else {
				$optimizer = new CssMinifier( $asset_cache_dir, $asset_cache_url );
			}

			$buffer = $optimizer->optimize( $buffer );
		}

		if ( $settings->minify_js ) {
			if ( $settings->combine_head_js ) {
				$optimizer = new JsCombiner( $asset_cache_dir, $asset_cache_url, 'head', $settings->combine_head_inline_js );
				$buffer    = $optimizer->optimize( $buffer );
			}

			if ( $settings->combine_body_js ) {
				$optimizer = new JsCombiner( $asset_cache_dir, $asset_cache_url, 'body', $settings->combine_body_inline_js );
				$buffer    = $optimizer->optimize( $buffer );
			}

			if ( ! $settings->combine_head_js && ! $settings->combine_body_js ) {
				$optimizer = new JsMinifier( $asset_cache_dir, $asset_cache_url );
				$buffer    = $optimizer->optimize( $buffer );
			}
		}

		if ( $settings->minify_html ) {
			wp_raise_memory_limit( 'image' );

			$buffer = Minify_HTML::minify( $buffer, [
				'htmlCleanComments' => $settings->minify_html_comments,

				'cssMinifier' => function ( $css ) use ( $settings ) {
					if ( ! $settings->minify_inline_css ) {
						return $css;
					}

					$minifier = new CSS( $css );
					$minifier->setMaxImportSize( 0 );
					$minifier->setImportExtensions( [] );

					return $minifier->minify();
				},

				'jsMinifier' => function ( $js ) use ( $settings ) {
					if ( ! $settings->minify_inline_js ) {
						return $js;
					}

					$minifier = new JS( $js );

					return $minifier->minify();
				},
			] );
		}

		if ( $settings->enable_webp_support && $this->webp_accepted() ) {
			$optimizer = new WebpConverter( $real_cache_dir, $cache_file, $this->webp_processor, $wpdb );
			$buffer    = $optimizer->optimize( $buffer );
		}

		$buffer = trim( $buffer );
		if ( empty( $buffer ) ) {
			Logger::log( 'ezCache will not save cache file for a blank page' );

			return $buffer;
		}

		if ( ! apply_filters( 'wp_bost_hide_cache_time_comment', false ) ) {
			$total_time = number_format( microtime( true ) - $this->cache_start_time, 2 );
			$buffer     .= "\n<!-- Cached by ezCache -->\n<!-- Cache created in {$total_time}s -->";
		}

		$buffer = apply_filters( 'ezcache_before_save_cache', $buffer );

		if ( ! file_exists( $real_cache_dir ) ) {
			if ( ! @wp_mkdir_p( $real_cache_dir ) ) {
				Logger::log( 'ezCache could not create directory ' . $real_cache_dir );

				return $buffer;
			}
		}

		// write gzipped file
		$handle = @fopen( $cache_file, 'w' );

		if ( $handle && @flock( $handle, LOCK_EX ) ) {
			fwrite( $handle, gzencode( $buffer, 6, FORCE_GZIP ) );
			flock( $handle, LOCK_UN );
		} else {
			Logger::log( 'ezCache could not write to ' . str_replace( ABSPATH, '', $cache_file ) );
		}

		if ( $handle ) {
			fclose( $handle );
		}

		return $buffer;
	}

	/**
	 * Delete a path recursively
	 *
	 * @param string $path
	 */
	public function rmdir_recursive( $path ) {
		if ( ! file_exists( $path ) ) {
			return;
		}

		$files = glob( $path . '/*' );
		foreach ( $files as $file ) {
			if ( file_exists( $file ) && is_dir( $file ) ) {
				$this->rmdir_recursive( $file );
			} elseif ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		rmdir( $path );
	}

	/**
	 * Preload the homepage and immediately create cache for it
	 */
	public function preload_homepage() {
		$desktop_ua = apply_filters(
			'ezcache_desktop_useragent',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36 (ezCache Preload)'
		);
		$mobile_ua  = apply_filters(
			'ezcache_mobile_useragent',
			'Mozilla/5.0 (iPhone; CPU iPhone OS 12_0 like Mac OS X) AppleWebKit/ 604.1.21 (KHTML, like Gecko) Version/ 12.0 Mobile/17A6278a Safari/602.1.26 (ezCache Preload)'
		);

		wp_safe_remote_get( site_url(), [
			'user-agent' => $desktop_ua,
			'timeout'    => 0.1,
		] );

		wp_safe_remote_get( site_url(), [
			'user-agent' => $mobile_ua,
			'timeout'    => 0.1,
		] );
	}

	function delete_missing_webp_images( $delete_all = false ) {
		global $wpdb;

		// delete the actual files
		$ids    = [ 0 ];
		$where  = $delete_all ? '' : "WHERE `status` = 'completed'";
		$images = $wpdb->get_results( "SELECT * FROM `{$wpdb->prefix}ezcache_webp_images` {$where}" );
		foreach ( $images as $image ) {
			if ( ! file_exists( $image->webp_path ) ) {
				$ids[] = $image->id;
			} elseif ( ( $delete_all || ! file_exists( $image->path ) ) && file_exists( $image->webp_path ) ) {
				unlink( $image->webp_path );
				$ids[] = $image->id;
			}
		}

		// clean the database
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$wpdb->prefix}ezcache_webp_images` WHERE `status` = 'failed' OR `id` IN ( " . substr( str_repeat( "%d, ", count( $ids ) ), 0, - 2 ) . " )",
				$ids
			)
		);

		$wpdb->query( "OPTIMIZE TABLE `{$wpdb->prefix}ezcache_webp_images`" );
	}

	function delete_all_webp_images() {
		$this->delete_missing_webp_images( true );
	}

	/**
	 * Clear all caches
	 *
	 * @param bool $clear_webp Should deleting cache clear the WebP images
	 */
	public function clear_cache( $clear_webp = false ) {
		$this->rmdir_recursive( $this->root_cache_dir );
		@wp_mkdir_p( $this->root_cache_dir );

		if ( $clear_webp ) {
			$this->delete_all_webp_images();
		} else {
			$this->delete_missing_webp_images();
		}

		$this->purge_varnish_cache();

		$this->preload_homepage();
	}

	/**
	 * Clear cache for a single post
	 *
	 * @param int $post_id
	 */
	public function clear_cache_single( $post_id ) {
		$real_cache_dir = $this->get_real_cache_dir( $post_id );

		$this->rmdir_recursive( $real_cache_dir );

		$this->purge_varnish_cache();
	}

	public function clear_cache_url( $url ) {
		$real_cache_dir = $this->get_real_cache_dir( 0, $url );

		$this->rmdir_recursive( $real_cache_dir );

		$this->purge_varnish_cache();
	}

	public function purge_varnish_cache() {
		$desktop_ua = apply_filters(
			'ezcache_desktop_useragent',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36 (ezCache Preload)'
		);

		$parseUrl = parse_url( home_url() );

		$schema = 'http://';
		if ( isset( $parseUrl['scheme'] ) ) {
			$schema = $parseUrl['scheme'] . '://';
		}

		$host = $parseUrl['host'];

		$request_args = [
			'method'    => 'PURGE',
			'headers'   => [
				'Host'       => $host,
				'User-Agent' => $desktop_ua,
			],
			'sslverify' => false,
		];
		$response = wp_remote_request( $schema . $host . '/.*', $request_args );
		if ( is_wp_error( $response ) || $response['response']['code'] != '200' ) {
			if ( $schema === 'https://' ) {
				$schema = 'http://';
			} else {
				$schema = 'https://';
			}
			wp_remote_request( $schema . $host . '/.*', $request_args );
		}
	}

	/**
	 * Delete expired cache
	 */
	public function clear_expired_cache() {
		$settings = $this->settings;

		try {
			$dir      = new RecursiveDirectoryIterator( $this->root_cache_dir );
			$iterator = new RecursiveIteratorIterator( $dir );
			$files    = new RegexIterator( $iterator, '/^.+\.(?:gz|html|js|css)$/i', RegexIterator::GET_MATCH );
		} catch ( UnexpectedValueException $ex ) {
			if ( strpos( $ex->getMessage(), 'No such file or directory' ) ) {
				$files = [];
			} else {
				throw $ex;
			}
		}

		foreach ( $files as $file ) {
			if ( is_array( $file ) ) {
				$file = array_shift( $file );
			}

			$stats = stat( $file );
			if ( $stats['mtime'] > ( time() - $settings->cache_lifetime ) ) {
				// skip not expired files
				continue;
			}

			@unlink( $file );
		}
	}

	/**
	 * Get caching statistics and file sizes
	 * @return array
	 */
	public function get_cache_stats() {
		$settings  = $this->settings;
		$cache_dir = $this->get_default_cache_path();

		try {
			$dir      = new RecursiveDirectoryIterator( $cache_dir );
			$iterator = new RecursiveIteratorIterator( $dir );
			$files    = new RegexIterator( $iterator, '/^.+\.(?:gz|html|css|js)$/i', RegexIterator::GET_MATCH );
		} catch ( UnexpectedValueException $ex ) {
			if ( strpos( $ex->getMessage(), 'No such file or directory' ) ) {
				$files = [];
			} else {
				throw $ex;
			}
		}

		$raw_data = [];

		$mobile_count          = 0;
		$mobile_size           = 0;
		$mobile_expired_count  = 0;
		$mobile_expired_size   = 0;
		$desktop_count         = 0;
		$desktop_size          = 0;
		$desktop_expired_count = 0;
		$desktop_expired_size  = 0;
		$js_count              = 0;
		$js_size               = 0;
		$js_expired_count      = 0;
		$js_expired_size       = 0;
		$css_count             = 0;
		$css_size              = 0;
		$css_expired_count     = 0;
		$css_expired_size      = 0;

		foreach ( $files as $file ) {
			if ( is_array( $file ) ) {
				$file = array_shift( $file );
			}

			$stats   = stat( $file );
			$expired = $stats['mtime'] <= ( time() - $settings->cache_lifetime );

			$raw_data[] = [
				'path'    => $file,
				'stats'   => $stats,
				'expired' => $expired,
			];

			if ( preg_match( '/^.+?-mobile\.html(\.gz)?$/i', $file ) ) {
				if ( ! $expired ) {
					$mobile_count ++;
					$mobile_size += $stats['size'];
				} else {
					$mobile_expired_count ++;
					$mobile_expired_size += $stats['size'];
				}
			} elseif ( preg_match( '/\.css$/i', $file ) ) {
				if ( $expired ) {
					$css_expired_count ++;
					$css_expired_size += $stats['size'];
				} else {
					$css_count ++;
					$css_size += $stats['size'];
				}
			} elseif ( preg_match( '/\.js$/i', $file ) ) {
				if ( $expired ) {
					$js_expired_count ++;
					$js_expired_size += $stats['size'];
				} else {
					$js_count ++;
					$js_size += $stats['size'];
				}
			} else {
				if ( ! $expired ) {
					$desktop_count ++;
					$desktop_size += $stats['size'];
				} else {
					$desktop_expired_count ++;
					$desktop_expired_size += $stats['size'];
				}
			}
		}

		// we want to count only the number of pages which have cache, but we have 2 files for each page
		$mobile_count  = $mobile_count / 2;
		$desktop_count = $desktop_count / 2;


		global $wpdb;
		$webp_images               = 0;
		$webp_images_size          = 0;
		$webp_images_original_size = 0;

		$results = $wpdb->get_row( "SELECT COUNT(*) AS total, SUM(`original_size`) AS total_original_size, SUM(`webp_size`) AS total_webp_size FROM `{$wpdb->prefix}ezcache_webp_images` WHERE `status` = 'completed'" );
		if ( $results ) {
			$webp_images               = intval( $results->total );
			$webp_images_size          = intval( $results->total_webp_size );
			$webp_images_original_size = intval( $results->total_original_size );
		}

		return compact( 'webp_images', 'webp_images_original_size', 'webp_images_size', 'mobile_count', 'desktop_count', 'mobile_expired_count', 'desktop_expired_count', 'mobile_size', 'desktop_size', 'mobile_expired_size', 'desktop_expired_size', 'css_size', 'css_count', 'js_count', 'js_size', 'css_expired_count', 'css_expired_size', 'js_expired_count', 'js_expired_size' );
	}

	/**
	 * Get the status of the cache
	 *
	 * @return array
	 */
	public function get_status() {
		global $wpdb;

		$wp_cache_enabled       = defined( 'WP_CACHE' ) && WP_CACHE;
		$adv_cache_exists       = file_exists( WP_CONTENT_DIR . '/advanced-cache.php' );
		$correct_advanced_cache = $adv_cache_exists && strpos( file_get_contents( WP_CONTENT_DIR . '/advanced-cache.php' ), 'ezCache Advanced Cache' ) !== false;
		$webp_table_exists      = ! is_null( $wpdb->get_row( "SHOW TABLES LIKE '{$wpdb->prefix}ezcache_webp_images'" ) );

		return [
			'cache_enabled'        => $wp_cache_enabled,
			'adv_cache_exists'     => $adv_cache_exists,
			'correct_cache_exists' => $correct_advanced_cache,
			'webp_table_exists'    => $webp_table_exists,
		];
	}

	/**
	 * Get a path by the URL
	 *
	 * @param string $url
	 *
	 * @return bool|string
	 */
	public static function url_to_path( $url ) {
		$root_dir = trailingslashit( dirname( WP_CONTENT_DIR ) );
		$root_url = str_replace( wp_basename( WP_CONTENT_DIR ), '', content_url() );
		$url_host = wp_parse_url( $url, PHP_URL_HOST );

		// relative path.
		if ( null === $url_host ) {
			$subdir_levels = substr_count( preg_replace( '/https?:\/\//', '', site_url() ), '/' );
			$url           = trailingslashit( site_url() . str_repeat( '/..', $subdir_levels ) ) . ltrim( $url, '/' );
		}

		$root_url  = preg_replace( '/^https?:/', '', $root_url );
		$url_rep   = preg_replace( '/^https?:/', '', $url );
		$file      = str_replace( $root_url, $root_dir, $url_rep );
		$real_path = self::realpath( $file );

		if ( ! file_exists( $real_path ) ) {
			return false;
		}

		return $real_path;
	}

	/**
	 * Returns canonicalized absolute pathname.
	 * The resulting path will have no symbolic link, '/./' or '/../' components.
	 * Same as the defautl PHP realpath() function but works even when the files does not exist.
	 *
	 * @param string $file The path being checked.
	 *
	 * @return string
	 * @see \realpath()
	 *
	 */
	public static function realpath( $file ) {
		$path = [];

		foreach ( explode( '/', $file ) as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}

			if ( '..' !== $part ) {
				array_push( $path, $part );
			} elseif ( count( $path ) > 0 ) {
				array_pop( $path );
			}
		}

		$prefix = 'WIN' === strtoupper( substr( PHP_OS, 0, 3 ) ) ? '' : '/';

		return $prefix . join( '/', $path );
	}
}
