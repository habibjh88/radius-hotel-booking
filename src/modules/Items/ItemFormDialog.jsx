import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';

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
import { Textarea } from '@/components/ui/textarea';

import { createItem, updateItem } from './api';

const EMPTY = { title: '', description: '', status: 'draft', price: '' };

/**
 * Create/edit dialog for an item.
 *
 * Field-level errors come straight from the server: the REST validator returns
 * `errors: { field: [message] }`, which api/client.js attaches to the thrown
 * Error, so the UI never has to duplicate the validation rules.
 *
 * @param {Object}   props         Component props.
 * @param {boolean}  props.open    Whether the dialog is open.
 * @param {Function} props.onClose Called when the dialog should close.
 * @param {Object?}  props.item    Item being edited, or null to create.
 * @param {Function} props.onSaved Called after a successful save.
 * @return {JSX.Element} Dialog.
 */
export default function ItemFormDialog( { open, onClose, item, onSaved } ) {
	const [ values, setValues ] = useState( EMPTY );
	const [ errors, setErrors ] = useState( {} );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		setValues(
			item
				? {
						title: item.title ?? '',
						description: item.description ?? '',
						status: item.status ?? 'draft',
						price: item.price ?? '',
				  }
				: EMPTY
		);
		setErrors( {} );
	}, [ item, open ] );

	const setField = ( field ) => ( value ) =>
		setValues( ( current ) => ( { ...current, [ field ]: value } ) );

	const onSubmit = async ( event ) => {
		event.preventDefault();
		setSaving( true );
		setErrors( {} );

		try {
			if ( item ) {
				await updateItem( item.id, values );
			} else {
				await createItem( values );
			}

			onSaved();
			onClose();
		} catch ( error ) {
			setErrors( error.errors || { _: [ error.message ] } );
		} finally {
			setSaving( false );
		}
	};

	const fieldError = ( field ) =>
		errors[ field ] ? (
			<p className="mt-1 text-xs text-destructive">
				{ Array.isArray( errors[ field ] )
					? errors[ field ][ 0 ]
					: errors[ field ] }
			</p>
		) : null;

	return (
		<Dialog open={ open } onOpenChange={ ( next ) => ! next && onClose() }>
			<DialogContent className="rtbp-root sm:max-w-lg">
				<form onSubmit={ onSubmit }>
					<DialogHeader>
						<DialogTitle>
							{ item
								? __( 'Edit item', 'radius-hotel-booking' )
								: __( 'New item', 'radius-hotel-booking' ) }
						</DialogTitle>
						<DialogDescription>
							{ __(
								'Items are the example resource shipped with the hotel booking.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</DialogHeader>

					<div className="space-y-4 py-4">
						<div>
							<Label htmlFor="rtbp-item-title">
								{ __( 'Title', 'radius-hotel-booking' ) }
							</Label>
							<Input
								id="rtbp-item-title"
								value={ values.title }
								onChange={ ( event ) =>
									setField( 'title' )( event.target.value )
								}
							/>
							{ fieldError( 'title' ) }
						</div>

						<div>
							<Label htmlFor="rtbp-item-description">
								{ __( 'Description', 'radius-hotel-booking' ) }
							</Label>
							<Textarea
								id="rtbp-item-description"
								rows={ 3 }
								value={ values.description }
								onChange={ ( event ) =>
									setField( 'description' )(
										event.target.value
									)
								}
							/>
							{ fieldError( 'description' ) }
						</div>

						<div className="grid grid-cols-2 gap-4">
							<div>
								<Label htmlFor="rtbp-item-price">
									{ __( 'Price', 'radius-hotel-booking' ) }
								</Label>
								<Input
									id="rtbp-item-price"
									type="number"
									step="0.01"
									min="0"
									value={ values.price }
									onChange={ ( event ) =>
										setField( 'price' )(
											event.target.value
										)
									}
								/>
								{ fieldError( 'price' ) }
							</div>

							<div>
								<Label htmlFor="rtbp-item-status">
									{ __( 'Status', 'radius-hotel-booking' ) }
								</Label>
								<Select
									value={ values.status }
									onValueChange={ setField( 'status' ) }
								>
									<SelectTrigger id="rtbp-item-status">
										<SelectValue />
									</SelectTrigger>
									<SelectContent className="rtbp-root">
										<SelectItem value="draft">
											{ __(
												'Draft',
												'radius-hotel-booking'
											) }
										</SelectItem>
										<SelectItem value="published">
											{ __(
												'Published',
												'radius-hotel-booking'
											) }
										</SelectItem>
										<SelectItem value="archived">
											{ __(
												'Archived',
												'radius-hotel-booking'
											) }
										</SelectItem>
									</SelectContent>
								</Select>
								{ fieldError( 'status' ) }
							</div>
						</div>

						{ errors._ ? (
							<p className="text-sm text-destructive">
								{ errors._[ 0 ] }
							</p>
						) : null }
					</div>

					<DialogFooter>
						<Button
							type="button"
							variant="outline"
							onClick={ onClose }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						<Button type="submit" disabled={ saving }>
							{ saving
								? __( 'Saving…', 'radius-hotel-booking' )
								: __( 'Save', 'radius-hotel-booking' ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
