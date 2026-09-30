<?php
/**
 * Blocked dates (feature 8.12).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Availability
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Availability;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Block;
use RadiusTheme\RadiusHotelBooking\Repositories\AvailabilityRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BlockRepository;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * A block closes the property, a floor, a room type or one room for
 * `[start_at, end_at)`: nothing in scope can be sold then (booking-engine §4;
 * no cleaning buffer around it). Bookings already inside are kept; the
 * response counts them so staff can move them.
 *
 * Staff create `manual` blocks. Other sources (Pro's iCal import) register
 * through `rtbp_block_sources`; their blocks are shown but read-only here,
 * since the next sync would undo an edit. When a source is no longer
 * registered, its blocks can still be removed (never edited).
 */
class BlockService {

	/**
	 * How far ahead a block may end, in days.
	 */
	public const MAX_DAYS_AHEAD = 1100;

	/**
	 * Blocks.
	 *
	 * @var BlockRepository
	 */
	private BlockRepository $blocks;

	/**
	 * Constructor.
	 *
	 * @param BlockRepository|null $blocks Blocks.
	 */
	public function __construct( ?BlockRepository $blocks = null ) {
		$this->blocks = $blocks ?? new BlockRepository();
	}

	/**
	 * Where blocks come from: `key => { label, editable }`.
	 *
	 * @return array<string, array{label: string, editable: bool}>
	 */
	public static function sources(): array {
		/**
		 * Block sources. An add-on that creates blocks (an iCal import) adds
		 * its key here, with `editable` false so staff edit it at the source.
		 *
		 * @param array $sources `key => { label, editable }`.
		 */
		$sources = (array) apply_filters(
			'rtbp_block_sources',
			array(
				'manual' => array(
					'label'    => __( 'Staff', 'radius-hotel-booking' ),
					'editable' => true,
				),
			)
		);
		$out = array();
		foreach ( $sources as $key => $source ) {
			$out[ sanitize_key( (string) $key ) ] = array(
				'label'    => (string) ( $source['label'] ?? $key ),
				'editable' => ! empty( $source['editable'] ),
			);
		}
		return $out;
	}

	/**
	 * A page of blocks.
	 *
	 * @param array                  $filters `{ when: upcoming|past|all, scope? }`.
	 * @param int                    $page    Page.
	 * @param int                    $per     Per page (1–100).
	 * @param DateTimeImmutable|null $now     Now.
	 * @return array{items: array[], total: int}
	 */
	public function page( array $filters, int $page = 1, int $per = 20, ?DateTimeImmutable $now = null ): array {
		$when  = in_array( $filters['when'] ?? '', array( 'upcoming', 'past', 'all' ), true ) ? $filters['when'] : 'upcoming';
		$scope = in_array( $filters['scope'] ?? '', Block::SCOPES, true ) ? $filters['scope'] : '';
		$data  = $this->blocks->page(
			array(
				'when'  => $when,
				'scope' => $scope,
			),
			Dates::to_gmt_db( $now ?? Dates::now() ),
			max( 1, $page ),
			min( 100, max( 1, $per ) )
		);
		return array(
			'items' => array_map( array( self::class, 'shape' ), $data['items'] ),
			'total' => $data['total'],
		);
	}

	/**
	 * One block, shaped, or 404.
	 *
	 * @param int $id Id.
	 * @return array
	 */
	public function get( int $id ): array {
		return self::shape( $this->row( $id ) );
	}

	/**
	 * Create a staff block.
	 *
	 * @param array                  $input `{ scope, scope_id, start_at, end_at (local Y-m-d H:i), reason }`.
	 * @param DateTimeImmutable|null $now   Now.
	 * @return array `{ block, bookings_inside }`.
	 */
	public function create( array $input, ?DateTimeImmutable $now = null ): array {
		$data = $this->validate( $input, $now ?? Dates::now() );

		$id = Transaction::run(
			function () use ( $data ) {
				$this->lockScope( $data['scope'], (int) $data['scope_id'] );
				$block = $this->blocks->create(
					array_merge(
						$data,
						array(
							'source'     => 'manual',
							'created_by' => get_current_user_id() ? get_current_user_id() : null,
						)
					)
				);
				$row = $this->row( (int) $block->id );
				rtbp_activity(
					'blocks.create',
					self::subject( $row ),
					array(
						'after'       => self::logged( $row ),
						/* translators: 1: what is closed, 2: from, 3: until. */
						'description' => sprintf( __( 'Blocked %1$s from %2$s until %3$s', 'radius-hotel-booking' ), self::targetLabel( $row ), self::when( $row['start_at'] ), self::when( $row['end_at'] ) ),
					)
				);
				return (int) $block->id;
			}
		);

		$this->changed( $id, 'create' );
		return $this->withInside( $this->row( $id ) );
	}

	/**
	 * Change a staff block.
	 *
	 * @param int                    $id    Id.
	 * @param array                  $input As create().
	 * @param DateTimeImmutable|null $now   Now.
	 * @return array `{ block, bookings_inside }`.
	 */
	public function update( int $id, array $input, ?DateTimeImmutable $now = null ): array {
		$before = $this->editable( $this->row( $id ) );
		$data   = $this->validate( $input, $now ?? Dates::now() );

		$changed = Transaction::run(
			function () use ( $id, $data, $before ) {
				$this->lockScope( $data['scope'], (int) $data['scope_id'] );
				$old = array_intersect_key( $before, $data );
				$new = $data;
				ksort( $old );
				ksort( $new );
				if ( array_map( 'strval', $old ) === array_map( 'strval', $new ) ) {
					return false;
				}
				$this->blocks->update( $id, $data );
				$after = $this->row( $id );
				rtbp_activity(
					'blocks.update',
					self::subject( $after ),
					array(
						'before'      => self::logged( $before ),
						'after'       => self::logged( $after ),
						/* translators: 1: what is closed, 2: from, 3: until. */
						'description' => sprintf( __( 'Changed the block on %1$s: now from %2$s until %3$s', 'radius-hotel-booking' ), self::targetLabel( $after ), self::when( $after['start_at'] ), self::when( $after['end_at'] ) ),
					)
				);
				return true;
			}
		);

		if ( $changed ) {
			$this->changed( $id, 'update' );
		}
		return $this->withInside( $this->row( $id ) );
	}

	/**
	 * Delete a staff block.
	 *
	 * @param int $id Id.
	 * @return void
	 */
	public function delete( int $id ): void {
		$row = $this->row( $id );
		if ( ! self::removable( $row ) ) {
			$this->editable( $row ); // Throws 409 with the source's name.
		}
		Transaction::run(
			function () use ( $id, $row ) {
				$this->blocks->delete( $id );
				rtbp_activity(
					'blocks.delete',
					self::subject( $row ),
					array(
						'before'      => self::logged( $row ),
						/* translators: 1: what was closed, 2: from, 3: until. */
						'description' => sprintf( __( 'Removed the block on %1$s from %2$s until %3$s', 'radius-hotel-booking' ), self::targetLabel( $row ), self::when( $row['start_at'] ), self::when( $row['end_at'] ) ),
					)
				);
			}
		);
		$this->changed( $id, 'delete' );
	}

	/**
	 * Blocks by id, shaped (the calendar overlay).
	 *
	 * @param int[] $ids Ids.
	 * @return array[]
	 */
	public function many( array $ids ): array {
		return array_values( array_map( array( self::class, 'shape' ), $this->blocks->labelled( $ids ) ) );
	}

	/**
	 * A block row for the API.
	 *
	 * @param array $row Row with labels.
	 * @return array
	 */
	public static function shape( array $row ): array {
		$sources = self::sources();
		$source  = (string) $row['source'];
		$user    = empty( $row['created_by'] ) ? null : get_userdata( (int) $row['created_by'] );
		$start   = substr( (string) $row['start_at'], 0, 16 );
		$end     = substr( (string) $row['end_at'], 0, 16 );
		return array(
			'id'             => (int) $row['id'],
			'scope'          => (string) $row['scope'],
			'scope_id'       => (int) $row['scope_id'],
			'target'         => (string) ( $row['target'] ?? '' ),
			'room_type_name' => (string) ( $row['room_type_name'] ?? '' ),
			// The room's type, for a room block (0 otherwise).
			'room_type_id'   => (int) ( $row['room_type_id'] ?? 0 ),
			'label'          => self::targetLabel( $row ),
			'start_at'       => $start,
			'end_at'         => $end,
			// Midnight to midnight: shown as dates rather than times.
			'whole_days'     => '00:00' === substr( $start, 11 ) && '00:00' === substr( $end, 11 ),
			'source'         => $source,
			'source_label'   => $sources[ $source ]['label'] ?? $source,
			'editable'       => $sources[ $source ]['editable'] ?? false,
			'removable'      => self::removable( $row ),
			'reason'         => (string) $row['reason'],
			'created_by'     => $user ? array(
				'id'   => (int) $user->ID,
				'name' => (string) $user->display_name,
			) : null,
			'created_at'     => (string) $row['created_at'],
		);
	}

	/**
	 * Whether staff may delete a block: their own, or one whose source is no
	 * longer registered (its add-on or feature is off, so nothing will ever
	 * reconcile it again and the room would stay closed for good). A block of
	 * a registered read-only source is changed at that source.
	 *
	 * @param array $row Row.
	 * @return bool
	 */
	public static function removable( array $row ): bool {
		$sources = self::sources();
		$source  = (string) $row['source'];
		return ! isset( $sources[ $source ] ) || ! empty( $sources[ $source ]['editable'] );
	}

	/**
	 * What a block closes, for people: "Whole property", "Floor: Ground",
	 * "Standard Room", "Room A1 (Standard Room)".
	 *
	 * @param array $row Row with labels.
	 * @return string
	 */
	public static function targetLabel( array $row ): string {
		$target = (string) ( $row['target'] ?? '' );
		switch ( $row['scope'] ) {
			case 'property':
				return __( 'Whole property', 'radius-hotel-booking' );
			case 'floor':
				/* translators: %s: floor name. */
				return sprintf( __( 'Floor: %s', 'radius-hotel-booking' ), $target );
			case 'room':
				return '' !== (string) ( $row['room_type_name'] ?? '' )
					/* translators: 1: room number, 2: room type. */
					? sprintf( __( 'Room %1$s (%2$s)', 'radius-hotel-booking' ), $target, $row['room_type_name'] )
					/* translators: %s: room number. */
					: sprintf( __( 'Room %s', 'radius-hotel-booking' ), $target );
			default:
				return $target;
		}
	}

	/**
	 * Validated columns from the input.
	 *
	 * @param array             $input Input.
	 * @param DateTimeImmutable $now   Now.
	 * @return array Columns: scope, scope_id, start/end (local and GMT), reason.
	 * @throws DomainException 422.
	 */
	private function validate( array $input, DateTimeImmutable $now ): array {
		$errors   = array();
		$scope    = (string) ( $input['scope'] ?? '' );
		$scope_id = 'property' === $scope ? 0 : absint( $input['scope_id'] ?? 0 );
		if ( ! in_array( $scope, Block::SCOPES, true ) ) {
			$errors['scope'] = __( 'Choose what to block.', 'radius-hotel-booking' );
		} elseif ( ! $this->blocks->targetExists( $scope, $scope_id ) ) {
			$errors['scope_id'] = array(
				'floor'     => __( 'Choose a floor.', 'radius-hotel-booking' ),
				'room_type' => __( 'Choose a room type.', 'radius-hotel-booking' ),
				'room'      => __( 'Choose a room.', 'radius-hotel-booking' ),
			)[ $scope ];
		}

		$start = self::moment( (string) ( $input['start_at'] ?? '' ) );
		$end   = self::moment( (string) ( $input['end_at'] ?? '' ) );
		if ( ! $start ) {
			$errors['start_at'] = __( 'Choose when the block starts.', 'radius-hotel-booking' );
		}
		if ( ! $end ) {
			$errors['end_at'] = __( 'Choose when the block ends.', 'radius-hotel-booking' );
		} elseif ( $start && $end <= $start ) {
			$errors['end_at'] = __( 'The end must be after the start.', 'radius-hotel-booking' );
		} elseif ( $end <= $now ) {
			$errors['end_at'] = __( 'The block must end in the future.', 'radius-hotel-booking' );
		} elseif ( $end > Dates::add_days( $now, self::MAX_DAYS_AHEAD ) ) {
			$errors['end_at'] = __( 'This date is too far ahead.', 'radius-hotel-booking' );
		}

		$reason = sanitize_text_field( (string) ( $input['reason'] ?? '' ) );
		if ( '' === $reason || mb_strlen( $reason ) > 191 ) {
			$errors['reason'] = __( 'Give a reason of up to 191 characters.', 'radius-hotel-booking' );
		}

		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}

		return array(
			'scope'        => $scope,
			'scope_id'     => $scope_id,
			'start_at'     => Dates::to_db( $start ),
			'end_at'       => Dates::to_db( $end ),
			'start_at_gmt' => Dates::to_gmt_db( $start ),
			'end_at_gmt'   => Dates::to_gmt_db( $end ),
			'reason'       => $reason,
		);
	}

	/**
	 * A local `Y-m-d H:i` (or `Y-m-d`, meaning midnight), or null.
	 *
	 * @param string $value Value.
	 * @return DateTimeImmutable|null
	 */
	private static function moment( string $value ): ?DateTimeImmutable {
		$value = trim( $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $value ) ) {
			return null;
		}
		try {
			$moment = Dates::local( $value );
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}
		// A time a clock change skips is moved by PHP; refuse it rather than guess.
		return substr( Dates::to_db( $moment ), 0, strlen( $value ) ) === $value ? $moment : null;
	}

	/**
	 * Lock the rooms a block closes, ascending, like the booking write path
	 * (§7.1): a stay being written at the same moment either commits first
	 * (and is counted as inside the block) or waits and then sees the block.
	 *
	 * @param string $scope    Scope.
	 * @param int    $scope_id Scope id.
	 * @return void
	 */
	private function lockScope( string $scope, int $scope_id ): void {
		( new AvailabilityRepository() )->lockRooms( $this->blocks->roomIdsInScope( $scope, $scope_id ) );
	}

	/**
	 * A labelled row, or 404.
	 *
	 * @param int $id Id.
	 * @return array
	 * @throws DomainException 404.
	 */
	private function row( int $id ): array {
		$row = $this->blocks->labelled( array( $id ) )[ $id ] ?? null;
		if ( ! $row ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This block does not exist.', 'radius-hotel-booking' ) );
		}
		return $row;
	}

	/**
	 * The row, if staff may change it here.
	 *
	 * @param array $row Row.
	 * @return array
	 * @throws DomainException 409 `block_read_only`.
	 */
	private function editable( array $row ): array {
		$sources = self::sources();
		if ( empty( $sources[ $row['source'] ]['editable'] ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict(
				'block_read_only',
				/* translators: %s: where the block comes from, e.g. "Calendar sync". */
				sprintf( __( 'This block comes from %s. Change it there.', 'radius-hotel-booking' ), $sources[ $row['source'] ]['label'] ?? $row['source'] )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $row;
	}

	/**
	 * The shaped block and how many booking lines are already inside it.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private function withInside( array $row ): array {
		return array(
			'block'           => self::shape( $row ),
			'bookings_inside' => $this->blocks->linesInside( (string) $row['scope'], (int) $row['scope_id'], (string) $row['start_at_gmt'], (string) $row['end_at_gmt'] ),
		);
	}

	/**
	 * The activity subject.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function subject( array $row ): array {
		return array(
			'type'  => 'block',
			'id'    => (int) $row['id'],
			'label' => self::targetLabel( $row ),
		);
	}

	/**
	 * The fields an activity entry records.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function logged( array $row ): array {
		return array(
			'scope'    => (string) $row['scope'],
			'scope_id' => (int) $row['scope_id'],
			'start_at' => (string) $row['start_at'],
			'end_at'   => (string) $row['end_at'],
			'reason'   => (string) $row['reason'],
		);
	}

	/**
	 * A local date-time for a description.
	 *
	 * @param string $local Local `Y-m-d H:i:s`.
	 * @return string
	 */
	private static function when( string $local ): string {
		return Dates::format( Dates::local( substr( $local, 0, 16 ) ), 'datetime' );
	}

	/**
	 * Fire the change hook.
	 *
	 * @param int    $id     Block id.
	 * @param string $change create|update|delete.
	 * @return void
	 */
	private function changed( int $id, string $change ): void {
		/**
		 * A staff block was created, changed or deleted.
		 *
		 * @param int    $id     Block id.
		 * @param string $change `create`, `update` or `delete`.
		 */
		do_action( 'rtbp_block_changed', $id, $change );
	}
}
