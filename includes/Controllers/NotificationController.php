<?php
/**
 * New-booking alerts: the poll behind every staff screen (M01, 1.12, ADR-006).
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

defined( 'ABSPATH' ) || exit;

use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AccessMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Services\Dashboard\DashboardCounters;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use WP_REST_Request;

/**
 * GET notifications/poll?since=<cursor> (`page.dashboard`): the bookings made
 * since the last poll by **someone else** (a guest on the website, another
 * desk), and the number awaiting approval for the bell.
 *
 * The cursor is the highest booking id seen: ids only grow, so no booking is
 * missed or repeated whatever the clocks say. A poll without a cursor only
 * returns the current one — opening a screen never replays old bookings.
 * At most 10 bookings per answer, newest first. Three light queries (the
 * highest id, the new bookings, and the counters — cached 15 s).
 */
class NotificationController {

	/**
	 * Most bookings returned by one poll.
	 */
	public const LIMIT = 10;

	/**
	 * GET notifications/poll.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function poll( WP_REST_Request $request ) {
		$auth       = new AuthMiddleware();
		$permission = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$access     = new AccessMiddleware( 'page.dashboard' );

		return $auth->handle(
			$request,
			fn( $request ) => $permission->handle(
				$request,
				fn( $request ) => $access->handle(
					$request,
					function ( $request ) {
						try {
							return ApiResponse::success( $this->build( (int) $request->get_param( 'since' ), get_current_user_id() ) )->send();
						} catch ( \Throwable $e ) {
							return ApiResponse::fromThrowable( $e )->send();
						}
					}
				)
			)
		);
	}

	/**
	 * The poll's answer.
	 *
	 * @param int $since   Highest booking id already seen (0 = first poll).
	 * @param int $user_id The viewer (their own bookings are not announced).
	 * @return array{cursor: int, bookings: array[], awaiting: int}
	 */
	public function build( int $since, int $user_id ): array {
		global $wpdb;
		$since = max( 0, $since );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a live poll on the primary key.
		$cursor = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE( MAX( id ), 0 ) FROM %i', rtbp_table( 'bookings' ) ) );
		$rows   = array();
		if ( $since > 0 && $cursor > $since ) {
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT b.id, b.reference, b.source, b.status, b.created_at_gmt,
						TRIM( CONCAT( COALESCE( g.first_name, '' ), ' ', COALESCE( g.last_name, '' ) ) ) AS guest,
						( SELECT MIN( r.start_at_gmt ) FROM %i r WHERE r.booking_id = b.id ) AS first_start,
						( SELECT COUNT(*) FROM %i r WHERE r.booking_id = b.id ) AS rooms
					FROM %i b LEFT JOIN %i g ON g.id = b.guest_id
					WHERE b.id > %d AND b.id <= %d AND b.deleted_at IS NULL
						AND ( b.created_by IS NULL OR b.created_by <> %d )
					ORDER BY b.id DESC
					LIMIT %d",
					rtbp_table( 'booking_rooms' ),
					rtbp_table( 'booking_rooms' ),
					rtbp_table( 'bookings' ),
					rtbp_table( 'guests' ),
					$since,
					$cursor,
					$user_id,
					self::LIMIT
				),
				ARRAY_A
			);
		}
		// phpcs:enable

		return array(
			'cursor'   => $cursor,
			'bookings' => array_map(
				static fn( $row ) => array(
					'id'          => (int) $row['id'],
					'reference'   => (string) $row['reference'],
					'guest'       => (string) $row['guest'],
					'source'      => (string) $row['source'],
					'status'      => (string) $row['status'],
					'rooms'       => (int) $row['rooms'],
					'first_start' => $row['first_start'] ? Dates::to_iso( Dates::from_gmt( (string) $row['first_start'] ) ) : null,
					'created_at'  => Dates::to_iso( Dates::from_gmt( (string) $row['created_at_gmt'] ) ),
				),
				$rows
			),
			'awaiting' => (int) ( ( new DashboardCounters() )->get()['awaiting_approval'] ?? 0 ),
		);
	}
}
