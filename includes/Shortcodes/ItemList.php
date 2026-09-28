<?php
/**
 * `[rtbp_items]` shortcode output.
 *
 * @package RadiusTheme\RadiusHotelBooking\Shortcodes
 */

namespace RadiusTheme\RadiusHotelBooking\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class ItemList
 *
 * BOILERPLATE: the example shortcode. It renders a template that prints the
 * mount node for the `site` webpack entry, passing the shortcode attributes to
 * React as data attributes.
 */
class ItemList {

	/**
	 * Output the item list.
	 *
	 * @param array|string $atts Shortcode attributes.
	 *
	 * @return void
	 */
	public static function output( $atts ): void {
		$atts = shortcode_atts(
			array(
				'layout'   => '',
				'columns'  => '',
				'per_page' => '',
			),
			$atts,
			'rtbp_items'
		);

		rtbp_get_template( 'items/item-list.php', array( 'atts' => $atts ) );
	}
}
