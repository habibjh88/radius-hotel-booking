<?php
/**
 * Phone numbers.
 *
 * @package RadiusTheme\RadiusHotelBooking\Support
 */

namespace RadiusTheme\RadiusHotelBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Pure: turns what a guest or receptionist types into one comparable form.
 *
 * - `toE164()` gives `+<country><number>` or null when the number cannot be
 *   placed. A number without a country code is read in the hotel's country
 *   (`rtbp_default_phone_country`, default 225, Côte d'Ivoire).
 * - Côte d'Ivoire has had 10-digit numbers since 31 January 2021: a 2-digit
 *   operator prefix before the old 8 digits (mobile 01 Moov, 05 MTN, 07
 *   Orange; fixed 21, 25, 27). An old 8-digit **mobile** number is converted
 *   by the published rule — its second digit picks the operator: 0–3 → 01,
 *   4–6 → 05, 7–9 → 07. An old 8-digit fixed number (first digit 2 or 3)
 *   is not guessed: `toE164()` returns null for it.
 * - `tail()` is the last 8 digits: the old number inside the new one, so
 *   either form of the same number matches (the legacy search did this).
 */
final class Phone {

	/**
	 * The hotel's country calling code, digits only.
	 *
	 * @return string
	 */
	public static function defaultCountry(): string {
		/**
		 * The country calling code for numbers typed without one.
		 *
		 * @param string $code Digits, e.g. '225'.
		 */
		$code = preg_replace( '/\D+/', '', (string) apply_filters( 'rtbp_default_phone_country', '225' ) );
		return '' === $code ? '225' : $code;
	}

	/**
	 * The number in E.164 (`+2250707123456`), or null when it cannot be placed.
	 *
	 * @param string $raw As typed: spaces, dots, dashes, brackets, `+` or `00`.
	 * @return string|null
	 */
	public static function toE164( string $raw ): ?string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		$international = 0 === strpos( $raw, '+' ) || 0 === strpos( preg_replace( '/[\s().-]+/', '', $raw ), '00' );
		$digits        = preg_replace( '/\D+/', '', $raw );
		if ( $international && 0 === strpos( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
		}

		$country = self::defaultCountry();
		if ( ! $international && '225' === $country && 13 === strlen( $digits ) && 0 === strpos( $digits, '225' ) ) {
			// "225 07 07 12 34 56" typed without the plus.
			$international = true;
		}

		if ( $international ) {
			if ( 0 === strpos( $digits, '225' ) ) {
				$national = self::ivorian( substr( $digits, 3 ) );
				return null === $national ? null : '+225' . $national;
			}
			// Another country: E.164 allows up to 15 digits in all.
			return strlen( $digits ) >= 8 && strlen( $digits ) <= 15 ? '+' . $digits : null;
		}

		if ( '225' === $country ) {
			$national = self::ivorian( $digits );
			return null === $national ? null : '+225' . $national;
		}
		// Another home country: drop one trunk 0 and prefix the code.
		$national = ltrim( $digits, '0' );
		$total    = strlen( $country ) + strlen( $national );
		return strlen( $national ) >= 6 && $total <= 15 ? '+' . $country . $national : null;
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
	 * A 10-digit Ivorian national number from 10 or 8 digits, or null.
	 *
	 * @param string $digits National digits.
	 * @return string|null
	 */
	private static function ivorian( string $digits ): ?string {
		if ( 10 === strlen( $digits ) ) {
			return in_array( substr( $digits, 0, 2 ), array( '01', '05', '07', '21', '25', '27' ), true ) ? $digits : null;
		}
		if ( 8 === strlen( $digits ) ) {
			$first = (int) $digits[0];
			if ( 2 === $first || 3 === $first ) {
				return null; // An old fixed number: its new prefix is not guessed.
			}
			$second = (int) $digits[1];
			$prefix = $second <= 3 ? '01' : ( $second <= 6 ? '05' : '07' );
			return $prefix . $digits;
		}
		return null;
	}
}
