<?php
/**
 * Elementor widget: the room type cards.
 *
 * @package RadiusTheme\RadiusHotelBooking\Elementor\Widgets
 */

namespace RadiusTheme\RadiusHotelBooking\Elementor\Widgets;

use RadiusTheme\RadiusHotelBooking\Frontend\Embeds;

defined( 'ABSPATH' ) || exit;

/**
 * Renders `Frontend\Embeds::rooms()` — the same template as `[rtbp_rooms]`
 * and the block. Loaded only when Elementor is active (ElementorManager).
 */
class RoomsWidget extends \Elementor\Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'rtbp-rooms';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Hotel room types', 'radius-hotel-booking' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-posts-grid';
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
		return array( 'hotel', 'rooms', 'room types', 'grid' );
	}

	/**
	 * Controls: layout, columns, limit, room count.
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
				'default' => 'grid',
				'options' => array(
					'grid' => __( 'Grid', 'radius-hotel-booking' ),
					'list' => __( 'List', 'radius-hotel-booking' ),
				),
			)
		);
		$this->add_control(
			'columns',
			array(
				'label'     => __( 'Columns', 'radius-hotel-booking' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '3',
				'options'   => array(
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'condition' => array( 'layout' => 'grid' ),
			)
		);
		$this->add_control(
			'limit',
			array(
				'label'       => __( 'Limit', 'radius-hotel-booking' ),
				'description' => __( 'How many room types to show. 0 shows them all.', 'radius-hotel-booking' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'default'     => 0,
				'min'         => 0,
			)
		);
		$this->add_control(
			'show_count',
			array(
				'label'        => __( 'Show the number of rooms', 'radius-hotel-booking' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
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
		echo Embeds::rooms(
			array(
				'layout'     => $settings['layout'] ?? 'grid',
				'columns'    => $settings['columns'] ?? 3,
				'limit'      => $settings['limit'] ?? 0,
				'show_count' => 'yes' === ( $settings['show_count'] ?? '' ) ? 'yes' : 'no',
			)
		);
	}
}
