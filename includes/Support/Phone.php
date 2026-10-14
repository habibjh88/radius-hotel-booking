<?php
/**
 * Phone numbers.
 *
 * @package RadiusTheme\RadiusHotelBooking\Support
 */

namespace RadiusTheme\RadiusHotelBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Turns what a guest or receptionist types into one comparable form.
 *
 * - `toE164()` gives `+<country><number>` or null when the number cannot be
 *   placed. A number without a country code is read in the default country
 *   (Settings → Phone numbers): one leading trunk 0 dropped, the calling
 *   code put in front. A number under one of the hotel's custom codes is
 *   checked by its number of digits; with allowed countries set, a number
 *   from elsewhere is refused.
 * - Local numbering rules (a country that changed its numbers, say) are an
 *   add-on's business: `rtbp_phone_to_e164` can place a number first.
 * - `tail()` is the last 8 digits, so an old and a new form of the same
 *   number still match in a search.
 *
 * The browser checks numbers fully (libphonenumber-js); this is the
 * server's lighter second check.
 */
final class Phone {

	/**
	 * The default country's calling code, digits only.
	 *
	 * @return string
	 */
	public static function defaultCountry(): string {
		$country = (string) rtbp_setting( 'phone', 'defaultCountry', 'US' );
		/**
		 * The calling code for numbers typed without one.
		 *
		 * @param string $code    Digits, e.g. '44'.
		 * @param string $country The default country (ISO 3166).
		 */
		$code = preg_replace( '/\D+/', '', (string) apply_filters( 'rtbp_default_phone_country', CallingCodes::of( $country ), $country ) );
		return '' === $code ? '1' : $code;
	}

	/**
	 * The number in E.164 (`+447700900123`), or null when it cannot be placed.
	 *
	 * @param string $raw As typed: spaces, dots, dashes, brackets, `+` or `00`.
	 * @return string|null
	 */
	public static function toE164( string $raw ): ?string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}

		/**
		 * Place a number by local rules before the general ones.
		 *
		 * Return an E.164 string to use it, false to refuse the number, or
		 * null to leave it to the general rules.
		 *
		 * @param string|false|null $e164    Null.
		 * @param string            $raw     As typed.
		 * @param string            $default The default calling code.
		 */
		$placed = apply_filters( 'rtbp_phone_to_e164', null, $raw, self::defaultCountry() );
		if ( false === $placed ) {
			return null;
		}
		if ( is_string( $placed ) && preg_match( '/^\+[1-9]\d{6,14}$/', $placed ) ) {
			return $placed;
		}

		$custom = self::custom( $raw );
		if ( null !== $custom ) {
			return $custom;
		}
		$e164 = self::place( $raw );
		return null !== $e164 && self::allowed( $e164 ) ? $e164 : null;
	}

	/**
	 * The last 8 digits, for matching old and new forms ('' when too short).
	 *
	 * @param string $raw As typed.
	 * @return string
	 */
	public static function tail( string $raw ): string {
		$digits = preg_replace( '/\D+/', '', $raw );
		return strlen( $digits ) >= 8 ? substr( $digits, -8 ) : '';
	}

	/**
	 * The digits of an international number (`+…` or `00…`), or null for a
	 * national one.
	 *
	 * @param string $raw As typed.
	 * @return string|null
	 */
	private static function international( string $raw ): ?string {
		$digits = preg_replace( '/\D+/', '', $raw );
		if ( 0 === strpos( $raw, '+' ) ) {
			return $digits;
		}
		if ( 0 === strpos( preg_replace( '/[\s().-]+/', '', $raw ), '00' ) ) {
			return substr( $digits, 2 );
		}
		return null;
	}

	/**
	 * The number by the general rules, or null.
	 *
	 * @param string $raw As typed.
	 * @return string|null
	 */
	private static function place( string $raw ): ?string {
		$digits = self::international( $raw );
		if ( null !== $digits ) {
			// E.164 allows up to 15 digits in all.
			return strlen( $digits ) >= 8 && strlen( $digits ) <= 15 && '0' !== $digits[0] ? '+' . $digits : null;
		}
		$country  = self::defaultCountry();
		$national = preg_replace( '/^0/', '', preg_replace( '/\D+/', '', $raw ) );
		$total    = strlen( $country ) + strlen( $national );
		return strlen( $national ) >= 6 && $total <= 15 ? '+' . $country . $national : null;
	}

	/**
	 * The number under one of the hotel's custom codes (written with `+` or
	 * `00`), or null.
	 *
	 * @param string $raw As typed.
	 * @return string|null
	 */
	private static function custom( string $raw ): ?string {
		$digits = self::international( $raw );
		if ( null === $digits ) {
			return null;
		}
		$codes = rtbp_setting( 'phone', 'customCodes', array() );
		foreach ( is_array( $codes ) ? $codes : array() as $code ) {
			$prefix = (string) ( $code['code'] ?? '' );
			if ( '' === $prefix || 0 !== strpos( $digits, $prefix ) ) {
				continue;
			}
			$length = strlen( $digits ) - strlen( $prefix );
			if ( $length >= (int) ( $code['minDigits'] ?? 0 ) && $length <= (int) ( $code['maxDigits'] ?? 0 ) ) {
				return '+' . $digits;
			}
		}
		return null;
	}

	/**
	 * Whether an E.164 number belongs to an allowed country (any number when
	 * none is set). Countries sharing a calling code (+1) cannot be told
	 * apart here; the browser does that.
	 *
	 * @param string $e164 E.164.
	 * @return bool
	 */
	private static function allowed( string $e164 ): bool {
		$allowed = rtbp_setting( 'phone', 'allowedCountries', array() );
		if ( ! is_array( $allowed ) || ! $allowed ) {
			return true;
		}
		$digits = substr( $e164, 1 );
		foreach ( $allowed as $country ) {
			$code = CallingCodes::of( (string) $country );
			if ( '' !== $code && 0 === strpos( $digits, $code ) ) {
				return true;
			}
		}
		return false;
	}
}
