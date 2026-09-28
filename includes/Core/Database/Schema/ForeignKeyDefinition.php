<?php
/**
 * Represents a database foreign key constraint definition, allowing for the specification
 * of relationships between tables, including the actions on update and delete operations.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Database\Schema;

use Exception;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Represents a database foreign key constraint definition.
 */
class ForeignKeyDefinition {
	/**
	 * An array of columns that are part of the foreign key constraint.
	 *
	 * @var array<string>
	 */
	private array $columns;
	/**
	 * The name of the table that this foreign key references.
	 *
	 * @var string|null
	 */
	private ?string $referencedTable = null;
	/**
	 * An array of columns in the referenced table that this foreign key points to.
	 *
	 * @var array
	 */
	private array $referencedColumns = array();
	/**
	 * The action to take when the referenced row is updated.
	 * This can be 'CASCADE', 'SET NULL', 'RESTRICT', etc.
	 *
	 * @var string|null
	 */
	private ?string $onUpdate = null;
	/**
	 * The action to take when the referenced row is deleted.
	 * This can be 'CASCADE', 'SET NULL', 'RESTRICT', etc.
	 *
	 * @var string|null
	 */
	private ?string $onDelete = null;
	/**
	 * The name of the foreign key constraint.
	 *
	 * @var string|null
	 */
	private ?string $name = null;

	/**
	 * Constructor method to initialize the foreign key definition with the specified columns.
	 *
	 * @param array $columns An array of column definitions or attributes.
	 *
	 * @return void
	 */
	public function __construct( array $columns ) {
		$this->columns = $columns;
	}

	/**
	 * Sets the referenced columns for a database relationship.
	 *
	 * @param string|array $columns The column(s) to reference. Can be a single column as a string or multiple columns as an array.
	 *
	 * @return self
	 */
	public function references( string|array $columns ): self {
		$this->referencedColumns = is_array( $columns ) ? $columns : array( $columns );

		return $this;
	}

	/**
	 * Sets the referenced table for a relation.
	 *
	 * @param string $table The name of the table to be referenced.
	 *
	 * @return self Returns the current instance.
	 */
	public function on( string $table ): self {
		$this->referencedTable = $table;

		return $this;
	}

	/**
	 * Sets the action to be performed during an update operation on the foreign key.
	 *
	 * @param string $action The action to be performed during an update operation.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function onUpdate( string $action ): self {
		$this->onUpdate = strtoupper( $action );

		return $this;
	}

	/**
	 * Sets the delete action for the current instance.
	 *
	 * @param string $action The action to be performed on delete (e.g., CASCADE, SET NULL, RESTRICT).
	 *
	 * @return self Returns the current instance.
	 */
	public function onDelete( string $action ): self {
		$this->onDelete = strtoupper( $action );

		return $this;
	}

	/**
	 * Sets the "ON UPDATE" behavior to "CASCADE", ensuring that when a parent record is updated,
	 *
	 * @return self
	 */
	public function cascadeOnUpdate(): self {
		return $this->onUpdate( 'CASCADE' );
	}

	/**
	 * Sets the "ON DELETE" behavior to "CASCADE", ensuring that when a parent record is deleted,
	 * all associated child records are also deleted automatically.
	 *
	 * @return self
	 */
	public function cascadeOnDelete(): self {
		return $this->onDelete( 'CASCADE' );
	}

	/**
	 * Sets the update action to 'RESTRICT'.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function restrictOnUpdate(): self {
		return $this->onUpdate( 'RESTRICT' );
	}

	/**
	 * Sets the delete action to 'RESTRICT'.
	 *
	 * @return self
	 */
	public function restrictOnDelete(): self {
		return $this->onDelete( 'RESTRICT' );
	}

	/**
	 *
	 * Configures the database behavior to set the value to null upon deletion of a related record.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function nullOnDelete(): self {
		return $this->onDelete( 'SET NULL' );
	}

	/**
	 * Sets the name.
	 *
	 * @param string $name The name to set.
	 *
	 * @return self Returns the current instance.
	 */
	public function name( string $name ): self {
		$this->name = $name;

		return $this;
	}

	/**
	 * Returns the constraint name (explicit or auto-generated).
	 *
	 * @return string|null The constraint name, or null if references are not set.
	 */
	public function getConstraintName(): ?string {
		if ( $this->name ) {
			return $this->name;
		}

		if ( ! $this->referencedTable || empty( $this->referencedColumns ) ) {
			return null;
		}

		return $this->generateName();
	}

	/**
	 * Generates the SQL statement for creating a foreign key constraint.
	 *
	 * @return string The SQL string defining the foreign key constraint.
	 * @throws Exception If the referenced table or columns are not properly defined.
	 */
	public function toSql(): string {
		global $wpdb;

		if ( ! $this->referencedTable || empty( $this->referencedColumns ) ) {
			throw new Exception( 'Foreign key must reference a table and columns' );
		}

		$name              = $this->name ?: $this->generateName(); //phpcs:ignore
		$columns           = '`' . implode( '`, `', $this->columns ) . '`';
		$referencedColumns = '`' . implode( '`, `', $this->referencedColumns ) . '`';
		$referencedTable   = $wpdb->prefix . $this->referencedTable;

		$sql = "CONSTRAINT `{$name}` FOREIGN KEY ({$columns}) REFERENCES `{$referencedTable}` ({$referencedColumns})";

		if ( $this->onUpdate ) {
			$sql .= " ON UPDATE {$this->onUpdate}";
		}

		if ( $this->onDelete ) {
			$sql .= " ON DELETE {$this->onDelete}";
		}

		return $sql;
	}

	/**
	 * Generates a default name for the foreign key constraint based on the columns and referenced table.
	 *
	 * @return string The generated name based on the columns and the referenced table.
	 */
	private function generateName(): string {
		return 'fk_' . implode( '_', $this->columns ) . '_' . $this->referencedTable;
	}
}
