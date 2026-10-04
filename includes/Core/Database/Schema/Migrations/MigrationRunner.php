<?php
/**
 * Radius Hotel Booking Plugin
 * Runs and records schema migrations.
 *
 * @package RadiusHotelBooking
 * @version 1.0.0
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Migrations;

use Exception;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Handles running database migrations.
 *
 * Responsible for running, rolling back, and tracking migrations.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Migrations
 * @since 1.0.0
 */
class MigrationRunner {

	/**
	 * List of migrations [name => class].
	 *
	 * @var array<string, string>
	 * @since 1.0.0
	 */
	private array $migrations = array();

	/**
	 * Name of the database table to track executed migrations.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private string $migrationsTable = 'radius_hotel_booking_migrations';

	/**
	 * Constructor.
	 *
	 * Creates the migrations table if it does not exist.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->createMigrationsTable();
	}

	/**
	 * Adds a migration to the runner.
	 *
	 * @param string $name       Unique migration name.
	 * @param string $class_name Fully qualified class name of the migration.
	 * @return void
	 * @since 1.0.0
	 */
	public function addMigration( string $name, string $class_name ): void {
		$this->migrations[ $name ] = $class_name;
	}

	/**
	 * Runs all migrations that have not yet been executed.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function run(): void {
		$executedMigrations = $this->getExecutedMigrations();
		foreach ( $this->migrations as $name => $class_name ) {
			if ( ! in_array( $name, $executedMigrations, true ) ) {
				$this->runMigration( $name, $class_name );
			}
		}
	}

	/**
	 * Rolls back the last executed migrations.
	 *
	 * @param int $steps Number of migrations to roll back. Default 1.
	 * @return void
	 * @since 1.0.0
	 */
	public function rollback( int $steps = 1 ): void {
		$executedMigrations = $this->getExecutedMigrations();
		$toRollback         = array_slice( array_reverse( $executedMigrations ), 0, $steps );

		foreach ( $toRollback as $name ) {
			if ( isset( $this->migrations[ $name ] ) ) {
				$this->rollbackMigration( $name, $this->migrations[ $name ] );
			}
		}
	}

	/**
	 * Runs a specific migration.
	 *
	 * @param string $name       Migration name.
	 * @param string $class_name Migration class.
	 * @return void
	 * @throws Exception If migration fails.
	 * @since 1.0.0
	 */
	public function runSpecificMigration( string $name, string $class_name ): void {
		$this->runMigration( $name, $class_name );
	}

	/**
	 * Gets all registered migrations.
	 *
	 * @return array<string, string>
	 * @since 1.0.0
	 */
	public function getAllMigrations(): array {
		return $this->migrations;
	}

	/**
	 * Gets migration status showing executed and pending migrations.
	 *
	 * @return array{executed: string[], pending: string[]}
	 * @since 1.0.0
	 */
	public function getStatus(): array {
		$executedMigrations = $this->getExecutedMigrations();
		$allMigrations      = array_keys( $this->migrations );
		$pendingMigrations  = array_diff( $allMigrations, $executedMigrations );

		return array(
			'executed' => $executedMigrations,
			'pending'  => array_values( $pendingMigrations ),
		);
	}

	/**
	 * Clears all migration records from the tracking table.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function clearAllRecords(): void {
		global $wpdb;

		$tableName = $wpdb->prefix . $this->migrationsTable;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct delete needed for migration tracking.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $tableName ) );

		// Clear cache when clearing all records.
		wp_cache_delete( 'radius_hotel_booking_executed_migrations' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- progress, only with WP_DEBUG.
			error_log( '[radius-hotel-booking] All migration records cleared' );
		}
	}

	/**
	 * Removes a specific migration record.
	 *
	 * @param string $name Migration name.
	 * @return void
	 * @since 1.0.0
	 */
	public function removeSpecificRecord( string $name ): void {
		$this->removeRecord( $name );
	}

	/**
	 * Runs a single migration.
	 *
	 * @param string $name       Migration name.
	 * @param string $class_name Migration class.
	 * @return void
	 * @throws Exception If migration fails.
	 * @since 1.0.0
	 */
	private function runMigration( string $name, string $class_name ): void {
		try {
			$migration = new $class_name();
			$migration->up();
			$this->recordMigration( $name );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- progress, only with WP_DEBUG.
				error_log( "[radius-hotel-booking] Migration executed: {$name}" );
			}
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- server-side record of a failed schema change.
			error_log( "[radius-hotel-booking] Migration failed: {$name} - " . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Rolls back a single migration.
	 *
	 * @param string $name       Migration name.
	 * @param string $class_name Migration class.
	 * @return void
	 * @throws Exception If rollback fails.
	 * @since 1.0.0
	 */
	private function rollbackMigration( string $name, string $class_name ): void {
		try {
			$migration = new $class_name();
			$migration->down();
			$this->removeRecord( $name );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- progress, only with WP_DEBUG.
				error_log( "[radius-hotel-booking] Migration rolled back: {$name}" );
			}
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- server-side record of a failed schema change.
			error_log( "[radius-hotel-booking] Migration rollback failed: {$name} - " . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Creates the migrations tracking table if it does not exist.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function createMigrationsTable(): void {
		if ( ! Schema::hasTable( $this->migrationsTable ) ) {
			Schema::create(
				$this->migrationsTable,
				function ( Blueprint $table ) {
					$table->id();
					$table->string( 'migration' );
					$table->datetime( 'executed_at' );
				}
			);
		}
	}

	/**
	 * Returns the list of executed migration names.
	 *
	 * @return string[] List of migration names.
	 * @since 1.0.0
	 */
	private function getExecutedMigrations(): array {
		global $wpdb;

		if ( ! Schema::hasTable( $this->migrationsTable ) ) {
			return array();
		}

		$tableName = $wpdb->prefix . $this->migrationsTable;

		// Use wp_cache for caching and wpdb->prepare() for SQL safety.
		$cache_key = 'radius_hotel_booking_executed_migrations';
		$results   = wp_cache_get( $cache_key );

		if ( false === $results ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct query needed for migration tracking.
			$results = $wpdb->get_results(
				$wpdb->prepare( 'SELECT migration FROM %i ORDER BY id ASC', $tableName ),
				ARRAY_A
			);
			wp_cache_set( $cache_key, $results, '', 3600 ); // Cache for 1 hour.
		}

		return array_column( $results, 'migration' );
	}

	/**
	 * Records a migration as executed.
	 *
	 * @param string $name Migration name.
	 * @return void
	 * @since 1.0.0
	 */
	private function recordMigration( string $name ): void {
		global $wpdb;

		$tableName = $wpdb->prefix . $this->migrationsTable;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert needed for migration tracking.
		$wpdb->insert(
			$tableName,
			array(
				'migration'   => $name,
				'executed_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s' )
		);

		// Clear cache when adding new migration.
		wp_cache_delete( 'radius_hotel_booking_executed_migrations' );
	}

	/**
	 * Removes a migration record.
	 *
	 * @param string $name Migration name.
	 * @return void
	 * @since 1.0.0
	 */
	private function removeRecord( string $name ): void {
		global $wpdb;

		$tableName = $wpdb->prefix . $this->migrationsTable;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct delete needed for migration tracking.
		$wpdb->delete(
			$tableName,
			array( 'migration' => $name ),
			array( '%s' )
		);

		// Clear cache when removing migration record.
		wp_cache_delete( 'radius_hotel_booking_executed_migrations' );
	}
}
