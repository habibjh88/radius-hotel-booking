<?php
/**
 * "email" validation rule.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

use RadiusTheme\RadiusHotelBooking\Core\Api\Validation\ValidationRule;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Represents a validation rule that checks if a given value is a valid email address.
 *
 * This rule uses PHP's built-in filter_var function with the FILTER_VALIDATE_EMAIL filter
 * to determine whether the provided value is a syntactically valid email address.
 *
 * Implements the ValidationRule interface, providing methods to validate the input
 * and to retrieve an error message for invalid inputs.
 */
class EmailRule implements ValidationRule {
	/**
	 * Validates whether the given value is a properly formatted email address.
	 *
	 * @param mixed $value The value to be validated.
	 *
	 * @return bool Returns true if the value is a valid email address, false otherwise.
	 */
	public function validate( $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_EMAIL ) !== false;
	}

	/**
	 * Retrieves a message indicating a validation requirement.
	 *
	 * @return string The validation message.
	 */
	public function getMessage(): string {
		return 'Must be a valid email address';
	}
}
