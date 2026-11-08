/**
 * Rooms report (M10, 10.8–10.11): the rooms by state, how many available
 * rooms were rented or stayed empty in the period, which ones were empty
 * floor by floor, and the period's booked rooms (`GET reports/rooms`).
 *
 * "Rented" means a stay occupied the room at some moment of the period —
 * a multi-night stay that started earlier counts (the legacy report only
 * counted check-ins inside the window).
 */
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AlertCircle,
	BedDouble,
	DoorOpen,
	FileText,
	KeyRound,
	Pencil,
	PowerOff,
	Wrench,
} from 'lucide-react';

import DataTable from '@/components/common/DataTable';
import DateTime from '@/components/common/DateTime';
import EmptyState from '@/components/common/EmptyState';
import Money from '@/components/common/Money';
import Panel from '@/components/common/Panel';
import StatCard from '@/components/common/StatCard';
import GuestLink from '@/components/common/GuestLink';
import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useReport } from '../api';

// An empty room's border: the success colour, softened (as on Room availability).
const SUCCESS_BORDER = 'color-mix(in srgb, var(--success) 45%, transparent)';

const PER_PAGE = 25;

/**
 * @param {Object} props       Props.
 * @param {Object} props.range `{ from, to, mode }`.
 * @return {JSX.Element} Tab.
 */
export default function Rooms( { range } ) {
	const navigate = useNavigate();
	const [ page, setPage ] = useState( 1 );
	// A new period starts the table on its first page.
	useEffect( () => setPage( 1 ), [ range.from, range.to, range.mode ] );

	const report = useReport( 'rooms', {
		...range,
		page,
		per_page: PER_PAGE,
	} );
	const data = report.data;
	const cards = data?.cards || {};
	const loading = report.isPending;

	if ( report.isError && ! data ) {
		return (
			<Panel>
				<div className="flex flex-col items-center gap-3 py-10 text-center">
					<AlertCircle
						className="h-8 w-8 text-destructive"
						aria-hidden="true"
					/>
					<p className="m-0 text-sm text-muted-foreground">
						{ report.error?.message ||
							__(
								'The report could not be loaded.',
								'radius-hotel-booking'
							) }
					</p>
					<Button
						variant="outline"
						onClick={ () => report.refetch() }
					>
						{ __( 'Try again', 'radius-hotel-booking' ) }
					</Button>
				</div>
			</Panel>
		);
	}

	const inPeriod =
		'created' === range.mode
			? __( 'by bookings taken in the period', 'radius-hotel-booking' )
			: __( 'at any moment of the period', 'radius-hotel-booking' );

	const columns = [
		{
			id: 'reference',
			header: __( 'Booking', 'radius-hotel-booking' ),
			cell: ( row ) => (
				<span className="font-medium text-heading">
					{ row.reference }
				</span>
			),
			mobile: 'title',
		},
		{
			id: 'guest',
			header: __( 'Guest', 'radius-hotel-booking' ),
			cell: ( row ) => (
				<GuestLink id={ row.guest_id } name={ row.guest } />
			),
			mobile: 'subtitle',
		},
		{
			id: 'created',
			header: __( 'Booked on', 'radius-hotel-booking' ),
			cell: ( row ) => <DateTime value={ row.created_at } show="date" />,
		},
		{
			id: 'room_type',
			header: __( 'Room type', 'radius-hotel-booking' ),
			cell: ( row ) => row.room_type,
		},
		{
			id: 'room',
			header: __( 'Room', 'radius-hotel-booking' ),
			cell: ( row ) => row.room,
		},
		{
			id: 'stay',
			header: __( 'Arrival – departure', 'radius-hotel-booking' ),
			cell: ( row ) => (
				<DateTime value={ row.start_at } end={ row.end_at } />
			),
		},
		{
			id: 'payment',
			header: __( 'Payment', 'radius-hotel-booking' ),
			cell: ( row ) => (
				<StatusBadge domain="payment" value={ row.payment_status } />
			),
		},
		{
			id: 'status',
			header: __( 'Status', 'radius-hotel-booking' ),
			cell: ( row ) => <StatusBadge domain="stay" value={ row.status } />,
		},
		{
			id: 'total',
			header: __( 'Subtotal', 'radius-hotel-booking' ),
			cell: ( row ) => <Money value={ row.total } />,
			align: 'right',
		},
	];

	const floors = data?.empty_floors || [];

	return (
		<div className="space-y-5">
			<div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
				<StatCard
					icon={ BedDouble }
					label={ __( 'Available', 'radius-hotel-booking' ) }
					value={ cards.available ?? 0 }
					hint={ sprintf(
						/* translators: %d: number of rooms. */
						_n(
							'of %d room',
							'of %d rooms',
							cards.total || 0,
							'radius-hotel-booking'
						),
						cards.total || 0
					) }
					loading={ loading }
				/>
				<StatCard
					icon={ KeyRound }
					label={ __( 'Rented', 'radius-hotel-booking' ) }
					value={ cards.rented ?? 0 }
					hint={ sprintf(
						/* translators: %s: "at any moment of the period" or "by bookings taken in the period". */
						__( 'Occupied %s', 'radius-hotel-booking' ),
						inPeriod
					) }
					loading={ loading }
				/>
				<StatCard
					icon={ DoorOpen }
					label={ __( 'Empty', 'radius-hotel-booking' ) }
					value={ cards.empty ?? 0 }
					hint={ __(
						'Available rooms nobody stayed in',
						'radius-hotel-booking'
					) }
					loading={ loading }
				/>
				<StatCard
					icon={ Wrench }
					label={ __( 'Maintenance', 'radius-hotel-booking' ) }
					value={ cards.maintenance ?? 0 }
					hint={ __(
						'Rooms in this state now',
						'radius-hotel-booking'
					) }
					loading={ loading }
				/>
				<StatCard
					icon={ PowerOff }
					label={ __( 'Out of service', 'radius-hotel-booking' ) }
					value={ cards.out_of_service ?? 0 }
					hint={ __(
						'Rooms in this state now',
						'radius-hotel-booking'
					) }
					loading={ loading }
				/>
			</div>

			<Panel
				title={ __( 'Empty rooms by floor', 'radius-hotel-booking' ) }
				description={ __(
					'Available rooms that no stay occupied in the period.',
					'radius-hotel-booking'
				) }
			>
				{ loading ? (
					<Skeleton className="h-20 w-full" />
				) : floors.length ? (
					<div className="divide-y divide-border">
						{ floors.map( ( floor ) => (
							<div
								key={ floor.floor }
								className="flex flex-col gap-3 py-5 first:pt-0 last:pb-0 md:flex-row md:gap-6"
							>
								<div className="shrink-0 md:w-48">
									<p className="m-0 text-sm font-semibold text-heading">
										{ floor.floor }
									</p>
									<span
										className="mt-2 inline-flex items-center gap-1.5 rounded-full border bg-success-soft px-2 py-0.5 text-xs font-semibold tabular-nums text-success"
										style={ {
											borderColor: SUCCESS_BORDER,
										} }
									>
										<span
											className="h-1.5 w-1.5 rounded-full bg-success"
											aria-hidden="true"
										/>
										{ sprintf(
											/* translators: %d: number of empty rooms. */
											_n(
												'%d empty room',
												'%d empty rooms',
												floor.rooms.length,
												'radius-hotel-booking'
											),
											floor.rooms.length
										) }
									</span>
								</div>
								<ul className="m-0 flex list-none flex-wrap gap-3 p-0">
									{ floor.rooms.map( ( room ) => (
										<li
											key={ room.id }
											className="flex h-[4.25rem] min-w-[5.5rem] flex-col items-center justify-center gap-0.5 rounded-lg border bg-success-soft px-3 text-success"
											style={ {
												borderColor: SUCCESS_BORDER,
											} }
										>
											<span className="text-base font-semibold leading-tight tabular-nums">
												{ room.number }
											</span>
											{ room.room_type ? (
												<span className="max-w-[7rem] truncate text-[11px] font-medium leading-tight opacity-80">
													{ room.room_type }
												</span>
											) : null }
										</li>
									) ) }
								</ul>
							</div>
						) ) }
					</div>
				) : (
					<p className="m-0 text-sm text-muted-foreground">
						{ __(
							'Every available room was rented in this period.',
							'radius-hotel-booking'
						) }
					</p>
				) }
			</Panel>

			<Panel
				title={ __( 'Booked rooms', 'radius-hotel-booking' ) }
				description={ sprintf(
					/* translators: %s: "at any moment of the period" or "by bookings taken in the period". */
					__(
						'Pending, confirmed, in-house and departed rooms, %s.',
						'radius-hotel-booking'
					),
					inPeriod
				) }
			>
				<DataTable
					columns={ columns }
					rows={ data?.bookings?.rows || [] }
					rowKey={ ( row ) => row.line_id }
					total={ data?.bookings?.total || 0 }
					page={ page }
					perPage={ PER_PAGE }
					onPageChange={ setPage }
					onRowClick={ ( row ) =>
						navigate( `/bookings/${ row.booking_id }` )
					}
					// Invoice and Edit on each booked room (R1-T15); Edit opens the
					// room's editor while it can still change, otherwise the booking.
					rowActions={ ( row ) => [
						row.invoice_url
							? {
									label: __(
										'Invoice',
										'radius-hotel-booking'
									),
									icon: FileText,
									inline: true,
									onSelect: () =>
										window.open(
											row.invoice_url,
											'_blank',
											'noopener'
										),
							  }
							: null,
						{
							label: __( 'Edit', 'radius-hotel-booking' ),
							icon: Pencil,
							inline: true,
							onSelect: () =>
								navigate(
									[ 'pending', 'confirmed' ].includes(
										row.status
									)
										? `/bookings/${ row.booking_id }?edit=${ row.line_id }`
										: `/bookings/${ row.booking_id }`
								),
						},
					] }
					loading={ loading }
					caption={ __( 'Booked rooms', 'radius-hotel-booking' ) }
					empty={
						<EmptyState
							icon={ BedDouble }
							title={ __(
								'No booked rooms in this period',
								'radius-hotel-booking'
							) }
							description={ __(
								'Try other dates, or switch between arrival date and booking date.',
								'radius-hotel-booking'
							) }
						/>
					}
				/>
			</Panel>
		</div>
	);
}
