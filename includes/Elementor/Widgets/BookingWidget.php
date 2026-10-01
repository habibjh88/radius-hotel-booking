<?php
/**
 * Elementor widget: the guest booking flow (M04, 4.3–4.10).
 *
 * @package RadiusTheme\RadiusHotelBooking\Elementor\Widgets
 */

namespace RadiusTheme\RadiusHotelBooking\Elementor\Widgets;

use RadiusTheme\RadiusHotelBooking\Frontend\Embeds;

defined( 'ABSPATH' ) || exit;

/**
 * Renders `Frontend\Embeds::booking()` — the same template as `[rtbp_booking]`
 * and the block. Loaded only when Elementor is active (ElementorManager).
 */
class BookingWidget extends \Elementor\Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'rtbp-booking';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Hotel booking', 'radius-hotel-booking' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-calendar';
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
		return array( 'hotel', 'booking', 'book', 'room' );
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes every value.
		echo Embeds::booking();
	}
}
