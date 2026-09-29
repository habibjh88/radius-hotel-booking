<?php
/**
 * Database Manager
 *
 * Handles the setup and execution of database migrations for the Radius Hotel Booking plugin.
 *
 * This class registers all migration classes, triggers setup hooks,
 * and executes the migrations during installation or update routines.
 *
 * @package    RadiusTheme\RadiusHotelBooking
 * @subpackage Databases
 * @since      1.0.0
 *
 * @author     RadiusTheme
 * @link       https://radiustheme.com
 * @license    GPL-2.0-or-later
 */

namespace RadiusTheme\RadiusHotelBooking\Databases;

use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Migrations\MigrationRunner;
use RadiusTheme\RadiusHotelBooking\Databases\Table\FilesTable;
use RadiusTheme\RadiusHotelBooking\Databases\Table\FloorsTable;
use RadiusTheme\RadiusHotelBooking\Databases\Table\RoomsTable;
use RadiusTheme\RadiusHotelBooking\Databases\Table\RoomTypesTable;
use RadiusTheme\RadiusHotelBooking\Databases\Table\SequencesTable;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class DatabaseManager
 *
 * Handles registration and execution of database migrations.
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases
 * @since 1.0.0
 */
class DatabaseManager {
	/**
	 * Runner for managing and executing migrations.
	 *
	 * @var MigrationRunner
	 * @since 1.0.0
	 */
	private MigrationRunner $migrationRunner;

	/**
	 * Array of migration classes mapped by their short names.
	 *
	 * @var array<string, string>
	 * @since 1.0.0
	 */
	private static array $migrationClassMap = array();

	/**
	 * DatabaseManager constructor.
	 *
	 * Initializes the migration setup process.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setupMigrations();
	}

	/**
	 * Sets up migration classes and registers them with the runner.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function setupMigrations(): void {
		/**
		 * Fires before setting up migration classes.
		 *
		 * Allows hooking into the beginning of the migration setup process.
		 *
		 * @since 1.0.0
		 * @location DatabaseManager::setupMigrations()
		 * @access private
		 */
		do_action( 'rtbp_setup_migrations' );

		$this->migrationRunner = new MigrationRunner();

		// Tables in dependency order. Each module appends its own.
		$migrationClasses = array(
			SequencesTable::class,
			FilesTable::class,
			// Inventory (M06).
			FloorsTable::class,
			RoomTypesTable::class,
			RoomsTable::class,
		);

		/**
		 * Filters the list of migration classes to be executed.
		 *
		 * Useful for registering additional migrations from other parts of the plugin or extensions.
		 *
		 * @since 1.0.0
		 * @location DatabaseManager::setupMigrations()
		 * @access private
		 *
		 * @param string[] $migrationClasses Array of fully qualified migration class names.
		 * @return string[] Modified array of migration class names.
		 */
		$migrationClasses = apply_filters( 'rtbp_migration_classes', $migrationClasses );

		// Build class map for quick lookup
		self::$migrationClassMap = array();

		foreach ( $migrationClasses as $index => $class ) {
			$name       = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1_$2', ( new \ReflectionClass( $class ) )->getShortName() ) );
			$db_version = RADIUS_HOTEL_BOOKING_DB_VERSION;
			$key        = "{$db_version}_{$name}";

			// Store class mapping for refresh functionality
			$shortName                             = ( new \ReflectionClass( $class ) )->getShortName();
			self::$migrationClassMap[ $shortName ] = $class;

			$this->migrationRunner->addMigration( $key, $class );
		}

		/**
		 * Fires after all migration classes are registered.
		 *
		 * Useful for post-migration setup or logging.
		 *
		 * @since 1.0.0
		 * @location DatabaseManager::setupMigrations()
		 * @access private
		 */
		do_action( 'rtbp_setup_migrations_after' );
	}

	/**
	 * Runs the registered migrations and flushes rewrite rules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function migrate(): void {
		$instance = new self();
		$instance->migrationRunner->run();
		flush_rewrite_rules();
	}

	/**
	 * Refreshes all tables by dropping and recreating them.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function refreshAll(): void {
		$instance = new self();

		// Clear all migration records first
		$instance->migrationRunner->clearAllRecords();

		// Drop all tables in reverse order to handle dependencies
		$migrationClasses = array_reverse( array_values( self::$migrationClassMap ) );

		foreach ( $migrationClasses as $class ) {
			try {
				$migration = new $class();
				if ( method_exists( $migration, 'down' ) ) {
					$migration->down();
				}
			} catch ( \Throwable $e ) {
				error_log( "Failed to drop table for {$class}: " . $e->getMessage() );
				// Continue with other tables
			}
		}

		// Run all migrations again
		$instance->migrationRunner->run();
		flush_rewrite_rules();
	}

	/**
	 * Refreshes a specific table by dropping and recreating it.
	 *
	 * @param string $tableName The migration class short name (e.g., 'UsersTable').
	 * @since 1.0.0
	 * @return void
	 * @throws \InvalidArgumentException If table name is not found.
	 */
	public static function refreshTable( string $tableName ): void {
		$instance = new self();

		if ( ! isset( self::$migrationClassMap[ $tableName ] ) ) {
			throw new \InvalidArgumentException(
				sprintf(
				/* translators: 1: migration class name, 2: comma separated list of available tables. */
					esc_html__( 'Migration class %1$s not found. Available tables: %2$s', 'radius-hotel-booking' ),
					esc_html( $tableName ),
					esc_html( implode( ', ', array_keys( self::$migrationClassMap ) ) )
				)
			);      }

		$class = self::$migrationClassMap[ $tableName ];
		// Get the migration key for this table
		$migrationKey = $instance->findMigrationKey( $class );
		if ( $migrationKey ) {
			// Remove the specific migration record
			$instance->migrationRunner->removeSpecificRecord( $migrationKey );
		}

		// Drop the table
		try {
			$migration = new $class();
			if ( method_exists( $migration, 'down' ) ) {
				$migration->down();
			}
		} catch ( \Throwable $e ) {
			error_log( "Failed to drop table {$tableName}: " . $e->getMessage() );
		}

		// Recreate the table
		if ( $migrationKey ) {
			$instance->migrationRunner->runSpecificMigration( $migrationKey, $class );
		}

		flush_rewrite_rules();
	}

	/**
	 * Rollbacks migrations.
	 *
	 * @param int $steps Number of migrations to rollback.
	 * @since 1.0.0
	 * @return void
	 */
	public static function rollback( int $steps = 1 ): void {
		$instance = new self();
		$instance->migrationRunner->rollback( $steps );
		flush_rewrite_rules();
	}

	/**
	 * Gets migration status.
	 *
	 * @since 1.0.0
	 * @return array{executed: string[], pending: string[]}
	 */
	public static function getStatus(): array {
		$instance = new self();
		return $instance->migrationRunner->getStatus();
	}

	/**
	 * Finds the migration key for a given class.
	 *
	 * @param string $className The migration class name.
	 * @return string|null The migration key or null if not found.
	 * @since 1.0.0
	 */
	private function findMigrationKey( string $className ): ?string {
		$migrations = $this->migrationRunner->getAllMigrations();

		foreach ( $migrations as $key => $migrationClass ) {
			if ( $migrationClass === $className ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Gets available table names for refresh command.
	 *
	 * @since 1.0.0
	 * @return string[]
	 */
	public static function getAvailableTables(): array {
		// Initialize if not already done
		if ( empty( self::$migrationClassMap ) ) {
			new self();
		}

		return array_keys( self::$migrationClassMap );
	}
}
