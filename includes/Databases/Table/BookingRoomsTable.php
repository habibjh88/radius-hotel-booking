<?php
/**
 * The booking lines table (schema from M08, ADR-022; M02 writes it).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_booking_rooms`: one physical room × one window
 * `[start_at, end_at)` (booking-engine §1, architecture §4.4).
 *
 * - Room type, rate plan, room number and floor are snapshots taken at
 *   creation; the price (`unit_price`, `total`, `price_breakdown`) is frozen.
 * - `occupied_until_gmt` equals `end_at_gmt` until an early check-out moves
 *   it earlier (§4); the availability engine compares against it, never
 *   against `end_at_gmt`.
 * - The `room_window` index serves the busy-interval query (§5.3).
 */
class BookingRoomsTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'booking_rooms',
			function ( Blueprint $table ) {
				$table->id();
				$table->unsignedBigInteger( 'booking_id' );
				$table->unsignedBigInteger( 'room_id' );
				$table->unsignedBigInteger( 'room_type_id' );
				$table->unsignedBigInteger( 'rate_plan_id' );
				$table->string( 'rate_plan_name', 191 )->default( '' );
				$table->string( 'room_number', 20 )->default( '' );
				$table->string( 'floor_name', 100 )->default( '' );
				$table->dateTime( 'start_at' );
				$table->dateTime( 'end_at' );
				$table->dateTime( 'start_at_gmt' );
				$table->dateTime( 'end_at_gmt' );
				$table->dateTime( 'occupied_until_gmt' );
				$table->unsignedInteger( 'units' )->default( 1 );
				$table->unsignedInteger( 'adults' )->default( 1 );
				$table->unsignedInteger( 'children' )->default( 0 );
				$table->string( 'status', 20 )->default( 'pending' );
				$table->decimal( 'unit_price', 12, 2 )->default( 0 );
				$table->decimal( 'total', 12, 2 )->default( 0 );
				$table->longText( 'price_breakdown' )->nullable();
				$table->dateTime( 'checked_in_at' )->nullable();
				$table->dateTime( 'checked_out_at' )->nullable();
				$table->unsignedBigInteger( 'checked_in_by' )->nullable();
				$table->unsignedBigInteger( 'checked_out_by' )->nullable();
				$table->timestamps();

				$table->index( array( 'room_id', 'start_at_gmt', 'occupied_until_gmt' ), 'room_window' );
				$table->index( 'start_at_gmt', 'start_gmt' );
				$table->index( 'end_at_gmt', 'end_gmt' );
				$table->index( 'status', 'status' );
				$table->index( 'booking_id', 'booking' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'booking_rooms' );
	}
}
