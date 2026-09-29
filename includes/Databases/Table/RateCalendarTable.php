<?php
/**
 * The rate calendar table (M08, features 8.2–8.4).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_rate_calendar`: per-date exceptions of a room type.
 *
 * - `rate_plan_id` 0 = the whole room type (8.4). Architecture §4.3 had NULL,
 *   but MySQL lets a UNIQUE key hold any number of NULLs, so the key would not
 *   stop two whole-type rows for one date.
 * - `price_override` NULL = no override; it replaces base + sale for that
 *   rate and date (booking-engine §6 step 3). Meaningless on a whole-type row.
 * - `is_closed` closes the rate (or, with plan 0, the room type) that date.
 * - `date` is the local date a unit starts on.
 */
class RateCalendarTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'rate_calendar',
			function ( Blueprint $table ) {
				$table->id();
				$table->unsignedBigInteger( 'room_type_id' );
				$table->unsignedBigInteger( 'rate_plan_id' )->default( 0 );
				$table->date( 'date' );
				$table->decimal( 'price_override', 12, 2 )->nullable();
				$table->boolean( 'is_closed' )->default( 0 );
				$table->timestamps();

				$table->unique( array( 'room_type_id', 'rate_plan_id', 'date' ), 'type_plan_date' );
				$table->index( array( 'room_type_id', 'date' ), 'type_date' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'rate_calendar' );
	}
}
