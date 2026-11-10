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
	const ROLES_VERSION = '4';

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
			$label = $labels[ $slug ] ?? ucwords( str_replace( array( 'rtbp_', '_' ), array( '', ' ' ), $slug ) );
			$role  = get_role( $slug );
			if ( ! $role ) {
				$role = add_role( $slug, Capabilities::wpRoleName( $label ), array( 'read' => true ) );
			} else {
				self::rename_role( $slug, $label, Capabilities::wpRoleName( $label ) );
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

			// Strip capabilities the plugin no longer grants.
			foreach ( Capabilities::retired() as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
				}
			}

			// Baseline WP cap so the role can authenticate.
			if ( ! $role->has_cap( 'read' ) ) {
				$role->add_cap( 'read' );
			}
		}

		self::sync_guest_role();

		update_option( self::VERSION_OPTION, self::ROLES_VERSION );
	}

	/**
	 * Give an existing role its new name, but only while it still has the old
	 * default one: a name the hotel chose stays. WordPress has no rename API,
	 * so the stored role list is updated in place.
	 *
	 * @param string $slug     Role slug.
	 * @param string $old_name The name it was created with.
	 * @param string $new_name The name it should have.
	 * @return void
	 */
	private static function rename_role( string $slug, string $old_name, string $new_name ): void {
		$roles   = wp_roles();
		$current = $roles->roles[ $slug ]['name'] ?? null;
		if ( $current !== $old_name ) {
			return;
		}
		$roles->roles[ $slug ]['name'] = $new_name;
		$roles->role_names[ $slug ]    = $new_name;
		if ( $roles->use_db ) {
			update_option( $roles->role_key, $roles->roles );
		}
	}

	/**
	 * Create the guest role: `read` only, and no plugin capability even if
	 * one was added to it by hand.
	 *
	 * @return void
	 */
	private static function sync_guest_role(): void {
		$name = Capabilities::wpRoleName( __( 'Guest', 'radius-hotel-booking' ) );
		$role = get_role( Capabilities::GUEST_ROLE );
		if ( ! $role ) {
			$role = add_role( Capabilities::GUEST_ROLE, $name, array( 'read' => true ) );
		} else {
			self::rename_role( Capabilities::GUEST_ROLE, __( 'Hotel Guest', 'radius-hotel-booking' ), $name );
		}
		if ( ! $role ) {
			return;
		}
		foreach ( array_merge( Capabilities::all(), Capabilities::retired() ) as $cap ) {
			if ( $role->has_cap( $cap ) ) {
				$role->remove_cap( $cap );
			}
		}
		if ( ! $role->has_cap( 'read' ) ) {
			$role->add_cap( 'read' );
		}
	}

	/**
	 * Remove the built-in roles and the version marker. Intended for uninstall,
	 * not deactivation.
	 *
	 * @return void
	 */
	public static function remove_roles(): void {
		foreach ( array_keys( Capabilities::roleMap() ) as $slug ) {
			// The map is filterable; never remove a core role an add-on grants caps to.
			if ( 0 === strpos( $slug, 'rtbp_' ) ) {
				remove_role( $slug );
			}
		}
		remove_role( Capabilities::GUEST_ROLE );
		delete_option( self::VERSION_OPTION );
	}
}
