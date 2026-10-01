/**
 * Date range picker with presets and an optional date-mode switch.
 *
 *   <DateRangePicker
 *       value={ { from: '2026-10-01', to: '2026-10-07' } }
 *       onChange={ setRange }
 *       mode="arrival"                  // optional: shows the switch
 *       onModeChange={ setMode }
 *   />
 *
 * Values are calendar days (`Y-m-d`) in the hotel's time zone; `{ from: '',
 * to: '' }` means "any date". The mode switch answers the question every list
 * and report asks (feature 1.8 / 10.7): does the range mean guests *arriving*
 * in it, or bookings *taken* in it?
 */
import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { CalendarRange, X } from 'lucide-react';

import SegmentedControl from '@/components/common/SegmentedControl';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from '@/components/ui/popover';
import useMediaQuery from '@/hooks/useMediaQuery';
import {
	addDaysYmd,
	dateToYmd,
	formatDate,
	siteToday,
	ymdToDate,
} from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Presets relative to the hotel's today.
 *
 * @return {Array<{key: string, label: string, from: string, to: string}>} Presets.
 */
function presets() {
	const today = siteToday();
	const params = window.radius_hotel_booking_param || {};
	const weekStart = Number.isInteger( params.start_of_week )
		? params.start_of_week
		: 1;
	const [ y, m, d ] = today.split( '-' ).map( Number );
	const weekday = new Date( Date.UTC( y, m - 1, d ) ).getUTCDay();
	const startOfWeek = addDaysYmd(
		today,
		-( ( weekday - weekStart + 7 ) % 7 )
	);
	const monthStart = `${ y }-${ String( m ).padStart( 2, '0' ) }-01`;
	const nextMonthStart = new Date( Date.UTC( y, m, 1 ) )
		.toISOString()
		.slice( 0, 10 );
	const lastMonthStart = new Date( Date.UTC( y, m - 2, 1 ) )
		.toISOString()
		.slice( 0, 10 );

	return [
		{
			key: 'today',
			label: __( 'Today', 'radius-hotel-booking' ),
			from: today,
			to: today,
		},
		{
			key: 'tomorrow',
			label: __( 'Tomorrow', 'radius-hotel-booking' ),
			from: addDaysYmd( today, 1 ),
			to: addDaysYmd( today, 1 ),
		},
		{
			key: 'next7',
			label: __( 'Next 7 days', 'radius-hotel-booking' ),
			from: today,
			to: addDaysYmd( today, 6 ),
		},
		{
			key: 'week',
			label: __( 'This week', 'radius-hotel-booking' ),
			from: startOfWeek,
			to: addDaysYmd( startOfWeek, 6 ),
		},
		{
			key: 'month',
			label: __( 'This month', 'radius-hotel-booking' ),
			from: monthStart,
			to: addDaysYmd( nextMonthStart, -1 ),
		},
		{
			key: 'lastMonth',
			label: __( 'Last month', 'radius-hotel-booking' ),
			from: lastMonthStart,
			to: addDaysYmd( monthStart, -1 ),
		},
	];
}

/**
 * Human label for a range.
 *
 * @param {{from: string, to: string}} value Range.
 * @return {string} Label.
 */
function rangeLabel( value ) {
	if ( ! value?.from ) {
		return __( 'Any date', 'radius-hotel-booking' );
	}
	const match = presets().find(
		( p ) => p.from === value.from && p.to === ( value.to || value.from )
	);
	if ( match ) {
		return match.label;
	}
	return ! value.to || value.to === value.from
		? formatDate( value.from )
		: `${ formatDate( value.from ) } – ${ formatDate( value.to ) }`;
}

/**
 * @param {Object}   props              Props.
 * @param {{from: string, to: string}} props.value Range (Y-m-d strings).
 * @param {Function} props.onChange     Called with the new range.
 * @param {string}   props.mode         'arrival' | 'created' (optional).
 * @param {Function} props.onModeChange Mode setter (with `mode`).
 * @param {string}   props.align        Popover alignment.
 * @param {string}   props.className    Extra classes for the trigger.
 * @return {JSX.Element} Picker.
 */
export default function DateRangePicker( {
	value = { from: '', to: '' },
	onChange,
	mode,
	onModeChange,
	align = 'start',
	className,
} ) {
	const [ open, setOpen ] = useState( false );
	const wide = useMediaQuery( '(min-width: 768px)' );
	const active = !! value?.from;

	const select = ( range ) => {
		onChange( {
			from: range?.from ? dateToYmd( range.from ) : '',
			to: range?.to
				? dateToYmd( range.to )
				: range?.from
				? dateToYmd( range.from )
				: '',
		} );
	};

	return (
		<div
			className={ cn( 'inline-flex max-w-full items-center', className ) }
		>
			<Popover open={ open } onOpenChange={ setOpen }>
				<PopoverTrigger asChild>
					<Button
						variant="outline"
						className={ cn(
							'min-w-0 justify-start gap-2 font-medium',
							active && 'rounded-r-none border-r-0'
						) }
					>
						<CalendarRange
							className="shrink-0 text-primary"
							aria-hidden="true"
						/>
						{ mode ? (
							<span className="text-muted-foreground">
								{ mode === 'created'
									? __( 'Booked:', 'radius-hotel-booking' )
									: __(
											'Arriving:',
											'radius-hotel-booking'
									  ) }
							</span>
						) : null }
						{ /* Truncates on a phone, so the clear button stays in view. */ }
						<span className="truncate">
							{ rangeLabel( value ) }
						</span>
					</Button>
				</PopoverTrigger>
				<PopoverContent
					align={ align }
					className="w-auto max-w-[calc(100vw-24px)] p-0"
				>
					<div className="flex flex-col md:flex-row">
						<div className="flex flex-wrap gap-1 border-b border-border p-3 md:w-40 md:flex-col md:flex-nowrap md:border-b-0 md:border-r">
							{ presets().map( ( preset ) => (
								<button
									key={ preset.key }
									type="button"
									onClick={ () => {
										onChange( {
											from: preset.from,
											to: preset.to,
										} );
										setOpen( false );
									} }
									className={ cn(
										'rounded-md border-0 px-2.5 py-1.5 text-left text-[13px] font-medium',
										value.from === preset.from &&
											value.to === preset.to
											? 'bg-primary-soft text-primary'
											: 'bg-transparent text-heading hover:bg-primary-softer'
									) }
								>
									{ preset.label }
								</button>
							) ) }
						</div>
						<div className="space-y-3 p-3">
							{ mode && onModeChange ? (
								<SegmentedControl
									label={ __(
										'Dates mean',
										'radius-hotel-booking'
									) }
									value={ mode }
									onChange={ onModeChange }
									options={ [
										{
											value: 'arrival',
											label: __(
												'Arrival date',
												'radius-hotel-booking'
											),
										},
										{
											value: 'created',
											label: __(
												'Booking date',
												'radius-hotel-booking'
											),
										},
									] }
								/>
							) : null }
							<Calendar
								mode="range"
								numberOfMonths={ wide ? 2 : 1 }
								defaultMonth={
									ymdToDate( value.from ) ||
									ymdToDate( siteToday() )
								}
								selected={ {
									from: ymdToDate( value.from ),
									to: ymdToDate( value.to ),
								} }
								onSelect={ select }
							/>
							<div className="flex justify-end gap-2 border-t border-border pt-3">
								<Button
									variant="ghost"
									size="sm"
									onClick={ () =>
										onChange( { from: '', to: '' } )
									}
								>
									{ __( 'Clear', 'radius-hotel-booking' ) }
								</Button>
								<Button
									size="sm"
									onClick={ () => setOpen( false ) }
								>
									{ __( 'Done', 'radius-hotel-booking' ) }
								</Button>
							</div>
						</div>
					</div>
				</PopoverContent>
			</Popover>
			{ active ? (
				<Button
					variant="outline"
					size="icon"
					className="rounded-l-none"
					onClick={ () => onChange( { from: '', to: '' } ) }
					aria-label={ __( 'Clear dates', 'radius-hotel-booking' ) }
				>
					<X aria-hidden="true" />
				</Button>
			) : null }
		</div>
	);
}
