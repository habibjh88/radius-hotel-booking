/**
 * Add a guest (M09). A guest already on file with the same phone or e-mail
 * is not added twice: the dialog shows who it is and offers that record.
 * With no e-mail, the server keeps a flagged internal address (9.12).
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

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
import { toast, toastError } from '@/lib/toast';
import { useCreateGuest } from '../api';

const FIELDS = [
	'first_name',
	'last_name',
	'phone',
	'email',
	'id_type',
	'id_number',
];

/**
 * @param {Object}   props           Props.
 * @param {Object}   props.idTypes   Document types, key => label.
 * @param {Function} props.onClose   Close.
 * @param {Function} props.onExisting Called with a guest already on file `{ id, reference, name }`.
 * @param {Function} props.onCreated Called with the new guest.
 * @return {JSX.Element} Dialog.
 */
export default function AddGuestDialog( {
	idTypes,
	onClose,
	onExisting,
	onCreated,
} ) {
	const [ values, setValues ] = useState( {
		first_name: '',
		last_name: '',
		phone: '',
		email: '',
		id_type: '',
		id_number: '',
	} );
	const [ errors, setErrors ] = useState( {} );
	const [ existing, setExisting ] = useState( [] );
	const create = useCreateGuest();

	const set = ( field, value ) => {
		setValues( ( prev ) => ( { ...prev, [ field ]: value } ) );
		setErrors( {} );
		setExisting( [] );
	};

	const submit = async ( event ) => {
		event.preventDefault();
		try {
			const guest = await create.mutateAsync( values );
			toast.success( __( 'Guest added.', 'radius-hotel-booking' ) );
			onCreated( guest );
		} catch ( err ) {
			if ( err?.code === 'guest_exists' ) {
				setExisting( err.data?.guests || [] );
				return;
			}
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

	const field = ( name, label, props = {} ) => (
		<div className="space-y-1.5">
			<Label
				htmlFor={ `rtbp-guest-${ name }` }
				className="text-sm font-semibold text-heading"
			>
				{ label }
			</Label>
			<Input
				id={ `rtbp-guest-${ name }` }
				value={ values[ name ] }
				onChange={ ( e ) => set( name, e.target.value ) }
				aria-invalid={ errors[ name ] ? true : undefined }
				{ ...props }
			/>
			{ errors[ name ] ? (
				<p role="alert" className="m-0 text-sm text-destructive">
					{ errors[ name ] }
				</p>
			) : null }
		</div>
	);

	return (
		<Dialog open onOpenChange={ ( next ) => ! next && onClose() }>
			<DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
				<form onSubmit={ submit } noValidate className="space-y-5">
					<DialogHeader>
						<DialogTitle>
							{ __( 'Add a guest', 'radius-hotel-booking' ) }
						</DialogTitle>
						<DialogDescription>
							{ __(
								'A phone number or an e-mail address is needed; the phone is best, since it finds the guest next time.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</DialogHeader>

					<div className="grid gap-4 sm:grid-cols-2">
						{ field(
							'first_name',
							__( 'First name', 'radius-hotel-booking' ),
							{ autoComplete: 'off', maxLength: 100 }
						) }
						{ field(
							'last_name',
							__( 'Last name', 'radius-hotel-booking' ),
							{ autoComplete: 'off', maxLength: 100 }
						) }
						{ field(
							'phone',
							__( 'Phone', 'radius-hotel-booking' ),
							{
								type: 'tel',
								inputMode: 'tel',
								placeholder: '07 07 12 34 56',
								autoComplete: 'off',
							}
						) }
						{ field(
							'email',
							__( 'E-mail (optional)', 'radius-hotel-booking' ),
							{ type: 'email', autoComplete: 'off' }
						) }
						<div className="space-y-1.5">
							<Label
								htmlFor="rtbp-guest-id_type"
								className="text-sm font-semibold text-heading"
							>
								{ __(
									'Identity document',
									'radius-hotel-booking'
								) }
							</Label>
							<Select
								value={ values.id_type || undefined }
								onValueChange={ ( value ) =>
									set( 'id_type', value )
								}
							>
								<SelectTrigger
									id="rtbp-guest-id_type"
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
							{ errors.id_type ? (
								<p
									role="alert"
									className="m-0 text-sm text-destructive"
								>
									{ errors.id_type }
								</p>
							) : null }
						</div>
						{ field(
							'id_number',
							__( 'Document number', 'radius-hotel-booking' ),
							{ autoComplete: 'off', maxLength: 60 }
						) }
					</div>

					{ existing.length ? (
						<div
							role="alert"
							className="space-y-2 rounded-lg border border-warning bg-warning-soft p-3"
						>
							<p className="m-0 text-sm font-semibold text-heading">
								{ __(
									'This guest is already on file',
									'radius-hotel-booking'
								) }
							</p>
							<ul className="m-0 list-none space-y-2 p-0">
								{ existing.map( ( guest ) => (
									<li
										key={ guest.id }
										className="m-0 flex flex-wrap items-center justify-between gap-2 text-sm"
									>
										<span>
											<span className="font-semibold text-heading">
												{ guest.name ||
													guest.reference }
											</span>{ ' ' }
											<span className="text-muted-foreground">
												{ sprintf(
													/* translators: 1: guest reference, 2: "same phone" or "same e-mail". */
													__(
														'%1$s · %2$s',
														'radius-hotel-booking'
													),
													guest.reference,
													guest.match === 'email'
														? __(
																'same e-mail',
																'radius-hotel-booking'
														  )
														: __(
																'same phone',
																'radius-hotel-booking'
														  )
												) }
											</span>
										</span>
										<Button
											type="button"
											size="sm"
											variant="outline"
											onClick={ () =>
												onExisting( guest )
											}
										>
											{ __(
												'Use this guest',
												'radius-hotel-booking'
											) }
										</Button>
									</li>
								) ) }
							</ul>
						</div>
					) : null }

					<DialogFooter className="gap-2">
						<Button
							type="button"
							variant="ghost"
							onClick={ onClose }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						<Button type="submit" disabled={ create.isPending }>
							{ __( 'Add guest', 'radius-hotel-booking' ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
