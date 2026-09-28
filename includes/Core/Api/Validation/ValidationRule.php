<?php
/**
 * Base class for a single validation rule.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/Api/Validation/ValidationRule.php
 * Base validation rule interface
 */
interface ValidationRule {

	/**
	 * Validates the given value against the rule.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @return bool True if the value is valid, false otherwise.
	 */
	public function validate( $value ): bool;

	/**
	 * Returns the error message if validation fails.
	 *
	 * @return string The error message to be returned if validation fails.
	 */
	public function getMessage(): string;
}
