<?php
/**
 * Migration for the `sequences` table.
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

/**
 * Named counters for human references (booking `RT-2026-000841`, invoice
 * numbers). One row per counter, incremented by Support\Sequence under a row
 * lock, so numbers are unbroken even under concurrent requests.
 */
class SequencesTable extends Migration {

	/**
	 * Create the table.
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'sequences',
			function ( Blueprint $table ) {
				$table->id();
				$table->string( 'name', 64 );
				$table->unsignedBigInteger( 'value' )->default( 0 );
				$table->dateTime( 'updated_at' )->nullable();

				$table->unique( 'name' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'sequences' );
	}
}
