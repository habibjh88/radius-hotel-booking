<?php
/**
 * The free plugin's own settings sections.
 *
 * @package RadiusTheme\RadiusHotelBooking\Settings
 */

namespace RadiusTheme\RadiusHotelBooking\Settings;

use RadiusTheme\RadiusHotelBooking\Access\Access;
use RadiusTheme\RadiusHotelBooking\Access\AccessRegistry;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the sections the free plugin owns. Later modules add theirs with
 * SettingsSchema::register() (the `rtbp-settings-section` skill).
 */
final class CoreSettings {

	/**
	 * Register the core sections.
	 *
	 * @return void
	 */
	public static function register(): void {
		SettingsSchema::register( 'general', self::general() );
		SettingsSchema::register( 'booking', self::booking() );
		SettingsSchema::register( 'display', self::display() );
		SettingsSchema::register( 'notifications', self::notifications() );
		SettingsSchema::register( 'email', self::email() );
		SettingsSchema::register( 'access', self::access() );
	}

	/**
	 * Access (M13): the open/locked level of each access key per built-in role.
	 * Keys a role does not set fall back to Access::role_defaults(), then to the
	 * registry default. The passcode level and custom roles are Pro's, in its
	 * own option.
	 *
	 * @return array
	 */
	private static function access(): array {
		return array(
			// Role slug => access key => open | locked.
			'roleLevels' => array(
				'type'     => 'array',
				'default'  => array(),
				'sanitize' => array( self::class, 'role_levels' ),
			),
		);
	}

	/**
	 * General: hotel identity, date/time and currency formats. Shared with
	 * e-mails, invoices (M05) and payslips (M15).
	 *
	 * @return array
	 */
	private static function general(): array {
		return array(
			// The hotel's trading name (e-mails, booking pages, documents).
			'companyName'           => array(
				'type'      => 'string',
				'default'   => static fn() => (string) get_bloginfo( 'name' ),
				'maxLength' => 120,
			),
			// Registered company name for invoices and payslips; empty = the hotel name.
			'legalName'             => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => 160,
			),
			// Postal address, several lines.
			'address'               => array(
				'type'      => 'text',
				'default'   => '',
				'maxLength' => 500,
			),
			'phone'                 => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => 40,
			),
			'contactEmail'          => array(
				'type'    => 'email',
				'default' => static fn() => (string) get_option( 'admin_email' ),
			),
			// Media library image (attachment id, 0 = none): e-mails and documents.
			'logo'                  => array(
				'type'    => 'media',
				'default' => 0,
				'mime'    => array( 'image/' ),
			),
			// Tax registration number printed on invoices.
			'taxNumber'             => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => 60,
			),
			// Employer social-security number (CNPS in Côte d'Ivoire), printed on payslips.
			'cnpsNumber'            => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => 60,
			),
			// PHP date format for display; defaults to the WordPress setting.
			'dateFormat'            => array(
				'type'      => 'string',
				'default'   => static fn() => (string) get_option( 'date_format', 'Y-m-d' ),
				'maxLength' => 30,
			),
			// '24h' or '12h'; defaults from the WordPress time format.
			'timeSystem'            => array(
				'type'    => 'enum',
				'options' => array( '24h', '12h' ),
				'default' => static fn() => false !== strpbrk( (string) get_option( 'time_format', 'g:i a' ), 'GH' ) ? '24h' : '12h',
			),
			// Currency (Support\Money). "15 000 CFA" is XOF / CFA / right_space / ' ' / ',' / 0.
			'currencyCode'          => array(
				'type'      => 'string',
				'default'   => 'USD',
				'maxLength' => 3,
			),
			'currencySymbol'        => array(
				'type'      => 'string',
				'default'   => '$',
				'maxLength' => 10,
			),
			'currencyPosition'      => array(
				'type'    => 'enum',
				'options' => array( 'left', 'right', 'left_space', 'right_space' ),
				'default' => 'left',
			),
			'thousandSeparator'     => array(
				'type'     => 'string',
				'default'  => ',',
				'sanitize' => array( self::class, 'separator' ),
			),
			'decimalSeparator'      => array(
				'type'     => 'string',
				'default'  => '.',
				'sanitize' => array( self::class, 'separator' ),
			),
			'decimals'              => array(
				'type'    => 'int',
				'default' => 2,
				'min'     => 0,
				'max'     => 4,
			),
			// uninstall.php removes every table, option, role and file only when true.
			'deleteDataOnUninstall' => array(
				'type'    => 'bool',
				'default' => false,
			),
		);
	}

	/**
	 * Booking rules: what guests may book and how rooms turn over. Read by
	 * the availability engine (M08), the booking flows (M02, M04) and holds.
	 *
	 * @return array
	 */
	private static function booking(): array {
		return array(
			// Days ahead a guest may book online; 0 = no limit (17.7).
			'bookingWindowDays' => array(
				'type'    => 'int',
				'default' => 0,
				'min'     => 0,
				'max'     => 3650,
			),
			// Guests may book online for today (17.8).
			'sameDayEnabled'    => array(
				'type'    => 'bool',
				'default' => false,
			),
			// Latest time (site time, HH:MM) for a same-day online booking.
			'sameDayCutoff'     => array(
				'type'    => 'time',
				'default' => '14:00',
			),
			// New bookings wait for staff approval (17.9).
			'manualApproval'    => array(
				'type'    => 'bool',
				'default' => false,
			),
			// Unavailable rooms in search results: 'hide', or 'disable' = shown but not bookable (17.11).
			'unavailableRooms'  => array(
				'type'    => 'enum',
				'options' => array( 'hide', 'disable' ),
				'default' => 'hide',
			),
			// Guests up to this age (years) count as children (8.9).
			'maxChildAge'       => array(
				'type'    => 'int',
				'default' => 16,
				'min'     => 0,
				'max'     => 17,
			),
			// Minutes a selected room is held while the booking form is filled in.
			'holdMinutes'       => array(
				'type'    => 'int',
				'default' => 15,
				'min'     => 5,
				'max'     => 120,
			),
			// Cleaning time (minutes) after a stay before the room can be sold again (D4).
			'bufferMinutes'     => array(
				'type'    => 'int',
				'default' => 0,
				'min'     => 0,
				'max'     => 1440,
			),
			// Default check-in and check-out times (HH:MM) for nightly rate plans.
			'checkInTime'       => array(
				'type'    => 'time',
				'default' => '14:00',
			),
			'checkOutTime'      => array(
				'type'    => 'time',
				'default' => '12:00',
			),
		);
	}

	/**
	 * Display: the brand colour (ThemeHelper, e-mails, the dashboard).
	 *
	 * @return array
	 */
	private static function display(): array {
		return array(
			'primaryColor' => array(
				'type'    => 'color',
				'default' => '#0040ff',
			),
		);
	}

	/**
	 * Notifications: the new-booking alert on staff screens (17.1, 17.2).
	 * Read by the dashboard poller (ADR-006).
	 *
	 * @return array
	 */
	private static function notifications(): array {
		return array(
			// Show an alert on every staff screen when a booking arrives.
			'newBookingAlert' => array(
				'type'    => 'bool',
				'default' => true,
			),
			// Play a sound with the alert.
			'playSound'       => array(
				'type'    => 'bool',
				'default' => true,
			),
			// Media library audio (attachment id); 0 = the built-in chime.
			'soundId'         => array(
				'type'    => 'media',
				'default' => 0,
				'mime'    => array( 'audio/' ),
			),
			// Seconds between checks for new bookings while a staff screen is open.
			'pollSeconds'     => array(
				'type'    => 'int',
				'default' => 30,
				'min'     => 10,
				'max'     => 300,
			),
		);
	}

	/**
	 * E-mail: sender and delivery.
	 *
	 * @return array
	 */
	private static function email(): array {
		return array(
			'enabled'      => array(
				'type'    => 'bool',
				'default' => true,
			),
			'senderName'   => array(
				'type'      => 'string',
				'default'   => static fn() => (string) get_bloginfo( 'name' ),
				'maxLength' => 120,
			),
			'senderEmail'  => array(
				'type'    => 'email',
				'default' => static fn() => (string) get_option( 'admin_email' ),
			),
			'replyToEmail' => array(
				'type'    => 'email',
				'default' => '',
			),
			// Per template: email id => on/off. A template not listed uses its own default.
			'templates'    => array(
				'type'     => 'array',
				'default'  => array(),
				'sanitize' => array( self::class, 'switches' ),
			),
			// Hand e-mails to a queue (something must hook `rtbp_queue_email`).
			'use_queue'    => array(
				'type'    => 'bool',
				'default' => false,
			),
		);
	}

	/**
	 * A map of ids to on/off, e.g. `{ "booking_confirmed": false }`.
	 *
	 * @param mixed $value Value.
	 * @return array|\WP_Error
	 */
	public static function switches( $value ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error( 'rtbp_invalid_setting', __( 'This value is not valid.', 'radius-hotel-booking' ) );
		}
		$clean = array();
		foreach ( $value as $id => $on ) {
			$id = sanitize_key( (string) $id );
			if ( '' !== $id ) {
				$clean[ $id ] = (bool) filter_var( $on, FILTER_VALIDATE_BOOLEAN );
			}
		}
		return $clean;
	}

	/**
	 * Role => key => level: built-in plugin roles, registered keys and the
	 * allowed levels (open, locked; `rtbp_access_role_levels`) only.
	 *
	 * @param mixed $value Value.
	 * @return array|\WP_Error
	 */
	public static function role_levels( $value ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error( 'rtbp_invalid_setting', __( 'This value is not valid.', 'radius-hotel-booking' ) );
		}

		$roles = Capabilities::manageableRoles();

		/**
		 * Filters the levels the permission matrix may store. The free plugin
		 * stores open and locked; Pro adds passcode. A stored passcode without
		 * a passcode handler is enforced as locked.
		 *
		 * @param string[] $levels Levels.
		 */
		$levels = array_intersect( (array) apply_filters( 'rtbp_access_role_levels', array( Access::OPEN, Access::LOCKED ) ), Access::levels() );
		$clean  = array();
		foreach ( $value as $role => $keys ) {
			if ( ! in_array( $role, $roles, true ) || ! is_array( $keys ) ) {
				continue;
			}
			foreach ( $keys as $key => $level ) {
				if ( AccessRegistry::has( (string) $key ) && in_array( $level, $levels, true ) ) {
					$clean[ $role ][ $key ] = $level;
				}
			}
		}
		return $clean;
	}

	/**
	 * A number separator: up to 2 characters, spaces kept (XOF uses a space).
	 *
	 * @param mixed $value Value.
	 * @return string|\WP_Error
	 */
	public static function separator( $value ) {
		// No markup; wp_strip_all_tags() would trim the space XOF needs.
		if ( ! is_scalar( $value ) || preg_match( '/[<>]/', (string) $value ) ) {
			return new \WP_Error( 'rtbp_invalid_setting', __( 'This value is not valid.', 'radius-hotel-booking' ) );
		}
		// A non-breaking space is stored as a plain space.
		$value = str_replace( "\u{00A0}", ' ', (string) $value );
		if ( mb_strlen( $value ) > 2 ) {
			/* translators: %d: the maximum number of characters. */
			return new \WP_Error( 'rtbp_invalid_setting', sprintf( __( 'Use %d characters or fewer.', 'radius-hotel-booking' ), 2 ) );
		}
		return $value;
	}
}
