<?php

/**
 * MinRule.php
 * Validates that a given value meets or exceeds a specified minimum.
 * Works for numbers, strings (by length), and arrays (by element count).
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class MinRule
 *
 * Validates that a given value meets or exceeds a specified minimum.
 * Works for numbers, strings (by length), and arrays (by element count).
 *
 * Example usage:
 * ```php
 * $rule = new MinRule(3);
 * $rule->validate(5);          // true  → numeric check
 * $rule->validate('test');     // true  → string length check
 * $rule->validate([1, 2, 3]);  // true  → array count check
 * ```
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules
 */
class MinRule {
	/**
	 * The minimum allowed value, length, or count.
	 *
	 * @var int
	 */
	private int $min;

	/**
	 * The validation error message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * MinRule constructor.
	 *
	 * @param int         $min     The minimum allowed value, string length, or array size.
	 * @param string|null $message Optional custom error message. Defaults to
	 *                             "Field must be at least {min}".
	 */
	public function __construct( int $min, ?string $message = null ) {
		$this->min     = $min;
		$this->message = $message ?? "Field must be at least {$min}";
	}

	/**
	 * Validate whether the given value meets the minimum requirement.
	 *
	 * - For numeric values, compares the numeric value.
	 * - For strings, checks length using `strlen()`.
	 * - For arrays, checks element count using `count()`.
	 *
	 * @param mixed $value The value to validate.
	 * @return bool True if the value meets or exceeds the minimum, false otherwise.
	 */
	public function validate( $value ): bool {
		if ( is_numeric( $value ) ) {
			return $value >= $this->min;
		}

		if ( is_string( $value ) ) {
			return strlen( $value ) >= $this->min;
		}

		if ( is_array( $value ) ) {
			return count( $value ) >= $this->min;
		}

		return false;
	}

	/**
	 * Get the validation error message.
	 *
	 * @return string The error message.
	 */
	public function getMessage(): string {
		return $this->message;
	}
}
