<?php
/**
 * Throttles REST requests per client IP and scope.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Middleware
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Middleware;

use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * A fixed-window limit per client and **scope**: `new RateLimitMiddleware( 30,
 * MINUTE_IN_SECONDS, 'availability' )` allows 30 requests a minute from one IP
 * to the routes of that scope — each scope counts on its own, so a busy search
 * never uses up the booking limit, and staff routes (which carry none) are
 * never throttled.
 *
 * The client is the **TCP peer** (`REMOTE_ADDR`): `X-Forwarded-For` can be
 * set by anyone, so it is trusted only through the `rtbp_activity_ip` filter
 * — the same one the activity log uses (Pro reads forwarded headers behind
 * proxies the site lists). IPv6 clients count per /64 network.
 * Over the limit: 429 with `Retry-After`. The window is aligned to the clock
 * and the count is atomic (`hit()`), so parallel bursts cannot overshoot it.
 */
class RateLimitMiddleware implements MiddlewareInterface {

	/**
	 * Requests allowed per window.
	 *
	 * @var int
	 */
	private int $maxRequests;

	/**
	 * Window length, in seconds.
	 *
	 * @var int
	 */
	private int $timeWindow;

	/**
	 * What is counted together.
	 *
	 * @var string
	 */
	private string $scope;

	/**
	 * Constructor.
	 *
	 * @param int    $maxRequests Requests allowed per window.
	 * @param int    $timeWindow  Window, in seconds.
	 * @param string $scope       Counter name (letters, digits, `_`).
	 */
	public function __construct( int $maxRequests = 100, int $timeWindow = 3600, string $scope = 'default' ) {
		$this->maxRequests = max( 1, $maxRequests );
		$this->timeWindow  = max( 1, $timeWindow );
		$this->scope       = preg_replace( '/[^a-z0-9_]/', '', strtolower( $scope ) );
	}

	/**
	 * Count the request; refuse it over the limit.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param callable        $next    The next handler.
	 * @return mixed
	 */
	public function handle( WP_REST_Request $request, callable $next ) {
		$now   = time();
		$reset = ( intdiv( $now, $this->timeWindow ) + 1 ) * $this->timeWindow;
		$count = self::hit( 'rtbp_rl_' . $this->scope . '_' . self::clientKey() . '_' . $reset, $reset - $now );

		if ( $count > $this->maxRequests ) {
			$response = ApiResponse::error( __( 'Too many requests. Please wait a moment and try again.', 'radius-hotel-booking' ), 429 )->send();
			if ( is_object( $response ) && method_exists( $response, 'header' ) ) {
				$response->header( 'Retry-After', (string) max( 1, $reset - $now ) );
			}
			return $response;
		}

		return $next( $request );
	}

	/**
	 * Count one request in a window, atomically (parallel requests must not
	 * read the same count — a get-then-set let twice the limit through, M04).
	 * With a persistent object cache: `wp_cache_incr()`. Without: one row per
	 * client and window in `wp_options` (not autoloaded), incremented by the
	 * database; past windows are deleted now and then.
	 *
	 * @param string $key     Counter name (ends with the window's reset time).
	 * @param int    $seconds Seconds left in the window.
	 * @return int The count including this request.
	 */
	private static function hit( string $key, int $seconds ): int {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $key, 0, 'rtbp_rate_limit', max( 1, $seconds ) );
			$count = wp_cache_incr( $key, 1, 'rtbp_rate_limit' );
			return false === $count ? 1 : (int) $count;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic counter; never read through the options cache.
		$wpdb->query( $wpdb->prepare( "INSERT INTO %i ( option_name, option_value, autoload ) VALUES ( %s, '1', 'no' ) ON DUPLICATE KEY UPDATE option_value = option_value + 1", $wpdb->options, $key ) );
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $key ) );
		if ( 1 === wp_rand( 1, 50 ) ) {
			// Past windows: the name ends with the window's reset time.
			$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE option_name LIKE %s AND CAST( SUBSTRING_INDEX( option_name, '_', -1 ) AS UNSIGNED ) < %d LIMIT 500", $wpdb->options, $wpdb->esc_like( 'rtbp_rl_' ) . '%', time() ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return max( 1, $count );
	}

	/**
	 * The client as a counter key: a hash of its IPv4 address, or of its IPv6
	 * network prefix (the first 64 bits: one connection is given a whole
	 * prefix, so counting single IPv6 addresses would let a visitor rotate
	 * past every limit).
	 *
	 * @return string 32 hex characters.
	 */
	public static function clientKey(): string {
		$ip = self::clientIp();
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = inet_pton( $ip );
			$ip     = false !== $packed ? bin2hex( substr( $packed, 0, 8 ) ) . '::' : $ip;
		}
		return md5( $ip );
	}

	/**
	 * The client's address: the TCP peer, unless `rtbp_activity_ip` says
	 * otherwise (trusted proxies).
	 *
	 * @return string
	 */
	public static function clientIp(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
		/** This filter is documented in includes/ActivityLog/Activity.php */
		return (string) apply_filters( 'rtbp_activity_ip', $ip );
	}

	/**
	 * Pass the request through (no limit).
	 *
	 * @param WP_REST_Request $request The request.
	 * @param callable        $next    The next handler.
	 * @return mixed
	 */
	public function byPassRequest( WP_REST_Request $request, callable $next ) {
		return $next( $request );
	}
}
