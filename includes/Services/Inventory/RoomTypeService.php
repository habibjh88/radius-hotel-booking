<?php
/**
 * Room types: create, change, delete.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Inventory
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Inventory;

use RadiusTheme\RadiusHotelBooking\ActivityLog\ChangeDiff;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\RoomType;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;
use RadiusTheme\RadiusHotelBooking\Support\Db;

defined( 'ABSPATH' ) || exit;

/**
 * Room types (features 6.1, 6.12, 6.13). Every change is logged with the
 * fields that changed. A room type that still has rooms cannot be deleted.
 */
class RoomTypeService {

	/**
	 * Room types.
	 *
	 * @var RoomTypeRepository
	 */
	private RoomTypeRepository $types;

	/**
	 * Rooms.
	 *
	 * @var RoomRepository
	 */
	private RoomRepository $rooms;

	/**
	 * Constructor.
	 *
	 * @param RoomTypeRepository|null $types Room types.
	 * @param RoomRepository|null     $rooms Rooms.
	 */
	public function __construct( ?RoomTypeRepository $types = null, ?RoomRepository $rooms = null ) {
		$this->types = $types ?? new RoomTypeRepository();
		$this->rooms = $rooms ?? new RoomRepository();
	}

	/**
	 * Every room type, in display order.
	 *
	 * @return RoomType[]
	 */
	public function all(): array {
		return $this->types->ordered();
	}

	/**
	 * One room type, or 404.
	 *
	 * @param int $id Id.
	 * @return RoomType
	 * @throws DomainException When missing.
	 */
	public function get( int $id ): RoomType {
		$type = $this->types->find( $id );
		if ( ! $type instanceof RoomType ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This room type does not exist.', 'radius-hotel-booking' ) );
		}
		return $type;
	}

	/**
	 * Create a room type.
	 *
	 * @param array $input Fields.
	 * @return RoomType
	 * @throws DomainException When the insert did not happen.
	 */
	public function create( array $input ): RoomType {
		$data         = $this->validate( $input, null );
		$data['slug'] = $this->uniqueSlug( (string) ( $input['slug'] ?? '' ) ? (string) $input['slug'] : $data['name'], 0 );
		$type         = Db::quietly( fn() => $this->types->create( $data ) );
		if ( ! $type instanceof RoomType || ! $type->id ) {
			// Two identical names at the same moment: the UNIQUE slug refused the second.
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'room_type_not_saved', __( 'The room type could not be saved. Please try again.', 'radius-hotel-booking' ), 409 );
		}

		rtbp_activity(
			'room_types.create',
			$type,
			array(
				'after'       => self::audited( $data ),
				/* translators: %s: room type name. */
				'description' => sprintf( __( 'Added the room type %s', 'radius-hotel-booking' ), $type->name ),
			)
		);

		/**
		 * Fires after a room type is created.
		 *
		 * @param RoomType $type Room type.
		 */
		do_action( 'rtbp_room_type_created', $type );

		return $type;
	}

	/**
	 * Change a room type (only the fields given).
	 *
	 * @param int   $id    Id.
	 * @param array $input Fields.
	 * @return RoomType
	 */
	public function update( int $id, array $input ): RoomType {
		$type   = $this->get( $id );
		$before = self::audited( $type->toArray() );
		$data   = $this->validate( $input, $type );
		if ( isset( $input['slug'] ) && '' !== (string) $input['slug'] && (string) $input['slug'] !== $type->slug ) {
			$data['slug'] = $this->uniqueSlug( (string) $input['slug'], $id );
		}

		$this->types->update( $id, $data );
		$type = $this->get( $id );

		$diff = ChangeDiff::between( $before, self::audited( $type->toArray() ) );
		if ( $diff['after'] ) {
			rtbp_activity(
				'room_types.update',
				$type,
				$diff + array(
					/* translators: %s: room type name. */
					'description' => sprintf( __( 'Changed the room type %s', 'radius-hotel-booking' ), $type->name ),
				)
			);

			/**
			 * Fires after a room type changed.
			 *
			 * @param RoomType $type Room type.
			 * @param array    $diff Before/after of the changed fields.
			 */
			do_action( 'rtbp_room_type_updated', $type, $diff );
		}

		return $type;
	}

	/**
	 * Delete a room type (soft). Refused while it still has rooms.
	 *
	 * @param int $id Id.
	 * @return void
	 * @throws DomainException When it has rooms.
	 */
	public function delete( int $id ): void {
		$type = $this->get( $id );

		Transaction::run(
			function () use ( $type, $id ) {
				// Locked, so a room cannot be added between the count and the delete.
				if ( ! $this->types->lock( $id ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::notFound( __( 'This room type does not exist.', 'radius-hotel-booking' ) );
				}
				$count = array_sum( $this->rooms->stateCounts( array( $id ) )[ $id ] ?? array() );
				if ( $count ) {
					// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict(
						'room_type_has_rooms',
						sprintf(
							/* translators: 1: room type name, 2: number of rooms. */
							_n( '%1$s still has %2$d room. Move or remove it first.', '%1$s still has %2$d rooms. Move or remove them first.', $count, 'radius-hotel-booking' ),
							$type->name,
							$count
						),
						array( 'rooms' => $count )
					);
					// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				$type->delete();
			}
		);

		rtbp_activity(
			'room_types.delete',
			$type,
			array(
				'before'      => self::audited( $type->toArray() ),
				/* translators: %s: room type name. */
				'description' => sprintf( __( 'Deleted the room type %s', 'radius-hotel-booking' ), $type->name ),
			)
		);

		/**
		 * Fires after a room type is deleted.
		 *
		 * @param RoomType $type Room type.
		 */
		do_action( 'rtbp_room_type_deleted', $type );
	}

	/**
	 * Validate and clean the fields given.
	 *
	 * @param array         $input Input.
	 * @param RoomType|null $type  The room type being changed, or null on create.
	 * @return array Clean columns.
	 * @throws DomainException With field errors.
	 */
	private function validate( array $input, ?RoomType $type ): array {
		$data   = array();
		$errors = array();
		$has    = static fn( $key ) => null === $type || array_key_exists( $key, $input );

		if ( $has( 'name' ) ) {
			$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
			if ( '' === $name || mb_strlen( $name ) > 120 ) {
				$errors['name'] = __( 'Give the room type a name of up to 120 characters.', 'radius-hotel-booking' );
			}
			$data['name'] = $name;
		}
		if ( $has( 'description' ) ) {
			$data['description'] = wp_kses_post( (string) ( $input['description'] ?? '' ) );
		}
		if ( $has( 'short_description' ) ) {
			$data['short_description'] = mb_substr( sanitize_textarea_field( (string) ( $input['short_description'] ?? '' ) ), 0, 500 );
		}
		if ( $has( 'bed_info' ) ) {
			$data['bed_info'] = mb_substr( sanitize_text_field( (string) ( $input['bed_info'] ?? '' ) ), 0, 191 );
		}
		if ( $has( 'amenities' ) ) {
			$list = is_array( $input['amenities'] ?? null ) ? $input['amenities'] : array();
			$clean = array();
			foreach ( $list as $item ) {
				$item = mb_substr( sanitize_text_field( (string) $item ), 0, 60 );
				if ( '' !== $item && ! in_array( $item, $clean, true ) ) {
					$clean[] = $item;
				}
			}
			if ( count( $clean ) > 40 ) {
				$errors['amenities'] = __( 'Use 40 amenities or fewer.', 'radius-hotel-booking' );
			}
			$data['amenities'] = $clean;
		}
		if ( $has( 'gallery' ) ) {
			$ids = array_values( array_unique( array_filter( array_map( 'absint', is_array( $input['gallery'] ?? null ) ? $input['gallery'] : array() ) ) ) );
			foreach ( $ids as $id ) {
				if ( ! wp_attachment_is_image( $id ) ) {
					$errors['gallery'] = __( 'Every photo must be an image from the media library.', 'radius-hotel-booking' );
					break;
				}
			}
			$data['gallery'] = $ids;
		}
		if ( $has( 'featured_image_id' ) || isset( $data['gallery'] ) ) {
			$gallery  = $data['gallery'] ?? (array) ( $type->gallery ?? array() );
			$featured = absint( $input['featured_image_id'] ?? ( $type->featured_image_id ?? 0 ) );
			// The cover is one of the photos; the first one when not chosen.
			$data['featured_image_id'] = in_array( $featured, $gallery, true ) ? $featured : (int) ( $gallery[0] ?? 0 );
		}

		$numbers = array(
			'max_adults'   => array( 1, 20, __( 'Between 1 and 20 adults.', 'radius-hotel-booking' ) ),
			'max_children' => array( 0, 20, __( 'Between 0 and 20 children.', 'radius-hotel-booking' ) ),
			'sort_order'   => array( 0, 100000, __( 'This value is not valid.', 'radius-hotel-booking' ) ),
		);
		foreach ( $numbers as $key => $rule ) {
			if ( ! $has( $key ) || ( null === $type && ! isset( $input[ $key ] ) ) ) {
				continue;
			}
			$value = filter_var( $input[ $key ], FILTER_VALIDATE_INT );
			if ( false === $value || $value < $rule[0] || $value > $rule[1] ) {
				$errors[ $key ] = $rule[2];
				continue;
			}
			$data[ $key ] = $value;
		}
		if ( $has( 'size_m2' ) && isset( $input['size_m2'] ) && '' !== $input['size_m2'] && null !== $input['size_m2'] ) {
			$size = filter_var( $input['size_m2'], FILTER_VALIDATE_FLOAT );
			if ( false === $size || $size <= 0 || $size > 10000 ) {
				$errors['size_m2'] = __( 'Give the size in square metres.', 'radius-hotel-booking' );
			} else {
				$data['size_m2'] = round( $size, 2 );
			}
		} elseif ( array_key_exists( 'size_m2', $input ) ) {
			$data['size_m2'] = null;
		}
		if ( array_key_exists( 'buffer_minutes', $input ) ) {
			if ( null === $input['buffer_minutes'] || '' === $input['buffer_minutes'] ) {
				$data['buffer_minutes'] = null; // Follow the Booking rules setting.
			} else {
				$buffer = filter_var( $input['buffer_minutes'], FILTER_VALIDATE_INT );
				if ( false === $buffer || $buffer < 0 || $buffer > 720 ) {
					$errors['buffer_minutes'] = __( 'Between 0 and 720 minutes, or empty to use the default.', 'radius-hotel-booking' );
				} else {
					$data['buffer_minutes'] = $buffer;
				}
			}
		}
		if ( array_key_exists( 'is_active', $input ) ) {
			$data['is_active'] = rest_sanitize_boolean( $input['is_active'] ) ? 1 : 0;
		}

		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}
		return $data;
	}

	/**
	 * A slug unique among room types (removed ones included).
	 *
	 * @param string $source    Name or requested slug.
	 * @param int    $except_id Ignore this type.
	 * @return string
	 */
	private function uniqueSlug( string $source, int $except_id ): string {
		$base = substr( sanitize_title( $source ), 0, 120 );
		$base = '' !== $base ? $base : 'room-type';
		$slug = $base;
		for ( $i = 2; $this->types->slugTaken( $slug, $except_id ); $i++ ) {
			$slug = $base . '-' . $i;
		}
		return $slug;
	}

	/**
	 * The fields the activity log compares.
	 *
	 * @param array $row Row or input.
	 * @return array
	 */
	private static function audited( array $row ): array {
		$keys = array( 'name', 'slug', 'short_description', 'description', 'gallery', 'featured_image_id', 'amenities', 'bed_info', 'size_m2', 'max_adults', 'max_children', 'buffer_minutes', 'is_active', 'sort_order' );
		$out  = array_intersect_key( $row, array_flip( $keys ) );
		if ( isset( $out['description'] ) ) {
			// Long HTML: record that it changed, not the whole text.
			$out['description'] = '' === (string) $out['description'] ? '' : md5( (string) $out['description'] );
		}
		return $out;
	}
}
