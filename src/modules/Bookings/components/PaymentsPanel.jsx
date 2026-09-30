/**
 * The booking's money and payments (M05): the summary (3.2), how it was paid
 * (3.3), *On hold* (the desk is waiting for money the guest promised), the
 * payment history with voided rows struck through and their reason (5.13),
 * and the actions — record a payment or refund (5.9–5.11), void a row
 * recorded by mistake. The status itself comes from the ledger; there is no
 * button that marks a booking paid (ADR-010).
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { FileText, Printer, Plus, Undo2 } from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import Money from '@/components/common/Money';
import Panel from '@/components/common/Panel';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { useAccess } from '@/lib/access';
import { formatDateTime } from '@/lib/format';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { usePaymentAction, usePayments } from '../api';
import RecordPaymentDialog from './RecordPaymentDialog';

/**
 * Total, what was paid and what is left.
 *
 * @param {Object} props       Props.
 * @param {Object} props.money The booking's money.
 * @return {JSX.Element} Summary.
 */
export function MoneySummary( { money } ) {
	const row = ( label, value, className = '' ) => (
		<div
			className={ cn(
				'flex items-baseline justify-between gap-3',
				className
			) }
		>
			<dt className="text-sm text-muted-foreground">{ label }</dt>
			<dd className="m-0 text-sm text-heading">
				<Money value={ value } />
			</dd>
		</div>
	);
	const due = Number( money.balance_due );
	return (
		<dl className="m-0 space-y-2">
			{ row( __( 'Rooms', 'radius-hotel-booking' ), money.subtotal ) }
			{ Number( money.discount_total )
				? row(
						__( 'Discount', 'radius-hotel-booking' ),
						-Math.abs( money.discount_total )
				  )
				: null }
			{ Number( money.tax_total )
				? row( __( 'Tax', 'radius-hotel-booking' ), money.tax_total )
				: null }
			{ row(
				__( 'Total', 'radius-hotel-booking' ),
				money.total,
				'border-t border-border pt-2 font-semibold [&_dd]:font-semibold [&_dt]:text-heading'
			) }
			{ row( __( 'Paid', 'radius-hotel-booking' ), money.paid_total ) }
			<div className="flex items-baseline justify-between gap-3 border-t border-border pt-2">
				<dt className="text-sm font-semibold text-heading">
					{ due < 0
						? __( 'Refund owed', 'radius-hotel-booking' )
						: __( 'Balance due', 'radius-hotel-booking' ) }
				</dt>
				<dd
					className={ cn(
						'm-0 text-base font-semibold',
						0 !== due ? 'text-warning' : 'text-success'
					) }
				>
					<Money value={ Math.abs( due ) } />
				</dd>
			</div>
		</dl>
	);
}

/**
 * The booking's invoice (5.5, 5.6): number, version, and print / download
 * in every format the site offers (free: the print view; Pro adds PDF).
 * Earlier versions stay printable.
 *
 * @param {Object} props         Props.
 * @param {Object} props.invoice `{ number, version, status, urls, older }`.
 * @return {JSX.Element|null} Row.
 */
function InvoiceRow( { invoice } ) {
	if ( ! invoice?.urls?.html ) {
		return null;
	}
	const formats = Object.keys( invoice.urls ).filter( ( f ) => 'html' !== f );
	return (
		<div className="space-y-2 rounded-lg border border-border p-3">
			<div className="flex flex-wrap items-center justify-between gap-2">
				<p className="m-0 flex flex-wrap items-center gap-2 text-sm text-heading">
					<FileText
						className="h-4 w-4 text-muted-foreground"
						aria-hidden="true"
					/>
					<span className="font-semibold">
						{ invoice.number ||
							__( 'Invoice', 'radius-hotel-booking' ) }
					</span>
					{ 'cancelled' === invoice.status ? (
						<span className="text-xs font-semibold text-destructive">
							{ __( 'Cancelled', 'radius-hotel-booking' ) }
						</span>
					) : null }
					{ 'revised' === invoice.status ? (
						<span className="text-xs text-muted-foreground">
							{ sprintf(
								/* translators: %d: invoice version. */
								__(
									'Revised, version %d',
									'radius-hotel-booking'
								),
								invoice.version
							) }
						</span>
					) : null }
				</p>
				<div className="flex flex-wrap gap-2">
					<Button asChild variant="outline" size="sm">
						<a
							href={ invoice.urls.html }
							target="_blank"
							rel="noopener noreferrer"
						>
							<Printer className="h-4 w-4" aria-hidden="true" />
							{ __( 'Print', 'radius-hotel-booking' ) }
						</a>
					</Button>
					{ formats.map( ( format ) => (
						<Button
							key={ format }
							asChild
							variant="outline"
							size="sm"
						>
							<a
								href={ `${ invoice.urls[ format ] }&download=1` }
								rel="noopener noreferrer"
							>
								{ sprintf(
									/* translators: %s: file format, e.g. PDF. */
									__( 'Download %s', 'radius-hotel-booking' ),
									format.toUpperCase()
								) }
							</a>
						</Button>
					) ) }
				</div>
			</div>
			{ invoice.older?.length ? (
				<p className="m-0 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
					{ __( 'Earlier versions:', 'radius-hotel-booking' ) }
					{ invoice.older.map( ( old ) => (
						<a
							key={ old.version }
							href={ old.url }
							target="_blank"
							rel="noopener noreferrer"
							className="text-primary"
						>
							{ sprintf(
								/* translators: %d: invoice version. */
								__( 'version %d', 'radius-hotel-booking' ),
								old.version
							) }
						</a>
					) ) }
				</p>
			) : null }
		</div>
	);
}

/**
 * @param {Object} props         Props.
 * @param {Object} props.booking The booking (record shape).
 * @return {JSX.Element} Panel.
 */
export default function PaymentsPanel( { booking } ) {
	const ledger = usePayments( booking.id );
	const act = usePaymentAction( booking.id );
	const canRecord = 'locked' !== useAccess( 'payments.record' );
	const canVoid = 'locked' !== useAccess( 'payments.void' );
	const canHold = 'locked' !== useAccess( 'payments.change_status' );
	const [ recording, setRecording ] = useState( false );
	const [ voiding, setVoiding ] = useState( null );

	const run = ( kind, extra ) =>
		act
			.mutateAsync( { kind, ...extra } )
			.then( ( { message } ) => toast.success( message ) )
			.catch( ( err ) => {
				toastError( err );
				throw err;
			} );

	const rows = ( ledger.data?.payments || [] ).filter(
		( row ) => 'void' !== row.type
	);
	// 3.3: how the guest paid (the methods of the rows still counting).
	const methods = [
		...new Set(
			rows
				.filter( ( row ) => ! row.voided && 'payment' === row.type )
				.map( ( row ) => row.method_label )
		),
	];
	const due = Number( booking.money.balance_due );
	const paid = Number( booking.money.paid_total );
	const showRecord = canRecord && ( due > 0 || paid > 0 );

	return (
		<Panel
			title={ __( 'Money', 'radius-hotel-booking' ) }
			actions={
				showRecord ? (
					<Button
						type="button"
						size="sm"
						onClick={ () => setRecording( true ) }
						disabled={ ! ledger.data }
					>
						<Plus className="h-4 w-4" aria-hidden="true" />
						{ due > 0
							? __( 'Record payment', 'radius-hotel-booking' )
							: __( 'Record refund', 'radius-hotel-booking' ) }
					</Button>
				) : null
			}
		>
			<div className="space-y-4">
				<MoneySummary money={ booking.money } />

				<InvoiceRow invoice={ booking.invoice } />

				{ methods.length ? (
					<p className="m-0 text-sm text-muted-foreground">
						{ sprintf(
							/* translators: %s: payment method names, e.g. "Wave, Cash at the desk". */
							__( 'Paid by %s', 'radius-hotel-booking' ),
							methods.join( ', ' )
						) }
					</p>
				) : null }

				{ canHold && 'paid' !== booking.payment_status ? (
					<label className="flex items-start justify-between gap-4 rounded-lg border border-border p-3">
						<span className="min-w-0">
							<span className="block text-sm font-semibold text-heading">
								{ __( 'On hold', 'radius-hotel-booking' ) }
							</span>
							<span className="block text-[13px] leading-5 text-muted-foreground">
								{ __(
									'Waiting for money the guest promised. The first payment takes it off hold.',
									'radius-hotel-booking'
								) }
							</span>
						</span>
						<Switch
							checked={ Boolean( booking.on_hold ) }
							disabled={ act.isPending }
							onCheckedChange={ ( on ) =>
								run( 'hold', { body: { on_hold: on } } ).catch(
									() => {}
								)
							}
						/>
					</label>
				) : null }

				<div className="space-y-2">
					<h3 className="m-0 text-sm font-semibold text-heading">
						{ __( 'Payments', 'radius-hotel-booking' ) }
					</h3>
					{ ledger.isPending ? (
						<Skeleton className="h-14 w-full" />
					) : null }
					{ ledger.error ? (
						<div className="flex flex-wrap items-center gap-2 text-sm">
							<span className="text-destructive">
								{ ledger.error.message }
							</span>
							<Button
								type="button"
								variant="outline"
								size="sm"
								onClick={ () => ledger.refetch() }
							>
								{ __( 'Try again', 'radius-hotel-booking' ) }
							</Button>
						</div>
					) : null }
					{ ledger.data && ! rows.length ? (
						<p className="m-0 text-sm text-muted-foreground">
							{ __(
								'No payment recorded yet.',
								'radius-hotel-booking'
							) }
						</p>
					) : null }
					{ rows.length ? (
						<ul className="m-0 list-none divide-y divide-border p-0">
							{ rows.map( ( row ) => (
								<li
									key={ row.id }
									className="flex flex-wrap items-start justify-between gap-2 py-2.5"
								>
									<div
										className={ cn(
											'min-w-0 space-y-0.5',
											row.voided && 'opacity-70'
										) }
									>
										<p
											className={ cn(
												'm-0 text-sm font-semibold text-heading',
												row.voided && 'line-through'
											) }
										>
											<Money value={ row.amount } />
											{ ' · ' }
											{ 'refund' === row.type
												? sprintf(
														/* translators: %s: payment method. */
														__(
															'Refund by %s',
															'radius-hotel-booking'
														),
														row.method_label
												  )
												: row.method_label }
											{ row.reference
												? ` · ${ row.reference }`
												: '' }
										</p>
										<p className="m-0 text-xs text-muted-foreground">
											{ row.recorded_by
												? sprintf(
														/* translators: 1: date and time, 2: staff name. */
														__(
															'%1$s · by %2$s',
															'radius-hotel-booking'
														),
														formatDateTime(
															row.received_at
														),
														row.recorded_by
												  )
												: formatDateTime(
														row.received_at
												  ) }
										</p>
										{ row.receipt_urls?.html ? (
											<a
												href={ row.receipt_urls.html }
												target="_blank"
												rel="noopener noreferrer"
												className="text-xs text-primary"
											>
												{ sprintf(
													/* translators: %s: receipt number. */
													__(
														'Receipt %s',
														'radius-hotel-booking'
													),
													row.receipt_no
												) }
											</a>
										) : null }
										{ row.note ? (
											<p className="m-0 text-xs text-muted-foreground">
												{ row.note }
											</p>
										) : null }
										{ row.voided ? (
											<p className="m-0 text-xs font-medium text-destructive">
												{ sprintf(
													/* translators: %s: why the payment was voided. */
													__(
														'Voided: %s',
														'radius-hotel-booking'
													),
													row.void_reason
												) }
											</p>
										) : null }
									</div>
									{ canVoid && ! row.voided ? (
										<Button
											type="button"
											variant="ghost"
											size="sm"
											className="text-destructive"
											onClick={ () => setVoiding( row ) }
										>
											<Undo2
												className="h-4 w-4"
												aria-hidden="true"
											/>
											{ __(
												'Void',
												'radius-hotel-booking'
											) }
										</Button>
									) : null }
								</li>
							) ) }
						</ul>
					) : null }
				</div>
			</div>

			<RecordPaymentDialog
				open={ recording }
				// The booking's money is current after any room change; the ledger adds the methods.
				ledger={
					ledger.data && {
						...ledger.data,
						summary: booking.money,
					}
				}
				onClose={ () => setRecording( false ) }
				onSubmit={ ( body ) => run( 'record', { body } ) }
			/>
			<ConfirmDialog
				open={ Boolean( voiding ) }
				onOpenChange={ ( open ) => ! open && setVoiding( null ) }
				title={ __( 'Void this payment?', 'radius-hotel-booking' ) }
				description={ __(
					'For a payment recorded by mistake. It stays in the history, struck through, and no longer counts.',
					'radius-hotel-booking'
				) }
				confirmLabel={ __( 'Void payment', 'radius-hotel-booking' ) }
				destructive
				requireReason
				onConfirm={ ( reason ) =>
					run( 'void', {
						paymentId: voiding.id,
						body: { reason },
					} ).then( () => setVoiding( null ) )
				}
			/>
		</Panel>
	);
}
