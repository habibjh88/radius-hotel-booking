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
use RadiusTheme\RadiusHotelBooking\Repositories\BlockRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\AmenityRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\FloorRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\NoteRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RatePlanRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\AvailabilityRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\HoldRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RateCalendarRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRateRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\SettingsRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\StoredFileRepository;
use RadiusTheme\RadiusHotelBooking\Services\Guests\GuestService;
use RadiusTheme\RadiusHotelBooking\Services\Notes\NoteService;
use RadiusTheme\RadiusHotelBooking\Services\Inventory\AmenityService;
use RadiusTheme\RadiusHotelBooking\Services\Inventory\FloorService;
use RadiusTheme\RadiusHotelBooking\Services\Availability\AvailabilityService;
use RadiusTheme\RadiusHotelBooking\Services\Availability\BlockService;
use RadiusTheme\RadiusHotelBooking\Services\Availability\CalendarService;
use RadiusTheme\RadiusHotelBooking\Services\Availability\HoldService;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingService;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingWriter;
use RadiusTheme\RadiusHotelBooking\Services\Availability\OccupancyCalculator;
use RadiusTheme\RadiusHotelBooking\Services\Pricing\PriceResolver;
use RadiusTheme\RadiusHotelBooking\Services\Pricing\RatePlanService;
use RadiusTheme\RadiusHotelBooking\Services\Pricing\RoomTypeRateService;
use RadiusTheme\RadiusHotelBooking\Services\Inventory\RoomService;
use RadiusTheme\RadiusHotelBooking\Services\Inventory\RoomTypeService;
use RadiusTheme\RadiusHotelBooking\Services\SettingsService;

return array(

	// Repositories.
	SettingsRepository::class => fn() => new SettingsRepository(),
	StoredFileRepository::class => fn() => new StoredFileRepository(),
	AmenityRepository::class    => fn() => new AmenityRepository(),
	FloorRepository::class      => fn() => new FloorRepository(),
	RoomTypeRepository::class   => fn() => new RoomTypeRepository(),
	RoomRepository::class       => fn() => new RoomRepository(),
	RatePlanRepository::class   => fn() => new RatePlanRepository(),
	RoomTypeRateRepository::class => fn() => new RoomTypeRateRepository(),
	AvailabilityRepository::class => fn() => new AvailabilityRepository(),
	RateCalendarRepository::class => fn() => new RateCalendarRepository(),
	HoldRepository::class         => fn() => new HoldRepository(),
	BlockRepository::class        => fn() => new BlockRepository(),
	GuestRepository::class        => fn() => new GuestRepository(),
	BookingRepository::class      => fn() => new BookingRepository(),
	BookingRoomRepository::class  => fn() => new BookingRoomRepository(),
	NoteRepository::class         => fn() => new NoteRepository(),

	// Services.
	SettingsService::class    => fn() => new SettingsService(),
	AmenityService::class     => fn() => new AmenityService( Container::resolve( AmenityRepository::class ), Container::resolve( RoomTypeRepository::class ) ),
	FloorService::class       => fn() => new FloorService( Container::resolve( FloorRepository::class ) ),
	RoomService::class        => fn() => new RoomService( Container::resolve( RoomRepository::class ), Container::resolve( RoomTypeRepository::class ), Container::resolve( FloorRepository::class ) ),
	RatePlanService::class    => fn() => new RatePlanService( Container::resolve( RatePlanRepository::class ), Container::resolve( RoomTypeRateRepository::class ) ),
	PriceResolver::class      => fn() => new PriceResolver( Container::resolve( RoomTypeRateRepository::class ), Container::resolve( RatePlanRepository::class ), Container::resolve( RoomTypeRepository::class ) ),
	// A new one per resolve; keep one for a whole quote to reuse its per-date cache.
	OccupancyCalculator::class => fn() => new OccupancyCalculator( Container::resolve( RoomRepository::class ) ),
	AvailabilityService::class => fn() => new AvailabilityService( Container::resolve( AvailabilityRepository::class ), Container::resolve( PriceResolver::class ) ),
	BookingWriter::class       => fn() => new BookingWriter( Container::resolve( AvailabilityRepository::class ), Container::resolve( RatePlanRepository::class ), Container::resolve( PriceResolver::class ) ),
	CalendarService::class     => fn() => new CalendarService( Container::resolve( AvailabilityRepository::class ), Container::resolve( RateCalendarRepository::class ), Container::resolve( RoomTypeRepository::class ), Container::resolve( PriceResolver::class ), Container::resolve( BlockRepository::class ) ),
	NoteService::class         => fn() => new NoteService( Container::resolve( NoteRepository::class ) ),
	GuestService::class        => fn() => new GuestService( Container::resolve( GuestRepository::class ) ),
	BlockService::class        => fn() => new BlockService( Container::resolve( BlockRepository::class ) ),
	HoldService::class         => fn() => new HoldService( Container::resolve( HoldRepository::class ), Container::resolve( BookingWriter::class ) ),
	BookingService::class      => fn() => new BookingService( Container::resolve( BookingWriter::class ), Container::resolve( GuestService::class ), Container::resolve( HoldService::class ), Container::resolve( BookingRepository::class ), Container::resolve( BookingRoomRepository::class ) ),
	RoomTypeRateService::class => fn() => new RoomTypeRateService( Container::resolve( RoomTypeRateRepository::class ), Container::resolve( RatePlanRepository::class ), Container::resolve( RoomTypeRepository::class ) ),
	RoomTypeService::class    => fn() => new RoomTypeService( Container::resolve( RoomTypeRepository::class ), Container::resolve( RoomRepository::class ), Container::resolve( AmenityService::class ) ),
);
