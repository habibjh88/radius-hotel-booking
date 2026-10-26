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
	 * `[rtbp_rooms]` attributes and their defaults — also the defaults the
	 * Shortcodes page's builder starts from (catalog()).
	 */
	public const ROOMS_DEFAULTS = array(
		'layout'     => 'grid',
		'columns'    => 3,
		'ids'        => '',
		'limit'      => 0,
		'show_count' => 'no',
	);

	/**
	 * `[rtbp_booking]` attributes and their defaults (also the Shortcodes
	 * page's builder, the block and the Elementor widget). An empty
	 * `button_text` shows "Book now" in the site's language.
	 */
	public const BOOKING_DEFAULTS = array(
		'layout'       => 'steps',
		'display'      => 'inline',
		'button_text'  => '',
		'room_details' => 'page',
		'room_card'    => 'classic',
		'rates_shown'     => 4,
		'price_breakdown' => 'no',
	);

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'rtbp_shortcodes', array( self::class, 'shortcodes' ) );
		add_filter( 'rtbp_shortcode_catalog', array( self::class, 'catalog' ) );
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
		$shortcodes['rtbp_rooms']   = static fn( $atts = array() ) => self::rooms( (array) $atts );
		return $shortcodes;
	}

	/**
	 * `rtbp_shortcode_catalog`: how the search bar and booking shortcodes are
	 * described on the dashboard's Shortcodes page.
	 *
	 * @param array $catalog Key => description.
	 * @return array
	 */
	public static function catalog( $catalog ): array {
		$catalog                 = (array) $catalog;
		$catalog['rtbp_search']  = array(
			'title'       => __( 'Hotel search bar', 'radius-hotel-booking' ),
			'description' => __( 'Arrival, departure and guests in one bar. Guests search here and land on the booking page with their dates. Place it on the home page or any page.', 'radius-hotel-booking' ),
			'also'        => array(
				__( 'Block: Hotel search bar', 'radius-hotel-booking' ),
				__( 'Elementor widget: Hotel search bar', 'radius-hotel-booking' ),
			),
			'order'       => 10,
		);
		$catalog['rtbp_booking'] = array(
			'title'       => __( 'Hotel booking', 'radius-hotel-booking' ),
			'description' => __( 'The full guest booking flow: rates, room choice, guest details and confirmation. Put it on one page and choose that page under Settings → Public booking.', 'radius-hotel-booking' ),
			'also'        => array(
				__( 'Block: Hotel booking', 'radius-hotel-booking' ),
				__( 'Elementor widget: Hotel booking', 'radius-hotel-booking' ),
			),
			'order'       => 20,
			'attributes'  => array(
				array(
					'name'    => 'layout',
					'label'   => __( 'Layout', 'radius-hotel-booking' ),
					'type'    => 'select',
					'default' => self::BOOKING_DEFAULTS['layout'],
					'options' => self::bookingLayouts(),
				),
				array(
					'name'    => 'display',
					'label'   => __( 'Display', 'radius-hotel-booking' ),
					'type'    => 'select',
					'default' => self::BOOKING_DEFAULTS['display'],
					'options' => self::bookingDisplays(),
					'help'    => __( 'In a popup, a button opens the form. Any link to #rtbp-booking on the page opens it too.', 'radius-hotel-booking' ),
				),
				array(
					'name'    => 'button_text',
					'label'   => __( 'Button text', 'radius-hotel-booking' ),
					'type'    => 'text',
					'default' => self::BOOKING_DEFAULTS['button_text'],
					'help'    => __( 'Empty shows "Book now".', 'radius-hotel-booking' ),
					'when'    => array( 'display' => 'modal' ),
				),
				array(
					'name'    => 'room_details',
					'label'   => __( 'Room details', 'radius-hotel-booking' ),
					'type'    => 'select',
					'default' => self::BOOKING_DEFAULTS['room_details'],
					'options' => self::bookingRoomDetails(),
					'help'    => __( 'What "Room details" on each room type opens.', 'radius-hotel-booking' ),
				),
				array(
					'name'    => 'room_card',
					'label'   => __( 'Room type design', 'radius-hotel-booking' ),
					'type'    => 'select',
					'default' => self::BOOKING_DEFAULTS['room_card'],
					'options' => self::bookingRoomCards(),
					'help'    => __( 'How each room type and its rates are laid out in the results.', 'radius-hotel-booking' ),
				),
				array(
					'name'    => 'rates_shown',
					'label'   => __( 'Rates shown first', 'radius-hotel-booking' ),
					'type'    => 'number',
					'default' => (string) self::BOOKING_DEFAULTS['rates_shown'],
					'min'     => 0,
					'max'     => 50,
					'help'    => __( 'Rates per room type before "Show more rates". 0 shows them all, without the button.', 'radius-hotel-booking' ),
				),
				array(
					'name'    => 'price_breakdown',
					'label'   => __( 'Price breakdown', 'radius-hotel-booking' ),
					'type'    => 'select',
					'default' => self::BOOKING_DEFAULTS['price_breakdown'],
					'options' => array(
						array(
							'value' => 'no',
							'label' => __( 'Hide (price only)', 'radius-hotel-booking' ),
						),
						array(
							'value' => 'yes',
							'label' => __( 'Show an info icon next to the price', 'radius-hotel-booking' ),
						),
					),
					'help'    => __( 'The icon opens how each rate\'s price is made: the stay, any discount and the total.', 'radius-hotel-booking' ),
				),
			),
		);
		$catalog['rtbp_rooms']   = array(
			'title'       => __( 'Room types', 'radius-hotel-booking' ),
			'description' => __( 'Your room types as cards with photo, guests, beds and "from" price, linking to each room page and to booking.', 'radius-hotel-booking' ),
			'also'        => array(
				__( 'Block: Hotel room types', 'radius-hotel-booking' ),
				__( 'Elementor widget: Hotel room types', 'radius-hotel-booking' ),
			),
			'order'       => 30,
			'attributes'  => array(
				array(
					'name'    => 'layout',
					'label'   => __( 'Layout', 'radius-hotel-booking' ),
					'type'    => 'select',
					'default' => self::ROOMS_DEFAULTS['layout'],
					'options' => array(
						array(
							'value' => 'grid',
							'label' => __( 'Grid', 'radius-hotel-booking' ),
						),
						array(
							'value' => 'list',
							'label' => __( 'List', 'radius-hotel-booking' ),
						),
					),
				),
				array(
					'name'    => 'columns',
					'label'   => __( 'Columns', 'radius-hotel-booking' ),
					'type'    => 'select',
					'default' => (string) self::ROOMS_DEFAULTS['columns'],
					'options' => array_map(
						static fn( $n ) => array(
							'value' => (string) $n,
							'label' => (string) $n,
						),
						array( 2, 3, 4 )
					),
					'help'    => __( 'On phones the cards stack in one column.', 'radius-hotel-booking' ),
					'when'    => array( 'layout' => 'grid' ),
				),
				array(
					'name'    => 'limit',
					'label'   => __( 'Limit', 'radius-hotel-booking' ),
					'type'    => 'number',
					'default' => (string) self::ROOMS_DEFAULTS['limit'],
					'min'     => 0,
					'max'     => 50,
					'help'    => __( 'How many room types to show. 0 shows them all.', 'radius-hotel-booking' ),
				),
				array(
					'name'    => 'show_count',
					'label'   => __( 'Number of rooms', 'radius-hotel-booking' ),
					'type'    => 'select',
					'default' => self::ROOMS_DEFAULTS['show_count'],
					'options' => array(
						array(
							'value' => 'no',
							'label' => __( 'Hide', 'radius-hotel-booking' ),
						),
						array(
							'value' => 'yes',
							'label' => __( 'Show on the photo', 'radius-hotel-booking' ),
						),
					),
				),
				array(
					'name'    => 'ids',
					'label'   => __( 'Room types', 'radius-hotel-booking' ),
					'type'    => 'multiselect',
					'default' => self::ROOMS_DEFAULTS['ids'],
					'options' => array_map(
						static fn( $row ) => array(
							'value' => (string) $row['id'],
							'label' => (string) $row['name'],
						),
						self::activeRoomTypes()
					),
					'help'    => __( 'None ticked shows every active room type.', 'radius-hotel-booking' ),
				),
			),
		);
		return $catalog;
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
				'attributes'      => array(
					'layout'     => array(
						'type'    => 'string',
						'default' => self::BOOKING_DEFAULTS['layout'],
					),
					'display'    => array(
						'type'    => 'string',
						'default' => self::BOOKING_DEFAULTS['display'],
					),
					'buttonText'  => array(
						'type'    => 'string',
						'default' => self::BOOKING_DEFAULTS['button_text'],
					),
					'roomDetails' => array(
						'type'    => 'string',
						'default' => self::BOOKING_DEFAULTS['room_details'],
					),
					'roomCard'    => array(
						'type'    => 'string',
						'default' => self::BOOKING_DEFAULTS['room_card'],
					),
					'ratesShown'  => array(
						'type'    => 'number',
						'default' => self::BOOKING_DEFAULTS['rates_shown'],
					),
					'priceBreakdown' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'render_callback' => static fn( $attributes = array() ) => self::booking(
					array(
						'layout'      => $attributes['layout'] ?? self::BOOKING_DEFAULTS['layout'],
						'display'     => $attributes['display'] ?? self::BOOKING_DEFAULTS['display'],
						'button_text'  => $attributes['buttonText'] ?? self::BOOKING_DEFAULTS['button_text'],
						'room_details' => $attributes['roomDetails'] ?? self::BOOKING_DEFAULTS['room_details'],
						'room_card'    => $attributes['roomCard'] ?? self::BOOKING_DEFAULTS['room_card'],
						'rates_shown'  => $attributes['ratesShown'] ?? self::BOOKING_DEFAULTS['rates_shown'],
						'price_breakdown' => ! empty( $attributes['priceBreakdown'] ) ? 'yes' : 'no',
					)
				),
			)
		);
		register_block_type(
			self::BLOCK_PREFIX . 'rooms',
			array(
				'api_version'     => 3,
				'title'           => __( 'Hotel room types', 'radius-hotel-booking' ),
				'category'        => 'radius-hotel-booking',
				'attributes'      => array(
					'layout'    => array(
						'type'    => 'string',
						'default' => 'grid',
					),
					'columns'   => array(
						'type'    => 'number',
						'default' => 3,
					),
					'limit'     => array(
						'type'    => 'number',
						'default' => 0,
					),
					'showCount' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'render_callback' => static fn( $attributes = array() ) => self::rooms(
					array(
						'layout'     => $attributes['layout'] ?? 'grid',
						'columns'    => $attributes['columns'] ?? 3,
						'limit'      => $attributes['limit'] ?? 0,
						'show_count' => ! empty( $attributes['showCount'] ) ? 'yes' : 'no',
					)
				),
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
			$widgets_manager->register( new \RadiusTheme\RadiusHotelBooking\Elementor\Widgets\RoomsWidget() );
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
	 * over; without JavaScript, a short message with the hotel's phone (or, in
	 * a popup, a link to the booking page).
	 *
	 * @param array $atts `layout` steps|onepage, `display` inline|modal, `button_text`, `price_breakdown` yes|no.
	 * @return string
	 */
	public static function booking( array $atts = array() ): string {
		$atts    = shortcode_atts( self::BOOKING_DEFAULTS, $atts, 'rtbp_booking' );
		$layout  = in_array( $atts['layout'], array_column( self::bookingLayouts(), 'value' ), true ) ? $atts['layout'] : self::BOOKING_DEFAULTS['layout'];
		$display = in_array( $atts['display'], array_column( self::bookingDisplays(), 'value' ), true ) ? $atts['display'] : self::BOOKING_DEFAULTS['display'];
		$text    = trim( sanitize_text_field( (string) $atts['button_text'] ) );
		$details = in_array( $atts['room_details'], array_column( self::bookingRoomDetails(), 'value' ), true ) ? $atts['room_details'] : self::BOOKING_DEFAULTS['room_details'];
		$card    = in_array( $atts['room_card'], array_column( self::bookingRoomCards(), 'value' ), true ) ? $atts['room_card'] : self::BOOKING_DEFAULTS['room_card'];
		// Left empty (an emptied field): the default, not 0 (= all).
		$first     = '' === trim( (string) $atts['rates_shown'] ) ? self::BOOKING_DEFAULTS['rates_shown'] : min( 50, absint( $atts['rates_shown'] ) );
		$breakdown = in_array( strtolower( (string) $atts['price_breakdown'] ), array( 'yes', 'true', '1' ), true );
		LoadAssets::enqueue_site_app();
		ob_start();
		rtbp_get_template(
			'embeds/booking.php',
			array(
				'phone'       => (string) rtbp_setting( 'general', 'phone', '' ),
				'atts'        => $atts,
				'layout'      => $layout,
				'display'     => $display,
				'button_text' => '' !== $text ? $text : __( 'Book now', 'radius-hotel-booking' ),
				'page_url'     => self::rules()['resultsUrl'],
				'room_details' => $details,
				'room_card'    => $card,
				'rates_shown'  => $first,
				'breakdown'    => $breakdown,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * The booking form's layouts.
	 *
	 * @return array[] `{ value, label }`.
	 */
	public static function bookingLayouts(): array {
		return array(
			array(
				'value' => 'steps',
				'label' => __( 'Steps (one step at a time)', 'radius-hotel-booking' ),
			),
			array(
				'value' => 'onepage',
				'label' => __( 'One page (everything on one page)', 'radius-hotel-booking' ),
			),
		);
	}

	/**
	 * How the booking form lays out each room type in the results.
	 *
	 * @return array[] `{ value, label }`.
	 */
	public static function bookingRoomCards(): array {
		return array(
			array(
				'value' => 'classic',
				'label' => __( 'Classic (rates in rows under the room)', 'radius-hotel-booking' ),
			),
			array(
				'value' => 'split',
				'label' => __( 'Split (room on the left, rate cards on the right)', 'radius-hotel-booking' ),
			),
		);
	}

	/**
	 * What a room type's "Room details" opens in the booking form.
	 *
	 * @return array[] `{ value, label }`.
	 */
	public static function bookingRoomDetails(): array {
		return array(
			array(
				'value' => 'page',
				'label' => __( 'Its own page', 'radius-hotel-booking' ),
			),
			array(
				'value' => 'popup',
				'label' => __( 'A popup, without leaving the form', 'radius-hotel-booking' ),
			),
		);
	}

	/**
	 * Where the booking form shows.
	 *
	 * @return array[] `{ value, label }`.
	 */
	public static function bookingDisplays(): array {
		return array(
			array(
				'value' => 'inline',
				'label' => __( 'On the page', 'radius-hotel-booking' ),
			),
			array(
				'value' => 'modal',
				'label' => __( 'In a popup, opened by a button', 'radius-hotel-booking' ),
			),
		);
	}

	/**
	 * The room type cards (`[rtbp_rooms]`, the *Hotel room types* block and
	 * widget): every active room type, in the catalogue order, with the same
	 * data as its own page (RoomTypePage::find()). Plain HTML; only the site
	 * stylesheet is needed.
	 *
	 * @param array $atts `layout` grid|list, `columns` 2–4, `ids` "1,3", `limit`, `show_count` yes|no.
	 * @return string
	 */
	public static function rooms( array $atts = array() ): string {
		$atts = shortcode_atts( self::ROOMS_DEFAULTS, $atts, 'rtbp_rooms' );

		$ids   = array_values( array_filter( array_map( 'absint', explode( ',', (string) $atts['ids'] ) ) ) );
		$limit = absint( $atts['limit'] );

		$rows = self::activeRoomTypes();

		$types = array();
		foreach ( (array) $rows as $row ) {
			if ( $ids && ! in_array( (int) $row['id'], $ids, true ) ) {
				continue;
			}
			$type = RoomTypePage::find( (string) $row['slug'] );
			if ( ! $type ) {
				continue;
			}
			$type['rooms'] = (int) $row['rooms'];
			$type['url']   = RoomTypePage::url( $type['slug'] );
			$types[]       = $type;
			if ( $limit && count( $types ) >= $limit ) {
				break;
			}
		}

		if ( wp_style_is( LoadAssets::SITE_HANDLE, 'registered' ) ) {
			wp_enqueue_style( LoadAssets::SITE_HANDLE );
		}

		ob_start();
		rtbp_get_template(
			'embeds/rooms.php',
			array(
				'types'      => $types,
				'layout'     => 'list' === $atts['layout'] ? 'list' : 'grid',
				'columns'    => min( 4, max( 2, absint( $atts['columns'] ) ) ),
				'show_count' => in_array( strtolower( (string) $atts['show_count'] ), array( 'yes', 'true', '1' ), true ),
				'atts'       => $atts,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * Active room types in the catalogue order, with their number of rooms.
	 *
	 * @return array<int,array{id:string,slug:string,name:string,rooms:string}>
	 */
	private static function activeRoomTypes(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a few rows, live data.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT t.id, t.slug, t.name, ( SELECT COUNT(*) FROM %i r WHERE r.room_type_id = t.id AND r.deleted_at IS NULL ) AS rooms
				FROM %i t WHERE t.deleted_at IS NULL AND t.is_active = 1 ORDER BY t.sort_order, t.name',
				rtbp_table( 'rooms' ),
				rtbp_table( 'room_types' )
			),
			ARRAY_A
		);
		return (array) $rows;
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
			// Guest field => required|optional|hidden (Settings → Website).
			'guestFields'       => \RadiusTheme\RadiusHotelBooking\Settings\WebsiteSettings::guestFields(),
			// Input => { label, placeholder }; '' = the built-in text.
			'guestFieldText'    => \RadiusTheme\RadiusHotelBooking\Settings\WebsiteSettings::guestFieldText(),
			// Most rooms one website booking may take (Settings → Booking).
			'maxRooms'          => \RadiusTheme\RadiusHotelBooking\Services\Booking\BookingService::maxRooms( 'public' ),
		);
	}
}
