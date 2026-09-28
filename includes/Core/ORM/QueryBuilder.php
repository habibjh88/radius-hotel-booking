<?php
/**
 * Groups the results by a specified column.
 *
 * @param string $column The name of the column to group by.
 *
 * @return self The current instance for method chaining.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\ORM;

use RadiusTheme\RadiusHotelBooking\Core\Database\Connection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Core/ORM/QueryBuilder.php
 * Eloquent-style query builder with full WordPress compatibility
 */
class QueryBuilder {

	/**
	 * The database connection instance.
	 * This is used to execute queries and interact with the database.
	 *
	 * @var Connection
	 */
	private Connection $connection;
	/**
	 * The name of the database table to query.
	 * This should be set to the table name without the WordPress prefix,
	 * as the prefix will be automatically applied by the Connection class.
	 *
	 * @var string
	 * The table name should be specified in the format 'table_name',
	 */
	private string $table;
	/**
	 * An array of where conditions to apply to the query.
	 * This array can contain various types of conditions, such as basic equality checks,
	 * 'in' checks, null checks, and more.
	 * Each condition is represented as an associative array with keys like 'type', 'column', 'operator', 'value', and 'boolean'.
	 * This allows for flexible and complex query conditions to be built dynamically.
	 *
	 * @var array<string, mixed>
	 */
	private array $wheres = array();
	/**
	 * An array of join clauses to apply to the query.
	 * This array contains information about the type of join (inner or left),
	 * the table to join, and the columns involved in the join condition.
	 * This allows for complex queries that involve multiple tables,
	 * enabling the retrieval of related data across different tables in the database.
	 * * Each join is represented as an associative array with keys like 'type', 'table', 'first', 'operator', and 'second'.
	 *
	 * @var array<string, mixed>
	 */
	private array $joins = array();
	/**
	 * An array of columns to select in the query.
	 * This array can contain specific column names or the wildcard '*' to select all columns.
	 * By default, it is set to select all columns from the table.
	 * This allows for flexibility in choosing which columns to retrieve from the database,
	 * enabling the retrieval of only the necessary data for a given query.
	 *
	 * @var array<string>
	 */
	private array $selects = array( '*' );
	/**
	 * An array of order by clauses to apply to the query.
	 * This array contains information about the columns to sort by and the direction of sorting (ASC or DESC).
	 * This allows for the results of the query to be ordered in a specific way,
	 * enabling the retrieval of data in a meaningful order based on the specified columns.
	 * Each order clause is represented as an associative array with keys like 'column' and 'direction'.
	 *
	 * @var array<string, mixed>
	 */
	private array $orders = array();
	/**
	 * An array of group by columns to apply to the query.
	 * This array contains the names of the columns by which the results should be grouped.
	 * This allows for aggregation of results based on specific columns,
	 * enabling the retrieval of summarized data from the database.
	 * * Each group by column is represented as a string in the array.
	 *
	 * @var array<string>
	 */
	private array $groups = array();
	/**
	 * The maximum number of records to return.
	 * This is used for limiting the number of results returned by the query,
	 * allowing for efficient data retrieval and pagination.
	 * If set to null, no limit is applied.
	 *
	 * @var int|null
	 * This property is used to specify the maximum number of records to retrieve from the database.
	 */
	private ?int $limit = null;
	/**
	 * The offset for the query, used for pagination.
	 * This is the number of records to skip before starting to return results.
	 * If set to null, no offset is applied.
	 * This property is used to specify the number of records to skip before starting to return results.
	 * It is particularly useful for implementing pagination in queries,
	 * allowing you to retrieve a specific subset of records based on the current page and the number of records per page.
	 *
	 * @var int|null
	 */
	private ?int $offset = null;
	/**
	 * An array of bindings for the query parameters.
	 * This array holds the values that will be bound to the query placeholders,
	 * ensuring that the query is executed safely and efficiently.
	 * This is particularly important for preventing SQL injection attacks
	 * and ensuring that the query parameters are properly escaped.
	 *
	 * @var array<string, mixed>
	 */
	private array $bindings = array();

	/**
	 * Whether to select distinct rows only.
	 *
	 * @var bool
	 */
	private bool $distinct = false;

	/**
	 * Constructor method to initialize the database table.
	 *
	 * @param string $table The name of the database table.
	 *
	 * @return void
	 */
	public function __construct( string $table ) {
		$this->connection = Connection::getInstance();
		$this->table      = $this->connection->prefix() . $table;
	}

	/**
	 * Sets the columns to be selected in the database query.
	 *
	 * @param array|string $columns The column(s) to be selected. Can be a single column as a string
	 *                               or multiple columns as an array. Defaults to all columns ['*'].
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function select( array|string $columns = array( '*' ) ): self {
		$this->selects = is_array( $columns ) ? $columns : func_get_args();

		return $this;
	}

	/**
	 * Adds a basic where clause to the query.
	 *
	 * @param string $column The column to filter on.
	 * @param string $operator The operator to evaluate with, defaults to '='.
	 * @param mixed|null $value The value to compare the column against. If undefined, the operator's value will be used as the value.
	 *
	 * @return self Returns the current query instance for method chaining.
	 */
	/**
	 * Adds a basic where clause to the query.
	 *
	 * Supports both simple column conditions and closure callbacks for grouped conditions.
	 *
	 * @param string|\Closure $column   The column to filter on, or a closure for nested conditions.
	 * @param string          $operator The operator to evaluate with, defaults to '='.
	 * @param mixed|null      $value    The value to compare the column against.
	 *
	 * @return self Returns the current query instance for method chaining.
	 */
	public function where( string|\Closure $column, string $operator = '=', $value = null ): self {
		// Handle closure for nested where groups.
		if ( $column instanceof \Closure ) {
			return $this->whereNested( $column, 'AND' );
		}

		if ( null === $value ) {
			$value    = $operator;
			$operator = '=';
		}

		$this->wheres[] = array(
			'type'     => 'basic',
			'column'   => $column,
			'operator' => $operator,
			'value'    => $value,
			'boolean'  => 'AND',
		);

		$this->bindings[] = $value;

		return $this;
	}

	/**
	 * Adds an "OR WHERE" condition to the query.
	 *
	 * @param string $column The name of the column for the condition.
	 * @param string $operator The operator for the condition, defaults to '='.
	 * @param mixed|null $value The value to compare against the column, defaults to null.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	/**
	 * Adds an "OR WHERE" condition to the query.
	 *
	 * Supports both simple column conditions and closure callbacks for grouped conditions.
	 *
	 * @param string|\Closure $column   The name of the column or a closure for nested conditions.
	 * @param string          $operator The operator for the condition, defaults to '='.
	 * @param mixed|null      $value    The value to compare against the column.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function orWhere( string|\Closure $column, string $operator = '=', $value = null ): self {
		// Handle closure for nested where groups.
		if ( $column instanceof \Closure ) {
			return $this->whereNested( $column, 'OR' );
		}

		if ( null === $value ) {
			$value    = $operator;
			$operator = '=';
		}

		$this->wheres[] = array(
			'type'     => 'basic',
			'column'   => $column,
			'operator' => $operator,
			'value'    => $value,
			'boolean'  => 'OR',
		);

		$this->bindings[] = $value;

		return $this;
	}

	/**
	 * Adds a nested where clause group to the query.
	 *
	 * Creates a new query builder instance, passes it to the closure,
	 * then merges the nested conditions wrapped in parentheses.
	 *
	 * @param \Closure $callback The closure receiving a fresh query builder instance.
	 * @param string   $boolean  The boolean connector (AND/OR) for this group.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	protected function whereNested( \Closure $callback, string $boolean = 'AND' ): self {
		$query = new static( str_replace( $this->connection->prefix(), '', $this->table ) );

		$callback( $query );

		if ( ! empty( $query->wheres ) ) {
			$this->wheres[] = array(
				'type'    => 'nested',
				'query'   => $query,
				'boolean' => $boolean,
			);

			// Merge bindings from nested query.
			$this->bindings = array_merge( $this->bindings, $query->bindings );
		}

		return $this;
	}
	/**
	 * Adds a "where in" clause to the query.
	 *
	 * @param string $column The name of the column to filter on.
	 * @param array $values The array of values to match against the column.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function whereIn( string $column, array $values ): self {
		if ( empty( $values ) ) {
			// Empty IN() is invalid SQL; add an impossible condition instead.
			$this->wheres[] = array(
				'type'    => 'raw',
				'sql'     => '0 = 1',
				'boolean' => 'AND',
			);

			return $this;
		}

		$this->wheres[] = array(
			'type'    => 'in',
			'column'  => $column,
			'values'  => $values,
			'boolean' => 'AND',
		);

		$this->bindings = array_merge( $this->bindings, $values );

		return $this;
	}
	/**
	 * Sets the query to return only distinct (unique) results.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function distinct(): self {
		$this->distinct = true;

		return $this;
	}

	/**
	 * Adds a condition to the query where the specified column must have a null value.
	 *
	 * @param string $column The name of the column to check for null.
	 *
	 * @return self The current instance for method chaining.
	 */
	public function whereNull( string $column ): self {
		$this->wheres[] = array(
			'type'    => 'null',
			'column'  => $column,
			'boolean' => 'AND',
		);

		return $this;
	}

	/**
	 * Adds a condition to the query requiring the specified column to not be null.
	 *
	 * @param string $column The name of the column to check for non-null values.
	 *
	 * @return self The current instance for method chaining.
	 */
	public function whereNotNull( string $column ): self {
		$this->wheres[] = array(
			'type'    => 'not_null',
			'column'  => $column,
			'boolean' => 'AND',
		);

		return $this;
	}


	// Add these methods to your QueryBuilder class

	/**
	 * Adds a "where not in" clause to the query.
	 *
	 * @param string $column The name of the column to filter on.
	 * @param array $values The array of values to exclude.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function whereNotIn( string $column, array $values ): self {
		if ( empty( $values ) ) {
			// Empty NOT IN() is a no-op — all rows match, so skip the clause.
			return $this;
		}

		$this->wheres[] = array(
			'type'    => 'not_in',
			'column'  => $column,
			'values'  => $values,
			'boolean' => 'AND',
		);

		$this->bindings = array_merge( $this->bindings, $values );

		return $this;
	}

	/**
	 * Adds an "or where in" clause to the query.
	 *
	 * @param string $column The name of the column to filter on.
	 * @param array $values The array of values to match against.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function orWhereIn( string $column, array $values ): self {
		if ( empty( $values ) ) {
			$this->wheres[] = array(
				'type'    => 'raw',
				'sql'     => '0 = 1',
				'boolean' => 'OR',
			);

			return $this;
		}

		$this->wheres[] = array(
			'type'    => 'in',
			'column'  => $column,
			'values'  => $values,
			'boolean' => 'OR',
		);

		$this->bindings = array_merge( $this->bindings, $values );

		return $this;
	}

	/**
	 * Adds an "or where not in" clause to the query.
	 *
	 * @param string $column The name of the column to filter on.
	 * @param array $values The array of values to exclude.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function orWhereNotIn( string $column, array $values ): self {
		if ( empty( $values ) ) {
			return $this;
		}

		$this->wheres[] = array(
			'type'    => 'not_in',
			'column'  => $column,
			'values'  => $values,
			'boolean' => 'OR',
		);

		$this->bindings = array_merge( $this->bindings, $values );

		return $this;
	}

	/**
	 * Filters the query to include rows where the specified column's value is between the given range.
	 *
	 * @param string $column The name of the column to compare.
	 * @param array $values An array containing two values representing the range [minimum, maximum].
	 * @param bool $isDate Indicates whether the values should be treated as dates. Default is true.
	 *
	 * @return self Returns the current query builder instance for method chaining.
	 */
	public function whereBetween( string $column, array $values, $isDate = true ): self {
		$this->wheres[] = array(
			'type'    => 'between',
			'column'  => $column,
			'values'  => $values,
			'boolean' => 'AND',
		);

		$this->bindings = array_merge( $this->bindings, $values );

		return $this;
	}

	/**
	 * Adds an OR condition to the query specifying that the column's value must be within the given range.
	 *
	 * @param string $column The name of the column to apply the condition to.
	 * @param array $values An array containing exactly two values representing the lower and upper bounds of the range.
	 *
	 * @return self Returns the current query builder instance for method chaining.
	 */
	public function orWhereBetween( string $column, array $values ): self {
		$this->wheres[] = array(
			'type'    => 'between',
			'column'  => $column,
			'values'  => $values,
			'boolean' => 'OR',
		);

		$this->bindings = array_merge( $this->bindings, $values );

		return $this;
	}

	/**
	 * Filters the query to exclude rows where the value of the specified column falls within the given range.
	 *
	 * @param string $column The name of the column to evaluate.
	 * @param array $values An array containing the lower and upper bounds of the range.
	 *
	 * @return self Returns the current query builder instance for method chaining.
	 */
	public function whereNotBetween( string $column, array $values ): self {
		$this->wheres[] = array(
			'type'    => 'not_between',
			'column'  => $column,
			'values'  => $values,
			'boolean' => 'AND',
		);

		$this->bindings = array_merge( $this->bindings, $values );

		return $this;
	}

	/**
	 * Adds a condition to the query using an OR conjunction, filtering rows where the specified column's value is not between the given values.
	 *
	 * @param string $column The name of the column to evaluate.
	 * @param array $values An array containing two values that define the range to exclude.
	 *
	 * @return self Returns the current query builder instance for method chaining.
	 */
	public function orWhereNotBetween( string $column, array $values ): self {
		$this->wheres[] = array(
			'type'    => 'not_between',
			'column'  => $column,
			'values'  => $values,
			'boolean' => 'OR',
		);

		$this->bindings = array_merge( $this->bindings, $values );

		return $this;
	}

	/**
	 * Adds a condition to the query to compare a date column using the specified operator and value.
	 *
	 * @param string $column The name of the column containing date values to compare.
	 * @param string $operator The comparison operator to use (e.g., '=', '!=', '<', '>', '<=', '>=').
	 * @param string $value The date value to compare against, formatted as 'YYYY-MM-DD'.
	 *
	 * @return self Returns the current query builder instance for method chaining.
	 */
	public function whereDate( string $column, string $operator, string $value ): self {
		$this->wheres[] = array(
			'type'     => 'date',
			'column'   => $column,
			'operator' => $operator,
			'value'    => $value,
			'boolean'  => 'AND',
		);

		$this->bindings[] = $value;
		return $this;
	}

	/**
	 * Adds an "or" condition to the query that compares a date column using the specified operator and value.
	 *
	 * @param string $column The name of the column to compare.
	 * @param string $operator The comparison operator (e.g., '=', '<', '>', '<=', '>=', '<>').
	 * @param string $value The date value to compare in 'YYYY-MM-DD' format.
	 *
	 * @return self Returns the current query builder instance for method chaining.
	 */
	public function orWhereDate( string $column, string $operator, string $value ): self {
		$this->wheres[] = array(
			'type'     => 'date',
			'column'   => $column,
			'operator' => $operator,
			'value'    => $value,
			'boolean'  => 'OR',
		);

		$this->bindings[] = $value;

		return $this;
	}

	/**
	 * Filters the query to include rows where the specified column matches the given date.
	 *
	 * @param string $column The name of the column to compare.
	 * @param string $date The date value to match in 'YYYY-MM-DD' format.
	 *
	 * @return self Returns the current query builder instance for method chaining.
	 */
	public function whereDateEquals( string $column, string $date ): self {
		return $this->whereDate( $column, '=', $date );
	}

	/**
	 * Adds a date-based "or" condition to the query, where the column's date matches the given value.
	 *
	 * @param string $column The name of the column to compare.
	 * @param string $date The date value to match in the condition.
	 *
	 * @return self Returns the query builder instance for method chaining.
	 */
	public function orWhereDateEquals( string $column, string $date ): self {
		return $this->orWhereDate( $column, '=', $date );
	}

	/**
	 * Adds a raw SQL query to the where clause with optional bindings.
	 *
	 * @param string $sql The raw SQL string to be used in the where clause.
	 * @param array $bindings An optional array of bindings to replace placeholders in the SQL string.
	 *
	 * @return self The current instance for method chaining.
	 */
	public function whereRaw( string $sql, array $bindings = array() ): self {
		$this->wheres[] = array(
			'type'    => 'raw',
			'sql'     => $sql,
			'boolean' => 'AND',
		);

		$this->bindings = array_merge( $this->bindings, $bindings );

		return $this;
	}

	/**
	 * Adds a raw SQL condition to the query using an 'OR' logical operator.
	 *
	 * @param string $sql The raw SQL condition to be added.
	 * @param array $bindings Optional bindings to replace placeholders within the raw SQL condition.
	 *
	 * @return self The current query builder instance for method chaining.
	 */
	public function orWhereRaw( string $sql, array $bindings = array() ): self {
		$this->wheres[] = array(
			'type'    => 'raw',
			'sql'     => $sql,
			'boolean' => 'OR',
		);

		$this->bindings = array_merge( $this->bindings, $bindings );

		return $this;
	}

	// Option 2: Add specific date range methods

	/**
	 * Filters the query by checking if the values of a given column fall between two specified dates.
	 *
	 * @param string $column The name of the database column to filter.
	 * @param string $startDate The start date of the range in 'Y-m-d' format.
	 * @param string $endDate The end date of the range in 'Y-m-d' format.
	 *
	 * @return self Returns the current query instance with the applied date range filter.
	 */
	public function whereDateBetween( string $column, string $startDate, string $endDate ): self {
		// For datetime columns, include full day range
		$startDateTime = $startDate . ' 00:00:00';
		$endDateTime   = $endDate . ' 23:59:59';

		return $this->whereBetween( $column, array( $startDateTime, $endDateTime ) );
	}

	/**
	 * Adds a condition to the query that matches records where the date column value falls within the specified range.
	 * The range is inclusive and applies to dates with full day precision.
	 *
	 * @param string $column The name of the date column to apply the condition to.
	 * @param string $startDate The starting date of the range in 'YYYY-MM-DD' format.
	 * @param string $endDate The ending date of the range in 'YYYY-MM-DD' format.
	 *
	 * @return self The current query builder instance for method chaining.
	 */
	public function orWhereDateBetween( string $column, string $startDate, string $endDate ): self {
		$startDateTime = $startDate . ' 00:00:00';
		$endDateTime   = $endDate . ' 23:59:59';

		return $this->orWhereBetween( $column, array( $startDateTime, $endDateTime ) );
	}

	// Option 3: Add month/year specific methods

	/**
	 * Adds a condition to filter records by a specific month on the given column.
	 *
	 * @param string $column The name of the column to apply the month condition.
	 * @param int $month The numeric representation of the month (1 for January, 12 for December).
	 *
	 * @return self The current instance for method chaining.
	 */
	public function whereMonth( string $column, int $month ): self {
		$this->wheres[] = array(
			'type'    => 'month',
			'column'  => $column,
			'value'   => $month,
			'boolean' => 'AND',
		);

		$this->bindings[] = $month;

		return $this;
	}

	/**
	 * Adds a "WHERE YEAR" condition to the query for filtering results based on a specific year.
	 *
	 * @param string $column The name of the column to apply the YEAR condition on.
	 * @param int $year The year value to filter the column by.
	 *
	 * @return self The current query instance with the added condition.
	 */
	public function whereYear( string $column, int $year ): self {
		$this->wheres[] = array(
			'type'    => 'year',
			'column'  => $column,
			'value'   => $year,
			'boolean' => 'AND',
		);

		$this->bindings[] = $year;

		return $this;
	}

	// Option 4: Alternative date range using >= and <= operators

	/**
	 * Adds a date range condition to the query for a specified column.
	 *
	 * @param string $column The name of the column to apply the date range condition.
	 * @param string $startDate The starting date of the range in 'Y-m-d' format.
	 * @param string $endDate The ending date of the range in 'Y-m-d' format.
	 *
	 * @return self The current instance with the applied date range conditions.
	 */
	public function whereDateRange( string $column, string $startDate, string $endDate ): self {
		$this->where( $column, '>=', $startDate . ' 00:00:00' );
		$this->where( $column, '<=', $endDate . ' 23:59:59' );

		return $this;
	}

	/**
	 * Adds an inner join clause to the query.
	 *
	 * @param string $table The name of the table to join.
	 * @param string $first The first column in the join condition.
	 * @param string $operator The comparison operator for the join condition.
	 * @param string $second The second column in the join condition.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function join( string $table, string $first, string $operator, string $second ): self {
		$this->joins[] = array(
			'type'     => 'inner',
			'table'    => $this->connection->prefix() . $table,
			'first'    => $first,
			'operator' => $operator,
			'second'   => $second,
		);

		return $this;
	}

	/**
	 * Adds a left join clause to the query.
	 *
	 * @param string $table The name of the table to join.
	 * @param string $first The first column for the join condition.
	 * @param string $operator The operator for the join condition.
	 * @param string $second The second column for the join condition.
	 *
	 * @return self Returns the current query object with the added join clause.
	 */
	public function leftJoin( string $table, string $first, string $operator, string $second ): self {
		$this->joins[] = array(
			'type'     => 'left',
			'table'    => $this->connection->prefix() . $table,
			'first'    => $this->connection->prefix() . $first,
			'operator' => $operator,
			'second'   => $this->connection->prefix() . $second,
		);

		return $this;
	}

	/**
	 * Sorts the results by a specified column in the given direction.
	 *
	 * @param string $column The name of the column to sort by.
	 * @param string $direction The direction to sort, either 'ASC' (ascending) or 'DESC' (descending). Defaults to 'ASC'.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function orderBy( string $column, string $direction = 'ASC' ): self {
		$this->orders[] = array(
			'column'    => $column,
			'direction' => strtoupper( $direction ),
		);

		return $this;
	}

	/**
	 * Order by a raw SQL expression.
	 *
	 * The expression is inlined into the query, so never pass user input here.
	 *
	 * @param string $expression Raw SQL ordering expression.
	 * @param string $direction  ASC or DESC.
	 *
	 * @return self
	 */
	public function orderByRaw( string $expression, string $direction = 'ASC' ): self {
		$this->orders[] = array(
			'type'       => 'raw',
			'expression' => $expression,
			'direction'  => strtoupper( $direction ),
		);
		return $this;
	}

	/**
	 * Groups the query results based on the specified column.
	 *
	 * @param string $column The name of the column to group by.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function groupBy( string $column ): self {
		$this->groups[] = $column;

		return $this;
	}

	/**
	 * Adds a raw group by expression to the query builder.
	 *
	 * @param string $expression The raw SQL expression to group the query results by.
	 *
	 * @return self The current instance of the query builder.
	 */
	public function groupByRaw( string $expression ): self {
		$this->groups[] = array(
			'type'       => 'raw',
			'expression' => $expression,
		);

		return $this;
	}

	/**
	 * Sets the maximum number of records to retrieve.
	 *
	 * @param int $limit The maximum number of records.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function limit( int $limit ): self {
		$this->limit = $limit;

		return $this;
	}

	/**
	 * Sets the offset value for a query or operation.
	 *
	 * @param int $offset The number of items to skip.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function offset( int $offset ): self {
		$this->offset = $offset;

		return $this;
	}

	/**
	 * Sets a limit on the number of results to return.
	 *
	 * @param int $limit The maximum number of results to retrieve.
	 *
	 * @return self The current instance for method chaining.
	 */
	public function take( int $limit ): self {
		return $this->limit( $limit );
	}

	/**
	 * Skips a specified number of records.
	 *
	 * @param int $offset The number of records to skip.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function skip( int $offset ): self {
		return $this->offset( $offset );
	}

	/**
	 * Executes a query to retrieve data from the database and returns the results as an array.
	 *
	 * @return array The results of the executed query.
	 */
	public function get(): array {
		$sql      = $this->buildSelectQuery();
		$prepared = $this->connection->prepare( $sql, ...$this->bindings );

		return $this->connection->get_results( $prepared );
	}

	/**
	 * Checks if the result of the query is empty.
	 *
	 * @return bool Returns true if the query does not return any rows, otherwise false.
	 */
	public function isEmpty() {
		$sql      = $this->buildSelectQuery();
		$prepared = $this->connection->prepare( $sql, ...$this->bindings );

		return $this->connection->get_var( $prepared ) === null;
	}

	/**
	 * Converts the results of the query into an array of associated arrays.
	 *
	 * @return array An array where each element is an associative array representation of a query result.
	 */
	public function toArray(): array {
		$results = $this->get();

		return array_map(
			function ( $result ) {
				return (array) $result;
			},
			$results
		);
	}

	/**
	 * Retrieves the first record from the results.
	 *
	 * @return object|null The first result object, or null if no results are found.
	 */
	public function first(): ?object {
		$this->limit( 1 );
		$results = $this->get();

		return $results[0] ?? null;
	}

	/**
	 * Retrieves the last record from the database table based on the primary key.
	 *
	 * If no primary key is explicitly defined, defaults to using 'id'. Orders the results in descending order by the primary key and limits to one record.
	 *
	 * @return object|null Returns the last record as an object if found, or null if no records exist.
	 */
	public function last(): ?object {
		$primaryKey = $this->primaryKey ?? 'id'; // fallback if you don't have a defined PK
		$this->orderBy( $primaryKey, 'DESC' )->limit( 1 );
		$results = $this->get();

		return $results[0] ?? null;
	}

	/**
	 * Retrieves an object by its unique identifier.
	 *
	 * @param int $id The unique identifier of the object to find.
	 *
	 * @return object|null The object if found, or null if not found.
	 */
	public function find( int $id ): ?object {
		return $this->where( 'id', $id )->first();
	}

	/**
	 * Counts the total number of records based on the current query conditions.
	 *
	 * @return int The total count of records.
	 */
	public function count(): int {
		$originalSelects = $this->selects;
		$this->selects   = array( 'COUNT(*) as count' );

		$sql      = $this->buildSelectQuery();
		$prepared = $this->connection->prepare( $sql, ...$this->bindings );
		$result   = $this->connection->get_var( $prepared );

		$this->selects = $originalSelects;

		return (int) $result;
	}

	/**
	 * Checks if any records exist in the current query context.
	 *
	 * @return bool True if records exist, false otherwise.
	 */
	public function exists(): bool {
		return $this->count() > 0;
	}

	/**
	 * Paginates the query results based on the given parameters.
	 *
	 * @param int $perPage Number of items to display per page. Default is 10.
	 * @param int $page The current page number to fetch. Default is 1.
	 * @param string $order The sorting order, either 'ASC' or 'DESC'. Default is 'DESC'.
	 * @param string $orderBy The column name to sort by. Default is 'created_at'.
	 * @param string|null $searchColumn The column to search in, if applicable. Default is null.
	 * @param string|null $searchData The data to search for within the specified column. Default is null.
	 * @param array $where Additional conditions to apply as an array of column, operator, and value. Default is an empty array.
	 *
	 * @return PaginationResult Returns a PaginationResult instance containing the paginated items and metadata.
	 */
	public function paginate( int $perPage = 10, int $page = 1, $order = 'DESC', $orderBy = 'created_at', $searchColumn = null, $searchData = null, $where = array() ): PaginationResult {
		$total   = $this->count();
		$offset  = ( $page - 1 ) * $perPage;
		$isWhere = '';
		if ( $where ) {
			if ( is_array( $where ) ) {
				foreach ( $where as $value ) {
					$isWhere = $this->where( $value['column'], $value['operator'], $value['value'] );
				}
			}
			$total = $this->count();
		}
		if ( $isWhere ) {
			$query = $isWhere;
		} else {
			$query = $this;
		}
		$query = $query->limit( $perPage )->offset( $offset );
		if ( $order ) {
			$query = $query->orderBy( $orderBy, $order );
		}

		if ( $searchColumn && $searchData ) {
			global $wpdb;
			$columns    = array_map( 'trim', explode( ',', $searchColumn ) );
			$like_value = '%' . $wpdb->esc_like( $searchData ) . '%';
			$conditions = array();

			foreach ( $columns as $col ) {
				// $col is a column name, which cannot be a prepare() placeholder;
				// it comes from the caller's column list, never from request data.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$conditions[] = $wpdb->prepare( "`{$col}` LIKE %s", $like_value );
			}

			$query = $query->whereRaw( '(' . implode( ' OR ', $conditions ) . ')' );
			$total = $query->count();
		}
		$items = $query->get();
		return new PaginationResult( $items, $total, $perPage, $page );
	}

	/**
	 * Inserts a new record into the database table.
	 *
	 * @param array $data An associative array where the keys are column names and the values are the corresponding data to insert.
	 *
	 * @return int|false Returns the ID of the inserted record as an integer on success, or false on failure.
	 */
	public function insert( array $data ): int|false {
		$columns      = array_keys( $data );
		$values       = array_values( $data );
		$placeholders = array_fill( 0, count( $values ), '%s' );

		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s)',
			$this->table,
			implode( ', ', $columns ),
			implode( ', ', $placeholders )
		);

		$prepared = $this->connection->prepare( $sql, ...$values );
		$result   = $this->connection->query( $prepared );

		return false !== $result ? $this->connection->insert_id() : false;
	}

	/**
	 * Updates the records in the database for the current table based on the provided data.
	 *
	 * @param array $data An associative array where the keys are column names and the values are the new values to be set.
	 *
	 * @return int|false Returns the number of rows affected by the update operation, or false on failure.
	 */
	public function update( array $data ): int|false {
		$setParts = array();
		$bindings = array();

		foreach ( $data as $column => $value ) {
			$setParts[] = "$column = %s";
			$bindings[] = $value;
		}

		$sql = sprintf( 'UPDATE %s SET %s', $this->table, implode( ', ', $setParts ) );

		if ( ! empty( $this->wheres ) ) {
			$sql     .= ' WHERE ' . $this->buildWhereClause();
			$bindings = array_merge( $bindings, $this->bindings );
		}

		$prepared = $this->connection->prepare( $sql, ...$bindings );

		return $this->connection->query( $prepared );
	}

	/**
	 *
	 * Deletes records from the database table based on the defined conditions.
	 *
	 * @return int|false The number of rows affected by the delete operation, or false on failure.
	 */
	public function delete(): int|false {
		$sql = "DELETE FROM {$this->table}";

		if ( ! empty( $this->wheres ) ) {
			$sql .= ' WHERE ' . $this->buildWhereClause();
		}

		$prepared = $this->connection->prepare( $sql, ...$this->bindings );

		return $this->connection->query( $prepared );
	}

	/**
	 * Executes a raw SQL query with optional binding parameters and returns the results as an array.
	 *
	 * @param string $query The raw SQL query to execute.
	 * @param array $bindings Optional array of parameters to bind to the query.
	 *
	 * @return array The results of the executed query.
	 */
	public function raw( string $query, array $bindings = array() ): array {
		if ( ! empty( $bindings ) ) {
			$query = $this->connection->prepare( $query, ...$bindings );
		}

		return $this->connection->get_results( $query );
	}

	/**
	 * Constructs and returns an SQL SELECT query string based on the specified select clauses, joins,
	 * where conditions, groupings, orderings, and optional limit and offset constraints.
	 *
	 * @return string The generated SQL SELECT query string.
	 */
	private function buildSelectQuery(): string {
		$sql = sprintf( 'SELECT %s FROM %s', implode( ', ', $this->selects ), $this->table );

		// Add joins
		foreach ( $this->joins as $join ) {
			$sql .= sprintf(
				' %s JOIN %s ON %s %s %s',
				strtoupper( $join['type'] ),
				$join['table'],
				$join['first'],
				$join['operator'],
				$join['second']
			);
		}

		// Add where clauses
		if ( ! empty( $this->wheres ) ) {
			$sql .= ' WHERE ' . $this->buildWhereClause();
		}

		// Add group by
		if ( ! empty( $this->groups ) ) {
			$groupClauses = array();

			foreach ( $this->groups as $group ) {
				if ( is_array( $group ) && $group['type'] === 'raw' ) {
					$groupClauses[] = $group['expression'];
				} else {
					$groupClauses[] = $group;
				}
			}
			$sql .= ' GROUP BY ' . implode( ', ', $groupClauses );
		}

		// Add order by
		if ( ! empty( $this->orders ) ) {
			$order_parts = array();

			foreach ( $this->orders as $order ) {
				// Handle raw ORDER BY expressions.
				if ( isset( $order['type'] ) && 'raw' === $order['type'] ) {
					$order_parts[] = $order['expression'];
					continue;
				}

				// Handle standard column ordering.
				if ( isset( $order['column'] ) ) {
					$direction     = isset( $order['direction'] ) ? $order['direction'] : 'ASC';
					$order_parts[] = $order['column'] . ' ' . $direction;
				}
			}

			if ( ! empty( $order_parts ) ) {
				$sql .= ' ORDER BY ' . implode( ', ', $order_parts );
			}
		}

		// Add limit and offset
		if ( null !== $this->limit ) {
			$sql .= ' LIMIT ' . $this->limit;
			if ( null !== $this->offset ) {
				$sql .= ' OFFSET ' . $this->offset;
			}
		}

		return $sql;
	}

	/**
	 * Builds a SQL WHERE clause based on the conditions stored in the $wheres property.
	 *
	 * This method processes each condition, constructing the appropriate
	 * SQL fragment based on the type of condition (basic, in, null, or not_null).
	 * The fragments are combined into a complete WHERE clause string.
	 *
	 * @return string The constructed SQL WHERE clause.
	 */
	private function buildWhereClause(): string {
		$parts   = array();
		$isFirst = true;

		foreach ( $this->wheres as $where ) {
			$boolean = $isFirst ? '' : $where['boolean'] . ' ';

			switch ( $where['type'] ) {
				case 'date':
				case 'basic':
					$parts[] = $boolean . $where['column'] . ' ' . $where['operator'] . ' %s';
					break;
				case 'in':
					$placeholders = implode( ', ', array_fill( 0, count( $where['values'] ), '%s' ) );
					$parts[]      = $boolean . $where['column'] . ' IN (' . $placeholders . ')';
					break;
				case 'not_in':
					$placeholders = implode( ', ', array_fill( 0, count( $where['values'] ), '%s' ) );
					$parts[]      = $boolean . $where['column'] . ' NOT IN (' . $placeholders . ')';
					break;
				case 'null':
					$parts[] = $boolean . $where['column'] . ' IS NULL';
					break;
				case 'not_null':
					$parts[] = $boolean . $where['column'] . ' IS NOT NULL';
					break;
				case 'between':
					$parts[] = $boolean . $where['column'] . ' BETWEEN %s AND %s';
					break;

				case 'not_between':
					$parts[] = $boolean . $where['column'] . ' NOT BETWEEN %s AND %s';
					break;

				case 'raw':
					$parts[] = $boolean . $where['sql'];
					break;

				case 'month':
					$parts[] = $boolean . 'MONTH(' . $where['column'] . ') = %s';
					break;

				case 'year':
					$parts[] = $boolean . 'YEAR(' . $where['column'] . ') = %s';
					break;
				case 'nested':
					$nestedSql = $where['query']->buildWhereClause();
					$parts[]   = $boolean . '(' . $nestedSql . ')';
					break;
			}

			$isFirst = false;
		}

		return implode( ' ', $parts );
	}

	/**
	 * Conditionally applies a callback to the query based on a given condition.
	 *
	 * @param mixed $condition The condition to evaluate. If truthy, the callback will be executed.
	 * @param callable $callback The callback function to execute if the condition is truthy.
	 *                          The callback receives the current QueryBuilder instance as its first parameter.
	 * @param callable|null $default Optional callback to execute if the condition is falsy.
	 *                              The callback receives the current QueryBuilder instance as its first parameter.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function when( $condition, callable $callback, ?callable $default = null ): self {
		if ( $condition ) {
			return $callback( $this ) ?? $this;
		} elseif ( $default ) {
			return $default( $this ) ?? $this;
		}

		return $this;
	}

	/**
	 * Conditionally applies a callback to the query when the condition is falsy.
	 *
	 * @param mixed $condition The condition to evaluate. If falsy, the callback will be executed.
	 * @param callable $callback The callback function to execute if the condition is falsy.
	 *                          The callback receives the current QueryBuilder instance as its first parameter.
	 * @param callable|null $default Optional callback to execute if the condition is truthy.
	 *                              The callback receives the current QueryBuilder instance as its first parameter.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function unless( $condition, callable $callback, ?callable $default = null ): self {
		if ( ! $condition ) {
			return $callback( $this ) ?? $this;
		} elseif ( $default ) {
			return $default( $this ) ?? $this;
		}

		return $this;
	}

	/**
	 * Calculates the sum of values from the specified column in the database.
	 *
	 * @param string $column The name of the column to sum the values from.
	 *
	 * @return float The sum of the values in the specified column.
	 */
	public function sum( $column ): float {
		$originalSelects = $this->selects;
		$this->selects   = array( "SUM({$column}) as total" );

		$sql      = $this->buildSelectQuery();
		$prepared = $this->connection->prepare( $sql, ...$this->bindings );
		$result   = $this->connection->get_var( $prepared );

		$this->selects = $originalSelects;

		return (float) $result;
	}

	/**
	 * Sets a raw query string to be used for the select operation.
	 *
	 * @param string $rawQuery The raw SQL query to be set for the select operation.
	 *
	 * @return self The current instance with the raw query applied.
	 */
	public function selectRaw( string $rawQuery ): self {
		$this->selects = array( $rawQuery );

		return $this;
	}
}
