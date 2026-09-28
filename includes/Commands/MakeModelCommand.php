<?php

/**
 * WP-CLI command for generating model class files.
 */
namespace RadiusTheme\RadiusHotelBooking\Commands;

use WP_CLI;
use WP_CLI_Command;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * WP-CLI command for generating model class files.
 */
class MakeModelCommand extends WP_CLI_Command {

	/**
	 * Handles the invocation of the class to generate and create a new model file based on a given name.
	 *
	 * @param array $args The arguments passed to the method. The first element should contain the model name.
	 *
	 * @return void
	 */
	public function __invoke( $args ) {
		$name = $args[0] ?? null;

		if ( empty( $name ) ) {
			WP_CLI::error( 'Please provide a model name.' );
			return;
		}

		$className = $this->studlyCase( $name );
		$fileName  = $className . '.php';
		$modelsDir = RADIUS_HOTEL_BOOKING_PATH . '/includes/Models';
		$path      = $modelsDir . '/' . $fileName;

		if ( file_exists( $path ) ) {
			WP_CLI::error( "Model '{$className}' already exists." );
			return;
		}

		// Ensure the models directory exists
		if ( ! is_dir( $modelsDir ) && ! mkdir( $modelsDir, 0755, true ) ) {
			WP_CLI::error( "Failed to create directory: {$modelsDir}" );
			return;
		}

		// Load stub file
		$stubPath = RADIUS_HOTEL_BOOKING_PATH . '/resources/stubs/model.stub';

		if ( ! file_exists( $stubPath ) ) {
			WP_CLI::error( 'Model stub file is missing at /stubs/model.stub' );
			return;
		}

		$stub = file_get_contents( $stubPath );

		// Replace placeholders
		$replaced = str_replace(
			array(
				'{{ class }}',
				'{{ table }}',
			),
			array(
				$className,
				$this->snakeCase( $name ) . 's',
			),
			$stub
		);

		// Save generated model file
		if ( file_put_contents( $path, $replaced ) === false ) {
			WP_CLI::error( "Failed to write model file to {$path}" );
			return;
		}

		WP_CLI::success( "Model '{$className}' created successfully at includes/Models/{$fileName}" );
	}

	/**
	 * Studly cases a given string.
	 * Converts a string to StudlyCase.
	 *
	 * @param string $value The input string to be converted.
	 * @return string The converted string in StudlyCase format.
	 */
	private function studlyCase( string $value ): string {
		$value = ucwords( str_replace( array( '-', '_' ), ' ', $value ) );
		return str_replace( ' ', '', $value );
	}

	/**
	 * Converts a given string to snake_case.
	 *
	 * @param string $value The input string to be converted.
	 *
	 * @return string The converted string in snake_case format.
	 */
	private function snakeCase( string $value ): string {
		$value = preg_replace( '/\s+/u', '', $value );
		$value = preg_replace( '/(.)(?=[A-Z])/u', '$1_', $value );
		return strtolower( $value );
	}
}
