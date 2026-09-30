<?php
/**
 * The payments ledger (M05).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_payments`: the ledger (ADR-010), append-only. `type`
 * is `payment`, `refund`, `adjustment` or `void`; `amount` is signed as it
 * counts towards what was paid (a refund or a void is negative), so a
 * booking's `paid_total` is the plain sum. A void points at the row it
 * cancels (`voids_payment_id`); nothing is ever edited or deleted. Each
 * payment gets a receipt number (M05 T4a).
 */
class PaymentsTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'payments',
			function ( Blueprint $table ) {
				$table->id();
				$table->unsignedBigInteger( 'booking_id' );
				$table->string( 'type', 20 )->default( 'payment' );
				$table->decimal( 'amount', 12, 2 );
				$table->string( 'method', 40 )->default( '' );
				$table->string( 'reference', 100 )->default( '' );
				$table->text( 'note' )->nullable();
				$table->unsignedBigInteger( 'voids_payment_id' )->nullable();
				$table->dateTime( 'received_at' );
				$table->dateTime( 'received_at_gmt' );
				$table->unsignedBigInteger( 'recorded_by' )->nullable();
				$table->string( 'receipt_no', 40 )->nullable();
				$table->timestamps();

				$table->index( 'booking_id', 'booking' );
				$table->index( 'received_at_gmt', 'received_gmt' );
				$table->index( array( 'method', 'received_at_gmt' ), 'method_received' );
				$table->index( 'voids_payment_id', 'voids' );
				$table->unique( 'receipt_no', 'receipt_no' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'payments' );
	}
}
