/**
 * Bulk update (8.5): a date range, the days of the week and the rates it
 * applies to, then one change — set a price, go back to the usual price,
 * open or close. A live preview counts what would really change before
 * anything is saved; the server validates again.
 */
import { useEffect, useMemo, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';

import DateRangePicker from '@/components/common/DateRangePicker';
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { currencyInfo, formatDateAs } from '@/lib/format';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { useBulkCalendar, useBulkPreview } from '../api';

const PRICE_ACTIONS = [ 'set_price', 'clear_price' ];

/**
 * Days of the week in the site's order (Settings → General → Week starts on).
 *
 * @return {Array<{value: number, short: string, long: string}>} Days, 0 = Sunday.
 */
function weekdays() {
	const params = window.radius_hotel_booking_param || {};
	const start = Number.isInteger( params.start_of_week )
		? params.start_of_week
		: 1;
	// 1–7 January 2023 ran Sunday to Saturday.
	return Array.from( { length: 7 }, ( _, i ) => {
		const value = ( start + i ) % 7;
		const date = `2023-01-0${ value + 1 }`;
		return {
			value,
			short: formatDateAs( date, 'D' ),
			long: formatDateAs( date, 'l' ),
		};
	} );
}

/**
 * The first field error of a failed request.
 *
 * @param {Object} err   API error.
 * @param {string} field Field.
 * @return {string} Message, or ''.
 */
const fieldError = ( err, field ) =>
	err?.errors?.[ field ]?.first_message || '';

/**
 * @param {Object}   props          Props.
 * @param {number}   props.typeId   Room type id.
 * @param {string}   props.typeName Room type name.
 * @param {Array}    props.rates    The grid's rates (`rate_plan_id`, `name`).
 * @param {string}   props.month    The month on screen, `YYYY-MM`.
 * @param {string}   props.today    The hotel's today, `YYYY-MM-DD`.
 * @param {Function} props.onClose  Close.
 * @return {JSX.Element} Dialog.
 */
export default function BulkEditor( {
	typeId,
	typeName,
	rates,
	month,
	today,
	onClose,
} ) {
	const days = useMemo( weekdays, [] );
	const [ year, number ] = month.split( '-' ).map( Number );
	// Day 0 of the next month is the last day of this one.
	const monthEnd = new Date( Date.UTC( year, number, 0 ) )
		.toISOString()
		.slice( 0, 10 );
	const from = `${ month }-01` < today ? today : `${ month }-01`;

	const [ action, setAction ] = useState( 'close' );
	const [ range, setRange ] = useState( {
		from,
		to: monthEnd < from ? from : monthEnd,
	} );
	const [ picked, setPicked ] = useState( [ 0, 1, 2, 3, 4, 5, 6 ] );
	const [ plans, setPlans ] = useState( [] );
	const [ price, setPrice ] = useState( '' );
	const [ errors, setErrors ] = useState( {} );
	const save = useBulkCalendar();
	const { symbol } = currencyInfo();

	const priceAction = PRICE_ACTIONS.includes( action );
	const priceValid =
		action !== 'set_price' ||
		( price.trim() !== '' &&
			Number.isFinite( Number( price ) ) &&
			Number( price ) >= 0 );

	const input = useMemo(
		() =>
			range.from &&
			range.to &&
			picked.length &&
			plans.length &&
			priceValid
				? {
						room_type_id: typeId,
						from: range.from,
						to: range.to,
						weekdays: [ ...picked ].sort(),
						rate_plan_ids: [ ...plans ].sort( ( a, b ) => a - b ),
						action,
						...( action === 'set_price'
							? { price: price.trim() }
							: {} ),
				  }
				: null,
		[ typeId, range, picked, plans, action, price, priceValid ]
	);

	// Count after the staff member stops typing, not on every keystroke.
	const [ debounced, setDebounced ] = useState( input );
	useEffect( () => {
		const timer = setTimeout( () => setDebounced( input ), 300 );
		return () => clearTimeout( timer );
	}, [ input ] );
	const preview = useBulkPreview( debounced );
	const settled = debounced === input;

	const changeAction = ( next ) => {
		setAction( next );
		setErrors( {} );
		// Prices belong to a rate, never to the whole room type.
		if ( PRICE_ACTIONS.includes( next ) ) {
			setPlans( ( list ) => list.filter( ( id ) => id !== 0 ) );
		}
	};

	const toggle = ( setter, value, on ) => {
		setter( ( list ) =>
			on ? [ ...list, value ] : list.filter( ( item ) => item !== value )
		);
		setErrors( {} );
	};

	const submit = async ( event ) => {
		event.preventDefault();
		if ( ! input ) {
			setErrors( {
				...( ! range.from
					? {
							from: __(
								'Choose the dates.',
								'radius-hotel-booking'
							),
					  }
					: {} ),
				...( ! picked.length
					? {
							weekdays: __(
								'Choose at least one day of the week.',
								'radius-hotel-booking'
							),
					  }
					: {} ),
				...( ! plans.length
					? {
							rate_plan_ids: __(
								'Choose at least one rate.',
								'radius-hotel-booking'
							),
					  }
					: {} ),
				...( ! priceValid
					? {
							price: __(
								'Enter a price of 0 or more.',
								'radius-hotel-booking'
							),
					  }
					: {} ),
			} );
			return;
		}
		try {
			const result = await save.mutateAsync( input );
			toast.success(
				sprintf(
					/* translators: %d: calendar entries changed. */
					_n(
						'%d change saved.',
						'%d changes saved.',
						result.changes,
						'radius-hotel-booking'
					),
					result.changes
				)
			);
			onClose();
		} catch ( err ) {
			const mapped = {};
			[
				'from',
				'to',
				'weekdays',
				'rate_plan_ids',
				'price',
				'action',
			].forEach( ( field ) => {
				if ( fieldError( err, field ) ) {
					mapped[ field ] = fieldError( err, field );
				}
			} );
			if ( Object.keys( mapped ).length ) {
				setErrors( mapped );
			} else {
				toastError( err );
			}
		}
	};

	// What the preview says: a count, a reason nothing would change, or the
	// server's objection to the choice.
	let summary = null;
	const previewError = preview.error;
	if ( ! input ) {
		summary = __(
			'Choose the dates, days and rates to see how many would change.',
			'radius-hotel-booking'
		);
	} else if ( previewError ) {
		summary = (
			<span className="text-destructive">
				{ Object.values( previewError.errors || {} )[ 0 ]
					?.first_message || previewError.message }
			</span>
		);
	} else if ( ! preview.data || ! settled || preview.isFetching ) {
		summary = __( 'Counting…', 'radius-hotel-booking' );
	} else if ( ! preview.data.dates ) {
		summary = __(
			'No date in the range falls on the chosen days.',
			'radius-hotel-booking'
		);
	} else if ( ! preview.data.changes ) {
		summary = __(
			'Nothing to change: these dates are already set this way.',
			'radius-hotel-booking'
		);
	} else {
		summary = (
			<>
				<span className="font-semibold text-heading">
					{ sprintf(
						/* translators: %d: calendar entries that will change. */
						_n(
							'%d change',
							'%d changes',
							preview.data.changes,
							'radius-hotel-booking'
						),
						preview.data.changes
					) }
				</span>{ ' ' }
				{ sprintf(
					/* translators: 1: matching dates, 2: date × rate entries they cover. */
					_n(
						'(%1$d date matches, %2$d entries in all).',
						'(%1$d dates match, %2$d entries in all).',
						preview.data.dates,
						'radius-hotel-booking'
					),
					preview.data.dates,
					preview.data.cells
				) }
			</>
		);
	}

	const canApply =
		Boolean( input ) &&
		settled &&
		! preview.isFetching &&
		! previewError &&
		preview.data?.changes > 0;

	const actions = [
		[ 'close', __( 'Close', 'radius-hotel-booking' ) ],
		[ 'open', __( 'Open', 'radius-hotel-booking' ) ],
		[ 'set_price', __( 'Set a price', 'radius-hotel-booking' ) ],
		[ 'clear_price', __( 'Use the usual price', 'radius-hotel-booking' ) ],
	];

	const errorText = ( field ) =>
		errors[ field ] ? (
			<p role="alert" className="m-0 text-sm text-destructive">
				{ errors[ field ] }
			</p>
		) : null;

	return (
		<Dialog open onOpenChange={ ( next ) => ! next && onClose() }>
			<DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
				<form onSubmit={ submit } noValidate className="space-y-5">
					<DialogHeader>
						<DialogTitle>
							{ sprintf(
								/* translators: %s: room type name. */
								__(
									'Update several dates of %s',
									'radius-hotel-booking'
								),
								typeName
							) }
						</DialogTitle>
						<DialogDescription>
							{ __(
								'Pick the dates, the days of the week and the rates, then one change to make to all of them.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</DialogHeader>

					<fieldset className="m-0 space-y-2 border-0 p-0">
						<legend className="mb-2 p-0 text-sm font-semibold text-heading">
							{ __( 'Change', 'radius-hotel-booking' ) }
						</legend>
						<RadioGroup
							value={ action }
							onValueChange={ changeAction }
							className="grid grid-cols-1 gap-2 sm:grid-cols-2"
						>
							{ actions.map( ( [ value, label ] ) => (
								<Label
									key={ value }
									htmlFor={ `rtbp-bulk-${ value }` }
									className={ cn(
										'flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium',
										action === value
											? 'border-primary bg-primary-softer text-heading'
											: 'border-border text-heading'
									) }
								>
									<RadioGroupItem
										id={ `rtbp-bulk-${ value }` }
										value={ value }
									/>
									{ label }
								</Label>
							) ) }
						</RadioGroup>
						{ errorText( 'action' ) }
					</fieldset>

					{ action === 'set_price' ? (
						<div className="space-y-1.5">
							<Label
								htmlFor="rtbp-bulk-price"
								className="text-sm font-semibold text-heading"
							>
								{ __( 'Price', 'radius-hotel-booking' ) }
							</Label>
							<div className="relative w-44">
								<Input
									id="rtbp-bulk-price"
									type="number"
									inputMode="decimal"
									min={ 0 }
									step="any"
									value={ price }
									onChange={ ( e ) => {
										setPrice( e.target.value );
										setErrors( {} );
									} }
									aria-invalid={
										errors.price ? true : undefined
									}
									aria-describedby="rtbp-bulk-price-help"
									className="pr-12 tabular-nums"
								/>
								<span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">
									{ symbol }
								</span>
							</div>
							<p
								id="rtbp-bulk-price-help"
								className="m-0 text-xs text-muted-foreground"
							>
								{ __(
									'Replaces the usual price on those dates. Seasonal and other rules still apply on top.',
									'radius-hotel-booking'
								) }
							</p>
							{ errorText( 'price' ) }
						</div>
					) : null }

					<div className="space-y-1.5">
						<p className="m-0 text-sm font-semibold text-heading">
							{ __( 'Dates', 'radius-hotel-booking' ) }
						</p>
						<DateRangePicker
							value={ range }
							onChange={ ( next ) => {
								setRange( next );
								setErrors( {} );
							} }
							className="flex w-full [&>button:first-child]:flex-1"
						/>
						{ errorText( 'from' ) }
						{ errorText( 'to' ) }
					</div>

					<fieldset className="m-0 space-y-2 border-0 p-0">
						<legend className="mb-2 p-0 text-sm font-semibold text-heading">
							{ __( 'On these days', 'radius-hotel-booking' ) }
						</legend>
						<div className="flex flex-wrap gap-1.5">
							{ days.map( ( day ) => {
								const on = picked.includes( day.value );
								return (
									<button
										key={ day.value }
										type="button"
										aria-pressed={ on }
										aria-label={ day.long }
										onClick={ () =>
											toggle( setPicked, day.value, ! on )
										}
										className={ cn(
											'h-9 min-w-[3rem] rounded-md border px-2 text-[13px] font-medium',
											on
												? 'border-primary bg-primary text-primary-foreground'
												: 'border-border bg-card text-heading hover:bg-primary-softer'
										) }
									>
										{ day.short }
									</button>
								);
							} ) }
						</div>
						<div className="flex flex-wrap gap-x-3">
							<Button
								type="button"
								variant="link"
								className="h-auto p-0 text-xs"
								onClick={ () =>
									setPicked( [ 0, 1, 2, 3, 4, 5, 6 ] )
								}
							>
								{ __( 'Every day', 'radius-hotel-booking' ) }
							</Button>
							<Button
								type="button"
								variant="link"
								className="h-auto p-0 text-xs"
								onClick={ () => setPicked( [ 5, 6 ] ) }
							>
								{ __(
									'Friday and Saturday',
									'radius-hotel-booking'
								) }
							</Button>
						</div>
						{ errorText( 'weekdays' ) }
					</fieldset>

					<fieldset className="m-0 space-y-2 border-0 p-0">
						<legend className="mb-2 p-0 text-sm font-semibold text-heading">
							{ __( 'Rates', 'radius-hotel-booking' ) }
						</legend>
						<div className="space-y-2">
							<label
								htmlFor="rtbp-bulk-plan-0"
								className={ cn(
									'flex items-start gap-2 text-sm text-heading',
									priceAction && 'opacity-60'
								) }
							>
								<Checkbox
									id="rtbp-bulk-plan-0"
									className="mt-0.5"
									checked={ plans.includes( 0 ) }
									disabled={ priceAction }
									onCheckedChange={ ( on ) =>
										toggle( setPlans, 0, on === true )
									}
								/>
								<span>
									{ __(
										'Whole room type',
										'radius-hotel-booking'
									) }
									<span className="block text-xs text-muted-foreground">
										{ priceAction
											? __(
													'Prices are set per rate.',
													'radius-hotel-booking'
											  )
											: __(
													'Closing it stops every rate from being sold.',
													'radius-hotel-booking'
											  ) }
									</span>
								</span>
							</label>
							{ rates.map( ( rate ) => (
								<label
									key={ rate.rate_plan_id }
									htmlFor={ `rtbp-bulk-plan-${ rate.rate_plan_id }` }
									className="flex items-center gap-2 text-sm text-heading"
								>
									<Checkbox
										id={ `rtbp-bulk-plan-${ rate.rate_plan_id }` }
										checked={ plans.includes(
											rate.rate_plan_id
										) }
										onCheckedChange={ ( on ) =>
											toggle(
												setPlans,
												rate.rate_plan_id,
												on === true
											)
										}
									/>
									{ rate.name }
								</label>
							) ) }
						</div>
						{ errorText( 'rate_plan_ids' ) }
					</fieldset>

					<p
						className="m-0 rounded-lg bg-muted px-3 py-2.5 text-sm text-muted-foreground"
						aria-live="polite"
					>
						{ summary }
					</p>

					<DialogFooter className="gap-2">
						<Button
							type="button"
							variant="ghost"
							onClick={ onClose }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						<Button
							type="submit"
							disabled={
								save.isPending || ( input && ! canApply )
							}
						>
							{ __( 'Apply', 'radius-hotel-booking' ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
