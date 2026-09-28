<?php

namespace RadiusTheme\RadiusHotelBooking\Commands;

use WP_CLI;
use WP_CLI_Command;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class for creating new controller files via WP-CLI.
 *
 * The MakeControllerCommand class provides functionality to generate a new controller file
 * based on provided arguments. It ensures that the controller class name follows specific
 * naming conventions and handles file generation using predefined stubs.
 */
class MakeControllerCommand extends WP_CLI_Command {

	/**
	 * Handles the invocation of the class to create a new controller file based on the provided arguments.
	 *
	 * @param array $args The arguments passed to the command. The first element should be the name of the controller class.
	 *                     The controller name must end with "Controller".
	 *
	 * @return void
	 */
	public function __invoke( $args ) {
		$name = $args[0] ?? null;

		if ( ! $name ) {
			WP_CLI::error( 'Please provide a controller class name.' );
			return;
		}

		if ( ! preg_match( '/Controller$/', $name ) ) {
			WP_CLI::error( 'Controller name must end with "Controller".' );
			return;
		}

		$model     = str_replace( 'Controller', '', $name );
		$classPath = RADIUS_HOTEL_BOOKING_PATH . "/includes/Controllers/{$name}.php";
		$stubPath  = RADIUS_HOTEL_BOOKING_PATH . '/resources/stubs/controller.stub';

		if ( file_exists( $classPath ) ) {
			WP_CLI::error( "{$name}.php already exists!" );
			return;
		}

		if ( ! file_exists( $stubPath ) ) {
			WP_CLI::error( 'Missing controller.stub file.' );
			return;
		}

		$stub = file_get_contents( $stubPath );

		$stub = str_replace(
			array( '{{class}}', '{{model}}' ),
			array( $name, $model ),
			$stub
		);

		if ( ! is_dir( dirname( $classPath ) ) ) {
			mkdir( dirname( $classPath ), 0755, true );
		}

		file_put_contents( $classPath, $stub );

		WP_CLI::success( "Controller {$name} created at includes/Controllers/{$name}.php" );
	}
}
