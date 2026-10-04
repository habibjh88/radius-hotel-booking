/**
 * Room types overview (`#/rooms`, feature 6.2): one card per type with its
 * cover, room counts by state and readiness. The rate plans a type sells
 * arrive with M07.
 */
import { Link } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	BedDouble,
	DoorOpen,
	ImageOff,
	Layers,
	Plus,
	Sparkles,
	Users,
} from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import StatusBadge from '@/components/common/StatusBadge';
import { usePageActions } from '@/components/layout/PageActions';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useAccess } from '@/lib/access';
import { useRoomTypes } from './api';

const STATES = [ 'available', 'maintenance', 'out_of_service' ];

/**
 * "2 adults".
 *
 * @param {number} count Max adults.
 * @return {string} Text.
 */
const adults = ( count ) =>
	sprintf(
		/* translators: %d: max adults. */
		_n( '%d adult', '%d adults', count, 'radius-hotel-booking' ),
		count
	);

/**
 * @return {JSX.Element} Screen.
 */
export default function RoomTypes() {
	const { data: types, isPending, error, refetch } = useRoomTypes();
	const canManage = useAccess( 'room_types.manage' ) !== 'locked';

	usePageActions(
		<div className="flex items-center gap-2">
			<Button asChild variant="outline">
				<Link to="/rooms/amenities">
					<Sparkles className="h-4 w-4" aria-hidden="true" />
					<span className="sr-only sm:not-sr-only">
						{ __( 'Amenities', 'radius-hotel-booking' ) }
					</span>
				</Link>
			</Button>
			<Button asChild variant="outline">
				<Link to="/rooms/floors">
					<Layers className="h-4 w-4" aria-hidden="true" />
					<span className="sr-only sm:not-sr-only">
						{ __( 'Floors', 'radius-hotel-booking' ) }
					</span>
				</Link>
			</Button>
			{ canManage ? (
				<Button asChild>
					<Link to="/rooms/new">
						<Plus className="h-4 w-4" aria-hidden="true" />
						<span className="sr-only sm:not-sr-only">
							{ __( 'Add room type', 'radius-hotel-booking' ) }
						</span>
					</Link>
				</Button>
			) : null }
		</div>,
		[ canManage ]
	);

	if ( isPending ) {
		return (
			<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
				{ [ 0, 1, 2 ].map( ( key ) => (
					<Skeleton key={ key } className="h-72 w-full rounded-xl" />
				) ) }
			</div>
		);
	}

	if ( error ) {
		return (
			<Panel>
				<EmptyState
					icon={ DoorOpen }
					title={ __(
						'The room types could not be loaded',
						'radius-hotel-booking'
					) }
					description={ error.message }
					action={
						<Button variant="outline" onClick={ () => refetch() }>
							{ __( 'Try again', 'radius-hotel-booking' ) }
						</Button>
					}
					className="border-0"
				/>
			</Panel>
		);
	}

	if ( ! types.length ) {
		return (
			<Panel>
				<EmptyState
					icon={ DoorOpen }
					title={ __( 'No room types yet', 'radius-hotel-booking' ) }
					description={ __(
						'Add your first room type, such as Standard Room or Suite, then add its rooms.',
						'radius-hotel-booking'
					) }
					action={
						canManage ? (
							<Button asChild>
								<Link to="/rooms/new">
									<Plus
										className="h-4 w-4"
										aria-hidden="true"
									/>
									{ __(
										'Add room type',
										'radius-hotel-booking'
									) }
								</Link>
							</Button>
						) : null
					}
					className="border-0 py-16"
				/>
			</Panel>
		);
	}

	return (
		<ul className="m-0 grid list-none gap-4 p-0 sm:grid-cols-2 xl:grid-cols-3">
			{ types.map( ( type ) => (
				<li key={ type.id } className="m-0 min-w-0">
					<RoomTypeCard type={ type } />
				</li>
			) ) }
		</ul>
	);
}

/**
 * One room type card; the whole card opens the type.
 *
 * @param {Object} props      Props.
 * @param {Object} props.type Room type from the API.
 * @return {JSX.Element} Card.
 */
function RoomTypeCard( { type } ) {
	const cover = type.featured_image || type.gallery[ 0 ];
	const readiness = type.is_active ? type.readiness : 'hidden';

	return (
		<Link
			to={ `/rooms/${ type.id }` }
			className="group flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card text-inherit no-underline shadow-sm transition-colors hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
		>
			<div className="relative aspect-[16/9] w-full overflow-hidden bg-muted">
				{ cover ? (
					<img
						src={ cover.thumb || cover.url }
						alt={ cover.alt || '' }
						loading="lazy"
						className="absolute inset-0 h-full w-full object-cover"
					/>
				) : (
					<span className="absolute inset-0 flex items-center justify-center text-muted-foreground">
						<ImageOff className="h-8 w-8" aria-hidden="true" />
					</span>
				) }
				<StatusBadge
					domain="readiness"
					value={ readiness }
					className="absolute left-3 top-3 bg-card shadow-sm"
				/>
			</div>

			<div className="flex flex-1 flex-col gap-3 p-4">
				<div className="min-w-0">
					<h3 className="m-0 truncate p-0 text-base font-semibold text-heading group-hover:text-primary">
						{ type.name }
					</h3>
					{ type.short_description ? (
						<p className="m-0 mt-1 line-clamp-2 text-[13px] leading-5 text-muted-foreground">
							{ type.short_description }
						</p>
					) : null }
				</div>

				<div className="flex flex-wrap gap-x-4 gap-y-1 text-[13px] text-muted-foreground">
					<span className="inline-flex items-center gap-1.5">
						<Users className="h-4 w-4" aria-hidden="true" />
						{ type.max_children
							? sprintf(
									/* translators: 1: "2 adults", 2: "1 child". */
									__( '%1$s · %2$s', 'radius-hotel-booking' ),
									adults( type.max_adults ),
									sprintf(
										/* translators: %d: max children. */
										_n(
											'%d child',
											'%d children',
											type.max_children,
											'radius-hotel-booking'
										),
										type.max_children
									)
							  )
							: adults( type.max_adults ) }
					</span>
					{ type.bed_info ? (
						<span className="inline-flex min-w-0 items-center gap-1.5">
							<BedDouble
								className="h-4 w-4 shrink-0"
								aria-hidden="true"
							/>
							<span className="truncate">{ type.bed_info }</span>
						</span>
					) : null }
				</div>

				<div className="mt-auto space-y-2 border-t border-border pt-3">
					<p className="m-0 text-sm font-semibold text-heading">
						{ sprintf(
							/* translators: %d: number of rooms. */
							_n(
								'%d room',
								'%d rooms',
								type.rooms.total,
								'radius-hotel-booking'
							),
							type.rooms.total
						) }
					</p>
					{ type.rooms.total ? (
						<div className="flex flex-wrap gap-1.5">
							{ STATES.filter(
								( state ) => type.rooms[ state ]
							).map( ( state ) => (
								<RoomStateCount
									key={ state }
									state={ state }
									count={ type.rooms[ state ] }
								/>
							) ) }
						</div>
					) : null }
					<p className="m-0 text-[13px] text-muted-foreground">
						{ type.rate_plans?.length
							? sprintf(
									/* translators: %s: rate plan names, e.g. "Half Day, Overnight". */
									__( 'Sells: %s', 'radius-hotel-booking' ),
									type.rate_plans.join( ', ' )
							  )
							: __(
									'No rate plans on sale yet',
									'radius-hotel-booking'
							  ) }
					</p>
				</div>
			</div>
		</Link>
	);
}

/**
 * "3 × Available": a room-state badge with its count.
 *
 * @param {Object} props       Props.
 * @param {string} props.state Room state.
 * @param {number} props.count Rooms in that state.
 * @return {JSX.Element} Badge.
 */
function RoomStateCount( { state, count } ) {
	return (
		<span className="inline-flex items-center gap-1">
			<span className="text-sm font-semibold tabular-nums text-heading">
				{ count }
			</span>
			<StatusBadge domain="room" value={ state } />
		</span>
	);
}
