<?php

/**
 * Validation rule to check if a value is an array.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Represents a validation rule for checking if a given value is an array.
 */
class ArrayRule {
	/**
	 * The message to be displayed if the validation fails.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Constructor method to initialize the class with a message.
	 *
	 * @param string $message The message to be set, defaults to 'Field must be an array'.
	 *
	 * @return void
	 */
	public function __construct( string $message = 'Field must be an array' ) {
		$this->message = $message;
	}

	/**
	 * Validates whether the given value is an array.
	 *
	 * @param mixed $value The value to be validated.
	 *
	 * @return bool Returns true if the value is an array, false otherwise.
	 */
	public function validate( $value ): bool {
		return is_array( $value );
	}

	/**
	 * Retrieves the message.
	 *
	 * @return string The message stored in the property.
	 */
	public function getMessage(): string {
		return $this->message;
	}
}
