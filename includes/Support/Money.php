<?php
/**
 * Money helper.
 *
 * @package RadiusTheme\RadiusHotelBooking\Support
 */

namespace RadiusTheme\RadiusHotelBooking\Support;

use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Formatting and arithmetic for amounts in the site's currency.
 *
 * Amounts are stored as DECIMAL(12,2) and handled as floats only at the
 * edges. All arithmetic goes through integer minor units (cents; for a
 * currency with no minor unit such as XOF, whole francs), so sums never drift
 * and a zero-decimal currency is always whole.
 *
 * The currency comes from Settings → General (`currencyCode`, `currencySymbol`,
 * `currencyPosition`, `thousandSeparator`, `decimalSeparator`, `decimals`).
 * JS twin: `formatMoney()` in src/lib/format.js, fed by config().
 */
class Money {

	/**
	 * Symbol positions.
	 */
	const POSITIONS = array( 'left', 'right', 'left_space', 'right_space' );

	/**
	 * Currency configuration, cached per request.
	 *
	 * @var array|null
	 */
	private static ?array $config = null;

	/**
	 * The currency configuration, normalised.
	 *
	 * @return array{code:string,symbol:string,position:string,thousand:string,decimal:string,decimals:int}
	 */
	public static function config(): array {
		if ( null !== self::$config ) {
			return self::$config;
		}

		$general  = SettingsHelper::get_setting( 'general' );
		$general  = is_array( $general ) ? $general : array();
		$position = (string) ( $general['currencyPosition'] ?? 'left' );

		self::$config = array(
			'code'     => strtoupper( (string) ( $general['currencyCode'] ?? 'USD' ) ),
			'symbol'   => (string) ( $general['currencySymbol'] ?? '$' ),
			'position' => in_array( $position, self::POSITIONS, true ) ? $position : 'left',
			'thousand' => (string) ( $general['thousandSeparator'] ?? ',' ),
			'decimal'  => (string) ( $general['decimalSeparator'] ?? '.' ),
			'decimals' => max( 0, min( 4, (int) ( $general['decimals'] ?? 2 ) ) ),
		);

		return self::$config;
	}

	/**
	 * Forget the cached configuration (after the settings change).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$config = null;
	}

	/**
	 * Number of decimals of the site currency.
	 *
	 * @return int
	 */
	public static function decimals(): int {
		return self::config()['decimals'];
	}

	/**
	 * Amount → integer minor units, rounding half away from zero.
	 *
	 * @param float|int|string $amount Amount.
	 * @return int
	 */
	public static function to_minor( $amount ): int {
		$decimals = self::decimals();

		// Round at the currency precision first: 1.005 * 100 is 100.4999… in
		// binary floating point, so scaling first would lose the half cent.
		return (int) round( round( (float) $amount, $decimals, PHP_ROUND_HALF_UP ) * ( 10 ** $decimals ) );
	}

	/**
	 * Integer minor units → amount.
	 *
	 * @param int $minor Minor units.
	 * @return float
	 */
	public static function from_minor( int $minor ): float {
		return round( $minor / ( 10 ** self::decimals() ), self::decimals() );
	}

	/**
	 * Round an amount to the currency's precision (XOF → whole francs).
	 *
	 * @param float|int|string $amount Amount.
	 * @return float
	 */
	public static function round( $amount ): float {
		return self::from_minor( self::to_minor( $amount ) );
	}

	/**
	 * Sum amounts exactly.
	 *
	 * @param array<float|int|string> $amounts Amounts.
	 * @return float
	 */
	public static function sum( array $amounts ): float {
		$total = 0;
		foreach ( $amounts as $amount ) {
			$total += self::to_minor( $amount );
		}
		return self::from_minor( $total );
	}

	/**
	 * Whether two amounts are equal at the currency's precision. Never compare
	 * money floats with `==`.
	 *
	 * @param float|int|string $a First amount.
	 * @param float|int|string $b Second amount.
	 * @return bool
	 */
	public static function equals( $a, $b ): bool {
		return self::to_minor( $a ) === self::to_minor( $b );
	}

	/**
	 * Format an amount for display: `$1,250.50`, `15 000 CFA`.
	 *
	 * @param float|int|string $amount   Amount.
	 * @param bool             $with_symbol Include the currency symbol.
	 * @return string
	 */
	public static function format( $amount, bool $with_symbol = true ): string {
		$config = self::config();
		$minor  = self::to_minor( $amount );
		$number = number_format(
			abs( $minor ) / ( 10 ** $config['decimals'] ),
			$config['decimals'],
			$config['decimal'],
			$config['thousand']
		);
		$sign   = $minor < 0 ? '-' : '';

		if ( ! $with_symbol || '' === $config['symbol'] ) {
			return $sign . $number;
		}

		switch ( $config['position'] ) {
			case 'right':
				return $sign . $number . $config['symbol'];
			case 'right_space':
				return $sign . $number . ' ' . $config['symbol'];
			case 'left_space':
				return $sign . $config['symbol'] . ' ' . $number;
			default:
				return $sign . $config['symbol'] . $number;
		}
	}
}
