<?php
/**
 * Room type rate model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * One cell row of the Price & Rates grid: a room type selling a rate plan.
 * Money columns are not cast: a scalar cast would read a NULL sale as 0.
 *
 * @property int         $id
 * @property int         $room_type_id
 * @property int         $rate_plan_id
 * @property string      $price
 * @property string|null $sale_price
 * @property int         $min_units
 * @property int|null    $max_units
 * @property bool        $enabled
 * @property int         $sort_order
 */
class RoomTypeRate extends BaseModel {

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_room_type_rates';

	/**
	 * Mass-assignable columns.
	 *
	 * @var array
	 */
	protected array $fillable = array(
		'room_type_id',
		'rate_plan_id',
		'price',
		'sale_price',
		'min_units',
		'max_units',
		'enabled',
		'sort_order',
	);

	/**
	 * Casts (NULL-able columns left out on purpose).
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'           => 'int',
		'room_type_id' => 'int',
		'rate_plan_id' => 'int',
		'min_units'    => 'int',
		'enabled'      => 'bool',
		'sort_order'   => 'int',
	);
}
