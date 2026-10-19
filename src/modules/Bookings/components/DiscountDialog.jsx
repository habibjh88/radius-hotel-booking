/**
 * Give, change or remove the booking's discount (whole booking): an amount
 * or a percentage of the rooms, and why. A preview shows the new total and
 * balance before saving. A percentage follows the rooms when rooms are
 * added or cancelled; an amount stays. The server checks everything again
 * (never below what was already paid); its field errors are shown on the
 * fields.
 */
import { useEffect, useId, useState } from 'react';
import { __ } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import Money from '@/components/common/Money';
import SegmentedControl from '@/components/common/SegmentedControl';
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

/**
 * What a discount comes to on the rooms' sum (the server's rule).
 *
 * @param {string} type     amount|percent.
 * @param {number} value    Value.
 * @param {number} subtotal Rooms' sum.
 * @return {number} Discount.
 */
const discountOf = ( type, value, subtotal ) => {
	const v = Math.max( 0, Number( value ) || 0 );
	return 'percent' === type
		? Math.round( ( subtotal * Math.min( 100, v ) ) / 100 )
		: Math.min( subtotal, v );
};

/**
 * @param {Object}   props          Props.
 * @param {boolean}  props.open     Open.
 * @param {Object}   props.money    The booking's money (with `discount`).
 * @param {Function} props.onClose  Close.
 * @param {Function} props.onSubmit Called with `{ type, value, reason }`; returns a promise (rejects with the API error).
 * @return {JSX.Element} Dialog.
 */
export default function DiscountDialog( { open, money, onClose, onSubmit } ) {
	const id = useId();
	const current = money?.discount || {};
	const has = Boolean( current.type );
	const [ values, setValues ] = useState( {} );
	const [ errors, setErrors ] = useState( {} );
	const [ busy, setBusy ] = useState( false );

	// Each opening starts from the discount as it is.
	useEffect( () => {
		if ( ! open ) {
			return;
		}
		setValues( {
			type: current.type || 'amount',
			value: current.type ? String( current.value ) : '',
			reason: current.reason || '',
		} );
		setErrors( {} );
	}, [ open ] ); // Only on opening: a refetch must not reset what is typed.

	const set = ( key ) => ( value ) =>
		setValues( ( prev ) => ( { ...prev, [ key ]: value } ) );

	const send = async ( body ) => {
		setBusy( true );
		setErrors( {} );
		try {
			await onSubmit( body );
			onClose();
		} catch ( err ) {
			const mapped = {};
			Object.entries( err?.errors || {} ).forEach( ( [ key, value ] ) => {
				mapped[ key ] = value?.first_message || String( value );
			} );
			setErrors( mapped );
		} finally {
			setBusy( false );
		}
	};

	const subtotal = Number( money?.subtotal ) || 0;
	const tax = Number( money?.tax_total ) || 0;
	const paid = Number( money?.paid_total ) || 0;
	const discount = discountOf( values.type, values.value, subtotal );
	const total = Math.max( 0, subtotal - discount + tax );
	const percent = 'percent' === values.type;

	return (
		<Dialog open={ open } onOpenChange={ ( next ) => ! next && onClose() }>
			<DialogContent className="rtbp-root max-w-lg">
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						send( values );
					} }
					className="space-y-4"
				>
					<DialogHeader>
						<DialogTitle>
							{ has
								? __(
										'Change the discount',
										'radius-hotel-booking'
								  )
								: __(
										'Give a discount',
										'radius-hotel-booking'
								  ) }
						</DialogTitle>
						<DialogDescription>
							{ __(
								'For the whole booking. A percentage follows the rooms if rooms are added or cancelled.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</DialogHeader>

					<SegmentedControl
						label={ __( 'Discount type', 'radius-hotel-booking' ) }
						value={ values.type }
						onChange={ set( 'type' ) }
						options={ [
							{
								value: 'amount',
								label: __( 'Amount', 'radius-hotel-booking' ),
							},
							{
								value: 'percent',
								label: __(
									'Percentage',
									'radius-hotel-booking'
								),
							},
						] }
					/>

					<Field
						label={
							percent
								? __( 'Percentage (%)', 'radius-hotel-booking' )
								: __( 'Amount', 'radius-hotel-booking' )
						}
						error={ errors.value }
						required
					>
						<Input
							id={ `${ id }-value` }
							type="number"
							inputMode="decimal"
							min="0"
							max={ percent ? 100 : subtotal }
							step={ percent ? '0.5' : '1' }
							value={ values.value ?? '' }
							onChange={ ( event ) =>
								set( 'value' )( event.target.value )
							}
							autoFocus
						/>
					</Field>

					<Field
						label={ __( 'Reason', 'radius-hotel-booking' ) }
						description={ __(
							'Shown in the activity log, e.g. Regular guest or Room issue.',
							'radius-hotel-booking'
						) }
						error={ errors.reason }
						required
					>
						<Input
							id={ `${ id }-reason` }
							maxLength={ 191 }
							value={ values.reason ?? '' }
							onChange={ ( event ) =>
								set( 'reason' )( event.target.value )
							}
						/>
					</Field>

					<dl className="m-0 space-y-1.5 rounded-lg border border-border bg-muted/50 p-3 text-sm">
						<div className="flex justify-between gap-3">
							<dt className="text-muted-foreground">
								{ __( 'Rooms', 'radius-hotel-booking' ) }
							</dt>
							<dd className="m-0 text-heading">
								<Money value={ subtotal } />
							</dd>
						</div>
						<div className="flex justify-between gap-3">
							<dt className="text-muted-foreground">
								{ __( 'Discount', 'radius-hotel-booking' ) }
							</dt>
							<dd className="m-0 text-success">
								<Money value={ -discount } />
							</dd>
						</div>
						<div className="flex justify-between gap-3 border-t border-border pt-1.5 font-semibold">
							<dt className="text-heading">
								{ __( 'New total', 'radius-hotel-booking' ) }
							</dt>
							<dd className="m-0 text-heading">
								<Money value={ total } />
							</dd>
						</div>
						<div className="flex justify-between gap-3">
							<dt className="text-muted-foreground">
								{ __( 'Balance due', 'radius-hotel-booking' ) }
							</dt>
							<dd className="m-0 text-heading">
								<Money value={ total - paid } />
							</dd>
						</div>
					</dl>

					<DialogFooter className="gap-2 sm:justify-between">
						{ has ? (
							<Button
								type="button"
								variant="ghost"
								className="text-destructive hover:text-destructive"
								disabled={ busy }
								onClick={ () => send( { type: 'none' } ) }
							>
								{ __(
									'Remove discount',
									'radius-hotel-booking'
								) }
							</Button>
						) : (
							<span />
						) }
						<div className="flex gap-2">
							<Button
								type="button"
								variant="ghost"
								onClick={ onClose }
							>
								{ __( 'Cancel', 'radius-hotel-booking' ) }
							</Button>
							<Button type="submit" disabled={ busy }>
								{ __(
									'Save discount',
									'radius-hotel-booking'
								) }
							</Button>
						</div>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
