<?php
/**
 * The notes table (M09).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_notes`: author-stamped notes shared by guests,
 * bookings and employees (`notable_type` + `notable_id`). `type` is
 * `general`, `caution` or `warning` (the legacy note types). The author's
 * name is kept, so a note still reads right after the user is removed.
 */
class NotesTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'notes',
			function ( Blueprint $table ) {
				$table->id();
				$table->string( 'notable_type', 30 );
				$table->unsignedBigInteger( 'notable_id' );
				$table->string( 'type', 20 )->default( 'general' );
				$table->text( 'body' );
				$table->unsignedBigInteger( 'author_id' )->nullable();
				$table->string( 'author_name', 191 )->default( '' );
				$table->unsignedBigInteger( 'edited_by' )->nullable();
				$table->timestamps();

				$table->index( array( 'notable_type', 'notable_id' ), 'notable' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'notes' );
	}
}
