<?php
/**
 * The room types table (M06).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_room_types`: what the hotel sells (*Standard Room*,
 * *Room VIP*; features 6.1, 6.12, 6.13). A room type owns its rooms.
 */
class RoomTypesTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'room_types',
			function ( Blueprint $table ) {
				$table->id();
				$table->string( 'name', 120 );
				$table->string( 'slug', 140 );
				$table->longText( 'description' )->nullable();
				$table->text( 'short_description' )->nullable();
				$table->text( 'gallery' )->nullable();
				$table->unsignedBigInteger( 'featured_image_id' )->default( 0 );
				$table->text( 'amenities' )->nullable();
				$table->string( 'bed_info', 191 )->default( '' );
				$table->decimal( 'size_m2', 8, 2 )->nullable();
				$table->unsignedInteger( 'max_adults' )->default( 2 );
				$table->unsignedInteger( 'max_children' )->default( 0 );
				$table->unsignedInteger( 'buffer_minutes' )->nullable();
				$table->boolean( 'is_active' )->default( 1 );
				$table->unsignedInteger( 'sort_order' )->default( 0 );
				$table->timestamps();
				$table->softDeletes();

				$table->unique( 'slug', 'slug' );
				$table->index( array( 'is_active', 'sort_order' ), 'active_order' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'room_types' );
	}
}
