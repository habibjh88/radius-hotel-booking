<?php
/**
 * Installer.
 *
 * Runs schema migrations, provisions roles and creates the default pages, on
 * activation and on the first request after a version bump.
 *
 * @package RadiusTheme\RadiusHotelBooking\Setup
 */

namespace RadiusTheme\RadiusHotelBooking\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Common\Keys;
use RadiusTheme\RadiusHotelBooking\Databases\DatabaseManager;

/**
 * Class Installer
 *
 * @since 1.0.0
 */
class Installer {

	/**
	 * Hook the installer into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'activation_redirect' ), 1 );
		add_action( 'admin_init', array( PermissionsInstaller::class, 'maybe_sync' ), 6 );

		// Run pending schema upgrades on *every* request, not just wp-admin:
		// `admin_init` never fires on front-end traffic, so after a release the
		// new code could query columns the migration hadn't added yet. Priority
		// 20 keeps it after the plugin's own bootstrap.
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ), 20 );
	}

	/**
	 * One-time redirect to the plugin's admin page after activation.
	 *
	 * @return void
	 */
	public static function activation_redirect() {
		if ( ! get_transient( Keys::ACTIVATION_REDIRECT ) ) {
			return;
		}

		delete_transient( Keys::ACTIVATION_REDIRECT );

		// Don't redirect on network/bulk activation, under WP-CLI, or on subsites.
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . RADIUS_HOTEL_BOOKING_SLUG ) );
		exit;
	}

	/**
	 * Run any pending schema upgrade, version-gated and lock-protected.
	 *
	 * Once the stored DB version matches the code this is a single get_option
	 * no-op, so it is safe on every request.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$current_version = get_option( Keys::DB_VERSION, '0' );

		if ( version_compare( $current_version, RADIUS_HOTEL_BOOKING_DB_VERSION, '>=' ) ) {
			return;
		}

		// dbDelta is idempotent, so the lock is an optimisation rather than a
		// correctness requirement.
		if ( get_transient( Keys::UPGRADING ) ) {
			return;
		}
		set_transient( Keys::UPGRADING, 1, MINUTE_IN_SECONDS );

		try {
			( new self() )->run();
		} finally {
			delete_transient( Keys::UPGRADING );
		}
	}

	/**
	 * Run the installer.
	 *
	 * @return void
	 */
	public function run() {
		// Create tables first, then stamp the version, so a failed migration retries.
		$this->create_tables();
		$this->add_version();

		// maybe_upgrade() runs on plugins_loaded, before WordPress builds
		// $wp_rewrite (wp_insert_post() fatals without it) and before
		// translations may load (the page titles are translated).
		if ( did_action( 'init' ) ) {
			self::finish();
		} else {
			add_action( 'init', array( __CLASS__, 'finish' ), 20 );
		}
	}

	/**
	 * Create any missing page, then announce the install.
	 *
	 * On a fresh install no page is stored yet, so every page is created.
	 *
	 * @return void
	 */
	public static function finish(): void {
		PageInstaller::create_missing_pages();

		// Once: files named after their download token get a secret name (M11).
		if ( ! get_option( 'rtbp_files_renamed' ) ) {
			\RadiusTheme\RadiusHotelBooking\Storage\ProtectedFiles::rename_token_named();
			update_option( 'rtbp_files_renamed', 1, false );
		}

		/**
		 * Fires after the installer has finished.
		 *
		 * @since 1.0.0
		 */
		do_action( 'rtbp_installed' );
	}

	/**
	 * Record install time and versions.
	 *
	 * @return void
	 */
	public function add_version(): void {
		if ( ! get_option( Keys::INSTALLED ) ) {
			update_option( Keys::INSTALLED, time() );
		}

		// DB Version
		update_option( Keys::DB_VERSION, RADIUS_HOTEL_BOOKING_DB_VERSION );
		// Plugin version
		update_option( Keys::VERSION, RADIUS_HOTEL_BOOKING_VERSION );
	}

	/**
	 * Create/upgrade the plugin's database tables.
	 *
	 * No capability check here: the schema upgrade must be able to run on an
	 * anonymous front-end request. Safe because every caller is either the
	 * activation hook (privileged) or maybe_upgrade() (version-gated, locked
	 * and idempotent).
	 *
	 * @return void
	 */
	public function create_tables() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$this->clear_old_migration_records();

		DatabaseManager::migrate();
	}

	/**
	 * Remove migration records from previous DB versions so migrations re-run
	 * cleanly after a schema version bump.
	 *
	 * @return void
	 */
	private function clear_old_migration_records() {
		global $wpdb;

		$table = $wpdb->prefix . 'radius_hotel_booking_migrations';

		// Bail if the migrations table doesn't exist yet (fresh install).
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore
		if ( empty( $exists ) ) {
			return;
		}

		$current_prefix = RADIUS_HOTEL_BOOKING_DB_VERSION . '_';

		// The table name is built from $wpdb->prefix, not from request data, and
		// a table name cannot be a prepare() placeholder.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE migration NOT LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_prefix . '%'
			)
		);

		wp_cache_delete( 'radius_hotel_booking_executed_migrations' );
	}
}
