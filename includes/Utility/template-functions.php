<?php
/**
 * Template functions.
 *
 * Theme-facing helpers. A theme or template calls these to render the plugin's
 * front-end pieces without knowing about the classes behind them.
 *
 * @package RadiusTheme\RadiusHotelBooking
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Assets\LoadAssets;

if ( ! function_exists( 'rtbp_page_url' ) ) {
	/**
	 * URL of one of the pages the plugin created on install.
	 *
	 * @param string $key Page key from Setup\PageInstaller, e.g. 'bookingPage'.
	 *
	 * @return string Empty string when the page does not exist.
	 */
	function rtbp_page_url( string $key ): string {
		$pages   = get_option( \RadiusTheme\RadiusHotelBooking\Common\Keys::PAGES, array() );
		$page_id = is_array( $pages ) ? ( $pages[ $key ] ?? 0 ) : 0;

		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}
}
