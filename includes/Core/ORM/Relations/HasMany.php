<?php
/**
 * One-to-many relationship.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\ORM\Relations
 */

namespace RadiusTheme\RadiusHotelBooking\Core\ORM\Relations;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/ORM/Relations/HasMany.php
 * Implements hasMany relationship
 */
class HasMany extends Relation {
	/**
	 * Retrieves an array of related records from the associated model based on the foreign key and local key values.
	 *
	 * @return array The collection of related records.
	 */
	public function get(): array {
		$relatedModel = $this->getRelatedModel();

		return $relatedModel->where( $this->foreignKey, $this->parent->{$this->localKey} )->get();
	}
}
