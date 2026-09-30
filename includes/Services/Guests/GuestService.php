<?php
/**
 * Guest records (M09).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Guests
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Guests;

use RadiusTheme\RadiusHotelBooking\ActivityLog\ChangeDiff;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Guest;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Phone;
use RadiusTheme\RadiusHotelBooking\Support\Sequence;

defined( 'ABSPATH' ) || exit;

/**
 * One record per real person (9.1–9.12). Phones are kept as typed and in
 * E.164 (`Support\Phone`), names folded for search, and a guest without an
 * e-mail gets a flagged placeholder address that nothing is ever sent to.
 * Creating a guest who already exists (same phone or e-mail) is refused with
 * the existing record, so the caller can use it instead. Every change is
 * logged with before/after (the ID number masked); a ban needs a reason, and
 * a banned guest cannot be booked (`assertBookable()`).
 */
class GuestService {

	/**
	 * Guests.
	 *
	 * @var GuestRepository
	 */
	private GuestRepository $guests;

	/**
	 * Constructor.
	 *
	 * @param GuestRepository|null $guests Guests.
	 */
	public function __construct( ?GuestRepository $guests = null ) {
		$this->guests = $guests ?? new GuestRepository();
	}

	/**
	 * Identity document types: key => label (9.6).
	 *
	 * @return array<string, string>
	 */
	public static function idTypes(): array {
		/**
		 * Identity document types a guest can show.
		 *
		 * @param array $types Key => translated label.
		 */
		return (array) apply_filters(
			'rtbp_id_document_types',
			array(
				'cni'            => __( 'National ID card', 'radius-hotel-booking' ),
				'passport'       => __( 'Passport', 'radius-hotel-booking' ),
				'consular_card'  => __( 'Consular card', 'radius-hotel-booking' ),
				'cni_receipt'    => __( 'National ID receipt', 'radius-hotel-booking' ),
				'id_certificate' => __( 'Identity certificate', 'radius-hotel-booking' ),
				'driver_license' => __( 'Driver license', 'radius-hotel-booking' ),
			)
		);
	}

	/**
	 * A name folded for search: accents removed, lower case, single spaces.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function fold( string $name ): string {
		$name = remove_accents( wp_strip_all_tags( $name ) );
		$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
		$name = preg_replace( '/[^\p{L}\p{N}@.\'+-]+/u', ' ', $name );
		return trim( preg_replace( '/\s+/', ' ', (string) $name ) );
	}

	/**
	 * An ID number masked for lists: `••••3456`.
	 *
	 * @param string $number Number.
	 * @return string
	 */
	public static function maskId( string $number ): string {
		$number = trim( $number );
		if ( '' === $number ) {
			return '';
		}
		return '••••' . ( strlen( $number ) > 4 ? substr( $number, -4 ) : '' );
	}

	/**
	 * One live guest, or 404.
	 *
	 * @param int $id Id.
	 * @return Guest
	 * @throws DomainException 404.
	 */
	public function get( int $id ): Guest {
		$guest = $id > 0 ? $this->guests->find( $id ) : null;
		if ( ! $guest instanceof Guest ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This guest does not exist.', 'radius-hotel-booking' ) );
		}
		return $guest;
	}

	/**
	 * Create a guest. A guest with the same phone or e-mail already on file
	 * is refused with 409 `guest_exists` and `{ guests: [ … ] }`, so the
	 * caller offers that record instead.
	 *
	 * @param array $input `{ first_name, last_name, phone, email, id_type, id_number }`.
	 * @return Guest
	 * @throws DomainException 422 fields, 409 `guest_exists`.
	 */
	public function create( array $input ): Guest {
		$data = $this->validate( $input, null );
		$this->refuseDuplicates( $data, 0 );

		$id = Transaction::run(
			function () use ( $data ) {
				$data['reference']  = self::reference( Sequence::next( 'guest' ) );
				$data['standing']   = Guest::NORMAL;
				$data['created_by'] = get_current_user_id() ? get_current_user_id() : null;
				$guest              = $this->guests->create( $data );
				if ( ! $guest->id ) {
					// A UNIQUE key refused it: the same guest was added a moment ago.
					$this->refuseDuplicates( $data, 0 );
					throw new \RuntimeException( 'The guest could not be saved.' );
				}
				rtbp_activity(
					'guests.create',
					$guest,
					array(
						'after'       => ChangeDiff::mask( self::logged( $data ) ),
						/* translators: 1: guest name, 2: guest reference. */
						'description' => sprintf( __( 'Added the guest %1$s (%2$s)', 'radius-hotel-booking' ), $guest->fullName(), $data['reference'] ),
					)
				);
				return (int) $guest->id;
			}
		);
		return $this->get( $id );
	}

	/**
	 * The existing guest with this phone or e-mail, or a new one (M02, M04).
	 * Details already on file are kept; empty ones are filled in.
	 *
	 * @param array $data As create().
	 * @return Guest
	 * @throws DomainException 422 when the details are invalid.
	 */
	public function findOrCreate( array $data ): Guest {
		try {
			return $this->create( $data );
		} catch ( DomainException $e ) {
			$found = $e->getContext()['guests'][0]['id'] ?? 0;
			if ( 'guest_exists' !== $e->getErrorCode() || ! $found ) {
				throw $e;
			}
			$guest = $this->get( (int) $found );
			$fill  = array();
			foreach ( array( 'first_name', 'last_name', 'id_type', 'id_number' ) as $field ) {
				if ( '' === (string) $guest->{$field} && '' !== trim( (string) ( $data[ $field ] ?? '' ) ) ) {
					$fill[ $field ] = $data[ $field ];
				}
			}
			if ( $guest->email_is_placeholder && '' !== trim( (string) ( $data['email'] ?? '' ) ) ) {
				$fill['email'] = $data['email'];
			}
			return $fill ? $this->update( (int) $guest->id, $fill ) : $guest;
		}
	}

	/**
	 * Change a guest's details (9.5, 9.7). Only the fields given change.
	 *
	 * @param int   $id    Guest id.
	 * @param array $input Any of create()'s fields.
	 * @return Guest
	 * @throws DomainException 404, 422 (a phone or e-mail already on another guest).
	 */
	public function update( int $id, array $input ): Guest {
		$guest = $this->get( $id );
		$data  = $this->validate( $input, $guest );
		$this->refuseDuplicates( $data, $id );

		$before = self::logged( $guest->toArray() );
		$after  = self::logged( array_merge( $guest->toArray(), $data ) );
		$diff   = ChangeDiff::between( $before, $after );
		if ( ! $diff['after'] ) {
			return $guest;
		}

		Transaction::run(
			function () use ( $id, $data, $diff, $guest ) {
				if ( ! $this->guests->lock( $id ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::notFound( __( 'This guest does not exist.', 'radius-hotel-booking' ) );
				}
				$this->guests->update( $id, $data );
				rtbp_activity(
					'guests.edit',
					$guest,
					array_merge(
						$diff,
						array(
							/* translators: 1: guest name, 2: guest reference. */
							'description' => sprintf( __( 'Changed the details of %1$s (%2$s)', 'radius-hotel-booking' ), $guest->fullName(), $guest->reference ),
						)
					)
				);
			}
		);
		return $this->get( $id );
	}

	/**
	 * Ban a guest (9.10): they cannot be booked until the ban is lifted.
	 *
	 * @param int    $id     Guest id.
	 * @param string $reason Why (required).
	 * @return Guest
	 */
	public function ban( int $id, string $reason ): Guest {
		return $this->setStanding( $id, Guest::BANNED, $reason );
	}

	/**
	 * Lift a ban.
	 *
	 * @param int    $id     Guest id.
	 * @param string $reason Why (required).
	 * @return Guest
	 */
	public function unban( int $id, string $reason ): Guest {
		return $this->setStanding( $id, Guest::NORMAL, $reason );
	}

	/**
	 * The full identity document number (9.6), for a viewer allowed
	 * `guests.view_id` (the controller checks). Every reveal is logged as a
	 * sensitive read, without the number.
	 *
	 * @param int $id Guest id.
	 * @return array `{ id_type, id_type_label, id_number }`.
	 */
	public function revealId( int $id ): array {
		$guest = $this->get( $id );
		$types = self::idTypes();
		if ( '' !== (string) $guest->id_number ) {
			rtbp_activity(
				'guests.view_id',
				$guest,
				array(
					/* translators: 1: guest name, 2: guest reference. */
					'description' => sprintf( __( 'Viewed the identity document of %1$s (%2$s)', 'radius-hotel-booking' ), $guest->fullName(), $guest->reference ),
				)
			);
		}
		return array(
			'id_type'       => (string) $guest->id_type,
			'id_type_label' => (string) ( $types[ $guest->id_type ] ?? $guest->id_type ),
			'id_number'     => (string) $guest->id_number,
		);
	}

	/**
	 * A guest's stays (9.4): their bookings with the rooms and windows.
	 *
	 * @param int $id Guest id.
	 * @return array[] `{ id, reference, status, payment_status, total, balance_due, source, arrival, departure, lines: [ { room_number, rate_plan_name, start_at, end_at, status, total } ] }`.
	 */
	public function stays( int $id ): array {
		$this->get( $id );
		return array_map(
			static fn( $row ) => array(
				'id'             => (int) $row['id'],
				'reference'      => (string) $row['reference'],
				'status'         => (string) $row['status'],
				'payment_status' => (string) $row['payment_status'],
				'total'          => (float) $row['total'],
				'balance_due'    => (float) $row['balance_due'],
				'source'         => (string) $row['source'],
				'arrival'        => $row['arrival'] ? substr( (string) $row['arrival'], 0, 16 ) : null,
				'departure'      => $row['departure'] ? substr( (string) $row['departure'], 0, 16 ) : null,
				'lines'          => array_map(
					static fn( $line ) => array(
						'id'             => (int) $line['id'],
						'room_number'    => (string) $line['room_number'],
						'rate_plan_name' => (string) $line['rate_plan_name'],
						'start_at'       => substr( (string) $line['start_at'], 0, 16 ),
						'end_at'         => substr( (string) $line['end_at'], 0, 16 ),
						'status'         => (string) $line['status'],
						'total'          => (float) $line['total'],
					),
					$row['lines']
				),
			),
			$this->guests->stays( $id )
		);
	}

	/**
	 * Refuse to book a banned guest (called by the booking services).
	 *
	 * @param Guest $guest Guest.
	 * @return void
	 * @throws DomainException 409 `guest_banned`.
	 */
	public static function assertBookable( Guest $guest ): void {
		if ( Guest::BANNED === $guest->standing ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict(
				'guest_banned',
				/* translators: %s: guest name. */
				sprintf( __( '%s is banned and cannot be booked.', 'radius-hotel-booking' ), $guest->fullName() ),
				array(
					'guest_id' => (int) $guest->id,
					'reason'   => (string) $guest->ban_reason,
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Search terms from what was typed.
	 *
	 * @param string $q Typed text.
	 * @return array{name: string, digits: string, tail: string, email: string}
	 */
	public static function terms( string $q ): array {
		$q      = trim( wp_strip_all_tags( $q ) );
		$digits = preg_replace( '/\D+/', '', $q );
		$has_letters = (bool) preg_match( '/\p{L}/u', $q );
		return array(
			'name'   => $has_letters ? self::fold( $q ) : '',
			// A number: at least 4 digits and no letters.
			'digits' => ! $has_letters && strlen( $digits ) >= 4 ? $digits : '',
			'tail'   => ! $has_letters ? Phone::tail( $q ) : '',
			'email'  => strtolower( $q ),
		);
	}

	/**
	 * A page of guests (9.1, 9.2).
	 *
	 * @param string $q        Search (name, e-mail, phone, reference).
	 * @param string $standing `normal`, `banned` or '' (all).
	 * @param int    $page     Page.
	 * @param int    $per      Per page (1–100).
	 * @return array{items: Guest[], total: int}
	 */
	public function page( string $q, string $standing, int $page = 1, int $per = 20 ): array {
		$data = $this->guests->page(
			array(
				'q'        => self::terms( $q ),
				'standing' => in_array( $standing, array( Guest::NORMAL, Guest::BANNED ), true ) ? $standing : '',
			),
			max( 1, $page ),
			min( 100, max( 1, $per ) )
		);
		return array(
			'items' => array_map( static fn( $row ) => Guest::hydrate( $row ), $data['items'] ),
			'total' => $data['total'],
		);
	}

	/**
	 * The booking forms' quick lookup: at most 8 guests, an exact phone first.
	 *
	 * @param string $q Typed text (2 characters or more).
	 * @return Guest[]
	 */
	public function lookup( string $q ): array {
		$terms = self::terms( $q );
		if ( mb_strlen( trim( $q ) ) < 2 || ( '' === $terms['name'] && '' === $terms['digits'] ) ) {
			return array();
		}
		return array_map( static fn( $row ) => Guest::hydrate( $row ), $this->guests->lookup( $terms, 8 ) );
	}

	/**
	 * Change the standing.
	 *
	 * @param int    $id       Guest id.
	 * @param string $standing Guest::BANNED or Guest::NORMAL.
	 * @param string $reason   Why.
	 * @return Guest
	 * @throws DomainException 404, 422 reason, 409 already in that standing.
	 */
	private function setStanding( int $id, string $standing, string $reason ): Guest {
		$guest  = $this->get( $id );
		$reason = sanitize_text_field( $reason );
		if ( mb_strlen( $reason ) < 3 || mb_strlen( $reason ) > 191 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'reason' => __( 'Give a reason (3 to 191 characters).', 'radius-hotel-booking' ) ) );
		}
		if ( $guest->standing === $standing ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict(
				'no_change',
				Guest::BANNED === $standing ? __( 'This guest is already banned.', 'radius-hotel-booking' ) : __( 'This guest is not banned.', 'radius-hotel-booking' )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		Transaction::run(
			function () use ( $id, $guest, $standing, $reason ) {
				if ( ! $this->guests->lock( $id ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::notFound( __( 'This guest does not exist.', 'radius-hotel-booking' ) );
				}
				$banned = Guest::BANNED === $standing;
				$this->guests->update(
					$id,
					array(
						'standing'   => $standing,
						// The last ban's reason stays after an unban, for the record.
						'ban_reason' => $banned ? $reason : (string) $guest->ban_reason,
						'banned_at'  => $banned ? Dates::to_gmt_db( Dates::now() ) : null,
						'banned_by'  => $banned && get_current_user_id() ? get_current_user_id() : null,
					)
				);
				rtbp_activity(
					$banned ? 'guests.ban' : 'guests.unban',
					$guest,
					array(
						'before'      => array( 'standing' => (string) $guest->standing ),
						'after'       => array(
							'standing' => $standing,
							'reason'   => $reason,
						),
						'description' => $banned
							/* translators: 1: guest name, 2: reason. */
							? sprintf( __( 'Banned %1$s: %2$s', 'radius-hotel-booking' ), $guest->fullName(), $reason )
							/* translators: 1: guest name, 2: reason. */
							: sprintf( __( 'Lifted the ban on %1$s: %2$s', 'radius-hotel-booking' ), $guest->fullName(), $reason ),
					)
				);
			}
		);
		return $this->get( $id );
	}

	/**
	 * Validated columns. On update (`$guest` given) only the fields present
	 * are checked; the result still carries every derived column they touch.
	 *
	 * @param array      $input Input.
	 * @param Guest|null $guest The guest being changed.
	 * @return array Columns.
	 * @throws DomainException 422.
	 */
	private function validate( array $input, ?Guest $guest ): array {
		$has    = static fn( $key ) => null === $guest || array_key_exists( $key, $input );
		$value  = static fn( $key ) => array_key_exists( $key, $input ) ? $input[ $key ] : ( $guest ? $guest->{$key} : '' );
		$errors = array();
		$data   = array();

		foreach ( array( 'first_name', 'last_name' ) as $field ) {
			if ( $has( $field ) ) {
				$data[ $field ] = sanitize_text_field( (string) $value( $field ) );
				if ( mb_strlen( $data[ $field ] ) > 100 ) {
					$errors[ $field ] = __( 'Use up to 100 characters.', 'radius-hotel-booking' );
				}
			}
		}
		$first = $data['first_name'] ?? ( $guest ? $guest->first_name : '' );
		$last  = $data['last_name'] ?? ( $guest ? $guest->last_name : '' );
		if ( '' === trim( $first . $last ) ) {
			$errors['first_name'] = __( 'Enter the guest’s name.', 'radius-hotel-booking' );
		}
		if ( isset( $data['first_name'] ) || isset( $data['last_name'] ) ) {
			$data['name_search'] = self::fold( $first . ' ' . $last );
		}

		// The phone: as typed, in E.164, and its last 8 digits.
		if ( $has( 'phone' ) ) {
			$phone              = sanitize_text_field( (string) $value( 'phone' ) );
			$data['phone']      = $phone;
			$data['phone_e164'] = Phone::toE164( $phone );
			$data['phone_tail'] = Phone::tail( $phone );
			if ( '' !== $phone && null === $data['phone_e164'] && '' === $data['phone_tail'] ) {
				$errors['phone'] = __( 'Enter a valid phone number.', 'radius-hotel-booking' );
			}
		}
		$phone_e164 = array_key_exists( 'phone_e164', $data ) ? $data['phone_e164'] : ( $guest ? $guest->phone_e164 : null );
		$phone_raw  = $data['phone'] ?? ( $guest ? $guest->phone : '' );

		// The e-mail: real, or a flagged placeholder from the phone (9.12).
		if ( $has( 'email' ) || $has( 'phone' ) ) {
			$email = array_key_exists( 'email', $input ) ? trim( (string) $input['email'] ) : ( $guest && ! $guest->email_is_placeholder ? $guest->email : '' );
			if ( '' !== $email ) {
				$clean = sanitize_email( $email );
				if ( ! is_email( $clean ) ) {
					$errors['email'] = __( 'Enter a valid e-mail address.', 'radius-hotel-booking' );
				} else {
					$data['email']                = $clean;
					$data['email_key']            = strtolower( $clean );
					$data['email_is_placeholder'] = false;
				}
			} elseif ( '' !== $phone_raw ) {
				$data['email']                = self::placeholderEmail( $phone_e164, $phone_raw );
				$data['email_key']            = null;
				$data['email_is_placeholder'] = true;
			} else {
				$errors['phone'] = __( 'Enter a phone number or an e-mail address.', 'radius-hotel-booking' );
			}
		}

		if ( $has( 'id_type' ) || $has( 'id_number' ) ) {
			$type   = sanitize_key( (string) $value( 'id_type' ) );
			$number = strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( (string) $value( 'id_number' ) ) ) );
			if ( '' !== $type && ! array_key_exists( $type, self::idTypes() ) ) {
				$errors['id_type'] = __( 'Choose a document type from the list.', 'radius-hotel-booking' );
			} elseif ( '' !== $number && '' === $type ) {
				$errors['id_type'] = __( 'Choose the document type.', 'radius-hotel-booking' );
			}
			if ( strlen( $number ) > 60 ) {
				$errors['id_number'] = __( 'Use up to 60 characters.', 'radius-hotel-booking' );
			}
			$data['id_type']   = $type;
			$data['id_number'] = $number;
		}

		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}
		return $data;
	}

	/**
	 * Refuse a phone or e-mail another guest already has.
	 *
	 * @param array $data      Validated columns.
	 * @param int   $except_id The guest being changed (0 = new).
	 * @return void
	 * @throws DomainException 409 `guest_exists` (create) or 422 (update).
	 */
	private function refuseDuplicates( array $data, int $except_id ): void {
		if ( ! array_key_exists( 'phone_e164', $data ) && ! array_key_exists( 'email_key', $data ) ) {
			return;
		}
		$existing = $except_id ? $this->get( $except_id ) : null;
		$found    = $this->guests->duplicates(
			array_key_exists( 'phone_e164', $data ) ? $data['phone_e164'] : ( $existing ? $existing->phone_e164 : null ),
			$data['phone_tail'] ?? ( $existing ? $existing->phone_tail : '' ),
			array_key_exists( 'email_key', $data ) ? $data['email_key'] : ( $existing ? $existing->email_key : null ),
			$except_id
		);
		if ( ! $found ) {
			return;
		}
		$first = $found[0];
		$name  = trim( $first['first_name'] . ' ' . $first['last_name'] );
		if ( $except_id ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid(
				array(
					'email' === $first['match'] ? 'email' : 'phone' => sprintf(
						/* translators: 1: guest name, 2: guest reference. */
						__( '%1$s (%2$s) already has this. Each guest needs their own.', 'radius-hotel-booking' ),
						$name,
						$first['reference']
					),
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
		throw DomainException::conflict(
			'guest_exists',
			/* translators: 1: guest name, 2: guest reference. */
			sprintf( __( 'This guest is already on file: %1$s (%2$s).', 'radius-hotel-booking' ), $name, $first['reference'] ),
			array(
				'guests' => array_map(
					static fn( $row ) => array(
						'id'        => (int) $row['id'],
						'reference' => (string) $row['reference'],
						'name'      => trim( $row['first_name'] . ' ' . $row['last_name'] ),
						'match'     => (string) $row['match'],
					),
					$found
				),
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * The internal address of a guest without an e-mail (9.12). `.invalid`
	 * can never be delivered to; the client add-on may keep the legacy
	 * `{phone}@residencetata.com` form through the filter.
	 *
	 * @param string|null $e164  E.164 phone.
	 * @param string      $phone Phone as typed.
	 * @return string
	 */
	private static function placeholderEmail( ?string $e164, string $phone ): string {
		$digits = preg_replace( '/\D+/', '', null !== $e164 ? $e164 : $phone );
		/**
		 * The placeholder e-mail of a guest without one. It is flagged
		 * `email_is_placeholder`, so no e-mail is ever sent to it.
		 *
		 * @param string      $email  Default `{digits}@no-email.invalid`.
		 * @param string      $digits The phone's digits.
		 * @param string|null $e164   The phone in E.164.
		 */
		return (string) apply_filters( 'rtbp_guest_placeholder_email', $digits . '@no-email.invalid', $digits, $e164 );
	}

	/**
	 * The guest's human reference: `G000123`.
	 *
	 * @param int $number Sequence number.
	 * @return string
	 */
	private static function reference( int $number ): string {
		/**
		 * A new guest's reference.
		 *
		 * @param string $reference Default `G` + 6 digits.
		 * @param int    $number    Sequence number.
		 */
		return (string) apply_filters( 'rtbp_guest_reference', sprintf( 'G%06d', $number ), $number );
	}

	/**
	 * The fields an activity entry records.
	 *
	 * @param array $values Guest columns.
	 * @return array
	 */
	private static function logged( array $values ): array {
		return array(
			'first_name' => (string) ( $values['first_name'] ?? '' ),
			'last_name'  => (string) ( $values['last_name'] ?? '' ),
			'phone'      => (string) ( $values['phone'] ?? '' ),
			'email'      => (string) ( $values['email'] ?? '' ),
			'id_type'    => (string) ( $values['id_type'] ?? '' ),
			'id_number'  => (string) ( $values['id_number'] ?? '' ),
		);
	}
}
