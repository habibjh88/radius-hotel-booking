<?php
/**
 * Settings sanitiser and validator.
 *
 * @package RadiusTheme\RadiusHotelBooking\Settings
 */

namespace RadiusTheme\RadiusHotelBooking\Settings;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Cleans a section's submitted values against its schema.
 *
 * Only keys the schema knows are kept (a whitelist, never raw input). Each value
 * is sanitised by its type; a value that cannot be made valid (out of range,
 * not one of the options, not a time) becomes a field error instead of being
 * silently changed, so the Settings screen can show it next to the field.
 */
final class SettingsValidator {

	/**
	 * Validate a section's values.
	 *
	 * @param array $schema Section schema (SettingsSchema::section()).
	 * @param array $values Values to clean (keys not in the schema are dropped).
	 * @return array{0: array, 1: array<string, string>} Clean values, and key => error message.
	 */
	public static function validate( array $schema, array $values ): array {
		$clean  = array();
		$errors = array();

		foreach ( $schema as $key => $definition ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

			$result = self::clean( $definition, $values[ $key ] );
			if ( $result instanceof WP_Error ) {
				$errors[ $key ] = $result->get_error_message();
				continue;
			}
			$clean[ $key ] = $result;
		}

		return array( $clean, $errors );
	}

	/**
	 * Clean one value.
	 *
	 * @param array $definition Normalised key definition.
	 * @param mixed $value      Raw value.
	 * @return mixed|WP_Error Clean value, or an error.
	 */
	public static function clean( array $definition, $value ) {
		if ( isset( $definition['sanitize'] ) && is_callable( $definition['sanitize'] ) ) {
			return call_user_func( $definition['sanitize'], $value, $definition );
		}

		switch ( $definition['type'] ) {
			case 'int':
				return self::number( $definition, $value, true );
			case 'float':
				return self::number( $definition, $value, false );
			case 'bool':
				return self::boolean( $value );
			case 'enum':
				return self::enum( $definition, $value );
			case 'email':
				return self::email( $value );
			case 'url':
				return self::url( $value );
			case 'color':
				return self::color( $value );
			case 'time':
				return self::time( $value );
			case 'text':
				return self::text( $definition, $value, true );
			case 'media':
				return self::media( $definition, $value );
			case 'array':
				return is_array( $value ) ? $value : self::invalid();
			default:
				return self::text( $definition, $value, false );
		}
	}

	/**
	 * Integer or float within min/max.
	 *
	 * @param array $definition Definition.
	 * @param mixed $value      Value.
	 * @param bool  $integer    Integers only.
	 * @return int|float|WP_Error
	 */
	private static function number( array $definition, $value, bool $integer ) {
		if ( is_bool( $value ) || ! is_numeric( $value ) ) {
			return self::error( __( 'Enter a number.', 'radius-hotel-booking' ) );
		}
		if ( $integer && (float) $value !== floor( (float) $value ) ) {
			return self::error( __( 'Enter a whole number.', 'radius-hotel-booking' ) );
		}

		$number = $integer ? (int) $value : (float) $value;

		if ( isset( $definition['min'] ) && $number < $definition['min'] ) {
			/* translators: %s: the smallest allowed number. */
			return self::error( sprintf( __( 'Enter %s or more.', 'radius-hotel-booking' ), number_format_i18n( $definition['min'] ) ) );
		}
		if ( isset( $definition['max'] ) && $number > $definition['max'] ) {
			/* translators: %s: the largest allowed number. */
			return self::error( sprintf( __( 'Enter %s or less.', 'radius-hotel-booking' ), number_format_i18n( $definition['max'] ) ) );
		}

		return $number;
	}

	/**
	 * Boolean from true/false, 1/0, "true"/"false", "yes"/"no".
	 *
	 * @param mixed $value Value.
	 * @return bool|WP_Error
	 */
	private static function boolean( $value ) {
		$bool = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		return null === $bool ? self::invalid() : $bool;
	}

	/**
	 * One of the allowed options.
	 *
	 * @param array $definition Definition.
	 * @param mixed $value      Value.
	 * @return mixed|WP_Error
	 */
	private static function enum( array $definition, $value ) {
		$options = array_keys( self::options( $definition ) );
		foreach ( $options as $option ) {
			if ( is_scalar( $value ) && (string) $option === (string) $value ) {
				return $option;
			}
		}
		return self::error( __( 'Choose one of the options.', 'radius-hotel-booking' ) );
	}

	/**
	 * An e-mail address, or empty.
	 *
	 * @param mixed $value Value.
	 * @return string|WP_Error
	 */
	private static function email( $value ) {
		if ( ! is_scalar( $value ) ) {
			return self::invalid();
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$email = sanitize_email( $value );
		return is_email( $email ) ? $email : self::error( __( 'Enter a valid e-mail address.', 'radius-hotel-booking' ) );
	}

	/**
	 * An http(s) address, or empty.
	 *
	 * @param mixed $value Value.
	 * @return string|WP_Error
	 */
	private static function url( $value ) {
		if ( ! is_scalar( $value ) ) {
			return self::invalid();
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$url = esc_url_raw( $value, array( 'http', 'https' ) );
		return '' !== $url ? $url : self::error( __( 'Enter a web address starting with https://', 'radius-hotel-booking' ) );
	}

	/**
	 * A hex colour.
	 *
	 * @param mixed $value Value.
	 * @return string|WP_Error
	 */
	private static function color( $value ) {
		$color = is_scalar( $value ) ? sanitize_hex_color( (string) $value ) : null;
		return $color ? $color : self::error( __( 'Enter a colour such as #0040ff.', 'radius-hotel-booking' ) );
	}

	/**
	 * A time of day, HH:MM (00:00–23:59).
	 *
	 * @param mixed $value Value.
	 * @return string|WP_Error
	 */
	private static function time( $value ) {
		if ( is_scalar( $value ) && preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', trim( (string) $value ), $m ) ) {
			return sprintf( '%02d:%s', (int) $m[1], $m[2] );
		}
		return self::error( __( 'Enter a time between 00:00 and 23:59.', 'radius-hotel-booking' ) );
	}

	/**
	 * A single-line or multi-line string.
	 *
	 * @param array $definition Definition.
	 * @param mixed $value      Value.
	 * @param bool  $multiline  Keep line breaks.
	 * @return string|WP_Error
	 */
	private static function text( array $definition, $value, bool $multiline ) {
		if ( ! is_scalar( $value ) && null !== $value ) {
			return self::invalid();
		}
		$text = $multiline ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );

		if ( isset( $definition['maxLength'] ) && mb_strlen( $text ) > $definition['maxLength'] ) {
			/* translators: %d: the maximum number of characters. */
			return self::error( sprintf( __( 'Use %d characters or fewer.', 'radius-hotel-booking' ), $definition['maxLength'] ) );
		}

		return $text;
	}

	/**
	 * A media library attachment id (0 = none), optionally of given mime types.
	 *
	 * @param array $definition Definition.
	 * @param mixed $value      Value.
	 * @return int|WP_Error
	 */
	private static function media( array $definition, $value ) {
		if ( ! is_numeric( $value ) || (int) $value < 0 ) {
			return self::invalid();
		}
		$id = (int) $value;
		if ( 0 === $id ) {
			return 0;
		}

		$mime = get_post_mime_type( $id );
		if ( 'attachment' !== get_post_type( $id ) || ! $mime ) {
			return self::error( __( 'That file is no longer in the media library.', 'radius-hotel-booking' ) );
		}

		if ( ! empty( $definition['mime'] ) ) {
			foreach ( (array) $definition['mime'] as $prefix ) {
				if ( 0 === strpos( $mime, $prefix ) ) {
					return $id;
				}
			}
			return self::error( __( 'This type of file is not allowed here.', 'radius-hotel-booking' ) );
		}

		return $id;
	}

	/**
	 * An enum's options as value => label (a callable is resolved).
	 *
	 * @param array $definition Definition.
	 * @return array
	 */
	public static function options( array $definition ): array {
		$options = $definition['options'] ?? array();
		if ( is_callable( $options ) && ! is_string( $options ) ) {
			$options = $options();
		}
		$options = (array) $options;
		// A plain list becomes value => value.
		$is_list = array() === $options || array_keys( $options ) === range( 0, count( $options ) - 1 );
		return $is_list ? array_combine( $options, $options ) : $options;
	}

	/**
	 * Generic "invalid value" error.
	 *
	 * @return WP_Error
	 */
	private static function invalid(): WP_Error {
		return self::error( __( 'This value is not valid.', 'radius-hotel-booking' ) );
	}

	/**
	 * A validation error.
	 *
	 * @param string $message Translated message.
	 * @return WP_Error
	 */
	private static function error( string $message ): WP_Error {
		return new WP_Error( 'rtbp_invalid_setting', $message );
	}
}
