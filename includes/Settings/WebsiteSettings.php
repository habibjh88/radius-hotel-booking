<?php
/**
 * Settings → Public booking: how guests book on the website (M04).
 *
 * @package RadiusTheme\RadiusHotelBooking\Settings
 */

namespace RadiusTheme\RadiusHotelBooking\Settings;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The `website` section (option `rtbp_website_settings`). The booking rules
 * shared with the desk (booking window, same-day cut-off, manual approval,
 * show or hide unavailable rooms, hold minutes) stay in Booking rules.
 */
class WebsiteSettings {

	/**
	 * The booking form's guest fields and how each is asked: `required`,
	 * `optional` or `hidden` (the first name is always required).
	 */
	const GUEST_FIELDS = array(
		'last_name'        => 'required',
		'phone'            => 'required',
		'email'            => 'optional',
		'id_document'      => 'required',
		'special_requests' => 'optional',
	);

	/**
	 * The modes a guest field can take.
	 */
	const FIELD_MODES = array( 'required', 'optional', 'hidden' );

	/**
	 * The booking form's inputs whose label and placeholder can be changed
	 * (the identity document is two inputs: its type and its number).
	 */
	const TEXT_FIELDS = array( 'first_name', 'last_name', 'phone', 'email', 'id_type', 'id_number', 'special_requests' );

	/**
	 * Most identity document types the list may hold.
	 */
	const MAX_ID_TYPES = 30;

	/**
	 * The section's schema.
	 *
	 * @return array
	 */
	public static function schema(): array {
		return array(
			// The guest picks the room number; off = the first free room by number is assigned (legacy: on).
			'guestPicksRoom' => array(
				'type'    => 'bool',
				'default' => true,
			),
			// A "how many rooms" field in the search bar (4.2).
			'showRoomsField' => array(
				'type'    => 'bool',
				'default' => false,
			),
			// The page holding [rtbp_booking], where the search bar sends guests; 0 = none yet.
			'resultsPageId'  => array(
				'type'     => 'int',
				'default'  => 0,
				'min'      => 0,
				'sanitize' => array( self::class, 'page' ),
			),
			// Guests tick "I agree to the privacy policy" before booking (the site's privacy page).
			'privacyConsent' => array(
				'type'    => 'bool',
				'default' => true,
			),
			// Guest fields on the booking form: field => required|optional|hidden (GUEST_FIELDS).
			'guestFields'    => array(
				'type'     => 'array',
				'default'  => self::GUEST_FIELDS,
				'sanitize' => array( self::class, 'guestFieldsValue' ),
			),
			// Each input's own label and placeholder: field => { label, placeholder }; '' = the built-in text.
			'guestFieldText' => array(
				'type'     => 'array',
				'default'  => array_fill_keys(
					self::TEXT_FIELDS,
					array(
						'label'       => '',
						'placeholder' => '',
					)
				),
				'sanitize' => array( self::class, 'guestFieldTextValue' ),
			),
			// The identity documents a guest can choose: [ { key, label } ], the built-in ones until changed.
			'idTypes'        => array(
				'type'     => 'array',
				'default'  => array( self::class, 'defaultIdTypes' ),
				'sanitize' => array( self::class, 'idTypesValue' ),
			),
			// Adults preselected in the search bar (legacy ?adults=2).
			'defaultAdults'  => array(
				'type'    => 'int',
				'default' => 2,
				'min'     => 1,
				'max'     => 10,
			),
		);
	}

	/**
	 * Every guest field's mode, saved values over the defaults.
	 *
	 * @return array<string, string> Field => required|optional|hidden.
	 */
	public static function guestFields(): array {
		$saved = rtbp_setting( 'website', 'guestFields', self::GUEST_FIELDS );
		$modes = self::GUEST_FIELDS;
		foreach ( is_array( $saved ) ? $saved : array() as $key => $mode ) {
			if ( isset( $modes[ $key ] ) && in_array( $mode, self::FIELD_MODES, true ) ) {
				$modes[ $key ] = $mode;
			}
		}
		return $modes;
	}

	/**
	 * One guest field's mode.
	 *
	 * @param string $field A GUEST_FIELDS key.
	 * @return string required|optional|hidden.
	 */
	public static function guestField( string $field ): string {
		return self::guestFields()[ $field ] ?? 'optional';
	}

	/**
	 * `guestFields`: a mode per known field, and a way to reach the guest —
	 * a guest record needs a phone or an e-mail (GuestService).
	 *
	 * @param mixed $value Input.
	 * @return array|WP_Error
	 */
	public static function guestFieldsValue( $value ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'invalid', __( 'Choose how each field is asked.', 'radius-hotel-booking' ) );
		}
		$modes = self::GUEST_FIELDS;
		foreach ( $modes as $key => $default ) {
			$mode = sanitize_key( (string) ( $value[ $key ] ?? $default ) );
			if ( ! in_array( $mode, self::FIELD_MODES, true ) ) {
				return new WP_Error( 'invalid', __( 'Choose how each field is asked.', 'radius-hotel-booking' ) );
			}
			$modes[ $key ] = $mode;
		}
		if ( 'required' !== $modes['phone'] && 'required' !== $modes['email'] ) {
			return new WP_Error( 'invalid', __( 'Make the phone or the e-mail required: the hotel needs a way to reach the guest.', 'radius-hotel-booking' ) );
		}
		return $modes;
	}

	/**
	 * Every input's label and placeholder, saved values over the defaults.
	 *
	 * @return array<string, array{label: string, placeholder: string}> '' = the built-in text.
	 */
	public static function guestFieldText(): array {
		$saved = rtbp_setting( 'website', 'guestFieldText', array() );
		$clean = self::guestFieldTextValue( is_array( $saved ) ? $saved : array() );
		return is_array( $clean ) ? $clean : array();
	}

	/**
	 * `guestFieldText`: a label and a placeholder per known input, plain text.
	 *
	 * @param mixed $value Input.
	 * @return array|WP_Error
	 */
	public static function guestFieldTextValue( $value ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'invalid', __( 'Enter the labels and placeholders as text.', 'radius-hotel-booking' ) );
		}
		$out = array();
		foreach ( self::TEXT_FIELDS as $field ) {
			$row           = is_array( $value[ $field ] ?? null ) ? $value[ $field ] : array();
			$out[ $field ] = array(
				'label'       => self::shortText( $row['label'] ?? '' ),
				'placeholder' => self::shortText( $row['placeholder'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * The built-in identity document types, in the site's language.
	 *
	 * @return array<int, array{key: string, label: string}>
	 */
	public static function defaultIdTypes(): array {
		$types = array(
			'cni'            => __( 'National ID card', 'radius-hotel-booking' ),
			'passport'       => __( 'Passport', 'radius-hotel-booking' ),
			'consular_card'  => __( 'Consular card', 'radius-hotel-booking' ),
			'cni_receipt'    => __( 'National ID receipt', 'radius-hotel-booking' ),
			'id_certificate' => __( 'Identity certificate', 'radius-hotel-booking' ),
			'driver_license' => __( 'Driver license', 'radius-hotel-booking' ),
		);
		$rows = array();
		foreach ( $types as $key => $label ) {
			$rows[] = array(
				'key'   => $key,
				'label' => $label,
			);
		}
		return $rows;
	}

	/**
	 * The identity document types the hotel accepts, in their order.
	 *
	 * @return array<string, string> Key => label.
	 */
	public static function idTypes(): array {
		$saved = rtbp_setting( 'website', 'idTypes', self::defaultIdTypes() );
		$rows  = self::idTypesValue( $saved );
		$out   = array();
		foreach ( is_array( $rows ) ? $rows : self::defaultIdTypes() as $row ) {
			$out[ $row['key'] ] = $row['label'];
		}
		return $out;
	}

	/**
	 * `idTypes`: at least one named type. A row keeps its key (guests on file
	 * point to it); a new row gets one from its name, unique in the list.
	 *
	 * @param mixed $value Input: [ { key?, label } ].
	 * @return array|WP_Error
	 */
	public static function idTypesValue( $value ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'invalid', __( 'Add the identity documents guests can choose.', 'radius-hotel-booking' ) );
		}
		$rows = array();
		$used = array();
		foreach ( array_values( $value ) as $row ) {
			$label = self::shortText( is_array( $row ) ? ( $row['label'] ?? '' ) : '' );
			if ( '' === $label ) {
				continue;
			}
			$key = substr( sanitize_key( (string) ( $row['key'] ?? '' ) ), 0, 30 );
			if ( '' === $key ) {
				// A name in a non-Latin script slugs to %-escapes: use a short hash instead.
				$slug = sanitize_title( $label );
				$key  = false === strpos( $slug, '%' ) ? substr( str_replace( '-', '_', sanitize_key( $slug ) ), 0, 26 ) : '';
				$key  = '' !== $key ? $key : 'doc_' . substr( md5( $label ), 0, 8 );
			}
			$base = substr( $key, 0, 26 );
			for ( $n = 2; isset( $used[ $key ] ); $n++ ) {
				$key = $base . '_' . $n;
			}
			$used[ $key ] = true;
			$rows[]       = array(
				'key'   => $key,
				'label' => $label,
			);
		}
		if ( ! $rows ) {
			return new WP_Error( 'invalid', __( 'Add at least one identity document.', 'radius-hotel-booking' ) );
		}
		if ( count( $rows ) > self::MAX_ID_TYPES ) {
			/* translators: %d: most document types allowed. */
			return new WP_Error( 'invalid', sprintf( __( 'Add up to %d identity documents.', 'radius-hotel-booking' ), self::MAX_ID_TYPES ) );
		}
		return $rows;
	}

	/**
	 * One line of plain text, up to 100 characters.
	 *
	 * @param mixed $value Input.
	 * @return string
	 */
	private static function shortText( $value ): string {
		$text = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 100 ) : substr( $text, 0, 100 );
	}

	/**
	 * A published page, or 0.
	 *
	 * @param mixed $value Page id.
	 * @return int|WP_Error
	 */
	public static function page( $value ) {
		$id = (int) $value;
		if ( 0 === $id ) {
			return 0;
		}
		return 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id )
			? $id
			: new WP_Error( 'rtbp_invalid_setting', __( 'Choose a published page.', 'radius-hotel-booking' ) );
	}

	/**
	 * The published pages to choose from (title and id), for the settings tab.
	 *
	 * @return array<int, array{id: int, title: string}>
	 */
	public static function pages(): array {
		$pages = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'post_title',
				'number'      => 200,
			)
		);
		return array_map(
			static fn( $page ) => array(
				'id'    => (int) $page->ID,
				'title' => '' !== $page->post_title ? $page->post_title : sprintf( '#%d', $page->ID ),
			),
			(array) $pages
		);
	}
}
