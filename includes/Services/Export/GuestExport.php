<?php
/**
 * The guest list export (M11, 11.7).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Export
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Export;

use RadiusTheme\RadiusHotelBooking\Services\Guests\GuestService;

defined( 'ABSPATH' ) || exit;

/**
 * Every guest on file (not deleted), one row each, oldest first — the whole
 * list, not a period. A placeholder e-mail (made up when the desk had none)
 * is left empty. Read in chunks of 500 by id.
 */
class GuestExport {

	/**
	 * Guests per read.
	 */
	public const CHUNK = 500;

	/**
	 * Header labels.
	 *
	 * @return string[]
	 */
	public static function columns(): array {
		return array(
			__( 'Guest Reference', 'radius-hotel-booking' ),
			__( 'First Name', 'radius-hotel-booking' ),
			__( 'Last Name', 'radius-hotel-booking' ),
			__( 'Phone', 'radius-hotel-booking' ),
			__( 'Email', 'radius-hotel-booking' ),
			__( 'ID Type', 'radius-hotel-booking' ),
			__( 'ID Number', 'radius-hotel-booking' ),
			__( 'Standing', 'radius-hotel-booking' ),
			__( 'Ban Reason', 'radius-hotel-booking' ),
			__( 'Stays', 'radius-hotel-booking' ),
			__( 'Last Stay', 'radius-hotel-booking' ),
			__( 'Website Account', 'radius-hotel-booking' ),
			__( 'Added On', 'radius-hotel-booking' ),
		);
	}

	/**
	 * Every row, a chunk at a time.
	 *
	 * @return \Generator<array>
	 * @throws \RuntimeException When a read fails.
	 */
	public function rows(): \Generator {
		global $wpdb;
		$types = GuestService::idTypes();
		$after = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an export reads each row once.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE deleted_at IS NULL AND id > %d ORDER BY id LIMIT %d',
					rtbp_table( 'guests' ),
					$after,
					self::CHUNK
				),
				ARRAY_A
			);
			if ( null === $rows || '' !== (string) $wpdb->last_error ) {
				throw new \RuntimeException( 'Reading the guests for the export failed: ' . esc_html( (string) $wpdb->last_error ) );
			}
			$read = count( $rows );
			foreach ( $rows as $guest ) {
				$after   = (int) $guest['id'];
				$account = (int) $guest['wp_user_id'] ? get_userdata( (int) $guest['wp_user_id'] ) : null;
				yield array(
					(string) $guest['reference'],
					(string) $guest['first_name'],
					(string) $guest['last_name'],
					(string) $guest['phone'],
					(int) $guest['email_is_placeholder'] ? '' : (string) $guest['email'],
					(string) ( $types[ (string) $guest['id_type'] ] ?? $guest['id_type'] ),
					(string) $guest['id_number'],
					'banned' === (string) $guest['standing'] ? __( 'Banned', 'radius-hotel-booking' ) : __( 'Normal', 'radius-hotel-booking' ),
					(string) $guest['ban_reason'],
					(int) $guest['stays_count'],
					substr( (string) $guest['last_stay_at'], 0, 16 ),
					$account ? (string) $account->user_login : '',
					substr( (string) $guest['created_at'], 0, 16 ),
				);
			}
		} while ( self::CHUNK === $read );
	}
}
