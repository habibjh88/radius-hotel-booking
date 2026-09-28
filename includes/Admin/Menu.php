<?php
/**
 * Admin menu.
 *
 * Registers the top-level menu and its submenu items. Each item carries its own
 * capability, so WordPress hides what the current user cannot access and the
 * menu adapts to the role. Submenu hrefs are HashRouter paths into the single
 * React admin app.
 *
 * @package RadiusTheme\RadiusHotelBooking\Admin
 */

namespace RadiusTheme\RadiusHotelBooking\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;

/**
 * Class Menu
 */
class Menu {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'init_menu' ) );
	}

	/**
	 * Register the menu.
	 *
	 * @return void
	 */
	public function init_menu() {
		global $submenu;

		$slug          = RADIUS_HOTEL_BOOKING_SLUG;
		$menu_position = 50;

		// Baseline cap to open the admin app. Administrators hold every rtbp_*
		// cap via PermissionsManager, so they always see the menu.
		$capability = Capabilities::VIEW_DASHBOARD;

		add_menu_page(
			esc_attr__( 'Radius Hotel Booking', 'radius-hotel-booking' ),
			esc_attr__( 'Radius Hotel Booking', 'radius-hotel-booking' ),
			$capability,
			$slug,
			array( $this, 'plugin_page' ),
			'dashicons-screenoptions',
			$menu_position
		);

		if ( current_user_can( $capability ) ) { // phpcs:ignore
			// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu[ $slug ][] = array( esc_attr__( 'Dashboard', 'radius-hotel-booking' ), Capabilities::VIEW_DASHBOARD, 'admin.php?page=' . $slug . '#/' );
			$submenu[ $slug ][] = array( esc_attr__( 'Items', 'radius-hotel-booking' ), Capabilities::MANAGE_ITEMS, 'admin.php?page=' . $slug . '#/items' );
			$submenu[ $slug ][] = array( esc_attr__( 'Settings', 'radius-hotel-booking' ), Capabilities::MANAGE_SETTINGS, 'admin.php?page=' . $slug . '#/settings' );
			// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

			/**
			 * Fires after the plugin's submenu items are registered.
			 *
			 * @since 1.0.0
			 */
			do_action( 'rtbp_after_settings_menu_item' );
		}
	}

	/**
	 * Render the admin page (the React mount point).
	 *
	 * @return void
	 */
	public function plugin_page() {
		require_once RADIUS_HOTEL_BOOKING_TEMPLATE_PATH . '/app.php';
	}
}
