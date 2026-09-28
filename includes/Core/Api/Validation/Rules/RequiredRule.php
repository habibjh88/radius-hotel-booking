<?php
/**
 * "required" validation rule.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

use RadiusTheme\RadiusHotelBooking\Core\Api\Validation\ValidationRule;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/Api/Validation/Rules/RequiredRule.php
 */
class RequiredRule implements ValidationRule {
	/**
	 * Validates the provided value.
	 *
	 * @param mixed $value The value to be validated.
	 *
	 * @return bool Returns true if the value is not empty, otherwise false.
	 */
	public function validate( $value ): bool {
		return ! empty( $value );
	}

	/**
	 * Returns the message indicating that this field is required.
	 *
	 * @return string Returns the message indicating that this field is required.
	 */
	public function getMessage(): string {
		return 'This field is required';
	}
}
