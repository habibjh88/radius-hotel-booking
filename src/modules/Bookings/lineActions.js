/**
 * Actions an add-on adds to a booked room (Pro: Move to another room, Extend
 * stay), through the `rtbp.booking.lineActions` filter:
 *
 *   {
 *     action: 'move_room',            // also in the line's `actions` from the server
 *     label: 'Move to another room',
 *     icon: ArrowRightLeft,
 *     accessKey: 'bookings.move_room', // hidden while locked
 *     Dialog,                          // ( { line, onClose, onConfirm } ); onConfirm( vars ) → Promise
 *     request: ( line, vars ) => Promise<{ booking, message, … }>,
 *   }
 *
 * The booking screen and the front desk list show each in the room's menu,
 * open its Dialog, and run `request` through their own mutation (so the
 * list, the booking and the dashboard refresh as for any other move).
 */
import { applyFilters } from '@wordpress/hooks';

import { canAccess } from '@/lib/access';
import { proLineActions, showsProLocked } from '@/admin/proCatalog';

let cache = null;

/**
 * The registered actions.
 *
 * @return {Array<Object>} Actions.
 */
export function extraLineActions() {
	if ( ! cache ) {
		const list = applyFilters( 'rtbp.booking.lineActions', [] );
		cache = ( Array.isArray( list ) ? list : [] ).filter(
			( item ) =>
				item &&
				'string' === typeof item.action &&
				item.Dialog &&
				'function' === typeof item.request
		);
	}
	return cache;
}

/**
 * One registered action.
 *
 * @param {string} action Action.
 * @return {Object|undefined} Action.
 */
export const extraLineAction = ( action ) =>
	extraLineActions().find( ( item ) => item.action === action );

/**
 * Whether the viewer may use a registered action (its access key).
 *
 * @param {string} action Action.
 * @return {boolean} Allowed.
 */
export const canUseExtra = ( action ) => {
	const item = extraLineAction( action );
	return Boolean( item ) && canAccess( item.accessKey );
};

/**
 * Pro's room actions shown locked (PRO badge) on a checked-in room while Pro
 * is not active, for the site owner (`showsProLocked()`). Empty once Pro
 * registers the real ones.
 *
 * @param {Object} line The line (`status`).
 * @return {Array<Object>} `proLineActions()` items.
 */
export function lockedLineActions( line ) {
	if ( 'checked_in' !== line?.status || ! showsProLocked() ) {
		return [];
	}
	return proLineActions().filter(
		( item ) => ! extraLineAction( item.action )
	);
}
