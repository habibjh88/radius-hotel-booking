<?php
/**
 * Booking line model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * A booking line (table `booking_rooms`): one physical room × one window,
 * with the price frozen at creation and snapshots of the names shown.
 *
 * @property int         $id
 * @property int         $booking_id
 * @property int         $room_id
 * @property int         $room_type_id
 * @property int         $rate_plan_id
 * @property string      $rate_plan_name
 * @property string      $room_number
 * @property string      $floor_name
 * @property string      $start_at
 * @property string      $end_at
 * @property string      $start_at_gmt
 * @property string      $end_at_gmt
 * @property string      $occupied_until_gmt
 * @property int         $units
 * @property int         $adults
 * @property int         $children
 * @property string      $status
 * @property float       $unit_price
 * @property float       $total
 * @property string|null $price_breakdown
 */
class BookingRoom extends BaseModel {

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_booking_rooms';

	/**
	 * Mass-assignable columns.
	 *
	 * @var string[]
	 */
	protected array $fillable = array(
		'booking_id',
		'room_id',
		'room_type_id',
		'rate_plan_id',
		'rate_plan_name',
		'room_number',
		'floor_name',
		'start_at',
		'end_at',
		'start_at_gmt',
		'end_at_gmt',
		'occupied_until_gmt',
		'units',
		'adults',
		'children',
		'status',
		'unit_price',
		'total',
		'price_breakdown',
		'checked_in_at',
		'checked_out_at',
		'checked_in_by',
		'checked_out_by',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'           => 'int',
		'booking_id'   => 'int',
		'room_id'      => 'int',
		'room_type_id' => 'int',
		'rate_plan_id' => 'int',
		'units'        => 'int',
		'adults'       => 'int',
		'children'     => 'int',
		'unit_price'   => 'float',
		'total'        => 'float',
	);
}
