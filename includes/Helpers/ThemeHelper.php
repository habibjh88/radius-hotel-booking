<?php
/**
 * Brand colour helper.
 *
 * @package RadiusTheme\RadiusHotelBooking\Helpers
 */

namespace RadiusTheme\RadiusHotelBooking\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Turns the Settings → Display primary colour into the CSS variables the apps
 * are styled with.
 *
 * Every shade the UI uses (soft tints, hover, focus ring) is derived from
 * `--primary` in src/index.css with color-mix(), so overriding that one
 * variable re-brands the whole plugin. The override is printed inline right
 * after the stylesheet, so the saved colour is there on first paint — no flash
 * of the default blue.
 */
class ThemeHelper {

	/**
	 * Default brand colour.
	 */
	const DEFAULT_PRIMARY = '#0040ff';

	/**
	 * The saved primary colour, validated.
	 *
	 * @return string Hex colour.
	 */
	public static function primary_color(): string {
		$display = SettingsHelper::get_setting( 'display' );
		$color   = is_array( $display ) ? sanitize_hex_color( (string) ( $display['primaryColor'] ?? '' ) ) : null;

		return $color ? $color : self::DEFAULT_PRIMARY;
	}

	/**
	 * Inline CSS overriding the brand variables.
	 *
	 * @return string
	 */
	public static function inline_css(): string {
		return sprintf( '.rtbp-root{--primary:%s;}', self::primary_color() );
	}

	/**
	 * Attach the override to an enqueued stylesheet handle.
	 *
	 * @param string $handle Style handle.
	 * @return void
	 */
	public static function attach_to( string $handle ): void {
		if ( wp_style_is( $handle, 'registered' ) || wp_style_is( $handle, 'enqueued' ) ) {
			wp_add_inline_style( $handle, self::inline_css() );
		}
	}
}
