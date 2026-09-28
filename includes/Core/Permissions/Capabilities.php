<?php
/**
 * Radius Hotel Booking capability registry.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Permissions
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Permissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Central registry for plugin capabilities and the built-in role => cap map.
 *
 * Single source of truth for the permission system, consumed by
 * PermissionMiddleware (enforcement), PermissionsInstaller (provisioning) and
 * LoadAssets (frontend localization).
 *
 * BOILERPLATE: replace the three example capabilities below with the ones your
 * plugin actually needs, then add matching entries to catalog() and
 * defaultRoleMap(). Nothing else in the permission system needs editing.
 */
class Capabilities {

	const VIEW_DASHBOARD  = 'rtbp_view_dashboard';
	const MANAGE_ITEMS    = 'rtbp_manage_items';
	const MANAGE_SETTINGS = 'rtbp_manage_settings';

	/**
	 * Option holding admin-edited role => cap overrides (the Roles & Permissions
	 * matrix). Overlays the code defaults in roleMap().
	 */
	const CAPS_OPTION = 'rtbp_role_caps';

	/**
	 * Every plugin capability. Filterable so an add-on can register more.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		$caps = array(
			self::VIEW_DASHBOARD,
			self::MANAGE_ITEMS,
			self::MANAGE_SETTINGS,
		);

		return array_values( array_unique( (array) apply_filters( 'rtbp_capabilities', $caps ) ) );
	}

	/**
	 * Code-default built-in role slug => capabilities.
	 *
	 * The core `administrator` role is intentionally absent: admins pass via the
	 * `manage_options` bypass in PermissionMiddleware / userCan(), so we never
	 * mutate the WordPress administrator role (safer on multisite).
	 *
	 * @return array<string,string[]>
	 */
	public static function defaultRoleMap(): array {
		return array(
			'rtbp_manager' => array(
				self::VIEW_DASHBOARD,
				self::MANAGE_ITEMS,
				self::MANAGE_SETTINGS,
			),
			'rtbp_staff'   => array(
				self::VIEW_DASHBOARD,
				self::MANAGE_ITEMS,
			),
		);
	}

	/**
	 * Effective role => cap map: code defaults overlaid with the admin-edited
	 * matrix stored in the CAPS_OPTION, then filtered. PermissionsInstaller::sync()
	 * reads this, so UI edits survive re-syncs.
	 *
	 * @return array<string,string[]>
	 */
	public static function roleMap(): array {
		// Add-ons inject custom roles first, so the CAPS_OPTION overlay below can
		// apply the admin's matrix edits to custom roles too.
		$map = apply_filters( 'rtbp_role_capabilities_map', self::defaultRoleMap() );

		$stored = get_option( self::CAPS_OPTION, array() );
		if ( is_array( $stored ) ) {
			$known = self::all();
			foreach ( $stored as $slug => $caps ) {
				// Override any role present in the map (built-in or custom) with
				// recognised caps only.
				if ( isset( $map[ $slug ] ) && is_array( $caps ) ) {
					$map[ $slug ] = array_values( array_intersect( $known, $caps ) );
				}
			}
		}

		return $map;
	}

	/**
	 * Role slugs an admin may edit and assign through the Roles & Permissions UI.
	 *
	 * @return string[]
	 */
	public static function manageableRoles(): array {
		return array_values( array_unique( (array) apply_filters( 'rtbp_manageable_roles', array_keys( self::defaultRoleMap() ) ) ) );
	}

	/**
	 * Capability catalog for the Roles & Permissions UI: each cap with a human
	 * label and a group heading.
	 *
	 * @return array<int,array{cap:string,label:string,group:string}>
	 */
	public static function catalog(): array {
		$meta = array(
			self::VIEW_DASHBOARD  => array( __( 'View Dashboard', 'radius-hotel-booking' ), __( 'General', 'radius-hotel-booking' ) ),
			self::MANAGE_ITEMS    => array( __( 'Manage Items', 'radius-hotel-booking' ), __( 'Content', 'radius-hotel-booking' ) ),
			self::MANAGE_SETTINGS => array( __( 'Manage Settings', 'radius-hotel-booking' ), __( 'System', 'radius-hotel-booking' ) ),
		);

		// Add-ons supply labels/groups for the capabilities they register.
		$meta = (array) apply_filters( 'rtbp_capability_catalog_meta', $meta );

		$out = array();
		foreach ( self::all() as $cap ) {
			$out[] = array(
				'cap'   => $cap,
				'label' => $meta[ $cap ][0] ?? $cap,
				'group' => $meta[ $cap ][1] ?? __( 'Other', 'radius-hotel-booking' ),
			);
		}
		return $out;
	}

	/**
	 * Human-readable titles for the built-in roles.
	 *
	 * @return array<string,string>
	 */
	public static function roleLabels(): array {
		$labels = array(
			'rtbp_manager' => __( 'Manager', 'radius-hotel-booking' ),
			'rtbp_staff'   => __( 'Staff', 'radius-hotel-booking' ),
		);

		// Add-ons supply labels for the custom roles they register.
		return (array) apply_filters( 'rtbp_role_labels', $labels );
	}

	/**
	 * Whether the current user holds a plugin capability. Administrators
	 * (manage_options) implicitly hold every capability, so a plugin cap can
	 * never lock a site admin out.
	 *
	 * @param string $cap Capability to check.
	 * @return bool
	 */
	public static function userCan( string $cap ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return current_user_can( $cap );
	}

	/**
	 * Capability => bool map for the current user, localized to the React admin
	 * so it can hide/disable features it can't use.
	 *
	 * @return array<string,bool>
	 */
	public static function forCurrentUser(): array {
		$is_admin = current_user_can( 'manage_options' );
		$caps     = array();
		foreach ( self::all() as $cap ) {
			$caps[ $cap ] = $is_admin ? true : current_user_can( $cap );
		}
		return $caps;
	}
}
