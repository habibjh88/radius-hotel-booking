/**
 * The words of the status moves, shared by the booking screen and the
 * front desk list (M01).
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * An action in words.
 *
 * @param {string} action Action.
 * @return {string} Label.
 */
export const actionLabel = ( action ) =>
	( {
		approve: __( 'Approve', 'radius-hotel-booking' ),
		decline: __( 'Decline', 'radius-hotel-booking' ),
		cancel: __( 'Cancel', 'radius-hotel-booking' ),
		check_in: __( 'Check in', 'radius-hotel-booking' ),
		check_out: __( 'Check out', 'radius-hotel-booking' ),
		no_show: __( 'No-show', 'radius-hotel-booking' ),
		move_room: __( 'Move to another room', 'radius-hotel-booking' ),
		undo_check_in: __( 'Undo check-in', 'radius-hotel-booking' ),
		extend_stay: __( 'Extend stay', 'radius-hotel-booking' ),
	} )[ action ] || action;

/**
 * The question asked before a move on one room.
 *
 * @param {string} action Action.
 * @param {string} room   Room number.
 * @return {string} Question.
 */
export const lineQuestion = ( action, room ) =>
	sprintf(
		{
			/* translators: %s: room number. */
			decline: __( 'Decline room %s?', 'radius-hotel-booking' ),
			/* translators: %s: room number. */
			cancel: __( 'Cancel room %s?', 'radius-hotel-booking' ),
			/* translators: %s: room number. */
			check_out: __( 'Check out room %s?', 'radius-hotel-booking' ),
			/* translators: %s: room number. */
			no_show: __( 'Mark room %s as a no-show?', 'radius-hotel-booking' ),
			/* translators: %s: room number. */
			undo_check_in: __(
				'Undo the check-in of room %s?',
				'radius-hotel-booking'
			),
		}[ action ] || '%s',
		room
	);
