<?php
/**
 * The amenities table.
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_amenities`: the shared, ordered amenity library that
 * room types pick from. A room type keeps the chosen names in its own
 * `amenities` list; the library keeps those names in step (rename, delete).
 */
class AmenitiesTable extends Migration {

	/**
	 * Set once the existing room type amenities were copied into the library.
	 */
	const SEEDED_OPTION = 'rtbp_amenities_seeded';

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'amenities',
			function ( Blueprint $table ) {
				$table->id();
				$table->string( 'name', 60 );
				$table->unsignedInteger( 'sort_order' )->default( 0 );
				$table->timestamps();

				$table->index( 'sort_order', 'sort_order' );
			}
		);

		$this->seed();
	}

	/**
	 * Copy the amenities room types already list into the library, once, in
	 * room type order. Names that differ only by case become one entry.
	 *
	 * @return void
	 */
	private function seed(): void {
		global $wpdb;
		if ( get_option( self::SEEDED_OPTION ) ) {
			return;
		}
		$table = rtbp_table( 'amenities' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time seed check.
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		if ( null === $count ) {
			return; // The table is not there: try again on the next migration run.
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time seed read.
		$lists = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT amenities FROM %i WHERE deleted_at IS NULL ORDER BY sort_order, name', rtbp_table( 'room_types' ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time seed read.
		$known = array_map( 'mb_strtolower', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT name FROM %i', $table ) ) );
		$names = array();
		foreach ( $lists as $list ) {
			foreach ( self::decode( $list ) as $name ) {
				$name = mb_substr( sanitize_text_field( (string) $name ), 0, 60 );
				$key  = mb_strtolower( $name );
				if ( '' !== $name && ! in_array( $key, $known, true ) ) {
					$known[] = $key;
					$names[] = $name;
				}
			}
		}

		$now   = current_time( 'mysql' );
		$order = (int) $count;
		foreach ( $names as $name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- seed insert.
			$wpdb->insert(
				$table,
				array(
					'name'       => $name,
					'sort_order' => $order++,
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
		}
		update_option( self::SEEDED_OPTION, 1, false );
	}

	/**
	 * A stored amenity list: the ORM's json cast writes it PHP-serialised,
	 * older rows may hold JSON.
	 *
	 * @param mixed $value Column value.
	 * @return array
	 */
	private static function decode( $value ): array {
		$value = maybe_unserialize( (string) $value );
		if ( is_array( $value ) ) {
			return $value;
		}
		$json = json_decode( (string) $value, true );
		return is_array( $json ) ? $json : array();
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'amenities' );
		delete_option( self::SEEDED_OPTION );
	}
}
