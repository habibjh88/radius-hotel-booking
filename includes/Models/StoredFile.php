<?php
/**
 * Stored file model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

/**
 * A file in protected storage. Use Storage\ProtectedFiles, not this model,
 * to create, read or delete files.
 */
class StoredFile extends BaseModel {

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_files';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array
	 */
	protected array $fillable = array(
		'token',
		'kind',
		'path',
		'original_name',
		'mime',
		'size',
		'sha256',
		'created_by',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'size'       => 'int',
		'created_by' => 'int',
	);
}
