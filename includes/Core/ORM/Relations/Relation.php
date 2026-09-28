<?php
/**
 * Base class for model relationships.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\ORM\Relations
 */

namespace RadiusTheme\RadiusHotelBooking\Core\ORM\Relations;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/ORM/Relations/Relation.php
 * Base class for all relationship types
 */
abstract class Relation {
	/**
	 * The parent model instance.
	 * This is the model that owns the relationship.
	 * For example, in a "User has many Posts" relationship,
	 * the User model would be the parent.
	 *
	 * @var BaseModel
	 */
	protected BaseModel $parent;
	/**
	 *  The related model name.
	 * This is the model that is related to the parent model.
	 * For example, in a "User has many Posts" relationship,
	 * the Posts model would be the related model.
	 *
	 * @var string
	 */
	protected string $related;
	/**
	 * The foreign key used in the relationship.
	 * This is the key in the related model that references
	 * the parent model.
	 * For example, in a "User has many Posts" relationship,
	 * the Posts model would have a foreign key
	 * that references the User model's ID.
	 *
	 * @var string
	 */
	protected string $foreignKey;
	/**
	 * The local key used in the relationship.
	 * This is the key in the parent model that is referenced
	 * by the foreign key in the related model.
	 * For example, in a "User has many Posts" relationship,
	 * the User model would have a local key
	 * that is the User's ID,
	 * which is referenced by the foreign key in the Posts model.
	 *
	 * @var string
	 */
	protected string $localKey;

	/**
	 * Constructor method.
	 *
	 * @param BaseModel $parent The parent model instance.
	 * @param string $related The related model name.
	 * @param string $foreignKey The foreign key used in the relationship.
	 * @param string $localKey The local key used in the relationship.
	 *
	 * @return void
	 */
	public function __construct( BaseModel $parent, string $related, string $foreignKey, string $localKey ) { //phpcs:ignore
		$this->parent     = $parent;
		$this->related    = $related;
		$this->foreignKey = $foreignKey;
		$this->localKey   = $localKey;
	}

	/**
	 * Retrieves or processes data in a manner defined by the implementing class.
	 *
	 * This method must be implemented by any subclass extending the parent class.
	 * The specific behavior, including the returned data type and processing logic,
	 * depends on the implementation in the derived class.
	 */
	abstract public function get();

	/**
	 * Retrieves the related model instance.
	 *
	 * @return BaseModel The instance of the related model.
	 */
	protected function getRelatedModel() {
		return new $this->related();
	}
}
