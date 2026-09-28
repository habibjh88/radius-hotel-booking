<?php
/**
 * DateRule
 * Validates whether a given value matches a specific date format.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class DateRule
 *
 * Validates whether a given value matches a specific date format.
 *
 * Example usage:
 * ```php
 * $rule = new DateRule('Y-m-d');
 * $isValid = $rule->validate('2025-11-03'); // true
 * ```
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules
 */
class DateRule {
	/**
	 * The expected date format.
	 *
	 * @var string
	 */
	private string $format;

	/**
	 * The validation error message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * DateRule constructor.
	 *
	 * @param string $format  The expected date format (default: 'Y-m-d').
	 * @param string $message The error message returned when validation fails.
	 */
	public function __construct( string $format = 'Y-m-d', string $message = 'Field must be a valid date' ) {
		$this->format  = $format;
		$this->message = $message;
	}

	/**
	 * Validate whether the given value matches the expected date format.
	 *
	 * @param mixed $value The value to validate.
	 * @return bool True if valid date, false otherwise.
	 */
	public function validate( $value ): bool {
		$date = \DateTime::createFromFormat( $this->format, $value );
		return $date && $date->format( $this->format ) === $value;
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
