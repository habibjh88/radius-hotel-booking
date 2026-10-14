<?php
/**
 * The amenity sections table.
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_amenity_sections`: the ordered groups of the
 * amenity library (Room comfort, Bathroom …). Each amenity belongs to one
 * (`amenities.section_id`); room type pages list amenities by section.
 */
class AmenitySectionsTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'amenity_sections',
			function ( Blueprint $table ) {
				$table->id();
				$table->string( 'name', 60 );
				$table->unsignedInteger( 'sort_order' )->default( 0 );
				$table->timestamps();

				$table->index( 'sort_order', 'sort_order' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'amenity_sections' );
	}
}
