/**
 * A period filter as on the hotel's old dashboard (R1-T9, R1-T12): what the
 * dates mean (check-in, check-out, creation — the modes the screen offers),
 * From and Until with an optional time, and Apply. The screen only changes
 * on Apply (or Clear), so picking two dates and two times does not reload it
 * four times. Used by the booking list and the reports.
 *
 * URL values are the API's: `Y-m-d` (a whole day) or `Y-m-d H:i`.
 */
import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Info } from 'lucide-react';

import { DatePicker, DateTimePicker } from '@/components/common/DatePicker';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { addDaysYmd, formatDate, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * The date modes a screen can offer, with their labels.
 *
 * @return {Object<string,string>} Mode => label.
 */
export const periodModes = () => ( {
	arrival: __( 'Check-in', 'radius-hotel-booking' ),
	departure: __( 'Check-out', 'radius-hotel-booking' ),
	created: __( 'Creation date & time', 'radius-hotel-booking' ),
} );

/**
 * `Y-m-d` / `Y-m-d H:i` (URL) → `Y-m-dTHH:MM` (picker).
 *
 * @param {string} value URL value.
 * @return {string} Picker value.
 */
const toPicker = ( value ) => ( value ? value.replace( ' ', 'T' ) : '' );

/**
 * Picker value → URL value; midnight means the whole day (a bare date).
 *
 * @param {string} value Picker value.
 * @return {string} URL value.
 */
const toParam = ( value ) => {
	if ( ! value ) {
		return '';
	}
	const [ day, time = '00:00' ] = value.split( 'T' );
	return '00:00' === time ? day : `${ day } ${ time }`;
};

/**
 * @param {Object}   props           Props.
 * @param {string[]} props.modes     Modes offered (keys of periodModes()); none = no switch.
 * @param {string}   props.mode      Current mode.
 * @param {string}   props.from      URL value.
 * @param {string}   props.to        URL value.
 * @param {boolean}  props.withTime  Offer a time next to each date.
 * @param {boolean}  props.clearable Show Clear while a period is set.
 * @param {Function} props.onApply   Called with `{ mode, from, to }` (Clear: empty dates).
 * @param {string}   props.idPrefix  Prefix of the fields' ids (two filters on a page).
 * @param {boolean}  props.card      Standalone white card (on the page background), not a
 *                                   tinted box inside a panel.
 * @return {JSX.Element} Filter.
 */
export default function PeriodFilter( {
	modes = [],
	mode,
	from,
	to,
	withTime = true,
	clearable = true,
	onApply,
	idPrefix = 'rtbp-period',
	card = false,
} ) {
	const [ draft, setDraft ] = useState( {
		mode,
		from: toPicker( from ),
		to: toPicker( to ),
	} );

	// Follow the URL (back button, a tab that resets the page).
	useEffect( () => {
		setDraft( { mode, from: toPicker( from ), to: toPicker( to ) } );
	}, [ mode, from, to ] );

	const set = ( key, value ) =>
		setDraft( ( prev ) => ( { ...prev, [ key ]: value } ) );

	// As on the booking form: a From on or after Until moves Until forward,
	// keeping the period's length in days (at least one) and Until's time.
	const setFrom = ( value ) =>
		setDraft( ( prev ) => {
			const fromDay = ( value || '' ).slice( 0, 10 );
			const toDay = prev.to.slice( 0, 10 );
			if ( ! fromDay || ! toDay || toDay > fromDay ) {
				return { ...prev, from: value };
			}
			const prevFromDay = prev.from.slice( 0, 10 );
			const span =
				prevFromDay && toDay > prevFromDay
					? Math.round(
							( Date.parse( toDay ) -
								Date.parse( prevFromDay ) ) /
								86400000
					  )
					: 1;
			return {
				...prev,
				from: value,
				to: addDaysYmd( fromDay, span ) + prev.to.slice( 10 ),
			};
		} );
	const labels = periodModes();
	const Picker = withTime ? DateTimePicker : DatePicker;

	// The applied period in words, at the foot of the card, as on the old dashboard
	// ("2 November 2026 · 00:00 — 23:59 · Check-in"; the client's "I stand
	// report", R1-T13). Only once a period is applied.
	const day = ( value ) => ( value || '' ).slice( 0, 10 );
	const time = ( value, fallback ) =>
		( value || '' ).length > 10
			? formatTime( value.replace( ' ', 'T' ) )
			: fallback;
	const summary =
		from || to
			? [
					day( from ) === day( to ) || ! to
						? formatDate( day( from || to ) )
						: `${ formatDate( day( from ) ) } — ${ formatDate(
								day( to )
						  ) }`,
					withTime
						? `${ time(
								from,
								formatTime( '2000-01-01T00:00' )
						  ) } — ${ time(
								to,
								formatTime( '2000-01-01T23:59' )
						  ) }`
						: null,
			  ].filter( Boolean )
			: [];
	// A date-only picker gives `Y-m-d`, already a URL value.
	const out = ( value ) => ( withTime ? toParam( value ) : value );

	return (
		<div
			className={ cn(
				'rounded-xl border border-border',
				card ? 'bg-card shadow-sm' : 'bg-muted/40'
			) }
		>
			<form
				className="flex flex-col gap-4 p-4 2xl:flex-row 2xl:items-end"
				onSubmit={ ( event ) => {
					event.preventDefault();
					onApply( {
						mode: draft.mode,
						from: out( draft.from ),
						to: out( draft.to ),
					} );
				} }
			>
				{ modes.length > 1 ? (
					<RadioGroup
						value={ draft.mode }
						onValueChange={ ( value ) => set( 'mode', value ) }
						aria-label={ __( 'Filter by', 'radius-hotel-booking' ) }
						className="flex shrink-0 flex-wrap gap-x-4 gap-y-2 2xl:flex-col 2xl:gap-1.5"
					>
						{ modes.map( ( key ) => (
							<Label
								key={ key }
								htmlFor={ `${ idPrefix }-${ key }` }
								className="flex cursor-pointer items-center gap-2 text-sm font-medium text-heading"
							>
								<RadioGroupItem
									id={ `${ idPrefix }-${ key }` }
									value={ key }
								/>
								{ labels[ key ] || key }
							</Label>
						) ) }
					</RadioGroup>
				) : null }

				<div className="grid min-w-0 flex-1 gap-3 sm:grid-cols-2">
					<div className="min-w-0 space-y-1.5">
						<Label
							htmlFor={ `${ idPrefix }-from` }
							className="text-xs text-muted-foreground"
						>
							{ __( 'From', 'radius-hotel-booking' ) }
						</Label>
						<Picker
							id={ `${ idPrefix }-from` }
							value={
								withTime
									? draft.from
									: draft.from.slice( 0, 10 )
							}
							onChange={ setFrom }
							minuteStep={ withTime ? 30 : undefined }
						/>
					</div>
					<div className="min-w-0 space-y-1.5">
						<Label
							htmlFor={ `${ idPrefix }-to` }
							className="text-xs text-muted-foreground"
						>
							{ __( 'Until', 'radius-hotel-booking' ) }
						</Label>
						<Picker
							id={ `${ idPrefix }-to` }
							value={
								withTime ? draft.to : draft.to.slice( 0, 10 )
							}
							onChange={ ( value ) => set( 'to', value ) }
							min={
								draft.from
									? draft.from.slice( 0, 10 )
									: undefined
							}
							minuteStep={ withTime ? 30 : undefined }
						/>
					</div>
				</div>

				<div className="flex shrink-0 justify-end gap-2">
					{ clearable && ( from || to ) ? (
						<Button
							type="button"
							variant="ghost"
							onClick={ () =>
								onApply( { mode, from: '', to: '' } )
							}
						>
							{ __( 'Clear', 'radius-hotel-booking' ) }
						</Button>
					) : null }
					<Button type="submit">
						{ __( 'Apply filter', 'radius-hotel-booking' ) }
					</Button>
				</div>
			</form>
			{ summary.length ? (
				<p className="m-0 flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-border px-4 py-2.5 text-sm text-heading">
					<Info
						className="h-4 w-4 shrink-0 text-muted-foreground"
						aria-hidden="true"
					/>
					<span className="font-semibold">{ summary[ 0 ] }</span>
					{ summary[ 1 ] ? (
						<span className="text-muted-foreground">
							{ summary[ 1 ] }
						</span>
					) : null }
					{ modes.length > 1 && labels[ mode ] ? (
						<span className="rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground">
							{ labels[ mode ] }
						</span>
					) : null }
				</p>
			) : null }
		</div>
	);
}
