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
use RadiusTheme\RadiusHotelBooking\Frontend\ExpressCheckout;
use RadiusTheme\RadiusHotelBooking\Frontend\GuestAccount;
use RadiusTheme\RadiusHotelBooking\Models\Guest;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Resources\AvailabilityResource;
use RadiusTheme\RadiusHotelBooking\Services\Availability\AvailabilityService;
use RadiusTheme\RadiusHotelBooking\Services\Availability\HoldService;
use RadiusTheme\RadiusHotelBooking\Services\Guests\GuestService;
use RadiusTheme\RadiusHotelBooking\Services\Payments\GuestBookingView;
use RadiusTheme\RadiusHotelBooking\Settings\WebsiteSettings;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Phone;
use WP_User;

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
				'hide_unavailable' => 'hide' === (string) rtbp_setting( 'booking', 'unavailableRooms', 'disable' ),
				'rooms'            => (bool) rtbp_setting( 'website', 'guestPicksRoom', true ),
			)
		);
	}

	/**
	 * Hold a room for the visitor.
	 *
	 * @param array                  $input   `room_type_id`, `rate_plan_id`, `arrival`, `departure`, `units`, `checkin_time`,
	 *                                        `adults`, `children`, `child_ages`, `room_id?`, `room_ids?`, `token?`.
	 * @param string                 $session The visitor's session key.
	 * @param DateTimeImmutable|null $now     Now.
	 * @return array `{ token, expires_at, hold }`, or `{ token, expires_at, holds }` for `room_ids`.
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
		if ( $picks && ! empty( $input['room_ids'] ) && is_array( $input['room_ids'] ) ) {
			return $holds->createMany( $base, $input['room_ids'], $actor, $now );
		}
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
	 *                                        `special_requests?`, `consent`, the honeypot, `accept_new_price?`,
	 *                                        `express_token?` (ExpressCheckout: the visitor proved the
	 *                                        phone of a guest on file, whose details fill empty fields).
	 * @param string                 $session The visitor's session key.
	 * @param DateTimeImmutable|null $now     Now.
	 * @param WP_User|null           $account The signed-in visitor, verified by their token
	 *                                        (GuestAccount): empty fields come from the
	 *                                        account and the booking goes on its guest.
	 * @return array `{ reference, token, status, page_url, account }`; `account` is
	 *               GuestAccount::createFor()'s answer for a visitor who was not signed in.
	 * @throws DomainException 422 fields, 409 `booking_refused` / `room_unavailable` / `price_changed` / engine rules,
	 *                         401 `sign_in_required`.
	 */
	public function create( array $input, string $session, ?DateTimeImmutable $now = null, ?WP_User $account = null ): array {
		$now = $now ?? Dates::now();
		self::assertSignedIn( $account );

		// A bot filled the hidden field: refuse without saying why.
		if ( '' !== trim( (string) ( $input[ self::HONEYPOT ] ?? '' ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict( 'booking_refused', __( 'We could not take this booking online. Please contact the hotel.', 'radius-hotel-booking' ) );
		}

		$typed   = (array) ( $input['guest'] ?? array() );
		$linked  = null;
		$on_file = null;
		// The account to link the booking's guest to afterwards.
		$link_to = $account;
		if ( $account ) {
			$linked  = GuestAccount::guest( $account );
			$on_file = GuestAccount::fields( $account );
		} else {
			$proof = ExpressCheckout::proof( (string) ( $input['express_token'] ?? '' ), $session );
			if ( $proof ) {
				$on_file = ExpressCheckout::fields( $proof['guest'], $proof['user'] );
				if ( $proof['verified'] ) {
					$linked  = $proof['guest'] ? $proof['guest'] : ( $proof['user'] ? GuestAccount::guest( $proof['user'] ) : null );
					$link_to = $proof['user'];
				}
				// A phone number alone (no code) fills the same fields, the document
				// on file included (it never reaches the browser), but the account
				// is never linked.
			}
		}
		if ( $on_file ) {
			// What the visitor left empty comes from the record (the form hides
			// the document fields when one is on file). A document stays whole.
			foreach ( array( 'first_name', 'last_name', 'phone', 'email' ) as $key ) {
				if ( '' === trim( (string) ( $typed[ $key ] ?? '' ) ) ) {
					$typed[ $key ] = $on_file[ $key ];
				}
			}
			if ( '' === trim( (string) ( $typed['id_number'] ?? '' ) ) ) {
				$typed['id_type']   = $on_file['id_type'];
				$typed['id_number'] = $on_file['id_number'];
			}
		}
		$guest  = self::guestFields( $typed );
		$errors = $guest['errors'];
		$requests = 'hidden' === WebsiteSettings::guestField( 'special_requests' ) ? '' : mb_substr( sanitize_textarea_field( (string) ( $input['special_requests'] ?? '' ) ), 0, 1000 );
		if ( 'required' === WebsiteSettings::guestField( 'special_requests' ) && '' === trim( $requests ) ) {
			$errors['special_requests'] = __( 'Please fill in your special requests.', 'radius-hotel-booking' );
		}
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
			'note'             => $requests,
			'accept_new_price' => rest_sanitize_boolean( $input['accept_new_price'] ?? false ),
		);
		// The signed-in visitor's own guest record; otherwise a guest already on
		// file is used only when the details agree with it (and is then linked).
		$known = $linked ? self::bookable( $linked, $guest['fields'] ) : $this->knownGuest( $guest['fields'] );
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
			self::refuse( (int) ( $e->getContext()['guest_id'] ?? 0 ), $guest['fields'], 'banned' );
		}

		$created = '';
		if ( $link_to ) {
			GuestAccount::link( $link_to, (int) $booking->guest_id );
		} elseif ( ! $account ) {
			$created = GuestAccount::createFor( (int) $booking->guest_id, $guest['fields']['phone'], $guest['fields']['email'] );
		}

		return array(
			'reference' => (string) $booking->reference,
			'token'     => (string) $booking->public_token,
			'status'    => (string) $booking->status,
			'page_url'  => GuestBookingView::pageUrl( $booking ),
			'account'   => $created,
		);
	}

	/**
	 * Settings → Guest accounts → Require sign-in: refuse a visitor who is not.
	 *
	 * @param WP_User|null $account The signed-in visitor.
	 * @return void
	 * @throws DomainException 401 `sign_in_required`.
	 */
	public static function assertSignedIn( ?WP_User $account ): void {
		if ( $account || ! rtbp_setting( 'accounts', 'requireSignIn', false ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
		throw new DomainException( 'sign_in_required', __( 'Please sign in to your account to book.', 'radius-hotel-booking' ), 401 );
	}

	/**
	 * The signed-in visitor's own guest record, unless it is banned.
	 *
	 * @param Guest $guest  Linked guest.
	 * @param array $fields The booking's guest details.
	 * @return int Guest id.
	 */
	private static function bookable( Guest $guest, array $fields ): int {
		if ( Guest::BANNED === (string) $guest->standing ) {
			self::refuse( (int) $guest->id, $fields, 'banned' );
		}
		return (int) $guest->id;
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
			self::refuse( $banned, $fields, 'banned' );
		}

		$email = is_email( (string) $fields['email'] ) ? strtolower( (string) $fields['email'] ) : null;
		$rows  = $repo->duplicates( $e164, Phone::tail( (string) $fields['phone'] ), $email );
		$ids   = array_values( array_unique( array_map( static fn( $row ) => (int) $row['id'], $rows ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$guest = 1 === count( $ids ) ? $repo->find( $ids[0] ) : null;
		if ( ! $guest instanceof Guest ) {
			// The phone and the e-mail belong to two different guests.
			self::refuse( $ids[0], $fields, 'two_guests' );
		}
		if ( Guest::BANNED === (string) $guest->standing ) {
			self::refuse( (int) $guest->id, $fields, 'banned' );
		}

		// Which of the typed details led to this guest (both can).
		$matched = array();
		if ( in_array( 'phone', array_column( $rows, 'match' ), true ) ) {
			$matched[] = 'phone';
		}
		if ( null !== $email && strtolower( (string) $guest->email ) === $email ) {
			$matched[] = 'email';
		}

		$same_name = self::nameKey( $fields['first_name'] . $fields['last_name'] ) === self::nameKey( (string) $guest->first_name . (string) $guest->last_name );
		if ( ! $same_name ) {
			self::refuse( (int) $guest->id, $fields, 'name', $matched );
		}
		// The document when both sides have one; otherwise (none on file, or
		// the form does not require one and none was typed) the contact detail
		// on file: the same phone, or the same e-mail.
		$by_document = '' !== self::documentKey( (string) $guest->id_number )
			&& ( '' !== self::documentKey( $fields['id_number'] ) || 'required' === WebsiteSettings::guestField( 'id_document' ) );
		$same_id     = $by_document
			? (string) $guest->id_type === $fields['id_type'] && self::documentKey( (string) $guest->id_number ) === self::documentKey( $fields['id_number'] )
			: ( null !== $e164 && (string) $guest->phone_e164 === $e164 ) || ( null !== $email && strtolower( (string) $guest->email ) === $email );
		if ( ! $same_id ) {
			self::refuse( (int) $guest->id, $fields, $by_document ? 'document' : 'name', $matched );
		}
		return (int) $guest->id;
	}

	/**
	 * Refuse a web booking, say why, and log it for staff.
	 *
	 * The visitor learns what to change (which fields), never the details on
	 * file: no name, no document, nothing of the other guest. A banned guest
	 * gets the generic message only.
	 *
	 * @param int      $guest_id The guest on file concerned.
	 * @param array    $fields   What the visitor typed.
	 * @param string   $reason   banned|two_guests|name|document.
	 * @param string[] $matched  The typed fields that matched the guest (`phone`, `email`).
	 * @return never
	 * @throws DomainException 409 `booking_refused` (banned) or `guest_details_mismatch`, with field errors.
	 */
	private static function refuse( int $guest_id, array $fields, string $reason, array $matched = array() ) {
		rtbp_activity(
			'security.booking_refused',
			array(
				'type'  => 'guest',
				'id'    => $guest_id,
				'label' => trim( $fields['first_name'] . ' ' . $fields['last_name'] ),
			),
			array(
				'description' => 'banned' === $reason
					? __( 'A banned guest tried to book on the website; the booking was refused.', 'radius-hotel-booking' )
					: __( 'A website booking used this guest\'s phone or e-mail with other details; it was refused and the visitor was told which details to check.', 'radius-hotel-booking' ),
			)
		);

		if ( 'banned' === $reason ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict( 'booking_refused', __( 'We could not take this booking online. Please contact the hotel.', 'radius-hotel-booking' ) );
		}

		throw self::mismatch( $reason, $matched );
	}

	/**
	 * The "details do not agree" answer: a message saying what to change, and
	 * the fields to fix. Shared with BookingService (a match found on create).
	 *
	 * @param string   $reason  two_guests|name|document.
	 * @param string[] $matched The typed fields that matched (`phone`, `email`); empty = not known.
	 * @return DomainException 409 `guest_details_mismatch`.
	 */
	public static function mismatch( string $reason, array $matched = array() ): DomainException {
		$matched = $matched ? $matched : array( 'phone', 'email' );
		$contact = array(
			'phone' => __( 'This phone number is already on file with us under another name.', 'radius-hotel-booking' ),
			'email' => __( 'This e-mail is already on file with us under another name.', 'radius-hotel-booking' ),
		);

		if ( 'two_guests' === $reason ) {
			$message = __( 'Your phone number and e-mail belong to two different guests on file. Use the phone and e-mail of one person, or contact the hotel.', 'radius-hotel-booking' );
			$errors  = array(
				'phone' => __( 'This phone number belongs to another guest on file.', 'radius-hotel-booking' ),
				'email' => __( 'This e-mail belongs to another guest on file.', 'radius-hotel-booking' ),
			);
		} elseif ( 'document' === $reason ) {
			$message = __( 'Your name and contact details match an earlier booking, but the identity document does not. Enter the same document as last time, or contact the hotel.', 'radius-hotel-booking' );
			$errors  = array(
				'id_number' => __( 'This is not the document we have on file for you.', 'radius-hotel-booking' ),
			);
		} else {
			$message = in_array( 'phone', $matched, true ) && in_array( 'email', $matched, true )
				? __( 'Your phone number and e-mail are already on file with us under another name. Enter the name from your earlier booking, use your own phone and e-mail, or contact the hotel.', 'radius-hotel-booking' )
				: ( in_array( 'phone', $matched, true )
					? __( 'This phone number is already on file with us under another name. Enter the name from your earlier booking, use your own phone number, or contact the hotel.', 'radius-hotel-booking' )
					: __( 'This e-mail is already on file with us under another name. Enter the name from your earlier booking, use your own e-mail, or contact the hotel.', 'radius-hotel-booking' ) );
			$errors  = array_intersect_key( $contact, array_flip( $matched ) );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
		return new DomainException( 'guest_details_mismatch', $message, 409, $errors );
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
	 * The guest's details, checked against the website's fields (Settings →
	 * Website → Guest details: each required, optional or hidden; a hidden
	 * field is dropped).
	 *
	 * @param array $raw Raw fields.
	 * @return array{fields: array, errors: array<string, string>}
	 */
	private static function guestFields( array $raw ): array {
		$modes  = WebsiteSettings::guestFields();
		$fields = array(
			'first_name' => sanitize_text_field( (string) ( $raw['first_name'] ?? '' ) ),
			'last_name'  => sanitize_text_field( (string) ( $raw['last_name'] ?? '' ) ),
			'phone'      => sanitize_text_field( (string) ( $raw['phone'] ?? '' ) ),
			'email'      => sanitize_email( (string) ( $raw['email'] ?? '' ) ),
			'id_type'    => sanitize_key( (string) ( $raw['id_type'] ?? '' ) ),
			'id_number'  => sanitize_text_field( (string) ( $raw['id_number'] ?? '' ) ),
		);
		$raw_email = (string) ( $raw['email'] ?? '' );
		foreach ( array(
			'last_name'   => array( 'last_name' ),
			'phone'       => array( 'phone' ),
			'email'       => array( 'email' ),
			'id_document' => array( 'id_type', 'id_number' ),
		) as $field => $keys ) {
			if ( 'hidden' === $modes[ $field ] ) {
				foreach ( $keys as $key ) {
					$fields[ $key ] = '';
				}
				if ( 'email' === $field ) {
					$raw_email = '';
				}
			}
		}

		$errors = array();
		if ( '' === trim( $fields['first_name'] ) ) {
			$errors['first_name'] = __( 'Enter your first name.', 'radius-hotel-booking' );
		}
		if ( 'required' === $modes['last_name'] && '' === trim( $fields['last_name'] ) ) {
			$errors['last_name'] = __( 'Enter your last name.', 'radius-hotel-booking' );
		}
		if ( '' !== trim( $fields['phone'] ) || 'required' === $modes['phone'] ) {
			if ( null === Phone::toE164( $fields['phone'] ) ) {
				$errors['phone'] = __( 'Enter a phone number we can reach you on.', 'radius-hotel-booking' );
			}
		}
		// A document is a type and a number together: asked for when required, or once either is given.
		if ( 'required' === $modes['id_document'] || '' !== $fields['id_type'] || '' !== trim( $fields['id_number'] ) ) {
			if ( ! array_key_exists( $fields['id_type'], GuestService::idTypes() ) ) {
				$errors['id_type'] = __( 'Choose your identity document type.', 'radius-hotel-booking' );
			}
			if ( '' === trim( $fields['id_number'] ) ) {
				$errors['id_number'] = __( 'Enter the number of your identity document.', 'radius-hotel-booking' );
			}
		}
		if ( 'required' === $modes['email'] && '' === $raw_email ) {
			$errors['email'] = __( 'Enter your e-mail address.', 'radius-hotel-booking' );
		} elseif ( '' !== $raw_email && ! is_email( $fields['email'] ) ) {
			$errors['email'] = 'required' === $modes['email']
				? __( 'Enter a valid e-mail address.', 'radius-hotel-booking' )
				: __( 'Enter a valid e-mail address, or leave it empty.', 'radius-hotel-booking' );
		}
		return array(
			'fields' => $fields,
			'errors' => $errors,
		);
	}
}
