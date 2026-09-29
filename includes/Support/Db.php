<?php
/**
 * Small database helpers.
 *
 * @package RadiusTheme\RadiusHotelBooking\Support
 */

namespace RadiusTheme\RadiusHotelBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers around `$wpdb` for writes whose failure is an expected outcome.
 */
final class Db {

	/**
	 * Run a write without wpdb printing its error. A UNIQUE index refusing a
	 * row is how a lost race ends (two rooms numbered A3 at once); with
	 * WP_DEBUG on, wpdb would print that error as HTML into the REST response.
	 * The error is still recorded: see duplicateKey().
	 *
	 * @param callable $write The write.
	 * @return mixed Its return value.
	 */
	public static function quietly( callable $write ) {
		global $wpdb;
		$previous = $wpdb->suppress_errors( true );
		try {
			return $write();
		} finally {
			$wpdb->suppress_errors( $previous );
		}
	}

	/**
	 * Whether the last query failed on a UNIQUE index (MySQL error 1062).
	 *
	 * @return bool
	 */
	public static function duplicateKey(): bool {
		global $wpdb;
		return str_starts_with( (string) $wpdb->last_error, 'Duplicate entry' );
	}
}
