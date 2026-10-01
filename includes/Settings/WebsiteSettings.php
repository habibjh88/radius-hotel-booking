<?php
/**
 * Settings → Public booking: how guests book on the website (M04).
 *
 * @package RadiusTheme\RadiusHotelBooking\Settings
 */

namespace RadiusTheme\RadiusHotelBooking\Settings;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The `website` section (option `rtbp_website_settings`). The booking rules
 * shared with the desk (booking window, same-day cut-off, manual approval,
 * show or hide unavailable rooms, hold minutes) stay in Booking rules.
 */
class WebsiteSettings {

	/**
	 * The section's schema.
	 *
	 * @return array
	 */
	public static function schema(): array {
		return array(
			// The guest picks the room number; off = the first free room by number is assigned (legacy: on).
			'guestPicksRoom' => array(
				'type'    => 'bool',
				'default' => true,
			),
			// A "how many rooms" field in the search bar (4.2).
			'showRoomsField' => array(
				'type'    => 'bool',
				'default' => false,
			),
			// The page holding [rtbp_booking], where the search bar sends guests; 0 = none yet.
			'resultsPageId'  => array(
				'type'     => 'int',
				'default'  => 0,
				'min'      => 0,
				'sanitize' => array( self::class, 'page' ),
			),
			// Guests tick "I agree to the privacy policy" before booking (the site's privacy page).
			'privacyConsent' => array(
				'type'    => 'bool',
				'default' => true,
			),
			// Adults preselected in the search bar (legacy ?adults=2).
			'defaultAdults'  => array(
				'type'    => 'int',
				'default' => 2,
				'min'     => 1,
				'max'     => 10,
			),
		);
	}

	/**
	 * A published page, or 0.
	 *
	 * @param mixed $value Page id.
	 * @return int|WP_Error
	 */
	public static function page( $value ) {
		$id = (int) $value;
		if ( 0 === $id ) {
			return 0;
		}
		return 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id )
			? $id
			: new WP_Error( 'rtbp_invalid_setting', __( 'Choose a published page.', 'radius-hotel-booking' ) );
	}

	/**
	 * The published pages to choose from (title and id), for the settings tab.
	 *
	 * @return array<int, array{id: int, title: string}>
	 */
	public static function pages(): array {
		$pages = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'post_title',
				'number'      => 200,
			)
		);
		return array_map(
			static fn( $page ) => array(
				'id'    => (int) $page->ID,
				'title' => '' !== $page->post_title ? $page->post_title : sprintf( '#%d', $page->ID ),
			),
			(array) $pages
		);
	}
}
