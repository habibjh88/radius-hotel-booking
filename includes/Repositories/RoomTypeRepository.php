<?php
/**
 * Room type data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\RoomType;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `room_types` (soft-deleted rows excluded).
 */
class RoomTypeRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = RoomType::class;

	/**
	 * Every room type in display order.
	 *
	 * @param bool $active_only Only active types.
	 * @return RoomType[]
	 */
	public function ordered( bool $active_only = false ): array {
		$query = RoomType::query();
		if ( $active_only ) {
			$query->where( 'is_active', '=', 1 );
		}
		$rows = $query->orderBy( 'sort_order' )->orderBy( 'name' )->get();
		return array_map( static fn( $row ) => RoomType::hydrate( $row ), $rows );
	}

	/**
	 * Whether a slug is taken (removed types included, so a slug is never reused).
	 *
	 * @param string $slug       Slug.
	 * @param int    $except_id  Ignore this type.
	 * @return bool
	 */
	public function slugTaken( string $slug, int $except_id = 0 ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uniqueness check.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE slug = %s AND id <> %d LIMIT 1', rtbp_table( 'room_types' ), $slug, $except_id ) );
	}

	/**
	 * Lock a live room type row until the transaction ends, so no room can be
	 * added to it while it is being deleted (and the reverse).
	 *
	 * @param int $room_type_id Room type id.
	 * @return bool Whether the type exists and is not removed.
	 */
	public function lock( int $room_type_id ): bool {
		return $this->lockRow( 'room_types', $room_type_id, true );
	}
}
