<?php
/**
 * Elementor widget: the hotel search bar (M04, 4.1).
 *
 * @package RadiusTheme\RadiusHotelBooking\Elementor\Widgets
 */

namespace RadiusTheme\RadiusHotelBooking\Elementor\Widgets;

use RadiusTheme\RadiusHotelBooking\Frontend\Embeds;

defined( 'ABSPATH' ) || exit;

/**
 * Renders `Frontend\Embeds::search()` — the same template as `[rtbp_search]`
 * and the block. Loaded only when Elementor is active (ElementorManager).
 */
class SearchWidget extends \Elementor\Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'rtbp-search';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Hotel search bar', 'radius-hotel-booking' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-search';
	}

	/**
	 * Widget categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'radius-hotel-booking' );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'hotel', 'booking', 'search', 'room' );
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes every value.
		echo Embeds::search();
	}
}
