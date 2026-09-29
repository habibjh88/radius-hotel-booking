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

use RadiusTheme\RadiusHotelBooking\Access\Access;
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
			$this->menu_icon(),
			$menu_position
		);

		if ( current_user_can( $capability ) ) { // phpcs:ignore
			// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
			// Label, hash route, M13 page key: an item is listed unless its page is locked.
			$items = array(
				array( esc_attr__( 'Dashboard', 'radius-hotel-booking' ), '#/', 'page.dashboard' ),
				array( esc_attr__( 'Bookings', 'radius-hotel-booking' ), '#/bookings', 'page.bookings' ),
				array( esc_attr__( 'Availability', 'radius-hotel-booking' ), '#/calendar', 'page.availability' ),
				array( esc_attr__( 'Guests', 'radius-hotel-booking' ), '#/guests', 'page.guests' ),
				array( esc_attr__( 'Rooms & floors', 'radius-hotel-booking' ), '#/rooms', 'page.rooms' ),
				array( esc_attr__( 'Settings', 'radius-hotel-booking' ), '#/settings', 'page.settings' ),
			);
			foreach ( $items as $item ) {
				if ( Access::LOCKED !== Access::level( $item[2] ) ) {
					$submenu[ $slug ][] = array( $item[0], Capabilities::VIEW_DASHBOARD, 'admin.php?page=' . $slug . $item[1] );
				}
			}
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
	 * The admin-menu icon: the logo's building, as a monochrome SVG data URI so
	 * WordPress repaints it to match the admin colour scheme.
	 *
	 * @return string
	 */
	private function menu_icon(): string {
		$file = RADIUS_HOTEL_BOOKING_DIR . 'assets/images/menu-icon.svg';

		if ( ! is_readable( $file ) ) {
			return 'dashicons-building';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		$svg = (string) file_get_contents( $file );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- the data URI format WordPress expects for menu icons.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
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
