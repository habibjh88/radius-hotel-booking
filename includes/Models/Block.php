<?php
/**
 * Block model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * A dated closure of the property, a floor, a room type or one room
 * (table `blocks`, feature 8.12).
 *
 * @property int         $id
 * @property string      $scope
 * @property int         $scope_id
 * @property string      $start_at
 * @property string      $end_at
 * @property string      $start_at_gmt
 * @property string      $end_at_gmt
 * @property string      $source
 * @property int|null    $feed_id
 * @property string      $external_uid
 * @property string      $reason
 * @property int|null    $created_by
 */
class Block extends BaseModel {

	/**
	 * What a block can close.
	 */
	const SCOPES = array( 'property', 'floor', 'room_type', 'room' );

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_blocks';

	/**
	 * Mass-assignable columns.
	 *
	 * @var string[]
	 */
	protected array $fillable = array(
		'scope',
		'scope_id',
		'start_at',
		'end_at',
		'start_at_gmt',
		'end_at_gmt',
		'source',
		'feed_id',
		'external_uid',
		'reason',
		'created_by',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'         => 'int',
		'scope_id'   => 'int',
		'feed_id'    => 'int',
		'created_by' => 'int',
	);
}
