/**
 * Amenities (`#/rooms/amenities`): the hotel's shared amenity library that
 * room types tick from. Add, rename, drag (or use the arrows) to reorder,
 * delete. A rename or delete reaches every room type that offers it; renaming
 * to the name of another amenity offers to merge the two.
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
	ListChecks,
	Pencil,
	Plus,
	Sparkles,
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
	useAddAmenity,
	useAmenities,
	useDeleteAmenity,
	useRenameAmenity,
	useReorderAmenities,
} from './api';
import CommonAmenitiesDialog from './components/CommonAmenitiesDialog';

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
 * "Standard Room, VIP Room" or "No room type".
 *
 * @param {Object} amenity Amenity.
 * @return {string} Text.
 */
const usedBy = ( amenity ) =>
	amenity.room_types.length
		? amenity.room_types.map( ( type ) => type.name ).join( ', ' )
		: __( 'Not used by any room type', 'radius-hotel-booking' );

/**
 * @return {JSX.Element} Screen.
 */
export default function Amenities() {
	const { data: amenities, isPending, error, refetch } = useAmenities();
	const canManage = useAccess( 'room_types.manage' ) !== 'locked';
	const reorder = useReorderAmenities();
	const remove = useDeleteAmenity();
	const rename = useRenameAmenity();
	const [ deleting, setDeleting ] = useState( null );
	const [ merging, setMerging ] = useState( null );
	const [ dragId, setDragId ] = useState( null );
	const [ commonOpen, setCommonOpen ] = useState( false );

	const saveOrder = ( ids ) =>
		reorder.mutate( ids, {
			onError: ( e ) => toastError( e ),
		} );

	const move = ( index, step ) => {
		const ids = amenities.map( ( amenity ) => amenity.id );
		const [ id ] = ids.splice( index, 1 );
		ids.splice( index + step, 0, id );
		saveOrder( ids );
	};

	const dropOn = ( targetId ) => {
		if ( ! dragId || dragId === targetId ) {
			return;
		}
		const ids = amenities
			.map( ( amenity ) => amenity.id )
			.filter( ( id ) => id !== dragId );
		ids.splice( ids.indexOf( targetId ), 0, dragId );
		// Dropped below its old place: land after the target instead.
		const from = amenities.findIndex(
			( amenity ) => amenity.id === dragId
		);
		const to = amenities.findIndex(
			( amenity ) => amenity.id === targetId
		);
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
				icon={ Sparkles }
				title={ __(
					'The amenities could not be loaded',
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
	} else if ( ! amenities.length ) {
		body = (
			<EmptyState
				icon={ Sparkles }
				title={ __( 'No amenities yet', 'radius-hotel-booking' ) }
				description={ __(
					'Add what your rooms offer once, e.g. Wi-Fi, Air conditioning, Mini bar. Then tick them on each room type.',
					'radius-hotel-booking'
				) }
				action={
					canManage ? (
						<Button onClick={ () => setCommonOpen( true ) }>
							<ListChecks
								className="h-4 w-4"
								aria-hidden="true"
							/>
							{ __(
								'Add common amenities',
								'radius-hotel-booking'
							) }
						</Button>
					) : null
				}
				className="border-0"
			/>
		);
	} else {
		body = (
			<ol className="m-0 list-none divide-y divide-border rounded-lg border border-border p-0">
				{ amenities.map( ( amenity, index ) => (
					<AmenityRow
						key={ amenity.id }
						amenity={ amenity }
						index={ index }
						last={ index === amenities.length - 1 }
						canManage={ canManage }
						busy={ reorder.isPending }
						dragging={ dragId === amenity.id }
						onDragStart={ () => setDragId( amenity.id ) }
						onDragEnd={ () => setDragId( null ) }
						onDrop={ () => dropOn( amenity.id ) }
						onMove={ ( step ) => move( index, step ) }
						onDelete={ () => setDeleting( amenity ) }
						onMerge={ setMerging }
					/>
				) ) }
			</ol>
		);
	}

	return (
		<div className="mx-auto max-w-3xl space-y-4">
			{ back }
			<Panel
				title={ __( 'Amenities', 'radius-hotel-booking' ) }
				description={ __(
					'One list for the whole hotel. Room types tick from it, and show their amenities in this order.',
					'radius-hotel-booking'
				) }
				actions={
					canManage && amenities?.length ? (
						<Button
							variant="outline"
							onClick={ () => setCommonOpen( true ) }
						>
							<ListChecks
								className="h-4 w-4"
								aria-hidden="true"
							/>
							{ __(
								'Add common amenities',
								'radius-hotel-booking'
							) }
						</Button>
					) : null
				}
			>
				<div className="space-y-4">
					{ canManage ? <AddAmenity /> : null }
					{ body }
				</div>
			</Panel>

			{ canManage ? (
				<CommonAmenitiesDialog
					open={ commonOpen }
					onOpenChange={ setCommonOpen }
				/>
			) : null }

			<ConfirmDialog
				open={ Boolean( deleting ) }
				onOpenChange={ ( open ) => ! open && setDeleting( null ) }
				title={ sprintf(
					/* translators: %s: amenity name. */
					__( 'Delete %s?', 'radius-hotel-booking' ),
					deleting?.name ?? ''
				) }
				description={
					deleting?.room_types.length
						? sprintf(
								/* translators: 1: number of room types, 2: their names. */
								_n(
									'It is also taken off %1$d room type: %2$s.',
									'It is also taken off %1$d room types: %2$s.',
									deleting.room_types.length,
									'radius-hotel-booking'
								),
								deleting.room_types.length,
								usedBy( deleting )
						  )
						: __( 'No room type uses it.', 'radius-hotel-booking' )
				}
				confirmLabel={ __( 'Delete amenity', 'radius-hotel-booking' ) }
				destructive
				onConfirm={ () =>
					remove.mutateAsync( deleting.id ).then( () =>
						toast.success(
							sprintf(
								/* translators: %s: amenity name. */
								__( '%s deleted.', 'radius-hotel-booking' ),
								deleting.name
							)
						)
					)
				}
			/>

			<ConfirmDialog
				open={ Boolean( merging ) }
				onOpenChange={ ( open ) => ! open && setMerging( null ) }
				title={ sprintf(
					/* translators: 1: amenity being renamed, 2: existing amenity. */
					__( 'Merge %1$s into %2$s?', 'radius-hotel-booking' ),
					merging?.from.name ?? '',
					merging?.into ?? ''
				) }
				description={ sprintf(
					/* translators: 1: amenity being renamed, 2: existing amenity. */
					__(
						'There is already an amenity called %2$s. Room types that offer %1$s will offer %2$s instead, and %1$s is removed from the list.',
						'radius-hotel-booking'
					),
					merging?.from.name ?? '',
					merging?.into ?? ''
				) }
				confirmLabel={ __( 'Merge', 'radius-hotel-booking' ) }
				onConfirm={ () =>
					rename
						.mutateAsync( {
							id: merging.from.id,
							name: merging.into,
							merge: true,
						} )
						.then( ( data ) => {
							merging.done?.();
							toast.success( data.message );
						} )
				}
			/>
		</div>
	);
}

/**
 * The "add an amenity" box.
 *
 * @return {JSX.Element} Form.
 */
function AddAmenity() {
	const add = useAddAmenity();
	const [ name, setName ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const submit = ( event ) => {
		event.preventDefault();
		if ( ! name.trim() ) {
			setError(
				__( 'Give the amenity a name.', 'radius-hotel-booking' )
			);
			return;
		}
		add.mutate( name.trim(), {
			onSuccess: () => {
				setName( '' );
				setError( '' );
				toast.success( __( 'Amenity added.', 'radius-hotel-booking' ) );
			},
			onError: ( e ) => setError( fieldError( e, 'name' ) ),
		} );
	};

	return (
		<form onSubmit={ submit } noValidate className="space-y-1.5">
			<label htmlFor="rtbp-new-amenity" className="sr-only">
				{ __( 'New amenity name', 'radius-hotel-booking' ) }
			</label>
			<div className="flex gap-2">
				<Input
					id="rtbp-new-amenity"
					value={ name }
					onChange={ ( event ) => setName( event.target.value ) }
					maxLength={ 60 }
					placeholder={ __( 'e.g. Balcony', 'radius-hotel-booking' ) }
					aria-invalid={ error ? true : undefined }
					aria-describedby={
						error ? 'rtbp-new-amenity-error' : undefined
					}
					className={ cn(
						'min-w-0 flex-1',
						error && '!border-destructive'
					) }
				/>
				<Button type="submit" disabled={ add.isPending }>
					<Plus className="h-4 w-4" aria-hidden="true" />
					{ __( 'Add amenity', 'radius-hotel-booking' ) }
				</Button>
			</div>
			{ error ? (
				<p
					id="rtbp-new-amenity-error"
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
 * One amenity: drag handle, name (rename in place), the room types using it,
 * arrows, delete.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.amenity     Amenity.
 * @param {number}   props.index       Position.
 * @param {boolean}  props.last        Last in the list.
 * @param {boolean}  props.canManage   May change amenities.
 * @param {boolean}  props.busy        An order save is running.
 * @param {boolean}  props.dragging    This row is being dragged.
 * @param {Function} props.onDragStart Drag started.
 * @param {Function} props.onDragEnd   Drag ended.
 * @param {Function} props.onDrop      Something was dropped on this row.
 * @param {Function} props.onMove      Called with -1 (up) or 1 (down).
 * @param {Function} props.onDelete    Delete asked.
 * @param {Function} props.onMerge     Called with `{ from, into, done }` when
 *                                     the new name belongs to another amenity.
 * @return {JSX.Element} Row.
 */
function AmenityRow( {
	amenity,
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
	onMerge,
} ) {
	const rename = useRenameAmenity();
	const [ editing, setEditing ] = useState( false );
	const [ name, setName ] = useState( amenity.name );
	const [ error, setError ] = useState( '' );
	const [ over, setOver ] = useState( false );

	const save = ( event ) => {
		event.preventDefault();
		if ( name.trim() === amenity.name ) {
			setEditing( false );
			return;
		}
		rename.mutate(
			{ id: amenity.id, name: name.trim() },
			{
				onSuccess: () => {
					setEditing( false );
					setError( '' );
					toast.success(
						__( 'Amenity renamed.', 'radius-hotel-booking' )
					);
				},
				onError: ( e ) => {
					if ( e?.code === 'amenity_name_taken' ) {
						onMerge( {
							from: amenity,
							into: e.data?.name ?? name.trim(),
							done: () => setEditing( false ),
						} );
						return;
					}
					setError( fieldError( e, 'name' ) );
				},
			}
		);
	};

	const cancel = () => {
		setEditing( false );
		setName( amenity.name );
		setError( '' );
	};

	const count = amenity.room_types.length;

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
						maxLength={ 60 }
						autoFocus
						aria-label={ __(
							'Amenity name',
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
						{ amenity.name }
					</p>
					<p
						className="m-0 truncate text-xs text-muted-foreground"
						title={ count ? usedBy( amenity ) : undefined }
					>
						{ count
							? sprintf(
									/* translators: 1: number of room types, 2: their names. */
									_n(
										'%1$d room type: %2$s',
										'%1$d room types: %2$s',
										count,
										'radius-hotel-booking'
									),
									count,
									usedBy( amenity )
							  )
							: usedBy( amenity ) }
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
							/* translators: %s: amenity name. */
							__( 'Move %s up', 'radius-hotel-booking' ),
							amenity.name
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
							/* translators: %s: amenity name. */
							__( 'Move %s down', 'radius-hotel-booking' ),
							amenity.name
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
							/* translators: %s: amenity name. */
							__( 'Rename %s', 'radius-hotel-booking' ),
							amenity.name
						) }
					>
						<Pencil className="h-4 w-4" aria-hidden="true" />
					</Button>
					<Button
						type="button"
						variant="ghost"
						size="icon"
						onClick={ onDelete }
						aria-label={ sprintf(
							/* translators: %s: amenity name. */
							__( 'Delete %s', 'radius-hotel-booking' ),
							amenity.name
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
