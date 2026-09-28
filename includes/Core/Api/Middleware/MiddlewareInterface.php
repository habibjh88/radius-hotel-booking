<?php
/**
 * Contract every REST middleware implements.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Middleware
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Middleware;

use WP_REST_Request;
use WP_REST_Response;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/Api/Middleware/MiddlewareInterface.php
 * Interface for API middleware
 */
interface MiddlewareInterface {
	/**
	 * Handles the incoming REST request and passes it to the next callable in the stack for further processing.
	 *
	 * @param WP_REST_Request $request The current REST request instance.
	 * @param callable $next The next callable to be executed in the middleware stack.
	 */
	public function handle( WP_REST_Request $request, callable $next );

	/**
	 * Handles bypassing a specific request and forwarding it to the next handler.
	 *
	 * @param WP_REST_Request $request The current REST request object.
	 * @param callable $next A callable to pass the request to the next handler in the chain.
	 */
	public function byPassRequest( WP_REST_Request $request, callable $next );
}
