<?php
/**
 * Gutenberg block registration.
 *
 * @package RadiusTheme\RadiusHotelBooking\Blocks
 */

namespace RadiusTheme\RadiusHotelBooking\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Shortcodes\ItemList;

/**
 * Class BlockManager
 *
 * Registers the plugin's block category and its blocks. Blocks are declared in
 * PHP (attributes + render callback) and their editor UI lives in the `blocks`
 * webpack entry (`src/blocks/`), which is enqueued by @wordpress/scripts'
 * generated asset file.
 */
class BlockManager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ) );
	}

	/**
	 * Add the plugin's own block category.
	 *
	 * @param array $categories Existing block categories.
	 *
	 * @return array
	 */
	public function register_block_category( $categories ) {
		return array_merge(
			array(
				array(
					'slug'  => 'radius-hotel-booking',
					'title' => esc_html__( 'Radius Hotel Booking', 'radius-hotel-booking' ),
					'icon'  => 'screenoptions',
				),
			),
			$categories
		);
	}

	/**
	 * Register the blocks.
	 *
	 * BOILERPLATE: one example block, server-rendered through the same template
	 * the shortcode uses, so both paths stay in sync.
	 *
	 * @return void
	 */
	public function register_blocks() {
		register_block_type(
			'radius-hotel-booking/items',
			array(
				'attributes'      => array(
					'layout'  => array(
						'type'    => 'string',
						'default' => 'grid',
					),
					'columns' => array(
						'type'    => 'number',
						'default' => 3,
					),
					'perPage' => array(
						'type'    => 'number',
						'default' => 9,
					),
				),
				'render_callback' => array( $this, 'render_items_block' ),
			)
		);
	}

	/**
	 * Server-side render for the items block.
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string
	 */
	public function render_items_block( $attributes ) {
		ob_start();

		ItemList::output(
			array(
				'layout'   => $attributes['layout'] ?? '',
				'columns'  => $attributes['columns'] ?? '',
				'per_page' => $attributes['perPage'] ?? '',
			)
		);

		return ob_get_clean();
	}

	/**
	 * Enqueue the block editor bundle.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets() {
		$script = RADIUS_HOTEL_BOOKING_DIR . 'build/blocks.js';

		if ( ! file_exists( $script ) ) {
			return;
		}

		$meta = include RADIUS_HOTEL_BOOKING_DIR . 'build/blocks.asset.php';

		wp_enqueue_script(
			'radius-hotel-booking-blocks',
			RADIUS_HOTEL_BOOKING_BUILD . '/blocks.js',
			$meta['dependencies'] ?? array(),
			$meta['version'] ?? RADIUS_HOTEL_BOOKING_VERSION,
			true
		);

		wp_set_script_translations( 'radius-hotel-booking-blocks', 'radius-hotel-booking', RADIUS_HOTEL_BOOKING_DIR . 'languages' );
	}
}
