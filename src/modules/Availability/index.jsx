/**
 * Availability calendar (features 8.1–8.4, 8.6): one room type, one month.
 * The first row is the room type itself (open or closed, rooms with nothing
 * on them that day); then one row per rate it sells, with the price a stay
 * starting that day costs, whether that rate is closed, and how many rooms
 * are free for its window. Staff with `availability.manage` click a cell to
 * change it; past dates are read-only.
 *
 * The room type and month live in the URL (`?type=&month=`), so a reload or
 * a shared link opens the same view.
 */
import { useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import { BedDouble, ChevronLeft, ChevronRight } from 'lucide-react';

import CalendarGrid from '@/components/common/CalendarGrid';
import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import { Button } from '@/components/ui/button';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useAccess } from '@/lib/access';
import { formatDate, formatDateAs, formatMoney, siteToday } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useRoomTypes } from '@/modules/Rooms/api';
import { useCalendar } from './api';
import CellEditor from './components/CellEditor';

/**
 * Move a `YYYY-MM` month by some months.
 *
 * @param {string} month `YYYY-MM`.
 * @param {number} by    Months (may be negative).
 * @return {string} `YYYY-MM`.
 */
const shiftMonth = ( month, by ) => {
	const [ year, number ] = month.split( '-' ).map( Number );
	const date = new Date( Date.UTC( year, number - 1 + by, 1 ) );
	return `${ date.getUTCFullYear() }-${ String(
		date.getUTCMonth() + 1
	).padStart( 2, '0' ) }`;
};

/**
 * The legend: colour is never the only signal (design system §4).
 *
 * @return {JSX.Element} Legend.
 */
function Legend() {
	const items = [
		[
			'bg-card border border-border',
			__( 'Open', 'radius-hotel-booking' ),
		],
		[
			'bg-destructive-soft',
			__( 'Full: no room free for this rate', 'radius-hotel-booking' ),
		],
		[ 'bg-muted', __( 'Closed', 'radius-hotel-booking' ) ],
	];
	return (
		<ul className="m-0 flex list-none flex-wrap items-center gap-x-4 gap-y-1 p-0 text-xs text-muted-foreground">
			{ items.map( ( [ swatch, label ] ) => (
				<li key={ label } className="m-0 flex items-center gap-1.5">
					<span
						className={ cn(
							'inline-block h-3 w-3 rounded-sm',
							swatch
						) }
						aria-hidden="true"
					/>
					{ label }
				</li>
			) ) }
			<li className="m-0 flex items-center gap-1.5">
				<span
					className="inline-block h-1.5 w-1.5 rounded-full bg-primary"
					aria-hidden="true"
				/>
				{ __( 'Price set for that date', 'radius-hotel-booking' ) }
			</li>
		</ul>
	);
}

export default function Availability() {
	const [ params, setParams ] = useSearchParams();
	const types = useRoomTypes();
	const canManage = useAccess( 'availability.manage' ) !== 'locked';
	const [ editing, setEditing ] = useState( null );

	const month = /^\d{4}-\d{2}$/.test( params.get( 'month' ) || '' )
		? params.get( 'month' )
		: siteToday().slice( 0, 7 );
	const typeList = types.data || [];
	const typeId =
		Number( params.get( 'type' ) ) ||
		( typeList.find( ( type ) => type.is_active ) || typeList[ 0 ] )?.id ||
		0;
	const calendar = useCalendar( typeId, month );
	const grid = calendar.data;

	const setView = ( changes ) =>
		setParams( ( prev ) => {
			const next = new URLSearchParams( prev );
			Object.entries( changes ).forEach( ( [ key, value ] ) =>
				next.set( key, String( value ) )
			);
			return next;
		} );

	const rows = useMemo( () => {
		if ( ! grid ) {
			return [];
		}
		const typeRow = {
			key: 'type',
			kind: 'type',
			label: __( 'Whole room type', 'radius-hotel-booking' ),
			sublabel: sprintf(
				/* translators: %d: number of rooms that can be sold. */
				_n(
					'%d room to sell',
					'%d rooms to sell',
					grid.room_type.rooms_sellable,
					'radius-hotel-booking'
				),
				grid.room_type.rooms_sellable
			),
			cells: grid.days,
		};
		return [
			typeRow,
			...grid.rates.map( ( rate ) => ( {
				key: `rate-${ rate.rate_plan_id }`,
				kind: 'rate',
				rate,
				label: rate.name,
				sublabel: formatMoney(
					rate.sale_price !== null
						? Math.min( rate.price, rate.sale_price )
						: rate.price
				),
				cells: rate.cells,
			} ) ),
		];
	}, [ grid ] );

	const dayOf = ( index ) => grid.days[ index ];

	const renderCell = ( row, cell, index ) => {
		if ( row.kind === 'type' ) {
			if ( cell.closed ) {
				return (
					<span className="text-xs font-semibold">
						{ __( 'Closed', 'radius-hotel-booking' ) }
					</span>
				);
			}
			return (
				<span className="text-xs">
					<span className="block text-sm font-semibold tabular-nums text-heading">
						{ `${ cell.free_rooms }/${ cell.sellable }` }
					</span>
					{ __( 'free all day', 'radius-hotel-booking' ) }
				</span>
			);
		}
		const typeClosed = dayOf( index ).closed;
		return (
			<span className="relative block text-xs">
				{ cell.override !== null ? (
					<span
						className="absolute right-0.5 top-0 h-1.5 w-1.5 rounded-full bg-primary"
						aria-hidden="true"
					/>
				) : null }
				<span className="block text-sm font-semibold tabular-nums text-heading">
					{ cell.price === null ? '—' : formatMoney( cell.price ) }
				</span>
				{ cell.closed || typeClosed
					? __( 'Closed', 'radius-hotel-booking' )
					: cell.free === 0
					? __( 'Full', 'radius-hotel-booking' )
					: sprintf(
							/* translators: %d: rooms free. */
							__( '%d free', 'radius-hotel-booking' ),
							cell.free ?? 0
					  ) }
			</span>
		);
	};

	const cellClassName = ( row, cell, index ) => {
		const closed =
			row.kind === 'type'
				? cell.closed
				: cell.closed || dayOf( index ).closed;
		if ( closed ) {
			return 'bg-muted text-muted-foreground';
		}
		if ( row.kind === 'rate' && cell.free === 0 ) {
			return 'bg-destructive-soft text-destructive';
		}
		return 'text-muted-foreground';
	};

	const cellLabel = ( row, cell, index ) => {
		const date = formatDate( grid.dates[ index ] );
		if ( row.kind === 'type' ) {
			return cell.closed
				? sprintf(
						/* translators: %s: date. */
						__(
							'Whole room type, %s: closed',
							'radius-hotel-booking'
						),
						date
				  )
				: sprintf(
						/* translators: 1: date, 2: free rooms, 3: rooms. */
						__(
							'Whole room type, %1$s: %2$d of %3$d rooms free all day',
							'radius-hotel-booking'
						),
						date,
						cell.free_rooms,
						cell.sellable
				  );
		}
		const state =
			cell.closed || dayOf( index ).closed
				? __( 'closed', 'radius-hotel-booking' )
				: sprintf(
						/* translators: %d: rooms free. */
						__( '%d rooms free', 'radius-hotel-booking' ),
						cell.free ?? 0
				  );
		return sprintf(
			/* translators: 1: rate plan, 2: date, 3: price, 4: state, 5: " (price set for this date)" or "". */
			__( '%1$s, %2$s: %3$s, %4$s%5$s', 'radius-hotel-booking' ),
			row.label,
			date,
			cell.price === null ? '—' : formatMoney( cell.price ),
			state,
			cell.override !== null
				? __( ' (price set for this date)', 'radius-hotel-booking' )
				: ''
		);
	};

	const toolbar = (
		<div className="flex flex-wrap items-center gap-2">
			<Select
				value={ typeId ? String( typeId ) : undefined }
				onValueChange={ ( value ) => setView( { type: value } ) }
			>
				<SelectTrigger
					className="w-full sm:w-56"
					aria-label={ __( 'Room type', 'radius-hotel-booking' ) }
				>
					<SelectValue
						placeholder={ __(
							'Choose a room type',
							'radius-hotel-booking'
						) }
					/>
				</SelectTrigger>
				<SelectContent>
					{ typeList.map( ( type ) => (
						<SelectItem key={ type.id } value={ String( type.id ) }>
							{ type.is_active
								? type.name
								: sprintf(
										/* translators: %s: room type name. */
										__(
											'%s (hidden)',
											'radius-hotel-booking'
										),
										type.name
								  ) }
						</SelectItem>
					) ) }
				</SelectContent>
			</Select>
			<div className="flex items-center gap-1">
				<Button
					type="button"
					variant="outline"
					size="icon"
					onClick={ () =>
						setView( { month: shiftMonth( month, -1 ) } )
					}
					aria-label={ __(
						'Previous month',
						'radius-hotel-booking'
					) }
				>
					<ChevronLeft className="h-4 w-4" aria-hidden="true" />
				</Button>
				<span
					className="min-w-[7.5rem] text-center text-sm font-semibold sm:min-w-[9rem] text-heading"
					aria-live="polite"
				>
					{ formatDateAs( `${ month }-01`, 'F Y' ) }
				</span>
				<Button
					type="button"
					variant="outline"
					size="icon"
					onClick={ () =>
						setView( { month: shiftMonth( month, 1 ) } )
					}
					aria-label={ __( 'Next month', 'radius-hotel-booking' ) }
				>
					<ChevronRight className="h-4 w-4" aria-hidden="true" />
				</Button>
			</div>
			{ month !== siteToday().slice( 0, 7 ) ? (
				<Button
					type="button"
					variant="ghost"
					size="sm"
					onClick={ () =>
						setView( { month: siteToday().slice( 0, 7 ) } )
					}
				>
					{ __( 'This month', 'radius-hotel-booking' ) }
				</Button>
			) : null }
		</div>
	);

	let body;
	if ( types.isPending || ( calendar.isPending && typeId ) ) {
		body = <Skeleton className="h-72 w-full rounded-lg" />;
	} else if ( types.error || calendar.error ) {
		body = (
			<div role="alert" className="space-y-2">
				<p className="m-0 text-sm text-destructive">
					{ ( types.error || calendar.error ).message }
				</p>
				<Button
					type="button"
					variant="outline"
					size="sm"
					onClick={ () =>
						types.error ? types.refetch() : calendar.refetch()
					}
				>
					{ __( 'Try again', 'radius-hotel-booking' ) }
				</Button>
			</div>
		);
	} else if ( ! typeList.length ) {
		body = (
			<EmptyState
				icon={ BedDouble }
				title={ __( 'No room types yet', 'radius-hotel-booking' ) }
				description={ __(
					'Add a room type and its rooms first; the calendar shows what they sell.',
					'radius-hotel-booking'
				) }
				action={
					<Button asChild>
						<Link to="/rooms">
							{ __( 'Go to Rooms', 'radius-hotel-booking' ) }
						</Link>
					</Button>
				}
				className="border-0"
			/>
		);
	} else if ( grid && ! grid.rates.length ) {
		body = (
			<EmptyState
				icon={ BedDouble }
				title={ __(
					'This room type sells no rate yet',
					'radius-hotel-booking'
				) }
				description={ __(
					'Switch on at least one rate plan and give it a price.',
					'radius-hotel-booking'
				) }
				action={
					<Button asChild>
						<Link to={ `/rooms/${ typeId }?tab=rates` }>
							{ __( 'Set the rates', 'radius-hotel-booking' ) }
						</Link>
					</Button>
				}
				className="border-0"
			/>
		);
	} else if ( grid ) {
		body = (
			<div
				className={ cn(
					'space-y-3 transition-opacity',
					calendar.isPlaceholderData && 'opacity-60'
				) }
				aria-busy={ calendar.isFetching || undefined }
			>
				<Legend />
				<CalendarGrid
					dates={ grid.dates }
					today={ grid.today }
					rows={ rows }
					renderCell={ renderCell }
					cellClassName={ cellClassName }
					cellLabel={ cellLabel }
					onCellClick={
						canManage
							? ( row, cell, index ) =>
									setEditing( {
										kind: row.kind,
										typeId,
										typeName: grid.room_type.name,
										rate: row.rate,
										cell: row.kind === 'rate' ? cell : null,
										day: grid.days[ index ],
									} )
							: undefined
					}
					isDisabled={ ( row, cell, index ) =>
						grid.days[ index ].past
					}
					corner={ grid.room_type.name }
					caption={ sprintf(
						/* translators: 1: room type, 2: month. */
						__(
							'Availability of %1$s in %2$s',
							'radius-hotel-booking'
						),
						grid.room_type.name,
						formatDateAs( `${ month }-01`, 'F Y' )
					) }
				/>
				{ ! canManage ? (
					<p className="m-0 text-xs text-muted-foreground">
						{ __(
							'You can view the calendar. Ask a manager to change prices or close dates.',
							'radius-hotel-booking'
						) }
					</p>
				) : null }
			</div>
		);
	}

	return (
		<>
			<Panel
				title={
					grid
						? grid.room_type.name
						: __( 'Availability', 'radius-hotel-booking' )
				}
				description={
					canManage
						? __(
								'Prices are for a stay starting that day. Click a date to change it.',
								'radius-hotel-booking'
						  )
						: __(
								'Prices are for a stay starting that day.',
								'radius-hotel-booking'
						  )
				}
			>
				{ /* In the body, not the header actions: there it could not shrink on a phone. */ }
				<div className="space-y-4">
					{ typeList.length ? toolbar : null }
					{ body }
				</div>
			</Panel>
			{ editing ? (
				<CellEditor
					key={ `${ editing.kind }-${
						editing.rate?.rate_plan_id ?? 0
					}-${ editing.day.date }` }
					target={ editing }
					onClose={ () => setEditing( null ) }
				/>
			) : null }
		</>
	);
}
