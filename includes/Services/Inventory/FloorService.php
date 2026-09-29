<?php
/**
 * Floors: create, rename, reorder, delete.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Inventory
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Inventory;

use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Floor;
use RadiusTheme\RadiusHotelBooking\Repositories\FloorRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The hotel's ordered list of named floors (feature 6.3). Any number of
 * floors; names are unique (ignoring case). A floor that holds rooms —
 * removed rooms included, their history names the floor — cannot be deleted.
 */
class FloorService {

	/**
	 * Floors.
	 *
	 * @var FloorRepository
	 */
	private FloorRepository $floors;

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

	/**
	 * Add a floor at the end of the list.
	 *
	 * @param array $input `{ name }`.
	 * @return Floor
	 */
	public function create( array $input ): Floor {
		$name  = $this->validName( $input, 0 );
		$floor = $this->floors->create(
			array(
				'name'       => $name,
				'sort_order' => $this->floors->nextSortOrder(),
			)
		);

		rtbp_activity(
			'floors.create',
			$floor,
			array(
				'after'       => array( 'name' => $name ),
				/* translators: %s: floor name. */
				'description' => sprintf( __( 'Added the floor %s', 'radius-hotel-booking' ), $name ),
			)
		);

		return $floor;
	}

	/**
	 * Rename a floor.
	 *
	 * @param int   $id    Id.
	 * @param array $input `{ name }`.
	 * @return Floor
	 */
	public function update( int $id, array $input ): Floor {
		$floor = $this->get( $id );
		$old   = (string) $floor->name;
		$name  = $this->validName( $input, $id );
		if ( $name === $old ) {
			return $floor;
		}

		$this->floors->update( $id, array( 'name' => $name ) );
		$floor = $this->get( $id );

		rtbp_activity(
			'floors.update',
			$floor,
			array(
				'before'      => array( 'name' => $old ),
				'after'       => array( 'name' => $name ),
				/* translators: 1: old floor name, 2: new floor name. */
				'description' => sprintf( __( 'Renamed the floor %1$s to %2$s', 'radius-hotel-booking' ), $old, $name ),
			)
		);

		return $floor;
	}

	/**
	 * Put the floors in a new order. `ids` must list every floor once.
	 *
	 * @param array $ids Floor ids, first to last.
	 * @return array[] The floors, as all() returns them.
	 * @throws DomainException When the list is not exactly the floors.
	 */
	public function reorder( array $ids ): array {
		$ids     = array_map( 'intval', array_values( $ids ) );
		$current = $this->floors->ordered();
		$known   = array_map( static fn( Floor $floor ) => (int) $floor->id, $current );

		$sorted_ids   = $ids;
		$sorted_known = $known;
		sort( $sorted_ids );
		sort( $sorted_known );
		if ( $sorted_ids !== $sorted_known ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid(
				array( 'ids' => __( 'The list of floors has changed. Reload the page and try again.', 'radius-hotel-booking' ) )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( $ids === $known ) {
			return $this->all();
		}

		$names = array();
		foreach ( $current as $floor ) {
			$names[ (int) $floor->id ] = (string) $floor->name;
		}

		Transaction::run(
			function () use ( $ids ) {
				foreach ( $ids as $position => $id ) {
					$this->floors->update( $id, array( 'sort_order' => $position ) );
				}
			}
		);

		rtbp_activity(
			'floors.reorder',
			array(
				'type'  => 'floor',
				'id'    => 0,
				'label' => __( 'Floors', 'radius-hotel-booking' ),
			),
			array(
				'before'      => array( 'order' => array_values( array_map( static fn( $id ) => $names[ $id ], $known ) ) ),
				'after'       => array( 'order' => array_values( array_map( static fn( $id ) => $names[ $id ], $ids ) ) ),
				'description' => __( 'Reordered the floors', 'radius-hotel-booking' ),
			)
		);

		return $this->all();
	}

	/**
	 * Delete a floor. Refused while any room (removed ones included) is on it.
	 *
	 * @param int $id Id.
	 * @return void
	 * @throws DomainException When it holds rooms.
	 */
	public function delete( int $id ): void {
		$floor = $this->get( $id );

		Transaction::run(
			function () use ( $floor, $id ) {
				// Locked, so a room cannot be put on it between the count and the delete.
				if ( ! $this->floors->lock( $id ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::notFound( __( 'This floor does not exist.', 'radius-hotel-booking' ) );
				}
				$count = $this->floors->roomCount( $id );
				if ( $count ) {
					// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict(
						'floor_has_rooms',
						sprintf(
							/* translators: 1: floor name, 2: number of rooms. */
							_n( '%1$s still has %2$d room, so it cannot be deleted.', '%1$s still has %2$d rooms, so it cannot be deleted.', $count, 'radius-hotel-booking' ),
							$floor->name,
							$count
						),
						array( 'rooms' => $count )
					);
					// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				$this->floors->delete( $id );
			}
		);

		rtbp_activity(
			'floors.delete',
			$floor,
			array(
				'before'      => array( 'name' => (string) $floor->name ),
				/* translators: %s: floor name. */
				'description' => sprintf( __( 'Deleted the floor %s', 'radius-hotel-booking' ), $floor->name ),
			)
		);
	}

	/**
	 * A valid, unused floor name from the input.
	 *
	 * @param array $input     Input.
	 * @param int   $except_id The floor being renamed.
	 * @return string
	 * @throws DomainException With the field error.
	 */
	private function validName( array $input, int $except_id ): string {
		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( '' === $name || mb_strlen( $name ) > 100 ) {
			$error = __( 'Give the floor a name of up to 100 characters.', 'radius-hotel-booking' );
		} elseif ( $this->floors->nameTaken( $name, $except_id ) ) {
			/* translators: %s: floor name. */
			$error = sprintf( __( 'There is already a floor called %s.', 'radius-hotel-booking' ), $name );
		}
		if ( isset( $error ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'name' => $error ) );
		}
		return $name;
	}
}
