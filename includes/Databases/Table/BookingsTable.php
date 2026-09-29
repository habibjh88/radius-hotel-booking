<?php
/**
 * The bookings table (schema from M08, ADR-022; M02 writes it).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_bookings`: one booking, with one or more lines in
 * `booking_rooms` (architecture §4.4).
 *
 * - `reference` is UNIQUE and comes from `Support\Sequence` inside the
 *   write transaction (gap-free).
 * - `status` summarises the lines; inventory is decided by the lines alone.
 * - Money is DECIMAL(12,2); `currency` is the site currency at creation.
 */
class BookingsTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'bookings',
			function ( Blueprint $table ) {
				$table->id();
				$table->string( 'reference', 32 );
				$table->unsignedBigInteger( 'guest_id' )->nullable();
				$table->string( 'source', 20 )->default( 'desk' );
				$table->string( 'status', 20 )->default( 'pending' );
				$table->string( 'payment_status', 20 )->default( 'unpaid' );
				$table->boolean( 'on_hold' )->default( 0 );
				$table->decimal( 'subtotal', 12, 2 )->default( 0 );
				$table->decimal( 'discount_total', 12, 2 )->default( 0 );
				$table->decimal( 'tax_total', 12, 2 )->default( 0 );
				$table->decimal( 'total', 12, 2 )->default( 0 );
				$table->decimal( 'paid_total', 12, 2 )->default( 0 );
				$table->decimal( 'balance_due', 12, 2 )->default( 0 );
				$table->string( 'currency', 8 )->default( '' );
				$table->dateTime( 'payment_due_at' )->nullable();
				$table->dateTime( 'payment_due_at_gmt' )->nullable();
				$table->unsignedInteger( 'adults' )->default( 1 );
				$table->unsignedInteger( 'children' )->default( 0 );
				$table->text( 'special_requests' )->nullable();
				$table->string( 'public_token', 64 )->nullable();
				$table->unsignedBigInteger( 'created_by' )->nullable();
				$table->unsignedBigInteger( 'approved_by' )->nullable();
				$table->dateTime( 'approved_at' )->nullable();
				$table->string( 'cancelled_reason', 191 )->default( '' );
				$table->timestamps();
				$table->dateTime( 'created_at_gmt' )->nullable();
				$table->softDeletes();

				$table->unique( 'reference', 'reference' );
				$table->index( 'status', 'status' );
				$table->index( array( 'payment_status', 'payment_due_at_gmt' ), 'payment_due' );
				$table->index( 'created_at_gmt', 'created_gmt' );
				$table->index( 'guest_id', 'guest' );
				$table->index( 'public_token', 'public_token' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'bookings' );
	}
}
