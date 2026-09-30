<?php
/**
 * Guest API shapes.
 *
 * @package RadiusTheme\RadiusHotelBooking\Resources
 */

namespace RadiusTheme\RadiusHotelBooking\Resources;

use RadiusTheme\RadiusHotelBooking\Access\Access;
use RadiusTheme\RadiusHotelBooking\Models\Guest;
use RadiusTheme\RadiusHotelBooking\Services\Guests\GuestService;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * A guest for the API. The ID number is **never** included in full — lists
 * and the detail show `••••3456`; the full number comes only from
 * `GET guests/{id}/id-number` with `guests.view_id`, which is logged. A
 * placeholder e-mail (9.12) is sent as `email: null` with the flag, so no
 * screen shows or uses it as a real address.
 */
final class GuestResource {

	/**
	 * A row of the list or the lookup.
	 *
	 * @param Guest $guest Guest.
	 * @return array
	 */
	public static function summary( Guest $guest ): array {
		$types = GuestService::idTypes();
		return array(
			'id'                   => (int) $guest->id,
			'reference'            => (string) $guest->reference,
			'first_name'           => (string) $guest->first_name,
			'last_name'            => (string) $guest->last_name,
			'name'                 => $guest->fullName(),
			'phone'                => (string) $guest->phone,
			'email'                => $guest->email_is_placeholder ? null : (string) $guest->email,
			'email_is_placeholder' => (bool) $guest->email_is_placeholder,
			'standing'             => (string) $guest->standing,
			'stays_count'          => (int) $guest->stays_count,
			'last_stay_at'         => self::local( $guest->last_stay_at ),
			'id_type'              => (string) $guest->id_type,
			'id_type_label'        => (string) ( $types[ $guest->id_type ] ?? $guest->id_type ),
			'id_number_masked'     => GuestService::maskId( (string) $guest->id_number ),
		);
	}

	/**
	 * The detail screen's guest.
	 *
	 * @param Guest $guest Guest.
	 * @return array
	 */
	public static function detail( Guest $guest ): array {
		$banned_by = $guest->banned_by ? get_userdata( (int) $guest->banned_by ) : null;
		$created   = $guest->created_by ? get_userdata( (int) $guest->created_by ) : null;
		return array_merge(
			self::summary( $guest ),
			array(
				'phone_e164'  => $guest->phone_e164,
				'has_id'      => '' !== (string) $guest->id_number,
				// Whether this viewer may reveal the full number (the server checks again).
				'can_view_id' => Access::LOCKED !== Access::level( 'guests.view_id' ),
				'ban_reason'  => (string) $guest->ban_reason,
				'banned_at'   => self::local( $guest->banned_at ),
				'banned_by'   => $banned_by ? array(
					'id'   => (int) $banned_by->ID,
					'name' => (string) $banned_by->display_name,
				) : null,
				'wp_user_id'  => $guest->wp_user_id ? (int) $guest->wp_user_id : null,
				'created_by'  => $created ? array(
					'id'   => (int) $created->ID,
					'name' => (string) $created->display_name,
				) : null,
				// BaseModel stamps created_at in local time already.
				'created_at'  => empty( $guest->created_at ) ? null : substr( (string) $guest->created_at, 0, 16 ),
			)
		);
	}

	/**
	 * A stored UTC time (`banned_at`, `last_stay_at`) as a local `Y-m-d H:i`, or null.
	 *
	 * @param string|null $gmt UTC `Y-m-d H:i:s`.
	 * @return string|null
	 */
	private static function local( $gmt ): ?string {
		if ( empty( $gmt ) || '0000-00-00 00:00:00' === $gmt ) {
			return null;
		}
		return substr( Dates::to_db( Dates::from_gmt( (string) $gmt ) ), 0, 16 );
	}
}
