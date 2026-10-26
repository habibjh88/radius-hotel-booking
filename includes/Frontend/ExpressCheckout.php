<?php
/**
 * Express checkout on the website booking form: a returning guest proves
 * their phone number with a one-time code, and their details fill the form.
 *
 * @package RadiusTheme\RadiusHotelBooking\Frontend
 */

namespace RadiusTheme\RadiusHotelBooking\Frontend;

use RadiusTheme\RadiusHotelBooking\Emails\EmailSender;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Guest;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Settings\WebsiteSettings;
use RadiusTheme\RadiusHotelBooking\Support\Phone;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Guest accounts → Express checkout.
 *
 * A phone number alone never shows anyone's details (the legacy
 * `/customer/lookup?phone=` leaked ID numbers): start() sends a 6-digit code
 * to the guest's own e-mail on file — or by SMS, when an add-on answers
 * `rtbp_express_send_code` — and only verify() with that code returns them.
 *
 * - Known means a guest record with that number, else a WordPress account
 *   (GuestAccount::findUserByPhone(): any stored form of the number).
 * - An unknown number and a known one that cannot be reached get the same
 *   answer (`new`: just the phone is filled in), so the reply says no more
 *   than "a code was sent".
 * - Codes are kept hashed for 10 minutes, tied to the visitor's session
 *   cookie, 5 tries; one number gets at most 3 codes per 15 minutes.
 * - The proof (a token, 1 hour, same session) goes with the booking; the
 *   server fills empty fields from the record. The document number never
 *   reaches the browser.
 * - With "Verify with a code" off, start() answers `recognised` at once: a
 *   masked hint only (`H••• R•••`, `h•••@site.com`) and an unverified proof
 *   that fills the name and e-mail, but never the document on file and never
 *   links the booking to an account.
 */
class ExpressCheckout {

	/**
	 * Minutes a code is valid.
	 */
	const CODE_MINUTES = 10;

	/**
	 * Wrong codes before the code is dropped.
	 */
	const MAX_TRIES = 5;

	/**
	 * Codes one number may receive per window.
	 */
	const MAX_SENDS = 3;

	/**
	 * Whether the website offers it (Settings → Guest accounts), which needs
	 * the form's phone field (Settings → Website).
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return (bool) rtbp_setting( 'accounts', 'expressCheckout', true )
			&& 'hidden' !== WebsiteSettings::guestField( 'phone' );
	}

	/**
	 * Whether a code proves the phone first (Settings → Guest accounts).
	 *
	 * @return bool
	 */
	public static function verifies(): bool {
		return (bool) rtbp_setting( 'accounts', 'expressVerify', true );
	}

	/**
	 * Step 1: the number typed in the popup.
	 *
	 * @param string $phone   As typed.
	 * @param string $session The visitor's session key.
	 * @return array `{ status: 'new', phone }`, `{ status: 'code_sent', challenge, channel, to, phone }`
	 *               or, without verification, `{ status: 'recognised', token, hint: { name, email }, phone }`.
	 * @throws DomainException 422 an invalid number, 429 too many codes, 403 off.
	 */
	public static function start( string $phone, string $session ): array {
		self::assertEnabled();
		$e164 = Phone::toE164( $phone );
		if ( null === $e164 ) {
			$message = __( 'Enter a valid phone number.', 'radius-hotel-booking' );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'phone' => $message ), $message );
		}
		$new = array(
			'status' => 'new',
			'phone'  => $e164,
		);

		$target = self::target( $e164 );
		if ( ! $target ) {
			return $new;
		}
		if ( ! self::verifies() ) {
			return self::recognise( $target, $session );
		}

		$sends = (int) get_transient( self::sendsKey( $e164 ) );
		if ( $sends >= self::MAX_SENDS ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'too_many_codes', __( 'Too many codes were sent to this number. Please wait a few minutes, or fill in your details.', 'radius-hotel-booking' ), 429 );
		}

		$code      = (string) random_int( 100000, 999999 );
		$challenge = bin2hex( random_bytes( 16 ) );
		$sent      = self::send( $code, $target );
		if ( ! $sent ) {
			// Known but unreachable: answered like an unknown number.
			return $new;
		}

		set_transient( self::sendsKey( $e164 ), $sends + 1, 15 * MINUTE_IN_SECONDS );
		set_transient(
			self::challengeKey( $challenge ),
			array(
				'hash'     => self::hash( $code, $challenge ),
				'session'  => $session,
				'tries'    => 0,
				'guest_id' => $target['guest_id'],
				'user_id'  => $target['user_id'],
				'phone'    => $e164,
			),
			self::CODE_MINUTES * MINUTE_IN_SECONDS
		);

		rtbp_activity(
			'security.express_code_sent',
			self::subject( $target ),
			array(
				/* translators: %s: how the code was sent (e-mail, SMS…). */
				'description' => sprintf( __( 'Sent an express checkout code by %s', 'radius-hotel-booking' ), $sent['channel'] ),
			)
		);

		return array(
			'status'    => 'code_sent',
			'challenge' => $challenge,
			'channel'   => $sent['channel'],
			'to'        => $sent['to'],
			'phone'     => $e164,
		);
	}

	/**
	 * Step 2: the code the guest received.
	 *
	 * @param string $challenge From start().
	 * @param string $code      As typed.
	 * @param string $session   The visitor's session key.
	 * @return array `{ token, details }` (GuestAccount::details() shape).
	 * @throws DomainException 422 a wrong code, 410 expired or locked, 403 off.
	 */
	public static function verify( string $challenge, string $code, string $session ): array {
		self::assertEnabled();
		$key   = self::challengeKey( $challenge );
		$state = preg_match( '/^[a-f0-9]{32}$/', $challenge ) ? get_transient( $key ) : false;
		if ( ! is_array( $state ) || '' === $session || ! hash_equals( (string) $state['session'], $session ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'code_expired', __( 'This code has expired. Ask for a new one.', 'radius-hotel-booking' ), 410 );
		}

		$code = preg_replace( '/\D+/', '', $code );
		if ( ! hash_equals( (string) $state['hash'], self::hash( $code, $challenge ) ) ) {
			++$state['tries'];
			if ( $state['tries'] >= self::MAX_TRIES ) {
				delete_transient( $key );
				rtbp_activity( 'security.express_locked', self::subject( $state ), array( 'description' => __( 'Express checkout code locked after wrong tries', 'radius-hotel-booking' ) ) );
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw new DomainException( 'code_expired', __( 'Too many wrong codes. Ask for a new one.', 'radius-hotel-booking' ), 410 );
			}
			set_transient( $key, $state, self::CODE_MINUTES * MINUTE_IN_SECONDS );
			$message = __( 'This code is not right. Check it and try again.', 'radius-hotel-booking' );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'code' => $message ), $message );
		}

		delete_transient( $key );
		$token = self::issueProof( $session, (int) $state['guest_id'], (int) $state['user_id'], true );

		$details = self::details( (int) $state['guest_id'], (int) $state['user_id'] );
		// The number just proved, in E.164: an older record may hold `01 92…`,
		// which the form would read in the wrong country.
		$details['phone'] = (string) $state['phone'];
		return array(
			'token'   => $token,
			'details' => $details,
		);
	}

	/**
	 * A known number without verification: a masked hint and an unverified proof.
	 *
	 * @param array  $target  target().
	 * @param string $session The visitor's session key.
	 * @return array `{ status: 'recognised', token, hint, phone }`.
	 */
	private static function recognise( array $target, string $session ): array {
		rtbp_activity(
			'security.express_recognised',
			self::subject( $target ),
			array( 'description' => __( 'Recognised a guest at express checkout without a code', 'radius-hotel-booking' ) )
		);
		return array(
			'status' => 'recognised',
			'token'  => self::issueProof( $session, $target['guest_id'], $target['user_id'], false ),
			'hint'   => array(
				'name'  => self::maskName( $target['name'] ),
				'email' => '' !== $target['email'] ? self::maskEmail( $target['email'] ) : '',
			),
			'phone'  => $target['phone'],
		);
	}

	/**
	 * Store the proof a booking carries (1 hour, this session).
	 *
	 * @param string $session  The visitor's session key.
	 * @param int    $guest_id Guest record (0 = none).
	 * @param int    $user_id  Account (0 = none).
	 * @param bool   $verified Proved with a code.
	 * @return string Token.
	 */
	private static function issueProof( string $session, int $guest_id, int $user_id, bool $verified ): string {
		$token = bin2hex( random_bytes( 16 ) );
		set_transient(
			self::proofKey( $token ),
			array(
				'session'  => $session,
				'guest_id' => $guest_id,
				'user_id'  => $user_id,
				'verified' => $verified,
			),
			HOUR_IN_SECONDS
		);
		return $token;
	}

	/**
	 * The record a booking's proof stands for (PublicBookingService).
	 *
	 * @param string $token   From verify() or start() (no code).
	 * @param string $session The visitor's session key.
	 * @return array{ guest: Guest|null, user: WP_User|null, verified: bool }|null Null when the proof is missing or not this visitor's.
	 */
	public static function proof( string $token, string $session ): ?array {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) || '' === $session ) {
			return null;
		}
		$state = get_transient( self::proofKey( $token ) );
		if ( ! is_array( $state ) || ! hash_equals( (string) $state['session'], $session ) ) {
			return null;
		}
		$guest = $state['guest_id'] ? ( new GuestRepository() )->find( (int) $state['guest_id'] ) : null;
		$user  = $state['user_id'] ? get_userdata( (int) $state['user_id'] ) : false;
		return array(
			'guest'    => $guest instanceof Guest ? $guest : null,
			'user'     => $user instanceof WP_User ? $user : null,
			// Older proofs (before the setting) were all verified.
			'verified' => (bool) ( $state['verified'] ?? true ),
		);
	}

	/**
	 * The guest details a record gives the form and the booking: a guest
	 * record first, completed by its account.
	 *
	 * @param Guest|null   $guest Guest record.
	 * @param WP_User|null $user  Account.
	 * @return array{ first_name: string, last_name: string, phone: string, email: string, id_type: string, id_number: string }
	 */
	public static function fields( ?Guest $guest, ?WP_User $user ): array {
		if ( $user ) {
			$fields = GuestAccount::fields( $user );
			if ( ! $guest || (int) $guest->id === (int) ( GuestAccount::guest( $user )->id ?? 0 ) ) {
				return $fields;
			}
		}
		$own = array(
			'first_name' => (string) $guest->first_name,
			'last_name'  => (string) $guest->last_name,
			'phone'      => (string) $guest->phone,
			'email'      => $guest->email_is_placeholder ? '' : (string) $guest->email,
			'id_type'    => (string) $guest->id_type,
			'id_number'  => (string) $guest->id_number,
		);
		if ( isset( $fields ) ) {
			foreach ( $own as $key => $value ) {
				if ( '' === trim( $value ) && 'id_' !== substr( $key, 0, 3 ) ) {
					$own[ $key ] = $fields[ $key ];
				}
			}
		}
		return $own;
	}

	/**
	 * What verify() hands the form: fields() without the document number.
	 *
	 * @param int $guest_id Guest record (0 = none).
	 * @param int $user_id  Account (0 = none).
	 * @return array
	 */
	private static function details( int $guest_id, int $user_id ): array {
		$guest  = $guest_id ? ( new GuestRepository() )->find( $guest_id ) : null;
		$user   = $user_id ? get_userdata( $user_id ) : false;
		$guest  = $guest instanceof Guest ? $guest : null;
		$user   = $user instanceof WP_User ? $user : null;
		$fields = self::fields( $guest, $user );
		$number = preg_replace( '/[^A-Za-z0-9]/', '', $fields['id_number'] );
		return array(
			'first_name' => $fields['first_name'],
			'last_name'  => $fields['last_name'],
			'phone'      => $fields['phone'],
			'email'      => $fields['email'],
			'id_type'    => $fields['id_type'],
			'id_on_file' => '' !== $number && '' !== $fields['id_type'],
			'id_hint'    => '' !== $number ? '•••• ' . substr( $number, -4 ) : '',
			// A guest record: the booking goes to it as it is (the form locks the details).
			'on_file'    => $guest || ( $user && GuestAccount::guest( $user ) ),
		);
	}

	/**
	 * Who has this number: a guest record (only one), else an account; with
	 * where a code can go.
	 *
	 * @param string $e164 Number.
	 * @return array|null `{ guest_id, user_id, email, phone, name }`.
	 */
	private static function target( string $e164 ): ?array {
		$rows  = ( new GuestRepository() )->duplicates( $e164, Phone::tail( $e164 ), null );
		$ids   = array_values( array_unique( array_map( static fn( $row ) => (int) $row['id'], array_filter( $rows, static fn( $row ) => 'phone' === ( $row['match'] ?? '' ) ) ) ) );
		$guest = 1 === count( $ids ) ? ( new GuestRepository() )->find( $ids[0] ) : null;
		if ( $guest instanceof Guest && Guest::BANNED === (string) $guest->standing ) {
			// Booking is refused anyway; nothing to speed up.
			return null;
		}
		$user_id = $guest instanceof Guest && (int) $guest->wp_user_id ? (int) $guest->wp_user_id : GuestAccount::findUserByPhone( $e164, true );
		$user    = $user_id ? get_userdata( $user_id ) : false;
		$user    = $user instanceof WP_User ? $user : null;
		if ( ! $guest instanceof Guest && ! $user ) {
			return null;
		}
		$fields = self::fields( $guest instanceof Guest ? $guest : null, $user );
		return array(
			'guest_id' => $guest instanceof Guest ? (int) $guest->id : 0,
			'user_id'  => $user ? (int) $user->ID : 0,
			'email'    => is_email( $fields['email'] ) ? $fields['email'] : '',
			'phone'    => $e164,
			'name'     => trim( $fields['first_name'] . ' ' . $fields['last_name'] ),
		);
	}

	/**
	 * Send the code: an add-on first (SMS…), else the e-mail on file.
	 *
	 * @param string $code   Code.
	 * @param array  $target target().
	 * @return array|null `{ channel, to }` (masked), or null when it could not be sent.
	 */
	private static function send( string $code, array $target ): ?array {
		/**
		 * Send an express checkout code another way than e-mail (SMS,
		 * WhatsApp…). Return `[ 'channel' => 'SMS', 'to' => '•••• 56' ]` when
		 * sent; null to let the e-mail (if any) go instead.
		 *
		 * @param array|null $sent   Null.
		 * @param string     $code   The 6-digit code.
		 * @param array      $target `{ guest_id, user_id, email, phone (E.164), name }`.
		 */
		$sent = apply_filters( 'rtbp_express_send_code', null, $code, $target );
		if ( is_array( $sent ) && ! empty( $sent['channel'] ) ) {
			return array(
				'channel' => (string) $sent['channel'],
				'to'      => (string) ( $sent['to'] ?? '' ),
			);
		}
		if ( '' === $target['email'] ) {
			return null;
		}

		$hotel = (string) rtbp_setting( 'general', 'companyName', get_bloginfo( 'name' ) );
		/* translators: 1: the code, 2: hotel name. */
		$subject = sprintf( __( '%1$s is your %2$s booking code', 'radius-hotel-booking' ), $code, $hotel );
		// The plugin's e-mails are HTML (EmailSender).
		$message = '<p>' . esc_html(
			sprintf(
				/* translators: %s: guest name. */
				__( 'Hello %s,', 'radius-hotel-booking' ),
				'' !== $target['name'] ? $target['name'] : __( 'there', 'radius-hotel-booking' )
			)
		) . '</p><p>' . esc_html__( 'Your code to fill in your details on our booking form is:', 'radius-hotel-booking' ) . '</p>'
			. '<p style="font-size:28px;font-weight:700;letter-spacing:6px;">' . esc_html( $code ) . '</p><p>' . esc_html(
				sprintf(
					/* translators: %d: minutes the code is valid. */
					__( 'It is valid for %d minutes. If you did not ask for it, you can ignore this e-mail: nobody can see your details without it.', 'radius-hotel-booking' ),
					self::CODE_MINUTES
				)
			) . '</p>';
		if ( ! EmailSender::send( $target['email'], $subject, $message ) ) {
			return null;
		}
		return array(
			'channel' => __( 'e-mail', 'radius-hotel-booking' ),
			'to'      => self::maskEmail( $target['email'] ),
		);
	}

	/**
	 * `H••• R•••`.
	 *
	 * @param string $name Full name.
	 * @return string
	 */
	private static function maskName( string $name ): string {
		$words = preg_split( '/\s+/u', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );
		return implode( ' ', array_map( static fn( $word ) => mb_substr( $word, 0, 1 ) . '•••', $words ) );
	}

	/**
	 * `a•••@gmail.com`.
	 *
	 * @param string $email E-mail.
	 * @return string
	 */
	private static function maskEmail( string $email ): string {
		list( $local, $domain ) = array_pad( explode( '@', $email, 2 ), 2, '' );
		return mb_substr( $local, 0, 1 ) . '•••@' . $domain;
	}

	/**
	 * The activity subject: the guest record, else the account.
	 *
	 * @param array $target `{ guest_id, user_id }`.
	 * @return array
	 */
	private static function subject( array $target ): array {
		return (int) ( $target['guest_id'] ?? 0 )
			? array(
				'type'  => 'guest',
				'id'    => (int) $target['guest_id'],
				'label' => '',
			)
			: array(
				'type'  => 'user',
				'id'    => (int) ( $target['user_id'] ?? 0 ),
				'label' => '',
			);
	}

	/**
	 * Refuse when Settings → Guest accounts → Express checkout is off.
	 *
	 * @return void
	 * @throws DomainException 403.
	 */
	private static function assertEnabled(): void {
		if ( ! self::enabled() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'express_off', __( 'Express checkout is not available.', 'radius-hotel-booking' ), 403 );
		}
	}

	/**
	 * The code's hash (never stored as typed).
	 *
	 * @param string $code      Code.
	 * @param string $challenge Challenge.
	 * @return string
	 */
	private static function hash( string $code, string $challenge ): string {
		return hash_hmac( 'sha256', $code, wp_salt( 'nonce' ) . $challenge );
	}

	/**
	 * Transient keys (`rtbp_` prefix: removed with the plugin's data).
	 *
	 * @param string $id Id.
	 * @return string
	 */
	private static function challengeKey( string $id ): string {
		return 'rtbp_xc_' . $id;
	}

	/**
	 * Proof transient key.
	 *
	 * @param string $id Id.
	 * @return string
	 */
	private static function proofKey( string $id ): string {
		return 'rtbp_xp_' . $id;
	}

	/**
	 * Per-number send counter key.
	 *
	 * @param string $e164 Number.
	 * @return string
	 */
	private static function sendsKey( string $e164 ): string {
		return 'rtbp_xs_' . md5( $e164 );
	}
}
