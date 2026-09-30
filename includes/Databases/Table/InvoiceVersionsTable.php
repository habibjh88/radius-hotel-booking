<?php
/**
 * The invoice versions table (M05).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_invoice_versions`: every version an invoice was
 * issued in, with the full snapshot it showed (JSON: hotel, guest, lines,
 * totals), so a printed or sent invoice can always be reproduced.
 */
class InvoiceVersionsTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'invoice_versions',
			function ( Blueprint $table ) {
				$table->id();
				$table->unsignedBigInteger( 'invoice_id' );
				$table->unsignedInteger( 'version' );
				$table->longText( 'snapshot' );
				$table->string( 'file_token', 64 )->nullable();
				$table->timestamps();

				$table->unique( array( 'invoice_id', 'version' ), 'invoice_version' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'invoice_versions' );
	}
}
