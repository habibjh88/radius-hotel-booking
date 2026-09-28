<?php

/**
 * MaxRule.php
 * Validates that a given value does not exceed a specified maximum.
 * Works for numbers, strings (by length), and arrays (by element count).
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class MaxRule
 *
 * Validates that a given value does not exceed a specified maximum.
 * Works for numbers, strings (by length), and arrays (by element count).
 *
 * Example usage:
 * ```php
 * $rule = new MaxRule(10);
 * $rule->validate(8);         // true  → numeric check
 * $rule->validate('testing'); // true  → string length check
 * $rule->validate([1,2,3]);   // true  → array count check
 * ```
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules
 */
class MaxRule {
	/**
	 * The maximum allowed value, length, or count.
	 *
	 * @var int
	 */
	private int $max;

	/**
	 * The validation error message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * MaxRule constructor.
	 *
	 * @param int         $max     The maximum allowed value, string length, or array size.
	 * @param string|null $message Optional custom error message. Defaults to
	 *                             "Field must not exceed {max}".
	 */
	public function __construct( int $max, ?string $message = null ) {
		$this->max     = $max;
		$this->message = $message ?? "Field must not exceed {$max}";
	}

	/**
	 * Validate whether the given value does not exceed the maximum.
	 *
	 * - For numeric values, compares the numeric value.
	 * - For strings, checks length using `strlen()`.
	 * - For arrays, checks element count using `count()`.
	 *
	 * @param mixed $value The value to validate.
	 * @return bool True if the value does not exceed the maximum, false otherwise.
	 */
	public function validate( $value ): bool {
		if ( is_numeric( $value ) ) {
			return $value <= $this->max;
		}

		if ( is_string( $value ) ) {
			return strlen( $value ) <= $this->max;
		}

		if ( is_array( $value ) ) {
			return count( $value ) <= $this->max;
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
