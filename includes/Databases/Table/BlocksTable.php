<?php
/**
 * The blocks table (M08, feature 8.12).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_blocks`: a dated closure of the property, a floor, a
 * room type or one room, for `[start_at, end_at)` (booking-engine §4).
 *
 * - `scope_id` is 0 for the property scope.
 * - `source` is `manual` or an external source (`ical`, through
 *   `rtbp_block_sources`); `feed_id` + `external_uid` let a sync reconcile
 *   the blocks it created.
 */
class BlocksTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'blocks',
			function ( Blueprint $table ) {
				$table->id();
				$table->string( 'scope', 20 );
				$table->unsignedBigInteger( 'scope_id' )->default( 0 );
				$table->dateTime( 'start_at' );
				$table->dateTime( 'end_at' );
				$table->dateTime( 'start_at_gmt' );
				$table->dateTime( 'end_at_gmt' );
				$table->string( 'source', 20 )->default( 'manual' );
				$table->unsignedBigInteger( 'feed_id' )->nullable();
				$table->string( 'external_uid', 191 )->default( '' );
				$table->string( 'reason', 191 )->default( '' );
				$table->unsignedBigInteger( 'created_by' )->nullable();
				$table->timestamps();

				$table->index( array( 'scope', 'scope_id', 'start_at_gmt', 'end_at_gmt' ), 'scope_window' );
				$table->index( array( 'feed_id', 'external_uid' ), 'feed_uid' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'blocks' );
	}
}
