<?php
/**
 * Handles event registration and event dispatching in a simple event system.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Core/Events/EventDispatcher.php
 * Simple event system for model events
 */
class EventDispatcher {

	/**
	 * An array to store event listeners.
	 *
	 * @var array<string, callable[]>
	 */
	private static array $listeners = array();

	/**
	 * Registers a callback function to listen for a specific event.
	 *
	 * @param string $event The name of the event to listen for.
	 * @param callable $callback The function to be called when the event is triggered.
	 *
	 * @return void
	 */
	public static function listen( string $event, callable $callback ): void {
		self::$listeners[ $event ][] = $callback;
	}

	/**
	 * Dispatches an event to all registered listeners for that event.
	 *
	 * @param string $event The name of the event to dispatch.
	 * @param mixed|null $payload Optional data to pass to the event listeners.
	 *
	 * @return void
	 */
	public static function dispatch( string $event, $payload = null ): void {
		if ( ! isset( self::$listeners[ $event ] ) ) {
			return;
		}

		foreach ( self::$listeners[ $event ] as $callback ) {
			call_user_func( $callback, $payload );
		}
	}
}
