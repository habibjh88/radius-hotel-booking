/**
 * Floors (`#/rooms/floors`, feature 6.3): the hotel's ordered list of named
 * floors. Add, rename, drag (or use the arrows) to reorder, delete. A floor
 * that holds rooms — removed rooms included — cannot be deleted.
 */
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	ArrowDown,
	ArrowLeft,
	ArrowUp,
	Check,
	GripVertical,
	Layers,
	Pencil,
	Plus,
	Trash2,
	X,
} from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useAccess } from '@/lib/access';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import {
	useAddFloor,
	useDeleteFloor,
	useFloors,
	useRenameFloor,
	useReorderFloors,
} from './api';

/**
 * The server's message for a field, else its general message.
 *
 * @param {Error}  error Error from src/api/client.js.
 * @param {string} field Field name.
 * @return {string} Message.
 */
const fieldError = ( error, field ) =>
	error?.errors?.[ field ]?.first_message || error?.message || '';

/**
 * @return {JSX.Element} Screen.
 */
export default function Floors() {
	const { data: floors, isPending, error, refetch } = useFloors();
	const canManage = useAccess( 'rooms.manage' ) !== 'locked';
	const reorder = useReorderFloors();
	const remove = useDeleteFloor();
	const [ deleting, setDeleting ] = useState( null );
	const [ dragId, setDragId ] = useState( null );

	const saveOrder = ( ids ) =>
		reorder.mutate( ids, {
			onError: ( e ) => toastError( e ),
		} );

	const move = ( index, step ) => {
		const ids = floors.map( ( floor ) => floor.id );
		const [ id ] = ids.splice( index, 1 );
		ids.splice( index + step, 0, id );
		saveOrder( ids );
	};

	const dropOn = ( targetId ) => {
		if ( ! dragId || dragId === targetId ) {
			return;
		}
		const ids = floors
			.map( ( floor ) => floor.id )
			.filter( ( id ) => id !== dragId );
		ids.splice( ids.indexOf( targetId ), 0, dragId );
		// Dropped below its old place: land after the target instead.
		const from = floors.findIndex( ( floor ) => floor.id === dragId );
		const to = floors.findIndex( ( floor ) => floor.id === targetId );
		if ( from < to ) {
			const at = ids.indexOf( dragId );
			ids.splice( at, 1 );
			ids.splice( at + 1, 0, dragId );
		}
		saveOrder( ids );
	};

	const back = (
		<Link
			to="/rooms"
			className="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground no-underline hover:text-heading"
		>
			<ArrowLeft className="h-4 w-4" aria-hidden="true" />
			{ __( 'Room types', 'radius-hotel-booking' ) }
		</Link>
	);

	let body;
	if ( isPending ) {
		body = (
			<div className="space-y-2">
				{ [ 0, 1, 2 ].map( ( key ) => (
					<Skeleton key={ key } className="h-12 w-full" />
				) ) }
			</div>
		);
	} else if ( error ) {
		body = (
			<EmptyState
				icon={ Layers }
				title={ __(
					'The floors could not be loaded',
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
		);
	} else if ( ! floors.length ) {
		body = (
			<EmptyState
				icon={ Layers }
				title={ __( 'No floors yet', 'radius-hotel-booking' ) }
				description={ __(
					'Add your floors from the bottom up, e.g. Ground Floor, Floor 1, Floor 2.',
					'radius-hotel-booking'
				) }
				className="border-0"
			/>
		);
	} else {
		body = (
			<ol className="m-0 list-none divide-y divide-border rounded-lg border border-border p-0">
				{ floors.map( ( floor, index ) => (
					<FloorRow
						key={ floor.id }
						floor={ floor }
						index={ index }
						last={ index === floors.length - 1 }
						canManage={ canManage }
						busy={ reorder.isPending }
						dragging={ dragId === floor.id }
						onDragStart={ () => setDragId( floor.id ) }
						onDragEnd={ () => setDragId( null ) }
						onDrop={ () => dropOn( floor.id ) }
						onMove={ ( step ) => move( index, step ) }
						onDelete={ () => setDeleting( floor ) }
					/>
				) ) }
			</ol>
		);
	}

	return (
		<div className="mx-auto max-w-3xl space-y-4">
			{ back }
			<Panel
				title={ __( 'Floors', 'radius-hotel-booking' ) }
				description={ __(
					'Every room sits on one floor. The order here is the order everywhere else.',
					'radius-hotel-booking'
				) }
			>
				<div className="space-y-4">
					{ canManage ? <AddFloor /> : null }
					{ body }
				</div>
			</Panel>

			<ConfirmDialog
				open={ Boolean( deleting ) }
				onOpenChange={ ( open ) => ! open && setDeleting( null ) }
				title={ sprintf(
					/* translators: %s: floor name. */
					__( 'Delete %s?', 'radius-hotel-booking' ),
					deleting?.name ?? ''
				) }
				description={ __(
					'The floor is removed from the list.',
					'radius-hotel-booking'
				) }
				confirmLabel={ __( 'Delete floor', 'radius-hotel-booking' ) }
				destructive
				onConfirm={ () =>
					remove.mutateAsync( deleting.id ).then( () =>
						toast.success(
							sprintf(
								/* translators: %s: floor name. */
								__( '%s deleted.', 'radius-hotel-booking' ),
								deleting.name
							)
						)
					)
				}
			/>
		</div>
	);
}

/**
 * The "add a floor" box.
 *
 * @return {JSX.Element} Form.
 */
function AddFloor() {
	const add = useAddFloor();
	const [ name, setName ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const submit = ( event ) => {
		event.preventDefault();
		if ( ! name.trim() ) {
			setError( __( 'Give the floor a name.', 'radius-hotel-booking' ) );
			return;
		}
		add.mutate( name.trim(), {
			onSuccess: () => {
				setName( '' );
				setError( '' );
				toast.success( __( 'Floor added.', 'radius-hotel-booking' ) );
			},
			onError: ( e ) => setError( fieldError( e, 'name' ) ),
		} );
	};

	return (
		<form onSubmit={ submit } noValidate className="space-y-1.5">
			<label htmlFor="rtbp-new-floor" className="sr-only">
				{ __( 'New floor name', 'radius-hotel-booking' ) }
			</label>
			<div className="flex gap-2">
				<Input
					id="rtbp-new-floor"
					value={ name }
					onChange={ ( event ) => setName( event.target.value ) }
					maxLength={ 100 }
					placeholder={ __( 'e.g. Floor 3', 'radius-hotel-booking' ) }
					aria-invalid={ error ? true : undefined }
					aria-describedby={
						error ? 'rtbp-new-floor-error' : undefined
					}
					className={ cn(
						'min-w-0 flex-1',
						error && '!border-destructive'
					) }
				/>
				<Button type="submit" disabled={ add.isPending }>
					<Plus className="h-4 w-4" aria-hidden="true" />
					{ __( 'Add floor', 'radius-hotel-booking' ) }
				</Button>
			</div>
			{ error ? (
				<p
					id="rtbp-new-floor-error"
					role="alert"
					className="m-0 text-xs text-destructive"
				>
					{ error }
				</p>
			) : null }
		</form>
	);
}

/**
 * One floor: drag handle, name (rename in place), room count, arrows, delete.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.floor       Floor.
 * @param {number}   props.index       Position.
 * @param {boolean}  props.last        Last in the list.
 * @param {boolean}  props.canManage   May change floors.
 * @param {boolean}  props.busy        An order save is running.
 * @param {boolean}  props.dragging    This row is being dragged.
 * @param {Function} props.onDragStart Drag started.
 * @param {Function} props.onDragEnd   Drag ended.
 * @param {Function} props.onDrop      Something was dropped on this row.
 * @param {Function} props.onMove      Called with -1 (up) or 1 (down).
 * @param {Function} props.onDelete    Delete asked.
 * @return {JSX.Element} Row.
 */
function FloorRow( {
	floor,
	index,
	last,
	canManage,
	busy,
	dragging,
	onDragStart,
	onDragEnd,
	onDrop,
	onMove,
	onDelete,
} ) {
	const rename = useRenameFloor();
	const [ editing, setEditing ] = useState( false );
	const [ name, setName ] = useState( floor.name );
	const [ error, setError ] = useState( '' );
	const [ over, setOver ] = useState( false );

	const save = ( event ) => {
		event.preventDefault();
		if ( name.trim() === floor.name ) {
			setEditing( false );
			return;
		}
		rename.mutate(
			{ id: floor.id, name: name.trim() },
			{
				onSuccess: () => {
					setEditing( false );
					setError( '' );
					toast.success(
						__( 'Floor renamed.', 'radius-hotel-booking' )
					);
				},
				onError: ( e ) => setError( fieldError( e, 'name' ) ),
			}
		);
	};

	const cancel = () => {
		setEditing( false );
		setName( floor.name );
		setError( '' );
	};

	const inUse = floor.total > 0;
	const removedOnly = floor.total > floor.rooms;

	return (
		<li
			className={ cn(
				'm-0 flex flex-wrap items-center gap-2 px-3 py-2.5',
				dragging && 'opacity-50',
				over && 'bg-primary-soft'
			) }
			draggable={ canManage && ! editing }
			onDragStart={ ( event ) => {
				event.dataTransfer.effectAllowed = 'move';
				onDragStart();
			} }
			onDragEnd={ onDragEnd }
			onDragOver={ ( event ) => {
				if ( canManage ) {
					event.preventDefault();
					setOver( true );
				}
			} }
			onDragLeave={ () => setOver( false ) }
			onDrop={ ( event ) => {
				event.preventDefault();
				setOver( false );
				onDrop();
			} }
		>
			{ canManage ? (
				<GripVertical
					className="hidden h-4 w-4 shrink-0 cursor-grab text-muted-foreground sm:block"
					aria-hidden="true"
				/>
			) : null }

			{ editing ? (
				<form
					onSubmit={ save }
					className="flex min-w-0 flex-1 basis-full flex-wrap items-center gap-2 sm:basis-auto"
				>
					<Input
						value={ name }
						onChange={ ( event ) => setName( event.target.value ) }
						onKeyDown={ ( event ) =>
							event.key === 'Escape' && cancel()
						}
						maxLength={ 100 }
						autoFocus
						aria-label={ __(
							'Floor name',
							'radius-hotel-booking'
						) }
						aria-invalid={ error ? true : undefined }
						className={ cn(
							'h-9 min-w-0 flex-1',
							error && '!border-destructive'
						) }
					/>
					<Button
						type="submit"
						size="sm"
						disabled={ rename.isPending }
						aria-label={ __( 'Save name', 'radius-hotel-booking' ) }
					>
						<Check className="h-4 w-4" aria-hidden="true" />
					</Button>
					<Button
						type="button"
						size="sm"
						variant="ghost"
						onClick={ cancel }
						aria-label={ __( 'Cancel', 'radius-hotel-booking' ) }
					>
						<X className="h-4 w-4" aria-hidden="true" />
					</Button>
					{ error ? (
						<p
							role="alert"
							className="m-0 basis-full text-xs text-destructive"
						>
							{ error }
						</p>
					) : null }
				</form>
			) : (
				<div className="min-w-0 flex-1">
					<p className="m-0 break-words text-sm font-semibold text-heading sm:truncate">
						{ floor.name }
					</p>
					<p className="m-0 text-xs text-muted-foreground">
						{ sprintf(
							/* translators: %d: number of rooms. */
							_n(
								'%d room',
								'%d rooms',
								floor.rooms,
								'radius-hotel-booking'
							),
							floor.rooms
						) }
						{ removedOnly
							? ' · ' +
							  sprintf(
									/* translators: %d: number of removed rooms. */
									_n(
										'%d removed room',
										'%d removed rooms',
										floor.total - floor.rooms,
										'radius-hotel-booking'
									),
									floor.total - floor.rooms
							  )
							: '' }
					</p>
				</div>
			) }

			{ canManage && ! editing ? (
				<div className="flex shrink-0 items-center gap-0.5">
					<Button
						type="button"
						variant="ghost"
						size="icon"
						className="h-9 w-9 sm:h-10 sm:w-10"
						onClick={ () => onMove( -1 ) }
						disabled={ index === 0 || busy }
						aria-label={ sprintf(
							/* translators: %s: floor name. */
							__( 'Move %s up', 'radius-hotel-booking' ),
							floor.name
						) }
					>
						<ArrowUp className="h-4 w-4" aria-hidden="true" />
					</Button>
					<Button
						type="button"
						variant="ghost"
						size="icon"
						className="h-9 w-9 sm:h-10 sm:w-10"
						onClick={ () => onMove( 1 ) }
						disabled={ last || busy }
						aria-label={ sprintf(
							/* translators: %s: floor name. */
							__( 'Move %s down', 'radius-hotel-booking' ),
							floor.name
						) }
					>
						<ArrowDown className="h-4 w-4" aria-hidden="true" />
					</Button>
					<Button
						type="button"
						variant="ghost"
						size="icon"
						className="h-9 w-9 sm:h-10 sm:w-10"
						onClick={ () => setEditing( true ) }
						aria-label={ sprintf(
							/* translators: %s: floor name. */
							__( 'Rename %s', 'radius-hotel-booking' ),
							floor.name
						) }
					>
						<Pencil className="h-4 w-4" aria-hidden="true" />
					</Button>
					<Button
						type="button"
						variant="ghost"
						size="icon"
						onClick={ onDelete }
						disabled={ inUse }
						title={
							inUse
								? __(
										'A floor with rooms cannot be deleted.',
										'radius-hotel-booking'
								  )
								: undefined
						}
						aria-label={ sprintf(
							/* translators: %s: floor name. */
							__( 'Delete %s', 'radius-hotel-booking' ),
							floor.name
						) }
						className="h-9 w-9 text-destructive sm:h-10 sm:w-10"
					>
						<Trash2 className="h-4 w-4" aria-hidden="true" />
					</Button>
				</div>
			) : null }
		</li>
	);
}
