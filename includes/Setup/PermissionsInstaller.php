<?php
/**
 * Provisions the plugin roles and capabilities.
 *
 * @package RadiusTheme\RadiusHotelBooking\Setup
 */

namespace RadiusTheme\RadiusHotelBooking\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;

/**
 * Creates the built-in plugin roles and grants each its capabilities.
 *
 * Version-gated by the `rtbp_roles_version` option so the sync runs only on
 * install and when the role/capability definitions change — never on every
 * request.
 */
class PermissionsInstaller {

	/**
	 * Bump when the capability set or role map changes so existing sites re-sync.
	 */
	const ROLES_VERSION = '1';

	/**
	 * Option storing the last-synced roles version.
	 */
	const VERSION_OPTION = 'rtbp_roles_version';

	/**
	 * Run the sync only if the stored roles version is behind. Cheap no-op
	 * otherwise — safe to call on every admin_init.
	 *
	 * Roles are intentionally left intact on deactivation so users who were
	 * granted access are not locked out. Uninstall cleanup is handled
	 * separately by remove_roles().
	 *
	 * @return void
	 */
	public static function maybe_sync(): void {
		if ( get_option( self::VERSION_OPTION ) === self::ROLES_VERSION ) {
			return;
		}
		self::sync();
	}

	/**
	 * Create/patch the built-in roles and reconcile their capabilities against
	 * the role map. Idempotent: grants missing plugin caps and strips plugin
	 * caps a role should no longer have, leaving non-plugin caps untouched.
	 *
	 * @return void
	 */
	public static function sync(): void {
		$map    = Capabilities::roleMap();
		$labels = Capabilities::roleLabels();
		$all    = array_flip( Capabilities::all() );

		foreach ( $map as $slug => $caps ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				$label = $labels[ $slug ] ?? ucwords( str_replace( array( 'rtbp_', '_' ), array( '', ' ' ), $slug ) );
				$role  = add_role( $slug, $label, array( 'read' => true ) );
			}
			if ( ! $role ) {
				continue;
			}

			$wanted = array_flip( $caps );

			// Reconcile only plugin caps; never touch caps outside our namespace.
			foreach ( $all as $cap => $unused ) {
				$has = $role->has_cap( $cap );
				if ( isset( $wanted[ $cap ] ) && ! $has ) {
					$role->add_cap( $cap );
				} elseif ( ! isset( $wanted[ $cap ] ) && $has ) {
					$role->remove_cap( $cap );
				}
			}

			// Baseline WP cap so the role can authenticate.
			if ( ! $role->has_cap( 'read' ) ) {
				$role->add_cap( 'read' );
			}
		}

		update_option( self::VERSION_OPTION, self::ROLES_VERSION );
	}

	/**
	 * Remove the built-in roles and the version marker. Intended for uninstall,
	 * not deactivation.
	 *
	 * @return void
	 */
	public static function remove_roles(): void {
		foreach ( array_keys( Capabilities::roleMap() ) as $slug ) {
			remove_role( $slug );
		}
		delete_option( self::VERSION_OPTION );
	}
}
