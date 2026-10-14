/**
 * Choose the physical room (2.5): the chosen room type's floors, each with
 * its room tiles. Free rooms are buttons; the others are greyed with their
 * reason (for staff, a booked room shows the booking reference on hover).
 * Rooms already in this booking for an overlapping time are marked.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Check, Loader2 } from 'lucide-react';

import { cn } from '@/lib/utils';

/**
 * A room's reason, in words.
 *
 * @param {Object|undefined} reason `{ code, booking_ref?, until? }`.
 * @param {string}           state  Room state.
 * @return {string} Text.
 */
export function roomReason( reason, state ) {
	const code = reason?.code || state;
	const labels = {
		booked: __( 'Booked', 'radius-hotel-booking' ),
		held: __( 'Being booked', 'radius-hotel-booking' ),
		blocked: __( 'Blocked', 'radius-hotel-booking' ),
		buffer: __( 'Cleaning', 'radius-hotel-booking' ),
		maintenance: __( 'Maintenance', 'radius-hotel-booking' ),
		out_of_service: __( 'Out of service', 'radius-hotel-booking' ),
	};
	return labels[ code ] || __( 'Unavailable', 'radius-hotel-booking' );
}

/**
 * The rooms of a type that can be sold at a rate, in the order shown.
 *
 * @param {Object}   type  The room type (with `floors`).
 * @param {Object}   rate  The rate.
 * @param {number[]} taken Room ids already in this booking for this time.
 * @return {Object[]} Rooms.
 */
export function freeRooms( type, rate, taken = [] ) {
	return ( type.floors || [] ).flatMap( ( floor ) =>
		( floor.rooms || [] ).filter(
			( room ) =>
				! taken.includes( room.id ) &&
				( room.available_for || [] ).includes( rate.rate_plan_id )
		)
	);
}

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.type     The room type (with `floors`).
 * @param {Object}   props.rate     The chosen rate.
 * @param {number[]} props.taken    Room ids already in this booking for this time.
 * @param {number}   props.pending  The room being held right now (spinner; -1: several).
 * @param {Function} props.onPick   Called with the room (one room at a time).
 * @param {number}   props.selected The room chosen without a hold (a room edited on a booking).
 * @param {Function} props.onToggle Several rooms: called with the room to tick or untick (replaces onPick).
 * @param {number[]} props.picked   Several rooms: the ticked room ids.
 * @return {JSX.Element} Picker.
 */
export default function RoomPicker( {
	type,
	rate,
	taken,
	pending,
	onPick,
	selected = 0,
	onToggle,
	picked = [],
} ) {
	const floors = ( type.floors || [] ).filter(
		( floor ) => ( floor.rooms || [] ).length
	);

	if ( ! floors.length ) {
		return (
			<p className="m-0 text-sm text-muted-foreground">
				{ __(
					'This room type has no rooms yet.',
					'radius-hotel-booking'
				) }
			</p>
		);
	}

	return (
		<div className="space-y-4">
			{ floors.map( ( floor ) => (
				<div key={ floor.id } className="space-y-2">
					<p className="m-0 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
						{ floor.name }
					</p>
					<div className="flex flex-wrap gap-2">
						{ floor.rooms.map( ( room ) => {
							const inBooking = taken.includes( room.id );
							const free =
								! inBooking &&
								( room.available_for || [] ).includes(
									rate.rate_plan_id
								);
							const reason =
								room.reasons_by_rate?.[ rate.rate_plan_id ];
							const why = inBooking
								? __(
										'In this booking',
										'radius-hotel-booking'
								  )
								: roomReason( reason, room.state );
							const hint =
								! free && reason?.booking_ref
									? sprintf(
											/* translators: 1: reason, 2: booking reference. */
											__(
												'%1$s: %2$s',
												'radius-hotel-booking'
											),
											why,
											reason.booking_ref
									  )
									: why;
							const on = onToggle
								? picked.includes( room.id )
								: selected === room.id;
							return free ? (
								<button
									key={ room.id }
									type="button"
									onClick={ () =>
										onToggle
											? onToggle( room )
											: onPick( room )
									}
									disabled={ Boolean( pending ) }
									aria-pressed={
										onToggle || selected ? on : undefined
									}
									className={ cn(
										'relative flex h-14 min-w-[4.5rem] flex-col items-center justify-center rounded-lg border px-3 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60',
										// Themes style button:hover/focus: pin the colours.
										on
											? 'border-primary bg-primary text-white hover:bg-primary hover:text-white focus:bg-primary focus:text-white'
											: 'border-border bg-white text-heading hover:border-primary hover:bg-primary-softer hover:text-heading focus:bg-white focus:text-heading'
									) }
									aria-label={ sprintf(
										/* translators: %s: room number. */
										__(
											'Room %s, free',
											'radius-hotel-booking'
										),
										room.number
									) }
								>
									{ pending === room.id ||
									( on && -1 === pending ) ? (
										<Loader2
											className="h-4 w-4 animate-spin"
											aria-hidden="true"
										/>
									) : (
										room.number
									) }
									{ onToggle && on ? (
										<span className="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-primary text-white">
											<Check
												className="h-3 w-3"
												strokeWidth={ 3 }
												aria-hidden="true"
											/>
										</span>
									) : null }
								</button>
							) : (
								<div
									key={ room.id }
									title={ hint }
									className={ cn(
										'flex h-14 min-w-[4.5rem] flex-col items-center justify-center rounded-lg border px-3 text-sm',
										inBooking
											? 'border-primary bg-primary-soft font-semibold text-primary'
											: 'border-dashed border-border bg-muted text-muted-foreground'
									) }
								>
									<span className="font-semibold">
										{ room.number }
									</span>
									<span className="text-[11px] leading-tight">
										{ why }
									</span>
									<span className="sr-only">{ hint }</span>
								</div>
							);
						} ) }
					</div>
				</div>
			) ) }
		</div>
	);
}
