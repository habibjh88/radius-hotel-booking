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
use RadiusTheme\RadiusHotelBooking\Shortcodes\ItemList;

if ( ! function_exists( 'rtbp_the_items' ) ) {
	/**
	 * Render the public item list anywhere in a theme template.
	 *
	 * Enqueues the public bundle first, so this works from a template part that
	 * runs after `wp_enqueue_scripts`.
	 *
	 * @param array $args {
	 *     Optional. Display arguments.
	 *
	 *     @type string $layout   'grid' or 'list'.
	 *     @type int    $columns  Number of columns.
	 *     @type int    $per_page Items per page.
	 * }
	 *
	 * @return void
	 */
	function rtbp_the_items( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'layout'   => '',
				'columns'  => '',
				'per_page' => '',
			)
		);

		LoadAssets::enqueue_site_app();

		ItemList::output( $args );
	}
}

if ( ! function_exists( 'rtbp_get_items_html' ) ) {
	/**
	 * Same as rtbp_the_items(), returned as a string.
	 *
	 * @param array $args Display arguments. See rtbp_the_items().
	 *
	 * @return string
	 */
	function rtbp_get_items_html( $args = array() ) {
		ob_start();
		rtbp_the_items( $args );

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'rtbp_page_url' ) ) {
	/**
	 * URL of one of the pages the plugin created on install.
	 *
	 * @param string $key Page key from Setup\PageInstaller, e.g. 'itemsPage'.
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
