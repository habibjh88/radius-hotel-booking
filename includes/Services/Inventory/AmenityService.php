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
use RadiusTheme\RadiusHotelBooking\Models\AmenitySection;
use RadiusTheme\RadiusHotelBooking\Models\RoomType;
use RadiusTheme\RadiusHotelBooking\Repositories\AmenityRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\AmenitySectionRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;
use RadiusTheme\RadiusHotelBooking\Support\Icons;

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
	 * Sections.
	 *
	 * @var AmenitySectionRepository
	 */
	private AmenitySectionRepository $sections;

	/**
	 * Constructor.
	 *
	 * @param AmenityRepository|null  $amenities Amenities.
	 * @param RoomTypeRepository|null $types     Room types.
	 */
	public function __construct( ?AmenityRepository $amenities = null, ?RoomTypeRepository $types = null ) {
		$this->amenities = $amenities ?? new AmenityRepository();
		$this->types     = $types ?? new RoomTypeRepository();
		$this->sections  = new AmenitySectionRepository();
	}

	/**
	 * Every amenity in order, with the room types that offer it.
	 *
	 * @return array[] `{ id, name, icon, section_id, sort_order, room_types: [ { id, name } ] }`.
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
				'icon'       => (string) $amenity->icon,
				'section_id' => (int) $amenity->section_id,
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
	 * @param array $input `{ name, icon?, section_id? }` (no section: the first one).
	 * @return Amenity
	 */
	public function create( array $input ): Amenity {
		$name    = $this->validName( $input, 0 );
		$icon    = self::validIcon( $input['icon'] ?? '' );
		$amenity = $this->insert( $name, $icon, $this->validSection( $input['section_id'] ?? 0 ) );

		rtbp_activity(
			'amenities.create',
			$amenity,
			array(
				'after'       => array_filter(
					array(
						'name' => $name,
						'icon' => $icon,
					)
				),
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
	 * @return array[] `[ { label, amenities: [ { name, icon, added } ] } ]`.
	 */
	public function common(): array {
		// Each with a Lucide icon (Support\Icons).
		$groups = array(
			array(
				'label'     => __( 'Room comfort', 'radius-hotel-booking' ),
				'amenities' => array(
					array( __( 'Air conditioning', 'radius-hotel-booking' ), 'air-vent' ),
					array( __( 'Ceiling fan', 'radius-hotel-booking' ), 'fan' ),
					array( __( 'Wardrobe', 'radius-hotel-booking' ), 'shirt' ),
					array( __( 'Work desk', 'radius-hotel-booking' ), 'lamp-desk' ),
					array( __( 'Seating area', 'radius-hotel-booking' ), 'sofa' ),
					array( __( 'Blackout curtains', 'radius-hotel-booking' ), 'blinds' ),
					array( __( 'Balcony', 'radius-hotel-booking' ), 'fence' ),
				),
			),
			array(
				'label'     => __( 'Technology', 'radius-hotel-booking' ),
				'amenities' => array(
					array( __( 'Free Wi-Fi', 'radius-hotel-booking' ), 'wifi' ),
					array( __( 'Flat-screen TV', 'radius-hotel-booking' ), 'tv' ),
					array( __( 'Satellite channels', 'radius-hotel-booking' ), 'satellite-dish' ),
					array( __( 'Telephone', 'radius-hotel-booking' ), 'phone' ),
					array( __( 'USB charging ports', 'radius-hotel-booking' ), 'usb' ),
				),
			),
			array(
				'label'     => __( 'Bathroom', 'radius-hotel-booking' ),
				'amenities' => array(
					array( __( 'Private bathroom', 'radius-hotel-booking' ), 'toilet' ),
					array( __( 'Shower', 'radius-hotel-booking' ), 'shower-head' ),
					array( __( 'Bathtub', 'radius-hotel-booking' ), 'bath' ),
					array( __( 'Hair dryer', 'radius-hotel-booking' ), 'wind' ),
					array( __( 'Free toiletries', 'radius-hotel-booking' ), 'soap-dispenser-droplet' ),
					array( __( 'Towels', 'radius-hotel-booking' ), 'layers' ),
					array( __( 'Bathrobe', 'radius-hotel-booking' ), 'shirt' ),
					array( __( 'Slippers', 'radius-hotel-booking' ), 'footprints' ),
				),
			),
			array(
				'label'     => __( 'Food and drink', 'radius-hotel-booking' ),
				'amenities' => array(
					array( __( 'Mini bar', 'radius-hotel-booking' ), 'wine' ),
					array( __( 'Refrigerator', 'radius-hotel-booking' ), 'refrigerator' ),
					array( __( 'Electric kettle', 'radius-hotel-booking' ), 'zap' ),
					array( __( 'Tea and coffee maker', 'radius-hotel-booking' ), 'coffee' ),
					array( __( 'Bottled water', 'radius-hotel-booking' ), 'glass-water' ),
					array( __( 'Breakfast included', 'radius-hotel-booking' ), 'croissant' ),
				),
			),
			array(
				'label'     => __( 'Services and safety', 'radius-hotel-booking' ),
				'amenities' => array(
					array( __( 'Room service', 'radius-hotel-booking' ), 'concierge-bell' ),
					array( __( 'Daily housekeeping', 'radius-hotel-booking' ), 'spray-can' ),
					array( __( 'Wake-up service', 'radius-hotel-booking' ), 'alarm-clock' ),
					array( __( 'In-room safe', 'radius-hotel-booking' ), 'vault' ),
					array( __( 'Key card access', 'radius-hotel-booking' ), 'key-round' ),
					array( __( 'Smoke detector', 'radius-hotel-booking' ), 'siren' ),
					array( __( 'Free parking', 'radius-hotel-booking' ), 'square-parking' ),
				),
			),
		);

		/**
		 * The groups offered by "Add common amenities".
		 *
		 * @param array[] $groups `[ { label, amenities: [ [ name, icon ] | name ] } ]`.
		 */
		$groups = (array) apply_filters( 'rtbp_common_amenities', $groups );

		$library = $this->byKey();
		$out     = array();
		foreach ( $groups as $group ) {
			$names = array();
			foreach ( (array) ( $group['amenities'] ?? array() ) as $item ) {
				list( $name, $icon ) = is_array( $item ) ? array_values( $item ) + array( '', '' ) : array( $item, '' );
				$name                = mb_substr( sanitize_text_field( (string) $name ), 0, 60 );
				if ( '' !== $name ) {
					$names[] = array(
						'name'  => $name,
						'icon'  => Icons::clean( $icon ),
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
	 * @param array $names Names, or `{ name, icon }`.
	 * @return string[] The names added.
	 * @throws DomainException When no name is given or one is too long.
	 */
	public function createMany( array $names ): array {
		$library = $this->byKey();
		$new     = array();
		$icons   = array();
		$homes   = array();
		foreach ( $names as $item ) {
			$name = sanitize_text_field( (string) ( is_array( $item ) ? ( $item['name'] ?? '' ) : $item ) );
			$icon = is_array( $item ) ? Icons::clean( $item['icon'] ?? '' ) : '';
			// The section by name (the common groups' labels): found, or made.
			$section = is_array( $item ) ? sanitize_text_field( (string) ( $item['section'] ?? '' ) ) : '';
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
				$icons[ $name ]  = $icon;
				$homes[ $name ]  = $section;
			}
		}
		if ( ! $new ) {
			return array();
		}

		Transaction::run(
			function () use ( $new, $icons, $homes ) {
				foreach ( $new as $name ) {
					$this->insert( $name, $icons[ $name ] ?? '', $this->sectionNamed( $homes[ $name ] ?? '' ) );
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
		$amenity  = $this->get( $id );
		$old      = (string) $amenity->name;
		$old_icon = (string) $amenity->icon;
		$icon     = array_key_exists( 'icon', $input ) ? self::validIcon( $input['icon'] ) : $old_icon;
		// Moved to another section: it goes last there.
		if ( isset( $input['section_id'] ) && (int) $input['section_id'] !== (int) $amenity->section_id ) {
			$section = $this->validSection( $input['section_id'] );
			$this->amenities->update(
				$id,
				array(
					'section_id' => $section,
					'sort_order' => $this->amenities->nextSortOrder(),
				)
			);
			rtbp_activity(
				'amenities.update',
				$amenity,
				array(
					'before'      => array( 'section' => $this->sectionName( (int) $amenity->section_id ) ),
					'after'       => array( 'section' => $this->sectionName( $section ) ),
					/* translators: 1: amenity name, 2: section name. */
					'description' => sprintf( __( 'Moved the amenity %1$s to %2$s', 'radius-hotel-booking' ), $old, $this->sectionName( $section ) ),
				)
			);
			$amenity = $this->get( $id );
		}
		$name     = isset( $input['name'] ) ? $this->validName( $input, $id, true ) : $old;
		if ( $name === $old ) {
			if ( $icon !== $old_icon ) {
				$this->amenities->update( $id, array( 'icon' => $icon ) );
				$amenity = $this->get( $id );
				rtbp_activity(
					'amenities.update',
					$amenity,
					array(
						'before'      => array( 'icon' => $old_icon ),
						'after'       => array( 'icon' => $icon ),
						/* translators: %s: amenity name. */
						'description' => sprintf( __( 'Changed the icon of the amenity %s', 'radius-hotel-booking' ), $name ),
					)
				);
			}
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
			function () use ( $id, $old, $name, $icon ) {
				$this->amenities->update(
					$id,
					array(
						'name' => $name,
						'icon' => $icon,
					)
				);
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
			function () use ( $from, $into, $old, $name ) {
				// The kept amenity takes the other's icon when it has none.
				if ( '' === (string) $into->icon && '' !== (string) $from->icon ) {
					$this->amenities->update( (int) $into->id, array( 'icon' => (string) $from->icon ) );
				}
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
	 * @param string $icon    Clean icon name ('' = none).
	 * @param int    $section Section id (0 = the first section).
	 * @return Amenity
	 */
	private function insert( string $name, string $icon = '', int $section = 0 ): Amenity {
		return $this->amenities->create(
			array(
				'name'       => $name,
				'icon'       => $icon,
				'section_id' => $section ? $section : $this->defaultSection(),
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
	 * Once, after the icon column arrived: amenities without an icon whose
	 * name is one of the common amenities (ignoring case) get its icon.
	 * Hooked on `init` by AmenitiesTable (the names are translated).
	 *
	 * @return void
	 */
	public static function suggestIconsOnce(): void {
		if ( get_option( 'rtbp_amenity_icons_suggested' ) ) {
			return;
		}
		$service = new self();
		$icons   = array();
		foreach ( $service->common() as $group ) {
			foreach ( $group['amenities'] as $amenity ) {
				if ( '' !== $amenity['icon'] ) {
					$icons[ mb_strtolower( $amenity['name'] ) ] = $amenity['icon'];
				}
			}
		}
		foreach ( $service->amenities->ordered() as $amenity ) {
			$key = mb_strtolower( (string) $amenity->name );
			if ( '' === (string) $amenity->icon && isset( $icons[ $key ] ) ) {
				$service->amenities->update( (int) $amenity->id, array( 'icon' => $icons[ $key ] ) );
			}
		}
		update_option( 'rtbp_amenity_icons_suggested', 1, false );
	}

	/**
	 * The sections in order.
	 *
	 * @return array[] `{ id, name, sort_order }`.
	 */
	public function sections(): array {
		return array_map(
			static fn( AmenitySection $section ) => array(
				'id'         => (int) $section->id,
				'name'       => (string) $section->name,
				'sort_order' => (int) $section->sort_order,
			),
			$this->sections->ordered()
		);
	}

	/**
	 * Add a section at the end.
	 *
	 * @param array $input `{ name }`.
	 * @return AmenitySection
	 */
	public function createSection( array $input ): AmenitySection {
		$name    = $this->validSectionName( $input, 0 );
		$section = $this->sections->create(
			array(
				'name'       => $name,
				'sort_order' => $this->sections->nextSortOrder(),
			)
		);
		rtbp_activity(
			'amenities.section',
			self::sectionRef( $section ),
			array(
				'after'       => array( 'name' => $name ),
				/* translators: %s: section name. */
				'description' => sprintf( __( 'Added the amenity section %s', 'radius-hotel-booking' ), $name ),
			)
		);
		return $section;
	}

	/**
	 * Rename a section.
	 *
	 * @param int   $id    Section id.
	 * @param array $input `{ name }`.
	 * @return AmenitySection
	 */
	public function renameSection( int $id, array $input ): AmenitySection {
		$section = $this->getSection( $id );
		$old     = (string) $section->name;
		$name    = $this->validSectionName( $input, $id );
		if ( $name !== $old ) {
			$this->sections->update( $id, array( 'name' => $name ) );
			$section = $this->getSection( $id );
			rtbp_activity(
				'amenities.section',
				self::sectionRef( $section ),
				array(
					'before'      => array( 'name' => $old ),
					'after'       => array( 'name' => $name ),
					/* translators: 1: old name, 2: new name. */
					'description' => sprintf( __( 'Renamed the amenity section %1$s to %2$s', 'radius-hotel-booking' ), $old, $name ),
				)
			);
		}
		return $section;
	}

	/**
	 * Delete a section; its amenities move to `$move_to` (at its end).
	 *
	 * @param int $id      Section id.
	 * @param int $move_to Section that receives the amenities (needed when it has any).
	 * @return void
	 * @throws DomainException When amenities would be left without a section.
	 */
	public function deleteSection( int $id, int $move_to ): void {
		$section = $this->getSection( $id );
		$members = array_values( array_filter( $this->amenities->ordered(), static fn( Amenity $a ) => (int) $a->section_id === $id ) );
		if ( $members ) {
			if ( ! $move_to || $move_to === $id || ! $this->sections->find( $move_to ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::invalid( array( 'move_to' => __( 'Choose the section that takes its amenities.', 'radius-hotel-booking' ) ) );
			}
		}
		Transaction::run(
			function () use ( $id, $members, $move_to ) {
				$next = $this->amenities->nextSortOrder();
				foreach ( $members as $amenity ) {
					$this->amenities->update(
						(int) $amenity->id,
						array(
							'section_id' => $move_to,
							'sort_order' => $next++,
						)
					);
				}
				$this->sections->delete( $id );
			}
		);
		rtbp_activity(
			'amenities.section',
			self::sectionRef( $section ),
			array(
				'before'      => array(
					'name'      => (string) $section->name,
					'amenities' => array_map( static fn( Amenity $a ) => (string) $a->name, $members ),
				),
				'after'       => $members ? array( 'moved_to' => $this->sectionName( $move_to ) ) : array(),
				/* translators: %s: section name. */
				'description' => sprintf( __( 'Deleted the amenity section %s', 'radius-hotel-booking' ), (string) $section->name ),
			)
		);
	}

	/**
	 * Save the whole arrangement at once (drag and drop): the sections in
	 * order, each with its amenities in order. Every section and every
	 * amenity must be listed exactly once.
	 *
	 * @param array $layout `[ { id, amenities: [ ids ] } ]`.
	 * @return void
	 * @throws DomainException When the list is not exactly the library.
	 */
	public function layout( array $layout ): void {
		$section_ids = array();
		$amenity_ids = array();
		foreach ( $layout as $row ) {
			$section_ids[] = (int) ( $row['id'] ?? 0 );
			foreach ( (array) ( $row['amenities'] ?? array() ) as $amenity_id ) {
				$amenity_ids[] = (int) $amenity_id;
			}
		}
		$known_sections  = array_map( static fn( AmenitySection $s ) => (int) $s->id, $this->sections->ordered() );
		$known_amenities = array_map( static fn( Amenity $a ) => (int) $a->id, $this->amenities->ordered() );
		$a               = $section_ids;
		$b               = $known_sections;
		$c               = $amenity_ids;
		$d               = $known_amenities;
		sort( $a );
		sort( $b );
		sort( $c );
		sort( $d );
		if ( $a !== $b || $c !== $d ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'layout' => __( 'The amenities have changed. Reload the page and try again.', 'radius-hotel-booking' ) ) );
		}
		Transaction::run(
			function () use ( $layout ) {
				$position = 0;
				foreach ( array_values( $layout ) as $index => $row ) {
					$this->sections->update( (int) $row['id'], array( 'sort_order' => $index ) );
					foreach ( (array) ( $row['amenities'] ?? array() ) as $amenity_id ) {
						$this->amenities->update(
							(int) $amenity_id,
							array(
								'section_id' => (int) $row['id'],
								'sort_order' => $position++,
							)
						);
					}
				}
			}
		);
		rtbp_activity(
			'amenities.reorder',
			array(
				'type'  => 'amenity',
				'id'    => 0,
				'label' => __( 'Amenities', 'radius-hotel-booking' ),
			),
			array( 'description' => __( 'Rearranged the amenities and their sections', 'radius-hotel-booking' ) )
		);
	}

	/**
	 * For the pages that list a room type's amenities: the sections in order
	 * and, per amenity name (lower case), its icon and section.
	 *
	 * @return array `{ sections: [ { id, name } ], amenities: { name: { icon, section } } }`.
	 */
	public function display(): array {
		$amenities = array();
		foreach ( $this->amenities->ordered() as $amenity ) {
			$amenities[ mb_strtolower( (string) $amenity->name ) ] = array(
				'icon'    => (string) $amenity->icon,
				'section' => (int) $amenity->section_id,
			);
		}
		return array(
			'sections'  => array_map(
				static fn( $s ) => array(
					'id' => $s['id'],
					'name' => $s['name'],
				),
				$this->sections()
			),
			'amenities' => $amenities,
		);
	}

	/**
	 * Once, after sections arrived: the common groups become sections, each
	 * existing amenity goes into the group it belongs to (by name), the rest
	 * into "Other"; sections left empty are removed. Hooked on `init` by
	 * AmenitiesTable (the names are translated).
	 *
	 * @return void
	 */
	public static function seedSectionsOnce(): void {
		if ( get_option( 'rtbp_amenity_sections_seeded' ) ) {
			return;
		}
		$service = new self();
		$all     = $service->amenities->ordered();
		$orphans = array_filter( $all, static fn( Amenity $a ) => ! (int) $a->section_id || ! $service->sections->find( (int) $a->section_id ) );
		if ( $orphans ) {
			$home = array();
			foreach ( $service->common() as $group ) {
				foreach ( $group['amenities'] as $amenity ) {
					$home[ mb_strtolower( $amenity['name'] ) ] = $group['label'];
				}
			}
			$labels = array_merge( array_map( static fn( $g ) => $g['label'], $service->common() ), array( __( 'Other', 'radius-hotel-booking' ) ) );
			$ids    = array();
			foreach ( $labels as $label ) {
				$ids[ $label ] = $service->sectionNamed( $label );
			}
			$order = 0;
			foreach ( $all as $amenity ) {
				if ( in_array( $amenity, $orphans, true ) ) {
					$label = $home[ mb_strtolower( (string) $amenity->name ) ] ?? __( 'Other', 'radius-hotel-booking' );
					$service->amenities->update(
						(int) $amenity->id,
						array(
							'section_id' => $ids[ $label ],
							'sort_order' => $order,
						)
					);
				}
				++$order;
			}
			// No empty sections from the seed.
			$used = array_unique( array_map( static fn( Amenity $a ) => (int) $a->section_id, $service->amenities->ordered() ) );
			foreach ( $ids as $section_id ) {
				if ( ! in_array( (int) $section_id, $used, true ) ) {
					$service->sections->delete( (int) $section_id );
				}
			}
		}
		update_option( 'rtbp_amenity_sections_seeded', 1, false );
	}

	/**
	 * One section, or 404.
	 *
	 * @param int $id Id.
	 * @return AmenitySection
	 * @throws DomainException When missing.
	 */
	private function getSection( int $id ): AmenitySection {
		$section = $this->sections->find( $id );
		if ( ! $section instanceof AmenitySection ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This section does not exist.', 'radius-hotel-booking' ) );
		}
		return $section;
	}

	/**
	 * The section an amenity goes to: the given one, else the first, made
	 * ("General") when there is none.
	 *
	 * @param mixed $id Raw section id.
	 * @return int
	 * @throws DomainException When the id names no section.
	 */
	private function validSection( $id ): int {
		$id = (int) $id;
		if ( ! $id ) {
			return $this->defaultSection();
		}
		if ( ! $this->sections->find( $id ) instanceof AmenitySection ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'section_id' => __( 'Choose a section.', 'radius-hotel-booking' ) ) );
		}
		return $id;
	}

	/**
	 * The first section, made ("General") when there is none.
	 *
	 * @return int
	 */
	private function defaultSection(): int {
		$first = $this->sections->ordered()[0] ?? null;
		return $first instanceof AmenitySection ? (int) $first->id : $this->sectionNamed( __( 'General', 'radius-hotel-booking' ) );
	}

	/**
	 * The section with this name, made at the end when missing ('' = the first section).
	 *
	 * @param string $name Name.
	 * @return int
	 */
	private function sectionNamed( string $name ): int {
		$name = mb_substr( trim( $name ), 0, 60 );
		if ( '' === $name ) {
			return $this->defaultSection();
		}
		$found = $this->sections->findByName( $name );
		if ( $found instanceof AmenitySection ) {
			return (int) $found->id;
		}
		return (int) $this->sections->create(
			array(
				'name'       => $name,
				'sort_order' => $this->sections->nextSortOrder(),
			)
		)->id;
	}

	/**
	 * A section's name ('' when gone).
	 *
	 * @param int $id Id.
	 * @return string
	 */
	private function sectionName( int $id ): string {
		$section = $this->sections->find( $id );
		return $section instanceof AmenitySection ? (string) $section->name : '';
	}

	/**
	 * A valid, unused section name.
	 *
	 * @param array $input     Input.
	 * @param int   $except_id The section being renamed.
	 * @return string
	 * @throws DomainException With the field error.
	 */
	private function validSectionName( array $input, int $except_id ): string {
		$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		if ( '' === $name || mb_strlen( $name ) > 60 ) {
			$error = __( 'Give the section a name of up to 60 characters.', 'radius-hotel-booking' );
		} elseif ( $this->sections->findByName( $name, $except_id ) ) {
			/* translators: %s: section name. */
			$error = sprintf( __( 'There is already a section called %s.', 'radius-hotel-booking' ), $name );
		}
		if ( isset( $error ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'name' => $error ) );
		}
		return $name;
	}

	/**
	 * A section for the activity log.
	 *
	 * @param AmenitySection $section Section.
	 * @return array
	 */
	private static function sectionRef( AmenitySection $section ): array {
		return array(
			'type'  => 'amenity_section',
			'id'    => (int) $section->id,
			'label' => (string) $section->name,
		);
	}

	/**
	 * Amenity name (lower case) => icon, for the pages that list a room
	 * type's amenities by name.
	 *
	 * @return array<string, string>
	 */
	public function icons(): array {
		$out = array();
		foreach ( $this->amenities->ordered() as $amenity ) {
			if ( '' !== (string) $amenity->icon ) {
				$out[ mb_strtolower( (string) $amenity->name ) ] = (string) $amenity->icon;
			}
		}
		return $out;
	}

	/**
	 * A valid icon name ('' = none).
	 *
	 * @param mixed $icon Raw icon name.
	 * @return string
	 * @throws DomainException When it is not an icon.
	 */
	private static function validIcon( $icon ): string {
		$icon = trim( (string) $icon );
		if ( '' === $icon ) {
			return '';
		}
		$clean = Icons::clean( $icon );
		if ( '' === $clean ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'icon' => __( 'Choose an icon from the icon library.', 'radius-hotel-booking' ) ) );
		}
		return $clean;
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
