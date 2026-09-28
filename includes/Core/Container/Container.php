<?php
/**
 * RadiusHotelBooking API Authentication Middleware
 *
 * This middleware checks if the user is authenticated before allowing access to the API.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Middleware
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Container;

use Exception;
use ReflectionClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Core/Container/Container.php
 * Simple dependency injection container
 */
class Container {

	/**
	 * Associative array to hold bindings of abstract types to concrete implementations or callables.
	 * This array maps abstract identifiers (like class names or interfaces) to their concrete implementations or factory functions.
	 * This allows for dynamic resolution of dependencies at runtime, enabling the container to create instances of classes with their dependencies automatically.
	 * This is particularly useful in a dependency injection context, where you want to decouple the creation of objects from their usage.
	 * For example, you can bind an interface to a specific implementation, and when you resolve that interface, the container will automatically create an instance of the bound implementation.
	 * This approach promotes loose coupling and enhances testability, as you can easily swap out implementations without changing the code that uses them.
	 *
	 * @var array<string, callable>
	 */
	private static array $bindings = array();
	/**
	 * Associative array to hold singleton instances of resolved classes.
	 * This array stores instances of classes that have been resolved by the container, ensuring that only one instance of each class is created and reused throughout the application.
	 * This is particularly useful for classes that are expensive to create or have a shared state, as it prevents unnecessary instantiation and allows for consistent behavior across the application.
	 * For example, if you bind a class as a singleton, the first time it is resolved, the container will create an instance and store it in this array.
	 * On subsequent requests for the same class, the container will return the already created instance from this array instead of creating a new one.
	 * This approach is commonly used in dependency injection containers to manage shared resources and ensure that the same instance is used wherever the class is required.
	 *
	 * @var array<string, mixed>
	 */
	private static array $instances = array();

	/**
	 * Binds an abstract type to a concrete implementation or callable.
	 *
	 * @param string $abstract The name of the abstract type being bound.
	 * @param callable $concrete The concrete implementation or callable to associate with the abstract type.
	 *
	 * @return void
	 */
	public static function bind( string $abstract, callable $concrete ): void { //phpcs:ignore
		self::$bindings[ $abstract ] = $concrete;
	}

	/**
	 * Registers a singleton in the container. Ensures that the same instance
	 * of the resolved class will be returned every time the singleton is accessed.
	 *
	 * @param string $abstract The abstract (interface or class name) to bind as a singleton.
	 * @param callable $concrete A callable that resolves and returns the concrete instance to bind.
	 *
	 * @return void
	 */
	public static function singleton( string $abstract, callable $concrete ): void { //phpcs:ignore
		self::bind(
			$abstract,
			function () use ( $concrete, $abstract ) {
				if ( ! isset( self::$instances[ $abstract ] ) ) {
					self::$instances[ $abstract ] = $concrete();
				}
				return self::$instances[ $abstract ];
			}
		);
	}

	/**
	 * Resolves and retrieves the binding associated with the given abstract identifier.
	 *
	 * @param string $abstract The abstract identifier for which the binding needs to be resolved.
	 *
	 * @return mixed The resolved binding for the given abstract identifier.
	 * @throws Exception If no binding is found for the given abstract identifier.
	 */
	public static function resolve( string $abstract ) { //phpcs:ignore
		if ( ! isset( self::$bindings[ $abstract ] ) ) {
			throw new Exception( "No binding found for {$abstract}" ); //phpcs:ignore
		}

		return self::$bindings[ $abstract ]();
	}

	/**
	 * Resolves and creates an instance of the given class, including its dependencies.
	 *
	 * @param string $class The fully qualified class name to instantiate.
	 *
	 * @return mixed The created instance of the specified class.
	 */
	public static function make( string $class ) { //phpcs:ignore
		if ( isset( self::$bindings[ $class ] ) ) {
			return self::resolve( $class );
		}

		$reflection  = new ReflectionClass( $class );
		$constructor = $reflection->getConstructor();

		if ( ! $constructor ) {
			return new $class();
		}

		$parameters   = $constructor->getParameters();
		$dependencies = array();

		foreach ( $parameters as $parameter ) {
			$type = $parameter->getType();
			if ( $type && ! $type->isBuiltin() ) {
				$dependencies[] = self::make( $type->getName() );
			}
		}

		return $reflection->newInstanceArgs( $dependencies );
	}
}
