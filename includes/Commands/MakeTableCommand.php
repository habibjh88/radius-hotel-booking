<?php
namespace RadiusTheme\RadiusHotelBooking\Commands;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Make Table Command
 */

use WP_CLI;
use WP_CLI_Command;

/**
 * Generate a new table migration class and register it.
 */
class MakeTableCommand extends WP_CLI_Command {
	/**
	 * Handles the creation and registration of a database migration class based on the provided class name.
	 *
	 * @param array $args The arguments passed to the method, where the first element is expected to be the class name.
	 *
	 * @return void
	 */
	public function __invoke( $args ) {
		$className = $args[0];
		$tableName = strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $className ) ); // Services => services

		$stubPath   = RADIUS_HOTEL_BOOKING_PATH . '/resources/stubs/migration.stub';
		$targetPath = RADIUS_HOTEL_BOOKING_PATH . '/includes/Databases/Table/' . $className . 'Table.php';

		if ( file_exists( $targetPath ) ) {
			WP_CLI::error( "The migration class '{$className}' already exists." );
			return;
		}

		if ( ! file_exists( $stubPath ) ) {
			WP_CLI::error( "Migration stub file not found: {$stubPath}" );
			return;
		}

		// Step 1: Create migration file
		$stub    = file_get_contents( $stubPath );
		$content = str_replace( array( '{{ class }}', '{{ table }}' ), array( $className, $tableName ), $stub );
		file_put_contents( $targetPath, $content );

		// Step 2: Register class in DatabaseManager
		$this->registerMigrationClass( $className );

		WP_CLI::success( "Migration created and registered: includes/Databases/Table/{$className}Table.php" );
	}

	/**
	 * Add use + ::class line to DatabaseManager
	 *
	 * @param string $className Class name
	 */
	private function registerMigrationClass( $className ) {
		$fullClass   = "{$className}Table";
		$namespace   = 'RadiusTheme\\RadiusHotelBooking\\Databases\\Table';
		$useLine     = "use {$namespace}\\{$fullClass};";
		$managerPath = RADIUS_HOTEL_BOOKING_PATH . '/includes/Databases/DatabaseManager.php';

		if ( ! file_exists( $managerPath ) ) {
			WP_CLI::warning( 'DatabaseManager.php not found. Skipping registration.' );
			return;
		}

		$file = file_get_contents( $managerPath );

		// 1. Insert use statement if not present
		if ( strpos( $file, $useLine ) === false ) {
			$file = preg_replace(
				'/^namespace\s+RadiusTheme\\\\RadiusHotelBooking\\\\Databases;/m',
				"namespace RadiusTheme\\RadiusHotelBooking\\Databases;\n\n$useLine",
				$file
			);
		}

		// 2. Add class to $migrationClasses = array(...)
		if ( strpos( $file, "{$fullClass}::class" ) === false ) {
			$file = preg_replace_callback(
				'/\$migrationClasses\s*=\s*array\s*\((.*?)\);/s',
				function ( $matches ) use ( $fullClass ) {
					$existing = trim( $matches[1] );
					$newLine  = "			{$fullClass}::class,";
					// Ensure trailing comma
					if ( ! str_ends_with( $existing, ',' ) && strlen( $existing ) > 0 ) {
						$existing .= ',';
					}
					return "\$migrationClasses = array(\n{$existing}\n{$newLine}\n		);";
				},
				$file
			);
		}

		file_put_contents( $managerPath, $file );
	}
}
