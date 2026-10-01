/**
 * Today at the front desk (M01, 1.11): one timeline of what is due today —
 * arrivals and departures as booked — merged with what the desk actually did
 * (check-ins, check-outs), each `todo`, `done` or `overdue`. Arrivals and
 * departures can also be shown on their own. The server builds the list
 * (`dashboard/summary` → `today`); this only draws it.
 */
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import {
	CalendarCheck,
	CheckCircle2,
	Circle,
	LogIn,
	LogOut,
} from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import SegmentedControl from '@/components/common/SegmentedControl';
import { Skeleton } from '@/components/ui/skeleton';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * What an event says, by kind.
 *
 * @param {string} kind arrival | departure | checked_in | checked_out.
 * @return {string} Label.
 */
const kindLabel = ( kind ) =>
	( {
		arrival: __( 'Arrives', 'radius-hotel-booking' ),
		departure: __( 'Leaves', 'radius-hotel-booking' ),
		checked_in: __( 'Checked in', 'radius-hotel-booking' ),
		checked_out: __( 'Checked out', 'radius-hotel-booking' ),
	} )[ kind ] || kind;

/**
 * The state in words, for screen readers and the overdue tag.
 *
 * @param {string} state todo | done | overdue.
 * @return {string} Label.
 */
const stateLabel = ( state ) =>
	( {
		todo: __( 'To do', 'radius-hotel-booking' ),
		done: __( 'Done', 'radius-hotel-booking' ),
		overdue: __( 'Late', 'radius-hotel-booking' ),
	} )[ state ] || state;

/**
 * One event.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item `{ id, at, kind, type, state, guest, room, room_type, booking_id }`.
 * @return {JSX.Element} Row.
 */
function Row( { item } ) {
	const actual = 'actual' === item.type;
	const Icon = 'done' === item.state ? CheckCircle2 : Circle;
	return (
		<li className="m-0 border-b border-border last:border-b-0">
			<Link
				to={ `/bookings/${ item.booking_id }` }
				className="flex items-center gap-3 py-3 text-inherit no-underline hover:bg-primary-softer"
			>
				<span className="w-[4.75rem] shrink-0 whitespace-nowrap text-sm font-semibold tabular-nums text-heading">
					{ formatTime( item.at ) }
				</span>
				<Icon
					className={ cn(
						'h-4 w-4 shrink-0',
						'done' === item.state && 'text-success',
						'todo' === item.state && 'text-muted-foreground',
						'overdue' === item.state &&
							'fill-destructive text-destructive'
					) }
					aria-label={ stateLabel( item.state ) }
				/>
				<div className="min-w-0 flex-1">
					<p
						className={ cn(
							'm-0 truncate text-sm text-heading',
							actual ? 'font-normal' : 'font-medium'
						) }
					>
						{ kindLabel( item.kind ) }
						{ ' · ' }
						{ item.guest || item.reference }
					</p>
					<p className="m-0 truncate text-xs text-muted-foreground">
						{ item.room_type }
					</p>
				</div>
				{ 'overdue' === item.state ? (
					<span className="shrink-0 rounded-md bg-destructive-soft px-2 py-1 text-xs font-semibold text-destructive">
						{ stateLabel( item.state ) }
					</span>
				) : null }
				<span className="shrink-0 rounded-md bg-primary-soft px-2 py-1 text-xs font-semibold text-primary">
					{ item.room }
				</span>
			</Link>
		</li>
	);
}

/**
 * @param {Object}  props         Props.
 * @param {Object}  props.today   `{ events: [], arrivals: [], departures: [] }`.
 * @param {boolean} props.loading Loading.
 * @return {JSX.Element} Panel.
 */
export default function TodayPanel( { today, loading } ) {
	const [ tab, setTab ] = useState( 'events' );
	const lists = {
		events: today?.events || [],
		arrivals: today?.arrivals || [],
		departures: today?.departures || [],
	};
	const items = lists[ tab ];
	const empty = {
		events: {
			icon: CalendarCheck,
			title: __( 'Nothing due today', 'radius-hotel-booking' ),
		},
		arrivals: {
			icon: LogIn,
			title: __( 'No arrivals today', 'radius-hotel-booking' ),
		},
		departures: {
			icon: LogOut,
			title: __( 'No departures today', 'radius-hotel-booking' ),
		},
	}[ tab ];

	return (
		<Panel
			className="h-full"
			title={ __( 'Today at the front desk', 'radius-hotel-booking' ) }
			description={ __(
				'Arrivals and departures, and what the desk has done',
				'radius-hotel-booking'
			) }
			actions={
				<SegmentedControl
					label={ __( 'Show', 'radius-hotel-booking' ) }
					value={ tab }
					onChange={ setTab }
					options={ [
						{
							value: 'events',
							label: __( 'Timeline', 'radius-hotel-booking' ),
							count: lists.events.length,
						},
						{
							value: 'arrivals',
							label: __( 'Arrivals', 'radius-hotel-booking' ),
							count: lists.arrivals.length,
						},
						{
							value: 'departures',
							label: __( 'Departures', 'radius-hotel-booking' ),
							count: lists.departures.length,
						},
					] }
				/>
			}
		>
			{ loading ? (
				<div className="space-y-3">
					{ [ 0, 1, 2 ].map( ( key ) => (
						<Skeleton key={ key } className="h-12 w-full" />
					) ) }
				</div>
			) : null }

			{ ! loading && items.length ? (
				<ul className="m-0 max-h-[26rem] list-none overflow-y-auto p-0">
					{ items.map( ( item ) => (
						<Row key={ item.id } item={ item } />
					) ) }
				</ul>
			) : null }

			{ ! loading && ! items.length ? (
				<EmptyState
					icon={ empty.icon }
					title={ empty.title }
					description={ __(
						'Bookings that start or end today will be listed here, in time order.',
						'radius-hotel-booking'
					) }
					action={
						<Link
							to="/bookings"
							className="text-sm font-semibold text-primary no-underline hover:underline"
						>
							{ __(
								'View all bookings',
								'radius-hotel-booking'
							) }
						</Link>
					}
				/>
			) : null }
		</Panel>
	);
}
