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

use RadiusTheme\RadiusHotelBooking\Models\Item;

return array(

	Item::class . '.created' => array(
		function ( $item ) {
			/**
			 * Fires after an item row is created.
			 *
			 * @param \RadiusTheme\RadiusHotelBooking\Models\Item $item Created model.
			 */
			do_action( 'rtbp_item_created', $item );
		},
	),

	Item::class . '.updated' => array(
		function ( $item ) {
			/**
			 * Fires after an item row is updated.
			 *
			 * @param \RadiusTheme\RadiusHotelBooking\Models\Item $item Updated model.
			 */
			do_action( 'rtbp_item_updated', $item );
		},
	),

	Item::class . '.deleted' => array(
		function ( $item ) {
			/**
			 * Fires after an item row is deleted.
			 *
			 * @param \RadiusTheme\RadiusHotelBooking\Models\Item $item Deleted model.
			 */
			do_action( 'rtbp_item_deleted', $item );
		},
	),
);
