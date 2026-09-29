/**
 * Edit one calendar cell: a rate on a date (price for that date, 8.2; open
 * or closed, 8.3) or the whole room type on a date (open or closed, 8.4).
 * Only the fields that changed are sent; the server validates again.
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
import { Switch } from '@/components/ui/switch';
import { currencyInfo, formatDate, formatMoney } from '@/lib/format';
import { toast, toastError } from '@/lib/toast';
import { useSaveCalendar } from '../api';

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.target   `{ kind: 'type'|'rate', typeId, typeName, rate?, day, cell? }`.
 * @param {Function} props.onClose  Close.
 * @return {JSX.Element} Dialog.
 */
export default function CellEditor( { target, onClose } ) {
	const save = useSaveCalendar();
	const isRate = target.kind === 'rate';
	const cell = target.cell;
	const initialOpen = isRate ? ! cell.closed : ! target.day.closed;
	const initialPrice =
		isRate && cell.override !== null ? String( cell.override ) : '';

	const [ open, setOpen ] = useState( initialOpen );
	const [ price, setPrice ] = useState( initialPrice );
	const [ error, setError ] = useState( '' );
	const { symbol } = currencyInfo();

	const regular =
		isRate && target.rate.sale_price !== null
			? Math.min( target.rate.price, target.rate.sale_price )
			: target.rate?.price;

	const submit = async ( event ) => {
		event.preventDefault();
		const change = {
			rate_plan_id: isRate ? target.rate.rate_plan_id : 0,
			date: target.day.date,
		};
		if ( open !== initialOpen ) {
			change.is_closed = ! open;
		}
		if ( isRate && price.trim() !== initialPrice ) {
			const value = price.trim();
			if (
				value !== '' &&
				( ! Number.isFinite( Number( value ) ) || Number( value ) < 0 )
			) {
				setError(
					__( 'Enter a price of 0 or more.', 'radius-hotel-booking' )
				);
				return;
			}
			change.price_override = value === '' ? null : value;
		}
		if ( Object.keys( change ).length === 2 ) {
			onClose();
			return;
		}
		try {
			await save.mutateAsync( {
				typeId: target.typeId,
				cells: [ change ],
			} );
			toast.success( __( 'Calendar saved.', 'radius-hotel-booking' ) );
			onClose();
		} catch ( err ) {
			const field =
				err?.errors?.[ 'cells.0.price_override' ] ||
				err?.errors?.[ 'cells.0.date' ] ||
				err?.errors?.[ 'cells.0' ];
			if ( field ) {
				setError( field.first_message );
			} else {
				toastError( err );
			}
		}
	};

	const date = formatDate( target.day.date );

	return (
		<Dialog open onOpenChange={ ( next ) => ! next && onClose() }>
			<DialogContent className="max-h-[90vh] max-w-md overflow-y-auto">
				<form onSubmit={ submit } noValidate className="space-y-5">
					<DialogHeader>
						<DialogTitle>
							{ isRate
								? sprintf(
										/* translators: 1: rate plan name, 2: date. */
										__(
											'%1$s on %2$s',
											'radius-hotel-booking'
										),
										target.rate.name,
										date
								  )
								: sprintf(
										/* translators: 1: room type name, 2: date. */
										__(
											'%1$s on %2$s',
											'radius-hotel-booking'
										),
										target.typeName,
										date
								  ) }
						</DialogTitle>
						<DialogDescription>
							{ isRate
								? __(
										'Change the price or close this rate for this date only.',
										'radius-hotel-booking'
								  )
								: __(
										'Closing the room type stops every rate from being sold on this date.',
										'radius-hotel-booking'
								  ) }
						</DialogDescription>
					</DialogHeader>

					<div className="flex items-center justify-between gap-4 rounded-lg border border-border px-3 py-3">
						<Label
							htmlFor="rtbp-cell-open"
							className="text-sm font-semibold text-heading"
						>
							{ __(
								'Open for bookings',
								'radius-hotel-booking'
							) }
						</Label>
						<Switch
							id="rtbp-cell-open"
							checked={ open }
							onCheckedChange={ setOpen }
						/>
					</div>
					{ isRate && target.day.closed ? (
						<p className="m-0 text-sm text-muted-foreground">
							{ __(
								'The whole room type is closed on this date, so this rate cannot be sold either.',
								'radius-hotel-booking'
							) }
						</p>
					) : null }

					{ isRate ? (
						<div className="space-y-1.5">
							<Label
								htmlFor="rtbp-cell-price"
								className="text-sm font-semibold text-heading"
							>
								{ __(
									'Price for this date',
									'radius-hotel-booking'
								) }
							</Label>
							<div className="relative w-44">
								<Input
									id="rtbp-cell-price"
									type="number"
									inputMode="decimal"
									min={ 0 }
									step="any"
									value={ price }
									onChange={ ( e ) => {
										setPrice( e.target.value );
										setError( '' );
									} }
									placeholder={ String( regular ?? '' ) }
									aria-invalid={ error ? true : undefined }
									aria-describedby="rtbp-cell-price-help"
									className="pr-12 tabular-nums"
								/>
								<span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">
									{ symbol }
								</span>
							</div>
							<p
								id="rtbp-cell-price-help"
								className="m-0 text-xs text-muted-foreground"
							>
								{ sprintf(
									/* translators: %s: the rate's usual price. */
									__(
										'Leave empty to use the usual price (%s). Seasonal and other rules still apply on top.',
										'radius-hotel-booking'
									),
									formatMoney( regular ?? 0 )
								) }
							</p>
							{ error ? (
								<p
									role="alert"
									className="m-0 text-sm text-destructive"
								>
									{ error }
								</p>
							) : null }
							{ price !== '' ? (
								<Button
									type="button"
									variant="link"
									className="h-auto p-0"
									onClick={ () => {
										setPrice( '' );
										setError( '' );
									} }
								>
									{ __(
										'Use the usual price',
										'radius-hotel-booking'
									) }
								</Button>
							) : null }
						</div>
					) : error ? (
						<p
							role="alert"
							className="m-0 text-sm text-destructive"
						>
							{ error }
						</p>
					) : null }

					<DialogFooter className="gap-2">
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
