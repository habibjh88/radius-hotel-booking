<?php
/**
 * Base repository: common data-access operations over a model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Abstracts
 */

namespace RadiusTheme\RadiusHotelBooking\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use Exception;

/**
 * Repositories/BaseRepository.php
 * Enhanced base repository with common patterns
 */
abstract class BaseRepository {

	/**
	 * The model class name that this repository will manage.
	 * Must be defined in the extending class.
	 *
	 * @var string
	 */
	protected string $model;

	/**
	 * BaseRepository constructor.
	 *
	 * Initializes the repository with the model class.
	 * Throws an exception if the model property is not defined.
	 *
	 * @throws Exception If the model property is not defined.
	 */
	public function __construct() {
		if ( ! isset( $this->model ) ) {
			throw new Exception( 'Model property must be defined in repository' );
		}
	}

	/**
	 * Retrieves all records from the model.
	 *
	 * @return array An array of all model instances.
	 */
	public function all(): array {
		return $this->model::all();
	}

	/**
	 * Finds a model instance by its ID.
	 *
	 * @param int $id The ID of the model instance to find.
	 *
	 * @return BaseModel|null The model instance if found, null otherwise.
	 */
	public function find( int $id ): ?BaseModel {
		return $this->model::find( $id );
	}

	/**
	 * Finds multiple model instances by their IDs.
	 *
	 * @param array $ids Array of IDs to retrieve.
	 *
	 * @return BaseModel[] Array of model instances.
	 */
	public function findMany( array $ids ): array {
		return $this->model::findMany( $ids );
	}

	/**
	 * Finds a model instance by its ID or throws an exception if not found.
	 *
	 * @param int $id The ID of the model instance to find.
	 *
	 * @return BaseModel The model instance if found.
	 * @throws Exception If the model instance is not found.
	 */
	public function findOrFail( int $id ): BaseModel {
		$model = $this->find( $id );
		if ( ! $model ) {
			throw new Exception( "Model not found with ID: {$id}" ); //phpcs:ignore
		}

		return $model;
	}

	/**
	 * Creates a new model instance with the provided data.
	 *
	 * @param array $data The data to create the model instance with.
	 *
	 * @return BaseModel The newly created model instance.
	 */
	public function create( array $data ): BaseModel {
		return $this->model::create( $data );
	}

	/**
	 * Updates an existing model instance with the provided data.
	 *
	 * @param int $id The ID of the model instance to update.
	 * @param array $data The data to update the model instance with.
	 *
	 * @return bool True if the update was successful, false otherwise.
	 */
	public function update( int $id, array $data ): bool {
		$model = $this->findOrFail( $id );
		return $model->update( $data );
	}

	/**
	 * Deletes a model instance by its ID.
	 *
	 * @param int $id The ID of the model instance to delete.
	 *
	 * @return bool True if the deletion was successful, false otherwise.
	 */
	public function delete( int $id ): bool {
		$model = $this->findOrFail( $id );

		return $model->delete();
	}

	/**
	 * Paginate the model results.
	 *
	 * @param int         $perPage      Number of items per page.
	 * @param int         $page         Current page number.
	 * @param string|null $order        Order direction (ASC or DESC).
	 * @param string|null $orderBy      Column to order by.
	 * @param string|null $searchColumn Column to search in.
	 * @param mixed       $searchData   Data to search for.
	 * @param array|null  $where        Additional where conditions.
	 *
	 * @return array Paginated results.
	 */
	public function paginate( int $perPage = 15, int $page = 1, $order = null, $orderBy = null, $searchColumn = null, $searchData = null, $where = null ) {
		return $this->model::query()->paginate( $perPage, $page, $order, $orderBy, $searchColumn, $searchData, $where );
	}

	/**
	 * Applies a where clause to the model query.
	 *
	 * @param string $column The column to filter by.
	 * @param string $operator The operator for the comparison (default is '=').
	 * @param mixed $value The value to compare against (optional).
	 *
	 * @return \Illuminate\Database\Eloquent\Builder The query builder instance.
	 */
	public function where( string $column, string $operator = '=', $value = null ) {
		return $this->model::where( $column, $operator, $value );
	}

	/**
	 * Applies an orWhere clause to the model query.
	 *
	 * @param string $column The column to filter by.
	 * @param string $operator The operator for the comparison (default is '=').
	 * @param mixed $value The value to compare against (optional).
	 * @return \Illuminate\Database\Eloquent\Builder The query builder instance.
	 */
	public function orWhere( string $column, string $operator = '=', $value = null ) {
		return $this->model::where( $column, $operator, $value );
	}

	/**
	 * Counts the total number of records in the model.
	 *
	 * @return int The total count of records.
	 */
	public function count(): int {
		return $this->model::query()->count();
	}

	/**
	 * Checks if a model instance exists by its ID.
	 *
	 * @param int $id The ID of the model instance to check.
	 *
	 * @return bool True if the model instance exists, false otherwise.
	 */
	public function exists( int $id ): bool {
		return $this->model::query()->where( 'id', $id )->exists();
	}

	/**
	 * Retrieves the model class name.
	 *
	 * @return string The model class name.
	 */
	protected function getModel(): string {
		return $this->model;
	}

	/**
	 * Eager loads relationships on a model or array of models.
	 *
	 * @param BaseModel|BaseModel[] $models Model instance or array of models.
	 * @param string[] $relations List of relationship method names to load.
	 *
	 * @return BaseModel|BaseModel[] The model(s) with loaded relationships.
	 */
	public function loadRelationships( BaseModel|array $models, array $relations ) {
		if ( is_array( $models ) ) {
			foreach ( $models as $model ) {
				if ( $model instanceof BaseModel ) {
					$model->load( ...$relations );
				}
			}

			return $models;
		}

		if ( $models instanceof BaseModel ) {
			$models->load( ...$relations );
		}

		return $models;
	}
}
