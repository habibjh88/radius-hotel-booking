<?php
/**
 * Event listeners.
 *
 * Maps an event name to the callbacks that run when it is dispatched by
 * `Core\Events\EventDispatcher`. Model lifecycle events fire automatically and
 * are named `<ModelFqcn>.<event>` where event is one of
 * creating|created|updating|updated|saving|saved|deleting|deleted.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\config
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * Model lifecycle event => listeners. Events are named `<ModelFqcn>.<event>`
 * (creating, created, updating, updated, deleting, deleted). Map one to an
 * `rtbp_*` action only when another module or an add-on needs the hook.
 */
return array();
