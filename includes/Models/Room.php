<?php
/**
 * Room model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * A physical room (table `rooms`).
 *
 * @property int    $id
 * @property int    $room_type_id The one type that owns it.
 * @property int    $floor_id
 * @property string $number       Unique across the property.
 * @property string $number_sort  NaturalSort::key( $number ).
 * @property string $state        available | maintenance | out_of_service.
 * @property string $state_note
 * @property int    $sort_order
 */
class Room extends BaseModel {

	const AVAILABLE      = 'available';
	const MAINTENANCE    = 'maintenance';
	const OUT_OF_SERVICE = 'out_of_service';

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_rooms';

	/**
	 * Soft deletes.
	 *
	 * @var bool
	 */
	protected bool $softDeletes = true;

	/**
	 * Mass-assignable columns.
	 *
	 * @var string[]
	 */
	protected array $fillable = array(
		'room_type_id',
		'floor_id',
		'number',
		'number_sort',
		'state',
		'state_note',
		'sort_order',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'           => 'int',
		'room_type_id' => 'int',
		'floor_id'     => 'int',
		'sort_order'   => 'int',
	);

	/**
	 * The states.
	 *
	 * @return string[]
	 */
	public static function states(): array {
		return array( self::AVAILABLE, self::MAINTENANCE, self::OUT_OF_SERVICE );
	}
}
