<?php
/**
 * Class Schema
 *
 * Provides methods to create, modify, and manage database tables in a WordPress environment, similar to Laravel's Schema facade.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Database\Schema;

use Exception;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/Schema/Schema.php
 * Main schema class (like Laravel's Schema facade)
 */
class Schema {
	/**
	 * Creates a database table with the specified schema and applies the given blueprint modifications.
	 *
	 * @param string $table The name of the table to create.
	 * @param callable $callback A callback function used to define the table's blueprint and schema.
	 *
	 * @return void
	 */
	public static function create( string $table, callable $callback ): void {
		$blueprint = new Blueprint( $table );
		$callback( $blueprint );

		// Create table using dbDelta
		$sql = $blueprint->toSql();
		static::execute( $sql );

		// Add foreign keys separately (dbDelta doesn't handle them well)
		static::addForeignKeys( $blueprint );
	}

	/**
	 * Modifies or recreates the structure of the specified database table using a blueprint object.
	 *
	 * @param string $table The name of the table to be modified or recreated.
	 * @param callable $callback A callback function that receives the blueprint object to define the table structure.
	 *
	 * @return void
	 */
	public static function table( string $table, callable $callback ): void {
		// For modifications - this would need more complex logic
		// For now, we'll just recreate (not recommended for production)
		$blueprint = new Blueprint( $table );
		$callback( $blueprint );

		$sql = $blueprint->toSql();
		static::execute( $sql );
		static::addForeignKeys( $blueprint );
	}

	/**
	 * Renames a table if the old name exists and the new name does not.
	 *
	 * @param string $from The old table name (without $wpdb->prefix).
	 * @param string $to   The new table name (without $wpdb->prefix).
	 *
	 * @return void
	 */
	public static function rename( string $from, string $to ): void {
		global $wpdb;

		$oldName = $wpdb->prefix . $from;
		$newName = $wpdb->prefix . $to;

		// Only rename if old table exists and new table does not.
		$oldExists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $oldName ) ); //phpcs:ignore
		$newExists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $newName ) ); //phpcs:ignore

		if ( ! empty( $oldExists ) && empty( $newExists ) ) {
			$wpdb->query( "RENAME TABLE `{$oldName}` TO `{$newName}`" ); //phpcs:ignore
		}
	}

	/**
	 * Drops the specified table from the database if it exists.
	 *
	 * @param string $table The name of the table to be dropped, without the prefix.
	 *
	 * @return void
	 */
	public static function drop( string $table ): void {
		global $wpdb;
		$tableName = $wpdb->prefix . $table;
		$sql       = "DROP TABLE IF EXISTS `{$tableName}`";
		$wpdb->query( $sql ); //phpcs:ignore
	}

	/**
	 * Drops the specified table if it exists in the database.
	 *
	 * @param string $table The name of the table to be dropped.
	 *
	 * @return void
	 */
	public static function dropIfExists( string $table ): void {
		static::drop( $table );
	}

	/**
	 * Checks if a specific table exists in the database.
	 *
	 * @param string $table The name of the table to check for existence.
	 *
	 * @return bool Returns true if the table exists, false otherwise.
	 */
	public static function hasTable( string $table ): bool {
		global $wpdb;
		$tableName = $wpdb->prefix . $table;
		$result    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tableName ) ); //phpcs:ignore

		return ! empty( $result );
	}

	/**
	 * Checks if a specific column exists in a given database table.
	 *
	 * @param string $table The name of the table to check.
	 * @param string $column The name of the column to look for.
	 *
	 * @return bool Returns true if the column exists in the table, otherwise false.
	 */
	public static function hasColumn( string $table, string $column ): bool {
		global $wpdb;
		$tableName = $wpdb->prefix . $table;
		$result    = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$tableName}` LIKE %s", $column ) ); //phpcs:ignore

		return ! empty( $result );
	}

	/**
	 * Executes the given SQL statement using the `dbDelta` function.
	 *
	 * @param string $sql The SQL statement to be executed.
	 *
	 * @return void
	 */
	private static function execute( string $sql ): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );
	}

	/**
	 * Adds foreign key constraints to the specified table as defined in the blueprint.
	 *
	 * @param Blueprint $blueprint The table blueprint containing foreign key definitions.
	 *
	 * @return void
	 */
	private static function addForeignKeys( Blueprint $blueprint ): void {
		global $wpdb;

		$foreignKeys = $blueprint->getForeignKeys();
		if ( empty( $foreignKeys ) ) {
			return;
		}

		$tableName = $wpdb->prefix . $blueprint->getTable();

		foreach ( $foreignKeys as $foreignKey ) {
			try {
				$constraintName = $foreignKey->getConstraintName();

				// Skip if constraint already exists.
				if ( $constraintName ) {
					$exists = $wpdb->get_var( //phpcs:ignore
						$wpdb->prepare(
							'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s AND CONSTRAINT_TYPE = %s',
							$wpdb->dbname,
							$tableName,
							$constraintName,
							'FOREIGN KEY'
						)
					);

					if ( $exists ) {
						continue;
					}
				}

				$sql = "ALTER TABLE `{$tableName}` ADD " . $foreignKey->toSql();
				$wpdb->query( $sql ); //phpcs:ignore
			} catch ( Exception $e ) {
				// Log error but don't stop execution
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- server-side record of a failed schema change.
				error_log( '[radius-hotel-booking] Failed to add foreign key: ' . $e->getMessage() );
			}
		}
	}
}
