<?php
/**
 * A page per room type on the website (M04, 4.5).
 *
 * @package RadiusTheme\RadiusHotelBooking\Frontend
 */

namespace RadiusTheme\RadiusHotelBooking\Frontend;

use RadiusTheme\RadiusHotelBooking\Assets\LoadAssets;
use RadiusTheme\RadiusHotelBooking\Repositories\AvailabilityRepository;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * `/rooms/{slug}` (the base is filterable: `rtbp_room_type_base`) — a virtual
 * page, not a post: a rewrite rule sets the `rtbp_room_type` query var and
 * `templates/booking/room-type.php` (theme-overridable at
 * `radius-hotel-booking/booking/room-type.php`) renders inside the theme's
 * header and footer. Rooms are not shop products (ADR-001), so nothing reaches
 * WooCommerce. An unknown or hidden room type answers 404.
 *
 * Shown: the gallery (featured image first), description, beds, size,
 * occupancy, amenities, each rate plan with its "from" price (the base price,
 * or the sale price when lower — pricing rules may change a given date), and
 * *Book* — the booking page with this room type chosen.
 */
class RoomTypePage {

	/**
	 * Query var.
	 */
	public const QUERY_VAR = 'rtbp_room_type';

	/**
	 * The room type of this request (null until resolved, false when none).
	 *
	 * @var array|false|null
	 */
	private static $current = null;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init(): void {
		self::rewrite();
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'template_redirect', array( self::class, 'resolve' ) );
		add_filter( 'template_include', array( self::class, 'template' ) );
		add_filter( 'document_title_parts', array( self::class, 'title' ) );
		// Not the blog: no posts query, no blog styling.
		add_action( 'parse_query', array( self::class, 'not_blog' ) );
		add_filter( 'posts_pre_query', array( self::class, 'no_posts' ), 10, 2 );
		add_filter( 'pre_handle_404', array( self::class, 'not_404_yet' ), 10, 2 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
	}

	/**
	 * `pre_handle_404`: WordPress would call the empty main query a 404 (and
	 * the theme would style it so) before `resolve()` looks the room type up;
	 * `resolve()` decides instead.
	 *
	 * @param bool      $handled Whether something else handled it.
	 * @param \WP_Query $query   Main query.
	 * @return bool
	 */
	public static function not_404_yet( $handled, $query ) {
		if ( $query instanceof \WP_Query && '' !== (string) $query->get( self::QUERY_VAR ) ) {
			return true;
		}
		return $handled;
	}

	/**
	 * `parse_query`: a room type request is not the blog home.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public static function not_blog( $query ): void {
		if ( $query instanceof \WP_Query && $query->is_main_query() && '' !== (string) $query->get( self::QUERY_VAR ) ) {
			$query->is_home    = false;
			$query->is_archive = false;
		}
	}

	/**
	 * `posts_pre_query`: skip the main posts query on a room type page.
	 *
	 * @param array|null $posts Posts, or null to run the query.
	 * @param \WP_Query  $query Query.
	 * @return array|null
	 */
	public static function no_posts( $posts, $query ) {
		if ( $query instanceof \WP_Query && $query->is_main_query() && '' !== (string) $query->get( self::QUERY_VAR ) ) {
			return array();
		}
		return $posts;
	}

	/**
	 * `body_class`: `rtbp-room-type-page` instead of `blog`.
	 *
	 * @param array $classes Classes.
	 * @return array
	 */
	public static function body_class( $classes ): array {
		$classes = (array) $classes;
		if ( '' === (string) get_query_var( self::QUERY_VAR ) ) {
			return $classes;
		}
		$classes   = array_values( array_diff( $classes, array( 'blog', 'home', 'archive' ) ) );
		$classes[] = 'rtbp-room-type-page';
		return $classes;
	}

	/**
	 * The URL base (`rooms`).
	 *
	 * @return string
	 */
	public static function base(): string {
		/**
		 * Filters the room type pages' URL base (`/rooms/{slug}`).
		 *
		 * @param string $base Base, without slashes.
		 */
		$base = trim( (string) apply_filters( 'rtbp_room_type_base', 'rooms' ), '/' );
		return '' !== $base ? $base : 'rooms';
	}

	/**
	 * A room type's page URL.
	 *
	 * @param string $slug Room type slug.
	 * @return string
	 */
	public static function url( string $slug ): string {
		return home_url( user_trailingslashit( self::base() . '/' . rawurlencode( $slug ) ) );
	}

	/**
	 * Add the rule, and make sure the saved rules carry it.
	 *
	 * Any flush made while the rule was not registered (activation, a database
	 * upgrade, another plugin) drops it; the saved rules are then cleared, and
	 * WordPress rebuilds them on demand later in the request — once every
	 * plugin has added its own rules (M04 critical review: a flush here at
	 * `init` lost later plugins' rules).
	 *
	 * @return void
	 */
	private static function rewrite(): void {
		$regex = '^' . preg_quote( self::base(), '#' ) . '/([^/]+)/?$';
		add_rewrite_rule( $regex, 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
		$saved = get_option( 'rewrite_rules' );
		if ( is_array( $saved ) && ! isset( $saved[ $regex ] ) ) {
			delete_option( 'rewrite_rules' );
		}
	}

	/**
	 * `query_vars`: the room type slug.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function query_vars( $vars ): array {
		$vars   = (array) $vars;
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * `template_redirect`: find the room type, or answer 404.
	 *
	 * @return void
	 */
	public static function resolve(): void {
		$slug = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $slug ) {
			return;
		}
		self::$current = self::find( sanitize_title( $slug ) );
		if ( ! self::$current ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}
		status_header( 200 );
		// The plugin's scoped styles (the page has no app to mount).
		wp_enqueue_style( LoadAssets::SITE_HANDLE );
	}

	/**
	 * `template_include`: our template for a room type page.
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public static function template( $template ) {
		if ( ! self::$current ) {
			return $template;
		}
		$ours = rtbp_locate_template( 'booking/room-type.php' );
		return $ours ? $ours : $template;
	}

	/**
	 * `document_title_parts`: the room type's name.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public static function title( $parts ): array {
		$parts = (array) $parts;
		if ( self::$current ) {
			$parts['title'] = self::$current['name'];
		}
		return $parts;
	}

	/**
	 * The data the template receives.
	 *
	 * @return array|null
	 */
	public static function current(): ?array {
		return self::$current ? self::$current : null;
	}

	/**
	 * A live, active room type by slug, with everything the page shows.
	 *
	 * @param string $slug Slug.
	 * @return array|false
	 */
	public static function find( string $slug ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one row by its unique slug.
		$type = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE slug = %s AND deleted_at IS NULL AND is_active = 1', rtbp_table( 'room_types' ), $slug ), ARRAY_A );
		if ( ! $type ) {
			return false;
		}

		// Featured image first, then the gallery, each once.
		$images = array();
		foreach ( array_merge( array( (int) $type['featured_image_id'] ), array_map( 'intval', self::listOf( $type['gallery'] ) ) ) as $id ) {
			if ( $id > 0 && ! in_array( $id, $images, true ) && wp_attachment_is_image( $id ) ) {
				$images[] = $id;
			}
		}

		// The rates sold for this room type, cheapest "from" price first.
		$rates = array();
		foreach ( ( new AvailabilityRepository() )->catalogue( (int) $type['id'] ) as $row ) {
			if ( empty( $row['rate_id'] ) || ! (int) $row['enabled'] || ! (int) $row['plan_active'] ) {
				continue;
			}
			$price = (float) $row['price'];
			if ( null !== $row['sale_price'] && '' !== (string) $row['sale_price'] && (float) $row['sale_price'] < $price ) {
				$price = (float) $row['sale_price'];
			}
			$rates[] = array(
				'name'  => (string) $row['plan_name'],
				'when'  => self::window( $row ),
				'from'  => Money::format( $price ),
				'price' => $price,
			);
		}
		usort( $rates, static fn( $a, $b ) => $a['price'] <=> $b['price'] );

		$results = (string) ( Embeds::rules()['resultsUrl'] ?? '' );
		return array(
			'id'                => (int) $type['id'],
			'name'              => (string) $type['name'],
			'slug'              => (string) $type['slug'],
			'short_description' => (string) $type['short_description'],
			'description'       => (string) $type['description'],
			'images'            => $images,
			'amenities'         => array_values( array_filter( array_map( 'strval', self::listOf( $type['amenities'] ) ) ) ),
			'bed_info'          => (string) $type['bed_info'],
			'size_m2'           => null !== $type['size_m2'] ? (float) $type['size_m2'] : null,
			'max_adults'        => (int) $type['max_adults'],
			'max_children'      => (int) $type['max_children'],
			'rates'             => $rates,
			// The booking page with this room type chosen ('' when no booking page is set).
			'book_url'          => '' !== $results ? add_query_arg( 'room_type', (int) $type['id'], $results ) : '',
		);
	}

	/**
	 * A stored list — PHP-serialised (the ORM's json cast) or JSON.
	 *
	 * @param mixed $value Stored value.
	 * @return array
	 */
	private static function listOf( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		$value = maybe_unserialize( (string) $value );
		if ( is_array( $value ) ) {
			return $value;
		}
		$json = json_decode( (string) $value, true );
		return is_array( $json ) ? $json : array();
	}

	/**
	 * The stay a rate plan sells, in words.
	 *
	 * @param array $row Catalogue row.
	 * @return string
	 */
	private static function window( array $row ): string {
		$format = (string) get_option( 'time_format', 'H:i' );
		$time   = static fn( $hm ) => $hm ? wp_date( $format, strtotime( '2000-01-01 ' . $hm . ' UTC' ), new \DateTimeZone( 'UTC' ) ) : '';
		if ( 'flexible' === (string) $row['plan_type'] ) {
			$hours = max( 1, (int) round( (int) $row['duration_minutes'] / 60 ) );
			/* translators: %d: number of hours. */
			return sprintf( _n( '%d hour, from the time you choose', '%d hours, from the time you choose', $hours, 'radius-hotel-booking' ), $hours );
		}
		/* translators: 1: start time, 2: end time. */
		return sprintf( __( '%1$s – %2$s', 'radius-hotel-booking' ), $time( (string) $row['start_time'] ), $time( (string) $row['end_time'] ) );
	}
}
