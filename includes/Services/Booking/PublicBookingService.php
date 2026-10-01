<?php
/**
 * Booking on the website (M04): search, hold, book — the public rules.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Booking;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Guest;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Resources\AvailabilityResource;
use RadiusTheme\RadiusHotelBooking\Services\Availability\AvailabilityService;
use RadiusTheme\RadiusHotelBooking\Services\Availability\HoldService;
use RadiusTheme\RadiusHotelBooking\Services\Guests\GuestService;
use RadiusTheme\RadiusHotelBooking\Services\Payments\GuestBookingView;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Phone;

defined( 'ABSPATH' ) || exit;

/**
 * The same engine as the desk, with the website's rules on top:
 *
 * - **Search** — `AvailabilityService::search( …, 'public' )` (booking window,
 *   same-day cut-off), shaped by `AvailabilityResource::public()`: no staff
 *   fields; sold-out rates and rooms hidden when `booking.unavailableRooms` is
 *   `hide` (4.4); no room list when guests do not pick their room.
 * - **Hold** — the party is always sent (the engine checks it against the room
 *   type, M08 critical review). When guests do not pick (`website.guestPicksRoom`
 *   off) or no room was sent, the first free room by number is taken (legacy:
 *   lowest number), the next one if it was just taken.
 * - **Book** — honeypot, consent (`website.privacyConsent`), name, phone and
 *   **identity document required** (legacy); always unpaid, `source = web`
 *   (pending or confirmed per *Manual approval*), made from exactly the
 *   visitor's live holds. A phone or e-mail already on file is reused **only
 *   when the details agree** with that guest (name, and the identity document
 *   — or the phone when none is on file); otherwise the booking is refused with
 *   the generic message, since booking under that guest would show their
 *   details to the visitor (M04 critical review; the legacy PII leak). A banned
 *   guest — matched by phone, e-mail or identity document — gets the same
 *   refusal. Refusals are logged (`security.booking_refused`) for staff.
 */
class PublicBookingService {

	/**
	 * Free rooms tried in turn when the system assigns the room.
	 */
	public const ASSIGN_TRIES = 5;

	/**
	 * The honeypot field: hidden from people, filled by bots.
	 */
	public const HONEYPOT = 'company_website';

	/**
	 * Availability for the website.
	 *
	 * @param array                  $input `arrival`, `departure`, `adults`, `children`, `child_ages`, `room_type_id`, `rate_plan_id`, `checkin_time`.
	 * @param DateTimeImmutable|null $now   Now.
	 * @return array The public payload.
	 */
	public function availability( array $input, ?DateTimeImmutable $now = null ): array {
		$result = Container::resolve( AvailabilityService::class )->search( $input, AvailabilityService::PUBLIC, $now );
		return AvailabilityResource::public(
			$result,
			array(
				'hide_unavailable' => 'hide' === (string) rtbp_setting( 'booking', 'unavailableRooms', 'hide' ),
				'rooms'            => (bool) rtbp_setting( 'website', 'guestPicksRoom', true ),
			)
		);
	}

	/**
	 * Hold a room for the visitor.
	 *
	 * @param array                  $input   `room_type_id`, `rate_plan_id`, `arrival`, `departure`, `units`, `checkin_time`,
	 *                                        `adults`, `children`, `child_ages`, `room_id?`, `token?`.
	 * @param string                 $session The visitor's session key.
	 * @param DateTimeImmutable|null $now     Now.
	 * @return array `{ token, expires_at, hold }`.
	 * @throws DomainException 422 input, 409 `room_unavailable` / engine rules.
	 */
	public function hold( array $input, string $session, ?DateTimeImmutable $now = null ): array {
		$now   = $now ?? Dates::now();
		$party = self::party( $input );
		$actor = array(
			'audience'    => AvailabilityService::PUBLIC,
			'session_key' => $session,
		);
		$base  = array_merge(
			array_intersect_key( $input, array_flip( array( 'room_type_id', 'rate_plan_id', 'arrival', 'units', 'checkin_time', 'token' ) ) ),
			$party
		);
		$holds = Container::resolve( HoldService::class );

		$picks = (bool) rtbp_setting( 'website', 'guestPicksRoom', true );
		if ( $picks && ! empty( $input['room_id'] ) ) {
			return $holds->create( $base + array( 'room_id' => (int) $input['room_id'] ), $actor, $now );
		}

		// The system assigns: the free rooms of that rate, lowest number first.
		$free = $this->freeRooms( $input, $party, $now );
		if ( ! $free['rooms'] && $free['reason'] && 'fully_booked' !== $free['reason']['code'] ) {
			// Not sold at all for this request (cut-off, window, closed…): say why.
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict( (string) $free['reason']['code'], (string) $free['reason']['message'] );
		}
		$last = null;
		foreach ( array_slice( $free['rooms'], 0, self::ASSIGN_TRIES ) as $room_id ) {
			try {
				return $holds->create( $base + array( 'room_id' => $room_id ), $actor, $now );
			} catch ( DomainException $e ) {
				// Taken a moment ago: try the next room; anything else is final.
				if ( 'room_unavailable' !== $e->getErrorCode() ) {
					throw $e;
				}
				$last = $e;
			}
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
		throw $last ? $last : DomainException::conflict( 'room_unavailable', __( 'This room was just taken. Please choose another option.', 'radius-hotel-booking' ) );
	}

	/**
	 * Release the visitor's holds (or one of them).
	 *
	 * @param string $token   Hold token.
	 * @param string $session The visitor's session key.
	 * @param int    $hold_id One hold (0 = all of the token's).
	 * @return int Holds released.
	 */
	public function release( string $token, string $session, int $hold_id = 0 ): int {
		return Container::resolve( HoldService::class )->release( $token, self::actor( $session ), $hold_id );
	}

	/**
	 * Keep the visitor's holds for another hold period (the flow does it at
	 * most twice while the guest is typing).
	 *
	 * @param string $token   Hold token.
	 * @param string $session The visitor's session key.
	 * @return array `{ token, expires_at }`.
	 */
	public function extend( string $token, string $session ): array {
		return Container::resolve( HoldService::class )->extend( $token, self::actor( $session ) );
	}

	/**
	 * The web visitor as a hold owner.
	 *
	 * @param string $session Session key.
	 * @return array
	 */
	private static function actor( string $session ): array {
		return array(
			'audience'    => AvailabilityService::PUBLIC,
			'session_key' => $session,
		);
	}

	/**
	 * Book from the visitor's holds.
	 *
	 * @param array                  $input   `hold_token`, `lines[]` (as held, each with `adults`, `children`),
	 *                                        `guest { first_name, last_name, phone, email?, id_type, id_number }`,
	 *                                        `special_requests?`, `consent`, the honeypot, `accept_new_price?`.
	 * @param string                 $session The visitor's session key.
	 * @param DateTimeImmutable|null $now     Now.
	 * @return array `{ reference, token, status, page_url }`.
	 * @throws DomainException 422 fields, 409 `booking_refused` / `room_unavailable` / `price_changed` / engine rules.
	 */
	public function create( array $input, string $session, ?DateTimeImmutable $now = null ): array {
		$now = $now ?? Dates::now();

		// A bot filled the hidden field: refuse without saying why.
		if ( '' !== trim( (string) ( $input[ self::HONEYPOT ] ?? '' ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict( 'booking_refused', __( 'We could not take this booking online. Please contact the hotel.', 'radius-hotel-booking' ) );
		}

		$guest  = self::guestFields( (array) ( $input['guest'] ?? array() ) );
		$errors = $guest['errors'];
		if ( rtbp_setting( 'website', 'privacyConsent', true ) && ! rest_sanitize_boolean( $input['consent'] ?? false ) ) {
			$errors['consent'] = __( 'Please accept the privacy policy to book.', 'radius-hotel-booking' );
		}
		$lines = array();
		foreach ( array_values( (array) ( $input['lines'] ?? array() ) ) as $line ) {
			$line    = (array) $line;
			$lines[] = array_merge(
				array_intersect_key( $line, array_flip( array( 'room_id', 'rate_plan_id', 'room_type_id', 'arrival', 'units', 'checkin_time', 'expected_total' ) ) ),
				self::party( $line )
			);
		}
		if ( ! $lines ) {
			$errors['lines'] = __( 'Choose a room first.', 'radius-hotel-booking' );
		}
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}

		$booking_input = array(
			'hold_token'       => (string) ( $input['hold_token'] ?? '' ),
			'lines'            => $lines,
			'payment_state'    => 'unpaid',
			'note'             => mb_substr( sanitize_textarea_field( (string) ( $input['special_requests'] ?? '' ) ), 0, 1000 ),
			'accept_new_price' => rest_sanitize_boolean( $input['accept_new_price'] ?? false ),
		);
		// A guest already on file is used only when the details agree with it.
		$known = $this->knownGuest( $guest['fields'] );
		if ( $known ) {
			$booking_input['guest_id'] = $known;
		} else {
			$booking_input['guest'] = $guest['fields'];
		}

		try {
			$booking = Container::resolve( BookingService::class )->create(
				$booking_input,
				array(
					'audience'    => AvailabilityService::PUBLIC,
					'session_key' => $session,
				),
				'web',
				$now
			);
		} catch ( DomainException $e ) {
			if ( 'guest_banned' !== $e->getErrorCode() ) {
				throw $e;
			}
			self::refuse( (int) ( $e->getContext()['guest_id'] ?? 0 ), $guest['fields'], true );
		}

		return array(
			'reference' => (string) $booking->reference,
			'token'     => (string) $booking->public_token,
			'status'    => (string) $booking->status,
			'page_url'  => GuestBookingView::pageUrl( $booking ),
		);
	}

	/**
	 * The free rooms of the requested rate, lowest number first (the search's
	 * own room order).
	 *
	 * @param array             $input Hold input.
	 * @param array             $party `{ adults, children, child_ages }`.
	 * @param DateTimeImmutable $now   Now.
	 * @return array{rooms: int[], reason: array|null} Room ids, and the rate's reason when it is not sold.
	 */
	private function freeRooms( array $input, array $party, DateTimeImmutable $now ): array {
		$type_id = (int) ( $input['room_type_id'] ?? 0 );
		$plan_id = (int) ( $input['rate_plan_id'] ?? 0 );
		$result  = Container::resolve( AvailabilityService::class )->search(
			array_merge(
				array_intersect_key( $input, array_flip( array( 'arrival', 'departure', 'checkin_time' ) ) ),
				$party,
				array(
					'room_type_id' => $type_id,
					'rate_plan_id' => $plan_id,
				)
			),
			AvailabilityService::PUBLIC,
			$now
		);
		$rooms  = array();
		$reason = null;
		foreach ( (array) ( $result['room_types'] ?? array() ) as $type ) {
			if ( (int) $type['id'] !== $type_id ) {
				continue;
			}
			foreach ( (array) ( $type['rates'] ?? array() ) as $rate ) {
				if ( (int) $rate['rate_plan_id'] === $plan_id && ! empty( $rate['reasons'][0] ) ) {
					$reason = $rate['reasons'][0];
				}
			}
			foreach ( (array) $type['floors'] as $floor ) {
				foreach ( (array) $floor['rooms'] as $room ) {
					if ( in_array( $plan_id, array_map( 'intval', (array) $room['available_for'] ), true ) ) {
						$rooms[] = (int) $room['id'];
					}
				}
			}
		}
		return array(
			'rooms'  => $rooms,
			'reason' => $reason,
		);
	}

	/**
	 * The guest on file this booking belongs to (0 = a new guest).
	 *
	 * A phone or e-mail already on file is reused only when the details agree
	 * with that guest: the same name, and the same identity document — or, when
	 * none is on file, the same phone. Anything else is refused: booking under
	 * that guest would show their details to whoever typed the phone or e-mail.
	 * A banned guest is refused too, also when matched by identity document.
	 *
	 * @param array $fields Guest fields (validated).
	 * @return int Guest id.
	 * @throws DomainException 409 `booking_refused`.
	 */
	private function knownGuest( array $fields ): int {
		$repo = new GuestRepository();
		$e164 = Phone::toE164( (string) $fields['phone'] );

		$banned = $repo->bannedByDocument( $fields['id_type'], self::documentKey( $fields['id_number'] ) );
		if ( $banned ) {
			self::refuse( $banned, $fields, true );
		}

		$email = is_email( (string) $fields['email'] ) ? strtolower( (string) $fields['email'] ) : null;
		$ids   = array_values( array_unique( array_map( static fn( $row ) => (int) $row['id'], $repo->duplicates( $e164, Phone::tail( (string) $fields['phone'] ), $email ) ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$guest = 1 === count( $ids ) ? $repo->find( $ids[0] ) : null;
		if ( ! $guest instanceof Guest ) {
			// The phone and the e-mail belong to two different guests.
			self::refuse( $ids[0], $fields, false );
		}
		if ( Guest::BANNED === (string) $guest->standing ) {
			self::refuse( (int) $guest->id, $fields, true );
		}

		$same_name = self::nameKey( $fields['first_name'] . $fields['last_name'] ) === self::nameKey( (string) $guest->first_name . (string) $guest->last_name );
		$same_id   = '' !== self::documentKey( (string) $guest->id_number )
			? (string) $guest->id_type === $fields['id_type'] && self::documentKey( (string) $guest->id_number ) === self::documentKey( $fields['id_number'] )
			: null !== $e164 && (string) $guest->phone_e164 === $e164;
		if ( ! $same_name || ! $same_id ) {
			self::refuse( (int) $guest->id, $fields, false );
		}
		return (int) $guest->id;
	}

	/**
	 * Refuse a web booking with the generic message, and log it for staff.
	 *
	 * @param int   $guest_id The guest on file concerned.
	 * @param array $fields   What the visitor typed.
	 * @param bool  $banned   A banned guest (else: details that do not agree).
	 * @return never
	 * @throws DomainException 409 `booking_refused`.
	 */
	private static function refuse( int $guest_id, array $fields, bool $banned ) {
		rtbp_activity(
			'security.booking_refused',
			array(
				'type'  => 'guest',
				'id'    => $guest_id,
				'label' => trim( $fields['first_name'] . ' ' . $fields['last_name'] ),
			),
			array(
				'description' => $banned
					? __( 'A banned guest tried to book on the website; the booking was refused.', 'radius-hotel-booking' )
					: __( 'A website booking used this guest\'s phone or e-mail with other details; it was refused. The visitor was asked to contact the hotel.', 'radius-hotel-booking' ),
			)
		);
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
		throw DomainException::conflict( 'booking_refused', __( 'We could not take this booking online. Please contact the hotel.', 'radius-hotel-booking' ) );
	}

	/**
	 * A name for comparing: letters only, lower case, no accents
	 * ("Jean-Paul Kouassi" = "jean paul  kouassi").
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function nameKey( string $name ): string {
		return (string) preg_replace( '/[^a-z]/', '', strtolower( remove_accents( $name ) ) );
	}

	/**
	 * An identity document number for comparing: letters and digits, upper case.
	 *
	 * @param string $number Number.
	 * @return string
	 */
	private static function documentKey( string $number ): string {
		return (string) preg_replace( '/[^A-Z0-9]/', '', strtoupper( $number ) );
	}

	/**
	 * The party of a request: at least one adult, always sent to the engine.
	 *
	 * @param array $input Input.
	 * @return array `{ adults, children, child_ages? }`.
	 */
	private static function party( array $input ): array {
		$party = array(
			'adults'   => max( 1, min( 20, (int) ( $input['adults'] ?? 1 ) ) ),
			'children' => max( 0, min( 10, (int) ( $input['children'] ?? 0 ) ) ),
		);
		if ( ! empty( $input['child_ages'] ) && is_array( $input['child_ages'] ) ) {
			$party['child_ages'] = array_slice( array_map( 'absint', $input['child_ages'] ), 0, 10 );
		}
		return $party;
	}

	/**
	 * The guest's details, with the website's required fields.
	 *
	 * @param array $raw Raw fields.
	 * @return array{fields: array, errors: array<string, string>}
	 */
	private static function guestFields( array $raw ): array {
		$fields = array(
			'first_name' => sanitize_text_field( (string) ( $raw['first_name'] ?? '' ) ),
			'last_name'  => sanitize_text_field( (string) ( $raw['last_name'] ?? '' ) ),
			'phone'      => sanitize_text_field( (string) ( $raw['phone'] ?? '' ) ),
			'email'      => sanitize_email( (string) ( $raw['email'] ?? '' ) ),
			'id_type'    => sanitize_key( (string) ( $raw['id_type'] ?? '' ) ),
			'id_number'  => sanitize_text_field( (string) ( $raw['id_number'] ?? '' ) ),
		);
		$errors = array();
		foreach ( array(
			'first_name' => __( 'Enter your first name.', 'radius-hotel-booking' ),
			'last_name'  => __( 'Enter your last name.', 'radius-hotel-booking' ),
			'id_number'  => __( 'Enter the number of your identity document.', 'radius-hotel-booking' ),
		) as $key => $message ) {
			if ( '' === trim( $fields[ $key ] ) ) {
				$errors[ $key ] = $message;
			}
		}
		if ( null === Phone::toE164( $fields['phone'] ) ) {
			$errors['phone'] = __( 'Enter a phone number we can reach you on.', 'radius-hotel-booking' );
		}
		if ( ! array_key_exists( $fields['id_type'], GuestService::idTypes() ) ) {
			$errors['id_type'] = __( 'Choose your identity document type.', 'radius-hotel-booking' );
		}
		if ( '' !== (string) ( $raw['email'] ?? '' ) && ! is_email( $fields['email'] ) ) {
			$errors['email'] = __( 'Enter a valid e-mail address, or leave it empty.', 'radius-hotel-booking' );
		}
		return array(
			'fields' => $fields,
			'errors' => $errors,
		);
	}
}
