<?php
/**
 * Item model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

/**
 * Class Item
 *
 * BOILERPLATE: the example model. Shows `fillable`, `casts`, validation rules
 * and soft deletes. Relationships are declared with hasMany()/hasOne()/belongsTo().
 *
 * @property int    $id
 * @property string $title
 * @property string $description
 * @property string $status
 * @property float  $price
 * @property int    $position
 * @property array  $meta
 */
class Item extends BaseModel {

	/**
	 * The database table associated with the model.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_items';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected array $fillable = array(
		'title',
		'description',
		'status',
		'price',
		'position',
		'meta',
	);

	/**
	 * The validation rules applied on save().
	 *
	 * @var array
	 */
	protected array $rules = array(
		'title' => 'required|string|max:255',
	);

	/**
	 * The attributes that should be cast to native types.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'price'    => 'float',
		'position' => 'int',
		'meta'     => 'json',
	);
}
