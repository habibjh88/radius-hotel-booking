<?php
/**
 * The web visitor's session key, for holds (M04).
 *
 * @package RadiusTheme\RadiusHotelBooking\Frontend
 */

namespace RadiusTheme\RadiusHotelBooking\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * A guest on the website has no account, so the rooms they hold belong to a
 * random key in an **HttpOnly** cookie (`rtbp_guest`, 32 hex) issued by the
 * server: page scripts cannot read or copy it, and a hold or a booking made
 * from another browser cannot touch someone else's held rooms
 * (`HoldService::assertOwner()`). It lasts the browser session.
 */
class PublicSession {

	/**
	 * Cookie name.
	 */
	public const COOKIE = 'rtbp_guest';

	/**
	 * The current visitor's key; with `$create`, a new one is issued when
	 * there is none.
	 *
	 * @param bool $create Issue a key when missing.
	 * @return string Key, or '' (none and not created).
	 */
	public static function key( bool $create = false ): string {
		$key = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
			return $key;
		}
		if ( ! $create ) {
			return '';
		}
		$key = bin2hex( random_bytes( 16 ) );
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE,
				$key,
				array(
					'expires'  => 0,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		$_COOKIE[ self::COOKIE ] = $key;
		return $key;
	}
}
