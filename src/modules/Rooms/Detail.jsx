/**
 * One room type (`#/rooms/:id`, `#/rooms/new`): Details · Rooms tabs (M06).
 * The Rates tab came with M07. `?tab=rooms|rates` opens that tab (the
 * availability calendar links to Rates).
 */
import { useState } from 'react';
import {
	Link,
	useNavigate,
	useParams,
	useSearchParams,
} from 'react-router-dom';
import { __, sprintf } from '@wordpress/i18n';
import { ArrowLeft, DoorOpen, Trash2 } from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAccess } from '@/lib/access';
import { toast } from '@/lib/toast';
import RoomTypeForm from './components/RoomTypeForm';
import RatesTab from './components/RatesTab';
import RoomsTab from './components/RoomsTab';
import { useDeleteRoomType, useRoomType } from './api';

/**
 * @return {JSX.Element} Screen.
 */
export default function RoomTypeDetail() {
	const { id: param } = useParams();
	const isNew = param === 'new';
	const id = isNew ? 0 : Number( param ) || 0;
	const navigate = useNavigate();
	const canManage = useAccess( 'room_types.manage' ) !== 'locked';
	const canSeeRates = useAccess( 'page.rates' ) !== 'locked';
	const [ params ] = useSearchParams();
	const tab = params.get( 'tab' );
	const { data: type, isPending, error, refetch } = useRoomType( id );
	const remove = useDeleteRoomType();
	const [ confirmDelete, setConfirmDelete ] = useState( false );

	const back = (
		<Link
			to="/rooms"
			className="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground no-underline hover:text-heading"
		>
			<ArrowLeft className="h-4 w-4" aria-hidden="true" />
			{ __( 'All room types', 'radius-hotel-booking' ) }
		</Link>
	);

	if ( isNew ) {
		return (
			<div className="space-y-4">
				{ back }
				{ canManage ? (
					<RoomTypeForm
						onSaved={ ( saved ) =>
							navigate( `/rooms/${ saved.id }`, {
								replace: true,
							} )
						}
					/>
				) : (
					<Panel>
						<EmptyState
							icon={ DoorOpen }
							title={ __(
								'You cannot add room types',
								'radius-hotel-booking'
							) }
							className="border-0"
						/>
					</Panel>
				) }
			</div>
		);
	}

	if ( isPending ) {
		return (
			<div className="space-y-4">
				{ back }
				<Skeleton className="h-8 w-64" />
				<Skeleton className="h-96 w-full" />
			</div>
		);
	}

	if ( error || ! type ) {
		return (
			<div className="space-y-4">
				{ back }
				<Panel>
					<EmptyState
						icon={ DoorOpen }
						title={
							error?.status === 404
								? __(
										'This room type does not exist',
										'radius-hotel-booking'
								  )
								: __(
										'The room type could not be loaded',
										'radius-hotel-booking'
								  )
						}
						description={
							error?.status === 404 ? '' : error?.message
						}
						action={
							error?.status === 404 ? null : (
								<Button
									variant="outline"
									onClick={ () => refetch() }
								>
									{ __(
										'Try again',
										'radius-hotel-booking'
									) }
								</Button>
							)
						}
						className="border-0"
					/>
				</Panel>
			</div>
		);
	}

	const deleteType = () =>
		remove.mutateAsync( type.id ).then( () => {
			toast.success(
				sprintf(
					/* translators: %s: room type name. */
					__( '%s deleted.', 'radius-hotel-booking' ),
					type.name
				)
			);
			navigate( '/rooms', { replace: true } );
		} );

	return (
		<div className="space-y-4">
			{ back }
			<div className="flex flex-wrap items-center justify-between gap-3">
				<div className="min-w-0">
					<h2 className="m-0 truncate p-0 text-xl font-bold text-heading">
						{ type.name }
					</h2>
					<p className="m-0 mt-0.5 text-[13px] text-muted-foreground">
						{ type.rooms.total
							? sprintf(
									/* translators: 1: number of rooms, 2: number available. */
									__(
										'%1$d rooms · %2$d available',
										'radius-hotel-booking'
									),
									type.rooms.total,
									type.rooms.available
							  )
							: __( 'No rooms yet', 'radius-hotel-booking' ) }
						{ type.is_active
							? ''
							: ' · ' +
							  __( 'Hidden from sale', 'radius-hotel-booking' ) }
					</p>
				</div>
				{ canManage ? (
					<Button
						variant="outline"
						onClick={ () => setConfirmDelete( true ) }
						className="text-destructive"
					>
						<Trash2 className="h-4 w-4" aria-hidden="true" />
						{ __( 'Delete', 'radius-hotel-booking' ) }
					</Button>
				) : null }
			</div>

			<Tabs
				defaultValue={
					tab === 'rooms' || ( tab === 'rates' && canSeeRates )
						? tab
						: 'details'
				}
			>
				<TabsList>
					<TabsTrigger value="details">
						{ __( 'Details', 'radius-hotel-booking' ) }
					</TabsTrigger>
					<TabsTrigger value="rooms">
						{ sprintf(
							/* translators: %d: number of rooms. */
							__( 'Rooms (%d)', 'radius-hotel-booking' ),
							type.rooms.total
						) }
					</TabsTrigger>
					{ canSeeRates ? (
						<TabsTrigger value="rates">
							{ __( 'Rates', 'radius-hotel-booking' ) }
						</TabsTrigger>
					) : null }
				</TabsList>
				<TabsContent value="details" className="mt-4">
					<RoomTypeForm
						key={ type.id }
						type={ type }
						readOnly={ ! canManage }
					/>
				</TabsContent>
				<TabsContent value="rooms" className="mt-4">
					<RoomsTab type={ type } />
				</TabsContent>
				{ canSeeRates ? (
					<TabsContent value="rates" className="mt-4">
						<RatesTab type={ type } />
					</TabsContent>
				) : null }
			</Tabs>

			<ConfirmDialog
				open={ confirmDelete }
				onOpenChange={ setConfirmDelete }
				title={ sprintf(
					/* translators: %s: room type name. */
					__( 'Delete %s?', 'radius-hotel-booking' ),
					type.name
				) }
				description={
					type.rooms.total
						? __(
								'A room type with rooms cannot be deleted. Move or remove its rooms first.',
								'radius-hotel-booking'
						  )
						: __(
								'It disappears from the admin and the booking form. Past bookings keep its name.',
								'radius-hotel-booking'
						  )
				}
				confirmLabel={ __(
					'Delete room type',
					'radius-hotel-booking'
				) }
				destructive
				onConfirm={ deleteType }
			/>
		</div>
	);
}
