<?php
/**
 * Migration for the `files` table.
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
 * Index of the files kept in protected storage (ADR-009): invoices, receipts,
 * exports, archives, employee documents. Other tables point at a file by its
 * `token`; the token is also the only way to download it.
 */
class FilesTable extends Migration {

	/**
	 * Create the table.
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'files',
			function ( Blueprint $table ) {
				$table->id();
				$table->char( 'token', 32 );
				$table->string( 'kind', 40 );
				$table->string( 'path', 255 );
				$table->string( 'original_name', 255 );
				$table->string( 'mime', 100 );
				$table->unsignedBigInteger( 'size' )->default( 0 );
				$table->char( 'sha256', 64 );
				$table->unsignedBigInteger( 'created_by' )->nullable();
				$table->timestamps();

				$table->unique( 'token' );
				$table->index( 'kind' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'files' );
	}
}
