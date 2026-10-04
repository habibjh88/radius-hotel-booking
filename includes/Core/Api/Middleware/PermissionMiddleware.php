<?php
/**
 * Permission middleware.
 *
 * Verifies the REST nonce, the request origin and the user's capability before
 * a route handler runs. Routes that must stay public (an unauthenticated form
 * submission, for example) opt out through the `rtbp_public_api_routes` filter
 * instead of being hard-coded here.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Middleware
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Middleware;

use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Capability-based middleware.
 */
class PermissionMiddleware implements MiddlewareInterface {

	/**
	 * The capability required to access the endpoint.
	 *
	 * May be a comma-separated "any-of" list, e.g. "rtbp_manage_items,rtbp_manage_settings".
	 *
	 * @var string
	 */
	private string $capability;

	/**
	 * Constructor.
	 *
	 * @param string $capability Capability (or comma-separated list) to require.
	 */
	public function __construct( string $capability ) {
		$this->capability = $capability;
	}

	/**
	 * Handle the incoming REST request and check user permissions.
	 *
	 * @param WP_REST_Request $request The incoming REST request instance.
	 * @param callable        $next    The next middleware or handler.
	 *
	 * @return mixed
	 */
	public function handle( WP_REST_Request $request, callable $next ) {

		/**
		 * 1. Check the REST nonce — proves the request came from this site.
		 */
		$nonce = $request->get_header( 'x-wp-nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return ApiResponse::forbidden( __( 'Invalid nonce', 'radius-hotel-booking' ) )->send();
		}

		/**
		 * 2. Check the referer to confirm the request originates from this domain.
		 */
		$referer        = $request->get_header( 'referer' );
		$allowed_domain = home_url();

		if ( empty( $referer ) || strpos( $referer, $allowed_domain ) !== 0 ) {
			return ApiResponse::forbidden( __( 'Invalid request source', 'radius-hotel-booking' ) )->send();
		}

		/**
		 * 3. Let explicitly public routes through without a capability check.
		 */
		if ( $this->isPublicRoute( $request ) ) {
			return $this->byPassRequest( $request, $next );
		}

		/**
		 * 4. Check the user capability.
		 *
		 * Plugin capabilities (the `rtbp_*` namespace) grant administrators an
		 * implicit pass via Capabilities::userCan(), so a plugin cap can never
		 * lock a site admin out. Any other capability string is checked with
		 * plain current_user_can(), with no admin bypass.
		 */
		$allowed = false;
		foreach ( array_filter( array_map( 'trim', explode( ',', $this->capability ) ) ) as $cap ) {
			$passed = ( 0 === strpos( $cap, 'rtbp_' ) )
				? Capabilities::userCan( $cap )
				: current_user_can( $cap );
			if ( $passed ) {
				$allowed = true;
				break;
			}
		}

		if ( ! $allowed ) {
			return ApiResponse::forbidden( __( 'Insufficient permissions', 'radius-hotel-booking' ) )->send();
		}

		return $next( $request );
	}

	/**
	 * Pass the request straight through to the next handler.
	 *
	 * @param WP_REST_Request $request The current REST request object.
	 * @param callable        $next    The next callable to process the request.
	 *
	 * @return mixed
	 */
	public function byPassRequest( WP_REST_Request $request, callable $next ) {
		return $next( $request );
	}

	/**
	 * Whether this route is allowed without a capability check.
	 *
	 * Register public routes as `METHOD /namespace/route` strings, or as regex
	 * patterns (delimited, e.g. `#^/radius-hotel-booking/v1/items/\d+/public$#`)
	 * keyed by method.
	 *
	 * @param WP_REST_Request $request Current request.
	 *
	 * @return bool
	 */
	protected function isPublicRoute( WP_REST_Request $request ): bool {
		$method = $request->get_method();
		$route  = $request->get_route();

		/**
		 * Exact `METHOD /route` matches that skip the capability check.
		 *
		 * BOILERPLATE: add your own public endpoints here, e.g.
		 * `'POST /radius-hotel-booking/v1/items'` for a public submission form.
		 *
		 * @param string[] $routes Exact "METHOD /route" strings.
		 */
		$exact = (array) apply_filters( 'rtbp_public_api_routes', array() );

		if ( in_array( $method . ' ' . $route, $exact, true ) ) {
			return true;
		}

		/**
		 * Regex patterns that skip the capability check, as
		 * `array( 'METHOD' => array( '#pattern#', … ) )`.
		 *
		 * @param array<string,string[]> $patterns Method => list of patterns.
		 */
		$patterns = (array) apply_filters( 'rtbp_public_api_route_patterns', array() );

		foreach ( $patterns[ $method ] ?? array() as $pattern ) {
			if ( preg_match( $pattern, $route ) ) {
				return true;
			}
		}

		return false;
	}
}
