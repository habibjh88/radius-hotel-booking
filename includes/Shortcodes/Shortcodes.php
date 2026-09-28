<?php
/**
 * Shortcode registry.
 *
 * @package RadiusTheme\RadiusHotelBooking\Shortcodes
 */

namespace RadiusTheme\RadiusHotelBooking\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Class Shortcodes
 *
 * Every shortcode the plugin registers is listed in init(). Each tag is passed
 * through a filter so a site can rename it without touching this file.
 *
 * @since 1.0.0
 */
class Shortcodes {

	/**
	 * Register the shortcodes.
	 *
	 * @return void
	 */
	public static function init() {
		/**
		 * Filters the plugin's shortcodes (tag => callback). None ship yet: the
		 * booking search and booking form shortcodes arrive with M04.
		 *
		 * @param array<string,callable> $shortcodes Shortcodes.
		 */
		$shortcodes = (array) apply_filters( 'rtbp_shortcodes', array() );

		foreach ( $shortcodes as $shortcode => $callback ) {
			add_shortcode( apply_filters( "rtbp_{$shortcode}_shortcode_tag", $shortcode ), $callback );
		}
	}

	/**
	 * Render a shortcode inside a wrapper element and return the markup.
	 *
	 * @param callable $callback Callback producing the output.
	 * @param array    $atts     Shortcode attributes.
	 * @param array    $wrapper  Wrapper config: 'class', 'before', 'after'.
	 *
	 * @return string
	 */
	public static function shortcode_wrapper(
		$callback,
		$atts = array(),
		$wrapper = array(
			'class'  => 'radius-hotel-booking',
			'before' => null,
			'after'  => null,
		)
	) {
		ob_start();

		echo empty( $wrapper['before'] )
			? '<div class="' . esc_attr( $wrapper['class'] ) . '">'
			: wp_kses_post( $wrapper['before'] );

		call_user_func( $callback, $atts );

		echo empty( $wrapper['after'] )
			? '</div>'
			: wp_kses_post( $wrapper['after'] );

		return ob_get_clean();
	}
}
