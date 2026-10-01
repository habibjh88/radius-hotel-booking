<?php
/**
 * Payment model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * One ledger row (table `payments`, M05, ADR-010): a payment, a refund, an
 * adjustment, or the void of another row. The amount is signed as it counts
 * towards what the guest paid. Rows are never edited or deleted.
 *
 * @property int         $id
 * @property int         $booking_id
 * @property string      $type
 * @property float       $amount
 * @property string      $method
 * @property string      $reference
 * @property string|null $note
 * @property int|null    $voids_payment_id
 * @property string      $received_at
 * @property string      $received_at_gmt
 * @property int|null    $recorded_by
 * @property string|null $receipt_no
 * @property float|null  $balance_after
 * @property string      $created_at
 * @property string      $updated_at
 */
class Payment extends BaseModel {

	/**
	 * Row types. Staff record payments and refunds; a void cancels a row;
	 * an adjustment is for imports and corrections by code.
	 */
	const TYPES = array( 'payment', 'refund', 'adjustment', 'void' );

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_payments';

	/**
	 * Mass-assignable columns.
	 *
	 * @var string[]
	 */
	protected array $fillable = array(
		'booking_id',
		'type',
		'amount',
		'method',
		'reference',
		'note',
		'voids_payment_id',
		'received_at',
		'received_at_gmt',
		'recorded_by',
		'receipt_no',
		'balance_after',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'               => 'int',
		'booking_id'       => 'int',
		'amount'           => 'float',
		'voids_payment_id' => 'int',
		'recorded_by'      => 'int',
	);
}
