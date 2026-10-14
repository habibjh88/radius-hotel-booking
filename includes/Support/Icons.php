<?php
/**
 * Lucide icons.
 *
 * @package RadiusTheme\RadiusHotelBooking\Support
 */

namespace RadiusTheme\RadiusHotelBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's icon set: Lucide (ISC licence), one SVG file per icon in
 * `build/icons/`, named as Lucide names them (`wifi`, `air-vent`). Settings
 * and records store that name; the icon picker (React) chooses it.
 *
 * - `valid( $name )`: whether the icon exists.
 * - `svg( $name, $class )`: the icon inline, coloured by `currentColor`,
 *   hidden from screen readers (the text next to it says what it is).
 * - `url( $name )`: the file, for an `<img>` or a CSS mask.
 */
final class Icons {

	/**
	 * SVG markup already read in this request.
	 *
	 * @var array<string, string>
	 */
	private static array $cache = array();

	/**
	 * Whether an icon name is well formed and the icon exists.
	 *
	 * @param string $name Icon name.
	 * @return bool
	 */
	public static function valid( string $name ): bool {
		return '' !== self::file( $name );
	}

	/**
	 * A clean icon name ('' when it is not an icon).
	 *
	 * @param mixed $name Raw name.
	 * @return string
	 */
	public static function clean( $name ): string {
		$name = strtolower( trim( (string) $name ) );
		return self::valid( $name ) ? $name : '';
	}

	/**
	 * The icon inline ('' when unknown).
	 *
	 * @param string $name  Icon name.
	 * @param string $class CSS classes (size them: `h-4 w-4`).
	 * @return string Safe SVG markup.
	 */
	public static function svg( string $name, string $class = '' ): string {
		$file = self::file( $name );
		if ( '' === $file ) {
			return '';
		}
		if ( ! isset( self::$cache[ $name ] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file shipped with the plugin.
			$svg = (string) file_get_contents( $file );
			// Only the drawing: Lucide icons are paths, circles, rects, lines and polylines.
			self::$cache[ $name ] = (string) wp_kses(
				$svg,
				array(
					'svg'      => array_fill_keys( array( 'xmlns', 'width', 'height', 'viewbox', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin' ), true ),
					'path'     => array_fill_keys( array( 'd' ), true ),
					'circle'   => array_fill_keys( array( 'cx', 'cy', 'r' ), true ),
					'ellipse'  => array_fill_keys( array( 'cx', 'cy', 'rx', 'ry' ), true ),
					'rect'     => array_fill_keys( array( 'x', 'y', 'width', 'height', 'rx', 'ry' ), true ),
					'line'     => array_fill_keys( array( 'x1', 'y1', 'x2', 'y2' ), true ),
					'polyline' => array_fill_keys( array( 'points' ), true ),
					'polygon'  => array_fill_keys( array( 'points' ), true ),
				)
			);
		}
		$attributes = ' aria-hidden="true" focusable="false"' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' );
		return (string) preg_replace( '/<svg\b/', '<svg' . $attributes, self::$cache[ $name ], 1 );
	}

	/**
	 * The icon file's URL ('' when unknown).
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public static function url( string $name ): string {
		return '' !== self::file( $name ) ? RADIUS_HOTEL_BOOKING_BUILD . '/icons/' . $name . '.svg' : '';
	}

	/**
	 * The icon's file path ('' when the name is bad or the icon missing).
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	private static function file( string $name ): string {
		if ( ! preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $name ) || strlen( $name ) > 60 ) {
			return '';
		}
		$file = RADIUS_HOTEL_BOOKING_DIR . 'build/icons/' . $name . '.svg';
		return is_readable( $file ) ? $file : '';
	}
}
