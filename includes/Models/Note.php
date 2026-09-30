<?php
/**
 * Note model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * An author-stamped note on a guest, a booking or an employee (table
 * `notes`, M09).
 *
 * @property int      $id
 * @property string   $notable_type
 * @property int      $notable_id
 * @property string   $type
 * @property string   $body
 * @property int|null $author_id
 * @property string   $author_name
 * @property int|null $edited_by
 * @property string   $created_at
 * @property string   $updated_at
 */
class Note extends BaseModel {

	/**
	 * Note types (the legacy `general|caution|warning`).
	 */
	const TYPES = array( 'general', 'caution', 'warning' );

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_notes';

	/**
	 * Mass-assignable columns.
	 *
	 * @var string[]
	 */
	protected array $fillable = array(
		'notable_type',
		'notable_id',
		'type',
		'body',
		'author_id',
		'author_name',
		'edited_by',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'         => 'int',
		'notable_id' => 'int',
		'author_id'  => 'int',
		'edited_by'  => 'int',
	);
}
