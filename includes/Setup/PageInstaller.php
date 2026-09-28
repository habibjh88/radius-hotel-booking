<?php
/**
 * Creates the plugin's default pages on first activation.
 *
 * @package RadiusTheme\RadiusHotelBooking\Setup
 */

namespace RadiusTheme\RadiusHotelBooking\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Common\Keys;

/**
 * Class PageInstaller
 *
 * @since 1.0.0
 */
class PageInstaller {

	/**
	 * Pages to create: settings key => [title, block content].
	 *
	 * The front-end staff dashboard ships here; the booking results and
	 * confirmation pages arrive with M04. Pages listed here are created on
	 * install and on upgrade (create_missing_pages()).
	 *
	 * @return array
	 */
	private static function get_pages(): array {
		$pages = array(
			'dashboardPage' => array(
				'title'   => __( 'Hotel Dashboard', 'radius-hotel-booking' ),
				'content' => '<!-- wp:shortcode -->[rtbp_dashboard]<!-- /wp:shortcode -->',
			),
		);

		/**
		 * Filters the pages created on install: settings key => [ title, content ].
		 *
		 * @param array $pages Pages.
		 */
		return (array) apply_filters( 'rtbp_install_pages', $pages );
	}

	/**
	 * Create any page added in a newer version, or recreate one that was
	 * trashed or deleted.
	 *
	 * @return void
	 */
	public static function create_missing_pages(): void {
		$existing = get_option( Keys::PAGES, array() );
		$existing = is_array( $existing ) ? $existing : array();

		$missing = array();
		foreach ( self::get_pages() as $key => $page_data ) {
			if ( ! empty( $existing[ $key ] ) && 'publish' === get_post_status( $existing[ $key ] ) ) {
				continue;
			}
			$missing[ $key ] = $page_data;
		}

		if ( $missing ) {
			self::insert( $missing, $existing );
		}
	}

	/**
	 * Insert pages and merge their IDs into the stored page map.
	 *
	 * @param array $pages    Pages to insert, keyed by settings key.
	 * @param array $existing Currently stored page map.
	 *
	 * @return void
	 */
	private static function insert( array $pages, array $existing ): void {
		$ids = array();

		foreach ( $pages as $key => $page_data ) {
			$page_id = wp_insert_post(
				array(
					'post_title'     => $page_data['title'],
					'post_content'   => $page_data['content'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'post_author'    => get_current_user_id() ? get_current_user_id() : 1,
					'comment_status' => 'closed',
				),
				true
			);

			if ( ! is_wp_error( $page_id ) ) {
				$ids[ $key ] = $page_id;
				update_post_meta( $page_id, '_rtbp_page', $key );
			}
		}

		if ( $ids ) {
			update_option( Keys::PAGES, array_merge( $existing, $ids ) );
		}
	}
}
