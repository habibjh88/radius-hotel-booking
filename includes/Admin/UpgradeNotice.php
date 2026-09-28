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
	 * @return array{show:bool,url:string}
	 */
	public static function params(): array {
		return array(
			'show' => self::should_show(),
			'url'  => self::url(),
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
