<?php
/**
 * Throttles REST requests per user or IP.
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
 * Core/Api/Middleware/RateLimitMiddleware.php
 * Rate limiting middleware
 */
class RateLimitMiddleware implements MiddlewareInterface {
	/**
	 * Maximum number of requests allowed within the specified time window.
	 * This is typically set to a value like 100 requests.
	 *
	 * @var int
	 */
	private int $maxRequests;
	/**
	 * Time window in seconds within which the requests are counted.
	 * This is typically set to a value like 3600 seconds (1 hour).
	 *
	 * @var int
	 */
	private int $timeWindow; // in seconds

	/**
	 * Constructor method for initializing the class with maximum requests and time window.
	 *
	 * @param int $maxRequests The maximum number of requests allowed. Default is 100.
	 * @param int $timeWindow The time window in seconds within which the requests are counted. Default is 3600.
	 *
	 * @return void
	 */
	public function __construct( int $maxRequests = 100, int $timeWindow = 3600 ) {
		$this->maxRequests = $maxRequests;
		$this->timeWindow  = $timeWindow;
	}

	/**
	 * Handles the incoming request and applies rate limiting based on the client's IP address.
	 *
	 * @param WP_REST_Request $request The current request object.
	 * @param callable $next The next middleware or handler to process the request.
	 */
	public function handle( WP_REST_Request $request, callable $next ) {
		$clientIp = $this->getClientIp();
		$clientIp = esc_html( $clientIp );
		$key      = "rtbp_rate_limit_{$clientIp}";

		$requests = get_transient( $key ) ?: 0; //phpcs:ignore

		if ( $requests >= $this->maxRequests ) {
			return ApiResponse::error( 'Rate limit exceeded', 429 )->send();
		}

		set_transient( $key, $requests + 1, $this->timeWindow );

		return $next( $request );
	}

	/**
	 * Retrieves the IP address of the client making the request.
	 *
	 * @return string Returns the client's IP address or 'unknown' if it cannot be determined.
	 */
	private function getClientIp(): string {

		// Prefer X-Forwarded-For if present (proxy/CDN)
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {

			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ips       = explode( ',', $forwarded );

			$candidate = trim( $ips[0] );

			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		// Fallback to REMOTE_ADDR
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {

			$candidate = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		return 'unknown';
	}



	/**
	 * Handles the bypass logic for a given request by executing the next callable in the chain.
	 *
	 * @param WP_REST_Request $request The incoming REST API request that needs to be processed.
	 * @param callable $next The next callable to execute in the request processing chain.
	 *
	 * @return mixed The result returned by the next callable.
	 */
	public function byPassRequest( WP_REST_Request $request, callable $next ) {
		return $next( $request );
	}
}
