<?php
/**
 * Amenity section data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\AmenitySection;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `amenity_sections`.
 */
class AmenitySectionRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = AmenitySection::class;

	/**
	 * Every section in display order.
	 *
	 * @return AmenitySection[]
	 */
	public function ordered(): array {
		$rows = AmenitySection::query()->orderBy( 'sort_order' )->orderBy( 'id' )->get();
		return array_map( static fn( $row ) => AmenitySection::hydrate( $row ), $rows );
	}

	/**
	 * The next sort position.
	 *
	 * @return int
	 */
	public function nextSortOrder(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- small read.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE( MAX(sort_order), -1 ) + 1 FROM %i', rtbp_table( 'amenity_sections' ) ) );
	}

	/**
	 * A section by name (case-insensitive), if any.
	 *
	 * @param string $name      Name.
	 * @param int    $except_id Ignore this section.
	 * @return AmenitySection|null
	 */
	public function findByName( string $name, int $except_id = 0 ): ?AmenitySection {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lookup by name.
		$id    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE LOWER( name ) = LOWER( %s ) AND id <> %d ORDER BY id LIMIT 1', rtbp_table( 'amenity_sections' ), $name, $except_id ) );
		$found = $id ? $this->find( $id ) : null;
		return $found instanceof AmenitySection ? $found : null;
	}
}
