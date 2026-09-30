<?php
/**
 * Loading one booking with everything its screen shows (M03).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Booking;

use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;

defined( 'ABSPATH' ) || exit;

/**
 * One booking in a fixed number of queries, whatever its size: the booking,
 * its lines, its guest, the room type names and the rooms' current state
 * (one `IN` query each), and the staff who touched it (WordPress's user
 * cache). The screen never needs a second request.
 */
class BookingQuery {

	/**
	 * Load a booking.
	 *
	 * @param int $id Booking id.
	 * @return array{ booking: Booking, lines: \RadiusTheme\RadiusHotelBooking\Models\BookingRoom[], guest: ?\RadiusTheme\RadiusHotelBooking\Models\Guest, room_types: array<int,string>, rooms: array<int,array>, users: array<int,string> }
	 * @throws DomainException 404.
	 */
	public function load( int $id ): array {
		global $wpdb;
		$booking = $id > 0 ? ( new BookingRepository() )->find( $id ) : null;
		if ( ! $booking instanceof Booking ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This booking does not exist.', 'radius-hotel-booking' ) );
		}
		$lines = ( new BookingRoomRepository() )->forBooking( (int) $booking->id );
		$guest = $booking->guest_id ? ( new GuestRepository() )->find( (int) $booking->guest_id ) : null;

		$type_ids   = array_values( array_unique( array_map( static fn( $line ) => (int) $line->room_type_id, $lines ) ) );
		$room_ids   = array_values( array_unique( array_map( static fn( $line ) => (int) $line->room_id, $lines ) ) );
		$room_types = array();
		$rooms      = array();
		if ( $type_ids ) {
			$in = implode( ',', array_fill( 0, count( $type_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders built above.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM %i WHERE id IN ( $in )", array_merge( array( rtbp_table( 'room_types' ) ), $type_ids ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $rows as $row ) {
				$room_types[ (int) $row['id'] ] = (string) $row['name'];
			}
		}
		if ( $room_ids ) {
			$in = implode( ',', array_fill( 0, count( $room_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders built above.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, number, state, deleted_at FROM %i WHERE id IN ( $in )", array_merge( array( rtbp_table( 'rooms' ) ), $room_ids ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $rows as $row ) {
				$rooms[ (int) $row['id'] ] = array(
					'number'  => (string) $row['number'],
					'state'   => (string) $row['state'],
					'removed' => null !== $row['deleted_at'],
				);
			}
		}

		$user_ids = array_filter(
			array_merge(
				array( (int) $booking->created_by, (int) ( $booking->approved_by ?? 0 ) ),
				array_map( static fn( $line ) => (int) $line->checked_in_by, $lines ),
				array_map( static fn( $line ) => (int) $line->checked_out_by, $lines )
			)
		);
		$users    = array();
		foreach ( array_unique( $user_ids ) as $user_id ) {
			$user                = get_userdata( $user_id );
			$users[ $user_id ] = $user ? (string) $user->display_name : '';
		}

		return array(
			'booking'    => $booking,
			'lines'      => $lines,
			'guest'      => $guest,
			'room_types' => $room_types,
			'rooms'      => $rooms,
			'users'      => $users,
		);
	}
}
