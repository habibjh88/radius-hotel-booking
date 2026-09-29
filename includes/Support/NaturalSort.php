<?php
/**
 * Natural-sort keys for room numbers.
 *
 * @package RadiusTheme\RadiusHotelBooking\Support
 */

namespace RadiusTheme\RadiusHotelBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * A key that sorts room numbers the way people read them (feature 6.7):
 * `A1, A2 … A9, A10`, `B2 < B10`, `101 < 1001`. Letters compare case-
 * insensitively; each run of digits is zero-padded to a fixed width, so a
 * plain `ORDER BY number_sort` gives the natural order.
 */
final class NaturalSort {

	const DIGITS = 8;

	/**
	 * The sort key of a label.
	 *
	 * @param string $label Room number, e.g. `A12`.
	 * @return string E.g. `a00000012`, at most 64 characters.
	 */
	public static function key( string $label ): string {
		$label = strtolower( trim( $label ) );
		$key   = preg_replace_callback(
			'/\d+/',
			static fn( $m ) => str_pad( ltrim( $m[0], '0' ) === '' ? '0' : ltrim( $m[0], '0' ), self::DIGITS, '0', STR_PAD_LEFT ),
			$label
		);
		return substr( (string) $key, 0, 64 );
	}

	/**
	 * Sort a list of labels naturally (for previews that are not in the database).
	 *
	 * @param string[] $labels Labels.
	 * @return string[]
	 */
	public static function sort( array $labels ): array {
		usort(
			$labels,
			static function ( $a, $b ) {
				$by_key = strcmp( self::key( (string) $a ), self::key( (string) $b ) );
				return 0 !== $by_key ? $by_key : strcmp( (string) $a, (string) $b );
			}
		);
		return $labels;
	}
}
