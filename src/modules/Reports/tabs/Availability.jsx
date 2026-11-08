/**
 * Room availability (M10, 10.12–10.13): every room, by room type then floor,
 * as it stands for a window — booked, held, blocked, free, or its own state
 * (maintenance, out of service) — with the window's counters. Live: the
 * server never caches it, holds included.
 *
 * The window is the period's days plus optional times (`?from_time=&to_time=`,
 * site time); *Right now* looks at this minute. A room's tile opens the booked
 * rooms listed for it (by the date mode: overlapping the window, or taken in it).
 */
import { useSearchParams, Link } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AlertCircle,
	BedDouble,
	CalendarCheck,
	Clock,
	DoorOpen,
	KeyRound,
} from 'lucide-react';

import DateTime from '@/components/common/DateTime';
import EmptyState from '@/components/common/EmptyState';
import StatCard from '@/components/common/StatCard';
import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import { TimePicker } from '@/components/common/DatePicker';
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { siteNowTime, siteToday } from '@/lib/format';
import { getStatus, TONE_CLASSES, TONE_COLOR } from '@/lib/status';
import { cn } from '@/lib/utils';
import { useReport } from '../api';

const LEGEND = [ 'free', 'booked', 'held', 'blocked' ];

/**
 * A tile's border: its tone, softened.
 *
 * @param {string} tone Tone.
 * @return {string} CSS colour.
 */
const tileBorder = ( tone ) =>
	`color-mix(in srgb, ${ TONE_COLOR[ tone ] } 45%, transparent)`;

/**
 * A grid status's label and tone (room states come from the `room` domain).
 *
 * @param {string} status Grid status.
 * @return {Object} `{ label, tone }`.
 */
const statusOf = ( status ) =>
	LEGEND.includes( status )
		? getStatus( 'availability', status )
		: getStatus( 'room', status );

/**
 * @param {Object} props       Props.
 * @param {Object} props.range `{ from, to, mode }`.
 * @return {JSX.Element} Tab.
 */
export default function Availability( { range } ) {
	const [ params, setParams ] = useSearchParams();
	const fromTime = params.get( 'from_time' ) || '';
	const toTime = params.get( 'to_time' ) || '';

	const setTimes = ( changes ) =>
		setParams(
			( prev ) => {
				const next = new URLSearchParams( prev );
				Object.entries( changes ).forEach( ( [ key, value ] ) =>
					value ? next.set( key, value ) : next.delete( key )
				);
				return next;
			},
			{ replace: true }
		);

	// This minute: today, from now to one minute later.
	const rightNow = () => {
		const now = siteNowTime();
		const [ hour, minute ] = now.split( ':' ).map( Number );
		const later =
			hour === 23 && minute === 59
				? ''
				: `${ String( minute === 59 ? hour + 1 : hour ).padStart(
						2,
						'0'
				  ) }:${ String( ( minute + 1 ) % 60 ).padStart( 2, '0' ) }`;
		setTimes( {
			from: siteToday(),
			to: siteToday(),
			from_time: now,
			to_time: later,
		} );
	};

	const report = useReport( 'availability', {
		...range,
		from_time: fromTime,
		to_time: toTime,
	} );
	const data = report.data;
	const counters = data?.counters || {};
	const loading = report.isPending;
	const fieldErrors = report.error?.errors || {};

	return (
		<div className="space-y-6">
			<div className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
				<div>
					<p className="m-0 text-sm font-semibold text-heading">
						{ __( 'Time of day', 'radius-hotel-booking' ) }
					</p>
					<p className="m-0 mt-1 text-sm text-muted-foreground">
						{ __(
							'Optional. Leave empty to look at whole days, or use Right now for this minute.',
							'radius-hotel-booking'
						) }
					</p>
				</div>
				<div className="flex flex-wrap items-end gap-4">
					<label className="space-y-1.5 text-sm">
						<span className="block font-medium text-heading">
							{ __( 'From time', 'radius-hotel-booking' ) }
						</span>
						<TimePicker
							value={ fromTime }
							onChange={ ( from_time ) =>
								setTimes( { from_time } )
							}
							className="w-40"
						/>
					</label>
					<label className="space-y-1.5 text-sm">
						<span className="block font-medium text-heading">
							{ __( 'To time', 'radius-hotel-booking' ) }
						</span>
						<TimePicker
							value={ toTime }
							onChange={ ( to_time ) => setTimes( { to_time } ) }
							className="w-40"
						/>
					</label>
					<Button
						type="button"
						variant="outline"
						onClick={ rightNow }
					>
						<Clock aria-hidden="true" />
						{ __( 'Right now', 'radius-hotel-booking' ) }
					</Button>
					{ fromTime || toTime ? (
						<Button
							type="button"
							variant="ghost"
							onClick={ () =>
								setTimes( { from_time: '', to_time: '' } )
							}
						>
							{ __( 'Whole days', 'radius-hotel-booking' ) }
						</Button>
					) : null }
				</div>
			</div>

			{ report.isError ? (
				<p
					className="m-0 flex items-center gap-2 text-sm text-destructive"
					role="alert"
				>
					<AlertCircle className="h-4 w-4" aria-hidden="true" />
					{ fieldErrors.to_time?.first_message ||
						fieldErrors.from_time?.first_message ||
						report.error?.message }
				</p>
			) : null }

			<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
				<StatCard
					icon={ BedDouble }
					tone="purple"
					label={ __( 'Rooms', 'radius-hotel-booking' ) }
					value={ counters.rooms ?? 0 }
					hint={ __(
						'Every room of the hotel',
						'radius-hotel-booking'
					) }
					loading={ loading }
				/>
				<StatCard
					icon={ KeyRound }
					tone="red"
					label={ __( 'Occupied', 'radius-hotel-booking' ) }
					value={ counters.occupied ?? 0 }
					hint={ __(
						'A stay at some moment of the window',
						'radius-hotel-booking'
					) }
					loading={ loading }
				/>
				<StatCard
					icon={ DoorOpen }
					tone="green"
					label={ __( 'Available', 'radius-hotel-booking' ) }
					value={ counters.available ?? 0 }
					hint={ __(
						'Free for the whole window',
						'radius-hotel-booking'
					) }
					loading={ loading }
				/>
				<StatCard
					icon={ CalendarCheck }
					tone="orange"
					label={ __( 'Bookings', 'radius-hotel-booking' ) }
					value={ counters.bookings ?? 0 }
					hint={
						'created' === range.mode
							? __(
									'Booked rooms taken in the window',
									'radius-hotel-booking'
							  )
							: __(
									'Booked rooms staying in the window',
									'radius-hotel-booking'
							  )
					}
					loading={ loading }
				/>
			</div>

			{ loading ? (
				<Skeleton className="h-64 w-full" />
			) : data?.room_types?.length ? (
				data.room_types.map( ( type ) => (
					<section
						key={ type.id }
						className="rounded-xl border border-border bg-card p-5 shadow-sm"
					>
						<TypeHeader type={ type } />
						{ type.floors.length ? (
							<div className="divide-y divide-dashed divide-border">
								{ type.floors.map( ( floor ) => (
									<div
										key={ floor.floor }
										className="space-y-5 py-8 last:pb-0"
									>
										<div className="flex flex-wrap items-center gap-x-3 gap-y-2">
											<h4 className="m-0 text-base font-semibold text-heading">
												{ floor.floor }
											</h4>
											<FloorCounts
												rooms={ floor.rooms }
											/>
										</div>
										<ul className="m-0 flex list-none flex-wrap gap-x-3 gap-y-4 p-0">
											{ floor.rooms.map( ( room ) => (
												<li key={ room.id }>
													<RoomTile room={ room } />
												</li>
											) ) }
										</ul>
									</div>
								) ) }
							</div>
						) : (
							<p className="m-0 pt-5 text-sm text-muted-foreground">
								{ __(
									'This room type has no rooms.',
									'radius-hotel-booking'
								) }
							</p>
						) }
					</section>
				) )
			) : (
				<EmptyState
					icon={ BedDouble }
					title={ __( 'No rooms yet', 'radius-hotel-booking' ) }
					description={ __(
						'Add room types and rooms in Rooms & floors.',
						'radius-hotel-booking'
					) }
				/>
			) }
		</div>
	);
}

/**
 * A floor's free and booked rooms, as chips in the tiles' colours.
 *
 * @param {Object}   props       Props.
 * @param {Object[]} props.rooms The floor's rooms.
 * @return {JSX.Element} Chips.
 */
function FloorCounts( { rooms } ) {
	const chips = [
		{
			status: 'free',
			/* translators: %d: number of free rooms. */
			text: __( '%d free', 'radius-hotel-booking' ),
		},
		{
			status: 'booked',
			/* translators: %d: number of booked rooms. */
			text: __( '%d booked', 'radius-hotel-booking' ),
		},
	];
	return (
		<div className="flex flex-wrap gap-1.5">
			{ chips.map( ( { status, text } ) => {
				const { tone } = statusOf( status );
				return (
					<span
						key={ status }
						className={ cn(
							'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-semibold tabular-nums',
							TONE_CLASSES[ tone ]?.[ 0 ]
						) }
						style={ { borderColor: tileBorder( tone ) } }
					>
						<span
							className="h-1.5 w-1.5 rounded-full"
							style={ { background: TONE_COLOR[ tone ] } }
							aria-hidden="true"
						/>
						{ sprintf(
							text,
							rooms.filter( ( room ) => room.status === status )
								.length
						) }
					</span>
				);
			} ) }
		</div>
	);
}

/**
 * A tile's look per grid status, as on the old dashboard: a free room is
 * plain white; booked is red; held amber; a room that cannot be used
 * (maintenance, out of service, blocked) grey with a faded number.
 */
const TILE = {
	free: { tile: 'bg-card border-border', number: 'text-heading' },
	booked: {
		tile: 'bg-destructive-soft',
		number: 'text-heading',
		border: TONE_COLOR.danger,
	},
	held: {
		tile: 'bg-warning-soft',
		number: 'text-heading',
		border: TONE_COLOR.warning,
	},
	unusable: {
		tile: 'bg-muted border-border',
		number: 'text-muted-foreground',
	},
};

/**
 * The look of one status.
 *
 * @param {string} status Grid status.
 * @return {Object} `{ tile, number, border? }`.
 */
const tileOf = ( status ) => TILE[ status ] || TILE.unusable;

/**
 * The badge on a tile's top edge: none for a free room.
 *
 * @param {string} status Grid status.
 * @return {string} Label, or ''.
 */
const badgeOf = ( status ) =>
	'free' === status
		? ''
		: 'booked' === status
		? __( 'Occupied', 'radius-hotel-booking' )
		: statusOf( status ).label;

/**
 * "not booked", "booked 1 time", "booked 3 times".
 *
 * @param {number} count Bookings of the room in the window.
 * @return {string} Line.
 */
const bookedTimes = ( count ) =>
	count
		? sprintf(
				/* translators: %d: how many times the room was booked in the window. */
				_n(
					'booked %d time',
					'booked %d times',
					count,
					'radius-hotel-booking'
				),
				count
		  )
		: __( 'not booked', 'radius-hotel-booking' );

/**
 * A room type's header, as on the old dashboard: its name, rooms, occupied
 * rooms and bookings as small labelled figures.
 *
 * @param {Object} props      Props.
 * @param {Object} props.type Room type of the report.
 * @return {JSX.Element} Header.
 */
function TypeHeader( { type } ) {
	// A booking with several rooms of the type counts once.
	const bookings = new Set();
	type.floors.forEach( ( floor ) =>
		floor.rooms.forEach( ( room ) =>
			room.bookings.forEach( ( booking ) =>
				bookings.add( booking.booking_id )
			)
		)
	);
	const figures = [
		[ __( 'Name', 'radius-hotel-booking' ), type.name ],
		[ __( 'Rooms', 'radius-hotel-booking' ), type.rooms ],
		[ __( 'Occupied', 'radius-hotel-booking' ), type.occupied ],
		[ __( 'Bookings', 'radius-hotel-booking' ), bookings.size ],
	];
	return (
		<dl className="m-0 flex flex-wrap gap-x-8 gap-y-3 border-b border-border pb-5">
			{ figures.map( ( [ label, value ] ) => (
				<div key={ label } className="min-w-0">
					<dt className="text-[11px] font-semibold uppercase tracking-[0.06em] text-muted-foreground">
						{ label }
					</dt>
					<dd className="m-0 mt-1 text-lg font-semibold leading-tight tabular-nums text-heading">
						{ value }
					</dd>
				</div>
			) ) }
		</dl>
	);
}

/**
 * One room: its number, how often it was booked in the window, and a badge
 * for any state but free; opens its bookings.
 *
 * @param {Object} props      Props.
 * @param {Object} props.room `{ number, status, bookings }`.
 * @return {JSX.Element} Tile.
 */
function RoomTile( { room } ) {
	const { label, tone } = statusOf( room.status );
	const count = room.bookings.length;
	const look = tileOf( room.status );
	const badge = badgeOf( room.status );
	return (
		<Popover>
			<PopoverTrigger asChild>
				<button
					type="button"
					className={ cn(
						'relative flex h-[5.5rem] w-[7.5rem] flex-col items-center justify-center gap-1.5 rounded-lg border px-3 transition-shadow hover:shadow-md',
						look.tile
					) }
					style={
						look.border
							? {
									borderColor: `color-mix(in srgb, ${ look.border } 45%, transparent)`,
							  }
							: undefined
					}
					aria-label={ sprintf(
						/* translators: 1: room number, 2: status, e.g. Booked, 3: e.g. "booked 2 times". */
						__( 'Room %1$s: %2$s, %3$s', 'radius-hotel-booking' ),
						room.number,
						label,
						bookedTimes( count )
					) }
				>
					{ badge ? (
						<span
							className="absolute -top-2.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full px-2 py-0.5 text-[10px] font-bold uppercase leading-3 tracking-wide text-white"
							style={ {
								background:
									TONE_COLOR[
										'booked' === room.status ||
										'held' === room.status
											? tone
											: 'neutral'
									],
							} }
						>
							{ badge }
						</span>
					) : null }
					<span
						className={ cn(
							'text-2xl font-bold leading-none tabular-nums',
							look.number
						) }
					>
						{ room.number }
					</span>
					<span className="text-xs text-muted-foreground">
						{ bookedTimes( count ) }
					</span>
				</button>
			</PopoverTrigger>
			<PopoverContent className="rtbp-root w-80 max-w-[calc(100vw-24px)] p-0">
				<div className="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
					<p className="m-0 font-semibold text-heading">
						{ sprintf(
							/* translators: %s: room number. */
							__( 'Room %s', 'radius-hotel-booking' ),
							room.number
						) }
					</p>
					<span
						className={ cn(
							'rounded-full px-2 py-0.5 text-xs font-medium',
							TONE_CLASSES[ tone ]?.[ 0 ]
						) }
					>
						{ label }
					</span>
				</div>
				{ count ? (
					<ul className="m-0 max-h-72 list-none space-y-3 overflow-y-auto p-4">
						{ room.bookings.map( ( booking ) => (
							<li
								key={ booking.line_id }
								className="space-y-1 text-sm"
							>
								<div className="flex items-center justify-between gap-2">
									<Link
										to={ `/bookings/${ booking.booking_id }` }
										className="font-semibold text-primary"
									>
										{ booking.reference }
									</Link>
									<StatusBadge
										domain="stay"
										value={ booking.status }
									/>
								</div>
								<p className="m-0 text-muted-foreground">
									{ booking.guest }
								</p>
								<DateTime
									value={ booking.start_at }
									end={ booking.end_at }
									className="text-muted-foreground"
								/>
							</li>
						) ) }
					</ul>
				) : (
					<p className="m-0 p-4 text-sm text-muted-foreground">
						{ __(
							'No booking for this room in the window.',
							'radius-hotel-booking'
						) }
					</p>
				) }
			</PopoverContent>
		</Popover>
	);
}
