<?php
/**
 * NumericRule
 * Validates that a given value is numeric.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class NumericRule
 *
 * Validates that a given value is numeric.
 * This includes integers, floats, and numeric strings (e.g., "123", "45.6").
 *
 * Example usage:
 * ```php
 * $rule = new NumericRule();
 * $rule->validate(123);    // true
 * $rule->validate('45.6'); // true
 * $rule->validate('abc');  // false
 * ```
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules
 */
class NumericRule {
	/**
	 * The validation error message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * NumericRule constructor.
	 *
	 * @param string $message The error message returned when validation fails.
	 *                        Defaults to "Field must be numeric".
	 */
	public function __construct( string $message = 'Field must be numeric' ) {
		$this->message = $message;
	}

	/**
	 * Validate whether the given value is numeric.
	 *
	 * @param mixed $value The value to validate.
	 * @return bool True if the value is numeric, false otherwise.
	 */
	public function validate( $value ): bool {
		return is_numeric( $value );
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
