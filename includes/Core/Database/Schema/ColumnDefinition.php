<?php
/**
 * Represents the definition of a database column with various attributes such as name, type, and options.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Represents the definition of a database column with various attributes such as name, type, and options.
 */
class ColumnDefinition {
	/**
	 * The name of the column.
	 *
	 * @var string
	 */
	private string $name;
	/**
	 * The type of the column, such as 'varchar', 'int', 'decimal', etc.
	 *
	 * @var string
	 */
	private string $type;
	/**
	 * An array of options to configure the column, such as 'nullable', 'default', 'unsigned', etc.
	 *
	 * @var array
	 */
	private array $options;

	/**
	 * Constructor method to initialize the object with name, type, and options.
	 *
	 * @param string $name The name to be assigned.
	 * @param string $type The type to be assigned.
	 * @param array $options An optional array of options to configure the object.
	 *
	 * @return void
	 */
	public function __construct( string $name, string $type, array $options = array() ) {
		$this->name    = $name;
		$this->type    = $type;
		$this->options = $options;
	}

	/**
	 * Sets the nullable option for the current instance.
	 *
	 * @param bool $value Determines whether the nullable option should be enabled (default is true).
	 *
	 * @return self Returns the current instance with the updated nullable option.
	 */
	public function nullable( bool $value = true ): self {
		$this->options['nullable'] = $value;

		return $this;
	}

	/**
	 * Sets the default value for the current instance.
	 *
	 * @param mixed $value The default value to set.
	 *
	 * @return self Returns the current instance.
	 */
	public function default( $value ): self {
		$this->options['default'] = $value;

		return $this;
	}

	/**
	 * Sets the 'unsigned' option to true for the current instance.
	 *
	 * @return self Returns the current instance with the 'unsigned' option applied.
	 */
	public function unsigned(): self {
		$this->options['unsigned'] = true;

		return $this;
	}

	/**
	 * Enables the auto-increment option for the current instance.
	 *
	 * @return self The current instance with the auto-increment option enabled.
	 */
	public function autoIncrement(): self {
		$this->options['auto_increment'] = true;

		return $this;
	}

	/**
	 * Sets the column as a primary key.
	 *
	 * @return self
	 */
	public function primary(): self {
		$this->options['primary'] = true;

		return $this;
	}

	/**
	 * Sets the 'unique' option to true, indicating that the resultant configuration should enforce unique values.
	 *
	 * @return self Returns the instance of the current object for method chaining.
	 */
	public function unique(): self {
		$this->options['unique'] = true;

		return $this;
	}

	/**
	 * Enables the 'index' option for the current column definition.
	 *
	 * @return self
	 */
	public function index(): self {
		$this->options['index'] = true;

		return $this;
	}

	/**
	 * Sets a comment option.
	 *
	 * @param string $comment The comment to set.
	 *
	 * @return self
	 */
	public function comment( string $comment ): self {
		$this->options['comment'] = $comment;

		return $this;
	}

	/**
	 * Specifies the column after which the current column should be placed.
	 *
	 * @param string $column The name of the column to place the current column after.
	 *
	 * @return self
	 */
	public function after( string $column ): self {
		$this->options['after'] = $column;

		return $this;
	}

	/**
	 * Enables the "first" option and returns the instance.
	 *
	 * @return self Returns the current instance with the "first" option enabled.
	 */
	public function first(): self {
		$this->options['first'] = true;

		return $this;
	}

	/**
	 * Checks if the column is set as a primary key.
	 *
	 * @return bool Returns true if the 'primary' option is set and evaluates to true, otherwise false.
	 */
	public function isPrimary(): bool {
		return isset( $this->options['primary'] ) && $this->options['primary'];
	}

	/**
	 * Builds and returns the SQL string representation of the column definition.
	 *
	 * @return string The SQL string representing the column, including its name, type,
	 *                and any additional attributes such as `UNSIGNED`, `NULL`, `NOT NULL`,
	 *                `AUTO_INCREMENT`, `DEFAULT`, and `COMMENT`.
	 */
	public function toSql(): string {
		$sql = "`{$this->name}` " . $this->buildType();

		// Add unsigned
		if ( isset( $this->options['unsigned'] ) && $this->options['unsigned'] ) {
			$sql .= ' UNSIGNED';
		}

		// Add nullable
		if ( isset( $this->options['nullable'] ) && $this->options['nullable'] ) {
			$sql .= ' NULL';
		} else {
			$sql .= ' NOT NULL';
		}

		// Add auto increment
		if ( isset( $this->options['auto_increment'] ) && $this->options['auto_increment'] ) {
			$sql .= ' AUTO_INCREMENT';
		}

		// Add default
		if ( isset( $this->options['default'] ) ) {
			$default = $this->options['default'];
			if ( is_string( $default ) && ! in_array( //phpcs:ignore
				strtoupper( $default ),
				array(
					'CURRENT_TIMESTAMP',
					'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
				)
			) ) {
				$default = "'" . esc_sql( $default ) . "'";
			}
			$sql .= ' DEFAULT ' . $default;
		}

		// Add comment
		if ( isset( $this->options['comment'] ) ) {
			$sql .= " COMMENT '" . esc_sql( $this->options['comment'] ) . "'";
		}

		return $sql;
	}

	/**
	 * Builds and returns the formatted type definition string based on the options and type provided.
	 *
	 * @return string The SQL-compatible type definition string (e.g., "VARCHAR(255)", "INT(11)", "DECIMAL(8,2)", "ENUM('value1','value2')").
	 */
	private function buildType(): string {
		switch ( $this->type ) {
			case 'varchar':
			case 'char':
				$length = $this->options['length'] ?? 255;

				return strtoupper( $this->type ) . "({$length})";

			case 'int':
			case 'tinyint':
			case 'smallint':
			case 'mediumint':
			case 'bigint':
				$length = $this->options['length'] ?? $this->getDefaultLength( $this->type );

				return strtoupper( $this->type ) . "({$length})";

			case 'decimal':
			case 'float':
				$precision = $this->options['precision'] ?? 8;
				$scale     = $this->options['scale'] ?? 2;

				return strtoupper( $this->type ) . "({$precision},{$scale})";

			case 'enum':
				$values     = $this->options['values'] ?? array();
				$valuesList = "'" . implode( "','", array_map( 'esc_sql', $values ) ) . "'";

				return "ENUM({$valuesList})";

			default:
				return strtoupper( $this->type );
		}
	}

	/**
	 * Retrieves the default length for a given data type.
	 *
	 * @param string $type The data type for which the default length is to be determined.
	 *
	 * @return int The default length associated with the provided data type.
	 */
	private function getDefaultLength( string $type ): int {
		return match ( $type ) {
			'tinyint' => 4,
			'smallint' => 6,
			'mediumint' => 9,
			'int' => 11,
			'bigint' => 20,
			default => 11
		};
	}

	/**
	 * Retrieves the name of the current instance.
	 *
	 * @return string Returns the name property of the object.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Retrieves the type of the current instance.
	 *
	 * @return string The type associated with the current instance.
	 */
	public function getType(): string {
		return $this->type;
	}
}
