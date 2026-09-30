<?php
/**
 * InvoiceVersion model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * One issued version of an invoice with the snapshot it showed (table
 * `invoice_versions`, M05).
 *
 * @property int         $id
 * @property int         $invoice_id
 * @property int         $version
 * @property string      $snapshot   JSON (wp_json_encode).
 * @property string|null $file_token
 * @property string      $created_at
 * @property string      $updated_at
 */
class InvoiceVersion extends BaseModel {

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_invoice_versions';

	/**
	 * Mass-assignable columns.
	 *
	 * @var string[]
	 */
	protected array $fillable = array(
		'invoice_id',
		'version',
		'snapshot',
		'file_token',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'         => 'int',
		'invoice_id' => 'int',
		'version'    => 'int',
	);
}
