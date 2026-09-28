<?php
/**
 * Provides static methods for common database operations,
 * including querying and executing raw statements.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\ORM;

use RadiusTheme\RadiusHotelBooking\Core\Database\Connection;
use RadiusTheme\RadiusHotelBooking\Core\ORM\QueryBuilder;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/ORM/DB.php
 * Static entry point for database operations
 */
class DB {

	/**
	 * Sets the table for the query builder.
	 *
	 * @param string $table The name of the table to set.
	 *
	 * @return QueryBuilder An instance of the query builder for the specified table.
	 */
	public static function table( string $table ): QueryBuilder {
		return new QueryBuilder( $table );
	}

	/**
	 * Executes a raw SQL query with optional bindings and returns the results.
	 *
	 * @param string $query The raw SQL query to be executed.
	 * @param array $bindings Optional array of bindings to be applied to the query.
	 *
	 * @return array The results of the executed query.
	 */
	public static function raw( string $query, array $bindings = array() ): array {
		$connection = Connection::getInstance();
		if ( ! empty( $bindings ) ) {
			$query = $connection->prepare( $query, ...$bindings );
		}
		return $connection->get_results( $query );
	}

	/**
	 * Executes a given SQL statement with optional bindings.
	 *
	 * @param string $query The SQL query to be executed.
	 * @param array $bindings Optional array of bindings to prepare the query with.
	 *
	 * @return int|false The number of affected rows if successful, or false on failure.
	 */
	public static function statement( string $query, array $bindings = array() ): int|false {
		$connection = Connection::getInstance();
		if ( ! empty( $bindings ) ) {
			$query = $connection->prepare( $query, ...$bindings );
		}
		return $connection->query( $query );
	}
}
