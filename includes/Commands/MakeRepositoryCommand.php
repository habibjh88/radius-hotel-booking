<?php
/**
 * WP CLI Command for generating repository class files.
 *
 * The `MakeRepositoryCommand` class provides a WP CLI command to create repository class files
 * based on a provided repository name. The command ensures proper structure and formatting by
 * using a predefined stub file.
 */
namespace RadiusTheme\RadiusHotelBooking\Commands;

use WP_CLI;
use WP_CLI_Command;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class MakeRepositoryCommand
 *
 * This class provides a WP-CLI command to generate a new repository class file.
 * The repository name provided as an argument must end with 'Repository'.
 */
class MakeRepositoryCommand extends WP_CLI_Command {

	/**
	 * Handles the creation of a new repository class file based on a given name.
	 *
	 * @param array $args The arguments passed to the command. The first argument should be the repository name.
	 *                    Example: 'UserRepository'. The name must end with 'Repository'.
	 *
	 * @return void
	 */
	public function __invoke( $args ) {
		$name = $args[0] ?? null;

		if ( ! $name ) {
			WP_CLI::error( 'Please provide a repository name.' );
			return;
		}

		// Validate name ends with 'Repository'
		if ( ! preg_match( '/Repository$/', $name ) ) {
			WP_CLI::error( 'Repository class name should end with "Repository". Example: UserRepository' );
			return;
		}

		// File path where repository will be created
		$filePath = RADIUS_HOTEL_BOOKING_PATH . '/includes/Repositories/' . $name . '.php';

		if ( file_exists( $filePath ) ) {
			WP_CLI::error( "Repository file {$name}.php already exists." );
			return;
		}

		// Load stub template
		$stubPath = RADIUS_HOTEL_BOOKING_PATH . '/resources/stubs/repository.stub';

		if ( ! file_exists( $stubPath ) ) {
			WP_CLI::error( 'Repository stub file not found.' );
			return;
		}

		$stub = file_get_contents( $stubPath );

		// Replace placeholders in stub
		$stub = str_replace(
			array( '{{namespace}}', '{{class}}', '{{model}}' ),
			array( 'RadiusTheme\\RadiusHotelBooking\\Repositories', $name, str_replace( 'Repository', '', $name ) ),
			$stub
		);

		// Create directory if not exists
		if ( ! is_dir( dirname( $filePath ) ) ) {
			mkdir( dirname( $filePath ), 0755, true );
		}

		// Write file
		file_put_contents( $filePath, $stub );

		WP_CLI::success( "Repository class {$name} created successfully!" );
	}
}
