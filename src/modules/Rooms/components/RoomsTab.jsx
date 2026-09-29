/**
 * The Rooms tab of a room type (features 6.4–6.6): its rooms grouped under
 * floor headings, each a tile with its number and state. Add a room, rename
 * it or put it on another floor, set its state with a reason, remove it.
 */
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	DoorOpen,
	ArrowRightLeft,
	Layers,
	ListPlus,
	MoreHorizontal,
	Pencil,
	Plus,
	Trash2,
	Wrench,
} from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useAccess } from '@/lib/access';
import { getStatus } from '@/lib/status';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import BulkAddDialog from './BulkAddDialog';
import MoveRoomDialog from './MoveRoomDialog';
import {
	useAddRoom,
	useFloors,
	useRemoveRoom,
	useSetRoomState,
	useTypeRooms,
	useUpdateRoom,
} from '../api';

const STATES = [ 'available', 'maintenance', 'out_of_service' ];

/**
 * @param {Object} props      Props.
 * @param {Object} props.type Room type.
 * @return {JSX.Element} Tab.
 */
export default function RoomsTab( { type } ) {
	const { data: groups, isPending, error, refetch } = useTypeRooms( type.id );
	const { data: floors = [] } = useFloors();
	const canManage = useAccess( 'rooms.manage' ) !== 'locked';
	const canMove = useAccess( 'rooms.move' ) !== 'locked';
	const remove = useRemoveRoom( type.id );
	// { mode: 'add' | 'bulk' | 'edit' | 'state' | 'move' | 'remove', room?, floorId? }
	const [ dialog, setDialog ] = useState( null );
	const close = () => setDialog( null );

	if ( isPending ) {
		return <Skeleton className="h-64 w-full rounded-xl" />;
	}
	if ( error ) {
		return (
			<Panel>
				<EmptyState
					icon={ DoorOpen }
					title={ __(
						'The rooms could not be loaded',
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

	if ( ! floors.length && ! groups.some( ( group ) => group.rooms.length ) ) {
		return (
			<Panel>
				<EmptyState
					icon={ Layers }
					title={ __(
						'Add your floors first',
						'radius-hotel-booking'
					) }
					description={ __(
						'Every room sits on a floor. Create the floors, then add rooms here.',
						'radius-hotel-booking'
					) }
					action={
						<Button asChild>
							<Link to="/rooms/floors">
								{ __( 'Go to floors', 'radius-hotel-booking' ) }
							</Link>
						</Button>
					}
					className="border-0"
				/>
			</Panel>
		);
	}

	const withRooms = groups.filter( ( group ) => group.rooms.length );
	const total = withRooms.reduce(
		( sum, group ) => sum + group.rooms.length,
		0
	);

	return (
		<div className="space-y-4">
			<div className="flex flex-wrap items-center justify-between gap-3">
				<p className="m-0 text-sm text-muted-foreground">
					{ sprintf(
						/* translators: 1: number of rooms, 2: number of floors. */
						__( '%1$s on %2$s', 'radius-hotel-booking' ),
						sprintf(
							/* translators: %d: number of rooms. */
							_n(
								'%d room',
								'%d rooms',
								total,
								'radius-hotel-booking'
							),
							total
						),
						sprintf(
							/* translators: %d: number of floors. */
							_n(
								'%d floor',
								'%d floors',
								withRooms.length,
								'radius-hotel-booking'
							),
							withRooms.length
						)
					) }
				</p>
				{ canManage ? (
					<div className="flex flex-wrap gap-2">
						<Button
							variant="outline"
							onClick={ () => setDialog( { mode: 'bulk' } ) }
						>
							<ListPlus className="h-4 w-4" aria-hidden="true" />
							{ __( 'Bulk add', 'radius-hotel-booking' ) }
						</Button>
						<Button onClick={ () => setDialog( { mode: 'add' } ) }>
							<Plus className="h-4 w-4" aria-hidden="true" />
							{ __( 'Add room', 'radius-hotel-booking' ) }
						</Button>
					</div>
				) : null }
			</div>

			{ withRooms.length ? (
				withRooms.map( ( group ) => (
					<Panel
						key={ group.id }
						title={ sprintf(
							/* translators: 1: floor name, 2: number of rooms. */
							__( '%1$s (%2$d)', 'radius-hotel-booking' ),
							group.name,
							group.rooms.length
						) }
						actions={
							canManage && group.id ? (
								<div className="flex gap-2">
									<Button
										variant="outline"
										size="sm"
										onClick={ () =>
											setDialog( {
												mode: 'bulk',
												floorId: group.id,
											} )
										}
										aria-label={ sprintf(
											/* translators: %s: floor name. */
											__(
												'Bulk add rooms on %s',
												'radius-hotel-booking'
											),
											group.name
										) }
									>
										<ListPlus
											className="h-4 w-4"
											aria-hidden="true"
										/>
										<span className="sr-only sm:not-sr-only">
											{ __(
												'Bulk add',
												'radius-hotel-booking'
											) }
										</span>
									</Button>
									<Button
										variant="outline"
										size="sm"
										onClick={ () =>
											setDialog( {
												mode: 'add',
												floorId: group.id,
											} )
										}
									>
										<Plus
											className="h-4 w-4"
											aria-hidden="true"
										/>
										{ __(
											'Add room',
											'radius-hotel-booking'
										) }
									</Button>
								</div>
							) : null
						}
					>
						<ul className="m-0 grid list-none gap-2 p-0 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
							{ group.rooms.map( ( room ) => (
								<RoomTile
									key={ room.id }
									room={ room }
									canManage={ canManage }
									onEdit={ () =>
										setDialog( { mode: 'edit', room } )
									}
									onState={ () =>
										setDialog( { mode: 'state', room } )
									}
									onRemove={ () =>
										setDialog( { mode: 'remove', room } )
									}
									onMove={
										canMove
											? () =>
													setDialog( {
														mode: 'move',
														room,
													} )
											: null
									}
								/>
							) ) }
						</ul>
					</Panel>
				) )
			) : (
				<Panel>
					<EmptyState
						icon={ DoorOpen }
						title={ __( 'No rooms yet', 'radius-hotel-booking' ) }
						description={ __(
							'Add the physical rooms of this type with their numbers, e.g. A1, A2, A3.',
							'radius-hotel-booking'
						) }
						className="border-0"
					/>
				</Panel>
			) }

			{ dialog?.mode === 'add' || dialog?.mode === 'edit' ? (
				<RoomDialog
					type={ type }
					floors={ floors }
					room={ dialog.room }
					floorId={ dialog.floorId }
					onClose={ close }
				/>
			) : null }
			{ dialog?.mode === 'move' ? (
				<MoveRoomDialog
					type={ type }
					room={ dialog.room }
					onClose={ close }
				/>
			) : null }
			{ dialog?.mode === 'bulk' ? (
				<BulkAddDialog
					type={ type }
					floors={ floors }
					floorId={ dialog.floorId }
					onClose={ close }
				/>
			) : null }
			{ dialog?.mode === 'state' ? (
				<StateDialog
					type={ type }
					room={ dialog.room }
					onClose={ close }
				/>
			) : null }
			<ConfirmDialog
				open={ dialog?.mode === 'remove' }
				onOpenChange={ ( open ) => ! open && close() }
				title={ sprintf(
					/* translators: %s: room number. */
					__( 'Remove room %s?', 'radius-hotel-booking' ),
					dialog?.room?.number ?? ''
				) }
				description={ __(
					'It is no longer sold. Past bookings keep it, and its number is never reused.',
					'radius-hotel-booking'
				) }
				confirmLabel={ __( 'Remove room', 'radius-hotel-booking' ) }
				destructive
				onConfirm={ () =>
					remove.mutateAsync( dialog.room.id ).then( () =>
						toast.success(
							sprintf(
								/* translators: %s: room number. */
								__(
									'Room %s removed.',
									'radius-hotel-booking'
								),
								dialog.room.number
							)
						)
					)
				}
			/>
		</div>
	);
}

/**
 * One room: number, state badge, reason, menu.
 *
 * @param {Object}   props           Props.
 * @param {Object}   props.room      Room.
 * @param {boolean}  props.canManage May change rooms.
 * @param {Function} props.onEdit    Rename / change floor.
 * @param {Function} props.onState   Change state.
 * @param {Function} props.onRemove  Remove.
 * @param {Function} props.onMove    Move to another type (null when not allowed).
 * @return {JSX.Element} Tile.
 */
function RoomTile( { room, canManage, onEdit, onState, onRemove, onMove } ) {
	return (
		<li
			className={ cn(
				'm-0 flex min-w-0 items-start gap-2 rounded-lg border px-3 py-2.5',
				room.state === 'available'
					? 'border-border bg-card'
					: 'border-dashed border-border bg-muted'
			) }
		>
			<div className="min-w-0 flex-1 space-y-1">
				<p className="m-0 text-base font-bold leading-6 text-heading">
					{ room.number }
				</p>
				<StatusBadge domain="room" value={ room.state } />
				{ room.state_note ? (
					<p
						className="m-0 break-words text-xs text-muted-foreground"
						title={ room.state_note }
					>
						{ room.state_note }
					</p>
				) : null }
			</div>
			{ canManage || onMove ? (
				<DropdownMenu>
					<DropdownMenuTrigger asChild>
						<Button
							variant="ghost"
							size="icon"
							className="h-8 w-8 shrink-0"
							aria-label={ sprintf(
								/* translators: %s: room number. */
								__(
									'Actions for room %s',
									'radius-hotel-booking'
								),
								room.number
							) }
						>
							<MoreHorizontal
								className="h-4 w-4"
								aria-hidden="true"
							/>
						</Button>
					</DropdownMenuTrigger>
					<DropdownMenuContent align="end">
						{ canManage ? (
							<>
								<DropdownMenuItem onSelect={ onState }>
									<Wrench
										className="h-4 w-4"
										aria-hidden="true"
									/>
									{ __(
										'Change state',
										'radius-hotel-booking'
									) }
								</DropdownMenuItem>
								<DropdownMenuItem onSelect={ onEdit }>
									<Pencil
										className="h-4 w-4"
										aria-hidden="true"
									/>
									{ __(
										'Rename or change floor',
										'radius-hotel-booking'
									) }
								</DropdownMenuItem>
								<DropdownMenuSeparator />
								<DropdownMenuItem
									onSelect={ onRemove }
									className="text-destructive"
								>
									<Trash2
										className="h-4 w-4"
										aria-hidden="true"
									/>
									{ __(
										'Remove room',
										'radius-hotel-booking'
									) }
								</DropdownMenuItem>
							</>
						) : null }
						{ onMove ? (
							<>
								{ canManage ? <DropdownMenuSeparator /> : null }
								<DropdownMenuItem onSelect={ onMove }>
									<ArrowRightLeft
										className="h-4 w-4"
										aria-hidden="true"
									/>
									{ __(
										'Move to another room type',
										'radius-hotel-booking'
									) }
								</DropdownMenuItem>
							</>
						) : null }
					</DropdownMenuContent>
				</DropdownMenu>
			) : null }
		</li>
	);
}

/**
 * Add a room, or rename one / put it on another floor.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.type    Room type.
 * @param {Object[]} props.floors  Floors.
 * @param {Object}   props.room    Room being edited (omit to add).
 * @param {number}   props.floorId Preselected floor when adding.
 * @param {Function} props.onClose Close.
 * @return {JSX.Element} Dialog.
 */
function RoomDialog( { type, floors, room, floorId, onClose } ) {
	const add = useAddRoom( type.id );
	const update = useUpdateRoom( type.id );
	const [ number, setNumber ] = useState( room?.number ?? '' );
	const [ floor, setFloor ] = useState(
		String( room?.floor_id || floorId || floors[ 0 ]?.id || '' )
	);
	const [ errors, setErrors ] = useState( {} );
	const busy = add.isPending || update.isPending;

	const submit = async ( event ) => {
		event.preventDefault();
		const next = {};
		if ( ! number.trim() ) {
			next.number = __(
				'Give the room a number.',
				'radius-hotel-booking'
			);
		}
		if ( ! Number( floor ) ) {
			next.floor_id = __( 'Choose a floor.', 'radius-hotel-booking' );
		}
		setErrors( next );
		if ( Object.keys( next ).length ) {
			return;
		}
		const values = { number: number.trim(), floor_id: Number( floor ) };
		try {
			if ( room ) {
				await update.mutateAsync( { id: room.id, ...values } );
				toast.success( __( 'Room saved.', 'radius-hotel-booking' ) );
			} else {
				await add.mutateAsync( values );
				toast.success(
					sprintf(
						/* translators: %s: room number. */
						__( 'Room %s added.', 'radius-hotel-booking' ),
						values.number
					)
				);
			}
			onClose();
		} catch ( error ) {
			const fields = {};
			Object.entries( error?.errors || {} ).forEach(
				( [ key, detail ] ) => {
					fields[ key ] = detail?.first_message || '';
				}
			);
			if ( Object.keys( fields ).length ) {
				setErrors( fields );
			} else {
				toastError( error );
			}
		}
	};

	return (
		<Dialog open onOpenChange={ ( open ) => ! open && onClose() }>
			<DialogContent className="max-w-md">
				<form onSubmit={ submit } noValidate className="space-y-4">
					<DialogHeader>
						<DialogTitle>
							{ room
								? sprintf(
										/* translators: %s: room number. */
										__(
											'Edit room %s',
											'radius-hotel-booking'
										),
										room.number
								  )
								: sprintf(
										/* translators: %s: room type name. */
										__(
											'Add a room to %s',
											'radius-hotel-booking'
										),
										type.name
								  ) }
						</DialogTitle>
						<DialogDescription>
							{ __(
								'Room numbers are unique across the hotel.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</DialogHeader>

					<div className="space-y-1.5">
						<Label
							htmlFor="rtbp-room-number"
							className="text-sm font-semibold text-heading"
						>
							{ __( 'Room number', 'radius-hotel-booking' ) }
						</Label>
						<Input
							id="rtbp-room-number"
							value={ number }
							onChange={ ( event ) =>
								setNumber( event.target.value )
							}
							maxLength={ 20 }
							autoFocus
							placeholder={ __(
								'e.g. A1',
								'radius-hotel-booking'
							) }
							aria-invalid={ errors.number ? true : undefined }
							aria-describedby={
								errors.number
									? 'rtbp-room-number-error'
									: undefined
							}
							className={ cn(
								errors.number && '!border-destructive'
							) }
						/>
						{ errors.number ? (
							<p
								id="rtbp-room-number-error"
								role="alert"
								className="m-0 text-xs text-destructive"
							>
								{ errors.number }
							</p>
						) : null }
					</div>

					<div className="space-y-1.5">
						<Label
							htmlFor="rtbp-room-floor"
							className="text-sm font-semibold text-heading"
						>
							{ __( 'Floor', 'radius-hotel-booking' ) }
						</Label>
						<Select value={ floor } onValueChange={ setFloor }>
							<SelectTrigger
								id="rtbp-room-floor"
								aria-invalid={
									errors.floor_id ? true : undefined
								}
								className={ cn(
									errors.floor_id && '!border-destructive'
								) }
							>
								<SelectValue
									placeholder={ __(
										'Choose a floor',
										'radius-hotel-booking'
									) }
								/>
							</SelectTrigger>
							<SelectContent>
								{ floors.map( ( option ) => (
									<SelectItem
										key={ option.id }
										value={ String( option.id ) }
									>
										{ option.name }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>
						{ errors.floor_id ? (
							<p
								role="alert"
								className="m-0 text-xs text-destructive"
							>
								{ errors.floor_id }
							</p>
						) : null }
					</div>

					<DialogFooter>
						<Button
							type="button"
							variant="ghost"
							onClick={ onClose }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						<Button type="submit" disabled={ busy }>
							{ room
								? __( 'Save', 'radius-hotel-booking' )
								: __( 'Add room', 'radius-hotel-booking' ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}

/**
 * Set a room's state and the reason.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.type    Room type.
 * @param {Object}   props.room    Room.
 * @param {Function} props.onClose Close.
 * @return {JSX.Element} Dialog.
 */
function StateDialog( { type, room, onClose } ) {
	const save = useSetRoomState( type.id );
	const [ state, setState ] = useState( room.state );
	const [ note, setNote ] = useState( room.state_note ?? '' );

	const submit = async ( event ) => {
		event.preventDefault();
		try {
			await save.mutateAsync( { id: room.id, state, note } );
			toast.success(
				sprintf(
					/* translators: 1: room number, 2: state label. */
					__( 'Room %1$s is now %2$s.', 'radius-hotel-booking' ),
					room.number,
					getStatus( 'room', state ).label
				)
			);
			onClose();
		} catch ( error ) {
			toastError( error );
		}
	};

	return (
		<Dialog open onOpenChange={ ( open ) => ! open && onClose() }>
			<DialogContent className="max-w-md">
				<form onSubmit={ submit } className="space-y-4">
					<DialogHeader>
						<DialogTitle>
							{ sprintf(
								/* translators: %s: room number. */
								__(
									'State of room %s',
									'radius-hotel-booking'
								),
								room.number
							) }
						</DialogTitle>
						<DialogDescription>
							{ __(
								'Only available rooms can be booked.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</DialogHeader>

					<fieldset className="m-0 space-y-2 border-0 p-0">
						<legend className="sr-only">
							{ __( 'State', 'radius-hotel-booking' ) }
						</legend>
						{ STATES.map( ( value ) => (
							<label
								key={ value }
								className={ cn(
									'flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5',
									state === value
										? 'border-primary bg-primary-soft'
										: 'border-border'
								) }
							>
								<input
									type="radio"
									name="rtbp-room-state"
									value={ value }
									checked={ state === value }
									onChange={ () => setState( value ) }
									className="accent-primary"
								/>
								<StatusBadge domain="room" value={ value } />
							</label>
						) ) }
					</fieldset>

					{ state !== 'available' ? (
						<div className="space-y-1.5">
							<Label
								htmlFor="rtbp-room-note"
								className="text-sm font-semibold text-heading"
							>
								{ __( 'Reason', 'radius-hotel-booking' ) }
							</Label>
							<Input
								id="rtbp-room-note"
								value={ note }
								onChange={ ( event ) =>
									setNote( event.target.value )
								}
								maxLength={ 191 }
								placeholder={ __(
									'e.g. AC repair',
									'radius-hotel-booking'
								) }
							/>
						</div>
					) : null }

					<DialogFooter>
						<Button
							type="button"
							variant="ghost"
							onClick={ onClose }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						<Button type="submit" disabled={ save.isPending }>
							{ __( 'Save', 'radius-hotel-booking' ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
