<?php
/**
 * The rooms table (M06).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_rooms`: every physical room (features 6.4–6.9).
 *
 * - `room_type_id`: the one room type that owns the room; there is no join
 *   table, so a room can never be sold under two types (6.8).
 * - `number` is UNIQUE across the property, soft-deleted rooms included
 *   (6.9); a removed room keeps its number so history stays unambiguous.
 * - `number_sort` is the natural-sort key (`NaturalSort::key()`, 6.7).
 * - `state`: available | maintenance | out_of_service (6.6).
 */
class RoomsTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'rooms',
			function ( Blueprint $table ) {
				$table->id();
				$table->unsignedBigInteger( 'room_type_id' );
				$table->unsignedBigInteger( 'floor_id' );
				$table->string( 'number', 20 );
				$table->string( 'number_sort', 64 );
				$table->string( 'state', 20 )->default( 'available' );
				$table->string( 'state_note', 191 )->default( '' );
				$table->unsignedInteger( 'sort_order' )->default( 0 );
				$table->timestamps();
				$table->softDeletes();

				$table->unique( 'number', 'number' );
				$table->index( array( 'room_type_id', 'state' ), 'type_state' );
				$table->index( 'floor_id', 'floor' );
				$table->index( 'number_sort', 'number_sort' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'rooms' );
	}
}
