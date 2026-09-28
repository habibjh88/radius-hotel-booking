<?php
/**
 * Inverse one-to-one / one-to-many relationship.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\ORM\Relations
 */

namespace RadiusTheme\RadiusHotelBooking\Core\ORM\Relations;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/ORM/Relations/BelongsTo.php
 * Implements belongsTo relationship
 */
class BelongsTo extends Relation {
	/**
	 * Retrieves the associated related model based on the defined local key and foreign key relationship.
	 *
	 * @return BaseModel|null Returns the first instance of the related model if found, or null if no match exists.
	 */
	public function get() {
		$relatedModel = $this->getRelatedModel();

		return $relatedModel->where( $this->localKey, $this->parent->{$this->foreignKey} )->first();
	}
}
