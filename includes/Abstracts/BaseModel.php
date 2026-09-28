<?php
/**
 * Base model: attributes, casts, relationships, soft deletes and lifecycle events.
 *
 * @package RadiusTheme\RadiusHotelBooking\Abstracts
 */

namespace RadiusTheme\RadiusHotelBooking\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use DateTime;
use RadiusTheme\RadiusHotelBooking\Core\ORM\QueryBuilder;
use RadiusTheme\RadiusHotelBooking\Core\Events\EventDispatcher;
use RadiusTheme\RadiusHotelBooking\Core\ORM\Relations\BelongsTo;
use RadiusTheme\RadiusHotelBooking\Core\ORM\Relations\HasMany;
use RadiusTheme\RadiusHotelBooking\Core\ORM\Relations\HasOne;
use RadiusTheme\RadiusHotelBooking\Core\ORM\Relations\Relation;
use RadiusTheme\RadiusHotelBooking\Core\Validation\Validator;

/**
 * Core/Abstracts/BaseModel.php
 * Base class for all models in the ORM system.
 * This class provides basic functionality for
 * CRUD operations, relationships,
 * validation, and event handling.
 */
abstract class BaseModel {

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected string $table;
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected string $primaryKey = 'id';
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected array $fillable = array();
	/**
	 * The attributes that are not mass assignable.
	 *
	 * @var array|string[]
	 */
	protected array $guarded = array( 'id' );
	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected array $casts = array();
	/**
	 * The attributes of the model.
	 *
	 * @var array
	 */
	protected array $attributes = array();
	/**
	 * The original attributes of the model before any changes.
	 *
	 * @var array
	 */
	protected array $original = array();
	/**
	 * Whether the model exists in the database.
	 *
	 * @var bool
	 */
	protected bool $exists = false;
	/**
	 * Whether the model uses soft deletes.
	 *
	 * @var bool
	 */
	protected bool $softDeletes = false;
	/**
	 * The validation rules for the model.
	 *
	 * @var array
	 */
	protected array $rules = array();
	/**
	 * The relations defined for the model.
	 *
	 * @var array
	 */
	protected array $relations = array();

	// Event hooks
	/**
	 * The events that the model can trigger.
	 *
	 * @var array|string[]
	 */
	protected static array $events = array(
		'creating',
		'created',
		'updating',
		'updated',
		'saving',
		'saved',
		'deleting',
		'deleted',
	);


	/**
	 * Constructor for the BaseModel class.
	 *
	 * @param array $attributes Initial attributes to fill the model with.
	 */
	public function __construct( array $attributes = array() ) {
		$this->fill( $attributes );
	}

	/**
	 * Creates a new QueryBuilder instance for the model's table.
	 *
	 * @return QueryBuilder
	 */
	public static function query(): QueryBuilder {
		$model   = new static();
		$table   = $model->getTable();
		$builder = new QueryBuilder( $table );

		// Apply soft delete scope if enabled — qualify with table name to avoid
		// ambiguity when LEFT JOINing other tables that also have deleted_at.
		if ( $model->softDeletes ) {
			$prefix = \RadiusTheme\RadiusHotelBooking\Core\Database\Connection::getInstance()->prefix();
			$builder->whereNull( $prefix . $table . '.deleted_at' );
		}

		return $builder;
	}

	/**
	 * Retrieves all records from the model's table.
	 *
	 * @return array
	 */
	public static function all(): array {
		return static::query()->get();
	}

	/**
	 * Finds a record by its primary key.
	 *
	 * @param int $id The primary key value to search for.
	 *
	 * @return static|null
	 */
	public static function find( int $id ): ?static {
		$result = static::query()->find( $id );
		return $result ? static::hydrate( $result ) : null;
	}

	/**
	 * Finds a record by its primary key or returns a new instance if not found.
	 *
	 * @param int $id The primary key value to search for.
	 *
	 * @return static
	 */
	public static function findOrNew( int $id ): static {
		$result = static::query()->find( $id );

		return $result ? static::hydrate( $result ) : new static();
	}

	/**
	 * Creates a new QueryBuilder instance with a where clause.
	 *
	 * @param string $column The column to filter by.
	 * @param string $operator The operator to use for the comparison (default is '=').
	 * @param  string $value The value to compare against (default is null).
	 *
	 * @return QueryBuilder
	 */
	public static function where( string $column, string $operator = '=', $value = null ): QueryBuilder {
		return static::query()->where( $column, $operator, $value );
	}

	/**
	 * Creates a new instance of the model and saves it to the database.
	 *
	 * @param array $attributes The attributes to set on the model before saving.
	 *
	 * @return static
	 */
	public static function create( array $attributes ): static {
		$model = new static( $attributes );
		$model->save();
		return $model;
	}

	/**
	 * Fills the model with attributes from an array.
	 *
	 * @param array $attributes The attributes to fill the model with.
	 *
	 * @return $this
	 */
	public function fill( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			if ( $this->isFillable( $key ) ) {
				$this->setAttribute( $key, $value );
			}
		}
		return $this;
	}

	/**
	 * Saves the model to the database.
	 *
	 * @return bool
	 */
	public function save() {

		// Validate if rules are defined
		if ( ! empty( $this->rules ) && ! $this->validate() ) {
			return false;
		}
		if ( $this->exists ) {
			return $this->performUpdate();
		} else {
			return $this->performInsert();
		}
	}

	/**
	 * Updates the model with new attributes and saves it to the database.
	 *
	 * @param array $attributes The attributes to update the model with.
	 *
	 * @return bool
	 */
	public function update( array $attributes ): bool {
		$this->fill( $attributes );
		return $this->save();
	}

	/**
	 * Deletes the model from the database.
	 *
	 * @return bool
	 */
	public function delete(): bool {
		if ( ! $this->exists ) {
			return false;
		}

		$this->fireEvent( 'deleting' );

		if ( $this->softDeletes ) {
			$this->setAttribute( 'deleted_at', current_time( 'mysql' ) );
			$result = $this->performUpdate();
		} else {
			$result = static::query()->where( $this->primaryKey, $this->getKey() )->delete() > 0;
		}

		if ( $result ) {
			$this->fireEvent( 'deleted' );
		}

		return $result;
	}

	/**
	 * Restores a soft-deleted model.
	 *
	 * @return bool
	 */
	public function restore(): bool {
		if ( ! $this->softDeletes ) {
			return false;
		}

		$this->setAttribute( 'deleted_at', null );
		return $this->save();
	}

	// Relationship methods

	/**
	 * Creates a one-to-one relationship.
	 *
	 * @param string $related The related model class name.
	 * @param string|null $foreignKey The foreign key on the related model (default is the related model's foreign key).
	 * @param string|null $localKey The local key on the current model (default is the current model's primary key).
	 *
	 * @return HasOne
	 */
	public function hasOne( string $related, ?string $foreignKey = null, ?string $localKey = null ): HasOne {
		$foreignKey = $foreignKey ?: $this->getForeignKey(); //phpcs:ignore
		$localKey   = $localKey ?: $this->getKeyName(); //phpcs:ignore

		return new HasOne( $this, $related, $foreignKey, $localKey );
	}

	/**
	 * Creates a one-to-many relationship.
	 *
	 * @param string $related The related model class name.
	 * @param string|null $foreignKey The foreign key on the related model (default is the related model's foreign key).
	 * @param string|null $localKey The local key on the current model (default is the current model's primary key).
	 *
	 * @return HasMany
	 */
	public function hasMany( string $related, ?string $foreignKey = null, ?string $localKey = null ): HasMany {
		$foreignKey = $foreignKey ?: $this->getForeignKey(); //phpcs:ignore
		$localKey   = $localKey ?: $this->getKeyName(); //phpcs:ignore

		return new HasMany( $this, $related, $foreignKey, $localKey );
	}

	/**
	 * Creates a belongs-to relationship.
	 *
	 * @param string $related The related model class name.
	 * @param string|null $foreignKey The foreign key on the related model (default is the related model's foreign key).
	 * @param string|null $localKey The local key on the current model (default is 'id').
	 *
	 * @return BelongsTo
	 */
	public function belongsTo( string $related, ?string $foreignKey = null, ?string $localKey = null ): BelongsTo {
		$foreignKey = $foreignKey ?: $this->getRelatedForeignKey( $related ); //phpcs:ignore
		$localKey   = $localKey ?: 'id'; //phpcs:ignore

		return new BelongsTo( $this, $related, $foreignKey, $localKey );
	}

	// Magic method to handle relationship calls

	/**
	 * Magic method to dynamically access relationships or attributes.
	 *
	 * @param string $key The attribute or relationship key to access.
	 *
	 * @return bool|DateTime|float|int|mixed|string|null
	 */
	public function __get( string $key ) {
		if ( method_exists( $this, $key ) ) {
			$relation = $this->$key();
			if ( $relation instanceof Relation ) {
				$this->relations[ $key ] = $relation->get();
				return $this->relations[ $key ];
			}
		}
		return $this->getAttribute( $key );
	}

	/**
	 * Eager-load relationships into the `relations` array.
	 *
	 * @param string ...$relations List of relation names to load.
	 * @return void|static|object
	 */
	public function load( string ...$relations ): static {
		foreach ( $relations as $relation ) {
			if ( method_exists( $this, $relation ) ) {
				$relationObject = $this->$relation();

				// If it's HasOne or BelongsTo → get one
				if ( method_exists( $relationObject, 'get' ) ) {
					$this->relations[ $relation ] = $relationObject->get();
				}
			}
		}
		return $this;
	}

	/**
	 * Check if a relation is loaded
	 *
	 * @param string $name Relation name
	 * @return bool
	 */
	public function relationIsLoaded( string $name ): bool {
		return isset( $this->relations[ $name ] );
	}

	/**
	 * Get the relation data if loaded
	 *
	 * @param string $name Relation name
	 * @return mixed|null
	 */
	public function getRelation( string $name ): mixed {
		return $this->relations[ $name ] ?? null;
	}

	/**
	 * Magic method to set attributes dynamically.
	 *
	 * @param string $key The attribute key to set.
	 * @param string $value The value to set for the attribute.
	 *
	 * @return void
	 */
	public function __set( string $key, $value ): void {
		$this->setAttribute( $key, $value );
	}

	/**
	 * Magic method to check if an attribute is set.
	 *
	 * @param string $key The attribute key to check.
	 *
	 * @return bool
	 */
	public function __isset( string $key ): bool {
		return isset( $this->attributes[ $key ] );
	}

	/**
	 * Performs the insert operation for a new model instance.
	 *
	 * @return int
	 */
	protected function performInsert(): int {
		$this->fireEvent( 'creating' );
		$this->fireEvent( 'saving' );

		// Add timestamps
		if ( $this->hasTimestamps() ) {
			$this->setAttribute( 'created_at', current_time( 'mysql' ) );
			$this->setAttribute( 'updated_at', current_time( 'mysql' ) );
		}

		$id = static::query()->insert( $this->getAttributes() );

		if ( $id ) {
			$this->setAttribute( $this->primaryKey, $id );
			$this->exists = true;
			$this->syncOriginal();

			$this->fireEvent( 'created' );
			$this->fireEvent( 'saved' );
			return $id;
		}

		return false;
	}

	/**
	 * Performs the update operation for an existing model instance.
	 *
	 * @return bool
	 */
	protected function performUpdate(): bool {
		$this->fireEvent( 'updating' );
		$this->fireEvent( 'saving' );

		// Add updated timestamp
		if ( $this->hasTimestamps() ) {
			$this->setAttribute( 'updated_at', current_time( 'mysql' ) );
		}

		$dirty = $this->getDirty();
		if ( empty( $dirty ) ) {
			return true;
		}

		$result = static::query()
						->where( $this->primaryKey, $this->getKey() )
						->update( $dirty ) > 0;
		if ( $result ) {
			$this->syncOriginal();
			$this->fireEvent( 'updated' );
			$this->fireEvent( 'saved' );
		}
		return $result;
	}

	/**
	 * Validates the model's attributes against the defined rules.
	 *
	 * @return bool
	 */
	protected function validate(): bool {
		$validator = new Validator( $this->rules );
		return $validator->validate( $this->getAttributes() );
	}

	/**
	 * Fires an event for the model.
	 *
	 * @param string $event The event name to fire (e.g., 'creating', 'created', etc.).
	 *
	 * @return void
	 */
	protected function fireEvent( string $event ): void {
		$eventName = static::class . '.' . $event;
		EventDispatcher::dispatch( $eventName, $this );

		// Call model-specific event method if it exists
		$method = 'on' . ucfirst( $event );
		if ( method_exists( $this, $method ) ) {
			$this->$method();
		}
	}

	/**
	 * Checks if a given key is fillable.
	 *
	 * @param string $key The attribute key to check.
	 *
	 * @return bool
	 */
	protected function isFillable( string $key ): bool {
		if ( in_array( $key, $this->guarded ) ) { //phpcs:ignore
			return false;
		}

		return empty( $this->fillable ) || in_array( $key, $this->fillable ); //phpcs:ignore
	}

	/**
	 * Sets an attribute on the model.
	 *
	 * @param string $key The attribute key to set.
	 * @param string $value The value to set for the attribute.
	 *
	 * @return void
	 */
	protected function setAttribute( string $key, $value ): void {
		if ( isset( $this->casts[ $key ] ) ) {
			$cast = $this->casts[ $key ];
			if ( 'json' === $cast && ( is_array( $value ) || is_object( $value ) ) ) {
				$value = maybe_serialize( $value );
			} elseif ( in_array( $cast, array( 'bool', 'boolean' ), true ) ) {
				$value = $value ? 1 : 0;
			}
		}
		$this->attributes[ $key ] = $value;
	}

	/**
	 * Gets an attribute from the model.
	 *
	 * @param string $key The attribute key to retrieve.
	 *
	 * @return bool|DateTime|float|int|mixed|string|null
	 */
	protected function getAttribute( string $key ) {
		$value = $this->attributes[ $key ] ?? null;

		// Apply casts
		if ( isset( $this->casts[ $key ] ) ) {
			return $this->castAttribute( $key, $value );
		}

		return $value;
	}

	/**
	 * Casts an attribute to its native type based on the defined casts.
	 *
	 * @param string $key The attribute key.
	 * @param mixed $value The value to cast.
	 * @return bool|DateTime|float|int|mixed|string|null
	 * @throws \Exception If the cast type is not recognized.
	 */
	protected function castAttribute( string $key, $value ) {
		$cast = $this->casts[ $key ];

		switch ( $cast ) {
			case 'int':
			case 'integer':
				return (int) $value;
			case 'real':
			case 'float':
			case 'double':
				return (float) $value;
			case 'string':
				return (string) $value;
			case 'bool':
			case 'boolean':
				return (bool) $value;
			case 'array':
				return (array) $value;
			case 'json':
				if ( is_string( $value ) ) {
					if ( is_serialized( $value ) ) {
						return maybe_unserialize( $value );
					}
					return json_decode( $value, true );
				}
				return is_array( $value ) || is_object( $value ) ? (array) $value : null;
			case 'datetime':
			case 'date':
				return new DateTime( $value );
			default:
				return $value;
		}
	}

	/**
	 * Gets all attributes of the model.
	 *
	 * @return array
	 */
	protected function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Gets the dirty attributes that have changed since the last sync.
	 *
	 * @return array
	 */
	protected function getDirty(): array {
		$dirty = array();

		foreach ( $this->attributes as $key => $value ) {
			if ( ! array_key_exists( $key, $this->original ) || $this->original[ $key ] !== $value ) {
				$dirty[ $key ] = $value;
			}
		}

		return $dirty;
	}

	/**
	 * Syncs the original attributes with the current attributes.
	 *
	 * @return void
	 */
	protected function syncOriginal(): void {
		$this->original = $this->attributes;
	}

	/**
	 * Gets the table name for the model.
	 *
	 * @return string
	 */
	protected function getTable(): string {
		return $this->table ?? strtolower( rtbp_class_basename( static::class ) ) . 's';
	}

	/**
	 * Gets the primary key name for the model.
	 *
	 * @return string
	 */
	protected function getKeyName(): string {
		return $this->primaryKey;
	}

	/**
	 * Gets the value of the primary key for the model.
	 *
	 * @return bool|DateTime|float|int|mixed|string|null
	 */
	protected function getKey() {
		return $this->getAttribute( $this->getKeyName() );
	}

	/**
	 * Gets the foreign key name for the model.
	 *
	 * @return string
	 */
	protected function getForeignKey(): string {
		return strtolower( rtbp_class_basename( static::class ) ) . '_id';
	}

	/**
	 * Gets the foreign key name for a related model.
	 *
	 * @param string $related The related model class name.
	 *
	 * @return string
	 */
	protected function getRelatedForeignKey( string $related ): string {
		return strtolower( rtbp_class_basename( $related ) ) . '_id';
	}

	/**
	 * Checks if the model has timestamps.
	 *
	 * @return bool
	 */
	protected function hasTimestamps(): bool {
		return true; // Override in models that don't want timestamps
	}

	/**
	 * Hydrates a model instance with data.
	 *
	 * @param array|object $data The data to hydrate the model with.
	 *
	 * @return static
	 */
	public static function hydrate( $data ): static {
		$model = new static();

		if ( is_object( $data ) ) {
			$data = (array) $data;
		}

		$model->attributes = $data;
		$model->syncOriginal();
		$model->exists = true;

		return $model;
	}
	/**
	 * Finds multiple records by their primary keys.
	 *
	 * @param array $ids The array of primary key values.
	 *
	 * @return static[]
	 */
	public static function findMany( array $ids ): array {
		$records = static::query()
						->whereIn( ( new static() )->getKeyName(), $ids )
						->get();

		return array_map( fn( $data ) => static::hydrate( $data ), $records );
	}

	/**
	 * Converts the model to an array including attributes and loaded relations.
	 *
	 * @return array
	 */
	public function toArray(): array {
		$array = array();

		foreach ( $this->attributes as $key => $value ) {
			$array[ $key ] = $this->__get( $key );
		}

		foreach ( $this->relations as $relation => $data ) {
			$array[ $relation ] = $data;
		}

		return $array;
	}

	// Add these methods to your BaseModel class

	/**
	 * Bulk delete records by their primary keys.
	 *
	 * @param array $ids Array of primary key values to delete.
	 * @return int Number of records deleted.
	 */
	public static function deleteMany( array $ids ): int {
		if ( empty( $ids ) ) {
			return 0;
		}

		$model   = new static();
		$keyName = $model->getKeyName();
		// Fire deleting event for each record if needed
		if ( in_array( 'deleting', static::$events, true ) ) {
			$records = static::findMany( $ids );
			foreach ( $records as $record ) {
				$record->fireEvent( 'deleting' );
			}
		}

		$query = static::query()->whereIn( $keyName, $ids );

		if ( $model->softDeletes ) {
			// Soft delete: update deleted_at timestamp
			$deletedCount = $query->update( array( 'deleted_at' => current_time( 'mysql' ) ) );
		} else {
			// Hard delete: actually remove records
			$deletedCount = $query->delete();
		}

		// Fire deleted event for each record if needed
		if ( $deletedCount > 0 && in_array( 'deleted', static::$events, true ) ) {
			if ( isset( $records ) ) {
				foreach ( $records as $record ) {
					$record->fireEvent( 'deleted' );
				}
			}
		}

		return $deletedCount;
	}

	/**
	 * Bulk delete records based on conditions.
	 *
	 * @param array $conditions Array of conditions ['column' => 'value'] or more complex conditions.
	 * @return int Number of records deleted.
	 */
	public static function deleteWhere( array $conditions ): int {
		if ( empty( $conditions ) ) {
			return 0;
		}

		$model = new static();
		$query = static::query();

		// Apply conditions
		foreach ( $conditions as $column => $value ) {
			if ( is_array( $value ) ) {
				// Handle array values with whereIn
				$query->whereIn( $column, $value );
			} else {
				// Handle single values
				$query->where( $column, '=', $value );
			}
		}

		// Get records before deletion for events
		$records = array();
		if ( in_array( 'deleting', static::$events, true ) || in_array( 'deleted', static::$events, true ) ) {
			$records = $query->get();
			$records = array_map( fn( $data ) => static::hydrate( $data ), $records );
		}

		// Fire deleting events
		if ( in_array( 'deleting', static::$events, true ) ) {
			foreach ( $records as $record ) {
				$record->fireEvent( 'deleting' );
			}
		}

		// Reset query for actual deletion
		$query = static::query();
		foreach ( $conditions as $column => $value ) {
			if ( is_array( $value ) ) {
				$query->whereIn( $column, $value );
			} else {
				$query->where( $column, '=', $value );
			}
		}

		if ( $model->softDeletes ) {
			// Soft delete
			$deletedCount = $query->update( array( 'deleted_at' => current_time( 'mysql' ) ) );
		} else {
			// Hard delete
			$deletedCount = $query->delete();
		}

		// Fire deleted events
		if ( $deletedCount > 0 && in_array( 'deleted', static::$events, true ) ) {
			foreach ( $records as $record ) {
				$record->fireEvent( 'deleted' );
			}
		}

		return $deletedCount;
	}

	/**
	 * Bulk delete all records (with optional conditions).
	 * Use with caution!
	 *
	 * @param array $conditions Optional conditions to limit deletion.
	 * @return int Number of records deleted.
	 */
	public static function deleteAll( array $conditions = array() ): int {
		$model = new static();
		$query = static::query();

		// Apply conditions if provided
		if ( ! empty( $conditions ) ) {
			foreach ( $conditions as $column => $value ) {
				if ( is_array( $value ) ) {
					$query->whereIn( $column, $value );
				} else {
					$query->where( $column, '=', $value );
				}
			}
		}

		// Get records before deletion for events
		$records = array();
		if ( in_array( 'deleting', static::$events, true ) || in_array( 'deleted', static::$events, true ) ) {
			$records = $query->get();
			$records = array_map( fn( $data ) => static::hydrate( $data ), $records );
		}

		// Fire deleting events
		if ( in_array( 'deleting', static::$events, true ) ) {
			foreach ( $records as $record ) {
				$record->fireEvent( 'deleting' );
			}
		}

		// Reset query for actual deletion
		$query = static::query();
		if ( ! empty( $conditions ) ) {
			foreach ( $conditions as $column => $value ) {
				if ( is_array( $value ) ) {
					$query->whereIn( $column, $value );
				} else {
					$query->where( $column, '=', $value );
				}
			}
		}

		if ( $model->softDeletes ) {
			// Soft delete
			$deletedCount = $query->update( array( 'deleted_at' => current_time( 'mysql' ) ) );
		} else {
			// Hard delete
			$deletedCount = $query->delete();
		}

		// Fire deleted events
		if ( $deletedCount > 0 && in_array( 'deleted', static::$events, true ) ) {
			foreach ( $records as $record ) {
				$record->fireEvent( 'deleted' );
			}
		}

		return $deletedCount;
	}

	/**
	 * Bulk force delete records (permanently delete even with soft deletes).
	 *
	 * @param array $ids Array of primary key values to force delete.
	 * @return int Number of records deleted.
	 */
	public static function forceDeleteMany( array $ids ): int {
		if ( empty( $ids ) ) {
			return 0;
		}

		$model   = new static();
		$keyName = $model->getKeyName();

		// Fire deleting event for each record if needed
		if ( in_array( 'deleting', static::$events, true ) ) {
			$records = static::findMany( $ids );
			foreach ( $records as $record ) {
				$record->fireEvent( 'deleting' );
			}
		}

		// Always hard delete regardless of soft delete setting
		$deletedCount = static::query()
								->whereIn( $keyName, $ids )
								->delete();

		// Fire deleted event for each record if needed
		if ( $deletedCount > 0 && in_array( 'deleted', static::$events, true ) ) {
			if ( isset( $records ) ) {
				foreach ( $records as $record ) {
					$record->fireEvent( 'deleted' );
				}
			}
		}

		return $deletedCount;
	}

	/**
	 * Bulk restore soft deleted records.
	 *
	 * @param array $ids Array of primary key values to restore.
	 * @return int Number of records restored.
	 */
	public static function restoreMany( array $ids ): int {
		if ( empty( $ids ) ) {
			return 0;
		}

		$model = new static();

		if ( ! $model->softDeletes ) {
			return 0;
		}

		$keyName = $model->getKeyName();

		return static::query()
					->whereIn( $keyName, $ids )
					->whereNotNull( 'deleted_at' )
					->update( array( 'deleted_at' => null ) );
	}

	/**
	 * Creates a new QueryBuilder instance with a whereIn clause.
	 *
	 * @param string $column The column to filter by.
	 * @param array $values The array of values to match against.
	 *
	 * @return QueryBuilder
	 */
	public static function whereIn( string $column, array $values ): QueryBuilder {
		return static::query()->whereIn( $column, $values );
	}

	/**
	 * Creates a new QueryBuilder instance with a whereNotIn clause.
	 *
	 * @param string $column The column to filter by.
	 * @param array $values The array of values to exclude.
	 *
	 * @return QueryBuilder
	 */
	public static function whereNotIn( string $column, array $values ): QueryBuilder {
		return static::query()->whereNotIn( $column, $values );
	}

	/**
	 * Creates a new QueryBuilder instance with an orWhereIn clause.
	 *
	 * @param string $column The column to filter by.
	 * @param array $values The array of values to match against.
	 *
	 * @return QueryBuilder
	 */
	public static function orWhereIn( string $column, array $values ): QueryBuilder {
		return static::query()->orWhereIn( $column, $values );
	}

	/**
	 * Creates a new QueryBuilder instance with an orWhereNotIn clause.
	 *
	 * @param string $column The column to filter by.
	 * @param array $values The array of values to exclude.
	 *
	 * @return QueryBuilder
	 */
	public static function orWhereNotIn( string $column, array $values ): QueryBuilder {
		return static::query()->orWhereNotIn( $column, $values );
	}

	/**
	 * Calculates the sum of a column for all records.
	 *
	 * @param string $column The column to sum.
	 *
	 * @return float|int
	 */
	public static function sum( string $column ): float|int {
		return static::query()->sum( $column );
	}
	/**
	 * Creates a new QueryBuilder instance with a whereDate clause.
	 *
	 * @param string $column The column to filter by.
	 * @param string $operator The operator to use for the comparison.
	 * @param string $values The date value to compare against.
	 *
	 * @return QueryBuilder
	 */
	public static function whereDate( $column, $operator, $values ) {
		return static::query()->whereDate( $column, $operator, $values );
	}

	/**
	 * Run a raw query through the model's query builder.
	 *
	 * @param string $query Raw SQL fragment.
	 *
	 * @return mixed
	 */
	public static function raw( $query ) {
		return static::query()->raw( $query );
	}
}
