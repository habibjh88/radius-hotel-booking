<?php
/**
 * Amenity section model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * A section of the amenity library (table `amenity_sections`).
 *
 * @property int    $id
 * @property string $name
 * @property int    $sort_order
 */
class AmenitySection extends BaseModel {

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_amenity_sections';

	/**
	 * Mass-assignable columns.
	 *
	 * @var string[]
	 */
	protected array $fillable = array( 'name', 'sort_order' );

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'         => 'int',
		'sort_order' => 'int',
	);
}
