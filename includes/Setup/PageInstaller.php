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
use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use RadiusTheme\RadiusHotelBooking\Services\SettingsService;

/**
 * Class PageInstaller
 *
 * @since 1.0.0
 */
class PageInstaller {

	/**
	 * Pages to create: page key => [ title, block content, setting? ].
	 *
	 * The front-end staff dashboard and the guest booking page. Pages listed
	 * here are created on install and on upgrade (create_missing_pages()). A
	 * page with `setting` ([ section, key ]) is the page that setting points
	 * to: a page already chosen there is adopted rather than duplicated, and a
	 * new page is chosen there when it is empty.
	 *
	 * @return array
	 */
	private static function get_pages(): array {
		$pages = array(
			'dashboardPage' => array(
				'title'   => __( 'Hotel Dashboard', 'radius-hotel-booking' ),
				'content' => '<!-- wp:shortcode -->[rtbp_dashboard]<!-- /wp:shortcode -->',
			),
			// Where the search bar sends guests (Settings → Public booking → Booking page).
			'bookingPage'   => array(
				'title'   => __( 'Book a room', 'radius-hotel-booking' ),
				'content' => '<!-- wp:shortcode -->[rtbp_booking]<!-- /wp:shortcode -->',
				'setting' => array( 'website', 'resultsPageId' ),
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
		$adopted = array();
		foreach ( self::get_pages() as $key => $page_data ) {
			if ( ! empty( $existing[ $key ] ) && 'publish' === get_post_status( $existing[ $key ] ) ) {
				continue;
			}
			// A page already chosen in its setting (e.g. made with the
			// "Create the booking page" button) is that page: no duplicate.
			$chosen = self::chosen( $page_data );
			if ( $chosen ) {
				$adopted[ $key ] = $chosen;
				continue;
			}
			$missing[ $key ] = $page_data;
		}

		if ( $adopted ) {
			$existing = array_merge( $existing, $adopted );
			update_option( Keys::PAGES, $existing );
		}
		if ( $missing ) {
			self::insert( $missing, $existing );
		}
	}

	/**
	 * Record a page made elsewhere as one of the plugin's pages (the booking
	 * page button), so install and upgrade do not make a second one.
	 *
	 * @param string $key     Page key, e.g. 'bookingPage'.
	 * @param int    $page_id Page ID.
	 * @return void
	 */
	public static function remember( string $key, int $page_id ): void {
		$existing = get_option( Keys::PAGES, array() );
		$existing = is_array( $existing ) ? $existing : array();
		update_option( Keys::PAGES, array_merge( $existing, array( $key => $page_id ) ) );
		update_post_meta( $page_id, '_rtbp_page', $key );
	}

	/**
	 * The published page a page's setting already points to, or 0.
	 *
	 * @param array $page_data Page definition.
	 * @return int
	 */
	private static function chosen( array $page_data ): int {
		if ( empty( $page_data['setting'] ) ) {
			return 0;
		}
		list( $section, $key ) = $page_data['setting'];
		$page_id               = (int) rtbp_setting( $section, $key, 0 );
		return $page_id && 'publish' === get_post_status( $page_id ) ? $page_id : 0;
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
				self::choose( $page_data, (int) $page_id );
			}
		}

		if ( $ids ) {
			update_option( Keys::PAGES, array_merge( $existing, $ids ) );
		}
	}

	/**
	 * Point a page's setting at a new page, through SettingsService (saved
	 * and logged like any settings change).
	 *
	 * @param array $page_data Page definition.
	 * @param int   $page_id   The new page.
	 * @return void
	 */
	private static function choose( array $page_data, int $page_id ): void {
		if ( empty( $page_data['setting'] ) ) {
			return;
		}
		list( $section, $key ) = $page_data['setting'];
		try {
			Container::resolve( SettingsService::class )->updateSection( $section, array( $key => $page_id ) );
		} catch ( \Throwable $e ) {
			// The page exists; it can still be chosen in Settings.
			unset( $e );
		}
	}
}
