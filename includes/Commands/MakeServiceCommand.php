<?php

namespace RadiusTheme\RadiusHotelBooking\Commands;

use WP_CLI;
use WP_CLI_Command;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Command for creating a service class file based on a predefined stub template.
 *
 * This command ensures the generation of a service class that adheres to expected naming conventions and file paths.
 * It checks for the presence of required input and prevents overwriting existing files. If required stub files
 * are missing, appropriate error messages are displayed. The generated service class includes a namespace and
 * replaces placeholders in the stub file with the provided service name and other derived values.
 */
class MakeServiceCommand extends WP_CLI_Command {

	/**
	 * Handles the creation of a service class file based on a stub template.
	 *
	 * @param array $args An array of arguments where the first element specifies the service class name.
	 *
	 * @return void
	 */
	public function __invoke( $args ) {
		$name = $args[0] ?? null;

		if ( ! $name ) {
			WP_CLI::error( 'Please provide a service class name.' );
			return;
		}

		if ( ! preg_match( '/Service$/', $name ) ) {
			WP_CLI::error( 'Service class name should end with "Service".' );
			return;
		}

		$model     = str_replace( 'Service', '', $name );
		$variable  = lcfirst( $model );
		$classPath = RADIUS_HOTEL_BOOKING_PATH . "/includes/Services/{$name}.php";
		$stubPath  = RADIUS_HOTEL_BOOKING_PATH . '/resources/stubs/service.stub';

		if ( file_exists( $classPath ) ) {
			WP_CLI::error( "{$name}.php already exists!" );
			return;
		}

		if ( ! file_exists( $stubPath ) ) {
			WP_CLI::error( 'Missing service.stub file.' );
			return;
		}

		$stub = file_get_contents( $stubPath );

		$stub = str_replace(
			array( '{{namespace}}', '{{class}}', '{{model}}', '{{variable}}' ),
			array( 'RadiusTheme\\RadiusHotelBooking\\Services', $name, $model, $variable ),
			$stub
		);

		if ( ! is_dir( dirname( $classPath ) ) ) {
			mkdir( dirname( $classPath ), 0755, true );
		}

		file_put_contents( $classPath, $stub );

		WP_CLI::success( "Service class {$name} created at includes/Services/{$name}.php" );
	}
}
