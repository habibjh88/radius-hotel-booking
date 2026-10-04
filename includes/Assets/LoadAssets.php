<?php
/**
 * Asset loading.
 *
 * Enqueues the webpack bundles produced by `@wordpress/scripts` and wires up
 * script translations.
 *
 * Each entry in webpack.config.js emits `build/<entry>.js`, optionally
 * `build/<entry>.css`, and `build/<entry>.asset.php` — a generated file
 * returning `array( 'dependencies' => [...], 'version' => '<content hash>' )`.
 * Reading it means WordPress core script dependencies (wp-element, wp-i18n, …)
 * are declared automatically and cache-busting follows the real file contents,
 * so there is no manifest to parse and no dev-server/production switch: `npm
 * run start` writes the same files `npm run build` does.
 *
 * @package RadiusTheme\RadiusHotelBooking\Assets
 */

namespace RadiusTheme\RadiusHotelBooking\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Admin\SiteChecks;
use RadiusTheme\RadiusHotelBooking\Admin\UpgradeNotice;
use RadiusTheme\RadiusHotelBooking\Controllers\AccessController;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Frontend\DashboardPage;
use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;
use RadiusTheme\RadiusHotelBooking\Helpers\ThemeHelper;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

/**
 * Load assets class.
 *
 * Responsible for every CSS/JS/locale asset the plugin enqueues.
 */
class LoadAssets {

	/**
	 * Text domain used for every script translation.
	 *
	 * @var string
	 */
	private const DOMAIN = 'radius-hotel-booking';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_dashboard' ), 20 );

		// Register the public bundle early and enqueue it late. Registering
		// unconditionally means anything rendering after wp_enqueue_scripts —
		// an Elementor widget, a page-builder module, a theme template — can
		// still pull the app in with LoadAssets::enqueue_site_app().
		add_action( 'wp_enqueue_scripts', array( $this, 'register_site_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_site_assets' ), 999 );

		// Resolve script translations by merging every
		// `radius-hotel-booking-{locale}-*.json` found in any of the three folders
		// Loco Translate can save to, so the per-script src-hash in the filename
		// stops mattering. See load_merged_script_translations().
		add_filter( 'pre_load_script_translations', array( $this, 'load_merged_script_translations' ), 10, 4 );

		// Resolve the PHP-side gettext `.mo` the same way: check all three Loco
		// save locations and prefer whichever file is newest.
		add_filter( 'load_textdomain_mofile', array( $this, 'resolve_newest_mofile' ), 10, 2 );
	}

	/**
	 * Read a webpack entry's generated `*.asset.php` file.
	 *
	 * @param string $entry Entry name, e.g. 'admin'.
	 *
	 * @return array{dependencies:string[],version:string}
	 */
	private function asset_meta( string $entry ): array {
		$file = RADIUS_HOTEL_BOOKING_DIR . 'build/' . $entry . '.asset.php';

		$meta = file_exists( $file ) ? include $file : array();

		return array(
			'dependencies' => isset( $meta['dependencies'] ) ? (array) $meta['dependencies'] : array(),
			'version'      => $meta['version'] ?? RADIUS_HOTEL_BOOKING_VERSION,
		);
	}

	/**
	 * Register a webpack entry's script, its stylesheet (when the entry emitted
	 * one) and its translations. Registering does not print anything — call
	 * wp_enqueue_script()/wp_enqueue_style() with the same handle for that.
	 *
	 * @param string   $entry              Entry name, matching webpack.config.js.
	 * @param string   $handle             Script handle to register under.
	 * @param string[] $extra_dependencies Dependencies to add to the generated list.
	 *
	 * @return bool True when the bundle existed and was registered.
	 */
	private function register_entry( string $entry, string $handle, array $extra_dependencies = array() ): bool {
		$script_path = RADIUS_HOTEL_BOOKING_DIR . 'build/' . $entry . '.js';

		if ( ! file_exists( $script_path ) ) {
			// Nothing built yet — run `npm run build` (or `npm run start`).
			return false;
		}

		$meta = $this->asset_meta( $entry );

		wp_register_script(
			$handle,
			RADIUS_HOTEL_BOOKING_BUILD . '/' . $entry . '.js',
			array_values( array_unique( array_merge( $meta['dependencies'], $extra_dependencies ) ) ),
			$meta['version'],
			true
		);

		if ( file_exists( RADIUS_HOTEL_BOOKING_DIR . 'build/' . $entry . '.css' ) ) {
			wp_register_style(
				$handle,
				RADIUS_HOTEL_BOOKING_BUILD . '/' . $entry . '.css',
				array(),
				$meta['version']
			);
		}

		$this->register_script_translations( $handle );

		return true;
	}

	/**
	 * Register an entry and enqueue it immediately.
	 *
	 * @param string   $entry              Entry name.
	 * @param string   $handle             Script handle.
	 * @param string[] $extra_dependencies Extra script dependencies.
	 *
	 * @return bool True when the bundle existed and was enqueued.
	 */
	private function enqueue_entry( string $entry, string $handle, array $extra_dependencies = array() ): bool {
		if ( ! $this->register_entry( $entry, $handle, $extra_dependencies ) ) {
			return false;
		}

		wp_enqueue_script( $handle );

		if ( wp_style_is( $handle, 'registered' ) ) {
			wp_enqueue_style( $handle );
		}

		return true;
	}

	/**
	 * Register JS translations for an enqueued script handle.
	 *
	 * Centralised so every entry uses the same domain and languages path and no
	 * bundle is ever enqueued without translations wired up.
	 *
	 * @param string $handle Registered script handle.
	 *
	 * @return void
	 */
	private function register_script_translations( $handle ) {
		wp_set_script_translations(
			$handle,
			self::DOMAIN,
			RADIUS_HOTEL_BOOKING_DIR . 'languages'
		);
	}

	/**
	 * Attach a data object to a registered script.
	 *
	 * Uses wp_add_inline_script() with wp_json_encode() rather than
	 * wp_localize_script(), because localize casts every scalar to a string —
	 * `false` reaches JS as `''` and `3` as `'3'`, which quietly breaks strict
	 * comparisons and arithmetic in the app. JSON preserves the real types.
	 *
	 * @param string $handle   Registered script handle.
	 * @param string $variable Global JS variable name to define.
	 * @param array  $data     Data to expose.
	 *
	 * @return void
	 */
	private function localize( string $handle, string $variable, array $data ): void {
		wp_add_inline_script(
			$handle,
			sprintf( 'var %s = %s;', $variable, wp_json_encode( $data ) ),
			'before'
		);
	}

	/**
	 * Enqueue the admin app on the plugin's own screens only.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! is_admin() || RADIUS_HOTEL_BOOKING_SLUG !== $page ) {
			return;
		}

		$this->enqueue_staff_app();
	}

	/**
	 * Enqueue the staff app on the front-end dashboard page ([rtbp_dashboard])
	 * for users allowed to use it.
	 *
	 * @return void
	 */
	public function enqueue_frontend_dashboard() {
		if ( DashboardPage::is_dashboard_request() && DashboardPage::user_can_view() ) {
			$this->enqueue_staff_app();
		}
	}

	/**
	 * Enqueue the staff app (the `admin` bundle) with its styles, brand
	 * colour, localized data and add-on hook. Shared by the wp-admin page and
	 * the front-end dashboard page, so both run exactly the same app.
	 *
	 * @return void
	 */
	public function enqueue_staff_app() {
		if ( wp_script_is( 'radius-hotel-booking-admin', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_media();

		if ( ! $this->enqueue_entry( 'admin', 'radius-hotel-booking-admin', array( 'wp-element', 'wp-i18n', 'wp-api-fetch' ) ) ) {
			return;
		}

		wp_enqueue_style( 'wp-components' );

		// The Settings → Display primary colour, on first paint.
		ThemeHelper::attach_to( 'radius-hotel-booking-admin' );

		$this->localize( 'radius-hotel-booking-admin', 'radius_hotel_booking_param', $this->admin_params() );

		/**
		 * Fires after the admin app is enqueued, so an add-on can enqueue its own
		 * bundle with `radius-hotel-booking-admin` as a dependency. The add-on
		 * then extends the app through `window.rtbp` and the `rtbp.*` JS filters.
		 *
		 * @param string $handle The admin app's script handle.
		 */
		do_action( 'rtbp_admin_enqueue_scripts', 'radius-hotel-booking-admin' );
	}

	/**
	 * Handle of the public site bundle.
	 *
	 * @var string
	 */
	public const SITE_HANDLE = 'radius-hotel-booking-site';

	/**
	 * Register the public bundle and its localized data on every front-end
	 * request. Nothing is printed until something enqueues the handle.
	 *
	 * @return void
	 */
	public function register_site_assets() {
		if ( ! $this->register_entry( 'site', self::SITE_HANDLE, array( 'wp-element', 'wp-i18n', 'wp-api-fetch' ) ) ) {
			return;
		}

		ThemeHelper::attach_to( self::SITE_HANDLE );

		$this->localize( self::SITE_HANDLE, 'radius_hotel_booking_site_param', $this->site_params() );
	}

	/**
	 * Enqueue the public site app where the shortcode or block is present, so
	 * the bundle never ships on unrelated pages.
	 *
	 * @return void
	 */
	public function enqueue_site_assets() {
		if ( ! $this->needs_site_bundle() ) {
			return;
		}

		self::enqueue_site_app();
	}

	/**
	 * Enqueue the public app from anywhere — a widget, a page-builder module or
	 * a theme template that renders the mount node outside the post content.
	 *
	 * Safe to call after wp_enqueue_scripts: the handle is already registered,
	 * so WordPress prints it in the footer.
	 *
	 * @return void
	 */
	public static function enqueue_site_app(): void {
		if ( ! wp_script_is( self::SITE_HANDLE, 'registered' ) ) {
			return;
		}

		wp_enqueue_script( self::SITE_HANDLE );

		if ( wp_style_is( self::SITE_HANDLE, 'registered' ) ) {
			wp_enqueue_style( self::SITE_HANDLE );
		}
	}

	/**
	 * Whether the current request renders the public app.
	 *
	 * @return bool
	 */
	private function needs_site_bundle(): bool {
		if ( is_admin() ) {
			return false;
		}

		// Only the page being viewed: on a list (search results, an archive) the first
		// post's content is not this page — a booking page found by search must not load
		// the app there (M04). An embed that does render enqueues the bundle itself.
		$post    = is_singular() ? get_queried_object() : null;
		$content = $post instanceof \WP_Post ? (string) $post->post_content : '';
		$needed  = false !== strpos( $content, '<!-- wp:radius-hotel-booking/' );

		/** This filter is documented in includes/Shortcodes/Shortcodes.php */
		foreach ( array_keys( (array) apply_filters( 'rtbp_shortcodes', array() ) ) as $tag ) {
			if ( $needed ) {
				break;
			}
			$needed = has_shortcode( $content, (string) $tag );
		}

		/**
		 * Filter whether to enqueue the public bundle on this request.
		 *
		 * Widgets, page builders and templates that render the app outside the
		 * post content should return true here.
		 *
		 * @param bool $needed Whether the bundle is needed.
		 */
		return (bool) apply_filters( 'rtbp_enqueue_site_assets', $needed );
	}

	/**
	 * Data localized to the admin app as `window.radius_hotel_booking_param`.
	 *
	 * @return array
	 */
	private function admin_params(): array {
		return apply_filters(
			'rtbp_admin_localize_params',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'rest_url'      => esc_url_raw( rest_url( 'radius-hotel-booking/v1/' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'plugin_url'    => RADIUS_HOTEL_BOOKING_URL,
				'assets_url'    => RADIUS_HOTEL_BOOKING_ASSETS,
				'admin_url'     => admin_url(),
				'version'       => RADIUS_HOTEL_BOOKING_VERSION,
				'is_addon'      => rtbp_addon_active(),
				'capabilities'  => Capabilities::forCurrentUser(),
				// The M13 access map (key => open | passcode | locked); `access/me` refreshes it.
				'access'        => AccessController::payload(),
				'current_user'  => array(
					'id'         => get_current_user_id(),
					'name'       => wp_get_current_user()->display_name,
					'email'      => wp_get_current_user()->user_email,
					// The account menu's *Sign out* (the app adds where to come back to);
					// decoded, wp_logout_url() escapes its `&` for HTML.
					'logout_url' => wp_specialchars_decode( wp_logout_url() ),
				),
				'settings'      => array(
					'general'       => SettingsHelper::get_setting( 'general' ),
					'display'       => SettingsHelper::get_setting( 'display' ),
					// The new-booking alert (poll interval, sound).
					'notifications' => SettingsHelper::get_setting( 'notifications' ),
				),
				'notify_sound_url' => $this->notification_sound_url(),
				// *Paid now* at the desk picks one of these (M05); the instructions stay on the server.
				'payment_methods'  => array_map(
					static fn( $method ) => array(
						'key'   => (string) $method['key'],
						'label' => (string) $method['label'],
					),
					\RadiusTheme\RadiusHotelBooking\Settings\PaymentSettings::enabled_methods()
				),
				// Settings → Public booking (M04): the booking page to choose, and whether a privacy page exists.
				'site_pages'       => \RadiusTheme\RadiusHotelBooking\Settings\WebsiteSettings::pages(),
				'privacy_url'      => (string) get_privacy_policy_url(),
				// Reports → Export offers these (M10; Pro adds XLSX through `rtbp_export_formats`).
				'export_formats'   => \RadiusTheme\RadiusHotelBooking\Services\Export\ExportWriter::choices(),
				// Settings → E-mail lists these with an on/off switch each.
				'email_templates'  => $this->email_templates(),
				// The one dismissible upsell, on Settings only (ADR-016).
				'upgrade'          => UpgradeNotice::params(),
				// Site configuration warnings, administrators only (M18: time zone; add-ons add theirs).
				'site_checks'      => SiteChecks::params(),
				'timezone'      => wp_timezone_string(),
				'format'        => $this->format_params(),
				// The developer UI kit route (#/dev/ui): WP_DEBUG sites, admins only.
				'dev_ui'        => defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ),
				'logo_url'      => RADIUS_HOTEL_BOOKING_ASSETS . '/images/logo.svg',
				'date_format'   => get_option( 'date_format' ),
				'time_format'   => get_option( 'time_format' ),
				'start_of_week' => (int) get_option( 'start_of_week' ),
			)
		);
	}

	/**
	 * URL of the chosen notification sound; '' = the built-in chime (played
	 * by src/lib/sound.js, no file).
	 *
	 * @return string
	 */
	private function notification_sound_url(): string {
		$id = (int) rtbp_setting( 'notifications', 'soundId', 0 );
		return $id ? (string) wp_get_attachment_url( $id ) : '';
	}

	/**
	 * The registered e-mail templates, for Settings → E-mail.
	 *
	 * @return array<int, array{id:string,title:string,description:string,recipient:string,default:bool}>
	 */
	private function email_templates(): array {
		$plugin  = function_exists( 'radius_hotel_booking' ) ? radius_hotel_booking() : null;
		$manager = $plugin && isset( $plugin->emails ) ? $plugin->emails : null;
		if ( ! $manager || ! method_exists( $manager, 'get_emails' ) ) {
			return array();
		}

		$list = array();
		foreach ( $manager->get_emails() as $email ) {
			$list[] = array(
				'id'          => (string) $email->get_id(),
				'title'       => (string) $email->get_title(),
				'description' => (string) $email->get_description(),
				'recipient'   => (string) $email->recipient_type,
				'default'     => $email->is_enabled_by_default(),
			);
		}
		return $list;
	}

	/**
	 * Formatting configuration for src/lib/format.js — the same settings
	 * Support\Money and Support\Dates use, so both sides format alike.
	 *
	 * @return array
	 */
	private function format_params(): array {
		$formats = Dates::display_formats();

		return array(
			'currency'   => Money::config(),
			'dateFormat' => $formats['date'],
			'timeFormat' => $formats['time'],
			'timezone'   => wp_timezone_string(),
			'locale'     => str_replace( '_', '-', determine_locale() ),
		);
	}

	/**
	 * Data localized to the public app as `window.radius_hotel_booking_site_param`.
	 *
	 * Deliberately smaller than the admin payload: nothing here is privileged.
	 *
	 * @return array
	 */
	private function site_params(): array {
		return apply_filters(
			'rtbp_site_localize_params',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'rest_url'    => esc_url_raw( rest_url( 'radius-hotel-booking/v1/' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'assets_url'  => RADIUS_HOTEL_BOOKING_ASSETS,
				'is_addon'    => rtbp_addon_active(),
				'is_loggedin' => is_user_logged_in(),
				'format'      => $this->format_params(),
				'settings'    => array(
					'display' => SettingsHelper::get_setting( 'display' ),
				),
				// The public booking rules the search bar and date pickers follow (M04); nothing private.
				'booking'     => \RadiusTheme\RadiusHotelBooking\Frontend\Embeds::rules(),
			)
		);
	}

	/**
	 * Merge all JSON translation files for the current locale into one
	 * locale_data block and return it as the script translations payload.
	 *
	 * Without this, WordPress only loads the single file whose name hashes to
	 * the enqueued bundle URL — which Loco / make-json can't reliably produce
	 * for webpack-built bundles because they shard JSON by source file.
	 *
	 * @param string|null $translations Translations JSON (null until provided).
	 * @param string|false $file        The path WP was going to load.
	 * @param string      $handle       Script handle.
	 * @param string      $domain       Text domain.
	 *
	 * @return string|null JSON string to use as locale data, or null to defer.
	 */
	public function load_merged_script_translations( $translations, $file, $handle, $domain ) {
		if ( 'radius-hotel-booking' !== $domain ) {
			return $translations;
		}

		static $cache = array();
		// determine_locale() returns the admin user's profile language on
		// admin requests. If the user hasn't picked a language in their
		// profile, it falls through to the site locale. We still also try
		// get_locale() as a fallback so a site-language change works without
		// needing to also change the user profile.
		$locales = array_unique( array_filter( array( determine_locale(), get_locale() ) ) );
		$cache_key = implode( '|', $locales );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$messages = array(
			'' => array(
				'domain' => 'messages',
				'lang'   => reset( $locales ),
			),
		);

		// 1) Seed the JS locale data from the compiled PHP catalog
		// (.l10n.php / .mo). This is the key fallback for third-party sites:
		// a translator (Loco Translate, a WordPress.org language pack, or a
		// hand-dropped .mo) reliably produces .po/.mo/.l10n.php, but the
		// per-script JSON files the React bundles rely on are often never
		// generated. Without this seed, every JS string silently falls back
		// to English until someone runs `wp i18n make-json`. Sourcing the JS
		// strings from the same catalog PHP uses means translations "just
		// work" from a .mo alone — no JSON required.
		$this->merge_php_catalog_messages( $messages, $locales );

		// 2) Overlay any per-script JSON files if present. These are effectively
		// identical to the catalog for JS strings, but we let them win so a
		// freshly regenerated JSON can override an older .mo.
		$files = $this->find_translation_json_files( $locales );
		foreach ( $files as $f ) {
			$raw = @file_get_contents( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $raw ) {
				continue;
			}
			$data = json_decode( $raw, true );
			if ( empty( $data['locale_data'] ) || ! is_array( $data['locale_data'] ) ) {
				continue;
			}
			// Loco keys locale_data by 'messages'; some tools use the domain
			// name. Take whichever inner block exists, ignoring the empty
			// header entry.
			foreach ( $data['locale_data'] as $inner ) {
				if ( ! is_array( $inner ) ) {
					continue;
				}
				if ( isset( $inner[''] ) && isset( $inner['']['plural-forms'] ) ) {
					$messages['']['plural-forms'] = $inner['']['plural-forms'];
				}
				foreach ( $inner as $msgid => $msgstr ) {
					if ( '' === $msgid ) {
						continue;
					}
					$messages[ $msgid ] = $msgstr;
				}
				break;
			}
		}

		// Nothing was translated for this locale (only the header entry remains).
		// Defer to WordPress's default handling instead of emitting an empty
		// locale-data block that would needlessly reset wp.i18n.
		if ( count( $messages ) <= 1 ) {
			$cache[ $cache_key ] = $translations;
			return $translations;
		}

		$merged = array(
			'domain'      => 'messages',
			'locale_data' => array(
				'messages' => $messages,
			),
		);

		$cache[ $cache_key ] = wp_json_encode( $merged );
		return $cache[ $cache_key ];
	}

	/**
	 * Merge the plugin's compiled PHP translation catalog (.l10n.php / .mo) into
	 * a script `locale_data` messages array.
	 *
	 * WordPress stores JS translations in per-script JSON files, but those must
	 * be generated explicitly (`wp i18n make-json` or a Loco "Sync"). On a site
	 * where only the .po/.mo was translated, no JSON exists — yet the .mo holds
	 * every string, JS ones included. We read it here so the React UI can be
	 * translated from the catalog alone.
	 *
	 * @param array    $messages Reference to the locale_data messages array to fill.
	 * @param string[] $locales  Locale codes in priority order.
	 *
	 * @return void
	 */
	private function merge_php_catalog_messages( array &$messages, array $locales ) {
		$locale = reset( $locales );
		$mofile = $this->find_newest_mofile( $locale );
		if ( '' === $mofile ) {
			return;
		}

		// Parse the .mo directly rather than reading get_translations_for_domain().
		// On WordPress 6.5+ the latter returns a controller-backed shim whose
		// ->entries is empty when the catalog was loaded from a .l10n.php cache,
		// so it can't be iterated. The MO parser always populates ->entries and
		// works on every supported WordPress version.
		if ( ! class_exists( 'MO' ) ) {
			require_once ABSPATH . WPINC . '/pomo/mo.php';
		}

		$mo = new \MO();
		if ( ! $mo->import_from_file( $mofile ) ) {
			return;
		}

		if ( empty( $messages['']['plural-forms'] ) && ! empty( $mo->headers['Plural-Forms'] ) ) {
			$messages['']['plural-forms'] = $mo->headers['Plural-Forms'];
		}

		foreach ( $mo->entries as $entry ) {
			$singular = isset( $entry->singular ) ? (string) $entry->singular : '';
			if ( '' === $singular ) {
				continue;
			}

			$entry_translations = isset( $entry->translations ) ? (array) $entry->translations : array();

			// Skip entries with no actual translation so they fall back to English.
			$has_translation = false;
			foreach ( $entry_translations as $t ) {
				if ( '' !== (string) $t ) {
					$has_translation = true;
					break;
				}
			}
			if ( ! $has_translation ) {
				continue;
			}

			// jed/tannin encodes context as "context\4singular".
			$context = ( isset( $entry->context ) && null !== $entry->context && '' !== (string) $entry->context ) ? (string) $entry->context : '';
			$key     = ( '' !== $context ) ? $context . "\4" . $singular : $singular;

			$messages[ $key ] = array_values( $entry_translations );
		}
	}

	/**
	 * Find the newest radius-hotel-booking .mo for a locale across the three folders a
	 * translator's file can live in (plugin-local, Loco "Custom", WP "System").
	 *
	 * Mirrors resolve_newest_mofile()'s "latest save wins" behaviour so the JS
	 * fallback reads exactly the catalog PHP would.
	 *
	 * @param string $locale Locale code, e.g. 'de_DE'.
	 *
	 * @return string Absolute path to the newest .mo, or '' if none found.
	 */
	private function find_newest_mofile( $locale ) {
		$dirs = array(
			RADIUS_HOTEL_BOOKING_DIR . '/languages',
			WP_LANG_DIR . '/loco/plugins',
			WP_LANG_DIR . '/plugins',
		);

		$best      = '';
		$best_time = -1;
		foreach ( $dirs as $dir ) {
			$candidate = $dir . '/radius-hotel-booking-' . $locale . '.mo';
			if ( is_readable( $candidate ) ) {
				$time = (int) filemtime( $candidate );
				if ( $time > $best_time ) {
					$best      = $candidate;
					$best_time = $time;
				}
			}
		}

		return $best;
	}

	/**
	 * Scan all known WordPress translation directories for radius-hotel-booking JSON
	 * files matching any of the given locales.
	 *
	 * Loco Translate stores its output in one of three places depending on the
	 * "File system access" setting: the plugin's own languages folder (Author),
	 * wp-content/languages/loco/plugins (Custom — Loco default), or
	 * wp-content/languages/plugins (System). We check all three so editors are
	 * free to pick whichever is appropriate for their server.
	 *
	 * @param string[] $locales Locale codes to try, in priority order.
	 *
	 * @return string[] List of absolute file paths.
	 */
	private function find_translation_json_files( array $locales ) {
		$dirs = array(
			RADIUS_HOTEL_BOOKING_DIR . '/languages',
			WP_LANG_DIR . '/loco/plugins',
			WP_LANG_DIR . '/plugins',
		);

		$found = array();
		foreach ( $dirs as $dir ) {
			foreach ( $locales as $locale ) {
				// Match Loco's hashed pattern and WordPress.org's bare pattern
				// so community translations from translate.wordpress.org are
				// picked up too.
				$patterns = array(
					$dir . '/radius-hotel-booking-' . $locale . '-*.json',
					$dir . '/radius-hotel-booking-' . $locale . '.json',
				);
				foreach ( $patterns as $pattern ) {
					$matches = glob( $pattern );
					if ( ! empty( $matches ) ) {
						$found = array_merge( $found, $matches );
					}
				}
			}
		}

		// Deduplicate in case the same path is reached more than once.
		$found = array_values( array_unique( $found ) );

		// Sort by mtime ascending so the most recently saved file is read
		// LAST in the merge loop — its messages overwrite anything older.
		// Without this, an older Loco JSON could win over a newer one
		// purely because glob() returned it later. mtime ordering matches
		// what a human would expect: latest save wins.
		usort(
			$found,
			static function ( $a, $b ) {
				return filemtime( $a ) <=> filemtime( $b );
			}
		);

		return $found;
	}

	/**
	 * Point WordPress at the newest radius-hotel-booking `.mo` across every Loco
	 * save location, so PHP-side translations behave like the JSON side.
	 *
	 * Loco Translate can write the compiled `.mo` to one of three folders
	 * depending on its "File system access" setting: the plugin's own
	 * languages folder (Author), wp-content/languages/loco/plugins (Custom —
	 * Loco's default) or wp-content/languages/plugins (System). WordPress's
	 * just-in-time loader only checks plugin-local and System, so a file saved
	 * to the Custom folder loads only while Loco itself is active. We reconcile
	 * that here.
	 *
	 * Safety contract (keeps this fully additive for existing users):
	 *  - Only the `radius-hotel-booking` domain is touched; every other domain is
	 *    returned untouched, so no other plugin/theme is affected.
	 *  - We seed the candidate with the path WordPress (or Loco) already chose
	 *    and only swap to a file that both exists AND is strictly newer, so we
	 *    can never regress to an older or missing file, and never fight a
	 *    newer selection Loco has already made.
	 *
	 * @param string $mofile Absolute path WordPress intends to load.
	 * @param string $domain Text domain being loaded.
	 *
	 * @return string Absolute path to the `.mo` that should be loaded.
	 */
	public function resolve_newest_mofile( $mofile, $domain ) {
		if ( 'radius-hotel-booking' !== $domain ) {
			return $mofile;
		}

		// Derive the locale from the incoming filename
		// (radius-hotel-booking-{locale}.mo) so we look for the same locale in the
		// other folders rather than guessing from get_locale().
		$basename = basename( $mofile );
		if ( 0 !== strpos( $basename, 'radius-hotel-booking-' ) || '.mo' !== substr( $basename, -3 ) ) {
			return $mofile;
		}

		static $cache = array();
		if ( isset( $cache[ $basename ] ) ) {
			return $cache[ $basename ];
		}

		$dirs = array(
			RADIUS_HOTEL_BOOKING_DIR . '/languages',
			WP_LANG_DIR . '/loco/plugins',
			WP_LANG_DIR . '/plugins',
		);

		// Start from whatever WordPress/Loco already resolved. Only an existing,
		// strictly-newer file is allowed to replace it.
		$best      = $mofile;
		$best_time = is_readable( $mofile ) ? (int) filemtime( $mofile ) : -1;

		foreach ( $dirs as $dir ) {
			$candidate = $dir . '/' . $basename;
			if ( $candidate === $best || ! is_readable( $candidate ) ) {
				continue;
			}
			$time = (int) filemtime( $candidate );
			if ( $time > $best_time ) {
				$best      = $candidate;
				$best_time = $time;
			}
		}

		$cache[ $basename ] = $best;
		return $best;
	}
}
