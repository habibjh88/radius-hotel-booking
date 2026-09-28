<?php
/**
 * Command Registry for WP-CLI
 *
 * This class provides a dynamic way to register and manage custom WP-CLI commands
 * for the Radius Hotel Booking plugin. It allows adding, removing, and listing commands,
 * while ensuring they are only registered in a WP-CLI environment.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core
 */

namespace RadiusTheme\RadiusHotelBooking\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * WP-CLI Command Registry
 *
 * Handles dynamic registration of artisan commands for WP-CLI environment.
 * Provides a centralized way to manage and register custom WP-CLI commands
 * with error handling and debugging capabilities.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core
 * @author  RadiusTheme
 * @version 1.0.0
 * @since   1.0.0
 *
 * @example
 * ```php
 * $registry = new CommandRegistry();
 * $registry->addCommand('make:helper', 'MakeHelperCommand')
 *          ->init();
 * ```
 */
class CommandRegistry {

	/**
	 * Base namespace for command classes
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private const BASE_NAMESPACE = '\\RadiusTheme\\RadiusHotelBooking\\Commands\\';

	/**
	 * Command prefix for WP-CLI registration
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public const COMMAND_PREFIX = 'radius-hotel-booking artisan';

	/**
	 * Available commands mapping
	 *
	 * Maps command names to their corresponding class names.
	 * Command names are used in WP-CLI (e.g., 'make:table')
	 * Class names should exist in the BASE_NAMESPACE
	 *
	 * @var array<string, string> Array of command_name => class_name pairs
	 * @since 1.0.0
	 */
	private array $commands = array(
		'make:table'       => 'MakeTableCommand',
		'migrate'          => 'MigrateCommand',
		'migrate:refresh'  => 'MigrateCommand::refresh',
		'migrate:rollback' => 'MigrateCommand::rollback',
		'migrate:status'   => 'MigrateCommand::status',
		'delete:table'     => 'DeleteTableCommand',
		'make:model'       => 'MakeModelCommand',
		'make:repository'  => 'MakeRepositoryCommand',
		'make:resource'    => 'MakeResourceCommand',
		'make:service'     => 'MakeServiceCommand',
		'make:controller'  => 'MakeControllerCommand',
		'make:module'      => 'MakeModuleCommand',
	);

	/**
	 * Initialize and register all commands
	 *
	 * Main entry point for the command registry. Checks if WP-CLI is available
	 * and registers all configured commands. Safe to call multiple times.
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 * @example
	 * ```php
	 * $registry = new CommandRegistry();
	 * $registry->init();
	 * ```
	 */
	public function init(): void {
		if ( ! $this->isWpCliEnvironment() ) {
			return;
		}

		$this->registerCommands();
	}

	/**
	 * Check if running in WP-CLI environment
	 *
	 * Determines whether the current execution context is WP-CLI.
	 * Used to prevent command registration in non-CLI environments.
	 *
	 * @return bool True if WP-CLI is available and active, false otherwise
	 * @since 1.0.0
	 */
	private function isWpCliEnvironment(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Register all available commands
	 *
	 * Iterates through all configured commands and registers them with WP-CLI.
	 * Commands with missing classes will be skipped and logged if debugging is enabled.
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 * @see self::registerCommand()
	 */
	private function registerCommands(): void {
		foreach ( $this->commands as $command_name => $class_name ) {
			$this->registerCommand( $command_name, $class_name );
		}
	}

	/**
	 * Register a single command with WP-CLI
	 *
	 * Constructs the full class name and command name, then registers
	 * the command with WP-CLI if the class exists. Missing classes
	 * are logged for debugging purposes.
	 *
	 * @param string $command_name The command name (e.g., 'make:table')
	 * @param string $class_name   The command class name (e.g., 'MakeTableCommand')
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 * @example
	 * ```php
	 * $this->registerCommand('make:helper', 'MakeHelperCommand');
	 * // Registers: wp radius-hotel-booking artisan make:helper
	 * ```
	 */
	private function registerCommand( string $command_name, string $class_name ): void {
		$full_class_name   = self::BASE_NAMESPACE . $class_name;
		$full_command_name = self::COMMAND_PREFIX . ' ' . $command_name;

		// 'MigrateCommand::status' registers one (instance) method, which
		// WP-CLI only accepts as array( $instance, 'method' ).
		list( $class_only, $method ) = array_pad( explode( '::', $full_class_name, 2 ), 2, '' );
		if ( ! class_exists( $class_only ) ) {
			$this->logMissingClass( $class_only );
			return;
		}

		\WP_CLI::add_command( $full_command_name, '' === $method ? $class_only : array( new $class_only(), $method ) );
	}

	/**
	 * Add a new command to the registry
	 *
	 * Dynamically adds a new command to the registry. The command will be
	 * registered when init() is called. Supports method chaining.
	 *
	 * @param string $command_name The command name for WP-CLI (e.g., 'make:helper')
	 * @param string $class_name   The command class name (without namespace)
	 *
	 * @return self Returns the current instance for method chaining
	 * @since 1.0.0
	 *
	 * @example
	 * ```php
	 * $registry->addCommand('make:helper', 'MakeHelperCommand')
	 *          ->addCommand('make:seeder', 'MakeSeederCommand');
	 * ```
	 */
	public function addCommand( string $command_name, string $class_name ): self {
		$this->commands[ $command_name ] = $class_name;
		return $this;
	}

	/**
	 * Remove a command from the registry
	 *
	 * Removes a command from the internal registry. This only affects
	 * future registrations and doesn't unregister already registered commands.
	 * Supports method chaining.
	 *
	 * @param string $command_name The command name to remove
	 *
	 * @return self Returns the current instance for method chaining
	 * @since 1.0.0
	 *
	 * @example
	 * ```php
	 * $registry->removeCommand('make:table')
	 *          ->removeCommand('migrate');
	 * ```
	 */
	public function removeCommand( string $command_name ): self {
		unset( $this->commands[ $command_name ] );
		return $this;
	}

	/**
	 * Get all registered commands
	 *
	 * Returns a copy of the internal commands array for inspection
	 * or external processing. Modifications to the returned array
	 * won't affect the registry.
	 *
	 * @return array<string, string> Array of command_name => class_name pairs
	 * @since 1.0.0
	 *
	 * @example
	 * ```php
	 * $commands = $registry->getCommands();
	 * foreach ($commands as $name => $class) {
	 *     echo "Command: {$name} -> {$class}\n";
	 * }
	 * ```
	 */
	public function getCommands(): array {
		return $this->commands;
	}

	/**
	 * Check if a specific command is registered
	 *
	 * Determines whether a command with the given name exists in the registry.
	 *
	 * @param string $command_name The command name to check
	 *
	 * @return bool True if the command exists, false otherwise
	 * @since 1.0.0
	 *
	 * @example
	 * ```php
	 * if ($registry->hasCommand('make:table')) {
	 *     echo "Table command is available";
	 * }
	 * ```
	 */
	public function hasCommand( string $command_name ): bool {
		return isset( $this->commands[ $command_name ] );
	}

	/**
	 * Get the total number of registered commands
	 *
	 * Returns the count of commands currently in the registry.
	 *
	 * @return int Number of registered commands
	 * @since 1.0.0
	 *
	 * @example
	 * ```php
	 * echo "Total commands: " . $registry->getCommandCount();
	 * ```
	 */
	public function getCommandCount(): int {
		return count( $this->commands );
	}

	/**
	 * Clear all commands from the registry
	 *
	 * Removes all commands from the internal registry. This only affects
	 * future registrations and doesn't unregister already registered commands.
	 *
	 * @return self Returns the current instance for method chaining
	 * @since 1.0.0
	 *
	 * @example
	 * ```php
	 * $registry->clearCommands()->addCommand('new:command', 'NewCommand');
	 * ```
	 */
	public function clearCommands(): self {
		$this->commands = array();
		return $this;
	}

	/**
	 * Log missing command class for debugging
	 *
	 * Logs a debug message when a command class cannot be found.
	 * Only logs when WP_DEBUG is enabled to avoid unnecessary output
	 * in production environments.
	 *
	 * @param string $class_name The fully qualified class name that was not found
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 * @internal This method is for internal use only
	 */
	private function logMissingClass( string $class_name ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\WP_CLI::debug( "Command class not found: {$class_name}" );
		}
	}
}
