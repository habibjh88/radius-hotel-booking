<?php
/**
 * Settings → Guest accounts: WordPress accounts and the website booking form.
 *
 * @package RadiusTheme\RadiusHotelBooking\Settings
 */

namespace RadiusTheme\RadiusHotelBooking\Settings;

use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The `accounts` section (option `rtbp_accounts_settings`). A guest's
 * WordPress account is linked to their guest record the first time they book
 * signed in (Frontend\GuestAccount): nothing is copied beforehand.
 */
class AccountSettings {

	/**
	 * The section's schema.
	 *
	 * @return array
	 */
	public static function schema(): array {
		return array(
			// Only signed-in visitors can book on the website.
			'requireSignIn'  => array(
				'type'    => 'bool',
				'default' => false,
			),
			// A "Sign in" link on the booking form; signed-in guests have their details filled in.
			'showSignIn'     => array(
				'type'    => 'bool',
				'default' => true,
			),
			// Express checkout: a returning guest proves their phone with a one-time code and their details fill the form.
			'expressCheckout' => array(
				'type'    => 'bool',
				'default' => true,
			),
			// Express checkout sends the code first; off: a known number is recognised at once (masked hint, no document on file).
			'expressVerify'   => array(
				'type'    => 'bool',
				'default' => true,
			),
			// A website booking from an unknown phone and e-mail creates a WordPress account.
			'createAccounts' => array(
				'type'    => 'bool',
				'default' => false,
			),
			// What new accounts sign in with: `phone` (digits) or `email`.
			'usernameFrom'   => array(
				'type'    => 'enum',
				'options' => array( 'phone', 'email' ),
				'default' => 'phone',
			),
			// Role of new accounts: one of roles() (never one that can edit or manage the site).
			'accountRole'    => array(
				'type'     => 'string',
				'default'  => static fn() => self::defaultRole(),
				'sanitize' => array( self::class, 'role' ),
			),
		);
	}

	/**
	 * The roles a guest account may get: the plugin's Hotel Guest and
	 * Subscriber, when the site has them. A list, not a test of capabilities:
	 * some plugins give a role its powers by its name alone (a "kitchen
	 * manager" with only `read`), so nothing else is safe to assume.
	 *
	 * @return array<string, string> Slug => translated name.
	 */
	public static function roles(): array {
		/**
		 * The roles a website guest's account may be given. Add a role only
		 * when it grants nothing beyond what a guest should have.
		 *
		 * @param string[] $slugs Role slugs.
		 */
		$slugs = (array) apply_filters( 'rtbp_account_roles', array( Capabilities::GUEST_ROLE, 'subscriber' ) );
		$all   = wp_roles()->roles;
		$out   = array();
		foreach ( $slugs as $slug ) {
			$slug = (string) $slug;
			if ( isset( $all[ $slug ] ) && ! in_array( $slug, array( 'administrator', 'editor', 'author', 'contributor', 'shop_manager' ), true ) ) {
				$out[ $slug ] = translate_user_role( (string) $all[ $slug ]['name'] );
			}
		}
		return $out;
	}

	/**
	 * Hotel Guest, else Subscriber.
	 *
	 * @return string
	 */
	public static function defaultRole(): string {
		$roles = self::roles();
		foreach ( array( Capabilities::GUEST_ROLE, 'subscriber' ) as $slug ) {
			if ( isset( $roles[ $slug ] ) ) {
				return $slug;
			}
		}
		return (string) ( array_key_first( $roles ) ?? 'subscriber' );
	}

	/**
	 * The role new accounts get: the one chosen, else the default when it is
	 * no longer offered (an earlier version offered WooCommerce's Customer).
	 *
	 * @return string
	 */
	public static function accountRole(): string {
		$role = (string) rtbp_setting( 'accounts', 'accountRole', '' );
		return isset( self::roles()[ $role ] ) ? $role : self::defaultRole();
	}

	/**
	 * `accountRole`: a role from roles(). Any other role that can do more
	 * than read is refused; one that cannot (gone, or no longer offered)
	 * falls back to the default.
	 *
	 * @param mixed $value Input.
	 * @return string|WP_Error
	 */
	public static function role( $value ) {
		$value = sanitize_key( (string) $value );
		if ( isset( self::roles()[ $value ] ) ) {
			return $value;
		}
		$role = '' !== $value ? get_role( $value ) : null;
		if ( $role && array_diff( array_keys( array_filter( (array) $role->capabilities ) ), array( 'read', 'level_0' ) ) ) {
			return new WP_Error( 'invalid', __( 'Choose a role that cannot edit or manage the site.', 'radius-hotel-booking' ) );
		}
		return self::defaultRole();
	}
}
