/**
 * The author-stamped notes of a record (design system §5): guests (M09),
 * bookings (M03) and employees (M12). Add a note with its kind (note,
 * caution, warning), edit or remove one; each shows who wrote it and when,
 * and whether it was edited. What the viewer may do comes from the server
 * (`can_add`, `can_edit`, `can_remove`), which checks again.
 *
 *   <NotesPanel type="guest" id={ guest.id } />
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { MessageSquareText, Pencil, Trash2 } from 'lucide-react';

import { useNoteMutations, useNotes } from '@/api/notes';
import ConfirmDialog from '@/components/common/ConfirmDialog';
import EmptyState from '@/components/common/EmptyState';
import SegmentedControl from '@/components/common/SegmentedControl';
import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { toast, toastError } from '@/lib/toast';

const MAX = 5000;

/**
 * The note kinds, for the picker.
 *
 * @return {Array<{value: string, label: string}>} Kinds.
 */
const kinds = () => [
	{ value: 'general', label: __( 'Note', 'radius-hotel-booking' ) },
	{ value: 'caution', label: __( 'Caution', 'radius-hotel-booking' ) },
	{ value: 'warning', label: __( 'Warning', 'radius-hotel-booking' ) },
];

/**
 * The write form: a kind and the text.
 *
 * @param {Object}   props           Props.
 * @param {Object}   props.initial   `{ type, body }`.
 * @param {string}   props.submitLabel Button text.
 * @param {boolean}  props.busy      Saving.
 * @param {Function} props.onSubmit  Called with `{ type, body }`; may reject with field errors.
 * @param {Function} props.onCancel  Optional cancel.
 * @param {string}   props.idPrefix  Unique id prefix.
 * @return {JSX.Element} Form.
 */
function NoteForm( {
	initial,
	submitLabel,
	busy,
	onSubmit,
	onCancel,
	idPrefix,
} ) {
	const [ type, setType ] = useState( initial.type );
	const [ body, setBody ] = useState( initial.body );
	const [ error, setError ] = useState( '' );

	const submit = async ( event ) => {
		event.preventDefault();
		if ( ! body.trim() ) {
			setError( __( 'Write the note first.', 'radius-hotel-booking' ) );
			return;
		}
		try {
			await onSubmit( { type, body } );
			if ( ! onCancel ) {
				setBody( '' );
				setType( 'general' );
			}
		} catch ( err ) {
			const message =
				err?.errors?.body?.first_message ||
				err?.errors?.type?.first_message;
			if ( message ) {
				setError( message );
			} else {
				toastError( err );
			}
		}
	};

	return (
		<form onSubmit={ submit } noValidate className="space-y-2">
			<label htmlFor={ `${ idPrefix }-body` } className="sr-only">
				{ __( 'Note', 'radius-hotel-booking' ) }
			</label>
			<Textarea
				id={ `${ idPrefix }-body` }
				value={ body }
				maxLength={ MAX }
				rows={ 3 }
				placeholder={ __(
					'Write a note for the team…',
					'radius-hotel-booking'
				) }
				onChange={ ( e ) => {
					setBody( e.target.value );
					setError( '' );
				} }
				aria-invalid={ error ? true : undefined }
			/>
			{ error ? (
				<p role="alert" className="m-0 text-sm text-destructive">
					{ error }
				</p>
			) : null }
			<div className="flex flex-wrap items-center justify-between gap-2">
				<SegmentedControl
					label={ __( 'Kind of note', 'radius-hotel-booking' ) }
					options={ kinds() }
					value={ type }
					onChange={ setType }
				/>
				<div className="flex gap-2">
					{ onCancel ? (
						<Button
							type="button"
							variant="ghost"
							size="sm"
							onClick={ onCancel }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
					) : null }
					<Button type="submit" size="sm" disabled={ busy }>
						{ submitLabel }
					</Button>
				</div>
			</div>
		</form>
	);
}

/**
 * @param {Object} props      Props.
 * @param {string} props.type Notable type (`guest`, `booking`, `employee`).
 * @param {number} props.id   Record id.
 * @return {JSX.Element} Panel body.
 */
export default function NotesPanel( { type, id } ) {
	const notes = useNotes( type, id );
	const { add, edit, remove } = useNoteMutations( type, id );
	const [ editing, setEditing ] = useState( 0 );
	const [ removing, setRemoving ] = useState( null );

	if ( notes.isPending ) {
		return (
			<div className="space-y-2">
				<Skeleton className="h-20 w-full" />
				<Skeleton className="h-16 w-full" />
			</div>
		);
	}
	if ( notes.error ) {
		return (
			<div role="alert" className="space-y-2">
				<p className="m-0 text-sm text-destructive">
					{ notes.error.message }
				</p>
				<Button
					variant="outline"
					size="sm"
					onClick={ () => notes.refetch() }
				>
					{ __( 'Try again', 'radius-hotel-booking' ) }
				</Button>
			</div>
		);
	}

	const { notes: list, canAdd } = notes.data;

	return (
		<div className="space-y-4">
			{ canAdd ? (
				<NoteForm
					idPrefix={ `rtbp-note-new-${ type }-${ id }` }
					initial={ { type: 'general', body: '' } }
					submitLabel={ __( 'Add note', 'radius-hotel-booking' ) }
					busy={ add.isPending }
					onSubmit={ ( fields ) =>
						add
							.mutateAsync( fields )
							.then( () =>
								toast.success(
									__( 'Note added.', 'radius-hotel-booking' )
								)
							)
					}
				/>
			) : null }

			{ list.length ? (
				<ul className="m-0 list-none divide-y divide-border p-0">
					{ list.map( ( note ) => (
						<li key={ note.id } className="m-0 space-y-2 py-3">
							{ editing === note.id ? (
								<NoteForm
									idPrefix={ `rtbp-note-${ note.id }` }
									initial={ {
										type: note.type,
										body: note.body,
									} }
									submitLabel={ __(
										'Save',
										'radius-hotel-booking'
									) }
									busy={ edit.isPending }
									onCancel={ () => setEditing( 0 ) }
									onSubmit={ ( fields ) =>
										edit
											.mutateAsync( {
												noteId: note.id,
												...fields,
											} )
											.then( () => {
												setEditing( 0 );
												toast.success(
													__(
														'Note saved.',
														'radius-hotel-booking'
													)
												);
											} )
									}
								/>
							) : (
								<>
									<div className="flex flex-wrap items-start justify-between gap-2">
										<div className="flex flex-wrap items-center gap-2">
											{ note.type !== 'general' ? (
												<StatusBadge
													domain="note"
													value={ note.type }
												/>
											) : null }
											<span className="text-xs text-muted-foreground">
												{ note.edited
													? sprintf(
															/* translators: 1: author, 2: date and time. */
															__(
																'%1$s · %2$s · edited',
																'radius-hotel-booking'
															),
															note.author.name ||
																__(
																	'Someone',
																	'radius-hotel-booking'
																),
															formatDateTime(
																note.updated_at
															)
													  )
													: sprintf(
															/* translators: 1: author, 2: date and time. */
															__(
																'%1$s · %2$s',
																'radius-hotel-booking'
															),
															note.author.name ||
																__(
																	'Someone',
																	'radius-hotel-booking'
																),
															formatDateTime(
																note.created_at
															)
													  ) }
											</span>
										</div>
										<div className="flex gap-1">
											{ note.can_edit ? (
												<Button
													variant="ghost"
													size="icon"
													className="h-8 w-8"
													onClick={ () =>
														setEditing( note.id )
													}
													aria-label={ __(
														'Edit note',
														'radius-hotel-booking'
													) }
												>
													<Pencil
														className="h-4 w-4"
														aria-hidden="true"
													/>
												</Button>
											) : null }
											{ note.can_remove ? (
												<Button
													variant="ghost"
													size="icon"
													className="h-8 w-8 text-destructive"
													onClick={ () =>
														setRemoving( note )
													}
													aria-label={ __(
														'Remove note',
														'radius-hotel-booking'
													) }
												>
													<Trash2
														className="h-4 w-4"
														aria-hidden="true"
													/>
												</Button>
											) : null }
										</div>
									</div>
									<p className="m-0 whitespace-pre-wrap break-words text-sm text-heading">
										{ note.body }
									</p>
								</>
							) }
						</li>
					) ) }
				</ul>
			) : (
				<EmptyState
					icon={ MessageSquareText }
					title={ __( 'No notes yet', 'radius-hotel-booking' ) }
					description={
						canAdd
							? __(
									'Notes help the team remember what matters: preferences, incidents, promises.',
									'radius-hotel-booking'
							  )
							: ''
					}
					className="border-0 py-8"
				/>
			) }

			<ConfirmDialog
				open={ Boolean( removing ) }
				onOpenChange={ ( open ) => ! open && setRemoving( null ) }
				title={ __( 'Remove this note?', 'radius-hotel-booking' ) }
				description={ __(
					'The note disappears for everyone. The activity log keeps a copy.',
					'radius-hotel-booking'
				) }
				confirmLabel={ __( 'Remove note', 'radius-hotel-booking' ) }
				destructive
				onConfirm={ () =>
					remove
						.mutateAsync( removing.id )
						.then( () =>
							toast.success(
								__( 'Note removed.', 'radius-hotel-booking' )
							)
						)
				}
			/>
		</div>
	);
}
