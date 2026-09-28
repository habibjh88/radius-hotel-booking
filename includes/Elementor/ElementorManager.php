<?php
/**
 * Elementor integration.
 *
 * @package RadiusTheme\RadiusHotelBooking\Elementor
 */

namespace RadiusTheme\RadiusHotelBooking\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class ElementorManager
 *
 * Registers the plugin's Elementor category and widgets. Instantiated only when
 * Elementor is active — see the guard in the main plugin file.
 */
class ElementorManager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
	}

	/**
	 * Add the plugin's widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 *
	 * @return void
	 */
	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'radius-hotel-booking',
			array(
				'title' => esc_html__( 'Radius Hotel Booking', 'radius-hotel-booking' ),
				'icon'  => 'eicon-archive',
			)
		);
	}

	/**
	 * Register the widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 *
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {
		/**
		 * Register Elementor widgets. None ship yet: the booking widgets arrive
		 * with M04 and render the same templates as the shortcodes.
		 *
		 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
		 */
		do_action( 'rtbp_register_elementor_widgets', $widgets_manager );
	}
}
