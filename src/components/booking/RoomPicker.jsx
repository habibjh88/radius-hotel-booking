/**
 * Choose the physical room (2.5): the chosen room type's floors, each a card
 * with its free-room count and room tiles under a small legend. Free rooms are buttons; the others are greyed with their
 * reason (for staff, a booked room shows the booking reference on hover).
 * Rooms already in this booking for an overlapping time are marked.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Building2, Check, DoorOpen, Loader2 } from 'lucide-react';

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

	const isFree = ( room ) =>
		! taken.includes( room.id ) &&
		( room.available_for || [] ).includes( rate.rate_plan_id );
	const isOn = ( room ) =>
		onToggle ? picked.includes( room.id ) : selected === room.id;
	const someClosed = floors.some( ( floor ) =>
		floor.rooms.some( ( room ) => ! isFree( room ) )
	);

	return (
		<div className="space-y-3">
			<ul className="m-0 flex list-none flex-wrap items-center gap-x-4 gap-y-1 p-0 text-xs text-muted-foreground">
				<li className="inline-flex items-center gap-1.5">
					<span
						className="h-3 w-3 rounded border border-border bg-white"
						aria-hidden="true"
					/>
					{ __( 'Free', 'radius-hotel-booking' ) }
				</li>
				<li className="inline-flex items-center gap-1.5">
					<span
						className="h-3 w-3 rounded bg-primary"
						aria-hidden="true"
					/>
					{ __( 'Selected', 'radius-hotel-booking' ) }
				</li>
				{ someClosed ? (
					<li className="inline-flex items-center gap-1.5">
						<span
							className="h-3 w-3 rounded border border-dashed border-border bg-muted"
							aria-hidden="true"
						/>
						{ __( 'Unavailable', 'radius-hotel-booking' ) }
					</li>
				) : null }
			</ul>

			{ floors.map( ( floor ) => {
				const total = floor.rooms.length;
				const open = floor.rooms.filter( isFree ).length;
				const chosen = floor.rooms.filter( isOn ).length;
				return (
					<section
						key={ floor.id }
						className="overflow-hidden rounded-xl border border-border bg-card"
						aria-label={ floor.name }
					>
						<header className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b border-border bg-muted/40 px-4 py-2.5">
							<span className="inline-flex items-center gap-2 text-sm font-semibold text-heading">
								<Building2
									className="h-4 w-4 text-muted-foreground"
									aria-hidden="true"
								/>
								{ floor.name }
								{ chosen && onToggle ? (
									<span className="rounded-full bg-primary-soft px-2 py-0.5 text-[11px] font-semibold text-primary">
										{ sprintf(
											/* translators: %d: rooms selected on this floor. */
											__(
												'%d selected',
												'radius-hotel-booking'
											),
											chosen
										) }
									</span>
								) : null }
							</span>
							<span className="inline-flex items-center gap-2 text-xs text-muted-foreground">
								{ sprintf(
									/* translators: 1: free rooms on the floor, 2: rooms on the floor. */
									__(
										'%1$d of %2$d free',
										'radius-hotel-booking'
									),
									open,
									total
								) }
								<span
									className="h-1.5 w-14 overflow-hidden rounded-full bg-border"
									aria-hidden="true"
								>
									<span
										className="block h-full rounded-full bg-success"
										style={ {
											width: `${
												total
													? ( open / total ) * 100
													: 0
											}%`,
										} }
									/>
								</span>
							</span>
						</header>
						<div className="grid grid-cols-[repeat(auto-fill,minmax(5.5rem,1fr))] gap-2 p-3">
							{ floor.rooms.map( ( room ) => {
								const inBooking = taken.includes( room.id );
								const free = isFree( room );
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
								const on = isOn( room );
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
											onToggle || selected
												? on
												: undefined
										}
										className={ cn(
											'group relative flex h-16 flex-col items-center justify-center gap-0.5 rounded-lg border px-2 transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 disabled:opacity-60',
											// Themes style button:hover/focus: pin the colours.
											on
												? 'border-primary bg-primary text-white shadow-sm hover:bg-primary hover:text-white focus:bg-primary focus:text-white'
												: 'border-border bg-white text-heading hover:-translate-y-px hover:border-primary hover:bg-primary-softer hover:text-heading hover:shadow-sm focus:bg-white focus:text-heading'
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
											<>
												<span className="inline-flex items-center gap-1 text-sm font-semibold">
													<DoorOpen
														className={ cn(
															'h-3.5 w-3.5',
															on
																? 'text-white/80'
																: 'text-muted-foreground group-hover:text-primary'
														) }
														aria-hidden="true"
													/>
													{ room.number }
												</span>
												<span
													className={ cn(
														'text-[11px] font-medium leading-tight',
														on
															? 'text-white/90'
															: 'text-success'
													) }
												>
													{ on
														? __(
																'Selected',
																'radius-hotel-booking'
														  )
														: __(
																'Free',
																'radius-hotel-booking'
														  ) }
												</span>
											</>
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
											'flex h-16 flex-col items-center justify-center gap-0.5 rounded-lg border px-2 text-sm',
											inBooking
												? 'border-primary bg-primary-soft text-primary'
												: 'border-dashed border-border bg-muted text-muted-foreground'
										) }
									>
										<span className="font-semibold">
											{ room.number }
										</span>
										<span className="text-[11px] leading-tight">
											{ why }
										</span>
										<span className="sr-only">
											{ hint }
										</span>
									</div>
								);
							} ) }
						</div>
					</section>
				);
			} ) }
		</div>
	);
}
