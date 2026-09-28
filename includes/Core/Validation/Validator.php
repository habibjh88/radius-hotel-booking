<?php
/**
 * Standalone value validator used outside the REST layer.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Validation
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/Validation/Validator.php
 * Simple validation system for models
 */
class Validator {

	/**
	 * An associative array of validation rules.
	 * Each key represents a field name, and the value is a string or array of rules to apply to that field.
	 *
	 * @var array<string, string|array>
	 */
	private array $rules = array();
	/**
	 * An associative array to hold validation errors.
	 * Each key represents a field name, and the value is an array of error messages for that field.
	 *
	 * @var array<string, array<string>>
	 */
	private array $errors = array();

	/**
	 * Constructor method to initialize the rules.
	 *
	 * @param array $rules An associative array of validation rules.
	 *
	 * @return void
	 */
	public function __construct( array $rules ) {
		$this->rules = $rules;
	}

	/**
	 * Validates the provided data against predefined validation rules.
	 *
	 * @param array $data An associative array where keys correspond to field names and values are the data to be validated.
	 *
	 * @return bool Returns true if all data passes validation. Returns false if there are validation errors.
	 */
	public function validate( array $data ): bool {
		$this->errors = array();

		foreach ( $this->rules as $field => $rules ) {
			$fieldRules = is_string( $rules ) ? explode( '|', $rules ) : $rules;
			$this->validateField( $field, $data[ $field ] ?? null, $fieldRules );
		}

		return empty( $this->errors );
	}

	/**
	 * Retrieves the list of validation errors.
	 *
	 * @return array An associative array containing validation errors, where the keys represent the fields and the values are arrays of error messages for each field.
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	/**
	 * Validates a field against a set of rules.
	 *
	 * @param string $field The name of the field being validated.
	 * @param mixed $value The value of the field to validate.
	 * @param array $rules An array of rules to validate the field against.
	 *
	 * @return void
	 */
	private function validateField( string $field, $value, array $rules ): void {
		foreach ( $rules as $rule ) {
			$this->applyRule( $field, $value, $rule );
		}
	}

	/**
	 * Applies a validation rule to a given field and value.
	 *
	 * @param string $field The name of the field being validated.
	 * @param mixed $value The value of the field to validate.
	 * @param string $rule The validation rule to apply.
	 *
	 * @return void
	 */
	private function applyRule( string $field, $value, string $rule ): void {
		switch ( $rule ) {
			case 'required':
				if ( empty( $value ) ) {
					$this->errors[ $field ][] = "The {$field} field is required.";
				}
				break;
			case 'string':
				if ( ! is_string( $value ) ) {
					$this->errors[ $field ][] = "The {$field} field must be a string.";
				}
				break;
			case 'integer':
				if ( ! is_int( $value ) && ! ctype_digit( $value ) ) {
					$this->errors[ $field ][] = "The {$field} field must be an integer.";
				}
				break;
			case 'email':
				if ( ! filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
					$this->errors[ $field ][] = "The {$field} field must be a valid email address.";
				}
				break;
		}

		// Handle max:n rule
		if ( strpos( $rule, 'max:' ) === 0 ) {
			$max = (int) substr( $rule, 4 );
			if ( ! is_null( $value ) && strlen( (string) $value ) > $max ) {
				$this->errors[ $field ][] = "The {$field} field may not be greater than {$max} characters.";
			}
		}
	}
}
