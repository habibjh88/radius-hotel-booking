<?php
/**
 * Room type API shape.
 *
 * @package RadiusTheme\RadiusHotelBooking\Resources
 */

namespace RadiusTheme\RadiusHotelBooking\Resources;

use RadiusTheme\RadiusHotelBooking\Models\RoomType;

defined( 'ABSPATH' ) || exit;

/**
 * A room type as the staff app sees it, with its room counts and readiness
 * (`ready` / `no_rooms`, feature 6.2).
 */
final class RoomTypeResource {

	/**
	 * One room type.
	 *
	 * @param RoomType $type       Room type.
	 * @param array    $counts     State => count for this type.
	 * @param string[] $rate_plans Names of the active rate plans it sells (M07), in grid order.
	 * @return array
	 */
	public static function make( RoomType $type, array $counts = array(), array $rate_plans = array() ): array {
		$gallery = array();
		foreach ( (array) $type->gallery as $id ) {
			$gallery[] = self::image( (int) $id );
		}

		$rooms = array(
			'available'      => (int) ( $counts['available'] ?? 0 ),
			'maintenance'    => (int) ( $counts['maintenance'] ?? 0 ),
			'out_of_service' => (int) ( $counts['out_of_service'] ?? 0 ),
		);
		$rooms['total'] = array_sum( $rooms );

		return array(
			'id'                => (int) $type->id,
			'name'              => (string) $type->name,
			'slug'              => (string) $type->slug,
			'description'       => (string) $type->description,
			'short_description' => (string) $type->short_description,
			'gallery'           => array_values( array_filter( $gallery ) ),
			'featured_image'    => $type->featured_image_id ? self::image( (int) $type->featured_image_id ) : null,
			'amenities'         => array_values( (array) $type->amenities ),
			'bed_info'          => (string) $type->bed_info,
			'size_m2'           => null === $type->size_m2 ? null : (float) $type->size_m2,
			'max_adults'        => (int) $type->max_adults,
			'max_children'      => (int) $type->max_children,
			'buffer_minutes'    => null === $type->buffer_minutes ? null : (int) $type->buffer_minutes,
			'is_active'         => (bool) $type->is_active,
			'sort_order'        => (int) $type->sort_order,
			'rooms'             => $rooms,
			'rate_plans'        => array_values( $rate_plans ),
			'readiness'         => $rooms['available'] > 0 ? 'ready' : 'no_rooms',
		);
	}

	/**
	 * An image attachment for the API.
	 *
	 * @param int $id Attachment id.
	 * @return array|null
	 */
	private static function image( int $id ): ?array {
		$url = wp_get_attachment_image_url( $id, 'full' );
		if ( ! $url ) {
			return null;
		}
		return array(
			'id'    => $id,
			'url'   => $url,
			'thumb' => (string) wp_get_attachment_image_url( $id, 'medium' ),
			'alt'   => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		);
	}
}
