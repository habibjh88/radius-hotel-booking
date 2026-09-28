<?php
/**
 * Enhanced ApiValidator with Better Nested Validation Support
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Enhanced API Validator that supports nested validation
 */
class ApiValidator {
	/**
	 * Array of validation rules for each field.
	 *
	 * @var array
	 */
	private array $rules = array();

	/**
	 * Array to hold validation errors.
	 *
	 * @var array
	 */
	private array $errors = array();

	/**
	 * Constructor method to initialize the validator with rules.
	 *
	 * @param array $rules An associative array where keys are field names and values are arrays of validation rules.
	 */
	public function __construct( array $rules = array() ) {
		$this->rules = $rules;
	}

	/**
	 * Validates the provided data against the defined rules.
	 *
	 * @param array $data The data to be validated.
	 * @return bool Returns true if validation passes, false otherwise.
	 */
	public function validate( array $data ): bool {
		$this->errors = array();

		foreach ( $this->rules as $field => $fieldRules ) {
			$this->validateField( $field, $data, $fieldRules );
		}

		return empty( $this->errors );
	}

	/**
	 * Returns the validation errors.
	 *
	 * @return array
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	/**
	 * Validates a field (supports nested validation with dot notation).
	 *
	 * @param string $field The field path (e.g., 'services.*.name' or 'services.0.price').
	 * @param array  $data The complete data array.
	 * @param array  $rules The validation rules.
	 */
	private function validateField( string $field, array $data, array $rules ): void {
		$values          = $this->getFieldValues( $field, $data );
		$hasRequiredRule = $this->hasRequiredRule( $rules );

		// If no values found at all and field is required, it's an error
		if ( empty( $values ) && $hasRequiredRule ) {
			$this->addError( $field, 'Field is required' );
			return;
		}

		// If no values found and field is not required, skip validation
		if ( empty( $values ) && ! $hasRequiredRule ) {
			return;
		}

		// Process each value (including null values for required field checking)
		foreach ( $values as $path => $value ) {
			// If value is null (field doesn't exist) and field is required, it's an error
			if ( $value === null && $hasRequiredRule ) {
				$this->addError( $path, 'Field is required' );
				continue;
			}

			// If value is null and field is not required, skip validation
			if ( $value === null && ! $hasRequiredRule ) {
				continue;
			}

			// Validate existing values
			$this->validateValue( $path, $value, $rules );
		}
	}

	/**
	 * Get values from data using dot notation field path.
	 *
	 * @param string $field The field path.
	 * @param array  $data The data array.
	 * @return array Array of path => value pairs.
	 */
	private function getFieldValues( string $field, array $data ): array {
		$values = array();
		$this->extractValues( $field, $data, '', $values );
		return $values;
	}

	/**
	 * Recursively extract values based on field path.
	 *
	 * @param string $field The field path.
	 * @param mixed  $data Current data.
	 * @param string $currentPath Current path being processed.
	 * @param array  $values Reference to values array.
	 */
	private function extractValues( string $field, $data, string $currentPath, array &$values ): void {
		$parts     = explode( '.', $field, 2 );
		$key       = $parts[0];
		$remaining = isset( $parts[1] ) ? $parts[1] : '';

		if ( $key === '*' ) {
			// Handle wildcard for arrays
			if ( is_array( $data ) ) {
				foreach ( $data as $index => $item ) {
					$newPath = $currentPath ? $currentPath . '.' . $index : (string) $index;

					if ( $remaining ) {
						$this->extractValues( $remaining, $item, $newPath, $values );
					} else {
						$values[ $newPath ] = $item;
					}
				}
			}
		} else {
			// Handle specific key
			if ( is_array( $data ) && array_key_exists( $key, $data ) ) {
				$newPath = $currentPath ? $currentPath . '.' . $key : $key;

				if ( $remaining ) {
					$this->extractValues( $remaining, $data[ $key ], $newPath, $values );
				} else {
					$values[ $newPath ] = $data[ $key ];
				}
			} else {
				// Field doesn't exist in data - always add null for missing fields
				$newPath = $currentPath ? $currentPath . '.' . $key : $key;
				if ( ! $remaining ) {
					$values[ $newPath ] = null;
				} else {
					// Continue processing the remaining path even if current key doesn't exist
					$this->extractValues( $remaining, null, $newPath, $values );
				}
			}
		}
	}

	/**
	 * Validate a single value against rules.
	 *
	 * @param string $path The path of the field.
	 * @param mixed  $value The value to validate.
	 * @param array  $rules The validation rules.
	 */
	private function validateValue( string $path, $value, array $rules ): void {
		foreach ( $rules as $rule ) {
			if ( is_object( $rule ) && method_exists( $rule, 'validate' ) ) {
				if ( ! $rule->validate( $value ) ) {
					$this->addError( $path, $rule->getMessage() );
				}
			} elseif ( is_callable( $rule ) ) {
				$result = $rule( $value );
				if ( $result !== true ) {
					$this->addError( $path, is_string( $result ) ? $result : 'Validation failed' );
				}
			}
		}
	}

	/**
	 * Add an error message.
	 *
	 * @param string $field The field path.
	 * @param string $message The error message.
	 */
	private function addError( string $field, string $message ): void {
		if ( ! isset( $this->errors[ $field ] ) ) {
			$this->errors[ $field ] = array();
		}
		$this->errors[ $field ][] = $message;
	}

	/**
	 * Check if field has required rule.
	 *
	 * @param array $rules The validation rules.
	 * @return bool
	 */
	private function hasRequiredRule( array $rules ): bool {
		foreach ( $rules as $rule ) {
			if ( is_object( $rule ) && get_class( $rule ) === 'RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules\RequiredRule' ) {
				return true;
			}
		}
		return false;
	}
}
