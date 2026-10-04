<?php
/**
 * Amenities: the shared library room types pick from.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Inventory
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Inventory;

use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Amenity;
use RadiusTheme\RadiusHotelBooking\Models\RoomType;
use RadiusTheme\RadiusHotelBooking\Repositories\AmenityRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;

defined( 'ABSPATH' ) || exit;

/**
 * One ordered list of amenities for the whole hotel. A room type stores the
 * names it offers; this service keeps them in step: a rename or a delete here
 * reaches every room type, and a name typed on a room type that the library
 * does not know yet is added to it. Names are unique (ignoring case).
 */
class AmenityService {

	/**
	 * Amenities.
	 *
	 * @var AmenityRepository
	 */
	private AmenityRepository $amenities;

	/**
	 * Room types.
	 *
	 * @var RoomTypeRepository
	 */
	private RoomTypeRepository $types;

	/**
	 * Constructor.
	 *
	 * @param AmenityRepository|null  $amenities Amenities.
	 * @param RoomTypeRepository|null $types     Room types.
	 */
	public function __construct( ?AmenityRepository $amenities = null, ?RoomTypeRepository $types = null ) {
		$this->amenities = $amenities ?? new AmenityRepository();
		$this->types     = $types ?? new RoomTypeRepository();
	}

	/**
	 * Every amenity in order, with the room types that offer it.
	 *
	 * @return array[] `{ id, name, sort_order, room_types: [ { id, name } ] }`.
	 */
	public function all(): array {
		$users = array();
		foreach ( $this->types->ordered() as $type ) {
			foreach ( self::listOf( $type ) as $name ) {
				$users[ mb_strtolower( $name ) ][] = array(
					'id'   => (int) $type->id,
					'name' => (string) $type->name,
				);
			}
		}
		return array_map(
			static fn( Amenity $amenity ) => array(
				'id'         => (int) $amenity->id,
				'name'       => (string) $amenity->name,
				'sort_order' => (int) $amenity->sort_order,
				'room_types' => $users[ mb_strtolower( (string) $amenity->name ) ] ?? array(),
			),
			$this->amenities->ordered()
		);
	}

	/**
	 * One amenity, or 404.
	 *
	 * @param int $id Id.
	 * @return Amenity
	 * @throws DomainException When missing.
	 */
	public function get( int $id ): Amenity {
		$amenity = $this->amenities->find( $id );
		if ( ! $amenity instanceof Amenity ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This amenity does not exist.', 'radius-hotel-booking' ) );
		}
		return $amenity;
	}

	/**
	 * Add an amenity at the end of the list.
	 *
	 * @param array $input `{ name }`.
	 * @return Amenity
	 */
	public function create( array $input ): Amenity {
		$name    = $this->validName( $input, 0 );
		$amenity = $this->insert( $name );

		rtbp_activity(
			'amenities.create',
			$amenity,
			array(
				'after'       => array( 'name' => $name ),
				/* translators: %s: amenity name. */
				'description' => sprintf( __( 'Added the amenity %s', 'radius-hotel-booking' ), $name ),
			)
		);

		return $amenity;
	}

	/**
	 * The amenities most hotels offer, grouped, for "Add common amenities".
	 * Each name carries whether the library already holds it.
	 *
	 * @return array[] `[ { label, amenities: [ { name, added } ] } ]`.
	 */
	public function common(): array {
		$groups = array(
			array(
				'label'     => __( 'Room comfort', 'radius-hotel-booking' ),
				'amenities' => array(
					__( 'Air conditioning', 'radius-hotel-booking' ),
					__( 'Ceiling fan', 'radius-hotel-booking' ),
					__( 'Wardrobe', 'radius-hotel-booking' ),
					__( 'Work desk', 'radius-hotel-booking' ),
					__( 'Seating area', 'radius-hotel-booking' ),
					__( 'Blackout curtains', 'radius-hotel-booking' ),
					__( 'Balcony', 'radius-hotel-booking' ),
				),
			),
			array(
				'label'     => __( 'Technology', 'radius-hotel-booking' ),
				'amenities' => array(
					__( 'Free Wi-Fi', 'radius-hotel-booking' ),
					__( 'Flat-screen TV', 'radius-hotel-booking' ),
					__( 'Satellite channels', 'radius-hotel-booking' ),
					__( 'Telephone', 'radius-hotel-booking' ),
					__( 'USB charging ports', 'radius-hotel-booking' ),
				),
			),
			array(
				'label'     => __( 'Bathroom', 'radius-hotel-booking' ),
				'amenities' => array(
					__( 'Private bathroom', 'radius-hotel-booking' ),
					__( 'Shower', 'radius-hotel-booking' ),
					__( 'Bathtub', 'radius-hotel-booking' ),
					__( 'Hair dryer', 'radius-hotel-booking' ),
					__( 'Free toiletries', 'radius-hotel-booking' ),
					__( 'Towels', 'radius-hotel-booking' ),
					__( 'Bathrobe', 'radius-hotel-booking' ),
					__( 'Slippers', 'radius-hotel-booking' ),
				),
			),
			array(
				'label'     => __( 'Food and drink', 'radius-hotel-booking' ),
				'amenities' => array(
					__( 'Mini bar', 'radius-hotel-booking' ),
					__( 'Refrigerator', 'radius-hotel-booking' ),
					__( 'Electric kettle', 'radius-hotel-booking' ),
					__( 'Tea and coffee maker', 'radius-hotel-booking' ),
					__( 'Bottled water', 'radius-hotel-booking' ),
					__( 'Breakfast included', 'radius-hotel-booking' ),
				),
			),
			array(
				'label'     => __( 'Services and safety', 'radius-hotel-booking' ),
				'amenities' => array(
					__( 'Room service', 'radius-hotel-booking' ),
					__( 'Daily housekeeping', 'radius-hotel-booking' ),
					__( 'Wake-up service', 'radius-hotel-booking' ),
					__( 'In-room safe', 'radius-hotel-booking' ),
					__( 'Key card access', 'radius-hotel-booking' ),
					__( 'Smoke detector', 'radius-hotel-booking' ),
					__( 'Free parking', 'radius-hotel-booking' ),
				),
			),
		);

		/**
		 * The groups offered by "Add common amenities".
		 *
		 * @param array[] $groups `[ { label, amenities: string[] } ]`.
		 */
		$groups = (array) apply_filters( 'rtbp_common_amenities', $groups );

		$library = $this->byKey();
		$out     = array();
		foreach ( $groups as $group ) {
			$names = array();
			foreach ( (array) ( $group['amenities'] ?? array() ) as $name ) {
				$name = mb_substr( sanitize_text_field( (string) $name ), 0, 60 );
				if ( '' !== $name ) {
					$names[] = array(
						'name'  => $name,
						'added' => isset( $library[ mb_strtolower( $name ) ] ),
					);
				}
			}
			if ( $names ) {
				$out[] = array(
					'label'     => (string) ( $group['label'] ?? '' ),
					'amenities' => $names,
				);
			}
		}
		return $out;
	}

	/**
	 * Add several amenities at the end of the list, in the order given.
	 * Names the library already holds (ignoring case) are skipped.
	 *
	 * @param array $names Names.
	 * @return string[] The names added.
	 * @throws DomainException When no name is given or one is too long.
	 */
	public function createMany( array $names ): array {
		$library = $this->byKey();
		$new     = array();
		foreach ( $names as $name ) {
			$name = sanitize_text_field( (string) $name );
			if ( '' === $name ) {
				continue;
			}
			if ( mb_strlen( $name ) > 60 ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::invalid( array( 'names' => __( 'Each amenity name can have up to 60 characters.', 'radius-hotel-booking' ) ) );
			}
			$key = mb_strtolower( $name );
			if ( ! isset( $library[ $key ] ) ) {
				$library[ $key ] = $name;
				$new[]           = $name;
			}
		}
		if ( ! $new ) {
			return array();
		}

		Transaction::run(
			function () use ( $new ) {
				foreach ( $new as $name ) {
					$this->insert( $name );
				}
			}
		);

		rtbp_activity(
			'amenities.import',
			array(
				'type'  => 'amenity',
				'id'    => 0,
				'label' => __( 'Amenities', 'radius-hotel-booking' ),
			),
			array(
				'after'       => array( 'names' => $new ),
				'description' => sprintf(
					/* translators: %d: number of amenities. */
					_n( 'Added %d common amenity', 'Added %d common amenities', count( $new ), 'radius-hotel-booking' ),
					count( $new )
				),
			)
		);

		return $new;
	}

	/**
	 * Rename an amenity, and every room type that offers it. Renaming it to
	 * the name of another amenity merges the two when `merge` is set (the
	 * other one is kept), and is refused with `amenity_name_taken` otherwise.
	 *
	 * @param int   $id    Id.
	 * @param array $input `{ name, merge? }`.
	 * @return Amenity The renamed amenity, or the one it was merged into.
	 * @throws DomainException When the name is taken and `merge` is not set.
	 */
	public function update( int $id, array $input ): Amenity {
		$amenity = $this->get( $id );
		$old     = (string) $amenity->name;
		$name    = $this->validName( $input, $id, true );
		if ( $name === $old ) {
			return $amenity;
		}

		$other = $this->amenities->findByName( $name, $id );
		if ( $other instanceof Amenity ) {
			if ( empty( $input['merge'] ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::conflict(
					'amenity_name_taken',
					/* translators: %s: amenity name. */
					sprintf( __( 'There is already an amenity called %s.', 'radius-hotel-booking' ), $other->name ),
					array(
						'id'   => (int) $other->id,
						'name' => (string) $other->name,
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			return $this->merge( $amenity, $other );
		}

		$changed = Transaction::run(
			function () use ( $id, $old, $name ) {
				$this->amenities->update( $id, array( 'name' => $name ) );
				return $this->rewriteRoomTypes(
					static fn( array $list ) => array_map( static fn( $item ) => self::same( $item, $old ) ? $name : $item, $list )
				);
			}
		);
		$amenity = $this->get( $id );

		rtbp_activity(
			'amenities.update',
			$amenity,
			array(
				'before'      => array( 'name' => $old ),
				'after'       => array(
					'name'       => $name,
					'room_types' => $changed,
				),
				/* translators: 1: old amenity name, 2: new amenity name. */
				'description' => sprintf( __( 'Renamed the amenity %1$s to %2$s', 'radius-hotel-booking' ), $old, $name ),
			)
		);

		return $amenity;
	}

	/**
	 * Fold one amenity into another: room types that offered the first now
	 * offer the second (once), and the first is deleted.
	 *
	 * @param Amenity $from The amenity that goes.
	 * @param Amenity $into The amenity that stays.
	 * @return Amenity The one that stays.
	 */
	private function merge( Amenity $from, Amenity $into ): Amenity {
		$old  = (string) $from->name;
		$name = (string) $into->name;

		$changed = Transaction::run(
			function () use ( $from, $old, $name ) {
				$this->amenities->delete( (int) $from->id );
				return $this->rewriteRoomTypes(
					static function ( array $list ) use ( $old, $name ) {
						$out = array();
						foreach ( $list as $item ) {
							$item = self::same( $item, $old ) ? $name : $item;
							if ( ! in_array( $item, $out, true ) ) {
								$out[] = $item;
							}
						}
						return $out;
					}
				);
			}
		);

		rtbp_activity(
			'amenities.merge',
			$into,
			array(
				'before'      => array( 'name' => $old ),
				'after'       => array(
					'name'       => $name,
					'room_types' => $changed,
				),
				/* translators: 1: amenity merged away, 2: amenity kept. */
				'description' => sprintf( __( 'Merged the amenity %1$s into %2$s', 'radius-hotel-booking' ), $old, $name ),
			)
		);

		return $into;
	}

	/**
	 * Put the amenities in a new order. `ids` must list every amenity once.
	 * Room types show their amenities in this order.
	 *
	 * @param array $ids Amenity ids, first to last.
	 * @return array[] The amenities, as all() returns them.
	 * @throws DomainException When the list is not exactly the amenities.
	 */
	public function reorder( array $ids ): array {
		$ids     = array_map( 'intval', array_values( $ids ) );
		$current = $this->amenities->ordered();
		$known   = array_map( static fn( Amenity $amenity ) => (int) $amenity->id, $current );

		$sorted_ids   = $ids;
		$sorted_known = $known;
		sort( $sorted_ids );
		sort( $sorted_known );
		if ( $sorted_ids !== $sorted_known ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid(
				array( 'ids' => __( 'The list of amenities has changed. Reload the page and try again.', 'radius-hotel-booking' ) )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( $ids === $known ) {
			return $this->all();
		}

		$names = array();
		foreach ( $current as $amenity ) {
			$names[ (int) $amenity->id ] = (string) $amenity->name;
		}

		Transaction::run(
			function () use ( $ids ) {
				foreach ( $ids as $position => $id ) {
					$this->amenities->update( $id, array( 'sort_order' => $position ) );
				}
				$this->rewriteRoomTypes( fn( array $list ) => $this->sorted( $list ) );
			}
		);

		rtbp_activity(
			'amenities.reorder',
			array(
				'type'  => 'amenity',
				'id'    => 0,
				'label' => __( 'Amenities', 'radius-hotel-booking' ),
			),
			array(
				'before'      => array( 'order' => array_values( array_map( static fn( $id ) => $names[ $id ], $known ) ) ),
				'after'       => array( 'order' => array_values( array_map( static fn( $id ) => $names[ $id ], $ids ) ) ),
				'description' => __( 'Reordered the amenities', 'radius-hotel-booking' ),
			)
		);

		return $this->all();
	}

	/**
	 * Delete an amenity and take it off every room type that offers it.
	 *
	 * @param int $id Id.
	 * @return void
	 */
	public function delete( int $id ): void {
		$amenity = $this->get( $id );
		$name    = (string) $amenity->name;

		$changed = Transaction::run(
			function () use ( $id, $name ) {
				$this->amenities->delete( $id );
				return $this->rewriteRoomTypes(
					static fn( array $list ) => array_values( array_filter( $list, static fn( $item ) => ! self::same( $item, $name ) ) )
				);
			}
		);

		rtbp_activity(
			'amenities.delete',
			$amenity,
			array(
				'before'      => array(
					'name'       => $name,
					'room_types' => $changed,
				),
				/* translators: %s: amenity name. */
				'description' => sprintf( __( 'Deleted the amenity %s', 'radius-hotel-booking' ), $name ),
			)
		);
	}

	/**
	 * A room type's chosen amenities in the library's spelling and order.
	 * Names the library does not know yet are added to it, so a name typed
	 * on a room type (or brought in by an import) is offered to the others.
	 *
	 * @param string[] $names Clean, non-empty names.
	 * @return string[]
	 */
	public function resolve( array $names ): array {
		$library = $this->byKey();
		$out     = array();
		foreach ( $names as $name ) {
			$key = mb_strtolower( $name );
			if ( ! isset( $library[ $key ] ) ) {
				$amenity         = $this->insert( $name );
				$library[ $key ] = (string) $amenity->name;
				rtbp_activity(
					'amenities.create',
					$amenity,
					array(
						'after'       => array( 'name' => $name ),
						/* translators: %s: amenity name. */
						'description' => sprintf( __( 'Added the amenity %s', 'radius-hotel-booking' ), $name ),
					)
				);
			}
			if ( ! in_array( $library[ $key ], $out, true ) ) {
				$out[] = $library[ $key ];
			}
		}
		return $this->sorted( $out );
	}

	/**
	 * Names in library order; names the library does not hold go last.
	 *
	 * @param string[] $names Names.
	 * @return string[]
	 */
	private function sorted( array $names ): array {
		$position = array_flip( array_keys( $this->byKey() ) );
		$last     = count( $position );
		usort(
			$names,
			static fn( $a, $b ) => ( $position[ mb_strtolower( $a ) ] ?? $last ) <=> ( $position[ mb_strtolower( $b ) ] ?? $last )
		);
		return array_values( $names );
	}

	/**
	 * Lower-cased name => library name, in library order.
	 *
	 * @return array<string, string>
	 */
	private function byKey(): array {
		$out = array();
		foreach ( $this->amenities->ordered() as $amenity ) {
			$out[ mb_strtolower( (string) $amenity->name ) ] = (string) $amenity->name;
		}
		return $out;
	}

	/**
	 * Apply a change to every room type's amenity list; save the ones that
	 * changed.
	 *
	 * @param callable $change Gets a list, returns the new list.
	 * @return string[] Names of the room types changed.
	 */
	private function rewriteRoomTypes( callable $change ): array {
		$changed = array();
		foreach ( $this->types->ordered() as $type ) {
			$list = self::listOf( $type );
			$new  = $change( $list );
			if ( $new !== $list ) {
				$this->types->update( (int) $type->id, array( 'amenities' => array_values( $new ) ) );
				$changed[] = (string) $type->name;
			}
		}
		return $changed;
	}

	/**
	 * Store a new amenity at the end of the list.
	 *
	 * @param string $name Clean name.
	 * @return Amenity
	 */
	private function insert( string $name ): Amenity {
		return $this->amenities->create(
			array(
				'name'       => $name,
				'sort_order' => $this->amenities->nextSortOrder(),
			)
		);
	}

	/**
	 * A room type's amenity names.
	 *
	 * @param RoomType $type Room type.
	 * @return string[]
	 */
	private static function listOf( RoomType $type ): array {
		return array_values( array_filter( array_map( 'strval', (array) $type->amenities ) ) );
	}

	/**
	 * Whether two amenity names are the same, ignoring case.
	 *
	 * @param string $a Name.
	 * @param string $b Name.
	 * @return bool
	 */
	private static function same( string $a, string $b ): bool {
		return mb_strtolower( $a ) === mb_strtolower( $b );
	}

	/**
	 * A valid amenity name from the input, unused unless `$allow_taken`.
	 *
	 * @param array $input       Input.
	 * @param int   $except_id   The amenity being renamed.
	 * @param bool  $allow_taken Skip the "already exists" check (the caller merges).
	 * @return string
	 * @throws DomainException With the field error.
	 */
	private function validName( array $input, int $except_id, bool $allow_taken = false ): string {
		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( '' === $name || mb_strlen( $name ) > 60 ) {
			$error = __( 'Give the amenity a name of up to 60 characters.', 'radius-hotel-booking' );
		} elseif ( ! $allow_taken && $this->amenities->nameTaken( $name, $except_id ) ) {
			/* translators: %s: amenity name. */
			$error = sprintf( __( 'There is already an amenity called %s.', 'radius-hotel-booking' ), $name );
		}
		if ( isset( $error ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'name' => $error ) );
		}
		return $name;
	}
}
