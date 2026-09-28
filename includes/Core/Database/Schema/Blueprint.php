<?php
/**
 * Adds a new unsigned big integer column to the table schema.
 *
 * @param string $column The name of the column to be added.
 *
 * @return ColumnDefinition The column definition instance for further configuration.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Adds a new unsigned medium integer column to the table.
 *
 * @param string $column The name of the column to be added.
 *
 * @return ColumnDefinition The column definition instance for further configuration.
 */
class Blueprint {
	/**
	 * The name of the table to be created or modified.
	 *
	 * @var string
	 */
	private string $table;
	/**
	 * An array of column definitions for the table.
	 *
	 * @var ColumnDefinition[]
	 */
	private array $columns = array();
	/**
	 * An array of indexes to be created on the table.
	 *
	 * @var array
	 */
	private array $indexes = array();
	/**
	 * An array of foreign key definitions for the table.
	 *
	 * @var ForeignKeyDefinition[]
	 */
	private array $foreignKeys = array();
	/**
	 * The database engine to be used for the table.
	 *
	 * @var string
	 */
	private string $engine = 'InnoDB';
	/**
	 * The character set to be used for database connections.
	 *
	 * @var string
	 */
	private string $charset = 'utf8mb4';
	/**
	 * The collation to be used for the table.
	 *
	 * @var string
	 */
	private string $collation = 'utf8mb4_unicode_ci';
	/**
	 * The primary key column name, if set.
	 *
	 * @var string|null
	 */
	private ?string $primaryKey = null;
	/**
	 * Indicates whether the table should have timestamps (created_at, updated_at).
	 *
	 * @var bool
	 */
	private bool $timestamps = false;
	/**
	 * Indicates whether the table should support soft deletes.
	 *
	 * @var bool
	 */
	private bool $softDeletes = false;

	/**
	 * Constructor method for initializing the table name.
	 *
	 * @param string $table The name of the table to be associated with this instance.
	 *
	 * @return void
	 */
	public function __construct( string $table ) {
		$this->table = $table;
	}

	// Primary key and auto increment

	/**
	 * Adds an auto-incrementing primary key column to the table.
	 *
	 * @param string $column The name of the column to be used as the primary key. Defaults to 'id'.
	 *
	 * @return ColumnDefinition The definition object for the created column.
	 */
	public function id( string $column = 'id' ): ColumnDefinition {
		$this->primaryKey = $column;

		return $this->addColumn(
			$column,
			'bigint',
			array(
				'length'         => 20,
				'unsigned'       => true,
				'auto_increment' => true,
				'primary'        => true,
			)
		);
	}

	/**
	 * Adds an auto-incrementing integer column, typically used for primary keys.
	 *
	 * @param string $column The name of the column. Defaults to 'id'.
	 *
	 * @return ColumnDefinition The column definition instance for further configuration.
	 */
	public function increments( string $column = 'id' ): ColumnDefinition {
		return $this->addColumn(
			$column,
			'int',
			array(
				'length'         => 10,
				'unsigned'       => true,
				'auto_increment' => true,
				'primary'        => true,
			)
		);
	}

	/**
	 * Creates a big auto-incrementing primary key column.
	 *
	 * @param string $column The name of the column. Defaults to 'id'.
	 *
	 * @return ColumnDefinition The definition of the created column.
	 */
	public function bigIncrements( string $column = 'id' ): ColumnDefinition {
		return $this->id( $column );
	}

	// String columns

	/**
	 * Adds a string column to the table schema.
	 *
	 * @param string $column The name of the column.
	 * @param int $length The length of the column. Defaults to 255.
	 *
	 * @return ColumnDefinition The column definition instance.
	 */
	public function string( string $column, int $length = 255 ): ColumnDefinition {
		return $this->addColumn( $column, 'varchar', array( 'length' => $length ) );
	}

	/**
	 * Add a CHAR column to the table schema.
	 *
	 * @param string $column The name of the column.
	 * @param int $length The length of the CHAR column. Defaults to 255.
	 *
	 * @return ColumnDefinition The column definition instance.
	 */
	public function char( string $column, int $length = 255 ): ColumnDefinition {
		return $this->addColumn( $column, 'char', array( 'length' => $length ) );
	}

	/**
	 * Adds a text column to the table schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The definition of the added column.
	 */
	public function text( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'text' );
	}

	/**
	 * Adds a 'mediumtext' column to the table schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The column definition instance.
	 */
	public function mediumText( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'mediumtext' );
	}

	/**
	 * Adds a column of type 'longtext' to the database schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The definition of the added column.
	 */
	public function longText( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'longtext' );
	}

	// Numeric columns

	/**
	 * Adds an integer column to the table schema with a default length of 11.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The definition of the added column.
	 */
	public function integer( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'int', array( 'length' => 11 ) );
	}

	/**
	 * Add a tiny integer column to the table schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The column definition instance for further configuration.
	 */
	public function tinyInteger( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'tinyint', array( 'length' => 4 ) );
	}

	/**
	 * Adds a small integer column to the table schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The column definition after adding a small integer column.
	 */
	public function smallInteger( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'smallint', array( 'length' => 6 ) );
	}

	/**
	 * Adds a medium integer column to the table schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The definition of the newly added column.
	 */
	public function mediumInteger( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'mediumint', array( 'length' => 9 ) );
	}

	/**
	 * Adds a big integer column to the database schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The column definition instance for further customization.
	 */
	public function bigInteger( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'bigint', array( 'length' => 20 ) );
	}

	/**
	 * Adds an unsigned integer column definition to the table schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The column definition instance for further modifications.
	 */
	public function unsignedInteger( string $column ): ColumnDefinition {
		return $this->addColumn(
			$column,
			'int',
			array(
				'length'   => 10,
				'unsigned' => true,
			)
		);
	}

	/**
	 * Add a new unsigned big integer column to the table.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The column definition instance for further customization.
	 */
	public function unsignedBigInteger( string $column ): ColumnDefinition {
		return $this->addColumn(
			$column,
			'bigint',
			array(
				'length'   => 20,
				'unsigned' => true,
			)
		);
	}

	// Decimal columns

	/**
	 * Adds a decimal column to the table with the specified precision and scale.
	 *
	 * @param string $column The name of the column.
	 * @param int $precision The total number of digits for the decimal column. Defaults to 8.
	 * @param int $scale The number of digits to the right of the decimal point. Defaults to 2.
	 *
	 * @return ColumnDefinition The column definition instance for the added decimal column.
	 */
	public function decimal( string $column, int $precision = 8, int $scale = 2 ): ColumnDefinition {
		return $this->addColumn(
			$column,
			'decimal',
			array(
				'precision' => $precision,
				'scale'     => $scale,
			)
		);
	}

	/**
	 * Adds a float column to the table schema with the specified precision and scale.
	 *
	 * @param string $column The name of the column.
	 * @param int $precision The total number of digits that the column can store.
	 * @param int $scale The number of digits to the right of the decimal point.
	 *
	 * @return ColumnDefinition The column definition instance for the added float column.
	 */
	public function float( string $column, int $precision = 8, int $scale = 2 ): ColumnDefinition {
		return $this->addColumn(
			$column,
			'float',
			array(
				'precision' => $precision,
				'scale'     => $scale,
			)
		);
	}

	/**
	 * Adds a column with a double data type to the table.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The definition of the added column.
	 */
	public function double( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'double' );
	}

	// Boolean column

	/**
	 * Adds a boolean column to the table schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The column definition instance.
	 */
	public function boolean( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'tinyint', array( 'length' => 1 ) );
	}

	// Date and time columns

	/**
	 * Adds a date column to the table schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The column definition after adding the date column.
	 */
	public function date( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'date' );
	}

	/**
	 * Adds a time column to the table schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The column definition after adding the time column.
	 */
	public function time( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'time' );
	}

	/**
	 * Adds a datetime column to the table schema.
	 *
	 * @param string $column The name of the column to which the datetime type should be assigned.
	 *
	 * @return ColumnDefinition Returns the column definition object after adding the datetime column.
	 */
	public function dateTime( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'datetime' );
	}

	/**
	 * Adds a timestamp column to the table schema.
	 *
	 * @param string $column The name of the column to be defined as a timestamp type.
	 *
	 * @return ColumnDefinition The instance of the ColumnDefinition for further customization.
	 */
	public function timestamp( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'timestamp' );
	}

	// JSON column (stored as longtext for compatibility)

	/**
	 * Adds a JSON column to the table.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The column definition for the newly added JSON column.
	 */
	public function json( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'longtext' );
	}

	// Binary columns

	/**
	 * Adds a binary column to the table schema.
	 *
	 * @param string $column The name of the column to be added.
	 *
	 * @return ColumnDefinition The column definition object for further customization.
	 */
	public function binary( string $column ): ColumnDefinition {
		return $this->addColumn( $column, 'blob' );
	}

	// Enum column

	/**
	 * Adds an ENUM column type to the table schema.
	 *
	 * @param string $column The name of the column to be added.
	 * @param array $values An array of valid values for the ENUM column.
	 *
	 * @return ColumnDefinition The column definition instance for further modifications.
	 */
	public function enum( string $column, array $values ): ColumnDefinition {
		return $this->addColumn( $column, 'enum', array( 'values' => $values ) );
	}

	// Foreign key helpers

	/**
	 * Adds a foreign ID column with an unsigned big integer type.
	 *
	 * @param string $column The name of the foreign ID column to be added.
	 *
	 * @return ColumnDefinition The column definition for further customization.
	 */
	public function foreignId( string $column ): ColumnDefinition {
		return $this->unsignedBigInteger( $column );
	}

	/**
	 * Add a foreign ID column for the given model to the database table.
	 *
	 * @param string $model The name of the model the foreign key will reference.
	 * @param string|null $column The foreign key column name. If null, it will default to the foreign key name derived from the model.
	 *
	 * @return ColumnDefinition The column definition instance for the foreign ID column.
	 */
	public function foreignIdFor( string $model, ?string $column = null ): ColumnDefinition {
		$column = $column ?: $this->getForeignKeyName( $model ); // phpcs:ignore

		return $this->foreignId( $column );
	}

	// Timestamps (created_at, updated_at)
	/**
	 * Adds created_at and updated_at timestamp columns to the table.
	 *
	 * @return self
	 */
	public function timestamps(): self {
		$this->timestamp( 'created_at' )->nullable()->default( 'CURRENT_TIMESTAMP' );
		$this->timestamp( 'updated_at' )->nullable()->default( 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP' );
		$this->timestamps = true;

		return $this;
	}

	// Soft deletes

	/**
	 * Enables soft delete functionality by adding a nullable timestamp column.
	 *
	 * @param string $column The name of the column to store the delete timestamp. Default is 'deleted_at'.
	 *
	 * @return ColumnDefinition The column definition after adding the soft delete column.
	 */
	public function softDeletes( string $column = 'deleted_at' ): ColumnDefinition {
		$this->softDeletes = true;

		return $this->timestamp( $column )->nullable();
	}

	// Indexes

	/**
	 * Adds a primary key index to the specified columns.
	 *
	 * @param string|array $columns The column name or an array of column names to be set as a primary key.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function primary( string|array $columns ): self {
		$columns         = is_array( $columns ) ? $columns : func_get_args();
		$this->indexes[] = array(
			'type'    => 'primary',
			'columns' => $columns,
			'name'    => 'PRIMARY',
		);

		return $this;
	}

	/**
	 * Adds an index to the table definition.
	 *
	 * @param string|array $columns The column or columns to include in the index.
	 * @param string|null $name The name of the index. If null, a name will be generated automatically.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function index( string|array $columns, ?string $name = null ): self {
		$columns = is_array( $columns ) ? $columns : array( $columns );

		$this->indexes[] = array(
			'type'    => 'index',
			'columns' => $columns,
			'name'    => $name ?: $this->createIndexName( 'index', $columns ), // phpcs:ignore
		);

		return $this;
	}

	/**
	 * Adds a unique index to the specified column(s).
	 *
	 * @param string|array $columns The column name or an array of column names to apply the unique index.
	 * @param string|null $name Optional. The name of the unique index. If not provided, a name will be generated automatically.
	 *
	 * @return self Returns the current instance, allowing method chaining.
	 */
	public function unique( string|array $columns, ?string $name = null ): self {
		$columns = is_array( $columns ) ? $columns : array( $columns );

		$this->indexes[] = array(
			'type'    => 'unique',
			'columns' => $columns,
			'name'    => $name ?: $this->createIndexName( 'unique', $columns ), // phpcs:ignore
		);

		return $this;
	}

	/**
	 * Adds a full-text index to the specified column(s) in the table.
	 *
	 * @param string|array $columns The column or columns to include in the full-text index.
	 * @param string|null $name Optional. The name of the full-text index. If not provided, a default name will be generated.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function fullText( string|array $columns, ?string $name = null ): self {
		$columns = is_array( $columns ) ? $columns : array( $columns );

		$this->indexes[] = array(
			'type'    => 'fulltext',
			'columns' => $columns,
			'name'    => $name ?: $this->createIndexName( 'fulltext', $columns ), // phpcs:ignore
		);

		return $this;
	}

	// Foreign keys

	/**
	 * Add a foreign key constraint to the specified column(s).
	 *
	 * @param string|array $columns The column name or an array of column names to define the foreign key constraint.
	 *
	 * @return ForeignKeyDefinition Returns an instance of ForeignKeyDefinition representing the foreign key constraint.
	 */
	public function foreign( string|array $columns ): ForeignKeyDefinition {
		$columns             = is_array( $columns ) ? $columns : array( $columns );
		$foreign             = new ForeignKeyDefinition( $columns );
		$this->foreignKeys[] = $foreign;

		return $foreign;
	}

	// Table options

	/**
	 * Sets the database engine for the table.
	 *
	 * @param string $engine The name of the database engine to be used.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function engine( string $engine ): self {
		$this->engine = $engine;

		return $this;
	}

	/**
	 * Sets the character set for the given configuration.
	 *
	 * @param string $charset The character set to be used.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function charset( string $charset ): self {
		$this->charset = $charset;

		return $this;
	}

	/**
	 * Sets the collation for the current instance.
	 *
	 * @param string $collation The collation to be set.
	 *
	 * @return self Returns the current instance.
	 */
	public function collation( string $collation ): self {
		$this->collation = $collation;

		return $this;
	}

	// Internal methods

	/**
	 * Adds a new column definition to the current collection of columns.
	 *
	 * @param string $name The name of the column to be added.
	 * @param string $type The data type of the column.
	 * @param array $options Optional parameters for the column definition.
	 *
	 * @return ColumnDefinition The newly created column definition instance.
	 */
	private function addColumn( string $name, string $type, array $options = array() ): ColumnDefinition {
		$column          = new ColumnDefinition( $name, $type, $options );
		$this->columns[] = $column;

		return $column;
	}

	/**
	 * Constructs the index name based on the table name, columns, and index type.
	 *
	 * @param string $type The type of the index (e.g., unique, primary, etc.).
	 * @param array $columns The columns included in the index.
	 *
	 * @return string The constructed index name.
	 */
	private function createIndexName( string $type, array $columns ): string {
		return $this->table . '_' . implode( '_', $columns ) . '_' . $type;
	}

	/**
	 * Generates the foreign key name for the given model.
	 *
	 * @param string $model The fully qualified class name of the model.
	 *
	 * @return string The generated foreign key name in the format of `<model>_id` in lowercase.
	 */
	private function getForeignKeyName( string $model ): string {
		if ( false !== $pos = strrpos( $model, '\\' ) ) { // phpcs:ignore
			$model = substr( $model, $pos + 1 );
		}
		return strtolower( $model ) . '_id';
	}

	// Generate SQL for dbDelta (WordPress compatible)

	/**
	 * Generates the SQL string for creating a database table based on the defined schema.
	 *
	 * @return string The SQL statement representing the structure of the table, including columns, primary keys,
	 *                and indexes. Foreign key constraints are excluded.
	 */
	public function toSql(): string {
		global $wpdb;

		$tableName = $wpdb->prefix . $this->table;
		$sql       = "CREATE TABLE {$tableName} (\n";

		// Add columns
		$columnSql = array();
		foreach ( $this->columns as $column ) {
			$columnSql[] = '  ' . $column->toSql();
		}
		$sql .= implode( ",\n", $columnSql );
		// Add primary key if set and not already defined in column
		if ( $this->primaryKey && $this->hasPrimaryKeyInColumns() ) {
			$sql .= ",\n  PRIMARY KEY ({$this->primaryKey})";
		}
		// Add indexes
		if ( ! empty( $this->indexes ) ) {
			foreach ( $this->indexes as $index ) {
				if ( 'primary' !== $index['type'] || ! $this->hasPrimaryKeyInColumns() ) {
					$sql .= ",\n  " . $this->buildIndexSql( $index );
				}
			}
		}

		// Note: Foreign keys are often problematic with dbDelta, so we'll skip them
		// They can be added separately after table creation

		$sql .= "\n) ENGINE={$this->engine} DEFAULT CHARSET={$this->charset} COLLATE={$this->collation};";

		return $sql;
	}

	/**
	 * Checks if any of the columns in the current collection is a primary key.
	 *
	 * @return bool Returns true if there is at least one primary key in the columns collection, otherwise false.
	 */
	private function hasPrimaryKeyInColumns(): bool {
		foreach ( $this->columns as $column ) {
			if ( $column->isPrimary() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds the SQL string for defining an index.
	 *
	 * @param array $index An associative array representing the index definition.
	 *                      Must include the keys 'columns' (array of column names) and 'type' (index type: 'primary', 'unique', 'fulltext', or other).
	 *                      For non-primary indexes, the 'name' key representing the index name is also required.
	 *
	 * @return string The SQL string for the specified index definition.
	 */
	private function buildIndexSql( array $index ): string {
		$columns = implode( ', ', $index['columns'] );

		switch ( $index['type'] ) {
			case 'primary':
				return "PRIMARY KEY ({$columns})";
			case 'unique':
				return "UNIQUE KEY {$index['name']} ({$columns})";
			case 'fulltext':
				return "FULLTEXT KEY {$index['name']} ({$columns})";
			default:
				return "KEY {$index['name']} ({$columns})";
		}
	}

	/**
	 * Retrieves the name of the table associated with this blueprint.
	 *
	 * @return string The name of the table.
	 */
	public function getTable(): string {
		return $this->table;
	}

	/**
	 * Retrieves the list of columns.
	 *
	 * @return array The array containing the column definitions.
	 */
	public function getColumns(): array {
		return $this->columns;
	}

	/**
	 * Retrieves the list of foreign keys defined in the current instance.
	 *
	 * @return array An array containing the foreign keys.
	 */
	public function getForeignKeys(): array {
		return $this->foreignKeys;
	}
}
