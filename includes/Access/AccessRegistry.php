<?php
/**
 * Access key registry.
 *
 * @package RadiusTheme\RadiusHotelBooking\Access
 */

namespace RadiusTheme\RadiusHotelBooking\Access;

use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Every page and action the access map controls (M13, ADR-004). A key is
 * `page.<area>` for a screen and `<area>.<verb>` for an action.
 *
 * Each key has:
 *
 * - `kind`: `page` | `action`
 * - `group`: the matrix row group (see groups())
 * - `label`: translated, for the permission matrix
 * - `default`: `open` | `locked`. The free plugin never defaults to `passcode`;
 *   keys the plan marks "passcode" default to `open` here, and Pro raises them.
 * - `legacy`: the legacy key it replaces, for the M18 import (optional)
 *
 * The free plugin registers the keys of its own features. Pro and the client
 * add-on add theirs (activity log, staff, payroll) with register() from
 * `rtbp_access_registry_init`, or with the `rtbp_access_keys` filter.
 */
final class AccessRegistry {

	/**
	 * Keys registered at runtime, before the filter runs.
	 *
	 * @var array<string, array>
	 */
	private static array $registered = array();

	/**
	 * The resolved registry, built once per request.
	 *
	 * @var array<string, array>|null
	 */
	private static ?array $resolved = null;

	/**
	 * Register (or replace) a key.
	 *
	 * @param string $key        Access key, e.g. `bookings.approve`.
	 * @param array  $definition kind, group, label, default, legacy.
	 * @return void
	 */
	public static function register( string $key, array $definition ): void {
		self::$registered[ $key ] = $definition;
		self::$resolved           = null;
	}

	/**
	 * Every key, normalised.
	 *
	 * @return array<string, array>
	 */
	public static function keys(): array {
		if ( null !== self::$resolved ) {
			return self::$resolved;
		}

		// Guard against re-entry from a filter that reads the registry.
		self::$resolved = array();

		/**
		 * Fires once, when the registry is first needed. Add-ons call
		 * AccessRegistry::register() here.
		 */
		do_action( 'rtbp_access_registry_init' );

		/**
		 * Filters the access keys.
		 *
		 * @param array $keys Key => definition (kind, group, label, default, legacy).
		 */
		$keys = (array) apply_filters( 'rtbp_access_keys', array_merge( self::core(), self::$registered ) );

		$resolved = array();
		foreach ( $keys as $key => $definition ) {
			$key = (string) $key;
			if ( ! preg_match( '/^[a-z0-9_]+\.[a-z0-9_]+$/', $key ) || ! is_array( $definition ) ) {
				continue;
			}
			$resolved[ $key ] = self::normalise( $key, $definition );
		}

		self::$resolved = $resolved;
		return self::$resolved;
	}

	/**
	 * One key's definition, or null when it is not registered.
	 *
	 * @param string $key Access key.
	 * @return array|null
	 */
	public static function get( string $key ): ?array {
		return self::keys()[ $key ] ?? null;
	}

	/**
	 * Whether a key is registered.
	 *
	 * @param string $key Access key.
	 * @return bool
	 */
	public static function has( string $key ): bool {
		return null !== self::get( $key );
	}

	/**
	 * Matrix groups, in display order.
	 *
	 * @return array<string, string> Group => translated label.
	 */
	public static function groups(): array {
		$groups = array(
			'pages'     => __( 'Pages', 'radius-hotel-booking' ),
			'bookings'  => __( 'Bookings', 'radius-hotel-booking' ),
			'payments'  => __( 'Payments and invoices', 'radius-hotel-booking' ),
			'guests'    => __( 'Guests', 'radius-hotel-booking' ),
			'inventory' => __( 'Rooms, rates and availability', 'radius-hotel-booking' ),
			'reports'   => __( 'Reports and exports', 'radius-hotel-booking' ),
			'settings'  => __( 'Settings and access', 'radius-hotel-booking' ),
		);

		/**
		 * Filters the permission matrix groups.
		 *
		 * @param array $groups Group => translated label.
		 */
		return (array) apply_filters( 'rtbp_access_groups', $groups );
	}

	/**
	 * The access key that guards writing a settings section: `settings.<section>`,
	 * or `access.manage` for the permission map. Add-ons route their own
	 * security sections to `access.manage` too (Pro: passcodes, feature switches).
	 *
	 * @param string $section Section key.
	 * @return string
	 */
	public static function settingsKey( string $section ): string {
		$key = 'access' === $section ? 'access.manage' : 'settings.' . $section;

		/**
		 * Filters the access key that guards writing a settings section.
		 *
		 * @param string $key     Access key.
		 * @param string $section Section key.
		 */
		return (string) apply_filters( 'rtbp_settings_access_key', $key, $section );
	}

	/**
	 * Forget the resolved registry (runtime registration, logic checks).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$resolved = null;
	}

	/**
	 * Fill a definition's optional fields and clamp its default.
	 *
	 * @param string $key        Access key.
	 * @param array  $definition Definition.
	 * @return array
	 */
	private static function normalise( string $key, array $definition ): array {
		$definition = array_merge(
			array(
				'kind'    => str_starts_with( $key, 'page.' ) ? 'page' : 'action',
				'group'   => str_starts_with( $key, 'page.' ) ? 'pages' : strtok( $key, '.' ),
				'label'   => $key,
				'default' => Access::LOCKED,
				'legacy'  => '',
			),
			$definition
		);

		// Unknown defaults fail closed.
		if ( ! in_array( $definition['default'], Access::levels(), true ) ) {
			$definition['default'] = Access::LOCKED;
		}

		$definition['key'] = $key;
		return $definition;
	}

	/**
	 * The free plugin's own keys (M13 "Access key registry").
	 *
	 * @return array<string, array>
	 */
	private static function core(): array {
		$open   = Access::OPEN;
		$locked = Access::LOCKED;

		$keys = array(
			// Pages.
			'page.dashboard'         => array( $open, __( 'Dashboard', 'radius-hotel-booking' ), '' ),
			'page.bookings'          => array( $open, __( 'Bookings', 'radius-hotel-booking' ), 'booking' ),
			'page.rooms'             => array( $open, __( 'Rooms', 'radius-hotel-booking' ), 'rooms' ),
			'page.rates'             => array( $locked, __( 'Rate plans and prices', 'radius-hotel-booking' ), '' ),
			'page.availability'      => array( $open, __( 'Availability', 'radius-hotel-booking' ), 'availability' ),
			'page.guests'            => array( $open, __( 'Guests', 'radius-hotel-booking' ), 'customers' ),
			'page.reports_sales'     => array( $open, __( 'Sales report', 'radius-hotel-booking' ), 'sales' ),
			'page.reports_rooms'     => array( $open, __( 'Rooms report', 'radius-hotel-booking' ), 'rooms' ),
			'page.exports'           => array( $locked, __( 'Exports', 'radius-hotel-booking' ), 'booking-backup' ),
			'page.settings'          => array( $locked, __( 'Settings', 'radius-hotel-booking' ), '' ),

			// Bookings.
			'bookings.create'        => array( $open, __( 'Add a booking', 'radius-hotel-booking' ), 'add-booking' ),
			'bookings.approve'       => array( $open, __( 'Approve a booking', 'radius-hotel-booking' ), 'approve-booking' ),
			'bookings.decline'       => array( $open, __( 'Decline a booking', 'radius-hotel-booking' ), 'decline-booking' ),
			'bookings.cancel'        => array( $open, __( 'Cancel a booking', 'radius-hotel-booking' ), 'cancel-booking' ),
			'bookings.check_in'      => array( $open, __( 'Check in', 'radius-hotel-booking' ), 'check-in' ),
			'bookings.check_out'     => array( $open, __( 'Check out', 'radius-hotel-booking' ), 'check-out' ),
			'bookings.no_show'       => array( $open, __( 'Mark as no-show', 'radius-hotel-booking' ), '' ),
			'bookings.line_add'      => array( $open, __( 'Add a room to a booking', 'radius-hotel-booking' ), 'add-booking-item' ),
			'bookings.line_edit'     => array( $open, __( 'Edit a room on a booking', 'radius-hotel-booking' ), 'edit-booking-item' ),
			'bookings.line_remove'   => array( $open, __( 'Remove a room from a booking', 'radius-hotel-booking' ), 'remove-booking-item' ),
			'bookings.note_add'      => array( $open, __( 'Add a booking note', 'radius-hotel-booking' ), 'add-order-note' ),
			'bookings.note_edit'     => array( $open, __( 'Edit a booking note', 'radius-hotel-booking' ), 'edit-order-note' ),
			'bookings.note_remove'   => array( $open, __( 'Remove a booking note', 'radius-hotel-booking' ), 'remove-order-note' ),

			// Payments and invoices.
			'payments.record'        => array( $open, __( 'Record a payment', 'radius-hotel-booking' ), 'mark-as-paid' ),
			'payments.change_status' => array( $open, __( 'Change the payment status', 'radius-hotel-booking' ), 'modify-order-status' ),
			'payments.void'          => array( $locked, __( 'Void a payment', 'radius-hotel-booking' ), '' ),
			'invoices.send'          => array( $open, __( 'Send an invoice', 'radius-hotel-booking' ), '' ),
			'invoices.regenerate'    => array( $open, __( 'Regenerate an invoice', 'radius-hotel-booking' ), '' ),

			// Guests.
			'guests.create'          => array( $open, __( 'Add a guest', 'radius-hotel-booking' ), '' ),
			'guests.edit'            => array( $open, __( 'Edit a guest', 'radius-hotel-booking' ), 'edit-customer' ),
			'guests.view_id'         => array( $open, __( 'See identity documents', 'radius-hotel-booking' ), '' ),
			'guests.ban'             => array( $open, __( 'Ban a guest', 'radius-hotel-booking' ), 'ban-users' ),
			'guests.note_add'        => array( $open, __( 'Add a guest note', 'radius-hotel-booking' ), 'add-customer-note' ),
			'guests.note_edit'       => array( $open, __( 'Edit a guest note', 'radius-hotel-booking' ), 'edit-customer-note' ),
			'guests.note_remove'     => array( $open, __( 'Remove a guest note', 'radius-hotel-booking' ), 'remove-customer-note' ),

			// Rooms, rates and availability.
			'rooms.manage'           => array( $locked, __( 'Manage rooms', 'radius-hotel-booking' ), 'manage-room-number' ),
			'rooms.move'             => array( $locked, __( 'Move a room to another room type', 'radius-hotel-booking' ), '' ),
			'room_types.manage'      => array( $locked, __( 'Manage room types', 'radius-hotel-booking' ), '' ),
			'rates.manage'           => array( $locked, __( 'Manage rate plans', 'radius-hotel-booking' ), '' ),
			'pricing.manage'         => array( $locked, __( 'Manage prices', 'radius-hotel-booking' ), '' ),
			'availability.manage'    => array( $locked, __( 'Manage availability and blocks', 'radius-hotel-booking' ), '' ),

			// Reports and exports.
			'reports.export'         => array( $open, __( 'Export a report', 'radius-hotel-booking' ), '' ),
			'exports.generate'       => array( $locked, __( 'Generate an export', 'radius-hotel-booking' ), 'generate-booking-backup' ),
			'exports.download'       => array( $locked, __( 'Download an export', 'radius-hotel-booking' ), 'download-booking-backup' ),
			'exports.delete'         => array( $locked, __( 'Delete an export', 'radius-hotel-booking' ), 'remove-booking-backup' ),

			// Access.
			'access.manage'          => array( $locked, __( 'Manage permissions', 'radius-hotel-booking' ), '' ),
		);

		// Area => matrix group, where they differ.
		$groups = array(
			'page'         => 'pages',
			'invoices'     => 'payments',
			'rooms'        => 'inventory',
			'room_types'   => 'inventory',
			'rates'        => 'inventory',
			'pricing'      => 'inventory',
			'availability' => 'inventory',
			'exports'      => 'reports',
			'access'       => 'settings',
		);

		$out = array();
		foreach ( $keys as $key => $row ) {
			$area = strtok( $key, '.' );

			$out[ $key ] = array(
				'default' => $row[0],
				'label'   => $row[1],
				'legacy'  => $row[2],
				'group'   => $groups[ $area ] ?? $area,
			);
		}

		// One key per settings section, add-on sections included:
		// `settings.<section>`, unless settingsKey() maps it elsewhere.
		$labels = array(
			'general'       => __( 'Change general settings', 'radius-hotel-booking' ),
			'booking'       => __( 'Change booking rules', 'radius-hotel-booking' ),
			'notifications' => __( 'Change notification settings', 'radius-hotel-booking' ),
			'email'         => __( 'Change e-mail settings', 'radius-hotel-booking' ),
			'display'       => __( 'Change display settings', 'radius-hotel-booking' ),
			'website'       => __( 'Change public booking settings', 'radius-hotel-booking' ),
		);
		foreach ( array_keys( SettingsHelper::all() ) as $section ) {
			// Sections guarded by another key (the permission map) get none.
			if ( self::settingsKey( $section ) !== 'settings.' . $section ) {
				continue;
			}
			$out[ 'settings.' . $section ] = array(
				'default' => $locked,
				/* translators: %s: settings section key, e.g. "pro_features". */
				'label'   => $labels[ $section ] ?? sprintf( __( 'Change settings: %s', 'radius-hotel-booking' ), $section ),
				'legacy'  => 'general' === $section ? 'edit-business-info' : '',
				'group'   => 'settings',
			);
		}

		return $out;
	}
}
