<?php
/**
 * Validation rule to check if a value is a string.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class StringRule
 *
 * Validates that a given value is a string.
 * This ensures the input type is a string, even if it's empty.
 *
 * Example usage:
 * ```php
 * $rule = new StringRule();
 * $rule->validate('Hello'); // true
 * $rule->validate('');      // true (empty string is still a string)
 * $rule->validate(123);     // false
 * ```
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules
 */
class StringRule {
	/**
	 * The validation error message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * StringRule constructor.
	 *
	 * @param string $message The error message returned when validation fails.
	 *                        Defaults to "Field must be a string".
	 */
	public function __construct( string $message = 'Field must be a string' ) {
		$this->message = $message;
	}

	/**
	 * Validate whether the given value is a string.
	 *
	 * @param mixed $value The value to validate.
	 * @return bool True if the value is a string, false otherwise.
	 */
	public function validate( $value ): bool {
		return is_string( $value );
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
