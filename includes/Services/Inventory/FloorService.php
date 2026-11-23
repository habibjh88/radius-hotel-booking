<?php
/**
 * Floors: create, rename, reorder, delete.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Inventory
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Inventory;

use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Floor;
use RadiusTheme\RadiusHotelBooking\Repositories\FloorRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The hotel's ordered list of named floors (feature 6.3). Any number of
 * floors; names are unique (ignoring case). A floor that holds current rooms
 * cannot be deleted. Removed rooms do not count: bookings keep the floor's
 * name, and a removed room that is added back goes on the floor chosen then.
 */
class FloorService {

	/**
	 * Floors.
	 *
	 * @var FloorRepository
	 */
	protected FloorRepository $floors;

	/**
	 * Constructor.
	 *
	 * @param FloorRepository|null $floors Floors.
	 */
	public function __construct( ?FloorRepository $floors = null ) {
		$this->floors = $floors ?? new FloorRepository();
	}

	/**
	 * Every floor in order, with its room counts.
	 *
	 * @return array[] `{ id, name, sort_order, rooms, total }`.
	 */
	public function all(): array {
		$counts = $this->floors->roomCounts();
		return array_map(
			static fn( Floor $floor ) => array(
				'id'         => (int) $floor->id,
				'name'       => (string) $floor->name,
				'sort_order' => (int) $floor->sort_order,
				// Live rooms, and every room incl. removed ones (the delete guard).
				'rooms'      => $counts[ (int) $floor->id ]['rooms'] ?? 0,
				'total'      => $counts[ (int) $floor->id ]['total'] ?? 0,
			),
			$this->floors->ordered()
		);
	}

	/**
	 * One floor, or 404.
	 *
	 * @param int $id Id.
	 * @return Floor
	 * @throws DomainException When missing.
	 */
	public function get( int $id ): Floor {
		$floor = $this->floors->find( $id );
		if ( ! $floor instanceof Floor ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This floor does not exist.', 'radius-hotel-booking' ) );
		}
		return $floor;
	}
}
