<?php
/**
 * The room type rates table (M07).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_room_type_rates`: "room type X sells rate plan Y at
 * price P" (features 7.6–7.9). One row per pair (UNIQUE); switching a rate
 * off keeps its row (`enabled = 0`). `sale_price` NULL means no sale;
 * `max_units` NULL means no maximum.
 */
class RoomTypeRatesTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'room_type_rates',
			function ( Blueprint $table ) {
				$table->id();
				$table->unsignedBigInteger( 'room_type_id' );
				$table->unsignedBigInteger( 'rate_plan_id' );
				$table->decimal( 'price', 12, 2 )->default( 0 );
				$table->decimal( 'sale_price', 12, 2 )->nullable();
				$table->unsignedInteger( 'min_units' )->default( 1 );
				$table->unsignedInteger( 'max_units' )->nullable();
				$table->boolean( 'enabled' )->default( 0 );
				$table->unsignedInteger( 'sort_order' )->default( 0 );
				$table->timestamps();

				$table->unique( array( 'room_type_id', 'rate_plan_id' ), 'type_plan' );
				$table->index( 'rate_plan_id', 'rate_plan' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'room_type_rates' );
	}
}
