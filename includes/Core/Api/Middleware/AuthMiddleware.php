<?php
/**
 * RadiusHotelBooking API Authentication Middleware
 *
 * This middleware checks if the user is authenticated before allowing access to the API.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Middleware
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Middleware;

use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use WP_REST_Request;
use WP_REST_Response;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/Api/Middleware/AuthMiddleware.php
 * Authentication middleware
 */
class AuthMiddleware implements MiddlewareInterface {
	/**
	 * Handles the incoming REST API request and checks if the user is authenticated.
	 *
	 * @param WP_REST_Request $request The HTTP request object.
	 * @param callable $next The next middleware or handler to call if the user is authenticated.
	 *
	 * @return mixed The response after handling the request.
	 */
	public function handle( WP_REST_Request $request, callable $next ) {
		// Check if user is authenticated
		if ( ! is_user_logged_in() ) {
			return ApiResponse::unauthorized( __( 'Authentication required', 'radius-hotel-booking' ) )->send();
		}

		return $next( $request );
	}

	/**
	 * Processes the given REST request through the provided callback.
	 *
	 * @param WP_REST_Request $request The REST request to be processed.
	 * @param callable $next The callback function to handle the request.
	 */
	public function byPassRequest( WP_REST_Request $request, callable $next ) {
		return $next( $request );
	}
}
