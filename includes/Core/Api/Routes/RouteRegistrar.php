<?php
/**
 * RadiusHotelBooking API Route Registrar
 *
 * This class is responsible for registering API routes dynamically with the WordPress REST API.
 * It supports CRUD operations and allows for middleware to be applied globally or per route.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Routes
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Routes;

use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\MiddlewareInterface;
use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use WP_REST_Request;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/Api/Routes/RouteRegistrar.php
 * Dynamic route registration system
 */
class RouteRegistrar {
	/**
	 * Namespace for the API routes.
	 * This is typically used to group routes under a common prefix,
	 * such as 'api/v1', to avoid conflicts with other plugins or themes.
	 *
	 * @var string
	 */
	private string $namespace;
	/**
	 * Array to hold the registered routes.
	 * Each route is an associative array containing the route path,
	 * HTTP methods, and options such as callbacks and permissions.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $routes = array();
	/**
	 * Array to hold global middleware that applies to all routes.
	 * This allows for common functionality, such as authentication or logging,
	 * to be applied across all API endpoints without needing to specify it for each route.
	 *
	 * @var MiddlewareInterface[]
	 */
	private array $globalMiddleware = array();

	/**
	 * Constructor method to initialize the object with a default namespace.
	 *
	 * @param string $namespace The namespace to be used, defaults to 'api/v1'.
	 *
	 * @return void
	 */
	public function __construct( string $namespace = 'api/v1' ) { //phpcs:ignore
		// Initialize the namespace for the API routes.
		$this->namespace = $namespace;
	}

	/**
	 * Adds a middleware to the global middleware stack.
	 *
	 * @param MiddlewareInterface $middleware The middleware instance to be added.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function middleware( MiddlewareInterface $middleware ): self {
		$this->globalMiddleware[] = $middleware;

		return $this;
	}

	/**
	 * Registers routes for a resource with standard CRUD operations.
	 *
	 * @param string $name The base name of the resource route.
	 * @param string $controller The controller that handles the resource logic.
	 * @param array $options Additional options to configure the routes.
	 *
	 * @return self
	 */
	public function resource( string $name, string $controller, array $options = array() ): self {
		$routes = array(
			'GET'  => array( 'callback' => array( $controller, 'index' ) ),
			'POST' => array( 'callback' => array( $controller, 'store' ) ),
		);

		$singleRoutes = array(
			'GET'    => array( 'callback' => array( $controller, 'show' ) ),
			'PUT'    => array( 'callback' => array( $controller, 'update' ) ),
			'PATCH'  => array( 'callback' => array( $controller, 'update' ) ),
			'DELETE' => array( 'callback' => array( $controller, 'destroy' ) ),
		);

		// Register collection routes
		$this->routes[] = array(
			'route'   => $name,
			'methods' => $routes,
			'options' => $options,
		);

		// Register single resource routes
		$this->routes[] = array(
			'route'   => $name . '/(?P<id>\d+)',
			'methods' => $singleRoutes,
			'options' => $options,
		);

		return $this;
	}

	/**
	 * Registers a new route with the HTTP GET method.
	 *
	 * @param string $route The URL pattern for the route.
	 * @param mixed $callback The callback to be executed when the route is matched.
	 * @param array $options Additional options for the route such as middleware or settings.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function get( string $route, mixed $callback, array $options = array() ): self {
		return $this->addRoute( $route, 'GET', $callback, $options );
	}

	/**
	 * Registers a POST route with the specified callback and options.
	 *
	 * @param string $route The route path to associate with the POST request.
	 * @param mixed $callback The callback to be executed when the route is matched.
	 * @param array $options Optional key-value pairs for additional route configuration.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function post( string $route, mixed $callback, array $options = array() ): self {
		return $this->addRoute( $route, 'POST', $callback, $options );
	}

	/**
	 * Registers a route with the HTTP PUT method.
	 *
	 * @param string $route The route path to register.
	 * @param mixed $callback The callback to execute when the route is accessed.
	 * @param array $options Additional options for the route, such as middlewares or settings.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function put( string $route, mixed $callback, array $options = array() ): self {
		return $this->addRoute( $route, 'PUT', $callback, $options );
	}

	/**
	 * Registers a new route with the HTTP DELETE method.
	 *
	 * @param string $route The route path to be matched.
	 * @param mixed $callback The callback to be executed when the route is matched.
	 * @param array $options Optional configuration for the route, such as middleware or additional options.
	 *
	 * @return self Returns the current instance to allow method chaining.
	 */
	public function delete( string $route, mixed $callback, array $options = array() ): self {
		return $this->addRoute( $route, 'DELETE', $callback, $options );
	}

	/**
	 * Adds a route to the list of routes. If the route already exists, the method and callback
	 * will be added to the existing route; otherwise, a new route entry will be created.
	 *
	 * @param string $route The URI pattern for the route.
	 * @param string $method The HTTP method (e.g., GET, POST) associated with the route.
	 * @param mixed $callback The callback function or handler to execute for the route.
	 * @param array $options Additional options for the route.
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	private function addRoute( string $route, string $method, mixed $callback, array $options ): self {
		$existingRoute = null;
		foreach ( $this->routes as &$r ) {
			if ( $r['route'] === $route ) {
				$existingRoute = &$r;
				break;
			}
		}

		if ( $existingRoute ) {
			$existingRoute['methods'][ $method ] = array( 'callback' => $callback );
		} else {
			$this->routes[] = array(
				'route'   => $route,
				'methods' => array( $method => array( 'callback' => $callback ) ),
				'options' => $options,
			);
		}

		return $this;
	}

	/**
	 * Registers the necessary actions to initialize routes for the REST API.
	 *
	 * @return void This method does not return any value.
	 */
	public function register(): void {
		if ( ! class_exists( 'WP_REST_Server' ) ) {
			return;
		}
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Registers all defined routes with the WordPress REST API.
	 *
	 * Iterates through the stored routes and registers each route with the
	 * REST API using the provided route configuration, methods, callbacks,
	 * and permission callbacks.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		foreach ( $this->routes as $route ) {
			register_rest_route(
				$this->namespace,
				$route['route'],
				array(
					'methods'             => array_keys( $route['methods'] ),
					'callback'            => $this->createRouteCallback( $route['methods'] ),
					'permission_callback' => $route['options']['permission_callback'] ?? '__return_true',
				)
			);
		}
	}

	/**
	 * Creates a callback function for handling a route based on the provided HTTP methods.
	 *
	 * @param array $methods An associative array mapping HTTP methods (e.g., 'GET', 'POST')
	 *                       to their respective callback configurations. Each configuration
	 *                       must include a 'callback' key which defines the callback to execute.
	 *
	 * @return callable Returns a callback function that processes the request and calls the
	 *                  appropriate method-specific handler.
	 */
	private function createRouteCallback( array $methods ): callable {
		return function ( WP_REST_Request $request ) use ( $methods ) {
			$method = $request->get_method();

			if ( ! isset( $methods[ $method ] ) ) {
				return ApiResponse::error( __( 'Method not allowed', 'radius-hotel-booking' ), 405 )->send();
			}

			$callback = $methods[ $method ]['callback'];

			if ( is_array( $callback ) && is_string( $callback[0] ) ) {
				$controller = Container::make( $callback[0] );
				$callback   = array( $controller, $callback[1] );
			}

			// The controller method is the "action" AccessMiddleware maps to an access key.
			if ( is_array( $callback ) && is_string( $callback[1] ?? null ) ) {
				$request->set_attributes( array_merge( $request->get_attributes(), array( 'rtbp_action' => $callback[1] ) ) );
			}

			return call_user_func( $callback, $request );
		};
	}
}
