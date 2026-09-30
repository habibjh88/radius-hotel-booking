<?php
/**
 * Invoice model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * A booking's invoice (table `invoices`, M05): its number, the version in
 * force and its status. Earlier versions are in `invoice_versions`.
 *
 * @property int         $id
 * @property int         $booking_id
 * @property string      $number
 * @property int         $version
 * @property string      $status
 * @property string|null $totals     JSON (wp_json_encode), as the booking lines store theirs.
 * @property string|null $file_token
 * @property string      $issued_at
 * @property string      $issued_at_gmt
 * @property string|null $sent_at
 * @property string      $created_at
 * @property string      $updated_at
 */
class Invoice extends BaseModel {

	/**
	 * Statuses: issued (version 1), revised (a later version), cancelled.
	 */
	const STATUSES = array( 'issued', 'revised', 'cancelled' );

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_invoices';

	/**
	 * Mass-assignable columns.
	 *
	 * @var string[]
	 */
	protected array $fillable = array(
		'booking_id',
		'number',
		'version',
		'status',
		'totals',
		'file_token',
		'issued_at',
		'issued_at_gmt',
		'sent_at',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'         => 'int',
		'booking_id' => 'int',
		'version'    => 'int',
	);
}
