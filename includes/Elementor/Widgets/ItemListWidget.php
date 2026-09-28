<?php
/**
 * Item list Elementor widget.
 *
 * @package RadiusTheme\RadiusHotelBooking\Elementor\Widgets
 */

namespace RadiusTheme\RadiusHotelBooking\Elementor\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use RadiusTheme\RadiusHotelBooking\Assets\LoadAssets;
use RadiusTheme\RadiusHotelBooking\Shortcodes\ItemList;

/**
 * Class ItemListWidget
 *
 * BOILERPLATE: the example widget. It exposes the same three attributes as the
 * block and the shortcode and renders through the same template.
 */
class ItemListWidget extends Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'rtbp-item-list';
	}

	/**
	 * Widget label.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Item List', 'radius-hotel-booking' );
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
	 * Register the widget controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => esc_html__( 'Layout', 'radius-hotel-booking' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => esc_html__( 'Layout', 'radius-hotel-booking' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid' => esc_html__( 'Grid', 'radius-hotel-booking' ),
					'list' => esc_html__( 'List', 'radius-hotel-booking' ),
				),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => esc_html__( 'Columns', 'radius-hotel-booking' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 3,
				'min'     => 1,
				'max'     => 6,
			)
		);

		$this->add_control(
			'per_page',
			array(
				'label'   => esc_html__( 'Items per page', 'radius-hotel-booking' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 9,
				'min'     => 1,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		// The public bundle is enqueued automatically only when the shortcode
		// or block is in the post content; a widget renders outside it, so pull
		// the already-registered handle in here.
		LoadAssets::enqueue_site_app();

		ItemList::output(
			array(
				'layout'   => $settings['layout'] ?? '',
				'columns'  => $settings['columns'] ?? '',
				'per_page' => $settings['per_page'] ?? '',
			)
		);
	}
}
