<?php
/**
 * Shortcode registry.
 *
 * @package RadiusTheme\RadiusHotelBooking\Shortcodes
 */

namespace RadiusTheme\RadiusHotelBooking\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Class Shortcodes
 *
 * Every shortcode the plugin registers is listed in init(). Each tag is passed
 * through a filter so a site can rename it without touching this file.
 *
 * Each shortcode also describes itself on `rtbp_shortcode_catalog`, next to
 * where it is registered; the dashboard's Shortcodes page (System) lists them
 * with a copy button.
 *
 * @since 1.0.0
 */
class Shortcodes {

	/**
	 * Register the shortcodes.
	 *
	 * @return void
	 */
	public static function init() {
		/**
		 * Filters the plugin's shortcodes (tag => callback). Describe each one on
		 * `rtbp_shortcode_catalog` too, so the Shortcodes page lists it.
		 *
		 * @param array<string,callable> $shortcodes Shortcodes.
		 */
		$shortcodes = (array) apply_filters( 'rtbp_shortcodes', array() );

		foreach ( $shortcodes as $shortcode => $callback ) {
			add_shortcode( apply_filters( "rtbp_{$shortcode}_shortcode_tag", $shortcode ), $callback );
		}
	}

	/**
	 * The shortcodes to list on the dashboard's Shortcodes page: those
	 * described on `rtbp_shortcode_catalog` and registered, under their
	 * (possibly renamed) tag.
	 *
	 * @return array<int,array{tag:string,title:string,description:string,also:string[],order:int,attributes:array}>
	 */
	public static function catalog(): array {
		/**
		 * Filters the shortcode descriptions (key => `{ title, description, also?, order?, attributes? }`).
		 * The key is the default tag (the `rtbp_shortcodes` key); `also` lists the
		 * other ways to place the same thing (block, Elementor widget); `order`
		 * (default 50) sorts the list, lowest first. `attributes` turns the card
		 * into a builder: `[ { name, label, type: select|multiselect|number|text,
		 * default, options?: [ { value, label } ], min?, max?, help?,
		 * when?: { other attribute: value } } ]` — keep `default` equal to the
		 * shortcode's own default (shortcode_atts()).
		 *
		 * @param array<string,array> $catalog Descriptions.
		 */
		$catalog = (array) apply_filters( 'rtbp_shortcode_catalog', array() );
		$list    = array();
		foreach ( $catalog as $key => $item ) {
			$tag = (string) apply_filters( "rtbp_{$key}_shortcode_tag", $key );
			if ( ! is_array( $item ) || ! shortcode_exists( $tag ) ) {
				continue;
			}
			$list[] = array(
				'tag'         => $tag,
				'title'       => (string) ( $item['title'] ?? $tag ),
				'description' => (string) ( $item['description'] ?? '' ),
				'also'        => array_values( array_map( 'strval', (array) ( $item['also'] ?? array() ) ) ),
				'order'       => (int) ( $item['order'] ?? 50 ),
				'attributes'  => self::attributes( (array) ( $item['attributes'] ?? array() ) ),
			);
		}
		usort( $list, static fn( $a, $b ) => $a['order'] <=> $b['order'] );
		return $list;
	}

	/**
	 * A catalogue entry's attributes in the shape the builder reads; unknown
	 * types and nameless entries are dropped.
	 *
	 * @param array $attributes Attributes.
	 * @return array
	 */
	private static function attributes( array $attributes ): array {
		$list = array();
		foreach ( $attributes as $attribute ) {
			$type = (string) ( $attribute['type'] ?? 'text' );
			$name = sanitize_key( (string) ( $attribute['name'] ?? '' ) );
			if ( '' === $name || ! in_array( $type, array( 'select', 'multiselect', 'number', 'text' ), true ) ) {
				continue;
			}
			$list[] = array(
				'name'    => $name,
				'label'   => (string) ( $attribute['label'] ?? $name ),
				'type'    => $type,
				'default' => (string) ( $attribute['default'] ?? '' ),
				'options' => array_values(
					array_map(
						static fn( $option ) => array(
							'value' => (string) ( $option['value'] ?? '' ),
							'label' => (string) ( $option['label'] ?? $option['value'] ?? '' ),
						),
						(array) ( $attribute['options'] ?? array() )
					)
				),
				'min'     => isset( $attribute['min'] ) ? (float) $attribute['min'] : null,
				'max'     => isset( $attribute['max'] ) ? (float) $attribute['max'] : null,
				'help'    => (string) ( $attribute['help'] ?? '' ),
				'when'    => (object) array_map( 'strval', (array) ( $attribute['when'] ?? array() ) ),
			);
		}
		return $list;
	}

	/**
	 * Render a shortcode inside a wrapper element and return the markup.
	 *
	 * @param callable $callback Callback producing the output.
	 * @param array    $atts     Shortcode attributes.
	 * @param array    $wrapper  Wrapper config: 'class', 'before', 'after'.
	 *
	 * @return string
	 */
	public static function shortcode_wrapper(
		$callback,
		$atts = array(),
		$wrapper = array(
			'class'  => 'radius-hotel-booking',
			'before' => null,
			'after'  => null,
		)
	) {
		ob_start();

		echo empty( $wrapper['before'] )
			? '<div class="' . esc_attr( $wrapper['class'] ) . '">'
			: wp_kses_post( $wrapper['before'] );

		call_user_func( $callback, $atts );

		echo empty( $wrapper['after'] )
			? '</div>'
			: wp_kses_post( $wrapper['after'] );

		return ob_get_clean();
	}
}
