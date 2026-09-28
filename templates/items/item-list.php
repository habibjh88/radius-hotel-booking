<?php
/**
 * Public item list template — mount point for the `site` React entry.
 *
 * Override in a theme at `radius-hotel-booking/items/item-list.php`.
 *
 * @var array $atts Shortcode attributes.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$atts = isset( $atts ) && is_array( $atts ) ? $atts : array();
?>
<div
	class="rtbp-item-list"
	id="radius-hotel-booking-site"
	data-layout="<?php echo esc_attr( $atts['layout'] ?? '' ); ?>"
	data-columns="<?php echo esc_attr( $atts['columns'] ?? '' ); ?>"
	data-per-page="<?php echo esc_attr( $atts['per_page'] ?? '' ); ?>"
></div>
