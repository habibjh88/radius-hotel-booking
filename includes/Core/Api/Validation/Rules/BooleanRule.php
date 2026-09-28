<?php

/**
 * Represents a rule to validate whether a given value is a boolean
 * or can be interpreted as a boolean.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Provides validation for boolean values.
 */
class BooleanRule {
	/**
	 * The message to display when the validation fails.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Constructor method to initialize the object with a custom message.
	 *
	 * @param string $message The custom message to initialize the object with. Defaults to 'Field must be a boolean'.
	 *
	 * @return void
	 */
	public function __construct( string $message = 'Field must be a boolean' ) {
		$this->message = $message;
	}

	/**
	 * Validates if the given value is a boolean or a boolean-like value.
	 *
	 * @param mixed $value The value to be validated.
	 *
	 * @return bool Returns true if the value is a boolean or a recognized boolean-like value, otherwise false.
	 */
	public function validate( $value ): bool {
		return is_bool( $value ) || in_array( $value, array( '0', '1', 0, 1, 'true', 'false' ), true );
	}

	/**
	 * Retrieves the message value.
	 *
	 * @return string The message string.
	 */
	public function getMessage(): string {
		return $this->message;
	}
}
