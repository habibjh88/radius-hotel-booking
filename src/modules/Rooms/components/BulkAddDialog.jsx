/**
 * Bulk add rooms (feature 6.10): floor + prefix + from/to (+ zero-pad) →
 * preview → create. Numbers that already exist are shown and skipped.
 */
import { useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';

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
import { cn } from '@/lib/utils';
import { previewBulkRooms, useBulkAddRooms } from '../api';

/**
 * The server's field messages as `{ field: message }`.
 *
 * @param {Error} error Error from src/api/client.js.
 * @return {Object} Messages.
 */
const fieldErrors = ( error ) =>
	Object.fromEntries(
		Object.entries( error?.errors || {} ).map( ( [ key, detail ] ) => [
			key,
			detail?.first_message || '',
		] )
	);

/**
 * @param {Object}   props         Props.
 * @param {Object}   props.type    Room type.
 * @param {Object[]} props.floors  Floors.
 * @param {number}   props.floorId Preselected floor.
 * @param {Function} props.onClose Close.
 * @return {JSX.Element} Dialog.
 */
export default function BulkAddDialog( { type, floors, floorId, onClose } ) {
	const create = useBulkAddRooms( type.id );
	const [ values, setValues ] = useState( {
		floor_id: String( floorId || floors[ 0 ]?.id || '' ),
		prefix: '',
		from: '1',
		to: '10',
		pad: false,
	} );
	const [ plan, setPlan ] = useState( null );
	const [ errors, setErrors ] = useState( {} );
	const [ loading, setLoading ] = useState( false );

	const set = ( key ) => ( value ) => {
		setValues( ( prev ) => ( { ...prev, [ key ]: value } ) );
		// Any change makes the preview stale.
		setPlan( null );
	};

	const body = () => ( {
		floor_id: Number( values.floor_id ),
		prefix: values.prefix.trim(),
		from: Number( values.from ),
		to: Number( values.to ),
		// Zero-pad to the width of the last number ("1–12" → 01…12).
		pad: values.pad ? String( Number( values.to ) ).length : 0,
	} );

	const preview = async ( event ) => {
		event.preventDefault();
		setLoading( true );
		try {
			setPlan( await previewBulkRooms( type.id, body() ) );
			setErrors( {} );
		} catch ( error ) {
			const fields = fieldErrors( error );
			setErrors( fields );
			if ( ! Object.keys( fields ).length ) {
				toastError( error );
			}
		} finally {
			setLoading( false );
		}
	};

	const confirm = async () => {
		try {
			const response = await create.mutateAsync( body() );
			const skipped = response.data.skip;
			toast.success(
				skipped
					? sprintf(
							/* translators: 1: rooms added, 2: numbers skipped. */
							__(
								'%1$d rooms added, %2$d skipped.',
								'radius-hotel-booking'
							),
							response.data.created.length,
							skipped
					  )
					: response.message
			);
			onClose();
		} catch ( error ) {
			toastError( error );
		}
	};

	const field = ( key, label, input ) => (
		<div className="min-w-0 space-y-1.5">
			<Label
				htmlFor={ `rtbp-bulk-${ key }` }
				className="text-sm font-semibold text-heading"
			>
				{ label }
			</Label>
			{ input }
			{ errors[ key ] ? (
				<p role="alert" className="m-0 text-xs text-destructive">
					{ errors[ key ] }
				</p>
			) : null }
		</div>
	);

	return (
		<Dialog open onOpenChange={ ( open ) => ! open && onClose() }>
			<DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
				<form onSubmit={ preview } noValidate className="space-y-4">
					<DialogHeader>
						<DialogTitle>
							{ sprintf(
								/* translators: %s: room type name. */
								__( 'Add rooms to %s', 'radius-hotel-booking' ),
								type.name
							) }
						</DialogTitle>
						<DialogDescription>
							{ __(
								'A range of numbers on one floor, e.g. A1 to A12. Numbers that already exist are skipped.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</DialogHeader>

					{ field(
						'floor_id',
						__( 'Floor', 'radius-hotel-booking' ),
						<Select
							value={ values.floor_id }
							onValueChange={ set( 'floor_id' ) }
						>
							<SelectTrigger id="rtbp-bulk-floor_id">
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
					) }

					<div className="grid grid-cols-3 gap-3">
						{ field(
							'prefix',
							__( 'Prefix', 'radius-hotel-booking' ),
							<Input
								id="rtbp-bulk-prefix"
								value={ values.prefix }
								onChange={ ( event ) =>
									set( 'prefix' )( event.target.value )
								}
								maxLength={ 14 }
								placeholder={ __(
									'e.g. A',
									'radius-hotel-booking'
								) }
							/>
						) }
						{ field(
							'from',
							__( 'From', 'radius-hotel-booking' ),
							<Input
								id="rtbp-bulk-from"
								type="number"
								inputMode="numeric"
								min={ 0 }
								value={ values.from }
								onChange={ ( event ) =>
									set( 'from' )( event.target.value )
								}
							/>
						) }
						{ field(
							'to',
							__( 'To', 'radius-hotel-booking' ),
							<Input
								id="rtbp-bulk-to"
								type="number"
								inputMode="numeric"
								min={ 0 }
								value={ values.to }
								onChange={ ( event ) =>
									set( 'to' )( event.target.value )
								}
							/>
						) }
					</div>

					<label className="flex items-center gap-2 text-sm text-heading">
						<input
							type="checkbox"
							checked={ values.pad }
							onChange={ ( event ) =>
								set( 'pad' )( event.target.checked )
							}
							className="accent-primary"
						/>
						{ __(
							'Pad with zeros (A01 … A12)',
							'radius-hotel-booking'
						) }
					</label>
					{ errors.pad ? (
						<p
							role="alert"
							className="m-0 text-xs text-destructive"
						>
							{ errors.pad }
						</p>
					) : null }

					{ plan ? (
						<div className="space-y-2 rounded-lg border border-border bg-muted/40 p-3">
							<p className="m-0 text-sm font-semibold text-heading">
								{ sprintf(
									/* translators: %d: number of rooms. */
									_n(
										'%d room will be added',
										'%d rooms will be added',
										plan.create,
										'radius-hotel-booking'
									),
									plan.create
								) }
								{ plan.skip
									? ' · ' +
									  sprintf(
											/* translators: %d: number of skipped numbers. */
											_n(
												'%d already exists and is skipped',
												'%d already exist and are skipped',
												plan.skip,
												'radius-hotel-booking'
											),
											plan.skip
									  )
									: '' }
							</p>
							<ul className="m-0 flex max-h-48 list-none flex-wrap gap-1.5 overflow-y-auto p-0">
								{ plan.numbers.map( ( item ) => (
									<li
										key={ item.number }
										className={ cn(
											'm-0 rounded-md border px-2 py-0.5 text-xs font-semibold',
											item.status === 'new'
												? 'border-success bg-success-soft text-success'
												: 'border-border bg-card text-muted-foreground line-through'
										) }
										title={
											item.status === 'new'
												? undefined
												: sprintf(
														/* translators: 1: room number, 2: room type name. */
														__(
															'%1$s already belongs to %2$s',
															'radius-hotel-booking'
														),
														item.number,
														item.owner
												  )
										}
									>
										{ item.number }
									</li>
								) ) }
							</ul>
							{ plan.skip ? (
								<ul className="m-0 list-none space-y-0.5 p-0 text-xs text-muted-foreground">
									{ plan.numbers
										.filter(
											( item ) => item.status !== 'new'
										)
										.map( ( item ) => (
											<li
												key={ item.number }
												className="m-0"
											>
												{ sprintf(
													/* translators: 1: room number, 2: room type name. */
													__(
														'%1$s already belongs to %2$s',
														'radius-hotel-booking'
													),
													item.number,
													item.owner
												) }
											</li>
										) ) }
								</ul>
							) : null }
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
						{ plan ? (
							<Button
								type="button"
								onClick={ confirm }
								disabled={ ! plan.create || create.isPending }
							>
								{ sprintf(
									/* translators: %d: number of rooms. */
									_n(
										'Add %d room',
										'Add %d rooms',
										plan.create,
										'radius-hotel-booking'
									),
									plan.create
								) }
							</Button>
						) : (
							<Button type="submit" disabled={ loading }>
								{ __( 'Preview', 'radius-hotel-booking' ) }
							</Button>
						) }
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
