<?php
/**
 * Registers the plugin REST API: route files, the router and CORS.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api;

use RadiusTheme\RadiusHotelBooking\Core\Api\Routes\RouteRegistrar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Class ApiManager
 *
 * Initializes the REST API for Radius Hotel Booking plugin.
 * Registers routes and adds CORS headers to support cross-origin requests.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api
 */
class ApiManager {
	/**
	 * The REST route registrar instance.
	 *
	 * @var RouteRegistrar
	 */
	private RouteRegistrar $router;

	/**
	 * ApiManager constructor.
	 *
	 * Initializes the route registrar and registers routes and CORS support.
	 */
	public function __construct() {
		$this->router = new RouteRegistrar( 'radius-hotel-booking/v1' );

		// Load route files and register routes on rest_api_init.
		// This runs late enough for add-on plugins and extensions to hook
		// into the rtbp_api_route_paths filter during
		// plugins_loaded or init.
		add_action( 'rest_api_init', array( $this, 'initRoutes' ) );

		// Add CORS support.
		add_action( 'rest_api_init', array( $this, 'addCorsSupport' ) );
	}

	/**
	 * Load route files and register routes with the REST API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function initRoutes(): void {
		/**
		 * Fires before API routes are initialized.
		 *
		 * @since 1.0.0
		 */
		do_action( 'rtbp_api_init_before' );

		$this->loadRouteFiles();

		$this->router->registerRoutes();

		/**
		 * Fires after API routes are initialized.
		 *
		 * @since 1.0.0
		 */
		do_action( 'rtbp_api_init_after' );
	}

	/**
	 * Load route files by including them.
	 *
	 * Fires a filter so the an add-on plugin (or any extension) can append
	 * additional route file paths before they are loaded.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function loadRouteFiles(): void {
		/**
		 * Filter the list of route file paths to load.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $route_paths Absolute paths to route files.
		 */
		$route_paths = apply_filters(
			'rtbp_api_route_paths',
			array( RADIUS_HOTEL_BOOKING_INCLUDES . '/Routes/routes.php' )
		);

		// Expose $router as a local variable so route files can use
		// either $this->router (base plugin) or $router (pro/extensions).
		$router = $this->router; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		foreach ( $route_paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Adds CORS headers to the REST API responses.
	 *
	 * @return void
	 */
	public function addCorsSupport(): void {
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

		add_filter(
			'rest_pre_serve_request',
			function ( $value ) {
				/**
				 * Filter the allowed origin for REST API CORS.
				 *
				 * @since 1.0.0
				 *
				 * @param string $origin The origin allowed to access the API.
				 */
				$origin = apply_filters( 'rtbp_api_cors_origin', '*' );

				header( 'Access-Control-Allow-Origin: ' . $origin );
				header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
				header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce' );
				header( 'Access-Control-Allow-Credentials: true' );

				/**
				 * Fires after CORS headers are sent in REST API.
				 *
				 * @since 1.0.0
				 */
				do_action( 'rtbp_api_cors_headers_sent' );

				return $value;
			}
		);
	}
}
