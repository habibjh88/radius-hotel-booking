<?php
/**
 * Handles the creation of a new resource class through WP-CLI.
 *
 * This command generates a new resource class file based on a stub template.
 * The generated class will be placed within the `*/
namespace RadiusTheme\RadiusHotelBooking\Commands;

use WP_CLI;
use WP_CLI_Command;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class MakeResourceCommand
 */
class MakeResourceCommand extends WP_CLI_Command {

	/**
	 * Handles the creation of a resource class file based on the provided arguments.
	 *
	 * This method generates a PHP class file for resource creation by taking a provided class name,
	 * ensures it adheres to a naming convention, validates the existence of necessary stub files,
	 * and creates the class using the stub content. It prevents overwriting existing files and provides
	 * feedback for successful or failed operations.
	 *
	 * @param array $args The arguments provided to invoke the method. The first element should be the resource class name.
	 *
	 * @return void
	 */
	public function __invoke( $args ) {
		$name = $args[0] ?? null;

		if ( ! $name ) {
			WP_CLI::error( 'Please provide a resource class name.' );
			return;
		}

		// Validate naming
		if ( ! preg_match( '/Resource$/', $name ) ) {
			WP_CLI::error( 'Resource class name should end with "Resource". Example: LocationResource' );
			return;
		}

		$modelName = str_replace( 'Resource', '', $name );
		$variable = lcfirst( $modelName );

		$filePath = RADIUS_HOTEL_BOOKING_PATH . "/includes/Resources/{$name}.php";
		$stubPath = RADIUS_HOTEL_BOOKING_PATH . '/resources/stubs/resource.stub';

		if ( file_exists( $filePath ) ) {
			WP_CLI::error( "{$name}.php already exists!" );
			return;
		}

		if ( ! file_exists( $stubPath ) ) {
			WP_CLI::error( 'Resource stub file is missing.' );
			return;
		}

		$stub = file_get_contents( $stubPath );

		$stub = str_replace(
			array( '{{namespace}}', '{{class}}', '{{model}}', '{{variable}}' ),
			array( 'RadiusTheme\\RadiusHotelBooking\\Resources', $name, $modelName, $variable ),
			$stub
		);

		// Ensure directory
		if ( ! is_dir( dirname( $filePath ) ) ) {
			mkdir( dirname( $filePath ), 0755, true );
		}

		file_put_contents( $filePath, $stub );

		WP_CLI::success( "Resource class {$name} created at includes/Resources/{$name}.php" );
	}
}
