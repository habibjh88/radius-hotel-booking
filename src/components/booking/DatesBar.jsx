/**
 * The booking flow's search bar (2.1, 2.6): arrival, departure (the same day
 * for day stays), adults, children, and — when flexible rates are offered —
 * the check-in time, within the plan's allowed range.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Minus, Plus } from 'lucide-react';

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
 * @param {Object|null} props.checkin  The flexible rates' `{ from, until }` (null: no time field).
 * @param {Object}      props.limits   Optional `{ min, max }` arrivals (`Y-m-d`; the website's rules, M04).
 * @return {JSX.Element} Bar.
 */
export default function DatesBar( { search, onChange, checkin, limits } ) {
	const today = siteToday();
	const first = limits?.min && limits.min > today ? limits.min : today;
	const last = limits?.max || undefined;
	const badSpan = search.departure && search.departure < search.arrival;

	return (
		<div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
			<Field label={ __( 'Arrival', 'radius-hotel-booking' ) } required>
				<Input
					type="date"
					min={ first }
					max={ last }
					value={ search.arrival }
					onChange={ ( e ) => {
						const arrival = e.target.value;
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
					search.departure === search.arrival
						? __( 'Same day: day stays.', 'radius-hotel-booking' )
						: ''
				}
				required
			>
				<Input
					type="date"
					min={ search.arrival || first }
					value={ search.departure }
					onChange={ ( e ) =>
						onChange( { departure: e.target.value } )
					}
				/>
			</Field>
			<Stepper
				label={ __( 'Adults', 'radius-hotel-booking' ) }
				value={ Number( search.adults ) }
				min={ 1 }
				max={ 20 }
				onChange={ ( adults ) => onChange( { adults } ) }
			/>
			<Stepper
				label={ __( 'Children', 'radius-hotel-booking' ) }
				value={ Number( search.children ) }
				min={ 0 }
				max={ 10 }
				onChange={ ( children ) => onChange( { children } ) }
			/>
			{ checkin ? (
				<Field
					label={ __( 'Check-in time', 'radius-hotel-booking' ) }
					description={ sprintf(
						/* translators: 1: earliest check-in time, 2: latest check-in time. */
						__( 'Between %1$s and %2$s.', 'radius-hotel-booking' ),
						checkin.from,
						checkin.until
					) }
				>
					<Input
						type="time"
						step={ 1800 }
						min={ checkin.from }
						max={ checkin.until }
						value={ search.checkin_time }
						onChange={ ( e ) =>
							onChange( { checkin_time: e.target.value } )
						}
					/>
				</Field>
			) : null }
		</div>
	);
}
