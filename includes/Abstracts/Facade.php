<?php
/**
 * Base facade: static proxy to a container-resolved instance.
 *
 * @package RadiusTheme\RadiusHotelBooking\Abstracts
 */

namespace RadiusTheme\RadiusHotelBooking\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use Exception;
use RadiusTheme\RadiusHotelBooking\Core\Container\Container;

/**
 * Core/Facades/Facade.php
 * Base facade class for static access
 */
abstract class Facade {

	/**
	 * Get the name of the service this facade provides access to.
	 *
	 * @throws Exception If the method is not implemented in the child class.
	 */
	protected static function getFacadeAccessor(): string {
		throw new Exception( 'Facade does not implement getFacadeAccessor method.' );
	}
	/**
	 * Dynamically handle static method calls to the underlying service.
	 *
	 * @param string $method The method name being called.
	 * @param array $args The arguments passed to the method.
	 * @return mixed The result of the method call on the underlying service.
	 */
	public static function __callStatic( string $method, array $args ) {
		$instance = Container::resolve( static::getFacadeAccessor() );
		return $instance->$method( ...$args );
	}
}
