<?php
namespace Upress\EzCache;

class RestApi {
	private $plugin;
	private $rest_routes = [];

	function __construct($plugin) {
		$this->plugin = $plugin;

		$this->get(    '/settings', '\Upress\EzCache\Rest\SettingsController@show' );
		$this->patch(  '/settings', '\Upress\EzCache\Rest\SettingsController@update' );
		$this->delete( '/settings', '\Upress\EzCache\Rest\SettingsController@destroy' );
		$this->get(    '/cache',    '\Upress\EzCache\Rest\CacheController@show' );
		$this->delete( '/cache',    '\Upress\EzCache\Rest\CacheController@destroy' );
		$this->post( '/webp',    '\Upress\EzCache\Rest\WebpController@process' );
		$this->delete( '/webp',    '\Upress\EzCache\Rest\WebpController@destroy' );
		$this->get(    '/status',   '\Upress\EzCache\Rest\StatusController@show' );
		$this->get(    '/license',  '\Upress\EzCache\Rest\LicenseController@show' );
		$this->patch(  '/license',  '\Upress\EzCache\Rest\LicenseController@update' );
		$this->delete( '/license',  '\Upress\EzCache\Rest\LicenseController@destroy' );

		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
	}

	function rest_api_init() {
		foreach ( $this->rest_routes as $rest ) {
			register_rest_route( "ezcache/v1", $rest['route'], $rest['params'] );
		}
	}

	function get( $route, $handler, $capabilities = 'manage_options' ) { $this->register_route( 'GET', $route, $handler, $capabilities ); }
	function post( $route, $handler, $capabilities = 'manage_options' ) { $this->register_route( 'POST', $route, $handler, $capabilities ); }
	function put( $route, $handler, $capabilities = 'manage_options' ) { $this->register_route( 'PUT', $route, $handler, $capabilities ); }
	function patch( $route, $handler, $capabilities = 'manage_options' ) { $this->register_route( 'PATCH', $route, $handler, $capabilities ); }
	function delete( $route, $handler, $capabilities = 'manage_options' ) { $this->register_route( 'DELETE', $route, $handler, $capabilities ); }

	/**
	 * Register a REST route
	 * @param string|string[] $http_method
	 * @param string $route
	 * @param callable|string $handler
	 * @param callable|string $capability
	 */
	protected function register_route( $http_method, $route, $handler, $capability = 'manage_options' ) {
		if ( is_callable( $handler ) || strpos( $handler, '::' ) !== false ) {
			$callback = $handler;
		} else {
			list( $class, $method ) = explode( '@', $handler );
			$class = new $class;
			$callback = [ $class, $method ];
		}

		if ( is_callable( $capability ) ) {
			$permission_callback = $capability;
		} else {
			$permission_callback = function () use ($capability) {
				return current_user_can( $capability );
			};
		}

		$this->rest_routes[] = [
			'route' => $route,
			'params' => [
				'methods' => $http_method,
				'callback' => $callback,
				'permission_callback' => $permission_callback,
			]
		];
	}
}
