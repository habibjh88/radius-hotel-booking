<?php
/**
 * Uninstall: runs when the plugin is deleted from the Plugins screen.
 *
 * Nothing is removed unless General → "Delete all data on uninstall"
 * (`rtbp_general_settings[deleteDataOnUninstall]`) is on, so deleting the
 * plugin to reinstall it never costs the hotel its bookings. When it is on,
 * each site loses:
 *
 * - every table starting with the plugin's table prefix (free, Pro and the
 *   client add-on share it);
 * - every `rtbp_*` option and transient;
 * - the pages the plugin created (Hotel Dashboard …);
 * - the `rtbp_*` roles, and `rtbp_*` capabilities on every other role;
 * - the protected files folder in uploads. A folder set with
 *   `RTBP_PROTECTED_DIR` belongs to the host and is left alone.
 *
 * WordPress runs this file without loading the plugin, so it relies on
 * WordPress functions only.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Whether the site asked for its data to be removed.
 *
 * @return bool
 */
function rtbp_uninstall_wanted(): bool {
	$general = get_option( 'rtbp_general_settings', array() );
	return is_array( $general ) && ! empty( $general['deleteDataOnUninstall'] );
}

/**
 * Remove the current site's data.
 *
 * @return void
 */
function rtbp_uninstall_site(): void {
	global $wpdb;

	// Tables (the prefix can be overridden in wp-config.php).
	$prefix = defined( 'RADIUS_HOTEL_BOOKING_TABLE_PREFIX' ) && RADIUS_HOTEL_BOOKING_TABLE_PREFIX
		? RADIUS_HOTEL_BOOKING_TABLE_PREFIX
		: $wpdb->prefix . 'radius_hotel_booking_';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	// Pages the plugin created.
	$pages = get_posts(
		array(
			'post_type'   => 'any',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => '_rtbp_page', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
		)
	);
	foreach ( $pages as $page_id ) {
		wp_delete_post( (int) $page_id, true );
	}

	// Roles and capabilities. Only roles the plugin owns are removed: an
	// add-on may grant caps to core roles, which must survive.
	$roles = wp_roles();
	foreach ( array_keys( $roles->roles ) as $slug ) {
		if ( 0 === strpos( $slug, 'rtbp_' ) ) {
			remove_role( $slug );
			continue;
		}
		$role = get_role( $slug );
		foreach ( array_keys( (array) $role->capabilities ) as $cap ) {
			if ( 0 === strpos( $cap, 'rtbp_' ) ) {
				$role->remove_cap( $cap );
			}
		}
	}

	// Protected files in uploads.
	if ( ! defined( 'RTBP_PROTECTED_DIR' ) ) {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . 'radius-hotel-booking';
		if ( is_dir( $dir ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			if ( WP_Filesystem() ) {
				global $wp_filesystem;
				$wp_filesystem->delete( $dir, true );
			}
		}
	}

	// Options and transients last, so the steps above could still read them.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'rtbp_' ) . '%',
			$wpdb->esc_like( '_transient_rtbp_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_rtbp_' ) . '%'
		)
	);
	wp_cache_flush();
}

if ( is_multisite() ) {
	$rtbp_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $rtbp_site_ids as $rtbp_site_id ) {
		switch_to_blog( (int) $rtbp_site_id );
		if ( rtbp_uninstall_wanted() ) {
			rtbp_uninstall_site();
		}
		restore_current_blog();
	}
} elseif ( rtbp_uninstall_wanted() ) {
	rtbp_uninstall_site();
}
