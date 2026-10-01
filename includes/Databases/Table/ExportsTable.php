<?php
/**
 * The exports table (M11).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * One row per export file (the library, 11.5): what was exported (`kind`
 * bookings | guests | report | activity, `params` JSON: period, mode,
 * separator…), the `format`, how many rows, the protected file (`file_token`,
 * `file_sha256`), what an archive-and-remove took out (`removed_count`, Pro),
 * `status` (done | failed | removed when the file was deleted), who made it.
 */
class ExportsTable extends Migration {

	/**
	 * Create or upgrade the table (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'exports',
			function ( Blueprint $table ) {
				$table->id();
				$table->string( 'kind', 20 );
				$table->text( 'params' );
				$table->string( 'format', 10 );
				$table->unsignedInteger( 'row_count' )->default( 0 );
				$table->char( 'file_token', 32 )->default( '' );
				$table->char( 'file_sha256', 64 )->default( '' );
				$table->unsignedInteger( 'removed_count' )->default( 0 );
				$table->string( 'status', 20 )->default( 'done' );
				$table->string( 'source', 20 )->default( 'manual' );
				$table->unsignedBigInteger( 'created_by' )->nullable();
				$table->timestamps();

				$table->index( array( 'kind', 'created_at' ), 'kind_created' );
				$table->index( 'file_token', 'file_token' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'exports' );
	}
}
