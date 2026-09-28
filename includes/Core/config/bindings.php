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
use RadiusTheme\RadiusHotelBooking\Repositories\SettingsRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\StoredFileRepository;
use RadiusTheme\RadiusHotelBooking\Services\SettingsService;

return array(

	// Repositories.
	SettingsRepository::class => fn() => new SettingsRepository(),
	StoredFileRepository::class => fn() => new StoredFileRepository(),

	// Services.
	SettingsService::class    => fn() => new SettingsService(),
);
