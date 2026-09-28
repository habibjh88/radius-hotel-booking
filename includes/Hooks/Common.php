<?php
/**
 * Common front-end / admin hooks.
 *
 * @package RadiusTheme\RadiusHotelBooking\Hooks
 */

namespace RadiusTheme\RadiusHotelBooking\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Common\Keys;

/**
 * Class Common
 *
 * Small cross-cutting hooks that don't belong to a feature: labelling the
 * plugin's pages in wp-admin, adding a body class on them, and suppressing
 * third-party admin notices on the plugin's own screens.
 *
 * @since 1.0.0
 */
class Common {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_head', array( $this, 'hide_notices_on_plugin_page' ), 1 );
		add_filter( 'display_post_states', array( $this, 'add_page_states' ), 10, 2 );
		add_filter( 'body_class', array( $this, 'add_page_body_class' ) );
	}

	/**
	 * Remove other plugins' admin notices from this plugin's screens, which are
	 * a full-page React app that notices would push off-screen.
	 *
	 * @return void
	 */
	public function hide_notices_on_plugin_page() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || false === strpos( (string) $screen->id, RADIUS_HOTEL_BOOKING_SLUG ) ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
	}

	/**
	 * Label the plugin's own pages in the Pages list table.
	 *
	 * @param string[] $states Existing post states.
	 * @param \WP_Post $post   Current post.
	 *
	 * @return string[]
	 */
	public function add_page_states( $states, $post ) {
		$pages = get_option( Keys::PAGES, array() );

		if ( is_array( $pages ) && in_array( (int) $post->ID, array_map( 'intval', $pages ), true ) ) {
			$states[] = esc_html__( 'Radius Hotel Booking Page', 'radius-hotel-booking' );
		}

		return $states;
	}

	/**
	 * Add a body class on the plugin's front-end pages so themes and the plugin
	 * stylesheet can target them.
	 *
	 * @param string[] $classes Body classes.
	 *
	 * @return string[]
	 */
	public function add_page_body_class( $classes ) {
		$pages = get_option( Keys::PAGES, array() );

		if ( is_page() && is_array( $pages ) && in_array( (int) get_the_ID(), array_map( 'intval', $pages ), true ) ) {
			$classes[] = 'rtbp-page';
		}

		return $classes;
	}
}
