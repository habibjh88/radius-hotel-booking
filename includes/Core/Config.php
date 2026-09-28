<?php
/**
 * Bootstraps the container bindings and event listeners.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core
 */

namespace RadiusTheme\RadiusHotelBooking\Core;

use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use RadiusTheme\RadiusHotelBooking\Core\Events\EventDispatcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Handles the initialization of the application by registering dependencies and event listeners.
 */
class Config {

	/**
	 * Initializes the class by registering dependencies and event listeners,
	 * and triggers the 'rtbp_bootstrapped' action.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->registerDependencies();
		$this->registerEventListeners();

		do_action( 'rtbp_bootstrapped', $this );
	}

	/**
	 * Registers the necessary dependencies for the plugin by binding classes to resolvers.
	 * The binding configuration is retrieved from an external file and allows filters
	 * to modify or add bindings. Triggers the 'rtbp_dependencies_registered' action
	 * after completing the registration process.
	 *
	 * @return void
	 */
	private function registerDependencies(): void {
		$bindings = include plugin_dir_path( __FILE__ ) . '/config/bindings.php';

		// Allow filters to modify/add bindings
		$bindings = apply_filters( 'rtbp_bindings', $bindings );

		foreach ( $bindings as $class => $resolver ) {
			Container::bind( $class, $resolver );
		}

		do_action( 'rtbp_dependencies_registered' );
	}

	/**
	 * Registers event listeners by loading event configurations, allowing modifications via a filter,
	 * and attaching listeners to events using the EventDispatcher. Triggers an action once all event listeners are registered.
	 *
	 * @return void
	 */
	private function registerEventListeners(): void {
		$events = include plugin_dir_path( __FILE__ ) . '/config/events.php';

		// Allow external modifications via filter
		$events = apply_filters( 'rtbp_events', $events );

		foreach ( $events as $event => $listeners ) {
			foreach ( (array) $listeners as $listener ) {
				EventDispatcher::listen( $event, $listener );
			}
		}

		do_action( 'rtbp_events_registered' );
	}
}
