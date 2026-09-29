<?php
/**
 * Container bindings.
 *
 * Every class the DI container knows how to build lives here. Loaded by
 * `Core\Config` on `plugins_loaded` and consumed by `Container::resolve()`.
 *
 * Add a binding as `Fqcn::class => fn() => new Fqcn( ...dependencies )`. Use
 * `Container::resolve()` inside the closure to inject other bound classes —
 * everything is lazy, so nothing is instantiated until it is first resolved.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\config
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use RadiusTheme\RadiusHotelBooking\Repositories\FloorRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\SettingsRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\StoredFileRepository;
use RadiusTheme\RadiusHotelBooking\Services\Inventory\FloorService;
use RadiusTheme\RadiusHotelBooking\Services\Inventory\RoomService;
use RadiusTheme\RadiusHotelBooking\Services\Inventory\RoomTypeService;
use RadiusTheme\RadiusHotelBooking\Services\SettingsService;

return array(

	// Repositories.
	SettingsRepository::class => fn() => new SettingsRepository(),
	StoredFileRepository::class => fn() => new StoredFileRepository(),
	FloorRepository::class      => fn() => new FloorRepository(),
	RoomTypeRepository::class   => fn() => new RoomTypeRepository(),
	RoomRepository::class       => fn() => new RoomRepository(),

	// Services.
	SettingsService::class    => fn() => new SettingsService(),
	FloorService::class       => fn() => new FloorService( Container::resolve( FloorRepository::class ) ),
	RoomService::class        => fn() => new RoomService( Container::resolve( RoomRepository::class ), Container::resolve( RoomTypeRepository::class ), Container::resolve( FloorRepository::class ) ),
	RoomTypeService::class    => fn() => new RoomTypeService( Container::resolve( RoomTypeRepository::class ), Container::resolve( RoomRepository::class ) ),
);
