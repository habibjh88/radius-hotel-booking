/**
 * Choose the physical room (2.5): the chosen room type's floors, each with
 * its room tiles. Free rooms are buttons; the others are greyed with their
 * reason (for staff, a booked room shows the booking reference on hover).
 * Rooms already in this booking for an overlapping time are marked.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Loader2 } from 'lucide-react';

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
 * @param {Object}   props         Props.
 * @param {Object}   props.type    The room type (with `floors`).
 * @param {Object}   props.rate    The chosen rate.
 * @param {number[]} props.taken   Room ids already in this booking for this time.
 * @param {number}   props.pending The room being held right now (spinner).
 * @param {Function} props.onPick  Called with the room.
 * @param {number}   props.selected The room chosen without a hold (a room edited on a booking).
 * @return {JSX.Element} Picker.
 */
export default function RoomPicker( {
	type,
	rate,
	taken,
	pending,
	onPick,
	selected = 0,
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
							return free ? (
								<button
									key={ room.id }
									type="button"
									onClick={ () => onPick( room ) }
									disabled={ Boolean( pending ) }
									aria-pressed={
										selected
											? selected === room.id
											: undefined
									}
									className={ cn(
										'flex h-14 min-w-[4.5rem] flex-col items-center justify-center rounded-lg border px-3 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60',
										selected === room.id
											? 'border-primary bg-primary text-white'
											: 'border-border bg-white text-heading hover:border-primary hover:bg-primary-softer'
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
									{ pending === room.id ? (
										<Loader2
											className="h-4 w-4 animate-spin"
											aria-hidden="true"
										/>
									) : (
										room.number
									) }
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
