<?php
/**
 * The one upsell the free plugin shows (ADR-016).
 *
 * @package RadiusTheme\RadiusHotelBooking\Admin
 */

namespace RadiusTheme\RadiusHotelBooking\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The Plugin Directory allows an upsell only as one dismissible notice on the
 * plugin's own Settings screen plus an "Upgrade" link. This class owns both:
 * the link on the Plugins screen row, and the data the Settings screen needs
 * to show the notice. Nothing is shown while Pro is active.
 *
 * Dismissal is per user, stored in user meta that the Settings screen writes
 * through the core `wp/v2/users/me` endpoint (so no endpoint of our own).
 */
final class UpgradeNotice {

	/**
	 * User meta: 1 once the user dismissed the notice.
	 */
	public const META_KEY = 'rtbp_upgrade_notice_dismissed';

	/**
	 * Register the user meta (REST-writable by the user themself).
	 *
	 * @return void
	 */
	public static function init(): void {
		register_meta(
			'user',
			self::META_KEY,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => static fn( $allowed, $meta_key, $user_id ) => get_current_user_id() === (int) $user_id,
			)
		);
	}

	/**
	 * Where "Upgrade" points.
	 *
	 * @return string
	 */
	public static function url(): string {
		/**
		 * Filter the Upgrade link (the Pro product page).
		 *
		 * @param string $url URL.
		 */
		return (string) apply_filters( 'rtbp_upgrade_url', 'https://radiustheme.com' );
	}

	/**
	 * Whether the notice should show to the current user.
	 *
	 * @return bool
	 */
	public static function should_show(): bool {
		return ! rtbp_addon_active()
			&& current_user_can( 'manage_options' )
			&& ! get_user_meta( get_current_user_id(), self::META_KEY, true );
	}

	/**
	 * Data for the Settings screen.
	 *
	 * `locked`: Pro is not active and the user is an administrator, so the
	 * dashboard lists Pro's pages and Settings tabs with a lock and a PRO badge
	 * (names and descriptions only; no Pro code ships here).
	 *
	 * `carryover`: blocks and date prices / closures made with Pro that the
	 * engine still applies (counted only for the locked pages).
	 *
	 * @return array{show:bool,url:string,locked:bool,carryover:array{blocks:int,dates:int}}
	 */
	public static function params(): array {
		$locked = ! rtbp_addon_active() && current_user_can( 'manage_options' );
		return array(
			'show'      => self::should_show(),
			'url'       => esc_url_raw( self::url(), array( 'https', 'http' ) ),
			'locked'    => $locked,
			'carryover' => $locked ? self::carryover() : array(
				'blocks' => 0,
				'dates'  => 0,
			),
		);
	}

	/**
	 * Blocks not yet over, and dates from today with a price or a closure.
	 *
	 * @return array{blocks:int,dates:int}
	 */
	private static function carryover(): array {
		global $wpdb;
		$now   = gmdate( 'Y-m-d H:i:s' );
		$today = wp_date( 'Y-m-d' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- two counts, administrators only, while Pro is inactive.
		$blocks = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE end_at_gmt > %s', rtbp_table( 'blocks' ), $now ) );
		$dates  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE date >= %s AND ( price_override IS NOT NULL OR is_closed = 1 )', rtbp_table( 'rate_calendar' ), $today ) );
		// phpcs:enable
		return array(
			'blocks' => $blocks,
			'dates'  => $dates,
		);
	}

	/**
	 * Append "Upgrade" to the plugin's row on the Plugins screen.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public static function action_link( array $links ): array {
		if ( ! rtbp_addon_active() ) {
			$links[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( self::url() ),
				esc_html__( 'Upgrade', 'radius-hotel-booking' )
			);
		}
		return $links;
	}
}
