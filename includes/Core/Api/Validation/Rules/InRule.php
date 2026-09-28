<?php
/**
 * InRule.php
 * Validates whether a given value exists within a predefined set of allowed values.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class InRule
 *
 * Validates whether a given value exists within a predefined set of allowed values.
 *
 * Example usage:
 * ```php
 * $rule = new InRule(['pending', 'approved', 'rejected']);
 * $isValid = $rule->validate('approved'); // true
 * ```
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules
 */
class InRule {
	/**
	 * The list of allowed values.
	 *
	 * @var array
	 */
	private array $values;

	/**
	 * The validation error message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * InRule constructor.
	 *
	 * @param array  $values  The list of allowed values.
	 * @param string $message The error message returned when validation fails.
	 */
	public function __construct( array $values, string $message = 'Field contains invalid value' ) {
		$this->values  = $values;
		$this->message = $message;
	}

	/**
	 * Validate whether the given value is in the list of allowed values.
	 *
	 * @param mixed $value The value to validate.
	 * @return bool True if the value is allowed, false otherwise.
	 */
	public function validate( $value ): bool {
		return in_array( $value, $this->values, true );
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
