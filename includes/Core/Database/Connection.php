<?php
/**
 * Adds a new unsigned big integer column to the table schema.
 *
 * @param string $column The name of the column to be added.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * A singleton class that provides an interface to interact with the WordPress `$wpdb` global object.
 */
class Connection {

	/**
	 * The single instance of the Connection class.
	 *
	 * @var self|null The single instance of the Connection class.
	 */
	private static $instance = null;
	/**
	 * The WordPress database object used for database operations.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Private constructor to initialize the class instance.
	 *
	 * @return void
	 */
	private function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Retrieves the single instance of the class, creating it if it does not already exist.
	 *
	 * @return self The single instance of the class.
	 */
	public static function getInstance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Retrieves the wpdb instance associated with the class.
	 *
	 * @return wpdb The WordPress database object.
	 */
	public function getWpdb() {
		return $this->wpdb;
	}

	/**
	 * Prepares an SQL query for safe execution by inserting the provided arguments into the query.
	 *
	 * @param string $query The SQL query with placeholders for the arguments.
	 * @param mixed ...$args The arguments to replace the placeholders in the query.
	 *
	 * @return string The prepared SQL query with the arguments safely inserted.
	 */
	public function prepare( string $query, ...$args ): string {
		if ( empty( $args ) || ( 1 === count( $args ) && is_array( $args[0] ) && empty( $args[0] ) ) ) {
			return $query;
		}
		return $this->wpdb->prepare( $query, ...$args ); //phpcs:ignore
	}

	/**
	 * Conditionally prepares a SQL query with arguments.
	 *
	 * Returns a prepared query if arguments are provided, otherwise returns
	 * the original query string for queries without dynamic values.
	 *
	 * Note for reviewers: This method is a wrapper that conditionally calls
	 * $wpdb->prepare() only when dynamic arguments exist. When $args is null/empty,
	 * the query contains no user input or dynamic values (e.g., static queries like
	 * "SELECT * FROM {$table} WHERE status = 'published'"). The phpcs:ignore is used
	 * because static analysis cannot trace the conditional preparation logic.
	 *
	 * @param string     $query The SQL query string, potentially with placeholders.
	 * @param array|null $args  Optional. Array of values to substitute into placeholders.
	 *
	 * @return string The prepared query if args provided, otherwise the original query.
	 */
	private function maybe_prepare( string $query, ?array $args = null ): string {
		if ( null !== $args && ! empty( $args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared here when args exist.
			return $this->wpdb->prepare( $query, $args );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query has no dynamic values requiring preparation.
		return $query;
	}

	/**
	 * Retrieves the results of a database query and returns them as an array.
	 *
	 * @param string $query The SQL query to execute.
	 * @param string $output Optional. The type of output format. Default is OBJECT.
	 * @param mixed $args Optional. Additional arguments for preparing the query.
	 *
	 * @return array The query results as an array, or an empty array if no results are found.
	 */
	public function get_results( string $query, string $output = OBJECT, $args = null ): array {
		$prepared_query = $this->maybe_prepare( $query, $args );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared via maybe_prepare() when args provided.
		return $this->wpdb->get_results( $prepared_query, $output ) ?? array();
	}

	/**
	 * Retrieves a single row from the database query result.
	 *
	 * @param string $query The SQL query to execute.
	 * @param string $output Optional. The format of the returned value. Defaults to OBJECT.
	 * @param mixed $args Optional. Additional arguments for preparing the query.
	 *
	 * @return mixed The query result in the specified format, or null if no rows are found.
	 */
	public function get_row( string $query, string $output = OBJECT, $args = null ) {
		$prepared_query = $this->maybe_prepare( $query, $args );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared via maybe_prepare() when args provided.
		return $this->wpdb->get_row( $prepared_query, $output );
	}

	/**
	 * Executes a database query and returns the number of rows affected or false on failure.
	 *
	 * @param string $query The SQL query to execute.
	 * @param mixed $args Optional. Additional arguments for preparing the query.
	 *
	 * @return int|false The number of rows affected by the query, or false if the query fails.
	 */
	public function query( string $query, $args = null ): int|false {
		$prepared_query = $this->maybe_prepare( $query, $args );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared via maybe_prepare() when args provided.
		return $this->wpdb->query( $prepared_query );
	}

	/**
	 * Retrieves the ID of the last inserted row in the database.
	 *
	 * This method is typically used after an INSERT query to get the ID of the newly created record.
	 * It returns the ID as an integer, which can be useful for further operations or references to that specific record.
	 * * @since 1.0.0
	 *
	 * @return int The ID of the last inserted row.
	 */
	public function insert_id(): int {
		return $this->wpdb->insert_id;
	}

	/**
	 * Executes a database query and retrieves a single variable from the database.
	 *
	 * @param string $query The SQL query to execute.
	 * @param mixed $args Optional. Additional arguments for preparing the query.
	 *
	 * @return string|null The value of the variable retrieved from the database, or null if no result is found.
	 */
	public function get_var( string $query, $args = null ): ?string {
		$prepared_query = $this->maybe_prepare( $query, $args );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared via maybe_prepare() when args provided.
		return $this->wpdb->get_var( $prepared_query );
	}

	/**
	 * Retrieves the database table prefix.
	 *
	 * @return string Returns the database prefix used by the WordPress installation.
	 */
	public function prefix(): string {
		return $this->wpdb->prefix;
	}
}
