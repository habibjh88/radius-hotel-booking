<?php
/**
 * Settings → Payments and Settings → Invoices (M05).
 *
 * @package RadiusTheme\RadiusHotelBooking\Settings
 */

namespace RadiusTheme\RadiusHotelBooking\Settings;

defined( 'ABSPATH' ) || exit;

use RadiusTheme\RadiusHotelBooking\Support\Icons;

/**
 * The payment methods the hotel accepts, with the instructions guests see
 * (17.12, 5.3), and how invoices are numbered and printed (17.13). Hotel
 * name, address, tax and social security numbers and the logo come from General, so
 * they are typed once. Registered by `CoreSettings::register()`.
 */
class PaymentSettings {

	/**
	 * Most payment methods a hotel can list.
	 */
	public const MAX_METHODS = 20;

	/**
	 * Longest short name (the dashboard's abbreviation of a method).
	 */
	public const SHORT_LENGTH = 12;

	/**
	 * Merge tags the instructions may use, replaced per booking.
	 */
	public const MERGE_TAGS = array( '{amount}', '{reference}', '{deadline}', '{hotel_name}' );

	/**
	 * Settings → Payments.
	 *
	 * @return array
	 */
	public static function payments(): array {
		return array(
			// The methods guests may pay with, in display order: `{ key, label, short, instructions, account, enabled }` (17.12);
			// `short` is the dashboard's abbreviation (OM, Cash), the full label is what guests see.
			'methods' => array(
				'type'     => 'array',
				'default'  => array( self::class, 'default_methods' ),
				'sanitize' => array( self::class, 'methods' ),
			),
		);
	}

	/**
	 * Settings → Invoices.
	 *
	 * @return array
	 */
	public static function invoices(): array {
		return array(
			// Text before the year in the number: FAC- gives FAC-2026-000001 (5.8).
			'prefix'      => array(
				'type'      => 'string',
				'default'   => 'FAC-',
				'maxLength' => 20,
				'sanitize'  => array( self::class, 'prefix' ),
			),
			// First number of each year's sequence (used when the year's first invoice is issued).
			'startNumber' => array(
				'type'    => 'int',
				'default' => 1,
				'min'     => 1,
				'max'     => 999999,
			),
			// Name of the tax shown on invoices (VAT, TVA …); prices include it.
			'taxLabel'    => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => 40,
			),
			// Tax rate in percent included in the prices; 0 = no tax line.
			'taxRate'     => array(
				'type'    => 'float',
				'default' => 0,
				'min'     => 0,
				'max'     => 100,
			),
			// Text at the foot of every invoice and receipt (bank details, legal mentions).
			'footerText'  => array(
				'type'      => 'text',
				'default'   => '',
				'maxLength' => 1000,
			),
		);
	}

	/**
	 * The methods a fresh install offers (the client's four).
	 *
	 * @return array[]
	 */
	public static function default_methods(): array {
		return array(
			array(
				'key'          => 'cash',
				'icon'         => 'banknote',
				'label'        => __( 'Cash at the desk', 'radius-hotel-booking' ),
				'short'        => __( 'Cash', 'radius-hotel-booking' ),
				/* translators: Keep the {…} merge tags as they are. */
				'instructions' => __( 'Pay {amount} in cash at reception, quoting booking {reference}.', 'radius-hotel-booking' ),
				'account'      => '',
				'enabled'      => true,
			),
			array(
				'key'          => 'card',
				'icon'         => 'credit-card',
				'label'        => __( 'Card at the desk', 'radius-hotel-booking' ),
				'short'        => __( 'Card', 'radius-hotel-booking' ),
				/* translators: Keep the {…} merge tags as they are. */
				'instructions' => __( 'Pay {amount} by card at reception, quoting booking {reference}.', 'radius-hotel-booking' ),
				'account'      => '',
				'enabled'      => true,
			),
			array(
				'key'          => 'bank_transfer',
				'icon'         => 'landmark',
				'label'        => __( 'Bank transfer', 'radius-hotel-booking' ),
				/* translators: Keep the {…} merge tags as they are. */
				'instructions' => __( 'Transfer {amount} to the account below before {deadline}, with {reference} as the transfer reference.', 'radius-hotel-booking' ),
				'account'      => '',
				'enabled'      => false,
			),
		);
	}

	/**
	 * Clean the methods list: each needs a label; keys are slugs, unique,
	 * made from the label when missing (a key never changes once saved: the
	 * ledger stores it).
	 *
	 * @param mixed $value Raw list.
	 * @return array|\WP_Error
	 */
	public static function methods( $value ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error( 'rtbp_invalid_setting', __( 'This value is not valid.', 'radius-hotel-booking' ) );
		}
		if ( count( $value ) > self::MAX_METHODS ) {
			/* translators: %d: most payment methods. */
			return new \WP_Error( 'rtbp_invalid_setting', sprintf( __( 'List at most %d payment methods.', 'radius-hotel-booking' ), self::MAX_METHODS ) );
		}
		$clean = array();
		$keys  = array();
		foreach ( array_values( $value ) as $index => $method ) {
			$method = is_array( $method ) ? $method : array();
			$label  = sanitize_text_field( (string) ( $method['label'] ?? '' ) );
			if ( '' === $label || mb_strlen( $label ) > 60 ) {
				return new \WP_Error(
					'rtbp_invalid_setting',
					/* translators: %d: position of the method in the list. */
					sprintf( __( 'Method %d needs a name of at most 60 characters.', 'radius-hotel-booking' ), $index + 1 )
				);
			}
			$key = sanitize_key( str_replace( '-', '_', (string) ( $method['key'] ?? '' ) ) );
			if ( '' === $key ) {
				$key = sanitize_key( str_replace( '-', '_', sanitize_title( $label ) ) );
			}
			if ( '' === $key ) {
				$key = 'method_' . ( $index + 1 );
			}
			$key = substr( $key, 0, 40 );
			if ( isset( $keys[ $key ] ) ) {
				/* translators: %s: payment method name. */
				return new \WP_Error( 'rtbp_invalid_setting', sprintf( __( '"%s" is listed twice.', 'radius-hotel-booking' ), $label ) );
			}
			$keys[ $key ] = true;
			$clean[]      = array(
				'key'          => $key,
				'label'        => $label,
				'short'        => mb_substr( sanitize_text_field( (string) ( $method['short'] ?? '' ) ), 0, self::SHORT_LENGTH ),
				'icon'         => Icons::clean( $method['icon'] ?? '' ),
				'instructions' => mb_substr( sanitize_textarea_field( (string) ( $method['instructions'] ?? '' ) ), 0, 2000 ),
				'account'      => mb_substr( sanitize_textarea_field( (string) ( $method['account'] ?? '' ) ), 0, 500 ),
				'enabled'      => ! empty( $method['enabled'] ) && 'false' !== $method['enabled'],
			);
		}
		return $clean;
	}

	/**
	 * A method's icon (Lucide name): the one picked in Settings, else a guess
	 * from its key and name, so methods saved before icons existed get one too.
	 *
	 * @param array $method Method.
	 * @return string
	 */
	public static function icon( array $method ): string {
		$icon = Icons::clean( $method['icon'] ?? '' );
		if ( '' !== $icon ) {
			return $icon;
		}
		$words = strtolower( ( $method['key'] ?? '' ) . ' ' . ( $method['label'] ?? '' ) );
		foreach ( array(
			'banknote'    => '/cash|esp[eè]ce/u',
			'credit-card' => '/card|carte/u',
			'landmark'    => '/bank|transfer|virement|wire/u',
			'smartphone'  => '/mobile|money|momo|phone/u',
		) as $guess => $pattern ) {
			if ( preg_match( $pattern, $words ) ) {
				return $guess;
			}
		}
		return 'wallet';
	}

	/**
	 * An invoice prefix: letters, digits, `-`, `/`, `_` and `.` only (it is part of a number).
	 *
	 * @param mixed $value Raw prefix.
	 * @return string|\WP_Error
	 */
	public static function prefix( $value ) {
		$value = trim( (string) $value );
		if ( mb_strlen( $value ) > 20 || ! preg_match( '#^[A-Za-z0-9/_.\-]*$#', $value ) ) {
			return new \WP_Error( 'rtbp_invalid_setting', __( 'Use up to 20 letters, digits and - / _ . only.', 'radius-hotel-booking' ) );
		}
		return $value;
	}

	/**
	 * The enabled methods, in order (for the record dialog, e-mails and the confirmation page).
	 *
	 * @return array[]
	 */
	public static function enabled_methods(): array {
		$methods = rtbp_setting( 'payments', 'methods', array() );
		return array_values( array_filter( is_array( $methods ) ? $methods : array(), static fn( $method ) => ! empty( $method['enabled'] ) ) );
	}

	/**
	 * A method's name from its key; the key itself when the method was removed since.
	 *
	 * @param string $key   Method key.
	 * @param bool   $short The short name when it has one (the dashboard); guests get the full name.
	 * @return string
	 */
	public static function method_label( string $key, bool $short = false ): string {
		foreach ( (array) rtbp_setting( 'payments', 'methods', array() ) as $method ) {
			if ( ( $method['key'] ?? '' ) === $key ) {
				return $short && '' !== (string) ( $method['short'] ?? '' ) ? (string) $method['short'] : (string) $method['label'];
			}
		}
		return $key;
	}
}
