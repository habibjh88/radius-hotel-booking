<?php
/**
 * Settings → Phone numbers.
 *
 * @package RadiusTheme\RadiusHotelBooking\Settings
 */

namespace RadiusTheme\RadiusHotelBooking\Settings;

use RadiusTheme\RadiusHotelBooking\Support\CallingCodes;

defined( 'ABSPATH' ) || exit;

/**
 * How every phone field works: the country it starts on, the countries it
 * offers, and the hotel's own calling codes (a code the country list does not
 * know, checked by its number of digits only). Registered by
 * `CoreSettings::register()`; the fields read it from
 * `settings.phone` in the page's params (src/lib/phone.js).
 */
class PhoneSettings {

	/**
	 * Most custom calling codes.
	 */
	public const MAX_CODES = 30;

	/**
	 * Settings → Phone numbers.
	 *
	 * @return array
	 */
	public static function schema(): array {
		return array(
			// ISO 3166 country a phone field starts on (GB, FR …); first install: the site language's country, else US.
			'defaultCountry'   => array(
				'type'     => 'string',
				'default'  => array( self::class, 'locale_country' ),
				'sanitize' => array( self::class, 'country' ),
			),
			// ISO countries the fields offer; empty = every country.
			'allowedCountries' => array(
				'type'     => 'array',
				'default'  => array(),
				'sanitize' => array( self::class, 'countries' ),
			),
			// The hotel's own codes: `{ label, code, minDigits, maxDigits }`, digits after the code.
			'customCodes'      => array(
				'type'     => 'array',
				'default'  => array(),
				'sanitize' => array( self::class, 'codes' ),
			),
		);
	}

	/**
	 * A two-letter country code.
	 *
	 * @param mixed $value Raw value.
	 * @return string|\WP_Error
	 */
	public static function country( $value ) {
		$code = strtoupper( trim( (string) $value ) );
		if ( ! CallingCodes::has( $code ) ) {
			return new \WP_Error( 'rtbp_invalid_setting', __( 'Choose a country.', 'radius-hotel-booking' ) );
		}
		return $code;
	}

	/**
	 * The country of the site's language (`en_GB` → GB), else US.
	 *
	 * @return string
	 */
	public static function locale_country(): string {
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : '';
		if ( preg_match( '/_([A-Z]{2})/', $locale, $match ) && CallingCodes::has( $match[1] ) ) {
			return $match[1];
		}
		return 'US';
	}

	/**
	 * A calling code: 1 to 4 digits, `+` allowed.
	 *
	 * @param mixed $value Raw value.
	 * @return string|\WP_Error
	 */
	public static function dial_code( $value ) {
		$code = ltrim( trim( (string) $value ), '+' );
		if ( ! preg_match( '/^[1-9]\d{0,3}$/', $code ) ) {
			return new \WP_Error( 'rtbp_invalid_setting', __( 'A calling code is 1 to 4 digits, such as 44.', 'radius-hotel-booking' ) );
		}
		return $code;
	}

	/**
	 * A list of countries, each once.
	 *
	 * @param mixed $value Raw value.
	 * @return array|\WP_Error
	 */
	public static function countries( $value ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error( 'rtbp_invalid_setting', __( 'This value is not valid.', 'radius-hotel-booking' ) );
		}
		$clean = array();
		foreach ( array_slice( array_values( $value ), 0, 300 ) as $code ) {
			$code = self::country( $code );
			if ( is_wp_error( $code ) ) {
				return $code;
			}
			$clean[ $code ] = $code;
		}
		return array_values( $clean );
	}

	/**
	 * The custom calling codes.
	 *
	 * @param mixed $value Raw value.
	 * @return array|\WP_Error
	 */
	public static function codes( $value ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error( 'rtbp_invalid_setting', __( 'This value is not valid.', 'radius-hotel-booking' ) );
		}
		if ( count( $value ) > self::MAX_CODES ) {
			/* translators: %d: most custom codes. */
			return new \WP_Error( 'rtbp_invalid_setting', sprintf( __( 'Add at most %d custom codes.', 'radius-hotel-booking' ), self::MAX_CODES ) );
		}
		$clean = array();
		foreach ( array_values( $value ) as $index => $row ) {
			$row   = is_array( $row ) ? $row : array();
			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			if ( '' === $label || mb_strlen( $label ) > 60 ) {
				return new \WP_Error(
					'rtbp_invalid_setting',
					/* translators: %d: position of the code in the list. */
					sprintf( __( 'Code %d needs a name of at most 60 characters.', 'radius-hotel-booking' ), $index + 1 )
				);
			}
			$code = self::dial_code( $row['code'] ?? '' );
			if ( is_wp_error( $code ) ) {
				/* translators: %s: name of the custom code. */
				return new \WP_Error( 'rtbp_invalid_setting', sprintf( __( '"%s": a calling code is 1 to 4 digits, such as 44.', 'radius-hotel-booking' ), $label ) );
			}
			$min = (int) ( $row['minDigits'] ?? 0 );
			$max = (int) ( $row['maxDigits'] ?? 0 );
			if ( $min < 4 || $max > 14 || $min > $max || strlen( $code ) + $max > 15 ) {
				/* translators: %s: name of the custom code. */
				return new \WP_Error( 'rtbp_invalid_setting', sprintf( __( '"%s": the number of digits must be between 4 and 14, the fewest first.', 'radius-hotel-booking' ), $label ) );
			}
			$clean[] = array(
				'label'     => $label,
				'code'      => $code,
				'minDigits' => $min,
				'maxDigits' => $max,
			);
		}
		return $clean;
	}

	/**
	 * The custom codes as saved.
	 *
	 * @return array[]
	 */
	public static function custom_codes(): array {
		$codes = rtbp_setting( 'phone', 'customCodes', array() );
		return is_array( $codes ) ? $codes : array();
	}
}
