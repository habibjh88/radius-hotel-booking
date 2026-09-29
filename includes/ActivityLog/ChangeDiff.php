<?php
/**
 * Before/after diffing and masking for activity events.
 *
 * @package RadiusTheme\RadiusHotelBooking\ActivityLog
 */

namespace RadiusTheme\RadiusHotelBooking\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps only the fields that changed (feature 14.14) and masks secrets: a PIN,
 * password, token, hash or identity-document number is recorded as `changed`,
 * never with its value (conventions §2.10).
 */
final class ChangeDiff {

	/**
	 * The fields whose value differs, as `{ before, after }`.
	 *
	 * @param array $before Values before.
	 * @param array $after  Values after.
	 * @return array{before: array, after: array}
	 */
	public static function between( array $before, array $after ): array {
		$out = array(
			'before' => array(),
			'after'  => array(),
		);

		foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $key ) {
			$old = $before[ $key ] ?? null;
			$new = $after[ $key ] ?? null;
			if ( self::same( $old, $new ) ) {
				continue;
			}
			$out['before'][ $key ] = $old;
			$out['after'][ $key ]  = $new;
		}

		return self::mask( $out );
	}

	/**
	 * Mask secret values anywhere in an array (keys matched by name).
	 *
	 * @param array $values Values.
	 * @return array
	 */
	public static function mask( array $values ): array {
		foreach ( $values as $key => $value ) {
			if ( self::is_secret( (string) $key ) ) {
				// Everything under a secret key, arrays included.
				$values[ $key ] = is_array( $value ) || ! self::is_marker( $value ) ? 'changed' : $value;
			} elseif ( is_array( $value ) ) {
				$values[ $key ] = self::mask( $value );
			}
		}
		return $values;
	}

	/**
	 * Whether a field name holds a secret.
	 *
	 * @param string $key Field name (the last segment of `profile.pin` etc.).
	 * @return bool
	 */
	public static function is_secret( string $key ): bool {
		/**
		 * Filters the pattern of field names whose values are never logged.
		 *
		 * @param string $pattern PCRE pattern.
		 */
		$pattern = (string) apply_filters(
			'rtbp_activity_secret_pattern',
			'/(^|[._])(pin|pins|passcode|password|pass|secret|token|hash|salt|otp|cvv|cvc|api_key|id_number|document_number|card_number|passport|passport_number)($|[._])|_(pin|hash|token|secret|password|passcode|key)$/i'
		);
		// camelCase keys (settings) are matched as snake_case: fallbackPin → fallback_pin.
		$snake = strtolower( (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', $key ) );
		return 1 === preg_match( $pattern, $snake );
	}

	/**
	 * Values that already say nothing about the secret (`changed`, `removed`,
	 * `unset`, `default`, empty) and policy words (`allow` / `restrict` on the
	 * profile fields `passcode` and `password`), kept as they are.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_marker( $value ): bool {
		return null === $value || '' === $value || is_bool( $value )
			|| in_array( $value, array( 'changed', 'removed', 'unset', 'default', 'set', 'allow', 'restrict' ), true );
	}

	/**
	 * Loose equality that treats "5" and 5 alike but not "" and 0.
	 *
	 * @param mixed $a Value.
	 * @param mixed $b Value.
	 * @return bool
	 */
	private static function same( $a, $b ): bool {
		if ( is_scalar( $a ) && is_scalar( $b ) && ! is_bool( $a ) && ! is_bool( $b ) ) {
			return (string) $a === (string) $b;
		}
		return wp_json_encode( $a ) === wp_json_encode( $b );
	}
}
