/**
 * Amenities (`#/rooms/amenities`): the hotel's shared amenity library that
 * room types tick from, in sections (Room comfort, Bathroom …). Add an
 * amenity into a section, give it an icon, rename it, drag it within its
 * section or into another (or use the arrows and "Move to section"), and
 * drag the sections themselves into order. "Add section" sits at the end;
 * deleting a section moves its amenities into another. A rename or delete
 * reaches every room type that offers the amenity; renaming to the name of
 * another amenity offers to merge the two.
 */
import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	ArrowDown,
	ArrowLeft,
	ArrowUp,
	Check,
	FolderInput,
	FolderPlus,
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
import IconField from '@/components/icons/IconField';
import LucideIcon from '@/components/icons/LucideIcon';
import { Button } from '@/components/ui/button';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuLabel,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useAccess } from '@/lib/access';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import {
	useAddAmenity,
	useAddAmenitySection,
	useAmenities,
	useAmenityLayout,
	useAmenitySections,
	useDeleteAmenity,
	useRenameAmenity,
	useRenameAmenitySection,
} from './api';
import CommonAmenitiesDialog from './components/CommonAmenitiesDialog';
import DeleteSectionDialog from './components/DeleteSectionDialog';

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
 * The layout the server takes: sections in order, each with its amenity ids.
 *
 * @param {Object[]} groups `{ id, items: [ amenity ] }`.
 * @return {Object[]} `[ { id, amenities: [ ids ] } ]`.
 */
const toLayout = ( groups ) =>
	groups.map( ( group ) => ( {
		id: group.id,
		amenities: group.items.map( ( amenity ) => amenity.id ),
	} ) );

/**
 * @return {JSX.Element} Screen.
 */
export default function Amenities() {
	const amenitiesQuery = useAmenities();
	const { data: sections } = useAmenitySections();
	const { data: amenities, isPending, error, refetch } = amenitiesQuery;
	const canManage = useAccess( 'room_types.manage' ) !== 'locked';
	const layout = useAmenityLayout();
	const remove = useDeleteAmenity();
	const rename = useRenameAmenity();
	const [ deleting, setDeleting ] = useState( null );
	const [ deletingSection, setDeletingSection ] = useState( null );
	const [ merging, setMerging ] = useState( null );
	// What is being dragged: `{ type: 'amenity' | 'section', id }`.
	const [ drag, setDrag ] = useState( null );
	const [ overSection, setOverSection ] = useState( null );
	const [ commonOpen, setCommonOpen ] = useState( false );

	const groups = useMemo(
		() =>
			( sections ?? [] ).map( ( section ) => ( {
				...section,
				items: ( amenities ?? [] ).filter(
					( amenity ) => amenity.section_id === section.id
				),
			} ) ),
		[ sections, amenities ]
	);

	const save = ( next ) =>
		layout.mutate( toLayout( next ), {
			onError: ( e ) => toastError( e ),
		} );

	// An amenity to a place: section `to`, before amenity `beforeId` (or last).
	const place = ( amenityId, to, beforeId = null ) => {
		const moving = ( amenities ?? [] ).find( ( a ) => a.id === amenityId );
		if ( ! moving || amenityId === beforeId ) {
			return;
		}
		const next = groups.map( ( group ) => ( {
			...group,
			items: group.items.filter( ( a ) => a.id !== amenityId ),
		} ) );
		const target = next.find( ( group ) => group.id === to );
		if ( ! target ) {
			return;
		}
		const at = beforeId
			? target.items.findIndex( ( a ) => a.id === beforeId )
			: -1;
		if ( at < 0 ) {
			target.items.push( moving );
		} else {
			target.items.splice( at, 0, moving );
		}
		save( next );
	};

	// Within a section, one step up or down.
	const step = ( group, index, by ) => {
		const items = [ ...group.items ];
		const [ item ] = items.splice( index, 1 );
		items.splice( index + by, 0, item );
		save(
			groups.map( ( g ) => ( g.id === group.id ? { ...g, items } : g ) )
		);
	};

	// Sections: one step, or dropped on another.
	const stepSection = ( index, by ) => {
		const next = [ ...groups ];
		const [ group ] = next.splice( index, 1 );
		next.splice( index + by, 0, group );
		save( next );
	};
	const dropSection = ( targetId ) => {
		if ( ! drag || 'section' !== drag.type || drag.id === targetId ) {
			return;
		}
		const next = groups.filter( ( g ) => g.id !== drag.id );
		const moving = groups.find( ( g ) => g.id === drag.id );
		const from = groups.findIndex( ( g ) => g.id === drag.id );
		let at = next.findIndex( ( g ) => g.id === targetId );
		// Dropped on a section below its old place: land after it.
		if ( from <= at ) {
			at += 1;
		}
		next.splice( at, 0, moving );
		save( next );
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
	} else if ( ! amenities.length && ! groups.length ) {
		body = (
			<EmptyState
				icon={ Sparkles }
				title={ __( 'No amenities yet', 'radius-hotel-booking' ) }
				description={ __(
					'Add what your rooms offer once, in sections, e.g. Wi-Fi under Technology. Then tick them on each room type.',
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
			<div className="space-y-4">
				{ groups.map( ( group, groupIndex ) => (
					<SectionCard
						key={ group.id }
						group={ group }
						first={ groupIndex === 0 }
						last={ groupIndex === groups.length - 1 }
						canManage={ canManage }
						busy={ layout.isPending }
						dragging={
							'section' === drag?.type && drag.id === group.id
						}
						dropping={
							overSection === group.id &&
							drag &&
							! (
								'section' === drag.type && drag.id === group.id
							)
						}
						onDragStart={ () =>
							setDrag( { type: 'section', id: group.id } )
						}
						onDragEnd={ () => {
							setDrag( null );
							setOverSection( null );
						} }
						onDragOver={ () => setOverSection( group.id ) }
						onDragLeave={ () =>
							setOverSection( ( id ) =>
								id === group.id ? null : id
							)
						}
						onDrop={ () => {
							setOverSection( null );
							if ( 'section' === drag?.type ) {
								dropSection( group.id );
							} else if ( 'amenity' === drag?.type ) {
								place( drag.id, group.id );
							}
							setDrag( null );
						} }
						onStep={ ( by ) => stepSection( groupIndex, by ) }
						onDelete={ () => setDeletingSection( group ) }
					>
						{ group.items.length ? (
							<ol className="m-0 list-none divide-y divide-border p-0">
								{ group.items.map( ( amenity, index ) => (
									<AmenityRow
										key={ amenity.id }
										amenity={ amenity }
										index={ index }
										last={
											index === group.items.length - 1
										}
										canManage={ canManage }
										busy={ layout.isPending }
										dragging={
											'amenity' === drag?.type &&
											drag.id === amenity.id
										}
										onDragStart={ () =>
											setDrag( {
												type: 'amenity',
												id: amenity.id,
											} )
										}
										onDragEnd={ () => {
											setDrag( null );
											setOverSection( null );
										} }
										onDrop={ () => {
											if ( 'amenity' === drag?.type ) {
												place(
													drag.id,
													group.id,
													amenity.id
												);
											}
											setDrag( null );
											setOverSection( null );
										} }
										onMove={ ( by ) =>
											step( group, index, by )
										}
										onDelete={ () =>
											setDeleting( amenity )
										}
										onMerge={ setMerging }
										sections={ groups }
										onMoveTo={ ( to ) =>
											place( amenity.id, to )
										}
									/>
								) ) }
							</ol>
						) : (
							<p className="m-0 px-4 py-6 text-center text-sm text-muted-foreground">
								{ canManage
									? __(
											'No amenities here yet. Drag one in, or add one above with this section chosen.',
											'radius-hotel-booking'
									  )
									: __(
											'No amenities in this section.',
											'radius-hotel-booking'
									  ) }
							</p>
						) }
					</SectionCard>
				) ) }
				{ canManage ? <AddSection /> : null }
			</div>
		);
	}

	return (
		<div className="mx-auto max-w-3xl space-y-4">
			{ back }
			<Panel
				title={ __( 'Amenities', 'radius-hotel-booking' ) }
				description={ __(
					'One list for the whole hotel, in sections. Room types tick from it, and room pages show the amenities by section, in this order.',
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
				<div className="space-y-5">
					{ canManage && groups.length ? (
						<AddAmenity sections={ groups } />
					) : null }
					{ body }
				</div>
			</Panel>

			{ canManage ? (
				<CommonAmenitiesDialog
					open={ commonOpen }
					onOpenChange={ setCommonOpen }
				/>
			) : null }

			{ canManage ? (
				<DeleteSectionDialog
					section={ deletingSection }
					sections={ groups }
					onClose={ () => setDeletingSection( null ) }
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
 * One section: a header (drag handle, name with rename, count, arrows,
 * delete) over its amenities. The whole card takes dropped amenities and
 * sections.
 *
 * @param {Object}      props             Props.
 * @param {Object}      props.group       Section with `items`.
 * @param {boolean}     props.first       First section.
 * @param {boolean}     props.last        Last section.
 * @param {boolean}     props.canManage   May change it.
 * @param {boolean}     props.busy        A save is running.
 * @param {boolean}     props.dragging    This section is being dragged.
 * @param {boolean}     props.dropping    Something is dragged over it.
 * @param {Function}    props.onDragStart Drag started (header).
 * @param {Function}    props.onDragEnd   Drag ended.
 * @param {Function}    props.onDragOver  Something is over the card.
 * @param {Function}    props.onDragLeave It left.
 * @param {Function}    props.onDrop      Something was dropped on the card.
 * @param {Function}    props.onStep      Called with -1 or 1.
 * @param {Function}    props.onDelete    Delete asked.
 * @param {JSX.Element} props.children    The amenity list.
 * @return {JSX.Element} Card.
 */
function SectionCard( {
	group,
	first,
	last,
	canManage,
	busy,
	dragging,
	dropping,
	onDragStart,
	onDragEnd,
	onDragOver,
	onDragLeave,
	onDrop,
	onStep,
	onDelete,
	children,
} ) {
	const renameSection = useRenameAmenitySection();
	const [ editing, setEditing ] = useState( false );
	const [ name, setName ] = useState( group.name );
	const [ error, setError ] = useState( '' );

	const save = ( event ) => {
		event.preventDefault();
		if ( name.trim() === group.name ) {
			setEditing( false );
			return;
		}
		renameSection.mutate(
			{ id: group.id, name: name.trim() },
			{
				onSuccess: () => {
					setEditing( false );
					setError( '' );
					toast.success(
						__( 'Section renamed.', 'radius-hotel-booking' )
					);
				},
				onError: ( e ) => setError( fieldError( e, 'name' ) ),
			}
		);
	};

	return (
		<section
			className={ cn(
				'overflow-hidden rounded-xl border bg-white shadow-sm transition-all',
				dropping
					? 'border-primary ring-2 ring-primary/30'
					: 'border-border',
				dragging && 'opacity-50'
			) }
			aria-label={ group.name }
			onDragOver={ ( event ) => {
				if ( canManage ) {
					event.preventDefault();
					onDragOver();
				}
			} }
			onDragLeave={ ( event ) => {
				if ( ! event.currentTarget.contains( event.relatedTarget ) ) {
					onDragLeave();
				}
			} }
			onDrop={ ( event ) => {
				event.preventDefault();
				onDrop();
			} }
		>
			<header
				className="flex flex-wrap items-center gap-2 border-b border-border bg-muted/40 px-3 py-2"
				draggable={ canManage && ! editing }
				onDragStart={ ( event ) => {
					event.dataTransfer.effectAllowed = 'move';
					event.dataTransfer.setData(
						'text/plain',
						`section-${ group.id }`
					);
					onDragStart();
				} }
				onDragEnd={ onDragEnd }
			>
				{ canManage ? (
					<GripVertical
						className="h-4 w-4 shrink-0 cursor-grab text-muted-foreground"
						aria-hidden="true"
					/>
				) : null }
				{ editing ? (
					<form
						onSubmit={ save }
						className="flex min-w-0 flex-1 flex-wrap items-center gap-2"
					>
						<Input
							value={ name }
							onChange={ ( event ) =>
								setName( event.target.value )
							}
							onKeyDown={ ( event ) => {
								if ( event.key === 'Escape' ) {
									setEditing( false );
									setName( group.name );
									setError( '' );
								}
							} }
							maxLength={ 60 }
							autoFocus
							aria-label={ __(
								'Section name',
								'radius-hotel-booking'
							) }
							className={ cn(
								'h-9 min-w-0 flex-1',
								error && '!border-destructive'
							) }
						/>
						<Button
							type="submit"
							size="sm"
							disabled={ renameSection.isPending }
							aria-label={ __(
								'Save name',
								'radius-hotel-booking'
							) }
						>
							<Check className="h-4 w-4" aria-hidden="true" />
						</Button>
						<Button
							type="button"
							size="sm"
							variant="ghost"
							onClick={ () => {
								setEditing( false );
								setName( group.name );
								setError( '' );
							} }
							aria-label={ __(
								'Cancel',
								'radius-hotel-booking'
							) }
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
					<h3 className="m-0 flex min-w-0 flex-1 items-center gap-2 text-xs font-bold uppercase tracking-wider text-heading">
						<span className="truncate">{ group.name }</span>
						<span className="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold normal-case tracking-normal text-muted-foreground shadow-sm">
							{ group.items.length }
						</span>
					</h3>
				) }
				{ canManage && ! editing ? (
					<div className="flex shrink-0 items-center gap-0.5">
						<Button
							type="button"
							variant="ghost"
							size="icon"
							className="h-8 w-8"
							onClick={ () => onStep( -1 ) }
							disabled={ first || busy }
							aria-label={ sprintf(
								/* translators: %s: section name. */
								__(
									'Move section %s up',
									'radius-hotel-booking'
								),
								group.name
							) }
						>
							<ArrowUp className="h-4 w-4" aria-hidden="true" />
						</Button>
						<Button
							type="button"
							variant="ghost"
							size="icon"
							className="h-8 w-8"
							onClick={ () => onStep( 1 ) }
							disabled={ last || busy }
							aria-label={ sprintf(
								/* translators: %s: section name. */
								__(
									'Move section %s down',
									'radius-hotel-booking'
								),
								group.name
							) }
						>
							<ArrowDown className="h-4 w-4" aria-hidden="true" />
						</Button>
						<Button
							type="button"
							variant="ghost"
							size="icon"
							className="h-8 w-8"
							onClick={ () => setEditing( true ) }
							aria-label={ sprintf(
								/* translators: %s: section name. */
								__(
									'Rename section %s',
									'radius-hotel-booking'
								),
								group.name
							) }
						>
							<Pencil className="h-4 w-4" aria-hidden="true" />
						</Button>
						<Button
							type="button"
							variant="ghost"
							size="icon"
							className="h-8 w-8 text-destructive"
							onClick={ onDelete }
							aria-label={ sprintf(
								/* translators: %s: section name. */
								__(
									'Delete section %s',
									'radius-hotel-booking'
								),
								group.name
							) }
						>
							<Trash2 className="h-4 w-4" aria-hidden="true" />
						</Button>
					</div>
				) : null }
			</header>
			{ children }
		</section>
	);
}

/**
 * "Add section", at the end of the list.
 *
 * @return {JSX.Element} Button or form.
 */
function AddSection() {
	const add = useAddAmenitySection();
	const [ open, setOpen ] = useState( false );
	const [ name, setName ] = useState( '' );
	const [ error, setError ] = useState( '' );

	if ( ! open ) {
		return (
			<button
				type="button"
				onClick={ () => setOpen( true ) }
				className="flex min-h-[52px] w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border bg-transparent text-sm font-semibold normal-case tracking-normal text-muted-foreground shadow-none transition-colors hover:border-primary hover:bg-primary-softer hover:text-primary focus:border-primary focus:bg-primary-softer focus:text-primary"
			>
				<FolderPlus className="h-4 w-4" aria-hidden="true" />
				{ __( 'Add section', 'radius-hotel-booking' ) }
			</button>
		);
	}

	const submit = ( event ) => {
		event.preventDefault();
		if ( ! name.trim() ) {
			setError(
				__( 'Give the section a name.', 'radius-hotel-booking' )
			);
			return;
		}
		add.mutate( name.trim(), {
			onSuccess: () => {
				setName( '' );
				setError( '' );
				setOpen( false );
				toast.success( __( 'Section added.', 'radius-hotel-booking' ) );
			},
			onError: ( e ) => setError( fieldError( e, 'name' ) ),
		} );
	};

	return (
		<form
			onSubmit={ submit }
			noValidate
			className="space-y-1.5 rounded-xl border border-primary/40 bg-primary-softer p-3"
		>
			<div className="flex flex-col gap-2 sm:flex-row">
				<Input
					value={ name }
					onChange={ ( event ) => setName( event.target.value ) }
					onKeyDown={ ( event ) =>
						event.key === 'Escape' && setOpen( false )
					}
					maxLength={ 60 }
					autoFocus
					placeholder={ __(
						'Section name, e.g. Kitchen',
						'radius-hotel-booking'
					) }
					aria-label={ __( 'Section name', 'radius-hotel-booking' ) }
					aria-invalid={ error ? true : undefined }
					className={ cn(
						'min-w-0 flex-1 bg-white',
						error && '!border-destructive'
					) }
				/>
				<div className="flex gap-2">
					<Button type="submit" disabled={ add.isPending }>
						<FolderPlus className="h-4 w-4" aria-hidden="true" />
						{ __( 'Add section', 'radius-hotel-booking' ) }
					</Button>
					<Button
						type="button"
						variant="ghost"
						onClick={ () => {
							setOpen( false );
							setError( '' );
						} }
					>
						{ __( 'Cancel', 'radius-hotel-booking' ) }
					</Button>
				</div>
			</div>
			{ error ? (
				<p role="alert" className="m-0 text-xs text-destructive">
					{ error }
				</p>
			) : null }
		</form>
	);
}

/**
 * The "add an amenity" box: section, name, icon.
 *
 * @param {Object}   props          Props.
 * @param {Object[]} props.sections Sections in order.
 * @return {JSX.Element} Form.
 */
function AddAmenity( { sections } ) {
	const add = useAddAmenity();
	const [ name, setName ] = useState( '' );
	const [ icon, setIcon ] = useState( '' );
	const [ section, setSection ] = useState( '' );
	const [ error, setError ] = useState( '' );
	// The chosen section, else the first.
	const sectionId =
		sections.find( ( s ) => String( s.id ) === section )?.id ??
		sections[ 0 ]?.id;

	const submit = ( event ) => {
		event.preventDefault();
		if ( ! name.trim() ) {
			setError(
				__( 'Give the amenity a name.', 'radius-hotel-booking' )
			);
			return;
		}
		add.mutate(
			{ name: name.trim(), icon, section_id: sectionId },
			{
				onSuccess: () => {
					setName( '' );
					setIcon( '' );
					setError( '' );
					toast.success(
						__( 'Amenity added.', 'radius-hotel-booking' )
					);
				},
				onError: ( e ) => setError( fieldError( e, 'name' ) ),
			}
		);
	};

	return (
		<form onSubmit={ submit } noValidate className="space-y-1.5">
			<label htmlFor="rtbp-new-amenity" className="sr-only">
				{ __( 'New amenity name', 'radius-hotel-booking' ) }
			</label>
			<div className="flex flex-col gap-2 lg:flex-row">
				<Select
					value={ String( sectionId ?? '' ) }
					onValueChange={ setSection }
				>
					<SelectTrigger
						className="h-[45px] w-full shrink-0 bg-white lg:w-52"
						aria-label={ __( 'Section', 'radius-hotel-booking' ) }
					>
						<SelectValue
							placeholder={ __(
								'Section',
								'radius-hotel-booking'
							) }
						/>
					</SelectTrigger>
					<SelectContent className="rtbp-root">
						{ sections.map( ( s ) => (
							<SelectItem key={ s.id } value={ String( s.id ) }>
								{ s.name }
							</SelectItem>
						) ) }
					</SelectContent>
				</Select>
				{ /* One field: the name, with its icon picker on the right edge. */ }
				<div
					className={ cn(
						'flex min-h-[45px] min-w-0 flex-1 items-stretch overflow-hidden rounded-md border bg-white transition-colors focus-within:border-primary focus-within:ring-2 focus-within:ring-ring',
						error ? 'border-destructive' : 'border-border'
					) }
				>
					<span className="flex items-center pl-3 text-muted-foreground">
						<Plus className="h-4 w-4" aria-hidden="true" />
					</span>
					<input
						id="rtbp-new-amenity"
						value={ name }
						onChange={ ( event ) => setName( event.target.value ) }
						maxLength={ 60 }
						placeholder={ __(
							'New amenity, e.g. Balcony',
							'radius-hotel-booking'
						) }
						aria-invalid={ error ? true : undefined }
						aria-describedby={
							error
								? 'rtbp-new-amenity-error'
								: 'rtbp-new-amenity-help'
						}
						className="min-w-0 flex-1 border-0 bg-transparent px-2.5 text-base text-foreground shadow-none outline-none placeholder:text-muted-foreground focus:border-0 focus:shadow-none focus:outline-none focus:ring-0 md:text-sm"
					/>
					<IconField
						value={ icon }
						onChange={ setIcon }
						addon
						label={ name.trim() }
					/>
				</div>
				<Button
					type="submit"
					className="h-[45px] shrink-0"
					disabled={ add.isPending }
				>
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
			) : (
				<p
					id="rtbp-new-amenity-help"
					className="m-0 text-xs text-muted-foreground"
				>
					{ __(
						'Choose its section; the icon is optional. Guests see both on room pages.',
						'radius-hotel-booking'
					) }
				</p>
			) }
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
 * @param {Object[]} props.sections    Every section (for "Move to section").
 * @param {Function} props.onMoveTo    Called with a section id.
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
	sections = [],
	onMoveTo,
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

	const saveIcon = ( icon ) =>
		rename.mutate(
			{ id: amenity.id, name: amenity.name, icon },
			{
				onSuccess: () =>
					toast.success(
						icon
							? __( 'Icon saved.', 'radius-hotel-booking' )
							: __( 'Icon removed.', 'radius-hotel-booking' )
					),
				onError: ( e ) => toastError( e ),
			}
		);

	return (
		<li
			className={ cn(
				'm-0 flex flex-wrap items-center gap-2 bg-white px-3 py-2.5',
				dragging && 'opacity-50',
				over && 'bg-primary-soft'
			) }
			draggable={ canManage && ! editing }
			onDragStart={ ( event ) => {
				event.stopPropagation();
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData(
					'text/plain',
					String( amenity.id )
				);
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
				event.stopPropagation();
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

			{ canManage ? (
				<IconField
					value={ amenity.icon || '' }
					onChange={ saveIcon }
					compact
					label={ amenity.name }
					disabled={ rename.isPending }
				/>
			) : (
				<span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
					<LucideIcon
						name={ amenity.icon || 'circle-check' }
						className="h-5 w-5"
					/>
				</span>
			) }

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
					{ sections.length > 1 ? (
						<DropdownMenu>
							<DropdownMenuTrigger asChild>
								<Button
									type="button"
									variant="ghost"
									size="icon"
									className="h-9 w-9 sm:h-10 sm:w-10"
									disabled={ busy }
									aria-label={ sprintf(
										/* translators: %s: amenity name. */
										__(
											'Move %s to another section',
											'radius-hotel-booking'
										),
										amenity.name
									) }
								>
									<FolderInput
										className="h-4 w-4"
										aria-hidden="true"
									/>
								</Button>
							</DropdownMenuTrigger>
							<DropdownMenuContent
								align="end"
								className="rtbp-root w-56"
							>
								<DropdownMenuLabel>
									{ __(
										'Move to section',
										'radius-hotel-booking'
									) }
								</DropdownMenuLabel>
								<DropdownMenuSeparator />
								{ sections.map( ( section ) => (
									<DropdownMenuItem
										key={ section.id }
										disabled={
											section.id === amenity.section_id
										}
										onSelect={ () =>
											onMoveTo( section.id )
										}
									>
										{ section.id === amenity.section_id ? (
											<Check
												className="h-4 w-4"
												aria-hidden="true"
											/>
										) : (
											<span
												className="h-4 w-4"
												aria-hidden="true"
											/>
										) }
										{ section.name }
									</DropdownMenuItem>
								) ) }
							</DropdownMenuContent>
						</DropdownMenu>
					) : null }
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
