<?php
/**
 * Amenity data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\Amenity;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `amenities`.
 */
class AmenityRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = Amenity::class;

	/**
	 * Every amenity in display order.
	 *
	 * @return Amenity[]
	 */
	public function ordered(): array {
		$rows = Amenity::query()->orderBy( 'sort_order' )->orderBy( 'id' )->get();
		return array_map( static fn( $row ) => Amenity::hydrate( $row ), $rows );
	}

	/**
	 * The next sort position.
	 *
	 * @return int
	 */
	public function nextSortOrder(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- small read.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE( MAX(sort_order), -1 ) + 1 FROM %i', rtbp_table( 'amenities' ) ) );
	}

	/**
	 * Another amenity with this name (case-insensitive), if any.
	 *
	 * @param string $name      Name.
	 * @param int    $except_id Ignore this amenity.
	 * @return Amenity|null
	 */
	public function findByName( string $name, int $except_id = 0 ): ?Amenity {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lookup by name.
		$id    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE LOWER( name ) = LOWER( %s ) AND id <> %d ORDER BY id LIMIT 1', rtbp_table( 'amenities' ), $name, $except_id ) );
		$found = $id ? $this->find( $id ) : null;
		return $found instanceof Amenity ? $found : null;
	}

	/**
	 * Whether another amenity already has this name (case-insensitive).
	 *
	 * @param string $name      Name.
	 * @param int    $except_id Ignore this amenity.
	 * @return bool
	 */
	public function nameTaken( string $name, int $except_id = 0 ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uniqueness check.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE LOWER( name ) = LOWER( %s ) AND id <> %d LIMIT 1', rtbp_table( 'amenities' ), $name, $except_id ) );
	}
}
