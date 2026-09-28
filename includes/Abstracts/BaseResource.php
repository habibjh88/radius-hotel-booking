<?php
/**
 * Base API resource: transforms a model into its API representation.
 *
 * @package RadiusTheme\RadiusHotelBooking\Abstracts
 */

namespace RadiusTheme\RadiusHotelBooking\Abstracts;

use RadiusTheme\RadiusHotelBooking\Core\Api\Resources\ResourceInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Core/Api/Resources/BaseResource.php
 * Base resource transformer
 */
abstract class BaseResource implements ResourceInterface {

	/**
	 * Transforms a single item into a resource representation.
	 *
	 * @param array $items The item to transform.
	 * @return array The transformed resource.
	 */
	public static function collection( array $items ): array {
		$resource = new static();

		return array_map( array( $resource, 'transform' ), $items );
	}

	/**
	 * Transforms a single item into a resource representation.
	 *
	 * @param bool $condition the condition to check.
	 * @param string $value the value to return if the condition is true.
	 *
	 * @return mixed
	 * Returns the value if the condition is true, otherwise returns null.
	 */
	public function when( bool $condition, $value ): mixed {
		return $condition ? $value : null;
	}

	/**
	 * Retrieves a relationship from the model if it is loaded.
	 *
	 * @param string $relationship The name of the relationship to retrieve.
	 * @param mixed $model The model instance containing the relationship.
	 * @return mixed The relationship data if loaded, null otherwise.
	 */
	public function whenLoaded( string $relationship, $model ): mixed {
		if ( method_exists( $model, 'relationIsLoaded' ) && $model->relationIsLoaded( $relationship ) ) {
			return $model->getRelation( $relationship );
		}
		return null;
	}
}
