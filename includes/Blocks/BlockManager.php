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
					'icon'  => 'building',
				),
			),
			$categories
		);
	}

	/**
	 * Register the blocks.
	 *
	 * None yet: the booking search and booking form blocks arrive with M04 and
	 * render the same templates as their shortcodes. Each block is registered
	 * here with a server-side render callback; its editor UI lives in
	 * `src/blocks/`.
	 *
	 * @return void
	 */
	public function register_blocks() {
		/**
		 * Fires when the plugin registers its blocks, so an add-on can register
		 * its own under the same category.
		 */
		do_action( 'rtbp_register_blocks' );
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
