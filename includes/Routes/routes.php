<?php
/**
 * REST route definitions.
 *
 * Loaded by Core\Api\ApiManager on `rest_api_init`. `$this->router` (also
 * available as `$router`) is a Core\Api\Routes\RouteRegistrar bound to the
 * `radius-hotel-booking/v1` namespace.
 *
 *   $this->router->resource( 'floors', FloorController::class );
 *     => GET    /floors           index
 *        POST   /floors           store
 *        GET    /floors/{id}      show
 *        PUT    /floors/{id}      update
 *        DELETE /floors/{id}      destroy
 *
 * @package RadiusTheme\RadiusHotelBooking\Routes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Controllers\AccessController;
use RadiusTheme\RadiusHotelBooking\Controllers\DashboardController;
use RadiusTheme\RadiusHotelBooking\Controllers\FileController;
use RadiusTheme\RadiusHotelBooking\Controllers\FloorController;
use RadiusTheme\RadiusHotelBooking\Controllers\PricingController;
use RadiusTheme\RadiusHotelBooking\Controllers\RatePlanController;
use RadiusTheme\RadiusHotelBooking\Controllers\RoomTypeRateController;
use RadiusTheme\RadiusHotelBooking\Controllers\RoomController;
use RadiusTheme\RadiusHotelBooking\Controllers\RoomTypeController;
use RadiusTheme\RadiusHotelBooking\Controllers\SettingsController;

/** Dashboard. */
$this->router->get( 'access/me', array( AccessController::class, 'me' ) );
$this->router->get( 'access/registry', array( AccessController::class, 'registry' ) );

$this->router->get( 'dashboard/summary', array( DashboardController::class, 'summary' ) );

/** Protected file download (plain links; see FileController). */
$this->router->get( 'files/(?P<token>[a-f0-9]{32})', array( FileController::class, 'download' ) );

/** Settings routes. */
// Fixed paths before the {section} pattern, which would also match them.
$this->router->get( 'settings', array( SettingsController::class, 'index' ) );
$this->router->put( 'settings', array( SettingsController::class, 'update' ) );
$this->router->get( 'settings/schema', array( SettingsController::class, 'schema' ) );
$this->router->put( 'settings/reset', array( SettingsController::class, 'reset' ) );
$this->router->put( 'settings/(?P<section>[a-zA-Z0-9_-]+)/reset', array( SettingsController::class, 'resetSection' ) ); // phpcs:ignore
$this->router->get( 'settings/(?P<section>[a-zA-Z0-9_-]+)', array( SettingsController::class, 'show' ) ); // phpcs:ignore
$this->router->put( 'settings/(?P<section>[a-zA-Z0-9_-]+)', array( SettingsController::class, 'updateSection' ) ); // phpcs:ignore

/** Inventory (M06). */
$this->router->put( 'floors/order', array( FloorController::class, 'order' ) );
$this->router->resource( 'floors', FloorController::class );
$this->router->resource( 'room-types', RoomTypeController::class );
$this->router->get( 'room-types/(?P<id>\d+)/rooms', array( RoomController::class, 'ofType' ) );
// Before the resource: `rooms/bulk` is not an id.
$this->router->post( 'rooms/bulk', array( RoomController::class, 'bulk' ) );
$this->router->resource( 'rooms', RoomController::class );
$this->router->post( 'rooms/(?P<id>\d+)/state', array( RoomController::class, 'state' ) );
$this->router->post( 'rooms/(?P<id>\d+)/move', array( RoomController::class, 'move' ) );

/** Rate plans and pricing (M07). */
$this->router->resource( 'rate-plans', RatePlanController::class );
$this->router->get( 'room-types/(?P<id>\d+)/rates', array( RoomTypeRateController::class, 'grid' ) );
$this->router->put( 'room-types/(?P<id>\d+)/rates', array( RoomTypeRateController::class, 'save' ) );
$this->router->post( 'pricing/quote', array( PricingController::class, 'quote' ) );

/**
 * Add-on routes — an add-on plugin registers its own endpoints here rather than
 * editing this file. It can also append a whole route file via the
 * `rtbp_api_route_paths` filter.
 *
 * @param \RadiusTheme\RadiusHotelBooking\Core\Api\Routes\RouteRegistrar $router
 */
do_action( 'rtbp_register_addon_routes', $this->router );
