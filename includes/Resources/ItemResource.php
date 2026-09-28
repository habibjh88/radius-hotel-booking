<?php
/**
 * Item API resource.
 *
 * @package RadiusTheme\RadiusHotelBooking\Resources
 */

namespace RadiusTheme\RadiusHotelBooking\Resources;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseResource;

/**
 * Class ItemResource
 *
 * Shapes an Item model for API output. Keep every field the frontend reads
 * here so the REST contract stays explicit.
 */
class ItemResource extends BaseResource {

	/**
	 * Transform an Item model into an API-friendly array.
	 *
	 * @param object $item The Item model instance.
	 *
	 * @return array
	 */
	public function transform( $item ): array {
		return array(
			'id'          => (int) $item->id,
			'title'       => $item->title,
			'description' => $item->description,
			'status'      => $item->status,
			'price'       => (float) $item->price,
			'position'    => (int) $item->position,
			'meta'        => $item->meta,
			'created_at'  => $item->created_at,
			'updated_at'  => $item->updated_at,
		);
	}
}
