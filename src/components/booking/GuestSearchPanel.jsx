/**
 * The website's search panel on the booking page (M04): arrival, departure,
 * guests (one field with a popover) and, when flexible rates are offered,
 * the check-in time — one row on a wide screen, a tidy grid on a phone.
 *
 * Same values and rules as the desk's DatesBar (the stay keeps its length
 * when the arrival moves); only the look is the website's.
 */
import { useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Minus, Plus, Users } from 'lucide-react';

import { DatePicker, TimePicker } from '@/components/common/DatePicker';
import { Button } from '@/components/ui/button';
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from '@/components/ui/popover';
import { addDaysYmd, siteToday, ymdToDate } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * A labelled cell of the panel.
 *
 * @param {Object}      props          Props.
 * @param {string}      props.label    Label.
 * @param {string}      props.hint     Small text under the field.
 * @param {string}      props.error    Error under the field.
 * @param {JSX.Element} props.children The field.
 * @return {JSX.Element} Cell.
 */
function Cell( { label, hint, error, children } ) {
	return (
		<div className="min-w-0 space-y-1.5">
			<span className="block text-xs font-semibold uppercase tracking-wide text-muted-foreground">
				{ label }
			</span>
			{ children }
			{ error || hint ? (
				<span
					className={ cn(
						'block text-xs',
						error ? 'text-destructive' : 'text-muted-foreground'
					) }
					role={ error ? 'alert' : undefined }
				>
					{ error || hint }
				</span>
			) : null }
		</div>
	);
}

/**
 * One row of the guests popover: a label and − n + (44 px targets).
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Label.
 * @param {string}   props.hint     Small text under the label.
 * @param {number}   props.value    Value.
 * @param {number}   props.min      Minimum.
 * @param {number}   props.max      Maximum.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} Row.
 */
function CountRow( { label, hint, value, min, max, onChange } ) {
	return (
		<div className="flex items-center justify-between gap-4 py-2">
			<div className="min-w-0">
				<span className="block text-sm font-semibold text-heading">
					{ label }
				</span>
				{ hint ? (
					<span className="block text-xs text-muted-foreground">
						{ hint }
					</span>
				) : null }
			</div>
			<div className="flex items-center gap-2">
				<Button
					type="button"
					variant="outline"
					size="icon"
					className="h-10 w-10 rounded-full"
					disabled={ value <= min }
					onClick={ () => onChange( Math.max( min, value - 1 ) ) }
					aria-label={ sprintf(
						/* translators: %s: field name, e.g. Adults. */
						__( 'Fewer %s', 'radius-hotel-booking' ),
						label
					) }
				>
					<Minus className="h-4 w-4" aria-hidden="true" />
				</Button>
				<span
					className="w-6 text-center text-base font-semibold tabular-nums text-heading"
					aria-live="polite"
				>
					{ value }
				</span>
				<Button
					type="button"
					variant="outline"
					size="icon"
					className="h-10 w-10 rounded-full"
					disabled={ value >= max }
					onClick={ () => onChange( Math.min( max, value + 1 ) ) }
					aria-label={ sprintf(
						/* translators: %s: field name, e.g. Adults. */
						__( 'More %s', 'radius-hotel-booking' ),
						label
					) }
				>
					<Plus className="h-4 w-4" aria-hidden="true" />
				</Button>
			</div>
		</div>
	);
}

/**
 * Adults and children in one field.
 *
 * @param {Object}   props          Props.
 * @param {number}   props.adults   Adults.
 * @param {number}   props.children Children.
 * @param {Function} props.onChange Called with `{ adults }` or `{ children }`.
 * @return {JSX.Element} Field.
 */
export function GuestsField( { adults, children, onChange } ) {
	const [ open, setOpen ] = useState( false );
	const label = [
		sprintf(
			/* translators: %d: number of adults. */
			_n( '%d adult', '%d adults', adults, 'radius-hotel-booking' ),
			adults
		),
		children
			? sprintf(
					/* translators: %d: number of children. */
					_n(
						'%d child',
						'%d children',
						children,
						'radius-hotel-booking'
					),
					children
			  )
			: null,
	]
		.filter( Boolean )
		.join( ' · ' );

	return (
		<Popover open={ open } onOpenChange={ setOpen }>
			<PopoverTrigger asChild>
				<button
					type="button"
					className="flex min-h-[45px] w-full items-center gap-2 rounded-md border border-border bg-white px-3 py-1 text-left text-base font-normal normal-case tracking-normal text-foreground shadow-none transition-colors hover:bg-white hover:text-foreground focus:border-primary focus:bg-white focus:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-sm"
					aria-label={ sprintf(
						/* translators: %s: e.g. "2 adults · 1 child". */
						__( 'Guests: %s', 'radius-hotel-booking' ),
						label
					) }
				>
					<Users
						className="h-4 w-4 shrink-0 text-muted-foreground"
						aria-hidden="true"
					/>
					<span className="min-w-0 flex-1 truncate">{ label }</span>
				</button>
			</PopoverTrigger>
			<PopoverContent align="start" className="w-72 p-3">
				<div className="divide-y divide-border">
					<CountRow
						label={ __( 'Adults', 'radius-hotel-booking' ) }
						value={ adults }
						min={ 1 }
						max={ 20 }
						onChange={ ( value ) => onChange( { adults: value } ) }
					/>
					<CountRow
						label={ __( 'Children', 'radius-hotel-booking' ) }
						value={ children }
						min={ 0 }
						max={ 10 }
						onChange={ ( value ) =>
							onChange( { children: value } )
						}
					/>
				</div>
				<Button
					type="button"
					className="mt-2 w-full"
					onClick={ () => setOpen( false ) }
				>
					{ __( 'Done', 'radius-hotel-booking' ) }
				</Button>
			</PopoverContent>
		</Popover>
	);
}

/**
 * @param {Object}      props          Props.
 * @param {Object}      props.search   `{ arrival, departure, adults, children, checkin_time }`.
 * @param {Function}    props.onChange Called with the changed fields.
 * @param {Object|null} props.checkin  The flexible rates' `{ from, until }` (null: no time field).
 * @param {Object}      props.limits   Optional `{ min, max }` arrivals (`Y-m-d`).
 * @param {boolean}     props.askDates The guest chose a room before the dates: flag the empty ones.
 * @param {boolean}     props.bare     No box of its own (inside a part that already is one: the one-page form).
 * @return {JSX.Element} Panel.
 */
export default function GuestSearchPanel( {
	search,
	onChange,
	checkin,
	limits,
	askDates = false,
	bare = false,
} ) {
	const today = siteToday();
	const first = limits?.min && limits.min > today ? limits.min : today;
	const last = limits?.max || undefined;
	const badSpan = search.departure && search.departure < search.arrival;
	const nights =
		search.arrival && search.departure && ! badSpan
			? Math.round(
					( ymdToDate( search.departure ) -
						ymdToDate( search.arrival ) ) /
						86400000
			  )
			: 0;

	let stay = '';
	if ( search.arrival && search.departure && ! badSpan ) {
		stay = nights
			? sprintf(
					/* translators: %d: number of nights. */
					_n(
						'%d night',
						'%d nights',
						nights,
						'radius-hotel-booking'
					),
					nights
			  )
			: __( 'Day stay', 'radius-hotel-booking' );
	}

	return (
		<div
			className={
				bare
					? undefined
					: 'rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-5'
			}
		>
			<div className="grid grid-cols-1 gap-4 min-[480px]:grid-cols-2 md:grid-cols-[repeat(auto-fit,minmax(10.5rem,1fr))]">
				<Cell
					label={ __( 'Arrival', 'radius-hotel-booking' ) }
					error={
						askDates && ! search.arrival
							? __(
									'Choose your arrival date.',
									'radius-hotel-booking'
							  )
							: ''
					}
				>
					<DatePicker
						className={ cn(
							askDates &&
								! search.arrival &&
								'rounded-md ring-2 ring-destructive'
						) }
						min={ first }
						max={ last }
						value={ search.arrival }
						onChange={ ( arrival ) => {
							if ( ! arrival ) {
								onChange( { arrival } );
								return;
							}
							// Keep the stay's length when the arrival moves.
							onChange( {
								arrival,
								departure: addDaysYmd( arrival, nights ),
							} );
						} }
					/>
				</Cell>
				<Cell
					label={ __( 'Departure', 'radius-hotel-booking' ) }
					hint={ stay }
					error={
						badSpan
							? __(
									'The departure cannot be before the arrival.',
									'radius-hotel-booking'
							  )
							: ''
					}
				>
					<DatePicker
						min={ search.arrival || first }
						value={ search.departure }
						onChange={ ( departure ) => onChange( { departure } ) }
					/>
				</Cell>
				<Cell label={ __( 'Guests', 'radius-hotel-booking' ) }>
					<GuestsField
						adults={ Number( search.adults ) }
						children={ Number( search.children ) }
						onChange={ onChange }
					/>
				</Cell>
				{ checkin ? (
					<Cell
						label={ __( 'Check-in time', 'radius-hotel-booking' ) }
						hint={ sprintf(
							/* translators: 1: earliest check-in time, 2: latest check-in time. */
							__(
								'Flexible stays, %1$s–%2$s',
								'radius-hotel-booking'
							),
							checkin.from,
							checkin.until
						) }
					>
						<TimePicker
							minuteStep={ 30 }
							min={ checkin.from }
							max={ checkin.until }
							value={ search.checkin_time }
							onChange={ ( checkin_time ) =>
								onChange( { checkin_time } )
							}
						/>
					</Cell>
				) : null }
			</div>
		</div>
	);
}
