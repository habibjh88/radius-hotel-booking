<?php
/**
 * Keeps each guest's stay count and last stay in step with their bookings.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Guests
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Guests;

use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Recounts `stays_count` and `last_stay_at` (9.4) after a booking is made or
 * changes, and once for every guest after an upgrade.
 */
class GuestStays {

	/**
	 * Option set once every guest has been counted.
	 */
	public const COUNTED = 'rtbp_guest_stays_counted';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init(): void {
		foreach ( array( 'rtbp_booking_created', 'rtbp_booking_changed', 'rtbp_booking_status_changed' ) as $hook ) {
			add_action( $hook, array( self::class, 'booking' ), 20 );
		}
	}

	/**
	 * Recount the guest of a booking that was made or changed.
	 *
	 * @param object $booking The booking.
	 * @return void
	 */
	public static function booking( $booking ): void {
		$guest_id = (int) ( $booking->guest_id ?? 0 );
		if ( $guest_id <= 0 ) {
			return;
		}
		try {
			( new GuestRepository() )->refreshStays( $guest_id );
		} catch ( \Throwable $e ) {
			// The booking is already saved: a failed count must not turn it into an error.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- say why the count is behind.
			error_log( '[radius-hotel-booking] Guest stay count failed for guest ' . $guest_id . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Count every guest once (the counts were never filled before 1.0.21).
	 *
	 * @return void
	 */
	public static function countAllOnce(): void {
		if ( get_option( self::COUNTED ) ) {
			return;
		}
		( new GuestRepository() )->refreshStays();
		update_option( self::COUNTED, 1, false );
	}
}
