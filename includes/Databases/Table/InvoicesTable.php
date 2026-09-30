<?php
/**
 * The invoices table (M05).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_invoices`: one invoice per booking, numbered from
 * an unbroken sequence (5.8). When the booking's rooms change it is
 * re-issued as a new `version` under the **same** number (the old versions
 * stay in `invoice_versions`); a cancelled booking's invoice is marked
 * `cancelled`, never deleted. `totals` is the snapshot of the current
 * version (JSON).
 */
class InvoicesTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'invoices',
			function ( Blueprint $table ) {
				$table->id();
				$table->unsignedBigInteger( 'booking_id' );
				$table->string( 'number', 40 );
				$table->unsignedInteger( 'version' )->default( 1 );
				$table->string( 'status', 20 )->default( 'issued' );
				$table->longText( 'totals' )->nullable();
				$table->string( 'file_token', 64 )->nullable();
				$table->dateTime( 'issued_at' );
				$table->dateTime( 'issued_at_gmt' );
				$table->dateTime( 'sent_at' )->nullable();
				$table->timestamps();

				$table->unique( 'booking_id', 'booking' );
				$table->unique( 'number', 'number' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'invoices' );
	}
}
