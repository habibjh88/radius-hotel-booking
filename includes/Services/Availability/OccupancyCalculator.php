<?php
/**
 * Occupancy of a room type on a local day.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Availability
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Availability;

use RadiusTheme\RadiusHotelBooking\Models\Room;
use RadiusTheme\RadiusHotelBooking\Repositories\AvailabilityRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomRepository;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Occupancy for pricing (booking-engine §6 step 5) and, later, reports: the
 * room type's occupied rooms ÷ its sellable rooms over one local day, as a
 * percentage. Minimal version (M07): sellable rooms are the type's rooms in
 * state `available`; occupied rooms come from the `rtbp_occupied_rooms`
 * filter, which the engine fills (M08: any occupying line, live hold or
 * block overlapping the day, `occupied_from_engine()`).
 *
 * The availability search already holds the rooms and busy intervals, so it
 * `prime()`s the counts for the dates it prices: no query per date then.
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
	 * Request-wide counts primed by the search: "type:date" => [ occupied, sellable ].
	 *
	 * @var array<string, int[]>
	 */
	private static array $primed = array();

	/**
	 * While set, what the engine's answer leaves out: `{ token, line }` — the
	 * requester's own holds and the line being edited, as the search does.
	 * Cached and primed counts are skipped meanwhile.
	 *
	 * @var array|null
	 */
	private static ?array $excluding = null;

	/**
	 * Run a callback (a re-quote inside the write path) with the requester's
	 * own holds and edited line left out of occupancy, so the price matches
	 * the one the search showed (critical review, M08).
	 *
	 * @param string   $hold_token   The requester's hold token.
	 * @param int      $exclude_line A line being edited.
	 * @param callable $callback     Work to run.
	 * @return mixed The callback's result.
	 */
	public static function excluding( string $hold_token, int $exclude_line, callable $callback ) {
		$previous        = self::$excluding;
		self::$excluding = array(
			'token' => $hold_token,
			'line'  => $exclude_line,
		);
		try {
			return $callback();
		} finally {
			self::$excluding = $previous;
		}
	}

	/**
	 * Register the engine's `rtbp_occupied_rooms` answer.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'rtbp_occupied_rooms', array( self::class, 'occupied_from_engine' ), 5, 3 );
	}

	/**
	 * Remember a room type's counts for a local date (from the search).
	 *
	 * @param int    $room_type_id Room type id.
	 * @param string $date         Local date.
	 * @param int    $occupied     Occupied sellable rooms.
	 * @param int    $sellable     Sellable rooms.
	 * @return void
	 */
	public static function prime( int $room_type_id, string $date, int $occupied, int $sellable ): void {
		self::$primed[ $room_type_id . ':' . $date ] = array( max( 0, $occupied ), max( 0, $sellable ) );
	}

	/**
	 * Prime from rows a caller already loaded (the search, the calendar):
	 * per room type and local date, the sellable rooms with any busy interval
	 * overlapping that day. Replaces what was primed before.
	 *
	 * @param int[]    $room_type_ids Room type ids.
	 * @param array    $rooms_by_type Type id => rooms (`id`, `state`).
	 * @param array    $busy          Room id => busy intervals (`s`, `e`, GMT).
	 * @param string[] $dates         Local dates.
	 * @return array<int, array<string, int[]>> Type => date => [ occupied, sellable ].
	 */
	public static function primeFrom( array $room_type_ids, array $rooms_by_type, array $busy, array $dates ): array {
		self::flush_primed();
		$out = array();
		foreach ( $dates as $date ) {
			$day_start = Dates::start_of_day( $date )->getTimestamp();
			$day_end   = Dates::end_of_day( $date )->getTimestamp();
			foreach ( $room_type_ids as $type_id ) {
				$sellable = 0;
				$occupied = 0;
				foreach ( $rooms_by_type[ $type_id ] ?? array() as $room ) {
					if ( Room::AVAILABLE !== $room['state'] ) {
						continue;
					}
					++$sellable;
					foreach ( $busy[ (int) $room['id'] ] ?? array() as $interval ) {
						if ( Overlap::overlaps( $day_start, $day_end, Overlap::ts( $interval['s'] ), Overlap::ts( $interval['e'] ) ) ) {
							++$occupied;
							break;
						}
					}
				}
				self::prime( (int) $type_id, (string) $date, $occupied, $sellable );
				$out[ (int) $type_id ][ (string) $date ] = array( $occupied, $sellable );
			}
		}
		return $out;
	}

	/**
	 * Forget the primed counts (after a booking write, in checks).
	 *
	 * @return void
	 */
	public static function flush_primed(): void {
		self::$primed = array();
	}

	/**
	 * `rtbp_occupied_rooms`: the room type's sellable rooms that have an
	 * occupying line, a live hold or a block overlapping the local day.
	 *
	 * @param int    $occupied     Earlier answer.
	 * @param int    $room_type_id Room type id.
	 * @param string $date         Local date.
	 * @return int
	 */
	public static function occupied_from_engine( $occupied, $room_type_id, $date ): int {
		if ( (int) $occupied > 0 || ! Dates::is_date( (string) $date ) ) {
			return (int) $occupied;
		}
		$room_ids = array_map( static fn( $room ) => (int) $room->id, ( new RoomRepository() )->sellableRooms( array( (int) $room_type_id ) ) );
		if ( ! $room_ids ) {
			return 0;
		}
		$start = Dates::start_of_day( (string) $date );
		$busy  = ( new AvailabilityRepository() )->busy( $room_ids, Dates::to_gmt_db( $start ), Dates::to_gmt_db( Dates::add_days( $start, 1 ) ), self::$excluding['token'] ?? '', self::$excluding['line'] ?? 0 );
		return count( array_filter( $busy ) );
	}

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
		// Inside excluding(): always ask the engine, with the exclusions.
		$fresh = null !== self::$excluding;
		if ( ! $fresh && isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		if ( ! $fresh && isset( self::$primed[ $key ] ) ) {
			list( $occupied, $sellable ) = self::$primed[ $key ];
			$percent                     = $sellable > 0 ? min( 100.0, round( $occupied * 100 / $sellable, 2 ) ) : 0.0;
			$this->cache[ $key ]         = $percent;
			return $percent;
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

		if ( ! $fresh ) {
			$this->cache[ $key ] = $percent;
		}
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
