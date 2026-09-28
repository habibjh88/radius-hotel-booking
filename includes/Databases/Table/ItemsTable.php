<?php
/**
 * Migration for the example `items` table.
 *
 * BOILERPLATE: this is the one example table shipped with the plugin. Copy it
 * (or run `wp radius-hotel-booking artisan make:table Things`) for your own
 * tables, then register the class in Databases\DatabaseManager.
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
 * Creates and drops the `items` table.
 */
class ItemsTable extends Migration {

	/**
	 * Create the table.
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'items',
			function ( Blueprint $table ) {
				$table->id();
				$table->string( 'title' )->default( '' );
				$table->text( 'description' )->nullable();
				$table->enum( 'status', array( 'draft', 'published', 'archived' ) )->default( 'draft' );
				$table->decimal( 'price', 10, 2 )->default( 0 );
				$table->unsignedInteger( 'position' )->default( 0 );
				$table->json( 'meta' )->nullable()->default( null );
				$table->timestamps();

				$table->index( 'status' );
				$table->index( 'position' );
			}
		);
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'items' );
	}
}
