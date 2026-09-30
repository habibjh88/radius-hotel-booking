<?php
/**
 * Guest model.
 *
 * @package RadiusTheme\RadiusHotelBooking\Models
 */

namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;

defined( 'ABSPATH' ) || exit;

/**
 * A guest (table `guests`, M09): one real person, not a WordPress user.
 *
 * @property int         $id
 * @property string      $reference
 * @property string      $first_name
 * @property string      $last_name
 * @property string      $name_search
 * @property string      $phone
 * @property string|null $phone_e164
 * @property string      $phone_tail
 * @property string      $email
 * @property string|null $email_key
 * @property bool        $email_is_placeholder
 * @property string      $id_type
 * @property string      $id_number
 * @property string      $standing
 * @property string      $ban_reason
 * @property string|null $banned_at
 * @property int|null    $banned_by
 * @property int|null    $wp_user_id
 * @property int         $stays_count
 * @property string|null $last_stay_at
 * @property int|null    $created_by
 */
class Guest extends BaseModel {

	/**
	 * Standing: may be booked.
	 */
	const NORMAL = 'normal';

	/**
	 * Standing: may not be booked (9.10).
	 */
	const BANNED = 'banned';

	/**
	 * Table, without the WordPress prefix.
	 *
	 * @var string
	 */
	protected string $table = 'radius_hotel_booking_guests';

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
		'first_name',
		'last_name',
		'name_search',
		'phone',
		'phone_e164',
		'phone_tail',
		'email',
		'email_key',
		'email_is_placeholder',
		'id_type',
		'id_number',
		'standing',
		'ban_reason',
		'banned_at',
		'banned_by',
		'wp_user_id',
		'stays_count',
		'last_stay_at',
		'created_by',
	);

	/**
	 * Casts.
	 *
	 * @var array
	 */
	protected array $casts = array(
		'id'                   => 'int',
		'email_is_placeholder' => 'bool',
		'banned_by'            => 'int',
		'wp_user_id'           => 'int',
		'stays_count'          => 'int',
		'created_by'           => 'int',
	);

	/**
	 * "First Last", or the reference when both are empty.
	 *
	 * @return string
	 */
	public function fullName(): string {
		$name = trim( $this->first_name . ' ' . $this->last_name );
		return '' === $name ? (string) $this->reference : $name;
	}
}
