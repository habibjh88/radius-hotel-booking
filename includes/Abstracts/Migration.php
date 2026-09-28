<?php
/**
 * Migration.php
 *
 * This file defines the base Migration class, which serves as a blueprint for creating database migrations in the Radius Hotel Booking plugin.
 * It provides methods to run and reverse migrations, ensuring that database schema changes can be applied and rolled back as needed.
 *
 * @package RadiusTheme\RadiusHotelBooking\Abstracts
 * @since 1.0.0
 */

namespace RadiusTheme\RadiusHotelBooking\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Base Migration class.
 *
 * This abstract class defines the structure for database migrations.
 *
 * @package RadiusTheme\RadiusHotelBooking\Abstracts
 * @since 1.0.0
 */
abstract class Migration {

	/**
	 * Table prefix used for all tables in this migration.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $tablePrefix = 'radius_hotel_booking_';

	/**
	 * Run the migration.
	 *
	 * This method should contain all operations to apply the migration,
	 * such as creating or altering tables.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	abstract public function up(): void;

	/**
	 * Reverse the migration.
	 *
	 * This method should undo the operations done by the `up()` method,
	 * such as dropping or reverting changes to tables.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	abstract public function down(): void;
}
