<?php
/**
 * "integer" validation rule.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

use RadiusTheme\RadiusHotelBooking\Core\Api\Validation\ValidationRule;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/Api/Validation/Rules/IntegerRule.php
 */
class IntegerRule implements ValidationRule {
	/**
	 * Validates if the given value is numeric and can be interpreted as an integer.
	 *
	 * @param mixed $value The value to be validated.
	 *
	 * @return bool Returns true if the value is numeric and an integer, otherwise false.
	 */
	public function validate( $value ): bool {
		return is_numeric( $value ) && is_int( $value + 0 );
	}

	/**
	 * Returns a message indicating that the value must be an integer.
	 *
	 * @return string Returns a message indicating that the value must be an integer.
	 */
	public function getMessage(): string {
		return 'Must be an integer';
	}
}
