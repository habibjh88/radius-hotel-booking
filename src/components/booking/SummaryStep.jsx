/**
 * Summary and confirm (2.10, 2.11): the rooms with their prices, the total,
 * the payment state at creation (Unpaid / Paid now), a note, and Confirm.
 * When the server says a price moved, the new price is shown next to the
 * old one and must be accepted before the booking is made.
 */
import { __, sprintf } from '@wordpress/i18n';
import { AlertTriangle, Ban, CheckCircle2 } from 'lucide-react';

import { Field } from '@/components/common/Form';
import Money from '@/components/common/Money';
import SegmentedControl from '@/components/common/SegmentedControl';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useAccess } from '@/lib/access';
import { formatMoney } from '@/lib/format';

/**
 * @param {Object}      props             Props.
 * @param {number}      props.total       Sum of the lines.
 * @param {string}      props.payment     `unpaid` | `paid`.
 * @param {string}      props.note        Note.
 * @param {Function}    props.onChange    Called with `{ payment?, note? }`.
 * @param {string}      props.blocked     Why confirming is not possible yet ('' = ready).
 * @param {boolean}     props.banned      The guest is banned.
 * @param {Object|null} props.priceChange `{ index, room, from, to }` from the server.
 * @param {boolean}     props.submitting  Sending.
 * @param {Function}    props.onConfirm   Send the booking (again after a price change).
 * @return {JSX.Element} Step.
 */
export default function SummaryStep( {
	total,
	payment,
	note,
	onChange,
	blocked,
	banned,
	priceChange,
	submitting,
	onConfirm,
} ) {
	// "Paid now" records money taken: `payments.record` (legacy mark-as-paid).
	const canMarkPaid = 'locked' !== useAccess( 'payments.record' );
	return (
		<div className="space-y-4">
			<div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-border pb-3">
				<span className="text-sm font-semibold text-heading">
					{ __( 'Total', 'radius-hotel-booking' ) }
				</span>
				<span className="text-xl font-semibold text-heading">
					<Money value={ total } />
				</span>
			</div>

			<div className="space-y-1.5">
				<p className="m-0 text-sm font-semibold text-heading">
					{ __( 'Payment', 'radius-hotel-booking' ) }
				</p>
				<SegmentedControl
					label={ __( 'Payment', 'radius-hotel-booking' ) }
					value={ payment }
					onChange={ ( value ) => onChange( { payment: value } ) }
					options={ [
						{
							value: 'unpaid',
							label: __( 'Unpaid', 'radius-hotel-booking' ),
						},
						...( canMarkPaid
							? [
									{
										value: 'paid',
										label: __(
											'Paid now',
											'radius-hotel-booking'
										),
									},
							  ]
							: [] ),
					] }
				/>
			</div>

			<Field
				label={ __( 'Note', 'radius-hotel-booking' ) }
				description={ __(
					'Special requests or anything the team should know. Optional.',
					'radius-hotel-booking'
				) }
			>
				<Textarea
					rows={ 2 }
					maxLength={ 2000 }
					value={ note }
					onChange={ ( e ) => onChange( { note: e.target.value } ) }
				/>
			</Field>

			{ priceChange ? (
				<div
					className="space-y-3 rounded-lg border border-warning bg-warning-soft p-3 text-sm"
					role="alert"
				>
					<p className="m-0 flex items-start gap-2 font-semibold text-heading">
						<AlertTriangle
							className="mt-0.5 h-4 w-4 shrink-0 text-warning"
							aria-hidden="true"
						/>
						{ sprintf(
							/* translators: 1: room number, 2: old price, 3: new price. */
							__(
								'The price of room %1$s changed from %2$s to %3$s.',
								'radius-hotel-booking'
							),
							priceChange.room,
							formatMoney( priceChange.from ),
							formatMoney( priceChange.to )
						) }
					</p>
					<Button
						type="button"
						disabled={ submitting }
						onClick={ () => onConfirm() }
					>
						{ __(
							'Accept the new price and confirm',
							'radius-hotel-booking'
						) }
					</Button>
				</div>
			) : null }

			{ banned ? (
				<p className="m-0 flex items-center gap-2 text-sm text-destructive">
					<Ban className="h-4 w-4 shrink-0" aria-hidden="true" />
					{ __(
						'The guest is banned: this booking cannot be confirmed.',
						'radius-hotel-booking'
					) }
				</p>
			) : null }
			{ blocked && ! banned ? (
				<p className="m-0 text-sm text-muted-foreground">{ blocked }</p>
			) : null }

			{ ! priceChange ? (
				<Button
					type="button"
					size="lg"
					className="w-full sm:w-auto"
					disabled={ Boolean( blocked ) || banned || submitting }
					onClick={ () => onConfirm() }
				>
					<CheckCircle2 className="h-4 w-4" aria-hidden="true" />
					{ submitting
						? __( 'Confirming…', 'radius-hotel-booking' )
						: __( 'Confirm booking', 'radius-hotel-booking' ) }
				</Button>
			) : null }
		</div>
	);
}
