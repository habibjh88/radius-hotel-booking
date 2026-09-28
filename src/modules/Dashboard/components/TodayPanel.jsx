/**
 * Today at the front desk: who arrives and who leaves today.
 */
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { LogIn, LogOut } from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import SegmentedControl from '@/components/common/SegmentedControl';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * One arrival or departure.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item `{ id, time, guest, room, room_type }`.
 * @return {JSX.Element} Row.
 */
function Row( { item } ) {
	return (
		<li className="m-0 flex items-center gap-4 border-b border-border py-3 last:border-b-0">
			<span className="w-14 shrink-0 text-sm font-semibold tabular-nums text-heading">
				{ item.time }
			</span>
			<div className="min-w-0 flex-1">
				<p className="m-0 truncate text-sm font-medium text-heading">
					{ item.guest }
				</p>
				<p className="m-0 truncate text-xs text-muted-foreground">
					{ item.room_type }
				</p>
			</div>
			<span className="shrink-0 rounded-md bg-primary-soft px-2 py-1 text-xs font-semibold text-primary">
				{ item.room }
			</span>
		</li>
	);
}

/**
 * @param {Object}  props         Props.
 * @param {Object}  props.today   `{ arrivals: [], departures: [] }`.
 * @param {boolean} props.loading Loading.
 * @return {JSX.Element} Panel.
 */
export default function TodayPanel( { today, loading } ) {
	const [ tab, setTab ] = useState( 'arrivals' );
	const arrivals = today?.arrivals || [];
	const departures = today?.departures || [];
	const items = tab === 'arrivals' ? arrivals : departures;

	return (
		<Panel
			className="h-full"
			title={ __( 'Today at the front desk', 'radius-hotel-booking' ) }
			description={ __( 'Guests arriving and leaving today', 'radius-hotel-booking' ) }
			actions={
				<SegmentedControl
					label={ __( 'Show', 'radius-hotel-booking' ) }
					value={ tab }
					onChange={ setTab }
					options={ [
						{
							value: 'arrivals',
							label: __( 'Arrivals', 'radius-hotel-booking' ),
							count: arrivals.length,
						},
						{
							value: 'departures',
							label: __( 'Departures', 'radius-hotel-booking' ),
							count: departures.length,
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
				<ul className="m-0 list-none p-0">
					{ items.map( ( item ) => (
						<Row key={ item.id } item={ item } />
					) ) }
				</ul>
			) : null }

			{ ! loading && ! items.length ? (
				<EmptyState
					icon={ tab === 'arrivals' ? LogIn : LogOut }
					title={
						tab === 'arrivals'
							? __( 'No arrivals today', 'radius-hotel-booking' )
							: __( 'No departures today', 'radius-hotel-booking' )
					}
					description={ __(
						'Bookings that start or end today will be listed here, in time order.',
						'radius-hotel-booking'
					) }
					action={
						<Link
							to="/bookings"
							className="text-sm font-semibold text-primary no-underline hover:underline"
						>
							{ __( 'View all bookings', 'radius-hotel-booking' ) }
						</Link>
					}
				/>
			) : null }
		</Panel>
	);
}
