<?php
/**
 * The public embeds: one renderer for the shortcode, the block and the
 * Elementor widget of each (M04).
 *
 * @package RadiusTheme\RadiusHotelBooking\Frontend
 */

namespace RadiusTheme\RadiusHotelBooking\Frontend;

use RadiusTheme\RadiusHotelBooking\Assets\LoadAssets;

defined( 'ABSPATH' ) || exit;

/**
 * Each embed renders **one** template (`templates/embeds/<embed>.php`,
 * overridable from a theme at `radius-hotel-booking/embeds/…`), whichever way
 * it was placed — `[rtbp_search]`, the *Search bar* block or the Elementor
 * widget — so the three can never drift (CLAUDE.md). The template prints a
 * mount node the public app (src/site/main.jsx) takes over, with a plain HTML
 * fallback inside it that works before or without JavaScript.
 */
class Embeds {

	/**
	 * Block namespace.
	 */
	public const BLOCK_PREFIX = 'radius-hotel-booking/';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'rtbp_shortcodes', array( self::class, 'shortcodes' ) );
		add_action( 'rtbp_register_blocks', array( self::class, 'blocks' ) );
		add_action( 'rtbp_register_elementor_widgets', array( self::class, 'elementor' ) );
	}

	/**
	 * `rtbp_shortcodes`: the search bar.
	 *
	 * @param array $shortcodes Tag => callback.
	 * @return array
	 */
	public static function shortcodes( $shortcodes ): array {
		$shortcodes                = (array) $shortcodes;
		$shortcodes['rtbp_search']  = static fn( $atts = array() ) => self::search( (array) $atts );
		$shortcodes['rtbp_booking'] = static fn( $atts = array() ) => self::booking( (array) $atts );
		return $shortcodes;
	}

	/**
	 * `rtbp_register_blocks`: the *Search bar* block (dynamic; the editor UI is
	 * in src/blocks/search).
	 *
	 * @return void
	 */
	public static function blocks(): void {
		register_block_type(
			self::BLOCK_PREFIX . 'search',
			array(
				'api_version'     => 3,
				'title'           => __( 'Hotel search bar', 'radius-hotel-booking' ),
				'category'        => 'radius-hotel-booking',
				'render_callback' => static fn() => self::search(),
			)
		);
		register_block_type(
			self::BLOCK_PREFIX . 'booking',
			array(
				'api_version'     => 3,
				'title'           => __( 'Hotel booking', 'radius-hotel-booking' ),
				'category'        => 'radius-hotel-booking',
				'render_callback' => static fn() => self::booking(),
			)
		);
	}

	/**
	 * `rtbp_register_elementor_widgets`: the *Hotel search bar* widget.
	 *
	 * @param object $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public static function elementor( $widgets_manager ): void {
		if ( class_exists( '\Elementor\Widget_Base' ) && is_object( $widgets_manager ) && method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( new \RadiusTheme\RadiusHotelBooking\Elementor\Widgets\SearchWidget() );
			$widgets_manager->register( new \RadiusTheme\RadiusHotelBooking\Elementor\Widgets\BookingWidget() );
		}
	}

	/**
	 * The search bar's markup (4.1, 4.2).
	 *
	 * @param array $atts Shortcode attributes (none yet; kept for later options).
	 * @return string
	 */
	public static function search( array $atts = array() ): string {
		LoadAssets::enqueue_site_app();
		$rules = self::rules();
		ob_start();
		rtbp_get_template(
			'embeds/search.php',
			array(
				'action' => $rules['resultsUrl'],
				'rules'  => $rules,
				'atts'   => $atts,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * The guest booking flow's markup (4.3–4.10): the mount node the app takes
	 * over; without JavaScript, a short message with the hotel's phone.
	 *
	 * @param array $atts Shortcode attributes (none yet).
	 * @return string
	 */
	public static function booking( array $atts = array() ): string {
		LoadAssets::enqueue_site_app();
		ob_start();
		rtbp_get_template(
			'embeds/booking.php',
			array(
				'phone' => (string) rtbp_setting( 'general', 'phone', '' ),
				'atts'  => $atts,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * The public booking rules the embeds and the date pickers need — the same
	 * values the server enforces (`AvailabilityService`), never anything
	 * private. Also sent to the site app (`radius_hotel_booking_site_param.booking`).
	 *
	 * @return array
	 */
	public static function rules(): array {
		$page = (int) rtbp_setting( 'website', 'resultsPageId', 0 );
		$url  = $page && 'publish' === get_post_status( $page ) ? (string) get_permalink( $page ) : '';
		return array(
			// Where the search bar sends guests ('' = stay on this page).
			'resultsUrl'        => $url,
			'defaultAdults'     => (int) rtbp_setting( 'website', 'defaultAdults', 2 ),
			'showRoomsField'    => (bool) rtbp_setting( 'website', 'showRoomsField', false ),
			'bookingWindowDays' => max( 0, (int) rtbp_setting( 'booking', 'bookingWindowDays', 0 ) ),
			'sameDayEnabled'    => (bool) rtbp_setting( 'booking', 'sameDayEnabled', false ),
			'sameDayCutoff'     => (string) rtbp_setting( 'booking', 'sameDayCutoff', '14:00' ),
			// The booking form (M04 T2b).
			'guestPicksRoom'    => (bool) rtbp_setting( 'website', 'guestPicksRoom', true ),
			'privacyConsent'    => (bool) rtbp_setting( 'website', 'privacyConsent', true ),
			'privacyUrl'        => (string) get_privacy_policy_url(),
			'idTypes'           => \RadiusTheme\RadiusHotelBooking\Services\Guests\GuestService::idTypes(),
		);
	}
}
