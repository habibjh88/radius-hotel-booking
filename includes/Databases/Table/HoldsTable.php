<?php
/**
 * The holds table (M08, feature 2.14).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_holds`: a room kept for a booking being completed
 * (booking-engine §7). A hold occupies its room while `expires_at_gmt` is in
 * the future, except for the requester presenting the same `token`.
 *
 * - Desk holds carry `user_id`; web holds carry `session_key`.
 * - Expired rows are ignored by every query at once and deleted by the sweep.
 */
class HoldsTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'holds',
			function ( Blueprint $table ) {
				$table->id();
				$table->unsignedBigInteger( 'room_id' );
				$table->unsignedBigInteger( 'room_type_id' );
				$table->unsignedBigInteger( 'rate_plan_id' );
				$table->dateTime( 'start_at_gmt' );
				$table->dateTime( 'end_at_gmt' );
				$table->string( 'token', 64 );
				$table->unsignedBigInteger( 'user_id' )->nullable();
				$table->string( 'session_key', 64 )->default( '' );
				$table->dateTime( 'expires_at_gmt' );
				$table->timestamps();

				$table->index( array( 'room_id', 'start_at_gmt', 'end_at_gmt' ), 'room_window' );
				$table->index( 'expires_at_gmt', 'expires' );
				$table->index( 'token', 'token' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'holds' );
	}
}
