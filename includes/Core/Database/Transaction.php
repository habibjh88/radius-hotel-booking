<?php
/**
 * Database transactions.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Database
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Database;

use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Run work atomically: every write inside `run()` commits together or not at
 * all. Required for anything touching inventory or money (conventions §2.3,
 * booking-engine §7). Tables are InnoDB (Blueprint's default), so row locks
 * taken with `SELECT … FOR UPDATE` inside the callback are held until commit.
 *
 * - Nested `run()` calls join the outer transaction (no savepoints): an
 *   exception anywhere rolls the whole thing back.
 * - `afterCommit()` defers side effects (e-mails, hooks, cache flushes) until
 *   the data is really saved; they are dropped on rollback.
 */
class Transaction {

	/**
	 * Nesting depth.
	 *
	 * @var int
	 */
	private static int $depth = 0;

	/**
	 * Callbacks waiting for the outermost commit.
	 *
	 * @var callable[]
	 */
	private static array $after_commit = array();

	/**
	 * Run a callback in a transaction and return its result.
	 *
	 * @param callable $callback Work to do; receives nothing.
	 * @return mixed The callback's return value.
	 * @throws Throwable Whatever the callback throws, after rolling back.
	 */
	public static function run( callable $callback ) {
		global $wpdb;

		if ( self::$depth > 0 ) {
			++self::$depth;
			try {
				return $callback();
			} finally {
				--self::$depth;
			}
		}

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::$depth = 1;

		try {
			$result = $callback();
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::$depth        = 0;
			self::$after_commit = array();
			throw $e;
		}

		self::$depth = 0;
		$pending     = self::$after_commit;

		self::$after_commit = array();
		foreach ( $pending as $pending_callback ) {
			$pending_callback();
		}

		return $result;
	}

	/**
	 * Run a callback once the current transaction commits, or right away when
	 * no transaction is open.
	 *
	 * @param callable $callback Side effect.
	 * @return void
	 */
	public static function afterCommit( callable $callback ): void {
		if ( self::$depth > 0 ) {
			self::$after_commit[] = $callback;
			return;
		}
		$callback();
	}

	/**
	 * Whether a transaction is open.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return self::$depth > 0;
	}
}
