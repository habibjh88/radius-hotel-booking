<?php
/**
 * The booking line status machine (M03).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Booking;

defined( 'ABSPATH' ) || exit;

/**
 * Every legal move of a booking line, in one table (booking-engine §9, the
 * M03 diagram), and the booking's stored summary status derived from its
 * lines. A move not in the table is illegal (`illegal_transition`).
 *
 * The moves:
 *
 *     pending ──approve──► confirmed ──check_in──► checked_in ──check_out──► checked_out
 *        ├──decline──► declined          └──no_show──► no_show
 *        └──cancel───► cancelled ◄──cancel── confirmed
 */
final class StatusMachine {

	public const PENDING     = 'pending';
	public const CONFIRMED   = 'confirmed';
	public const CHECKED_IN  = 'checked_in';
	public const CHECKED_OUT = 'checked_out';
	public const DECLINED    = 'declined';
	public const CANCELLED   = 'cancelled';
	public const NO_SHOW     = 'no_show';

	/**
	 * Action => from status => to status.
	 */
	public const MOVES = array(
		'approve'   => array( self::PENDING => self::CONFIRMED ),
		'decline'   => array( self::PENDING => self::DECLINED ),
		'cancel'    => array(
			self::PENDING   => self::CANCELLED,
			self::CONFIRMED => self::CANCELLED,
		),
		'check_in'  => array( self::CONFIRMED => self::CHECKED_IN ),
		'check_out' => array( self::CHECKED_IN => self::CHECKED_OUT ),
		'no_show'   => array( self::CONFIRMED => self::NO_SHOW ),
	);

	/**
	 * Statuses a line never leaves.
	 */
	public const FINAL = array( self::CHECKED_OUT, self::DECLINED, self::CANCELLED, self::NO_SHOW );

	/**
	 * The status an action moves a line to, or null when it is not a legal move.
	 *
	 * @param string $action Action.
	 * @param string $from   Current line status.
	 * @return string|null
	 */
	public static function to( string $action, string $from ): ?string {
		return self::MOVES[ $action ][ $from ] ?? null;
	}

	/**
	 * The actions legal from a line status.
	 *
	 * @param string $from Line status.
	 * @return string[]
	 */
	public static function actions( string $from ): array {
		$out = array();
		foreach ( self::MOVES as $action => $moves ) {
			if ( isset( $moves[ $from ] ) ) {
				$out[] = $action;
			}
		}
		return $out;
	}

	/**
	 * The booking's summary status from its line statuses: pending if any
	 * line is pending; checked in if any is checked in; checked out if every
	 * live line is; cancelled / declined / no-show when every line ended that
	 * way (a mix of those reads cancelled); confirmed otherwise.
	 *
	 * @param string[] $statuses Line statuses.
	 * @return string
	 */
	public static function summary( array $statuses ): string {
		$statuses = array_values( $statuses );
		if ( ! $statuses ) {
			return self::CANCELLED;
		}
		if ( in_array( self::PENDING, $statuses, true ) ) {
			return self::PENDING;
		}
		if ( in_array( self::CHECKED_IN, $statuses, true ) ) {
			return self::CHECKED_IN;
		}
		$live = array_diff( $statuses, array( self::DECLINED, self::CANCELLED, self::NO_SHOW ) );
		if ( ! $live ) {
			$ended = array_unique( $statuses );
			return 1 === count( $ended ) ? (string) reset( $ended ) : self::CANCELLED;
		}
		if ( ! array_diff( $live, array( self::CHECKED_OUT ) ) ) {
			return self::CHECKED_OUT;
		}
		return self::CONFIRMED;
	}
}
