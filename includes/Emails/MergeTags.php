<?php
/**
 * Merge tags.
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails
 */

namespace RadiusTheme\RadiusHotelBooking\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers and replaces the `{tag}` placeholders available in email subjects
 * and templates.
 *
 * BOILERPLATE: register your own tags in init() (or from an add-on via
 * register_tag()); the site-wide tags at the bottom are always available.
 */
class MergeTags {

	/**
	 * Registered merge tags.
	 *
	 * @var array
	 */
	private static $tags = array();

	/**
	 * Register the built-in tags.
	 *
	 * @return void
	 */
	public static function init() {
		self::register_tag( 'recipient_name', __( 'Recipient Name', 'radius-hotel-booking' ) );
		self::register_tag( 'recipient_email', __( 'Recipient Email', 'radius-hotel-booking' ) );
		self::register_tag( 'date', __( 'Date', 'radius-hotel-booking' ) );
		self::register_tag( 'time', __( 'Time', 'radius-hotel-booking' ) );
		self::register_tag( 'price', __( 'Price', 'radius-hotel-booking' ) );
		self::register_tag( 'status', __( 'Status', 'radius-hotel-booking' ) );
		self::register_tag( 'notes', __( 'Notes', 'radius-hotel-booking' ) );

		// Always-available site tags, filled in by replace().
		self::register_tag( 'site_url', __( 'Site URL', 'radius-hotel-booking' ) );
		self::register_tag( 'site_name', __( 'Site Name', 'radius-hotel-booking' ) );
		self::register_tag( 'current_year', __( 'Current Year', 'radius-hotel-booking' ) );

		do_action( 'rtbp_register_merge_tags' );
	}

	/**
	 * Register a tag.
	 *
	 * @param string        $tag      Tag name, without braces.
	 * @param string        $label    Human label shown in the UI.
	 * @param callable|null $callback Optional resolver receiving the data array.
	 *
	 * @return void
	 */
	public static function register_tag( $tag, $label, $callback = null ) {
		self::$tags[ $tag ] = array(
			'label'    => $label,
			'callback' => $callback,
		);
	}

	/**
	 * All registered tags.
	 *
	 * @return array
	 */
	public static function get_tags() {
		return self::$tags;
	}

	/**
	 * Replace `{tag}`, `[tag]` and `{{tag}}` placeholders in a string.
	 *
	 * @param string $content Content containing placeholders.
	 * @param array  $data    Tag name => replacement value.
	 *
	 * @return string
	 */
	public static function replace( $content, $data ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}

		$data['site_url']     = get_site_url();
		$data['site_name']    = get_bloginfo( 'name' );
		$data['current_year'] = gmdate( 'Y' );

		$patterns = array(
			'/\{\{([a-zA-Z0-9_]+)\}\}/',
			'/\{([a-zA-Z0-9_]+)\}/',
			'/\[([a-zA-Z0-9_]+)\]/',
		);

		foreach ( $patterns as $pattern ) {
			$content = preg_replace_callback(
				$pattern,
				function ( $matches ) use ( $data ) {
					$tag = $matches[1];

					if ( isset( self::$tags[ $tag ] ) && is_callable( self::$tags[ $tag ]['callback'] ) ) {
						return call_user_func( self::$tags[ $tag ]['callback'], $data );
					}

					return isset( $data[ $tag ] ) ? $data[ $tag ] : '';
				},
				$content
			);
		}

		return $content;
	}
}
