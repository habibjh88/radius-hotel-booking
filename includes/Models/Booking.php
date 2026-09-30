<?php
/**
 * Booking model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * A booking (table `bookings`, M02): the guest, the money and a summary
 * status; the rooms and windows are its lines (`booking_rooms`).
 *
 * @property int         $id
 * @property string      $reference
 * @property int|null    $guest_id
 * @property string      $source
 * @property string      $status
 * @property string      $payment_status
 * @property bool        $on_hold
 * @property float       $subtotal
 * @property float       $discount_total
 * @property float       $tax_total
 * @property float       $total
 * @property float       $paid_total
 * @property float       $balance_due
 * @property string      $currency
 * @property int         $adults
 * @property int         $children
 * @property string|null $special_requests
 * @property string|null $public_token
 * @property int|null    $created_by
 * @property string|null $created_at_gmt
 */
class Booking extends BaseModel {

	/**
	 * Payment states a booking can be created in (2.11).
	 */
	const PAYMENT_STATES = array( 'unpaid', 'paid' );

	/**
	 * Sources.
	 */
	const SOURCES = array( 'desk', 'web', 'import', 'ical' );

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_bookings';

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
		'reference',
		'guest_id',
		'source',
		'status',
		'payment_status',
		'on_hold',
		'subtotal',
		'discount_total',
		'tax_total',
		'total',
		'paid_total',
		'balance_due',
		'currency',
		'payment_due_at',
		'payment_due_at_gmt',
		'adults',
		'children',
		'special_requests',
		'public_token',
		'created_by',
		'approved_by',
		'approved_at',
		'cancelled_reason',
		'created_at_gmt',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'             => 'int',
		'guest_id'       => 'int',
		'on_hold'        => 'bool',
		'subtotal'       => 'float',
		'discount_total' => 'float',
		'tax_total'      => 'float',
		'total'          => 'float',
		'paid_total'     => 'float',
		'balance_due'    => 'float',
		'adults'         => 'int',
		'children'       => 'int',
		'created_by'     => 'int',
	);
}
