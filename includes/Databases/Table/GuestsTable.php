<?php
/**
 * The guests table (M09).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_guests`: one row per real person (not a WordPress
 * user; `wp_user_id` may link one later).
 *
 * - `name_search`: "first last", accents removed and lower-cased, for search.
 * - `phone_e164` (UNIQUE, NULL when unknown) and `phone_tail` (the last 8
 *   digits: matches a number written in the old 8-digit form and the new
 *   10-digit one, as the legacy search did).
 * - `email_key`: the lower-cased e-mail, NULL for a placeholder; carries the
 *   UNIQUE key MySQL cannot put on "email where not a placeholder".
 */
class GuestsTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'guests',
			function ( Blueprint $table ) {
				$table->id();
				$table->string( 'reference', 20 );
				$table->string( 'first_name', 100 )->default( '' );
				$table->string( 'last_name', 100 )->default( '' );
				$table->string( 'name_search', 191 )->default( '' );
				$table->string( 'phone', 40 )->default( '' );
				$table->string( 'phone_e164', 20 )->nullable();
				$table->string( 'phone_tail', 8 )->default( '' );
				$table->string( 'email', 191 )->default( '' );
				$table->string( 'email_key', 191 )->nullable();
				$table->boolean( 'email_is_placeholder' )->default( 0 );
				$table->string( 'id_type', 30 )->default( '' );
				$table->string( 'id_number', 60 )->default( '' );
				$table->string( 'standing', 20 )->default( 'normal' );
				$table->string( 'ban_reason', 191 )->default( '' );
				$table->dateTime( 'banned_at' )->nullable();
				$table->unsignedBigInteger( 'banned_by' )->nullable();
				$table->unsignedBigInteger( 'wp_user_id' )->nullable();
				$table->unsignedInteger( 'stays_count' )->default( 0 );
				$table->dateTime( 'last_stay_at' )->nullable();
				$table->unsignedBigInteger( 'created_by' )->nullable();
				$table->timestamps();
				$table->softDeletes();

				$table->unique( 'reference', 'reference' );
				$table->unique( 'phone_e164', 'phone_e164' );
				$table->unique( 'email_key', 'email_key' );
				$table->index( 'name_search', 'name_search' );
				$table->index( 'phone_tail', 'phone_tail' );
				$table->index( 'standing', 'standing' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'guests' );
	}
}
