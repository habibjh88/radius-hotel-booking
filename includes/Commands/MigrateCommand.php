<?php
namespace RadiusTheme\RadiusHotelBooking\Commands;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * WP-CLI command to run database migrations.
 */

use WP_CLI;
use WP_CLI_Command;
use RadiusTheme\RadiusHotelBooking\Databases\DatabaseManager;

/**
 * Handles database migration execution via WP-CLI.
 */
class MigrateCommand extends WP_CLI_Command {

	/**
	 * Handle the migration commands for Radius Hotel Booking.
	 *
	 * Executes database migrations, with support for refreshing, checking status,
	 * or rolling back migrations based on the provided arguments.
	 *
	 * @param array $args Positional arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 *                          Supported options:
	 *                          - 'refresh': Refresh migrations.
	 *                          - 'status': Check migration status.
	 *                          - 'rollback': Rollback migrations.
	 *
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ) {
		WP_CLI::log( 'Running Radius Hotel Booking migrations...' );

		if ( ! empty( $assoc_args['refresh'] ) ) {
			WP_CLI::log( 'Refreshing migrations...' );
			$this->refresh( array(), $assoc_args );
			return;
		}
		if ( ! empty( $assoc_args['status'] ) ) {
			WP_CLI::log( 'Checking migration status...' );
			$this->status( array(), $assoc_args );
			return;
		}
		if ( ! empty( $assoc_args['rollback'] ) ) {
			WP_CLI::log( 'Rolling back migrations...' );
			$this->rollback( array(), $assoc_args );
			return;
		}
		try {
			DatabaseManager::migrate();
			WP_CLI::success( 'All migrations executed successfully.' );
		} catch ( \Throwable $e ) {
			WP_CLI::error( 'Migration failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Refresh the specified table or all tables by dropping and recreating them.
	 *
	 * ## OPTIONS
	 *
	 * [--table=<name>]
	 * : The name of the table to refresh. If omitted, all tables will be refreshed.
	 *
	 * [--force]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *     wp radius-hotel-booking artisan migrate:refresh
	 *     wp radius-hotel-booking artisan migrate:refresh --table=users --force
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments, including `table` for the table name and `force` to skip confirmation.
	 *
	 * @return void
	 */
	public function refresh( $args, $assoc_args ) {
		$table_name = $assoc_args['table'] ?? null;
		$force = isset( $assoc_args['force'] );

		if ( $table_name ) {
			WP_CLI::log( "Preparing to refresh table: {$table_name}" );
		} else {
			WP_CLI::log( 'Preparing to refresh ALL tables...' );
		}

		// Confirmation prompt unless --force is used
		if ( ! $force ) {
			$message = $table_name
				? "This will drop and recreate the {$table_name} table. All data will be lost!"
				: 'This will drop and recreate ALL tables. All data will be lost!';

			WP_CLI::confirm( $message . ' Do you want to continue?' );
		}

		try {
			if ( $table_name ) {
				DatabaseManager::refreshTable( $table_name );
				WP_CLI::success( "Table {$table_name} refreshed successfully." );
			} else {
				DatabaseManager::refreshAll();
				WP_CLI::success( 'All tables refreshed successfully.' );
			}
		} catch ( \Throwable $e ) {
			WP_CLI::error( 'Refresh failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Rolls back a specified number of migrations.
	 *
	 * @param array $args Positional arguments passed to the command.
	 * @param array $assoc_args Associative arguments. Expected to include 'steps' to specify the number of migrations to roll back.
	 *
	 * @return void
	 */
	public function rollback( $args, $assoc_args ) {
		$steps = isset( $assoc_args['steps'] ) ? (int) $assoc_args['steps'] : 1;

		WP_CLI::log( "Rolling back {$steps} migration(s)..." );

		try {
			DatabaseManager::rollback( $steps );
			WP_CLI::success( "Successfully rolled back {$steps} migration(s)." );
		} catch ( \Throwable $e ) {
			WP_CLI::error( 'Rollback failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Displays the status of migrations, including executed and pending migrations.
	 *
	 * @param array $args Positional arguments passed to the command.
	 * @param array $assoc_args Associative arguments. No specific keys are expected for this command.
	 *
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		try {
			$status = DatabaseManager::getStatus();

			if ( empty( $status['executed'] ) && empty( $status['pending'] ) ) {
				WP_CLI::log( 'No migrations found.' );
				return;
			}

			if ( ! empty( $status['executed'] ) ) {
				WP_CLI::log( 'Executed migrations:' );
				foreach ( $status['executed'] as $migration ) {
					WP_CLI::log( "  ✓ {$migration}" );
				}
			}

			if ( ! empty( $status['pending'] ) ) {
				WP_CLI::log( 'Pending migrations:' );
				foreach ( $status['pending'] as $migration ) {
					WP_CLI::log( "  ⏳ {$migration}" );
				}
			}

			WP_CLI::success(
				sprintf(
					'Total: %d executed, %d pending',
					count( $status['executed'] ),
					count( $status['pending'] )
				)
			);
		} catch ( \Throwable $e ) {
			WP_CLI::error( 'Failed to get migration status: ' . $e->getMessage() );
		}
	}
}
