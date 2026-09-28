<?php
/**
 * Front-end staff dashboard page.
 *
 * @package RadiusTheme\RadiusHotelBooking\Frontend
 */

namespace RadiusTheme\RadiusHotelBooking\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Helpers\ThemeHelper;

/**
 * `[rtbp_dashboard]`: the staff app on a front-end page (ADR-003), so a hotel
 * can keep its dashboard at an address like `/hotel-dashboard`.
 *
 * A page containing the shortcode is served with the plugin's own bare
 * template (templates/dashboard/app-page.php, theme-overridable) instead of
 * the theme's layout: the dashboard is a full-screen app, and the admin
 * stylesheet's CSS reset must not meet the theme's markup.
 *
 * What the visitor sees:
 * - logged out → a login form that returns to the page;
 * - logged in without `rtbp_view_dashboard` → a "no access" message;
 * - staff → the same React app as wp-admin (LoadAssets::enqueue_staff_app()).
 */
class DashboardPage {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'rtbp_dashboard';

	/**
	 * Style handle for the login and no-access screens.
	 */
	const STYLE = 'radius-hotel-booking-dashboard-page';

	/**
	 * Hook in. Called from the plugin bootstrap.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'rtbp_shortcodes', array( __CLASS__, 'register_shortcode' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_page_style' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );

		// Staff get their profile language here, as they do in wp-admin
		// (early, before anything on the page is translated).
		add_action( 'template_redirect', array( __CLASS__, 'use_staff_locale' ), 0 );

		// The page is an app, not a themed page: drop other plugins' and the
		// theme's scripts and styles (they only slow it down and can clash),
		// both when enqueued normally and when enqueued late for the footer.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'isolate_assets' ), PHP_INT_MAX );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'isolate_assets' ), 0 );
	}

	/**
	 * Switch the request to the signed-in user's profile language.
	 *
	 * WordPress does this in wp-admin only; on the front end it uses the site
	 * language. The app's REST calls send `_locale=user`, so their messages
	 * follow the same language.
	 *
	 * @return void
	 */
	public static function use_staff_locale(): void {
		if ( ! is_user_logged_in() || ! self::is_dashboard_request() ) {
			return;
		}

		$locale = get_user_locale();
		if ( ! $locale || determine_locale() === $locale || switch_to_locale( $locale ) ) {
			return;
		}

		// WordPress only switches to a language whose core files are
		// installed. The plugin ships its own translations, so its screens
		// still follow the user: the plugin's catalog and the app's locale
		// data (both read determine_locale()) are reloaded in that language.
		add_filter(
			'determine_locale',
			static function () use ( $locale ) {
				return $locale;
			}
		);
		unload_textdomain( 'radius-hotel-booking', true );
		radius_hotel_booking()->load_textdomain();
	}

	/**
	 * On the dashboard page, dequeue every script and style that is neither
	 * WordPress core nor one of this plugin family's (free, Pro, client
	 * add-on — all live in `plugins/radius-hotel-booking*`).
	 *
	 * @return void
	 */
	public static function isolate_assets(): void {
		if ( ! self::is_dashboard_request() ) {
			return;
		}

		foreach ( array(
			'script' => wp_scripts(),
			'style' => wp_styles(),
		) as $type => $registry ) {
			foreach ( (array) $registry->queue as $handle ) {
				$src  = isset( $registry->registered[ $handle ] ) ? (string) $registry->registered[ $handle ]->src : '';
				$keep = self::is_own_or_core_asset( $handle, $src );

				/**
				 * Filters whether an asset is kept on the dashboard page.
				 *
				 * @param bool   $keep   Keep it.
				 * @param string $handle Handle.
				 * @param string $src    Source URL (may be relative or empty).
				 * @param string $type   'script' or 'style'.
				 */
				if ( ! apply_filters( 'rtbp_dashboard_page_keep_asset', $keep, $handle, $src, $type ) ) {
					'script' === $type ? wp_dequeue_script( $handle ) : wp_dequeue_style( $handle );
				}
			}
		}
	}

	/**
	 * Whether an asset is WordPress core or from this plugin family.
	 *
	 * @param string $handle Handle.
	 * @param string $src    Source.
	 * @return bool
	 */
	private static function is_own_or_core_asset( string $handle, string $src ): bool {
		if ( 0 === strpos( $handle, 'radius-hotel-booking' ) || 0 === strpos( $handle, 'rtbp' ) ) {
			return true;
		}

		// Handles without a file (inline-only groups) and core files.
		if ( '' === $src || 0 === strpos( $src, '/wp-includes/' ) || 0 === strpos( $src, '/wp-admin/' ) ) {
			return true;
		}

		$path = (string) wp_parse_url( $src, PHP_URL_PATH );
		if ( false !== strpos( $path, '/wp-includes/' ) || false !== strpos( $path, '/wp-admin/' ) ) {
			return true;
		}

		return false !== strpos( $path, '/plugins/radius-hotel-booking' );
	}

	/**
	 * The page footer: only what the dashboard needs (admin bar, media
	 * library templates, enqueued scripts). Replaces wp_footer() in the app
	 * template so other plugins cannot inject markup (a shop cart, a chat
	 * bubble) into the front desk.
	 *
	 * @return void
	 */
	public static function footer(): void {
		if ( did_action( 'wp_enqueue_media' ) && function_exists( 'wp_print_media_templates' ) ) {
			wp_print_media_templates();
		}

		if ( is_admin_bar_showing() ) {
			wp_admin_bar_render();
		}

		wp_print_footer_scripts();

		/**
		 * Fires at the end of the dashboard page, for add-ons that need to
		 * print something there (instead of the theme-wide wp_footer).
		 */
		do_action( 'rtbp_dashboard_footer' );
	}

	/**
	 * Add the shortcode to the plugin's list.
	 *
	 * @param array $shortcodes Tag => callback.
	 * @return array
	 */
	public static function register_shortcode( $shortcodes ): array {
		$shortcodes                    = (array) $shortcodes;
		$shortcodes[ self::SHORTCODE ] = array( __CLASS__, 'shortcode' );
		return $shortcodes;
	}

	/**
	 * Whether the current request is a page carrying the shortcode.
	 *
	 * @return bool
	 */
	public static function is_dashboard_request(): bool {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}
		$post = get_post();
		return $post && has_shortcode( (string) $post->post_content, self::SHORTCODE );
	}

	/**
	 * Whether the current user may use the dashboard.
	 *
	 * @return bool
	 */
	public static function user_can_view(): bool {
		return is_user_logged_in() && Capabilities::userCan( Capabilities::VIEW_DASHBOARD );
	}

	/**
	 * Swap the theme template for the bare app page.
	 *
	 * @param string $template Template WordPress picked.
	 * @return string
	 */
	public static function template( $template ): string {
		if ( ! self::is_dashboard_request() ) {
			return (string) $template;
		}

		/**
		 * Filters whether the dashboard page uses the plugin's full-screen
		 * template. Return false to render it inside the theme instead (only
		 * safe once the CSS reset is scoped — see M00 progress notes).
		 *
		 * @param bool $fullscreen Use the plugin template.
		 */
		if ( ! apply_filters( 'rtbp_dashboard_fullscreen', true ) ) {
			return (string) $template;
		}

		$ours = rtbp_locate_template( 'dashboard/app-page.php' );
		return $ours ? $ours : (string) $template;
	}

	/**
	 * The shortcode output.
	 *
	 * @return string
	 */
	public static function shortcode(): string {
		if ( ! is_user_logged_in() ) {
			return self::render_template( 'dashboard/login.php' );
		}

		if ( ! self::user_can_view() ) {
			return self::render_template( 'dashboard/no-access.php' );
		}

		// LoadAssets enqueues the app in wp_enqueue_scripts for this page; the
		// mount node is the same one wp-admin uses (views/app.php).
		return '<div class="rtbp-root radius-hotel-booking-admin-page radius-hotel-booking-frontend" id="radius-hotel-booking"></div>';
	}

	/**
	 * Styles for the login and no-access screens (plain CSS, no Tailwind, in
	 * the brand colour).
	 *
	 * @return void
	 */
	public static function enqueue_page_style(): void {
		if ( ! self::is_dashboard_request() || self::user_can_view() ) {
			return;
		}

		$primary = ThemeHelper::primary_color();
		$css     = '
			body.rtbp-dashboard-page{margin:0;background:#edf0f3;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;color:#1f2937}
			.rtbp-gate{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;box-sizing:border-box}
			.rtbp-gate__card{width:100%;max-width:400px;background:#fff;border:1px solid #dbe1e6;border-radius:14px;padding:32px 28px;box-shadow:0 1px 2px rgba(16,24,40,.05);box-sizing:border-box}
			.rtbp-gate__brand{display:flex;align-items:center;gap:12px;margin:0 0 24px}
			.rtbp-gate__logo{width:40px;height:40px;border-radius:10px;background:' . $primary . ';color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px}
			.rtbp-gate__site{margin:0;font-size:17px;font-weight:700;color:#00134d;line-height:1.2}
			.rtbp-gate__tag{margin:2px 0 0;font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#5b6b7f}
			.rtbp-gate h1{margin:0 0 6px;font-size:20px;line-height:1.3;color:#00134d}
			.rtbp-gate p.rtbp-gate__lead{margin:0 0 20px;font-size:14px;color:#5b6b7f}
			.rtbp-gate label{display:block;font-size:13px;font-weight:600;color:#00134d;margin:0 0 6px}
			.rtbp-gate input[type=text],.rtbp-gate input[type=password]{width:100%;height:42px;padding:0 12px;border:1px solid #dbe1e6;border-radius:8px;font-size:15px;box-sizing:border-box;background:#fff}
			.rtbp-gate input:focus{outline:2px solid ' . $primary . ';outline-offset:1px;border-color:' . $primary . '}
			.rtbp-gate form p{margin:0 0 16px}
			.rtbp-gate .login-remember label{display:flex;align-items:center;gap:8px;font-weight:500}
			.rtbp-gate input[type=submit],.rtbp-gate .rtbp-gate__button{display:inline-flex;align-items:center;justify-content:center;width:100%;height:44px;border:0;border-radius:8px;background:' . $primary . ';color:#fff;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none}
			.rtbp-gate input[type=submit]:hover,.rtbp-gate .rtbp-gate__button:hover{filter:brightness(.92)}
			.rtbp-gate__links{margin:16px 0 0;text-align:center;font-size:13px}
			.rtbp-gate__links a{color:' . $primary . ';text-decoration:none}
		';

		wp_register_style( self::STYLE, false, array(), RADIUS_HOTEL_BOOKING_VERSION );
		wp_enqueue_style( self::STYLE );
		wp_add_inline_style( self::STYLE, (string) preg_replace( '/\s+/', ' ', $css ) );
	}

	/**
	 * Body class for styling and for the app shell.
	 *
	 * @param array $classes Classes.
	 * @return array
	 */
	public static function body_class( $classes ): array {
		$classes = (array) $classes;
		if ( self::is_dashboard_request() ) {
			$classes[] = 'rtbp-dashboard-page';
		}
		return $classes;
	}

	/**
	 * Render a template to a string.
	 *
	 * @param string $name Template path relative to templates/.
	 * @return string
	 */
	private static function render_template( string $name ): string {
		ob_start();
		rtbp_get_template( $name, array( 'page_url' => get_permalink() ) );
		return (string) ob_get_clean();
	}
}
