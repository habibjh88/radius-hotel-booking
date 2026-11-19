/**
 * The booking flow's search bar (2.1, 2.6): arrival, departure (the same day
 * for day stays), adults and children. The arrival time of a flexible rate
 * is asked where the rate is chosen (ArrivalTimeField, in the room step), so
 * it does not read as a limit on which days can be booked.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Minus, Plus } from 'lucide-react';

import { DatePicker, TimePicker } from '@/components/common/DatePicker';
import { Field } from '@/components/common/Form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { addDaysYmd, siteToday, ymdToDate } from '@/lib/format';

/**
 * A number with − and + buttons (big targets on a tablet).
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Label.
 * @param {number}   props.value    Value.
 * @param {number}   props.min      Minimum.
 * @param {number}   props.max      Maximum.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} Stepper.
 */
export function Stepper( { label, value, min, max, onChange } ) {
	return (
		<Field label={ label }>
			<div className="flex items-center gap-2">
				<Button
					type="button"
					variant="outline"
					size="icon"
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
				<Input
					type="number"
					inputMode="numeric"
					min={ min }
					max={ max }
					value={ value }
					aria-label={ label }
					onChange={ ( e ) => {
						const next = parseInt( e.target.value, 10 );
						onChange(
							Number.isNaN( next )
								? min
								: Math.min( max, Math.max( min, next ) )
						);
					} }
					className="w-16 text-center"
				/>
				<Button
					type="button"
					variant="outline"
					size="icon"
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
		</Field>
	);
}

/**
 * @param {Object}      props          Props.
 * @param {Object}      props.search   `{ arrival, departure, adults, children, checkin_time }`.
 * @param {Function}    props.onChange Called with the changed fields.
 * @param {Object}      props.limits   Optional `{ min, max }` arrivals (`Y-m-d`; the website's rules, M04).
 * @param {boolean}     props.showChildren Show the Children field (off on the website when Settings → Public booking says so).
 * @return {JSX.Element} Bar.
 */
export default function DatesBar( {
	search,
	onChange,
	limits,
	showChildren = true,
} ) {
	const today = siteToday();
	const first = limits?.min && limits.min > today ? limits.min : today;
	const last = limits?.max || undefined;
	const badSpan = search.departure && search.departure < search.arrival;

	return (
		<div
			className={
				showChildren
					? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-4'
					: 'grid gap-4 sm:grid-cols-3'
			}
		>
			<Field label={ __( 'Arrival', 'radius-hotel-booking' ) } required>
				<DatePicker
					min={ first }
					max={ last }
					value={ search.arrival }
					onChange={ ( arrival ) => {
						if ( ! arrival ) {
							onChange( { arrival } );
							return;
						}
						// Keep the stay's length (nights) when the arrival moves.
						const nights =
							search.arrival &&
							search.departure &&
							search.departure >= search.arrival
								? Math.round(
										( ymdToDate( search.departure ) -
											ymdToDate( search.arrival ) ) /
											86400000
								  )
								: 0;
						onChange( {
							arrival,
							departure: addDaysYmd( arrival, nights ),
						} );
					} }
				/>
			</Field>
			<Field
				label={ __( 'Departure', 'radius-hotel-booking' ) }
				error={
					badSpan
						? __(
								'The departure cannot be before the arrival.',
								'radius-hotel-booking'
						  )
						: ''
				}
				description={
					search.arrival && search.departure === search.arrival
						? __( 'Same day: day stays.', 'radius-hotel-booking' )
						: ''
				}
				required
			>
				<DatePicker
					min={ search.arrival || first }
					value={ search.departure }
					onChange={ ( departure ) => onChange( { departure } ) }
				/>
			</Field>
			<Stepper
				label={ __( 'Adults', 'radius-hotel-booking' ) }
				value={ Number( search.adults ) }
				min={ 1 }
				max={ 20 }
				onChange={ ( adults ) => onChange( { adults } ) }
			/>
			{ showChildren ? (
				<Stepper
					label={ __( 'Children', 'radius-hotel-booking' ) }
					value={ Number( search.children ) }
					min={ 0 }
					max={ 10 }
					onChange={ ( children ) => onChange( { children } ) }
				/>
			) : null }
		</div>
	);
}

/**
 * The time field's help: the stay is counted from the arrival time, plus the
 * allowed range when the plan restricts it.
 *
 * @param {Object} checkin  `{ from, until }`.
 * @param {string} rateName The chosen rate's name, or ''.
 * @return {string} Help text.
 */
function checkinHelp( checkin, rateName ) {
	const counted = rateName
		? sprintf(
				/* translators: %s: rate plan name, e.g. 24 h. */
				__(
					'The %s stay is counted from this time.',
					'radius-hotel-booking'
				),
				rateName
		  )
		: __(
				'The flexible stay is counted from this time.',
				'radius-hotel-booking'
		  );
	if ( '00:00' === checkin.from && '23:30' === checkin.until ) {
		return counted;
	}
	return sprintf(
		/* translators: 1: sentence about the stay, 2: earliest arrival time, 3: latest arrival time. */
		__( '%1$s Arrival between %2$s and %3$s.', 'radius-hotel-booking' ),
		counted,
		checkin.from,
		checkin.until
	);
}

/**
 * A flexible rate's arrival time: the stay is counted from it. Shown in the
 * room step once such a rate is chosen (desk drawer, room dialog).
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.checkin  The rate's `{ from, until }`.
 * @param {string}   props.rateName The rate's name, for the help.
 * @param {string}   props.value    `HH:MM`.
 * @param {Function} props.onChange Called with the new `HH:MM`.
 * @return {JSX.Element} Field.
 */
export function ArrivalTimeField( {
	checkin,
	rateName = '',
	value,
	onChange,
} ) {
	return (
		<Field
			label={ __( 'Arrival time', 'radius-hotel-booking' ) }
			description={ checkinHelp( checkin, rateName ) }
		>
			<TimePicker
				minuteStep={ 30 }
				min={ checkin.from }
				max={ checkin.until }
				value={ value }
				onChange={ onChange }
			/>
		</Field>
	);
}
