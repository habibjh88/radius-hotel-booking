<?php
/**
 * Occupancy of a room type on a local day.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Availability
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Availability;

use RadiusTheme\RadiusHotelBooking\Repositories\RoomRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Occupancy for pricing (booking-engine §6 step 5) and, later, reports: the
 * room type's occupied rooms ÷ its sellable rooms over one local day, as a
 * percentage. Minimal version (M07): sellable rooms are the type's rooms in
 * state `available`; occupied rooms come from the `rtbp_occupied_rooms`
 * filter, which the bookings and availability modules (M02, M08) fill —
 * until then it is 0.
 */
class OccupancyCalculator {

	/**
	 * Rooms.
	 *
	 * @var RoomRepository
	 */
	private RoomRepository $rooms;

	/**
	 * Per-request cache: "type:date" => percent.
	 *
	 * @var array<string, float>
	 */
	private array $cache = array();

	/**
	 * Constructor.
	 *
	 * @param RoomRepository|null $rooms Rooms.
	 */
	public function __construct( ?RoomRepository $rooms = null ) {
		$this->rooms = $rooms ?? new RoomRepository();
	}

	/**
	 * Occupancy in percent (0–100) of a room type on a local date.
	 *
	 * @param int    $room_type_id Room type id.
	 * @param string $date         Local date `Y-m-d`.
	 * @return float
	 */
	public function percent( int $room_type_id, string $date ): float {
		$key = $room_type_id . ':' . $date;
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$sellable = count( $this->rooms->sellableRooms( array( $room_type_id ) ) );

		/**
		 * The number of the room type's rooms occupied on a local day (any
		 * booking line, hold or block overlapping it). Filled by M02 / M08.
		 *
		 * @param int    $occupied     Occupied rooms (0).
		 * @param int    $room_type_id Room type id.
		 * @param string $date         Local date `Y-m-d`.
		 */
		$occupied = max( 0, (int) apply_filters( 'rtbp_occupied_rooms', 0, $room_type_id, $date ) );

		$percent = $sellable > 0 ? min( 100.0, round( $occupied * 100 / $sellable, 2 ) ) : 0.0;

		$this->cache[ $key ] = $percent;
		return $percent;
	}

	/**
	 * Forget the cached values (after a write, in tests).
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->cache = array();
	}
}
