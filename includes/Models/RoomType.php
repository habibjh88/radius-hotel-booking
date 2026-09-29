<?php
/**
 * Room type model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * A room type (table `room_types`): what the hotel sells. It owns its rooms.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string      $description
 * @property string      $short_description
 * @property int[]       $gallery           Attachment ids.
 * @property int         $featured_image_id
 * @property string[]    $amenities
 * @property string      $bed_info
 * @property float|null  $size_m2
 * @property int         $max_adults
 * @property int         $max_children
 * @property int|null    $buffer_minutes    Null = the Booking rules setting.
 * @property bool        $is_active
 * @property int         $sort_order
 */
class RoomType extends BaseModel {

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_room_types';

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
		'name',
		'slug',
		'description',
		'short_description',
		'gallery',
		'featured_image_id',
		'amenities',
		'bed_info',
		'size_m2',
		'max_adults',
		'max_children',
		'buffer_minutes',
		'is_active',
		'sort_order',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'                => 'int',
		'gallery'           => 'json',
		'featured_image_id' => 'int',
		'amenities'         => 'json',
		// size_m2 and buffer_minutes are not cast: a scalar cast reads NULL as 0.
		'max_adults'        => 'int',
		'max_children'      => 'int',
		'is_active'         => 'bool',
		'sort_order'        => 'int',
	);
}
