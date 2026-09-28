<?php
/**
 * One-to-one relationship.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\ORM\Relations
 */

namespace RadiusTheme\RadiusHotelBooking\Core\ORM\Relations;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/ORM/Relations/HasOne.php
 * Implements hasOne relationship
 */
class HasOne extends Relation {
	/**
	 * Retrieves the related model based on the foreign key and local key relationship.
	 *
	 * @return BaseModel|null Returns an instance of the related model if found, or null if no matching record exists.
	 */
	public function get() {
		$relatedModel = $this->getRelatedModel();

		return $relatedModel->where( $this->foreignKey, $this->parent->{$this->localKey} )->first();
	}
}
