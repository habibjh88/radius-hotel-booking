/**
 * Edit a guest's details (9.5, 9.7): name, phone, e-mail and identity
 * document. Only the fields that changed are sent; the server logs them with
 * before/after. The document number is never pre-filled (the full number is
 * a logged read): leave it empty to keep the one on file, type a new one to
 * replace it, or remove the document.
 */
import { useState } from 'react';
import { __ } from '@wordpress/i18n';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { toast, toastError } from '@/lib/toast';
import { useUpdateGuest } from '../api';

const FIELDS = [
	'first_name',
	'last_name',
	'phone',
	'email',
	'id_type',
	'id_number',
];

/**
 * @param {Object}   props         Props.
 * @param {Object}   props.guest   Guest (detail shape).
 * @param {Object}   props.idTypes Document types, key => label.
 * @param {Function} props.onClose Close.
 * @return {JSX.Element} Dialog.
 */
export default function EditGuestDialog( { guest, idTypes, onClose } ) {
	const initial = {
		first_name: guest.first_name,
		last_name: guest.last_name,
		phone: guest.phone,
		email: guest.email || '',
		id_type: guest.id_type,
	};
	const [ values, setValues ] = useState( initial );
	const [ newNumber, setNewNumber ] = useState( '' );
	const [ removeId, setRemoveId ] = useState( false );
	const [ errors, setErrors ] = useState( {} );
	const update = useUpdateGuest( guest.id );

	const set = ( field, value ) => {
		setValues( ( prev ) => ( { ...prev, [ field ]: value } ) );
		setErrors( {} );
	};

	const submit = async ( event ) => {
		event.preventDefault();
		const changes = {};
		[ 'first_name', 'last_name', 'phone', 'email' ].forEach( ( field ) => {
			if (
				values[ field ].trim() !== ( initial[ field ] || '' ).trim()
			) {
				changes[ field ] = values[ field ];
			}
		} );
		if ( removeId ) {
			if ( guest.has_id || guest.id_type ) {
				changes.id_type = '';
				changes.id_number = '';
			}
		} else {
			if ( values.id_type !== initial.id_type ) {
				changes.id_type = values.id_type;
			}
			if ( newNumber.trim() !== '' ) {
				changes.id_number = newNumber;
				changes.id_type = values.id_type;
			}
		}
		if ( ! Object.keys( changes ).length ) {
			onClose();
			return;
		}
		try {
			await update.mutateAsync( changes );
			toast.success( __( 'Guest saved.', 'radius-hotel-booking' ) );
			onClose();
		} catch ( err ) {
			const mapped = {};
			FIELDS.forEach( ( field ) => {
				const message = err?.errors?.[ field ]?.first_message;
				if ( message ) {
					mapped[ field ] = message;
				}
			} );
			if ( Object.keys( mapped ).length ) {
				setErrors( mapped );
			} else {
				toastError( err );
			}
		}
	};

	const errorText = ( name ) =>
		errors[ name ] ? (
			<p role="alert" className="m-0 text-sm text-destructive">
				{ errors[ name ] }
			</p>
		) : null;

	const field = ( name, label, props = {} ) => (
		<div className="space-y-1.5">
			<Label
				htmlFor={ `rtbp-edit-guest-${ name }` }
				className="text-sm font-semibold text-heading"
			>
				{ label }
			</Label>
			<Input
				id={ `rtbp-edit-guest-${ name }` }
				value={ values[ name ] }
				onChange={ ( e ) => set( name, e.target.value ) }
				aria-invalid={ errors[ name ] ? true : undefined }
				autoComplete="off"
				{ ...props }
			/>
			{ errorText( name ) }
		</div>
	);

	return (
		<Dialog open onOpenChange={ ( next ) => ! next && onClose() }>
			<DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
				<form onSubmit={ submit } noValidate className="space-y-5">
					<DialogHeader>
						<DialogTitle>
							{ __( 'Edit guest', 'radius-hotel-booking' ) }
						</DialogTitle>
						<DialogDescription>
							{ __(
								'Every change is recorded in the activity log.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</DialogHeader>

					<div className="grid gap-4 sm:grid-cols-2">
						{ field(
							'first_name',
							__( 'First name', 'radius-hotel-booking' ),
							{ maxLength: 100 }
						) }
						{ field(
							'last_name',
							__( 'Last name', 'radius-hotel-booking' ),
							{ maxLength: 100 }
						) }
						{ field(
							'phone',
							__( 'Phone', 'radius-hotel-booking' ),
							{
								type: 'tel',
								inputMode: 'tel',
							}
						) }
						{ field(
							'email',
							__( 'E-mail', 'radius-hotel-booking' ),
							{
								type: 'email',
								placeholder: guest.email_is_placeholder
									? __( 'None given', 'radius-hotel-booking' )
									: undefined,
							}
						) }
					</div>

					<fieldset className="m-0 space-y-3 rounded-lg border border-border p-3">
						<legend className="px-1 text-sm font-semibold text-heading">
							{ __(
								'Identity document',
								'radius-hotel-booking'
							) }
						</legend>
						<div className="grid gap-4 sm:grid-cols-2">
							<div className="space-y-1.5">
								<Label
									htmlFor="rtbp-edit-guest-id_type"
									className="text-sm font-semibold text-heading"
								>
									{ __( 'Type', 'radius-hotel-booking' ) }
								</Label>
								<Select
									value={ values.id_type || undefined }
									onValueChange={ ( value ) =>
										set( 'id_type', value )
									}
									disabled={ removeId }
								>
									<SelectTrigger
										id="rtbp-edit-guest-id_type"
										aria-invalid={
											errors.id_type ? true : undefined
										}
									>
										<SelectValue
											placeholder={ __(
												'None',
												'radius-hotel-booking'
											) }
										/>
									</SelectTrigger>
									<SelectContent>
										{ Object.entries( idTypes ).map(
											( [ key, label ] ) => (
												<SelectItem
													key={ key }
													value={ key }
												>
													{ label }
												</SelectItem>
											)
										) }
									</SelectContent>
								</Select>
								{ errorText( 'id_type' ) }
							</div>
							<div className="space-y-1.5">
								<Label
									htmlFor="rtbp-edit-guest-id_number"
									className="text-sm font-semibold text-heading"
								>
									{ guest.has_id
										? __(
												'New number',
												'radius-hotel-booking'
										  )
										: __(
												'Number',
												'radius-hotel-booking'
										  ) }
								</Label>
								<Input
									id="rtbp-edit-guest-id_number"
									value={ newNumber }
									onChange={ ( e ) => {
										setNewNumber( e.target.value );
										setErrors( {} );
									} }
									placeholder={
										guest.has_id
											? guest.id_number_masked
											: undefined
									}
									maxLength={ 60 }
									autoComplete="off"
									disabled={ removeId }
									aria-describedby="rtbp-edit-guest-id-help"
									aria-invalid={
										errors.id_number ? true : undefined
									}
								/>
								{ errorText( 'id_number' ) }
							</div>
						</div>
						{ guest.has_id ? (
							<>
								<p
									id="rtbp-edit-guest-id-help"
									className="m-0 text-xs text-muted-foreground"
								>
									{ __(
										'Leave the number empty to keep the one on file.',
										'radius-hotel-booking'
									) }
								</p>
								<label
									htmlFor="rtbp-edit-guest-remove-id"
									className="flex items-center gap-2 text-sm text-heading"
								>
									<Checkbox
										id="rtbp-edit-guest-remove-id"
										checked={ removeId }
										onCheckedChange={ ( on ) => {
											setRemoveId( on === true );
											setErrors( {} );
										} }
									/>
									{ __(
										'Remove the document on file',
										'radius-hotel-booking'
									) }
								</label>
							</>
						) : null }
					</fieldset>

					<DialogFooter className="gap-2">
						<Button
							type="button"
							variant="ghost"
							onClick={ onClose }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						<Button type="submit" disabled={ update.isPending }>
							{ __( 'Save', 'radius-hotel-booking' ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
