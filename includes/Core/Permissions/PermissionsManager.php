<?php
/**
 * Runtime permission wiring for Radius Hotel Booking.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Permissions
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Permissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Grants Radius Hotel Booking capabilities to administrators at runtime so every
 * `current_user_can( 'rtbp_*' )` check — admin menu, REST middleware, React
 * localization — passes for site admins without ever mutating the core
 * `administrator` role (keeps multisite super-admins working automatically).
 */
class PermissionsManager {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'user_has_cap', array( $this, 'grant_admin_plugin_caps' ), 10, 3 );
		// WooCommerce redirects users without edit_posts/manage_woocommerce out
		// of wp-admin to the My Account page. Let plugin-role users (who hold
		// rtbp_view_dashboard) reach the admin so they can use their dashboard.
		add_filter( 'woocommerce_prevent_admin_access', array( $this, 'allow_plugin_admin_access' ) );
	}

	/**
	 * Allow users with a plugin capability into wp-admin despite WooCommerce's
	 * admin-access restriction. Users without it (no rtbp_view_dashboard) are
	 * still redirected, as before.
	 *
	 * @param bool $prevent Whether WooCommerce intends to block wp-admin access.
	 * @return bool
	 */
	public function allow_plugin_admin_access( $prevent ) {
		if ( $prevent && current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			return false;
		}
		return $prevent;
	}

	/**
	 * Dynamically grant any requested `rtbp_*` capability to users who can
	 * `manage_options`. Only the capabilities actually being checked are added,
	 * so this stays cheap on every cap check.
	 *
	 * @param bool[]   $allcaps All capabilities currently granted to the user.
	 * @param string[] $caps    Primitive capabilities being checked.
	 * @param array    $args    Arguments passed to the cap check (unused).
	 * @return bool[]
	 */
	public function grant_admin_plugin_caps( $allcaps, $caps, $args ) {
		if ( empty( $allcaps['manage_options'] ) ) {
			return $allcaps;
		}
		foreach ( (array) $caps as $cap ) {
			if ( is_string( $cap ) && 0 === strpos( $cap, 'rtbp_' ) ) {
				$allcaps[ $cap ] = true;
			}
		}
		return $allcaps;
	}
}
