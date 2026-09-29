<?php
/**
 * Rate plan model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * A rate plan (table `rate_plans`): a reusable stay window. Its behaviour
 * comes from explicit fields (type, times, duration, multi_unit), never from
 * its name.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $code
 * @property string      $type
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int         $duration_minutes
 * @property string|null $checkin_from
 * @property string|null $checkin_until
 * @property bool        $multi_unit
 * @property array       $features
 * @property string      $policy
 * @property bool        $is_active
 * @property int         $sort_order
 */
class RatePlan extends BaseModel {

	const FIXED    = 'fixed';
	const FLEXIBLE = 'flexible';

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_rate_plans';

	/**
	 * Soft deletes: bookings keep naming a removed plan.
	 *
	 * @var bool
	 */
	protected bool $softDeletes = true;

	/**
	 * Mass-assignable columns.
	 *
	 * @var array
	 */
	protected array $fillable = array(
		'name',
		'code',
		'type',
		'start_time',
		'end_time',
		'duration_minutes',
		'checkin_from',
		'checkin_until',
		'multi_unit',
		'features',
		'policy',
		'is_active',
		'sort_order',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'               => 'int',
		'duration_minutes' => 'int',
		'multi_unit'       => 'bool',
		'features'         => 'json',
		'is_active'        => 'bool',
		'sort_order'       => 'int',
	);

	/**
	 * The plan types.
	 *
	 * @return string[]
	 */
	public static function types(): array {
		return array( self::FIXED, self::FLEXIBLE );
	}
}
