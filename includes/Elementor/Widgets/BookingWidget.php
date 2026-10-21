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
	 * Controls: layout, display, button text (the same options as the
	 * shortcode, Embeds::BOOKING_DEFAULTS).
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'layout_section',
			array(
				'label' => __( 'Layout', 'radius-hotel-booking' ),
			)
		);
		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'radius-hotel-booking' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => Embeds::BOOKING_DEFAULTS['layout'],
				'options' => array_column( Embeds::bookingLayouts(), 'label', 'value' ),
			)
		);
		$this->add_control(
			'display',
			array(
				'label'       => __( 'Display', 'radius-hotel-booking' ),
				'description' => __( 'In a popup, a button opens the form. Any link to #rtbp-booking on the page opens it too.', 'radius-hotel-booking' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => Embeds::BOOKING_DEFAULTS['display'],
				'options'     => array_column( Embeds::bookingDisplays(), 'label', 'value' ),
			)
		);
		$this->add_control(
			'button_text',
			array(
				'label'       => __( 'Button text', 'radius-hotel-booking' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Book now', 'radius-hotel-booking' ),
				'condition'   => array( 'display' => 'modal' ),
			)
		);
		$this->add_control(
			'room_details',
			array(
				'label'       => __( 'Room details', 'radius-hotel-booking' ),
				'description' => __( 'What "Room details" on each room type opens.', 'radius-hotel-booking' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => Embeds::BOOKING_DEFAULTS['room_details'],
				'options'     => array_column( Embeds::bookingRoomDetails(), 'label', 'value' ),
			)
		);
		$this->add_control(
			'room_card',
			array(
				'label'       => __( 'Room type design', 'radius-hotel-booking' ),
				'description' => __( 'How each room type and its rates are laid out in the results.', 'radius-hotel-booking' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => Embeds::BOOKING_DEFAULTS['room_card'],
				'options'     => array_column( Embeds::bookingRoomCards(), 'label', 'value' ),
			)
		);
		$this->add_control(
			'rates_shown',
			array(
				'label'       => __( 'Rates shown first', 'radius-hotel-booking' ),
				'description' => __( 'Rates per room type before "Show more rates". 0 shows them all, without the button.', 'radius-hotel-booking' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'default'     => Embeds::BOOKING_DEFAULTS['rates_shown'],
				'min'         => 0,
				'max'         => 50,
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes every value.
		echo Embeds::booking(
			array(
				'layout'       => $settings['layout'] ?? Embeds::BOOKING_DEFAULTS['layout'],
				'display'      => $settings['display'] ?? Embeds::BOOKING_DEFAULTS['display'],
				'button_text'  => $settings['button_text'] ?? '',
				'room_details' => $settings['room_details'] ?? Embeds::BOOKING_DEFAULTS['room_details'],
				'room_card'    => $settings['room_card'] ?? Embeds::BOOKING_DEFAULTS['room_card'],
				'rates_shown'  => $settings['rates_shown'] ?? Embeds::BOOKING_DEFAULTS['rates_shown'],
			)
		);
	}
}
