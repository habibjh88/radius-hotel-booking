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
use RadiusTheme\RadiusHotelBooking\Controllers\BookingController;
use RadiusTheme\RadiusHotelBooking\Controllers\GuestController;
use RadiusTheme\RadiusHotelBooking\Controllers\NoteController;
use RadiusTheme\RadiusHotelBooking\Controllers\AvailabilityController;
use RadiusTheme\RadiusHotelBooking\Controllers\BlockController;
use RadiusTheme\RadiusHotelBooking\Controllers\CalendarController;
use RadiusTheme\RadiusHotelBooking\Controllers\HoldController;
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

// Availability (M08): the staff search.
$this->router->get( 'availability', array( AvailabilityController::class, 'search' ) );
$this->router->get( 'availability/calendar', array( CalendarController::class, 'grid' ) );
$this->router->put( 'availability/calendar', array( CalendarController::class, 'save' ) );
$this->router->post( 'availability/calendar/bulk', array( CalendarController::class, 'bulk' ) );
$this->router->resource( 'blocks', BlockController::class );
$this->router->post( 'holds', array( HoldController::class, 'store' ) );
$this->router->put( 'holds/(?P<token>[A-Za-z0-9]{32})', array( HoldController::class, 'extend' ) );
$this->router->delete( 'holds/(?P<token>[A-Za-z0-9]{32})', array( HoldController::class, 'destroy' ) );

// Bookings (M02): creating one at the desk. The booking record (M03) adds the rest.
$this->router->post( 'bookings', array( BookingController::class, 'store' ) );
$this->router->get( 'bookings/(?P<id>\d+)', array( BookingController::class, 'show' ) );
$this->router->post( 'bookings/(?P<id>\d+)/approve', array( BookingController::class, 'approve' ) );
$this->router->post( 'bookings/(?P<id>\d+)/decline', array( BookingController::class, 'decline' ) );
$this->router->post( 'bookings/(?P<id>\d+)/cancel', array( BookingController::class, 'cancel' ) );
$this->router->post( 'booking-lines/(?P<id>\d+)/check-in', array( BookingController::class, 'checkIn' ) );
$this->router->post( 'booking-lines/(?P<id>\d+)/check-out', array( BookingController::class, 'checkOut' ) );
$this->router->post( 'booking-lines/(?P<id>\d+)/no-show', array( BookingController::class, 'noShow' ) );
$this->router->get( 'booking-lines/(?P<id>\d+)/rooms', array( BookingController::class, 'freeRooms' ) );
$this->router->post( 'bookings/(?P<id>\d+)/lines', array( BookingController::class, 'addLine' ) );
$this->router->put( 'booking-lines/(?P<id>\d+)', array( BookingController::class, 'editLine' ) );
$this->router->delete( 'booking-lines/(?P<id>\d+)', array( BookingController::class, 'removeLine' ) );

// Guests (M09). `guests/lookup` before the resource: it is not an id.
$this->router->get( 'guests/lookup', array( GuestController::class, 'lookup' ) );
$this->router->resource( 'guests', GuestController::class );
$this->router->post( 'guests/(?P<id>\d+)/ban', array( GuestController::class, 'ban' ) );
$this->router->post( 'guests/(?P<id>\d+)/unban', array( GuestController::class, 'unban' ) );
$this->router->get( 'guests/(?P<id>\d+)/stays', array( GuestController::class, 'stays' ) );
$this->router->get( 'guests/(?P<id>\d+)/id-number', array( GuestController::class, 'idNumber' ) );
// Notes on guests (and, later, bookings and staff): `rtbp_note_types`.
$this->router->resource( 'notes', NoteController::class );

/**
 * Add-on routes — an add-on plugin registers its own endpoints here rather than
 * editing this file. It can also append a whole route file via the
 * `rtbp_api_route_paths` filter.
 *
 * @param \RadiusTheme\RadiusHotelBooking\Core\Api\Routes\RouteRegistrar $router
 */
do_action( 'rtbp_register_addon_routes', $this->router );
