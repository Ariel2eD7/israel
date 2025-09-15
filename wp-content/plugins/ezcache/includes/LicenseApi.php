<?php
namespace Upress\EzCache;

use ErrorException;
use Upress\EzCache\Utilities\Encrypter;
use WP_Error;

class LicenseApi {
	protected $domain;
	protected $encrypter;
	protected $pid = 'EZCSLPRO';
	protected $api_url = 'https://ezcache.app';
	protected $opt_key = 'ezcache_license';
	protected $opt_key_data = 'ezcache_license_data';

	protected static $_key;
	protected static $_license_data;

	function __construct() {
		$this->domain = str_replace( [ 'https://', 'http://' ], '', get_bloginfo( 'wpurl' ) );
		$this->encrypter = new Encrypter();
	}

	public function get_expiration_date() {
		$data = $this->get_license_data();
		return isset( $data['licence_expire'] ) ? strtotime( $data['licence_expire'] ) : 0;
	}

	public function is_license_valid() {
		$data = $this->get_license_data();
		return isset( $data['licence_status'] ) && $data['licence_status'] == 'active';
	}

	public function get_masked_license_key() {
		$key = $this->get_license_key();
		if ( empty( $key ) ) return '';

		$maskedKey = substr( $key, - 4 );
		return "•••• •••• •••• {$maskedKey}";
	}

	public function get_license_key() {
		if ( self::$_key ) return self::$_key;

		try {
			self::$_key = $this->encrypter->decrypt( get_option( $this->opt_key ) );
		} catch( ErrorException $ex ) {
			self::$_key = '';
		}

		return self::$_key;
	}

	public function get_license_data( $cached=true ) {
		if ( self::$_license_data ) return self::$_license_data;

		try {
			self::$_license_data = $this->encrypter->decrypt( get_transient( $this->opt_key_data ) );
		} catch( ErrorException $ex ) {
			self::$_license_data = [];
		}

		if ( ! $cached || empty( self::$_license_data ) || WP_DEBUG ) {
			$key = $this->get_license_key();
			// get the current status and cache it
			self::$_license_data = $this->status_check( $key );
			self::$_license_data = array_merge( (array) self::$_license_data, [ 'key' => $key ] );
			set_transient( $this->opt_key_data, $this->encrypter->encrypt( self::$_license_data ), DAY_IN_SECONDS );
		}

		return self::$_license_data;
	}

	public function update_license( $key ) {
		if ( empty( $key ) ) {
			return new WP_Error( 'empty_license_key', __( 'Can\'t set empty license key', 'ezcache' ) );
		}

		// try to activate the license
		$response = $this->activate( $key );
		if ( isset( $response->status ) && 'error' == $response->status ) {
			return new WP_Error( $response->status_code, $response->message );
		}

		// get the current status and cache it
		self::$_key = $key;
		self::$_license_data = $this->status_check( $key );
		self::$_license_data = array_merge( (array) self::$_license_data, [ 'key' => $key ] );
		update_option( $this->opt_key, $this->encrypter->encrypt( $key ) );
		set_transient( $this->opt_key_data, $this->encrypter->encrypt( self::$_license_data ), DAY_IN_SECONDS );

		return self::$_license_data;
	}

	public function clear_license() {
		$key = $this->get_license_key();

		$response = [];
		if ( ! empty( $key ) ) {
			// deactivate the license on the server
			if ( ! empty( $key ) ) {
				$response = $this->deactivate( $key );

				if ( isset( $response->status ) && 'error' == $response->status && 'e111' != $response->status_code ) {
					return new WP_Error( $response->status_code, $response->message );
				}
			}
		}

		// and clear any cached data
		self::$_key = null;
		self::$_license_data = null;
		delete_option( $this->opt_key );
		delete_transient( $this->opt_key_data );

		return $this->get_license_data( false );
	}



	/**
	 * Check the license status for a key
	 *
	 * @param string $key License key
	 *
	 * @return array|mixed|object|WP_Error
	 */
	protected function status_check( $key ) {
		return $this->api_function( 'status-check', $key );
	}

	/**
	 * Activate the current website for the provided key
	 *
	 * @param string $key License key
	 *
	 * @return array|mixed|object|WP_Error
	 */
	protected function activate( $key ) {
		return $this->api_function( 'activate', $key );
	}

	/**
	 * Deactivate the current website from the provided key
	 *
	 * @param string $key License key
	 *
	 * @return array|mixed|object|WP_Error
	 */
	protected function deactivate( $key ) {
		return $this->api_function( 'deactivate', $key );
	}


	/**
	 * Run a API function
	 *
	 * @param string $action API action
	 * @param string $key License key
	 *
	 * @return array|mixed|object|WP_Error
	 */
	protected function api_function( $action, $key ) {
		$response = wp_safe_remote_get( $this->api_url . '?' . http_build_query( [
				'woo_sl_action'     => $action,
				'licence_key'       => $key,
				'product_unique_id' => $this->pid,
				'domain'            => $this->domain,
			] ) );

		if ( is_wp_error( $response ) ) {
			/** @param WP_Error $response */
			return (object)[
				'status' => 'error',
				'status_code' => 'error_checking_status',
				'message' => $response->get_error_message(),
			];
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );
		if ( is_array( $response ) ) {
			$response = array_shift( $response );
		}

		if ( isset( $response->status_code ) ) {
			$response->message = $this->get_status_message( $response->status_code );
		}

		return $response;
	}

	/**
	 * Get a message text for a specific status code
	 *
	 * @param string $status
	 *
	 * @return mixed|string|void
	 */
	protected function get_status_message( $status ) {
		$map = [
			// success responses
			's100' => _x( 'Licence key successfully activated for the current domain', 'license API response message', 'ezcache' ),
			's101' => _x( 'Licence key successfully activated for the current domain', 'license API response message', 'ezcache' ),
			's201' => _x( 'Licence key successfully unassigned', 'license API response message', 'ezcache' ),
			's203' => _x( 'Licence key is not valid for this website', 'license API response message', 'ezcache' ),
			's205' => _x( 'Licence key is active and valid for this website', 'license API response message', 'ezcache' ),
			's401' => '', // a full response with code metadata on calling plugin_update or theme_update methods
			's402' => '', // a full response with code metadata on calling plugin_information method
			's403' => '', // a full response with code metadata on calling code_information method
			's610' => _x( 'Licence key successfully deleted', 'license API response message', 'ezcache' ),

			// error responses
			'e001' => _x( 'Invalid provided data', 'license API response message', 'ezcache' ),
			'e002' => _x( 'Invalid licence key', 'license API response message', 'ezcache' ),
			'e003' => _x( 'Order does not exists anymore', 'license API response message', 'ezcache' ),
			'e004' => _x( 'Order status not allowed', 'license API response message', 'ezcache' ),
			'e110' => _x( 'Invalid licence key or licence not active for domain', 'license API response message', 'ezcache' ),
			'e111' => _x( 'Invalid data', 'license API response message', 'ezcache' ),
			'e112' => _x( 'You had reached the maximum number of domains for this key', 'license API response message', 'ezcache' ),
			'e204' => _x( 'Licence key not active for current domain', 'license API response message', 'ezcache' ),
			'e301' => _x( 'Licence key does not match this product', 'license API response message', 'ezcache' ),
			'e312' => _x( 'Licence is not active', 'license API response message', 'ezcache' ),
			'e419' => _x( 'Invalid product unique ID', 'license API response message', 'ezcache' ),
		];

		return isset( $map[ $status ] ) ? $map[ $status ] : __( 'Unknown error', 'ezcache' );
	}
}
