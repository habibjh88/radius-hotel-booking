<?php
/**
 * Named counters.
 *
 * @package RadiusTheme\RadiusHotelBooking\Support
 */

namespace RadiusTheme\RadiusHotelBooking\Support;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Unbroken counters for human references: `Sequence::next( 'booking' )`.
 *
 * `next()` increments the counter row with the documented MySQL counter
 * pattern (`UPDATE … SET value = LAST_INSERT_ID( value + 1 )`), which is atomic
 * and row-locks the counter. **Call it inside the Transaction that writes the
 * record the number belongs to**: the lock is then held until commit, and a
 * rollback undoes the increment, so invoice numbers never skip (M05, 5.8).
 * Outside a transaction it is still atomic and unique, just not gap-free if
 * the caller later fails.
 */
class Sequence {

	/**
	 * Counter names: lowercase letters, digits, underscores.
	 */
	const NAME_PATTERN = '/^[a-z0-9_]{1,64}$/';

	/**
	 * The next value of a counter, creating it on first use.
	 *
	 * @param string $name  Counter name, e.g. 'booking', 'invoice_2026'.
	 * @param int    $start First value handed out for a new counter.
	 * @return int
	 * @throws RuntimeException When the name is invalid or the database fails.
	 */
	public static function next( string $name, int $start = 1 ): int {
		global $wpdb;

		self::assert_name( $name );
		$table = self::table();
		$now   = Dates::to_gmt_db( Dates::now() );

		$increment = static fn() => $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$table} SET value = LAST_INSERT_ID( value + 1 ), updated_at = %s WHERE name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				$name
			)
		);

		// UPDATE first: it takes the exclusive row lock directly. Running
		// INSERT IGNORE first on an existing row takes a shared lock that the
		// UPDATE then has to upgrade — two concurrent transactions deadlock on
		// that upgrade (found by the concurrency check, M00 T3).
		$updated = $increment();

		if ( 0 === $updated ) {
			// First use of this counter: create it one below the first number.
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (name, value, updated_at) VALUES (%s, %d, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$name,
					max( 0, $start - 1 ),
					$now
				)
			);
			$updated = $increment();
		}

		if ( 1 !== $updated ) {
			throw new RuntimeException( sprintf( 'Sequence "%s" could not be incremented.', esc_html( $name ) ) );
		}

		return (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * The last value handed out (0 if the counter was never used). Read only;
	 * never use it to predict the next number.
	 *
	 * @param string $name Counter name.
	 * @return int
	 */
	public static function current( string $name ): int {
		global $wpdb;

		self::assert_name( $name );
		$table = self::table();

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT value FROM {$table} WHERE name = %s", $name ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * A formatted reference: `format( 'RT-2026-', 841 )` → `RT-2026-000841`.
	 *
	 * @param string $prefix Prefix.
	 * @param int    $number Number.
	 * @param int    $pad    Minimum digits.
	 * @return string
	 */
	public static function format( string $prefix, int $number, int $pad = 6 ): string {
		return $prefix . str_pad( (string) $number, $pad, '0', STR_PAD_LEFT );
	}

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	private static function table(): string {
		return rtbp_table_prefix() . 'sequences';
	}

	/**
	 * Reject names that could not be a counter.
	 *
	 * @param string $name Name.
	 * @return void
	 * @throws RuntimeException When invalid.
	 */
	private static function assert_name( string $name ): void {
		if ( ! preg_match( self::NAME_PATTERN, $name ) ) {
			throw new RuntimeException( sprintf( 'Invalid sequence name "%s".', esc_html( $name ) ) );
		}
	}
}
