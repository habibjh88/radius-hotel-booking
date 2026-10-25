<?php
/**
 * Guests' WordPress accounts on the website booking form.
 *
 * @package RadiusTheme\RadiusHotelBooking\Frontend
 */

namespace RadiusTheme\RadiusHotelBooking\Frontend;

use RadiusTheme\RadiusHotelBooking\Models\Guest;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Settings\AccountSettings;
use RadiusTheme\RadiusHotelBooking\Support\Phone;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Guest accounts.
 *
 * Existing WordPress users (a shop's customers, say) are **not copied**: the
 * first time someone books signed in, their account is linked to a guest
 * record (`guests.wp_user_id`), found by their details or created; later
 * bookings reuse it and the form is filled in from it.
 *
 * The public API runs without a nonce (cached pages), so WordPress treats
 * those requests as anonymous. The account is read here from the logged-in
 * cookie instead, and a booking only counts as the account's when it carries
 * the token `GET public/account` handed out (`X-RTBP-Account`), so another
 * site cannot make a signed-in visitor's browser book in their name.
 *
 * Never signs anyone in: an account found by phone or e-mail for a visitor who
 * is not signed in is reported, not used (the legacy auto-login was an
 * account takeover).
 *
 * Phones are compared as numbers, not text: an older shop kept whatever was
 * typed (`0707123456`, `2250707123456`, `+225 07 07…`), the booking form
 * sends E.164. phoneLogins() lists the forms one number can be stored under,
 * and a guest can sign in with their phone written any of those ways.
 */
class GuestAccount {

	/**
	 * The nonce action of the account token.
	 */
	const NONCE = 'rtbp_account';

	/**
	 * The request header carrying the token.
	 */
	const HEADER = 'X-RTBP-Account';

	/**
	 * Hook in: sign in with a phone number written any way.
	 *
	 * @return void
	 */
	public static function init(): void {
		// After WordPress's own checks (20): only when they did not find the username as typed.
		add_filter( 'authenticate', array( self::class, 'phoneSignIn' ), 30, 3 );
	}

	/**
	 * `authenticate`: a username that is not an account but reads as a phone
	 * number signs in the one account with that number — its username in any
	 * of phoneLogins()' forms, or its saved phone.
	 *
	 * @param \WP_User|\WP_Error|null $user     So far.
	 * @param string                  $username As typed.
	 * @param string                  $password As typed.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function phoneSignIn( $user, $username, $password ) {
		$username = trim( (string) $username );
		if ( $user instanceof WP_User || '' === $username || '' === (string) $password ) {
			return $user;
		}
		if ( username_exists( $username ) || is_email( $username ) || ! preg_match( '/^\+?[\d\s().\-]{6,}$/', $username ) ) {
			return $user;
		}
		$id = self::findUserByPhone( $username, true );
		if ( ! $id ) {
			return $user;
		}
		$found = wp_authenticate_username_password( null, (string) get_userdata( $id )->user_login, (string) $password );
		if ( is_wp_error( $found ) ) {
			// Not WordPress's message, which would name the account's username.
			return new \WP_Error( 'incorrect_password', __( '<strong>Error:</strong> The phone number or password is incorrect.', 'radius-hotel-booking' ) );
		}
		return $found;
	}

	/**
	 * The usernames one phone number can be stored under: its digits in full
	 * (`2250707123456`) and, for a number of the hotel's own country, the
	 * national forms (`0707123456`; with the trunk `0` added when the number
	 * drops it). An add-on adds local history (old numbering plans…).
	 *
	 * @param string $phone As typed (any form).
	 * @return string[]
	 */
	public static function phoneLogins( string $phone ): array {
		$e164 = Phone::toE164( $phone );
		$out  = array( preg_replace( '/\D+/', '', $phone ) );
		if ( null !== $e164 ) {
			$full    = substr( $e164, 1 );
			$country = Phone::defaultCountry();
			$out[]   = $full;
			if ( 0 === strpos( $full, $country ) ) {
				$national = substr( $full, strlen( $country ) );
				$out[]    = $national;
				$out[]    = '0' === ( $national[0] ?? '' ) ? $national : '0' . $national;
			}
		}

		/**
		 * The usernames a phone number may be stored under.
		 *
		 * @param string[]    $logins Candidates.
		 * @param string|null $e164   The number in E.164, or null.
		 * @param string      $phone  As typed.
		 */
		$out = (array) apply_filters( 'rtbp_account_phone_logins', $out, $e164, $phone );
		return array_values( array_unique( array_filter( array_map( 'strval', $out ), static fn( $login ) => strlen( $login ) >= 6 ) ) );
	}

	/**
	 * The account of a phone number: a username in one of phoneLogins()'
	 * forms, else an account whose saved phone is the same number.
	 *
	 * @param string $phone  As typed.
	 * @param bool   $unique Only an unambiguous match (signing in): two
	 *                       accounts with that saved phone give none.
	 * @return int User id, or 0.
	 */
	public static function findUserByPhone( string $phone, bool $unique = false ): int {
		foreach ( self::phoneLogins( $phone ) as $login ) {
			$found = get_user_by( 'login', $login );
			if ( $found ) {
				return (int) $found->ID;
			}
		}

		$e164 = Phone::toE164( $phone );
		$key  = (string) apply_filters( 'rtbp_account_phone_meta_key', 'billing_phone' );
		$tail = Phone::tail( (string) $e164 );
		if ( null === $e164 || '' === $key || '' === $tail ) {
			return 0;
		}
		global $wpdb;
		// Saved phones are free text: narrow by the last 8 digits, then compare as numbers.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one indexed meta key, read once per booking or sign-in.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND REPLACE( REPLACE( REPLACE( REPLACE( REPLACE( meta_value, ' ', '' ), '-', '' ), '.', '' ), '(', '' ), ')', '' ) LIKE %s",
				$key,
				'%' . $wpdb->esc_like( $tail )
			)
		);
		$ids = array();
		foreach ( (array) $rows as $row ) {
			if ( Phone::toE164( (string) $row->meta_value ) === $e164 ) {
				$ids[ (int) $row->user_id ] = true;
			}
		}
		$ids = array_keys( $ids );
		if ( ! $ids || ( $unique && count( $ids ) > 1 ) ) {
			return 0;
		}
		return (int) $ids[0];
	}

	/**
	 * The signed-in visitor, from the logged-in cookie.
	 *
	 * @return WP_User|null
	 */
	public static function user(): ?WP_User {
		$id   = (int) wp_validate_auth_cookie( '', 'logged_in' );
		$user = $id ? get_userdata( $id ) : false;
		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * A fresh token for the signed-in visitor.
	 *
	 * @param WP_User $user Visitor.
	 * @return string
	 */
	public static function token( WP_User $user ): string {
		return (string) self::asUser( $user, static fn() => wp_create_nonce( self::NONCE ) );
	}

	/**
	 * The signed-in visitor when the request carries their token, else null.
	 *
	 * @param string $token Header value.
	 * @return WP_User|null
	 */
	public static function verified( string $token ): ?WP_User {
		$user = self::user();
		if ( ! $user || '' === $token ) {
			return null;
		}
		$valid = self::asUser( $user, static fn() => wp_verify_nonce( $token, self::NONCE ) );
		return $valid ? $user : null;
	}

	/**
	 * Run something as the visitor (nonces are per user), then go back to the
	 * anonymous request: bookings made here must not record them as staff.
	 *
	 * @param WP_User  $user Visitor.
	 * @param callable $run  Callback.
	 * @return mixed
	 */
	private static function asUser( WP_User $user, callable $run ) {
		$before = get_current_user_id();
		wp_set_current_user( $user->ID );
		try {
			return $run();
		} finally {
			wp_set_current_user( $before );
		}
	}

	/**
	 * What the account says about the guest. Free reads the WordPress names,
	 * the account e-mail and the common `billing_*` fields; an add-on adds or
	 * corrects fields (an ID document kept in user meta, placeholder e-mails…).
	 *
	 * @param WP_User $user Account.
	 * @return array{ first_name: string, last_name: string, phone: string, email: string, id_type: string, id_number: string }
	 */
	public static function profile( WP_User $user ): array {
		$meta    = static fn( string $key ) => trim( (string) get_user_meta( $user->ID, $key, true ) );
		$profile = array(
			'first_name' => '' !== $meta( 'billing_first_name' ) ? $meta( 'billing_first_name' ) : $meta( 'first_name' ),
			'last_name'  => '' !== $meta( 'billing_last_name' ) ? $meta( 'billing_last_name' ) : $meta( 'last_name' ),
			'phone'      => $meta( 'billing_phone' ),
			'email'      => '' !== $meta( 'billing_email' ) ? $meta( 'billing_email' ) : (string) $user->user_email,
			'id_type'    => '',
			'id_number'  => '',
		);

		/**
		 * The guest details a WordPress account gives the booking form.
		 *
		 * @param array   $profile `{ first_name, last_name, phone, email, id_type, id_number }`.
		 * @param WP_User $user    Account.
		 */
		$profile = (array) apply_filters( 'rtbp_account_guest_profile', $profile, $user );

		$out = array();
		foreach ( array( 'first_name', 'last_name', 'phone', 'email', 'id_type', 'id_number' ) as $key ) {
			$out[ $key ] = trim( sanitize_text_field( (string) ( $profile[ $key ] ?? '' ) ) );
		}
		$out['email'] = is_email( $out['email'] ) ? $out['email'] : '';
		return $out;
	}

	/**
	 * The guest linked to an account, if any.
	 *
	 * @param WP_User $user Account.
	 * @return Guest|null
	 */
	public static function guest( WP_User $user ): ?Guest {
		return ( new GuestRepository() )->findByUser( (int) $user->ID );
	}

	/**
	 * The details the form starts with: the linked guest's, completed by the
	 * account's. The document number itself never leaves the server.
	 *
	 * @param WP_User $user Account.
	 * @return array{ first_name: string, last_name: string, phone: string, email: string, id_type: string, id_on_file: bool, id_hint: string }
	 */
	public static function details( WP_User $user ): array {
		$fields = self::fields( $user );
		$number = preg_replace( '/[^A-Za-z0-9]/', '', $fields['id_number'] );
		return array(
			'first_name' => $fields['first_name'],
			'last_name'  => $fields['last_name'],
			'phone'      => $fields['phone'],
			'email'      => $fields['email'],
			'id_type'    => $fields['id_type'],
			'id_on_file' => '' !== $number && '' !== $fields['id_type'],
			// The last four characters, so the guest knows which document it is.
			'id_hint'    => '' !== $number ? '•••• ' . substr( $number, -4 ) : '',
		);
	}

	/**
	 * The account's guest details: the linked guest's first, the profile for
	 * whatever the guest record lacks.
	 *
	 * @param WP_User $user Account.
	 * @return array
	 */
	public static function fields( WP_User $user ): array {
		$profile = self::profile( $user );
		$guest   = self::guest( $user );
		if ( ! $guest ) {
			return $profile;
		}
		$own = array(
			'first_name' => (string) $guest->first_name,
			'last_name'  => (string) $guest->last_name,
			'phone'      => (string) $guest->phone,
			'email'      => $guest->email_is_placeholder ? '' : (string) $guest->email,
			'id_type'    => (string) $guest->id_type,
			'id_number'  => (string) $guest->id_number,
		);
		foreach ( $own as $key => $value ) {
			if ( '' === trim( $value ) ) {
				$own[ $key ] = $profile[ $key ];
			}
		}
		// A document is a type and a number together, never one from each source.
		if ( '' === trim( (string) $guest->id_number ) ) {
			$own['id_type']   = $profile['id_type'];
			$own['id_number'] = $profile['id_number'];
		}
		return $own;
	}

	/**
	 * Link an account to a guest record (only one that is not linked yet).
	 *
	 * @param WP_User $user     Account.
	 * @param int     $guest_id Guest.
	 * @return void
	 */
	public static function link( WP_User $user, int $guest_id ): void {
		$repo  = new GuestRepository();
		$guest = $guest_id ? $repo->find( $guest_id ) : null;
		if ( ! $guest instanceof Guest || (int) $guest->wp_user_id || self::guest( $user ) ) {
			return;
		}
		$repo->update( $guest_id, array( 'wp_user_id' => (int) $user->ID ) );
		rtbp_activity(
			'guests.account_linked',
			$guest,
			array(
				'after'       => array( 'wp_user_id' => (int) $user->ID ),
				/* translators: 1: guest name, 2: account username. */
				'description' => sprintf( __( 'Linked %1$s to the website account %2$s', 'radius-hotel-booking' ), $guest->fullName(), $user->user_login ),
			)
		);
	}

	/**
	 * After a booking by a visitor who was not signed in (Settings → Create
	 * an account): make an account for a guest the site does not know yet.
	 *
	 * @param int    $guest_id Guest of the booking.
	 * @param string $phone    The phone as typed.
	 * @param string $email    The e-mail as typed ('' when none).
	 * @return string `created`, `exists` (an account has this phone or e-mail:
	 *                never used without signing in), `no_email` (no way to set a
	 *                password) or `` (off, or already linked).
	 */
	public static function createFor( int $guest_id, string $phone, string $email ): string {
		if ( ! rtbp_setting( 'accounts', 'createAccounts', false ) ) {
			return '';
		}
		$guest = $guest_id ? ( new GuestRepository() )->find( $guest_id ) : null;
		if ( ! $guest instanceof Guest || (int) $guest->wp_user_id ) {
			return '';
		}
		$email = is_email( $email ) ? strtolower( $email ) : '';
		if ( self::findUser( $phone, $email ) ) {
			return 'exists';
		}
		if ( '' === $email ) {
			return 'no_email';
		}

		// The full international digits (`2250707123456`): one form for every
		// country; guests can sign in with the number written any way.
		$e164   = Phone::toE164( $phone );
		$digits = null !== $e164 ? substr( $e164, 1 ) : preg_replace( '/\D+/', '', $phone );
		$login  = 'phone' === rtbp_setting( 'accounts', 'usernameFrom', 'phone' ) && '' !== $digits ? $digits : $email;
		if ( username_exists( $login ) ) {
			$login = $email;
		}
		$role    = (string) rtbp_setting( 'accounts', 'accountRole', AccountSettings::defaultRole() );
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24 ),
				'first_name'   => (string) $guest->first_name,
				'last_name'    => (string) $guest->last_name,
				'display_name' => $guest->fullName(),
				'role'         => isset( AccountSettings::roles()[ $role ] ) ? $role : AccountSettings::defaultRole(),
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return '';
		}

		/**
		 * The user meta key the phone of a new account is kept in (`billing_phone`,
		 * which shops already use; '' keeps none).
		 *
		 * @param string $key Meta key.
		 */
		$phone_key = (string) apply_filters( 'rtbp_account_phone_meta_key', 'billing_phone' );
		if ( '' !== $phone_key && '' !== trim( $phone ) ) {
			update_user_meta( $user_id, $phone_key, sanitize_text_field( $phone ) );
		}

		( new GuestRepository() )->update( $guest_id, array( 'wp_user_id' => (int) $user_id ) );
		rtbp_activity(
			'guests.account_created',
			$guest,
			array(
				'after'       => array(
					'wp_user_id' => (int) $user_id,
					'role'       => $role,
				),
				/* translators: 1: guest name, 2: account username. */
				'description' => sprintf( __( 'Created the website account %2$s for %1$s', 'radius-hotel-booking' ), $guest->fullName(), $login ),
			)
		);

		// WordPress's own e-mail: a link to set a password (the guest is not signed in).
		wp_new_user_notification( $user_id, null, 'user' );

		/**
		 * Fires after a guest account was created by a website booking.
		 *
		 * @param int   $user_id  New user.
		 * @param Guest $guest    Guest.
		 */
		do_action( 'rtbp_guest_account_created', (int) $user_id, $guest );

		return 'created';
	}

	/**
	 * An account with this phone or e-mail: the e-mail, the phone as a
	 * username in any of its forms or as an account's saved phone
	 * (findUserByPhone()), or what an add-on finds.
	 *
	 * @param string $phone Phone as typed.
	 * @param string $email E-mail ('' when none).
	 * @return int User id, or 0.
	 */
	public static function findUser( string $phone, string $email ): int {
		if ( '' !== $email ) {
			$id = (int) email_exists( $email );
			if ( $id ) {
				return $id;
			}
		}
		$id = self::findUserByPhone( $phone );
		if ( $id ) {
			return $id;
		}

		/**
		 * Find the account of a phone or e-mail another way (0 = none).
		 *
		 * @param int    $user_id 0.
		 * @param string $phone   Phone as typed.
		 * @param string $email   E-mail ('' when none).
		 */
		return (int) apply_filters( 'rtbp_account_find_user', 0, $phone, $email );
	}

	/**
	 * `GET public/account`: who is signed in, their token and the details the
	 * form starts with. Never cached (it is personal).
	 *
	 * @return array
	 */
	public static function payload(): array {
		$user = self::user();
		if ( ! $user ) {
			return array( 'signed_in' => false );
		}
		return array(
			'signed_in'  => true,
			'token'      => self::token( $user ),
			'name'       => (string) $user->display_name,
			// Nonced for this user, so made as them.
			'logout_url' => (string) self::asUser( $user, static fn() => wp_logout_url() ),
			'details'    => self::details( $user ),
		);
	}

	/**
	 * What the booking form needs to know about accounts (site params).
	 *
	 * @return array
	 */
	public static function siteParams(): array {
		return array(
			'requireSignIn' => (bool) rtbp_setting( 'accounts', 'requireSignIn', false ),
			'showSignIn'    => (bool) rtbp_setting( 'accounts', 'showSignIn', true ),
			'express'       => ExpressCheckout::enabled(),
			'loginUrl'      => wp_login_url(),
			'registerUrl'   => get_option( 'users_can_register' ) ? wp_registration_url() : '',
		);
	}
}
