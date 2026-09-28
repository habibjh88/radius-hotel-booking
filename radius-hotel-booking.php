<?php
/**
 * Plugin Name:     Radius Hotel Booking
 * Description:     Hotel room booking by the hour or the night: half-day, overnight and 24-hour stays, taken at the front desk or on your website. No WooCommerce needed.
 * Plugin URI:      https://radiustheme.com
 * Version:         1.0.0
 * Author:          RadiusTheme
 * Author URI:      https://radiustheme.com
 * Text Domain:     radius-hotel-booking
 * Domain Path:     /languages
 * Requires PHP:    8.0
 * Requires at least: 6.2
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

use RadiusTheme\RadiusHotelBooking\Core\Api\ApiManager;
use RadiusTheme\RadiusHotelBooking\Core\Config;
use RadiusTheme\RadiusHotelBooking\Emails\TemplateHooks;

defined( 'ABSPATH' ) || exit;

/**
 * Class RadiusHotelBooking
 *
 * Main plugin class: defines constants, wires the activation/deactivation
 * lifecycle, boots the container and instantiates the plugin's components.
 *
 * @since 1.0.0
 */
final class RadiusHotelBooking {

	/**
	 * Plugin version. Keep in sync with the header above and readme.txt.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Database schema version. Bump it when a table changes so the installer
	 * re-runs the migrations on the next request.
	 *
	 * @var string
	 */
	const DBVERSION = '1.0.2';

	/**
	 * Plugin slug — the admin page slug and the asset handle prefix.
	 *
	 * @var string
	 */
	const SLUG = 'radius-hotel-booking';

	/**
	 * Holds the plugin's long-lived instances, exposed through __get().
	 *
	 * @var array
	 */
	private $container = array();

	/**
	 * Shortcode instance.
	 *
	 * @var \RadiusTheme\RadiusHotelBooking\Shortcodes\Shortcodes
	 */
	public $shortcode;

	/**
	 * Email manager instance.
	 *
	 * @var \RadiusTheme\RadiusHotelBooking\Emails\EmailManager
	 */
	public $emails;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		require_once __DIR__ . '/vendor/autoload.php';

		$this->define_constants();

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		$this->init_plugin();
	}

	/**
	 * Singleton accessor.
	 *
	 * @return RadiusHotelBooking
	 * @since 1.0.0
	 */
	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new RadiusHotelBooking();
		}

		return $instance;
	}

	/**
	 * Magic getter for the instance container.
	 *
	 * @param string $prop Property name.
	 *
	 * @return mixed
	 */
	public function __get( $prop ) {
		if ( array_key_exists( $prop, $this->container ) ) {
			return $this->container[ $prop ];
		}

		return $this->{$prop};
	}

	/**
	 * Whether a property is set.
	 *
	 * @param string $prop Property name.
	 *
	 * @return bool
	 */
	public function __isset( $prop ) {
		return isset( $this->{$prop} ) || isset( $this->container[ $prop ] );
	}

	/**
	 * Define the plugin constants.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function define_constants() {
		define( 'RADIUS_HOTEL_BOOKING_VERSION', self::VERSION );
		define( 'RADIUS_HOTEL_BOOKING_DB_VERSION', self::DBVERSION );
		define( 'RADIUS_HOTEL_BOOKING_SLUG', self::SLUG );

		define( 'RADIUS_HOTEL_BOOKING_FILE', __FILE__ );
		define( 'RADIUS_HOTEL_BOOKING_DIR', plugin_dir_path( __FILE__ ) );
		define( 'RADIUS_HOTEL_BOOKING_PATH', dirname( RADIUS_HOTEL_BOOKING_FILE ) );
		define( 'RADIUS_HOTEL_BOOKING_INCLUDES', RADIUS_HOTEL_BOOKING_PATH . '/includes' );
		define( 'RADIUS_HOTEL_BOOKING_TEMPLATE_PATH', RADIUS_HOTEL_BOOKING_PATH . '/views' );
		define( 'RADIUS_HOTEL_BOOKING_URL', plugin_dir_url( __FILE__ ) );
		define( 'RADIUS_HOTEL_BOOKING_BUILD', untrailingslashit( RADIUS_HOTEL_BOOKING_URL ) . '/build' );
		define( 'RADIUS_HOTEL_BOOKING_ASSETS', untrailingslashit( RADIUS_HOTEL_BOOKING_URL ) . '/assets' );
		define( 'RADIUS_HOTEL_BOOKING_TEMPLATES_DIR', RADIUS_HOTEL_BOOKING_DIR . 'templates/emails/' );
	}

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init_plugin() {
		$this->includes();
		$this->init_hooks();

		add_action( 'init', array( '\RadiusTheme\RadiusHotelBooking\Shortcodes\Shortcodes', 'init' ) );

		RadiusTheme\RadiusHotelBooking\Setup\Installer::init();
		RadiusTheme\RadiusHotelBooking\Setup\Scheduler::init();
		RadiusTheme\RadiusHotelBooking\Frontend\DashboardPage::init();

		// Multisite: set the tables up when a new site is created.
		add_action( 'wp_insert_site', array( $this, 'on_new_site_created' ) );

		// Elementor — registered on init, before Elementor collects widgets.
		add_action(
			'init',
			function () {
				if ( did_action( 'elementor/loaded' ) ) {
					new RadiusTheme\RadiusHotelBooking\Elementor\ElementorManager();
				}
			}
		);

		// Gutenberg blocks.
		new RadiusTheme\RadiusHotelBooking\Blocks\BlockManager();

		/**
		 * Fires once the plugin is loaded.
		 *
		 * @since 1.0.0
		 */
		do_action( 'rtbp_loaded' );
	}

	/**
	 * Activation hook.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 *
	 * @return void
	 */
	public function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$this->network_activate();

			return;
		}

		$this->install();

		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		set_transient( RadiusTheme\RadiusHotelBooking\Common\Keys::ACTIVATION_REDIRECT, true, 30 );
	}

	/**
	 * Run the installer on every site of the network.
	 *
	 * @return void
	 */
	private function network_activate() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			$this->install();
			restore_current_blog();
		}
	}

	/**
	 * Set the plugin up on a newly created multisite subsite.
	 *
	 * @param \WP_Site $new_site New site object.
	 *
	 * @return void
	 */
	public function on_new_site_created( $new_site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
			return;
		}

		switch_to_blog( $new_site->blog_id );
		$this->install();
		restore_current_blog();
	}

	/**
	 * Deactivation hook. Tables and data are deliberately preserved.
	 *
	 * @param bool $network_wide Whether the plugin is being network-deactivated.
	 *
	 * @return void
	 */
	public function deactivate( $network_wide = false ) {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		RadiusTheme\RadiusHotelBooking\Setup\Scheduler::unschedule_all();
		flush_rewrite_rules();
	}

	/**
	 * Run the installer.
	 *
	 * @return void
	 */
	private function install() {
		( new RadiusTheme\RadiusHotelBooking\Setup\Installer() )->run();
	}

	/**
	 * Instantiate the always-on components and load the procedural helpers.
	 *
	 * @return void
	 */
	public function includes() {
		if ( $this->is_request( 'admin' ) ) {
			$this->container['admin_menu'] = new RadiusTheme\RadiusHotelBooking\Admin\Menu();
		}

		$this->container['assets']   = new RadiusTheme\RadiusHotelBooking\Assets\LoadAssets();
		$this->container['rest_api'] = new ApiManager();

		require_once RADIUS_HOTEL_BOOKING_INCLUDES . '/Utility/core-functions.php';
		require_once RADIUS_HOTEL_BOOKING_INCLUDES . '/Utility/template-functions.php';
	}

	/**
	 * Register the plugin's hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'init', array( $this, 'init_classes' ) );
		add_action( 'plugins_loaded', array( $this, 'loaded_bootstrap' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Register the translations shipped in languages/ (French).
	 *
	 * Recent WordPress (7.0) registers the Domain Path folder by itself; the
	 * older versions the plugin supports only read wp-content/languages, so
	 * there the bundled .mo would never load for PHP strings. A language pack
	 * in wp-content/languages still takes priority.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'radius-hotel-booking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Load the container bindings and event listeners.
	 *
	 * @return void
	 */
	public function loaded_bootstrap() {
		new Config();
	}

	/**
	 * Instantiate the classes that need to run on `init`.
	 *
	 * @return void
	 */
	public function init_classes() {
		( new RadiusTheme\RadiusHotelBooking\Core\Permissions\PermissionsManager() )->init();

		new RadiusTheme\RadiusHotelBooking\Hooks\Common();

		$this->shortcode = new RadiusTheme\RadiusHotelBooking\Shortcodes\Shortcodes();
		$this->emails    = new RadiusTheme\RadiusHotelBooking\Emails\EmailManager();

		TemplateHooks::init();

		/**
		 * Lets an add-on plugin register its integrations once the free plugin
		 * is fully booted.
		 *
		 * @since 1.0.0
		 */
		do_action( 'rtbp_register_addon_integrations' );
	}

	/**
	 * What kind of request is this.
	 *
	 * @param string $type One of admin, ajax, rest, cron, frontend.
	 *
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();

			case 'ajax':
				return defined( 'DOING_AJAX' );

			case 'rest':
				return defined( 'REST_REQUEST' );

			case 'cron':
				return defined( 'DOING_CRON' );

			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}

		return false;
	}

	/**
	 * Add links to the plugin's row on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '#/settings' ) ) . '">' . esc_html__( 'Settings', 'radius-hotel-booking' ) . '</a>';

		return $links;
	}
}

/**
 * Plugin entry point.
 *
 * @return RadiusHotelBooking
 * @since 1.0.0
 */
function radius_hotel_booking() { // phpcs:ignore
	return RadiusHotelBooking::init();
}

radius_hotel_booking();

/**
 * Register the WP-CLI commands.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	( new \RadiusTheme\RadiusHotelBooking\Core\CommandRegistry() )->init();
}
