<?php
/**
 * WP-CLI command that scaffolds a whole module.
 *
 * @package RadiusTheme\RadiusHotelBooking\Commands
 */

namespace RadiusTheme\RadiusHotelBooking\Commands;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Core\CommandRegistry;
use WP_CLI;
use WP_CLI_Command;

/**
 * Generates a model, repository, resource, service and controller in one go.
 */
class MakeModuleCommand extends WP_CLI_Command {

	/**
	 * Run the command.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Singular, StudlyCase module name, e.g. Product.
	 *
	 * ## EXAMPLES
	 *
	 *     wp radius-hotel-booking artisan make:module Product
	 *
	 * @param array $args       Positional arguments; the first is the module name.
	 * @param array $assoc_args Associative arguments (unused).
	 *
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$prefix = CommandRegistry::COMMAND_PREFIX;
		$name   = $args[0] ?? null;

		if ( ! $name ) {
			WP_CLI::error( "Module name is required. Example: wp {$prefix} make:module Product" );
		}

		$commands = array(
			"make:model {$name}",
			"make:repository {$name}Repository",
			"make:resource {$name}Resource",
			"make:service {$name}Service",
			"make:controller {$name}Controller",
		);

		$failed = array();

		foreach ( $commands as $command ) {
			WP_CLI::log( "Running: wp {$prefix} {$command}" );

			if ( ! $this->runSubcommand( "{$prefix} {$command}" ) ) {
				$failed[] = $command;
			}
		}

		if ( $failed ) {
			WP_CLI::error(
				sprintf(
					"Module '%s' was only partially created. Failed: %s",
					$name,
					implode( ', ', $failed )
				)
			);
		}

		WP_CLI::success( "Module '{$name}' created." );
	}

	/**
	 * Run one generator in this same process.
	 *
	 * `launch => false` keeps WordPress loaded instead of spawning a new `wp`
	 * (which would require the binary on PATH), and `exit_error => false` lets
	 * one failing generator be reported without aborting the rest.
	 *
	 * @param string $command Full command, without the leading `wp`.
	 *
	 * @return bool True when the sub-command succeeded.
	 */
	private function runSubcommand( string $command ): bool {
		$result = WP_CLI::runcommand(
			$command,
			array(
				'launch'     => false,
				'exit_error' => false,
				'return'     => 'all',
			)
		);

		foreach ( array_filter( array( $result->stdout, $result->stderr ) ) as $stream ) {
			foreach ( explode( "\n", trim( $stream ) ) as $line ) {
				if ( '' !== trim( $line ) ) {
					WP_CLI::log( '   ' . $line );
				}
			}
		}

		return 0 === $result->return_code;
	}
}
