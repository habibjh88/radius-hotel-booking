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
import Panel from '@/components/common/Panel';
import StatCard from '@/components/common/StatCard';
import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
		<div className="space-y-5">
			<div className="flex flex-wrap items-end gap-3">
				<label className="space-y-1 text-sm">
					<span className="block font-medium text-heading">
						{ __( 'From time', 'radius-hotel-booking' ) }
					</span>
					<Input
						type="time"
						value={ fromTime }
						onChange={ ( e ) =>
							setTimes( { from_time: e.target.value } )
						}
						className="h-10 w-36"
					/>
				</label>
				<label className="space-y-1 text-sm">
					<span className="block font-medium text-heading">
						{ __( 'To time', 'radius-hotel-booking' ) }
					</span>
					<Input
						type="time"
						value={ toTime }
						onChange={ ( e ) =>
							setTimes( { to_time: e.target.value } )
						}
						className="h-10 w-36"
					/>
				</label>
				<Button type="button" variant="outline" onClick={ rightNow }>
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

			<ul
				className="m-0 flex list-none flex-wrap gap-3 p-0 text-sm"
				aria-label={ __( 'Legend', 'radius-hotel-booking' ) }
			>
				{ [ ...LEGEND, 'maintenance', 'out_of_service' ].map(
					( status ) => {
						const { label, tone } = statusOf( status );
						return (
							<li
								key={ status }
								className="flex items-center gap-2 text-muted-foreground"
							>
								<span
									className={ cn(
										'h-3.5 w-3.5 rounded border',
										TONE_CLASSES[ tone ]?.[ 0 ]
									) }
									style={ {
										borderColor: TONE_COLOR[ tone ],
									} }
									aria-hidden="true"
								/>
								{ label }
							</li>
						);
					}
				) }
			</ul>

			{ loading ? (
				<Skeleton className="h-64 w-full" />
			) : data?.room_types?.length ? (
				data.room_types.map( ( type ) => (
					<Panel
						key={ type.id }
						title={ type.name }
						description={ sprintf(
							/* translators: 1: occupied rooms, 2: rooms. */
							_n(
								'%1$d of %2$d room occupied',
								'%1$d of %2$d rooms occupied',
								type.rooms,
								'radius-hotel-booking'
							),
							type.occupied,
							type.rooms
						) }
					>
						{ type.floors.length ? (
							<div className="space-y-4">
								{ type.floors.map( ( floor ) => (
									<div
										key={ floor.floor }
										className="space-y-2"
									>
										<p className="m-0 text-sm font-semibold text-heading">
											{ floor.floor }
										</p>
										<ul className="m-0 flex list-none flex-wrap gap-2 p-0">
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
							<p className="m-0 text-sm text-muted-foreground">
								{ __(
									'This room type has no rooms.',
									'radius-hotel-booking'
								) }
							</p>
						) }
					</Panel>
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
 * One room: its number in its status colour; opens its bookings.
 *
 * @param {Object} props      Props.
 * @param {Object} props.room `{ number, status, bookings }`.
 * @return {JSX.Element} Tile.
 */
function RoomTile( { room } ) {
	const { label, tone } = statusOf( room.status );
	const count = room.bookings.length;
	return (
		<Popover>
			<PopoverTrigger asChild>
				<button
					type="button"
					className={ cn(
						'relative flex h-12 min-w-[3.5rem] flex-col items-center justify-center rounded-lg border px-2 text-sm font-semibold tabular-nums',
						TONE_CLASSES[ tone ]?.[ 0 ]
					) }
					style={ {
						borderColor: `color-mix(in srgb, ${ TONE_COLOR[ tone ] } 45%, transparent)`,
					} }
					aria-label={ sprintf(
						/* translators: 1: room number, 2: status, e.g. Booked. */
						__( 'Room %1$s: %2$s', 'radius-hotel-booking' ),
						room.number,
						label
					) }
				>
					{ room.number }
					{ count ? (
						<span className="absolute -right-1.5 -top-1.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-primary px-1 text-[11px] font-bold text-primary-foreground">
							{ count }
						</span>
					) : null }
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
