<?php
/**
 * Handles the deletion of database tables, migration records,
 * accompanying files, and references associated with a specific migration.
 */

namespace RadiusTheme\RadiusHotelBooking\Commands;

use WP_CLI;
use WP_CLI_Command;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Handles the deletion of database tables, migration records,
 * accompanying files, and references associated with a specific migration.
 */
class DeleteTableCommand extends WP_CLI_Command {

	/**
	 * Invokes the method to remove a database migration, its related records, and corresponding files.
	 *
	 * @param array $args An array of arguments, where the first element is the name of the migration class to handle.
	 *                    Expected format: [className].
	 *
	 * @return void
	 */
	public function __invoke( $args ) {
		global $wpdb;

		$className       = $args[0];
		$fullClassName   = "RadiusTheme\\RadiusHotelBooking\\Databases\\Table\\{$className}Table";
		$filePath        = RADIUS_HOTEL_BOOKING_PATH . '/includes/Databases/Table/' . $className . 'Table.php';
		$dbManagerPath   = RADIUS_HOTEL_BOOKING_PATH . '/includes/Databases/DatabaseManager.php';
		$migrationsTable = $wpdb->prefix . 'radius_hotel_booking_migrations';

		// Step 1: Try to drop table via down()
		if ( class_exists( $fullClassName ) ) {
			$migration = new $fullClassName();

			if ( method_exists( $migration, 'down' ) ) {
				$migration->down();
				WP_CLI::log( "Dropped DB table via {$className}Table::down()" );
			}
		} else {
			WP_CLI::warning( "Class {$fullClassName} not found. Skipping table drop." );
		}

		// Step 2: Delete migration record
		$like = '%' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1_$2', $className ) ) . '%';
		$table = esc_sql( $migrationsTable ); // Sanitize the dynamic table name

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped using esc_sql() and cannot be parameterized.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE migration LIKE %s", //phpcs:ignore
				$like
			)
		);
		wp_cache_delete( 'radius_hotel_booking_executed_migrations' );
		WP_CLI::log( "Deleted migration record(s) for: {$className}" );

		// Step 3: Delete class file
		if ( file_exists( $filePath ) ) {
			if ( ! unlink( $filePath ) ) {
				WP_CLI::error( "Failed to delete '{$className}Table.php'. Check file permissions." );
				return;
			}
			WP_CLI::success( "Migration class file '{$className}Table.php' deleted." );
		} else {
			WP_CLI::warning( "Migration file '{$className}Table.php' not found." );
		}

		// Step 4: Clean from DatabaseManager.php
		if ( file_exists( $dbManagerPath ) ) {
			$content = file_get_contents( $dbManagerPath );

			$usePattern   = '/^use\s+RadiusTheme\\\\RadiusHotelBooking\\\\Databases\\\\Table\\\\' . preg_quote( $className . 'Table', '/' ) . ';\s*$/m';
			$arrayPattern = '/^\s*' . preg_quote( $className . 'Table::class', '/' ) . ',\s*$/m';

			$newContent = preg_replace( $usePattern, '', $content, 1 );
			$newContent = preg_replace( $arrayPattern, '', $newContent, 1 );
			$newContent = preg_replace( "/^\s*\n/m", '', $newContent );

			if ( $newContent !== $content ) {
				file_put_contents( $dbManagerPath, $newContent );
				WP_CLI::success( "Removed '{$className}Table' from DatabaseManager." );
			} else {
				WP_CLI::warning( 'No references found in DatabaseManager.php.' );
			}
		} else {
			WP_CLI::warning( 'DatabaseManager.php not found.' );
		}
	}
}
