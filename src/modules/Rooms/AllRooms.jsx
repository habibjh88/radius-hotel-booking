/**
 * All rooms (`#/rooms/all`): every physical room of the hotel in one list,
 * whatever its room type, filtered by type, floor, state or number. Tick
 * rooms to set their state, move them to another room type or remove them in
 * one go. Each room goes through the same checks as on its own; the ones the
 * server refuses (an upcoming booking) are listed and stay ticked.
 */
import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AlertTriangle,
	ArrowLeft,
	ArrowRightLeft,
	DoorOpen,
	ListPlus,
	Plus,
	Trash2,
	Wrench,
	X,
} from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import DataTable from '@/components/common/DataTable';
import EmptyState from '@/components/common/EmptyState';
import FilterTabs from '@/components/common/FilterTabs';
import Panel from '@/components/common/Panel';
import StatusBadge from '@/components/common/StatusBadge';
import { usePageActions } from '@/components/layout/PageActions';
import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { useAccess } from '@/lib/access';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import BulkAddDialog from './components/BulkAddDialog';
import RoomDialog from './components/RoomDialog';
import {
	useAllRooms,
	useBulkMoveRooms,
	useBulkRoomAction,
	useFloors,
	useRoomTypes,
} from './api';

const STATES = [ 'available', 'maintenance', 'out_of_service' ];
const ANY = 'any';

/**
 * @return {JSX.Element} Screen.
 */
export default function AllRooms() {
	const { data: rooms = [], isPending, error, refetch } = useAllRooms();
	const { data: types = [] } = useRoomTypes();
	const { data: floors = [] } = useFloors();
	const canManage = useAccess( 'rooms.manage' ) !== 'locked';
	const canMove = useAccess( 'rooms.move' ) !== 'locked';
	const bulk = useBulkRoomAction();
	const bulkMove = useBulkMoveRooms();

	const [ q, setQ ] = useState( '' );
	const [ type, setType ] = useState( ANY );
	const [ floor, setFloor ] = useState( ANY );
	const [ state, setState ] = useState( ANY );
	const [ selected, setSelected ] = useState( [] );
	// 'add' | 'bulkAdd' | 'state' | 'move' | 'remove'
	const [ dialog, setDialog ] = useState( null );
	// Rooms the last bulk action could not change: `{ id, number, message }`.
	const [ failed, setFailed ] = useState( [] );

	usePageActions(
		<div className="flex items-center gap-2">
			<Button asChild variant="outline">
				<Link to="/rooms">
					<ArrowLeft className="h-4 w-4" aria-hidden="true" />
					<span className="sr-only sm:not-sr-only">
						{ __( 'Room types', 'radius-hotel-booking' ) }
					</span>
				</Link>
			</Button>
			{ canManage ? (
				<>
					<Button
						variant="outline"
						onClick={ () => setDialog( 'bulkAdd' ) }
					>
						<ListPlus className="h-4 w-4" aria-hidden="true" />
						<span className="sr-only sm:not-sr-only">
							{ __( 'Bulk add', 'radius-hotel-booking' ) }
						</span>
					</Button>
					<Button onClick={ () => setDialog( 'add' ) }>
						<Plus className="h-4 w-4" aria-hidden="true" />
						<span className="sr-only sm:not-sr-only">
							{ __( 'Add room', 'radius-hotel-booking' ) }
						</span>
					</Button>
				</>
			) : null }
		</div>,
		[ canManage ]
	);

	const shown = useMemo( () => {
		const needle = q.trim().toLowerCase();
		return rooms.filter(
			( room ) =>
				( type === ANY || String( room.room_type_id ) === type ) &&
				( floor === ANY || String( room.floor_id ) === floor ) &&
				( state === ANY || room.state === state ) &&
				( ! needle || room.number.toLowerCase().includes( needle ) )
		);
	}, [ rooms, q, type, floor, state ] );

	// A ticked room that left the list (removed, or by someone else) is dropped.
	const liveIds = new Set( rooms.map( ( room ) => room.id ) );
	const ticked = selected.filter( ( id ) => liveIds.has( id ) );
	const hiddenTicked = ticked.filter(
		( id ) => ! shown.some( ( room ) => room.id === id )
	).length;

	const counts = STATES.reduce(
		( out, value ) => ( {
			...out,
			[ value ]: rooms.filter( ( room ) => room.state === value ).length,
		} ),
		{}
	);

	/**
	 * Show the outcome: the server's message, then the refused rooms (which
	 * stay ticked so they can be retried or handled one by one).
	 *
	 * @param {Object} result `{ done, failed, message }`.
	 */
	const report = ( result ) => {
		if ( result.done.length ) {
			toast.success( result.message );
		}
		setFailed( result.failed );
		setSelected( result.failed.map( ( item ) => item.id ) );
		setDialog( null );
	};

	const columns = [
		{
			id: 'number',
			header: __( 'Room', 'radius-hotel-booking' ),
			mobile: 'title',
			cell: ( room ) => (
				<span className="font-semibold text-heading">
					{ room.number }
				</span>
			),
		},
		{
			id: 'type',
			header: __( 'Room type', 'radius-hotel-booking' ),
			mobile: 'subtitle',
			cell: ( room ) => (
				<Link
					to={ `/rooms/${ room.room_type_id }` }
					onClick={ ( event ) => event.stopPropagation() }
					className="text-foreground underline-offset-2 hover:text-primary hover:underline"
				>
					{ room.room_type_name ||
						__( 'Unknown type', 'radius-hotel-booking' ) }
				</Link>
			),
		},
		{
			id: 'floor',
			header: __( 'Floor', 'radius-hotel-booking' ),
			cell: ( room ) =>
				room.floor_name || __( 'No floor', 'radius-hotel-booking' ),
		},
		{
			id: 'state',
			header: __( 'State', 'radius-hotel-booking' ),
			cell: ( room ) => (
				<StatusBadge domain="room" value={ room.state } />
			),
		},
		{
			id: 'note',
			header: __( 'Reason', 'radius-hotel-booking' ),
			wrap: true,
			cell: ( room ) =>
				room.state_note || (
					<span className="text-muted-foreground">—</span>
				),
		},
	];

	const filtered = q || type !== ANY || floor !== ANY || state !== ANY;

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

	return (
		<div className="mx-auto max-w-6xl space-y-4">
			<Panel
				title={ __( 'All rooms', 'radius-hotel-booking' ) }
				description={
					isPending
						? null
						: sprintf(
								/* translators: %d: number of rooms. */
								_n(
									'%d room in the hotel',
									'%d rooms in the hotel',
									rooms.length,
									'radius-hotel-booking'
								),
								rooms.length
						  )
				}
			>
				<div className="space-y-3">
					<FilterTabs
						label={ __( 'State', 'radius-hotel-booking' ) }
						value={ state }
						onChange={ setState }
						tabs={ [
							{
								value: ANY,
								label: __( 'All', 'radius-hotel-booking' ),
								count: rooms.length,
							},
							{
								value: 'available',
								label: __(
									'Available',
									'radius-hotel-booking'
								),
								count: counts.available,
							},
							{
								value: 'maintenance',
								label: __(
									'Maintenance',
									'radius-hotel-booking'
								),
								count: counts.maintenance,
							},
							{
								value: 'out_of_service',
								label: __(
									'Out of service',
									'radius-hotel-booking'
								),
								count: counts.out_of_service,
							},
						] }
					/>

					{ failed.length ? (
						<FailedList
							failed={ failed }
							onDismiss={ () => setFailed( [] ) }
						/>
					) : null }

					{ /* Always shown, so ticking the first room does not push the rows down. */ }
					{ canManage || canMove ? (
						<div
							role="region"
							aria-label={ __(
								'Bulk actions',
								'radius-hotel-booking'
							) }
							className={ cn(
								'flex flex-wrap items-center gap-2 rounded-lg border px-3 py-2',
								ticked.length
									? 'border-primary/30 bg-primary-soft'
									: 'border-border bg-muted'
							) }
						>
							<p
								className={ cn(
									'm-0 mr-auto text-sm',
									ticked.length
										? 'font-semibold text-heading'
										: 'text-muted-foreground'
								) }
								aria-live="polite"
							>
								{ ! ticked.length
									? __(
											'Select rooms to change them together.',
											'radius-hotel-booking'
									  )
									: sprintf(
											/* translators: %d: number of rooms. */
											_n(
												'%d room selected',
												'%d rooms selected',
												ticked.length,
												'radius-hotel-booking'
											),
											ticked.length
									  ) }
								{ hiddenTicked ? (
									<span className="ml-1 font-normal text-muted-foreground">
										{ sprintf(
											/* translators: %d: number of rooms. */
											_n(
												'(%d hidden by the filters)',
												'(%d hidden by the filters)',
												hiddenTicked,
												'radius-hotel-booking'
											),
											hiddenTicked
										) }
									</span>
								) : null }
							</p>
							{ canManage ? (
								<Button
									size="sm"
									variant="outline"
									disabled={ ! ticked.length }
									onClick={ () => setDialog( 'state' ) }
								>
									<Wrench
										className="h-4 w-4"
										aria-hidden="true"
									/>
									{ __(
										'Set state',
										'radius-hotel-booking'
									) }
								</Button>
							) : null }
							{ canMove ? (
								<Button
									size="sm"
									variant="outline"
									disabled={ ! ticked.length }
									onClick={ () => setDialog( 'move' ) }
								>
									<ArrowRightLeft
										className="h-4 w-4"
										aria-hidden="true"
									/>
									{ __(
										'Move to type',
										'radius-hotel-booking'
									) }
								</Button>
							) : null }
							{ canManage ? (
								<Button
									size="sm"
									variant="outline"
									className="text-destructive"
									disabled={ ! ticked.length }
									onClick={ () => setDialog( 'remove' ) }
								>
									<Trash2
										className="h-4 w-4"
										aria-hidden="true"
									/>
									{ __( 'Remove', 'radius-hotel-booking' ) }
								</Button>
							) : null }
							<Button
								size="sm"
								variant="ghost"
								disabled={ ! ticked.length }
								onClick={ () => setSelected( [] ) }
							>
								<X className="h-4 w-4" aria-hidden="true" />
								{ __(
									'Clear selection',
									'radius-hotel-booking'
								) }
							</Button>
						</div>
					) : null }

					<DataTable
						columns={ columns }
						rows={ shown }
						total={ shown.length }
						loading={ isPending }
						caption={ __( 'All rooms', 'radius-hotel-booking' ) }
						search={ {
							value: q,
							onChange: setQ,
							placeholder: __(
								'Search room number…',
								'radius-hotel-booking'
							),
						} }
						toolbar={
							<>
								<FilterSelect
									label={ __(
										'Room type',
										'radius-hotel-booking'
									) }
									anyLabel={ __(
										'All room types',
										'radius-hotel-booking'
									) }
									value={ type }
									onChange={ setType }
									options={ types }
								/>
								<FilterSelect
									label={ __(
										'Floor',
										'radius-hotel-booking'
									) }
									anyLabel={ __(
										'All floors',
										'radius-hotel-booking'
									) }
									value={ floor }
									onChange={ setFloor }
									options={ floors }
								/>
								{ filtered ? (
									<Button
										variant="ghost"
										size="sm"
										onClick={ () => {
											setQ( '' );
											setType( ANY );
											setFloor( ANY );
											setState( ANY );
										} }
									>
										{ __(
											'Clear filters',
											'radius-hotel-booking'
										) }
									</Button>
								) : null }
							</>
						}
						selection={
							canManage || canMove
								? {
										selected: ticked,
										onChange: setSelected,
										label: ( room ) => room.number,
								  }
								: undefined
						}
						empty={
							<EmptyState
								icon={ DoorOpen }
								title={
									filtered
										? __(
												'No room matches',
												'radius-hotel-booking'
										  )
										: __(
												'No rooms yet',
												'radius-hotel-booking'
										  )
								}
								description={
									filtered
										? __(
												'Try another number, or clear the filters.',
												'radius-hotel-booking'
										  )
										: __(
												'Add rooms with Add room or Bulk add.',
												'radius-hotel-booking'
										  )
								}
								className="border-0 py-12"
							/>
						}
					/>
				</div>
			</Panel>

			{ dialog === 'add' ? (
				<RoomDialog
					types={ types }
					typeId={ type === ANY ? undefined : Number( type ) }
					floors={ floors }
					onClose={ () => setDialog( null ) }
				/>
			) : null }
			{ dialog === 'bulkAdd' ? (
				<BulkAddDialog
					types={ types }
					typeId={ type === ANY ? undefined : Number( type ) }
					floors={ floors }
					floorId={ floor === ANY ? undefined : Number( floor ) }
					onClose={ () => setDialog( null ) }
				/>
			) : null }
			{ dialog === 'state' ? (
				<BulkStateDialog
					count={ ticked.length }
					busy={ bulk.isPending }
					onClose={ () => setDialog( null ) }
					onSave={ ( values ) =>
						bulk
							.mutateAsync( {
								action: 'state',
								ids: ticked,
								...values,
							} )
							.then( report )
							.catch( toastError )
					}
				/>
			) : null }
			{ dialog === 'move' ? (
				<BulkMoveDialog
					count={ ticked.length }
					types={ types }
					busy={ bulkMove.isPending }
					onClose={ () => setDialog( null ) }
					onSave={ ( typeId ) =>
						bulkMove
							.mutateAsync( {
								ids: ticked,
								room_type_id: typeId,
							} )
							.then( report )
							.catch( toastError )
					}
				/>
			) : null }
			<ConfirmDialog
				open={ dialog === 'remove' }
				onOpenChange={ ( open ) => ! open && setDialog( null ) }
				title={ sprintf(
					/* translators: %d: number of rooms. */
					_n(
						'Remove %d room?',
						'Remove %d rooms?',
						ticked.length,
						'radius-hotel-booking'
					),
					ticked.length
				) }
				description={ __(
					'They are no longer sold, and past bookings keep them. Adding a number again brings its room back. Rooms with upcoming bookings are skipped.',
					'radius-hotel-booking'
				) }
				confirmLabel={ __( 'Remove rooms', 'radius-hotel-booking' ) }
				destructive
				onConfirm={ () =>
					bulk
						.mutateAsync( { action: 'remove', ids: ticked } )
						.then( report )
				}
			/>
		</div>
	);
}

/**
 * A compact filter select with an "all" option.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Accessible name.
 * @param {string}   props.anyLabel Label of the "all" option.
 * @param {string}   props.value    Selected id, or ANY.
 * @param {Function} props.onChange Called with the new value.
 * @param {Object[]} props.options  `{ id, name }`.
 * @return {JSX.Element} Select.
 */
function FilterSelect( { label, anyLabel, value, onChange, options } ) {
	return (
		<Select value={ value } onValueChange={ onChange }>
			<SelectTrigger aria-label={ label } className="h-10 w-44">
				<SelectValue />
			</SelectTrigger>
			<SelectContent>
				<SelectItem value={ ANY }>{ anyLabel }</SelectItem>
				{ options.map( ( option ) => (
					<SelectItem key={ option.id } value={ String( option.id ) }>
						{ option.name }
					</SelectItem>
				) ) }
			</SelectContent>
		</Select>
	);
}

/**
 * The rooms the last bulk action left unchanged, with the reason for each.
 *
 * @param {Object}   props           Props.
 * @param {Object[]} props.failed    `{ id, number, message }`.
 * @param {Function} props.onDismiss Hide the list.
 * @return {JSX.Element} Notice.
 */
function FailedList( { failed, onDismiss } ) {
	return (
		<div
			role="alert"
			className="flex gap-3 rounded-lg border border-warning/40 bg-warning-soft px-3 py-2.5"
		>
			<AlertTriangle
				className="mt-0.5 h-4 w-4 shrink-0 text-warning"
				aria-hidden="true"
			/>
			<div className="min-w-0 flex-1 space-y-1">
				<p className="m-0 text-sm font-semibold text-heading">
					{ sprintf(
						/* translators: %d: number of rooms. */
						_n(
							'%d room was not changed. It is still selected.',
							'%d rooms were not changed. They are still selected.',
							failed.length,
							'radius-hotel-booking'
						),
						failed.length
					) }
				</p>
				<ul className="m-0 list-none space-y-0.5 p-0">
					{ failed.map( ( item ) => (
						<li
							key={ item.id }
							className="m-0 break-words text-[13px] text-foreground"
						>
							{ item.message }
						</li>
					) ) }
				</ul>
			</div>
			<Button
				variant="ghost"
				size="icon"
				className="h-7 w-7 shrink-0"
				onClick={ onDismiss }
				aria-label={ __( 'Dismiss', 'radius-hotel-booking' ) }
			>
				<X className="h-4 w-4" aria-hidden="true" />
			</Button>
		</div>
	);
}

/**
 * Set the state (and reason) of the ticked rooms.
 *
 * @param {Object}   props         Props.
 * @param {number}   props.count   Ticked rooms.
 * @param {boolean}  props.busy    Saving.
 * @param {Function} props.onClose Close.
 * @param {Function} props.onSave  Called with `{ state, note }`.
 * @return {JSX.Element} Dialog.
 */
function BulkStateDialog( { count, busy, onClose, onSave } ) {
	const [ state, setState ] = useState( 'maintenance' );
	const [ note, setNote ] = useState( '' );

	return (
		<Dialog open onOpenChange={ ( open ) => ! open && onClose() }>
			<DialogContent className="max-w-md">
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						onSave( { state, note } );
					} }
					className="space-y-4"
				>
					<DialogHeader>
						<DialogTitle>
							{ sprintf(
								/* translators: %d: number of rooms. */
								_n(
									'State of %d room',
									'State of %d rooms',
									count,
									'radius-hotel-booking'
								),
								count
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
									name="rtbp-bulk-room-state"
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
								htmlFor="rtbp-bulk-room-note"
								className="text-sm font-semibold text-heading"
							>
								{ __( 'Reason', 'radius-hotel-booking' ) }
							</Label>
							<Input
								id="rtbp-bulk-room-note"
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
						<Button type="submit" disabled={ busy }>
							{ __( 'Save', 'radius-hotel-booking' ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}

/**
 * Move the ticked rooms to one room type.
 *
 * @param {Object}   props         Props.
 * @param {number}   props.count   Ticked rooms.
 * @param {Object[]} props.types   Room types.
 * @param {boolean}  props.busy    Saving.
 * @param {Function} props.onClose Close.
 * @param {Function} props.onSave  Called with the room type id.
 * @return {JSX.Element} Dialog.
 */
function BulkMoveDialog( { count, types, busy, onClose, onSave } ) {
	const [ typeId, setTypeId ] = useState( '' );
	const [ error, setError ] = useState( '' );

	return (
		<Dialog open onOpenChange={ ( open ) => ! open && onClose() }>
			<DialogContent className="max-w-md">
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						if ( ! Number( typeId ) ) {
							setError(
								__(
									'Choose the room type to move the rooms to.',
									'radius-hotel-booking'
								)
							);
							return;
						}
						onSave( Number( typeId ) );
					} }
					noValidate
					className="space-y-4"
				>
					<DialogHeader>
						<DialogTitle>
							{ sprintf(
								/* translators: %d: number of rooms. */
								_n(
									'Move %d room',
									'Move %d rooms',
									count,
									'radius-hotel-booking'
								),
								count
							) }
						</DialogTitle>
						<DialogDescription>
							{ __(
								'Rooms with upcoming bookings or a guest in them are skipped: move those one by one from their room type.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</DialogHeader>

					<div className="space-y-1.5">
						<Label
							htmlFor="rtbp-bulk-move-type"
							className="text-sm font-semibold text-heading"
						>
							{ __( 'Move to', 'radius-hotel-booking' ) }
						</Label>
						<Select
							value={ typeId }
							onValueChange={ ( next ) => {
								setTypeId( next );
								setError( '' );
							} }
						>
							<SelectTrigger
								id="rtbp-bulk-move-type"
								aria-invalid={ error ? true : undefined }
								className={ cn(
									error && '!border-destructive'
								) }
							>
								<SelectValue
									placeholder={ __(
										'Choose a room type',
										'radius-hotel-booking'
									) }
								/>
							</SelectTrigger>
							<SelectContent>
								{ types.map( ( option ) => (
									<SelectItem
										key={ option.id }
										value={ String( option.id ) }
									>
										{ option.name }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>
						{ error ? (
							<p
								role="alert"
								className="m-0 text-xs text-destructive"
							>
								{ error }
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
							{ __( 'Move rooms', 'radius-hotel-booking' ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
