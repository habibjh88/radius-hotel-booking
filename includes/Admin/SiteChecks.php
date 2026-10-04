<?php
/**
 * Site configuration checks shown to administrators.
 *
 * @package RadiusTheme\RadiusHotelBooking\Admin
 */

namespace RadiusTheme\RadiusHotelBooking\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Problems in the site's own configuration that make the hotel's times or
 * reports wrong. Built on every dashboard load (so a check made at activation
 * keeps showing until it is fixed), for administrators only — they are the
 * ones who can change WordPress settings — and shown by the app as warnings
 * on the Dashboard and in Settings → General.
 *
 * Free checks one thing: a time zone set as a raw UTC offset ("UTC+1")
 * follows no daylight-saving rule, so a site in a zone with DST shows
 * arrivals an hour off for half the year. Add-ons add their own checks
 * through `rtbp_site_checks`.
 */
final class SiteChecks {

	/**
	 * The checks that fail, for the current user.
	 *
	 * @return array<int, array{id:string, message:string, action_label:string, action_url:string}>
	 */
	public static function params(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		$checks = array();
		if ( '' === (string) get_option( 'timezone_string', '' ) ) {
			$checks[] = array(
				'id'           => 'timezone_offset',
				'message'      => sprintf(
					/* translators: %s: the site's UTC offset, e.g. +01:00. */
					__( 'Your site\'s time zone is a fixed UTC offset (%s), which ignores daylight saving. Choose your city so arrival times and reports follow local time.', 'radius-hotel-booking' ),
					wp_timezone_string()
				),
				'action_label' => __( 'Choose the time zone', 'radius-hotel-booking' ),
				'action_url'   => admin_url( 'options-general.php#timezone_string' ),
			);
		}

		/**
		 * Filter the site checks that fail. Each is `{ id, message,
		 * action_label, action_url }`; only administrators see them.
		 *
		 * @param array $checks Failing checks.
		 */
		$checks = (array) apply_filters( 'rtbp_site_checks', $checks );

		$clean = array();
		foreach ( $checks as $check ) {
			if ( ! is_array( $check ) || empty( $check['id'] ) || empty( $check['message'] ) ) {
				continue;
			}
			$clean[] = array(
				'id'           => sanitize_key( (string) $check['id'] ),
				'message'      => wp_strip_all_tags( (string) $check['message'] ),
				'action_label' => wp_strip_all_tags( (string) ( $check['action_label'] ?? '' ) ),
				'action_url'   => esc_url_raw( (string) ( $check['action_url'] ?? '' ) ),
			);
		}
		return $clean;
	}
}
