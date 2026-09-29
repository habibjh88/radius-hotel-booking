<?php
/**
 * Rooms: add, rename, change floor, change state, remove.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Inventory
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Inventory;

use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Room;
use RadiusTheme\RadiusHotelBooking\Repositories\FloorRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;
use RadiusTheme\RadiusHotelBooking\Support\Db;
use RadiusTheme\RadiusHotelBooking\Support\NaturalSort;

defined( 'ABSPATH' ) || exit;

/**
 * Physical rooms (features 6.4–6.9). A room belongs to exactly one room type
 * and sits on one floor. Its number is unique across the property, removed
 * rooms included, so a room can never be sold under two types and history
 * stays unambiguous. Only `available` rooms are sold.
 *
 * Writes lock the room type and floor rows (`FOR UPDATE`) inside a
 * transaction: RoomTypeService::delete() and FloorService::delete() take the
 * same locks, so neither can pass its "no rooms" check while a room is added.
 */
class RoomService {

	/**
	 * Rooms.
	 *
	 * @var RoomRepository
	 */
	private RoomRepository $rooms;

	/**
	 * Room types.
	 *
	 * @var RoomTypeRepository
	 */
	private RoomTypeRepository $types;

	/**
	 * Floors.
	 *
	 * @var FloorRepository
	 */
	private FloorRepository $floors;

	/**
	 * Constructor.
	 *
	 * @param RoomRepository|null     $rooms  Rooms.
	 * @param RoomTypeRepository|null $types  Room types.
	 * @param FloorRepository|null    $floors Floors.
	 */
	public function __construct( ?RoomRepository $rooms = null, ?RoomTypeRepository $types = null, ?FloorRepository $floors = null ) {
		$this->rooms  = $rooms ?? new RoomRepository();
		$this->types  = $types ?? new RoomTypeRepository();
		$this->floors = $floors ?? new FloorRepository();
	}

	/**
	 * One room (not removed), or 404.
	 *
	 * @param int $id Id.
	 * @return Room
	 * @throws DomainException When missing.
	 */
	public function get( int $id ): Room {
		$room = $this->rooms->find( $id );
		if ( ! $room instanceof Room ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This room does not exist.', 'radius-hotel-booking' ) );
		}
		return $room;
	}

	/**
	 * A room type's rooms grouped by floor, floors in their order, rooms in
	 * natural order: the `RoomPicker` shape. Rooms whose floor is gone come
	 * last, under a group with id 0.
	 *
	 * @param int $room_type_id Room type id.
	 * @return array{ floors: array[] }
	 */
	public function groupedByFloor( int $room_type_id ): array {
		$groups = array();
		foreach ( $this->floors->ordered() as $floor ) {
			$groups[ (int) $floor->id ] = array(
				'id'    => (int) $floor->id,
				'name'  => (string) $floor->name,
				'rooms' => array(),
			);
		}

		$orphans = array();
		foreach ( $this->rooms->ofType( $room_type_id ) as $room ) {
			$item = self::shape( $room );
			if ( isset( $groups[ (int) $room->floor_id ] ) ) {
				$groups[ (int) $room->floor_id ]['rooms'][] = $item;
			} else {
				$orphans[] = $item;
			}
		}

		$floors = array_values( $groups );
		if ( $orphans ) {
			$floors[] = array(
				'id'    => 0,
				'name'  => __( 'No floor', 'radius-hotel-booking' ),
				'rooms' => $orphans,
			);
		}
		return array( 'floors' => $floors );
	}

	/**
	 * Add a room.
	 *
	 * @param array $input `{ room_type_id, floor_id, number, state?, state_note? }`.
	 * @return Room
	 * @throws DomainException When the insert did not happen.
	 */
	public function create( array $input ): Room {
		$number  = $this->validNumber( $input['number'] ?? '', 0 );
		$type_id = absint( $input['room_type_id'] ?? 0 );
		$floor   = absint( $input['floor_id'] ?? 0 );
		list( $state, $note ) = $this->validState( $input, Room::AVAILABLE, '' );

		$room = Transaction::run(
			function () use ( $number, $type_id, $floor, $state, $note ) {
				$this->lockParents( $type_id, $floor );
				// Checked again under the locks: two adds of one number cannot both pass.
				$this->assertNumberFree( $number, 0 );
				return Db::quietly(
					fn() => $this->rooms->create(
						array(
							'room_type_id' => $type_id,
							'floor_id'     => $floor,
							'number'       => $number,
							'number_sort'  => NaturalSort::key( $number ),
							'state'        => $state,
							'state_note'   => $note,
						)
					)
				);
			}
		);
		if ( ! $room instanceof Room || ! $room->id ) {
			$this->assertNumberFree( $number, 0 );
			if ( Db::duplicateKey() ) {
				$message = sprintf(
					/* translators: %s: room number. */
					__( 'Room %s was just added by someone else. Choose another number.', 'radius-hotel-booking' ),
					$number
				);
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw new DomainException( 'room_number_taken', $message, 409, array( 'number' => $message ) );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'room_not_saved', __( 'The room could not be saved. Please try again.', 'radius-hotel-booking' ), 500 );
		}

		rtbp_activity(
			'rooms.create',
			$room,
			array(
				'after'       => array(
					'number'       => $number,
					'room_type_id' => $type_id,
					'floor_id'     => $floor,
					'state'        => $state,
				),
				/* translators: %s: room number. */
				'description' => sprintf( __( 'Added room %s', 'radius-hotel-booking' ), $number ),
			)
		);

		/**
		 * Fires after a room is added.
		 *
		 * @param Room $room Room.
		 */
		do_action( 'rtbp_room_created', $room );

		return $room;
	}

	/**
	 * Most rooms one bulk add may create.
	 */
	const BULK_MAX = 200;

	/**
	 * Add a range of rooms: prefix + from…to, optionally zero-padded
	 * ("A" 1–12 → A1…A12; pad 3 → A001…A012), all on one floor (6.10).
	 * Numbers that already exist (removed rooms included) are listed and
	 * skipped. With `dry_run` nothing is written: the answer is the preview.
	 *
	 * @param array $input `{ room_type_id, floor_id, prefix, from, to, pad, dry_run }`.
	 * @return array{ numbers: array[], create: int, skip: int, created: array[] }
	 * @throws DomainException With field errors.
	 */
	public function bulk( array $input ): array {
		$type_id = absint( $input['room_type_id'] ?? 0 );
		$floor   = absint( $input['floor_id'] ?? 0 );
		$prefix  = trim( sanitize_text_field( (string) ( $input['prefix'] ?? '' ) ) );
		$from    = filter_var( $input['from'] ?? null, FILTER_VALIDATE_INT );
		$to      = filter_var( $input['to'] ?? null, FILTER_VALIDATE_INT );
		$pad     = filter_var( $input['pad'] ?? 0, FILTER_VALIDATE_INT );
		$dry     = rest_sanitize_boolean( $input['dry_run'] ?? false );

		$errors = array();
		if ( '' !== $prefix && ! preg_match( '/^[\p{L}\p{N}][\p{L}\p{N} \-\/.]*$/u', $prefix ) ) {
			$errors['prefix'] = __( 'Use letters, digits, spaces, dashes, slashes or dots.', 'radius-hotel-booking' );
		}
		if ( false === $from || $from < 0 || $from > 99999 ) {
			$errors['from'] = __( 'A whole number from 0.', 'radius-hotel-booking' );
		}
		if ( false === $to || $to < 0 || $to > 99999 ) {
			$errors['to'] = __( 'A whole number from 0.', 'radius-hotel-booking' );
		} elseif ( false !== $from && $to < $from ) {
			$errors['to'] = __( 'The last number must not be smaller than the first.', 'radius-hotel-booking' );
		} elseif ( false !== $from && $to - $from + 1 > self::BULK_MAX ) {
			/* translators: %d: most rooms per bulk add. */
			$errors['to'] = sprintf( __( 'Add at most %d rooms at a time.', 'radius-hotel-booking' ), self::BULK_MAX );
		}
		if ( false === $pad || $pad < 0 || $pad > 6 ) {
			$errors['pad'] = __( 'Pad to at most 6 digits.', 'radius-hotel-booking' );
		}
		if ( ! $errors && mb_strlen( $prefix . str_pad( (string) $to, (int) $pad, '0', STR_PAD_LEFT ) ) > 20 ) {
			$errors['prefix'] = __( 'Room numbers must stay within 20 characters.', 'radius-hotel-booking' );
		}
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}

		$numbers = array();
		for ( $n = $from; $n <= $to; $n++ ) {
			$numbers[] = $prefix . str_pad( (string) $n, (int) $pad, '0', STR_PAD_LEFT );
		}

		if ( $dry ) {
			if ( ! $type_id || ! $this->types->find( $type_id ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::invalid( array( 'room_type_id' => __( 'Choose a room type.', 'radius-hotel-booking' ) ) );
			}
			if ( ! $floor || ! $this->floors->find( $floor ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::invalid( array( 'floor_id' => __( 'Choose a floor.', 'radius-hotel-booking' ) ) );
			}
			return $this->bulkPlan( $numbers ) + array( 'created' => array() );
		}

		$result = Transaction::run(
			function () use ( $numbers, $type_id, $floor ) {
				$this->lockParents( $type_id, $floor );
				// Planned under the locks, so the list of clashes is the truth.
				$plan    = $this->bulkPlan( $numbers );
				$created = array();
				foreach ( $plan['numbers'] as $item ) {
					if ( 'new' !== $item['status'] ) {
						continue;
					}
					$room = Db::quietly(
						fn() => $this->rooms->create(
							array(
								'room_type_id' => $type_id,
								'floor_id'     => $floor,
								'number'       => $item['number'],
								'number_sort'  => NaturalSort::key( $item['number'] ),
								'state'        => Room::AVAILABLE,
								'state_note'   => '',
							)
						)
					);
					if ( ! $room instanceof Room || ! $room->id ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
						throw new DomainException( 'room_not_saved', __( 'The rooms could not be saved. Nothing was added.', 'radius-hotel-booking' ), 500 );
					}
					$created[] = $room;
				}
				return array(
					'plan'    => $plan,
					'created' => $created,
				);
			}
		);

		$created = $result['created'];
		if ( $created ) {
			$skipped = array_values( array_column( array_filter( $result['plan']['numbers'], static fn( $item ) => 'new' !== $item['status'] ), 'number' ) );
			rtbp_activity(
				'rooms.bulk_create',
				array(
					'type'  => 'room_type',
					'id'    => $type_id,
					'label' => (string) ( $this->types->find( $type_id )->name ?? '' ),
				),
				array(
					'after'       => array(
						'floor_id' => $floor,
						'rooms'    => array_map( static fn( Room $room ) => (string) $room->number, $created ),
						'skipped'  => $skipped,
					),
					'description' => sprintf(
						/* translators: %d: number of rooms. */
						_n( 'Added %d room in bulk', 'Added %d rooms in bulk', count( $created ), 'radius-hotel-booking' ),
						count( $created )
					),
				)
			);
			foreach ( $created as $room ) {
				/** This action is documented in RoomService::create(). */
				do_action( 'rtbp_room_created', $room );
			}
		}

		return $result['plan'] + array( 'created' => array_map( array( self::class, 'shape' ), $created ) );
	}

	/**
	 * Which of the numbers are new and which exist already (with the owner).
	 *
	 * @param string[] $numbers Numbers.
	 * @return array{ numbers: array[], create: int, skip: int }
	 */
	private function bulkPlan( array $numbers ): array {
		// One IN query finds whether anything clashes. The column's collation
		// also ignores accents and width ("É1" = "E1"), which PHP cannot
		// mirror, so when something does, each number is matched by the
		// database itself (at most BULK_MAX indexed lookups).
		$any = (bool) $this->rooms->existingNumbers( $numbers );

		$out  = array();
		$seen = array();
		foreach ( $numbers as $number ) {
			$key = mb_strtolower( $number );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$owner        = $any ? $this->rooms->findByNumberAny( $number ) : null;
			$out[]        = $owner
				? array(
					'number' => $number,
					'status' => 'taken',
					'owner'  => '' !== (string) $owner->room_type_name ? (string) $owner->room_type_name : __( 'another room type', 'radius-hotel-booking' ),
				)
				: array(
					'number' => $number,
					'status' => 'new',
					'owner'  => '',
				);
		}

		$create = count( array_filter( $out, static fn( $item ) => 'new' === $item['status'] ) );
		return array(
			'numbers' => $out,
			'create'  => $create,
			'skip'    => count( $out ) - $create,
		);
	}

	/**
	 * Rename a room or put it on another floor. Moving it to another room type
	 * is a separate action (move, T5b).
	 *
	 * @param int   $id    Id.
	 * @param array $input `{ number?, floor_id? }`.
	 * @return Room
	 */
	public function update( int $id, array $input ): Room {
		$room   = $this->get( $id );
		$before = array(
			'number'   => (string) $room->number,
			'floor_id' => (int) $room->floor_id,
		);
		$data   = array();

		if ( array_key_exists( 'number', $input ) ) {
			$number = $this->validNumber( $input['number'], $id );
			if ( $number !== $before['number'] ) {
				$data['number']      = $number;
				$data['number_sort'] = NaturalSort::key( $number );
			}
		}
		if ( array_key_exists( 'floor_id', $input ) && absint( $input['floor_id'] ) !== $before['floor_id'] ) {
			$data['floor_id'] = absint( $input['floor_id'] );
		}
		if ( ! $data ) {
			return $room;
		}

		Transaction::run(
			function () use ( $id, $data ) {
				$this->lockRoom( $id );
				if ( isset( $data['floor_id'] ) ) {
					$this->lockFloor( $data['floor_id'] );
				}
				if ( isset( $data['number'] ) ) {
					$this->assertNumberFree( $data['number'], $id );
				}
				$this->saveOrFail( $id, $data );
			}
		);
		$room  = $this->get( $id );
		$after = array_intersect_key(
			array(
				'number'   => (string) $room->number,
				'floor_id' => (int) $room->floor_id,
			),
			$data
		);

		rtbp_activity(
			'rooms.update',
			$room,
			array(
				'before'      => array_intersect_key( $before, $after ),
				'after'       => $after,
				/* translators: %s: room number. */
				'description' => sprintf( __( 'Changed room %s', 'radius-hotel-booking' ), $room->number ),
			)
		);

		return $room;
	}

	/**
	 * Set a room's state (6.6). Rooms that are not `available` are not sold.
	 *
	 * @param int   $id    Id.
	 * @param array $input `{ state, note? }`.
	 * @return Room
	 */
	public function setState( int $id, array $input ): Room {
		$room   = $this->get( $id );
		$before = array(
			'state'      => (string) $room->state,
			'state_note' => (string) $room->state_note,
		);
		if ( ! array_key_exists( 'state', $input ) ) {
			$input['state'] = '';
		}
		list( $state, $note ) = $this->validState( $input, $before['state'], $before['state_note'] );
		$after = array(
			'state'      => $state,
			'state_note' => $note,
		);
		if ( $after === $before ) {
			return $room;
		}

		$this->rooms->update( $id, $after );
		$room = $this->get( $id );

		rtbp_activity(
			'rooms.state',
			$room,
			array(
				'before'      => $before,
				'after'       => $after,
				'description' => sprintf(
					/* translators: 1: room number, 2: new state label. */
					__( 'Set room %1$s to %2$s', 'radius-hotel-booking' ),
					$room->number,
					self::stateLabel( $state )
				),
			)
		);

		/**
		 * Fires after a room's state changed.
		 *
		 * @param Room   $room   Room.
		 * @param string $old    Previous state.
		 * @param string $state  New state.
		 */
		do_action( 'rtbp_room_state_changed', $room, $before['state'], $state );

		return $room;
	}

	/**
	 * Move a room to another room type (6.11). Refused while a guest is in the
	 * room. Its upcoming bookings keep their lines (and their snapshot type);
	 * the new type sees the room as busy for those windows (booking-engine
	 * case 23). Without `confirm` nothing changes: the answer lists the
	 * upcoming bookings to warn about. With `confirm`, the client sends the
	 * `booking_ids` it showed (and their count as `bookings`); under the room
	 * lock, any upcoming booking not among them — or a higher count — is
	 * refused with `move_needs_confirm` and the fresh list, so a booking made
	 * after the warning is never moved silently.
	 *
	 * @param int   $id    Room id.
	 * @param array $input `{ room_type_id, confirm?, booking_ids?, bookings? }`.
	 * @return array{ moved: bool, room: array, from: array, to: array, count: int, bookings: array[] }
	 * @throws DomainException When the move is refused.
	 */
	public function move( int $id, array $input ): array {
		$room    = $this->get( $id );
		$from_id = (int) $room->room_type_id;
		$to_id   = absint( $input['room_type_id'] ?? 0 );
		$to      = $to_id ? $this->types->find( $to_id ) : null;
		if ( ! $to ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'room_type_id' => __( 'Choose the room type to move the room to.', 'radius-hotel-booking' ) ) );
		}
		if ( $to_id === $from_id ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'room_type_id' => __( 'The room already belongs to this room type.', 'radius-hotel-booking' ) ) );
		}

		$this->assertNotInUse( $room );
		$bookings = $this->futureBookings( (int) $room->id );
		$from     = $this->types->find( $from_id );
		$answer   = array(
			'moved'    => false,
			'room'     => self::shape( $room ),
			'from'     => array(
				'id'   => $from_id,
				'name' => (string) ( $from->name ?? '' ),
			),
			'to'       => array(
				'id'   => $to_id,
				'name' => (string) $to->name,
			),
			'count'    => $bookings['count'],
			'bookings' => $bookings['list'],
		);

		if ( ! rest_sanitize_boolean( $input['confirm'] ?? false ) ) {
			return $answer;
		}

		$shown_ids   = array_map( 'intval', (array) ( $input['booking_ids'] ?? array() ) );
		$shown_count = absint( $input['bookings'] ?? 0 );

		$bookings = Transaction::run(
			function () use ( $id, $from_id, $to_id, $shown_ids, $shown_count ) {
				// The room row is locked like a booking write locks it, so no
				// check-in or booking can land between these checks and the move.
				$this->lockRoom( $id );
				$room = $this->get( $id );
				if ( (int) $room->room_type_id !== $from_id ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'room_changed', __( 'This room was just moved by someone else. Reload and try again.', 'radius-hotel-booking' ) );
				}
				$this->assertNotInUse( $room );

				// Every upcoming booking must be one the user was shown: a new
				// one (even if another was cancelled, same count) asks again.
				$current = $this->futureBookings( $id );
				$unseen  = array_filter( $current['list'], static fn( $b ) => ! in_array( (int) $b['id'], $shown_ids, true ) );
				if ( $unseen || $current['count'] > max( $shown_count, count( $shown_ids ) ) ) {
					// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict(
						'move_needs_confirm',
						sprintf(
							/* translators: 1: room number, 2: number of bookings. */
							_n( 'Room %1$s has %2$d upcoming booking. Check it, then confirm the move again.', 'Room %1$s has %2$d upcoming bookings. Check them, then confirm the move again.', $current['count'], 'radius-hotel-booking' ),
							$room->number,
							$current['count']
						),
						array(
							'count'    => $current['count'],
							'bookings' => $current['list'],
						)
					);
					// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}

				// The target type cannot be deleted while the room moves in.
				if ( ! $this->types->lock( $to_id ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::invalid( array( 'room_type_id' => __( 'Choose the room type to move the room to.', 'radius-hotel-booking' ) ) );
				}
				$this->saveOrFail( $id, array( 'room_type_id' => $to_id ) );
				return $current;
			}
		);
		$answer['count']    = $bookings['count'];
		$answer['bookings'] = $bookings['list'];
		$room = $this->get( $id );

		rtbp_activity(
			'rooms.move',
			$room,
			array(
				'before'      => array(
					'room_type_id' => $from_id,
					'room_type'    => $answer['from']['name'],
				),
				'after'       => array(
					'room_type_id'    => $to_id,
					'room_type'       => $answer['to']['name'],
					'future_bookings' => $bookings['count'],
				),
				'description' => sprintf(
					/* translators: 1: room number, 2: old room type, 3: new room type. */
					__( 'Moved room %1$s from %2$s to %3$s', 'radius-hotel-booking' ),
					$room->number,
					$answer['from']['name'],
					$answer['to']['name']
				),
			)
		);

		/**
		 * Fires after a room moved to another room type.
		 *
		 * @param Room $room     Room (already on the new type).
		 * @param int  $from_id  Previous room type id.
		 * @param int  $to_id    New room type id.
		 * @param int  $upcoming Upcoming bookings of the room.
		 */
		do_action( 'rtbp_room_moved', $room, $from_id, $to_id, $bookings['count'] );

		return array(
			'moved' => true,
			'room'  => self::shape( $room ),
		) + $answer;
	}

	/**
	 * Refuse while a guest is in the room.
	 *
	 * @param Room $room Room.
	 * @return void
	 * @throws DomainException 409 when occupied.
	 */
	private function assertNotInUse( Room $room ): void {
		/**
		 * Whether a guest is in the room right now (checked in, not out).
		 * The bookings module fills it; an occupied room is not moved.
		 *
		 * @param bool $in_use  False.
		 * @param int  $room_id Room id.
		 */
		if ( apply_filters( 'rtbp_room_in_use', false, (int) $room->id ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict(
				'room_in_use',
				/* translators: %s: room number. */
				sprintf( __( 'Room %s is occupied. Move it after the guest checks out.', 'radius-hotel-booking' ), $room->number )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * The room's upcoming bookings, from the bookings module (M02).
	 *
	 * @param int $room_id Room id.
	 * @return array{ count: int, list: array[] }
	 */
	private function futureBookings( int $room_id ): array {
		/**
		 * The room's bookings that have not ended, for the move warning:
		 * `[ { id, reference, start, end, guest } ]`. The bookings module fills it.
		 *
		 * @param array $list    Bookings ([]).
		 * @param int   $room_id Room id.
		 */
		$list = array_values( array_filter( (array) apply_filters( 'rtbp_room_future_booking_list', array(), $room_id ), 'is_array' ) );
		$list = array_map(
			static fn( array $item ) => array(
				'id'        => (int) ( $item['id'] ?? 0 ),
				'reference' => (string) ( $item['reference'] ?? '' ),
				'start'     => (string) ( $item['start'] ?? '' ),
				'end'       => (string) ( $item['end'] ?? '' ),
				'guest'     => (string) ( $item['guest'] ?? '' ),
			),
			$list
		);

		/**
		 * The number of bookings of this room that have not ended yet. The
		 * bookings module (M02) fills it; a room with any is not removed, and
		 * moving it needs confirmation.
		 *
		 * @param int $count   Future bookings (0).
		 * @param int $room_id Room id.
		 */
		$count = max( count( $list ), (int) apply_filters( 'rtbp_room_future_bookings', 0, $room_id ) );

		return array(
			'count' => $count,
			'list'  => $list,
		);
	}

	/**
	 * Remove a room. Refused while it has future bookings; otherwise it is
	 * soft-deleted: past bookings keep it and it keeps its number.
	 *
	 * @param int $id Id.
	 * @return void
	 * @throws DomainException When it has future bookings.
	 */
	public function delete( int $id ): void {
		$room = $this->get( $id );

		Transaction::run(
			function () use ( $room, $id ) {
				// Locked like a booking write locks it: a booking cannot land
				// between the count and the removal.
				$this->lockRoom( $id );
				$future = $this->futureBookings( $id )['count'];
				if ( $future > 0 ) {
					// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict(
						'room_has_future_bookings',
						sprintf(
							/* translators: 1: room number, 2: number of bookings. */
							_n( 'Room %1$s has %2$d upcoming booking. Move or cancel it first.', 'Room %1$s has %2$d upcoming bookings. Move or cancel them first.', $future, 'radius-hotel-booking' ),
							$room->number,
							$future
						),
						array( 'bookings' => $future )
					);
					// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				if ( ! $room->delete() ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw new DomainException( 'room_not_saved', __( 'The room could not be removed. Please try again.', 'radius-hotel-booking' ), 500 );
				}
			}
		);

		rtbp_activity(
			'rooms.delete',
			$room,
			array(
				'before'      => array(
					'number'       => (string) $room->number,
					'room_type_id' => (int) $room->room_type_id,
					'floor_id'     => (int) $room->floor_id,
					'state'        => (string) $room->state,
				),
				/* translators: %s: room number. */
				'description' => sprintf( __( 'Removed room %s', 'radius-hotel-booking' ), $room->number ),
			)
		);

		/**
		 * Fires after a room is removed.
		 *
		 * @param Room $room Room.
		 */
		do_action( 'rtbp_room_deleted', $room );
	}

	/**
	 * A room for the API.
	 *
	 * @param Room $room Room.
	 * @return array
	 */
	public static function shape( Room $room ): array {
		return array(
			'id'           => (int) $room->id,
			'room_type_id' => (int) $room->room_type_id,
			'floor_id'     => (int) $room->floor_id,
			'number'       => (string) $room->number,
			'state'        => (string) $room->state,
			'state_note'   => (string) $room->state_note,
		);
	}

	/**
	 * Lock a live room row; 404 when it is gone (removed meanwhile).
	 *
	 * @param int $id Room id.
	 * @return void
	 * @throws DomainException When missing.
	 */
	private function lockRoom( int $id ): void {
		if ( ! $this->rooms->lock( $id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This room does not exist.', 'radius-hotel-booking' ) );
		}
	}

	/**
	 * Write the room, or fail loudly. The UNIQUE index on `number` is the
	 * last line of defence: when it refuses a rename, say which room owns the
	 * number instead of reporting success (throwing rolls the transaction back).
	 *
	 * @param int   $id   Room id.
	 * @param array $data Columns.
	 * @return void
	 * @throws DomainException When nothing was written.
	 */
	private function saveOrFail( int $id, array $data ): void {
		if ( Db::quietly( fn() => $this->rooms->update( $id, $data ) ) ) {
			return;
		}
		if ( isset( $data['number'] ) && Db::duplicateKey() ) {
			// Taken by a write that committed after this transaction's
			// snapshot, so assertNumberFree() cannot see it: say so plainly.
			$message = sprintf(
				/* translators: %s: room number. */
				__( 'Room %s was just added by someone else. Choose another number.', 'radius-hotel-booking' ),
				$data['number']
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'room_number_taken', $message, 409, array( 'number' => $message ) );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
		throw new DomainException( 'room_not_saved', __( 'The room could not be saved. Please try again.', 'radius-hotel-booking' ), 500 );
	}

	/**
	 * Lock the room type and the floor a new room goes on; both must exist.
	 *
	 * @param int $type_id Room type id.
	 * @param int $floor   Floor id.
	 * @return void
	 * @throws DomainException When one is missing.
	 */
	private function lockParents( int $type_id, int $floor ): void {
		if ( ! $type_id || ! $this->types->lock( $type_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'room_type_id' => __( 'Choose a room type.', 'radius-hotel-booking' ) ) );
		}
		$this->lockFloor( $floor );
	}

	/**
	 * Lock a floor; it must exist.
	 *
	 * @param int $floor Floor id.
	 * @return void
	 * @throws DomainException When missing.
	 */
	private function lockFloor( int $floor ): void {
		if ( ! $floor || ! $this->floors->lock( $floor ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'floor_id' => __( 'Choose a floor.', 'radius-hotel-booking' ) ) );
		}
	}

	/**
	 * A clean room number: letters, digits, spaces and `- / .`, up to 20.
	 *
	 * @param mixed $raw        Input.
	 * @param int   $except_id  The room being renamed.
	 * @return string
	 * @throws DomainException With the field error.
	 */
	private function validNumber( $raw, int $except_id ): string {
		$number = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $raw ) ) );
		if ( '' === $number || mb_strlen( $number ) > 20 || ! preg_match( '/^[\p{L}\p{N}][\p{L}\p{N} \-\/.]*$/u', $number ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid(
				array( 'number' => __( 'Use up to 20 letters, digits, spaces, dashes, slashes or dots.', 'radius-hotel-booking' ) )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$this->assertNumberFree( $number, $except_id );
		return $number;
	}

	/**
	 * Refuse a number another room has (removed rooms included; the column's
	 * collation ignores case, so "a3" clashes with "A3").
	 *
	 * @param string $number    Number.
	 * @param int    $except_id The room being renamed.
	 * @return void
	 * @throws DomainException 409 when taken.
	 */
	private function assertNumberFree( string $number, int $except_id ): void {
		$owner = $this->rooms->findByNumberAny( $number );
		if ( ! $owner || (int) $owner->id === $except_id ) {
			return;
		}
		// The stored spelling ("A3"), not what was typed ("a3").
		$number  = (string) $owner->number;
		$type    = '' !== (string) $owner->room_type_name ? (string) $owner->room_type_name : __( 'another room type', 'radius-hotel-booking' );
		$message = $owner->deleted_at
			/* translators: 1: room number, 2: room type name. */
			? sprintf( __( 'Room %1$s was used by a removed room of %2$s. Numbers are never reused.', 'radius-hotel-booking' ), $number, $type )
			/* translators: 1: room number, 2: room type name. */
			: sprintf( __( 'Room %1$s already belongs to %2$s.', 'radius-hotel-booking' ), $number, $type );
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
		throw new DomainException( 'room_number_taken', $message, 409, array( 'number' => $message ) );
	}

	/**
	 * A valid state and note from the input.
	 *
	 * @param array  $input        `{ state?, note?|state_note? }`.
	 * @param string $default      State when none is given.
	 * @param string $default_note Note when none is given.
	 * @return array{0: string, 1: string}
	 * @throws DomainException With field errors.
	 */
	private function validState( array $input, string $default, string $default_note ): array {
		$state = array_key_exists( 'state', $input ) ? sanitize_key( (string) $input['state'] ) : $default;
		if ( ! in_array( $state, Room::states(), true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'state' => __( 'Choose Available, Maintenance or Out of service.', 'radius-hotel-booking' ) ) );
		}
		$note = $input['note'] ?? $input['state_note'] ?? $default_note;
		$note = mb_substr( sanitize_text_field( (string) $note ), 0, 191 );
		// An available room carries no reason.
		return array( $state, Room::AVAILABLE === $state ? '' : $note );
	}

	/**
	 * A state's label for log descriptions.
	 *
	 * @param string $state State.
	 * @return string
	 */
	private static function stateLabel( string $state ): string {
		$labels = array(
			Room::AVAILABLE      => __( 'Available', 'radius-hotel-booking' ),
			Room::MAINTENANCE    => __( 'Maintenance', 'radius-hotel-booking' ),
			Room::OUT_OF_SERVICE => __( 'Out of service', 'radius-hotel-booking' ),
		);
		return $labels[ $state ] ?? $state;
	}
}
