/**
 * Record a payment or a refund (5.9, 5.11): the amount starts at what is
 * due (or, for a refund, what is owed back), the method comes from Settings
 * → Payments, the reference is the Wave / Orange Money transaction number
 * or anything the desk wants to find it by, and the time the money came in
 * defaults to now. The server checks everything again; its field errors are
 * shown on the fields.
 */
import { useEffect, useId, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
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
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney, siteNowTime, siteToday } from '@/lib/format';

/**
 * The amount a new entry starts at.
 *
 * @param {string} type    payment|refund.
 * @param {Object} summary `{ balance_due, paid_total }`.
 * @return {string} Amount.
 */
const startAmount = ( type, summary ) => {
	const due = Number( summary?.balance_due ) || 0;
	const paid = Number( summary?.paid_total ) || 0;
	if ( 'refund' === type ) {
		// Owed back after a room was taken off, else everything paid.
		return String( due < 0 ? Math.min( -due, paid ) : paid );
	}
	return String( Math.max( 0, due ) );
};

/**
 * Where the booking stands, in one line.
 *
 * @param {Object} summary `{ balance_due }`.
 * @return {string} Text.
 */
const describe = ( summary ) => {
	const due = Number( summary?.balance_due ) || 0;
	if ( due > 0 ) {
		return sprintf(
			/* translators: %s: amount due. */
			__( 'Balance due: %s', 'radius-hotel-booking' ),
			formatMoney( due )
		);
	}
	if ( due < 0 ) {
		return sprintf(
			/* translators: %s: amount to give back. */
			__( 'A refund of %s is owed.', 'radius-hotel-booking' ),
			formatMoney( -due )
		);
	}
	return __( 'Nothing is due on this booking.', 'radius-hotel-booking' );
};

/**
 * @param {Object}   props          Props.
 * @param {boolean}  props.open     Open.
 * @param {Object}   props.ledger   `{ summary, methods }` from the server.
 * @param {Function} props.onClose  Close.
 * @param {Function} props.onSubmit Called with the body; returns a promise (rejects with the API error).
 * @return {JSX.Element} Dialog.
 */
export default function RecordPaymentDialog( {
	open,
	ledger,
	onClose,
	onSubmit,
} ) {
	const id = useId();
	const methods = ledger?.methods || [];
	const summary = ledger?.summary || {};
	const canRefund = Number( summary.paid_total ) > 0;
	const [ values, setValues ] = useState( {} );
	const [ errors, setErrors ] = useState( {} );
	const [ busy, setBusy ] = useState( false );

	// Each opening starts fresh: a payment of the balance, the first method, now.
	useEffect( () => {
		if ( ! open ) {
			return;
		}
		const type =
			Number( summary.balance_due ) > 0 || ! canRefund
				? 'payment'
				: 'refund';
		setValues( {
			type,
			amount: startAmount( type, summary ),
			method: methods[ 0 ]?.key || '',
			reference: '',
			received_at: `${ siteToday() }T${ siteNowTime() }`,
			note: '',
		} );
		setErrors( {} );
	}, [ open ] ); // Only on opening: a refetch must not reset what is typed.

	const set = ( key ) => ( value ) =>
		setValues( ( prev ) => ( { ...prev, [ key ]: value } ) );

	const submit = async ( event ) => {
		event.preventDefault();
		setBusy( true );
		setErrors( {} );
		try {
			await onSubmit( {
				...values,
				received_at: String( values.received_at || '' ).replace(
					'T',
					' '
				),
			} );
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

	const refund = 'refund' === values.type;

	return (
		<Dialog open={ open } onOpenChange={ ( next ) => ! next && onClose() }>
			<DialogContent className="rtbp-root max-w-lg">
				<form onSubmit={ submit } className="space-y-4">
					<DialogHeader>
						<DialogTitle>
							{ refund
								? __(
										'Record a refund',
										'radius-hotel-booking'
								  )
								: __(
										'Record a payment',
										'radius-hotel-booking'
								  ) }
						</DialogTitle>
						<DialogDescription>
							{ describe( summary ) }
						</DialogDescription>
					</DialogHeader>

					{ canRefund ? (
						<SegmentedControl
							label={ __( 'Type', 'radius-hotel-booking' ) }
							value={ values.type }
							onChange={ ( type ) =>
								setValues( ( prev ) => ( {
									...prev,
									type,
									amount: startAmount( type, summary ),
								} ) )
							}
							options={ [
								{
									value: 'payment',
									label: __(
										'Payment',
										'radius-hotel-booking'
									),
								},
								{
									value: 'refund',
									label: __(
										'Refund',
										'radius-hotel-booking'
									),
								},
							] }
						/>
					) : null }

					<div className="grid gap-4 sm:grid-cols-2">
						<Field
							label={ __( 'Amount', 'radius-hotel-booking' ) }
							error={ errors.amount }
							required
						>
							<Input
								type="number"
								inputMode="decimal"
								min="0"
								step="any"
								value={ values.amount ?? '' }
								onChange={ ( e ) =>
									set( 'amount' )( e.target.value )
								}
							/>
						</Field>
						<Field
							label={ __( 'Method', 'radius-hotel-booking' ) }
							error={ errors.method }
							required
						>
							<Select
								value={ values.method || undefined }
								onValueChange={ set( 'method' ) }
							>
								<SelectTrigger id={ `${ id }-method` }>
									<SelectValue
										placeholder={ __(
											'Choose',
											'radius-hotel-booking'
										) }
									/>
								</SelectTrigger>
								<SelectContent>
									{ methods.map( ( method ) => (
										<SelectItem
											key={ method.key }
											value={ method.key }
										>
											{ method.label }
										</SelectItem>
									) ) }
								</SelectContent>
							</Select>
						</Field>
						<Field
							label={ __( 'Reference', 'radius-hotel-booking' ) }
							description={ __(
								'The transaction number, or anything to find it by.',
								'radius-hotel-booking'
							) }
							error={ errors.reference }
						>
							<Input
								value={ values.reference ?? '' }
								maxLength={ 100 }
								onChange={ ( e ) =>
									set( 'reference' )( e.target.value )
								}
							/>
						</Field>
						<Field
							label={ __( 'Received', 'radius-hotel-booking' ) }
							error={ errors.received_at }
						>
							<Input
								type="datetime-local"
								value={ values.received_at ?? '' }
								onChange={ ( e ) =>
									set( 'received_at' )( e.target.value )
								}
							/>
						</Field>
					</div>
					<Field
						label={ __( 'Note', 'radius-hotel-booking' ) }
						error={ errors.note }
					>
						<Textarea
							rows={ 2 }
							className="!min-h-[64px]"
							maxLength={ 1000 }
							value={ values.note ?? '' }
							onChange={ ( e ) =>
								set( 'note' )( e.target.value )
							}
						/>
					</Field>
					{ ! methods.length ? (
						<p className="m-0 text-sm text-destructive">
							{ __(
								'No payment method is switched on. Add one in Settings → Payments.',
								'radius-hotel-booking'
							) }
						</p>
					) : null }

					<DialogFooter>
						<Button
							type="button"
							variant="ghost"
							onClick={ onClose }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						<Button
							type="submit"
							disabled={ busy || ! methods.length }
						>
							{ refund
								? __( 'Record refund', 'radius-hotel-booking' )
								: __(
										'Record payment',
										'radius-hotel-booking'
								  ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
