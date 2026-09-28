<?php
/**
 * Scheduled jobs.
 *
 * @package RadiusTheme\RadiusHotelBooking\Setup
 */

namespace RadiusTheme\RadiusHotelBooking\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * One registry for every WP-Cron job the plugin runs (ADR-012): hold expiry,
 * payment-deadline release, scheduled exports, log archiving.
 *
 * A module registers a job through the `rtbp_scheduled_events` filter:
 *
 *     add_filter( 'rtbp_scheduled_events', function ( $events ) {
 *         $events['rtbp_sweep_holds'] = array(
 *             'recurrence' => 'rtbp_five_minutes',
 *             'callback'   => array( HoldService::class, 'sweep' ),
 *         );
 *         return $events;
 *     } );
 *
 * The scheduler then (1) hooks the callback, wrapped in an overlap lock so a
 * slow run is never doubled; (2) schedules the event if it is missing;
 * (3) unschedules events the plugin no longer registers; and (4) clears
 * everything on deactivation. Jobs must be idempotent — WP-Cron can fire late,
 * twice, or not at all without traffic (the admin notes recommend a real
 * system cron hitting wp-cron.php).
 */
class Scheduler {

	/**
	 * Option listing the hooks this plugin has scheduled.
	 */
	const OPTION = 'rtbp_scheduled_hooks';

	/**
	 * Default overlap-lock lifetime, seconds.
	 */
	const LOCK_TTL = 600;

	/**
	 * Wire the scheduler. Called from the plugin bootstrap.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_recurrences' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- intervals are 5 and 15 minutes.
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Recurrences the plugin's jobs use, beyond WordPress's hourly/daily.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_recurrences( $schedules ): array {
		$schedules = (array) $schedules;

		$schedules['rtbp_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes', 'radius-hotel-booking' ),
		);

		$schedules['rtbp_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes', 'radius-hotel-booking' ),
		);

		return $schedules;
	}

	/**
	 * Registered jobs: hook => { recurrence, callback, lock_ttl? }.
	 *
	 * @return array<string,array{recurrence:string,callback:callable,lock_ttl?:int}>
	 */
	public static function events(): array {
		$events = (array) apply_filters( 'rtbp_scheduled_events', array() );

		return array_filter(
			$events,
			static fn( $event, $hook ) => is_string( $hook )
				&& 0 === strpos( $hook, 'rtbp_' )
				&& is_array( $event )
				&& ! empty( $event['recurrence'] )
				&& isset( $event['callback'] )
				&& is_callable( $event['callback'] ),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Hook every job's callback, schedule missing events, and unschedule
	 * events that are no longer registered.
	 *
	 * @return void
	 */
	public static function register(): void {
		$events = self::events();

		foreach ( $events as $hook => $event ) {
			add_action(
				$hook,
				static function () use ( $hook, $event ) {
					self::run_locked( $hook, $event['callback'], (int) ( $event['lock_ttl'] ?? self::LOCK_TTL ) );
				}
			);

			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + MINUTE_IN_SECONDS, $event['recurrence'], $hook );
			}
		}

		$known = get_option( self::OPTION, array() );
		$known = is_array( $known ) ? $known : array();

		foreach ( array_diff( $known, array_keys( $events ) ) as $retired ) {
			wp_clear_scheduled_hook( $retired );
		}

		$current = array_keys( $events );
		sort( $current );
		sort( $known );
		if ( $current !== $known ) {
			update_option( self::OPTION, $current, false );
		}
	}

	/**
	 * Run a job unless another run of it is still going. The lock is an
	 * option (atomic add_option), expiring after `$ttl` seconds so a crashed
	 * run cannot block the job forever.
	 *
	 * @param string   $hook     Job hook.
	 * @param callable $callback Job.
	 * @param int      $ttl      Lock lifetime in seconds.
	 * @return bool Whether the job ran.
	 */
	public static function run_locked( string $hook, callable $callback, int $ttl = self::LOCK_TTL ): bool {
		$lock = 'rtbp_lock_' . $hook;

		if ( ! add_option( $lock, time() + $ttl, '', false ) ) {
			$expires = (int) get_option( $lock, 0 );
			if ( $expires > time() ) {
				return false;
			}
			// Stale lock from a crashed run: take it over.
			update_option( $lock, time() + $ttl, false );
		}

		try {
			$callback();
		} finally {
			delete_option( $lock );
		}

		return true;
	}

	/**
	 * Unschedule every plugin job (plugin deactivation).
	 *
	 * @return void
	 */
	public static function unschedule_all(): void {
		$known = get_option( self::OPTION, array() );
		$hooks = array_unique( array_merge( is_array( $known ) ? $known : array(), array_keys( self::events() ) ) );

		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		delete_option( self::OPTION );
	}
}
