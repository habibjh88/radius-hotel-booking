/**
 * Date, time and date + time pickers (shadcn style: a trigger button and a
 * popover), the plugin's replacement for the browser's native inputs.
 *
 *   <DatePicker value="2026-10-14" onChange={ setDay } min={ today } />
 *   <TimePicker value="14:30" onChange={ setTime } minuteStep={ 30 } />
 *   <DateTimePicker value="2026-10-14T14:30" onChange={ setWhen } />
 *
 * Values are the same strings the native inputs use — `Y-m-d`, `HH:MM` and
 * `Y-m-dTHH:MM` — in the hotel's time zone, and `onChange` receives the new
 * string ('' when cleared). Labels follow the site's date and time formats.
 */
import { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { CalendarDays, Clock, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from '@/components/ui/popover';
import {
	dateToYmd,
	formatDate,
	formatTime,
	siteNowTime,
	siteToday,
	ymdToDate,
} from '@/lib/format';
import { cn } from '@/lib/utils';

const pad = ( number ) => String( number ).padStart( 2, '0' );

/**
 * The trigger: looks like the plugin's Input, opens the popover.
 *
 * @param {Object}      props           Props.
 * @param {JSX.Element} props.icon      Leading icon.
 * @param {string}      props.label     Shown value, or '' for the placeholder.
 * @param {string}      props.placeholder Placeholder.
 * @param {Function}    props.onClear   Clears the value (shows the × button).
 * @param {string}      props.className Extra classes.
 * @return {JSX.Element} Trigger.
 */
function Trigger( {
	icon: Icon,
	label,
	placeholder,
	onClear,
	className,
	...props
} ) {
	return (
		<div className={ cn( 'relative w-full', className ) }>
			<PopoverTrigger asChild>
				<button
					type="button"
					className={ cn(
						'flex min-h-[45px] w-full items-center gap-2 rounded-md border border-border bg-white px-3 py-1 text-left text-base font-normal normal-case tracking-normal text-foreground shadow-none transition-colors hover:bg-white hover:text-foreground focus:border-primary focus:bg-white focus:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 aria-[invalid=true]:border-destructive md:text-sm',
						onClear && label && 'pr-9'
					) }
					{ ...props }
				>
					<Icon
						className="h-4 w-4 shrink-0 text-muted-foreground"
						aria-hidden="true"
					/>
					<span
						className={ cn(
							'min-w-0 flex-1 truncate tabular-nums',
							! label && 'text-muted-foreground'
						) }
					>
						{ label || placeholder }
					</span>
				</button>
			</PopoverTrigger>
			{ onClear && label && ! props.disabled ? (
				<button
					type="button"
					onClick={ onClear }
					className="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded border-0 bg-transparent p-0 text-muted-foreground hover:bg-primary-softer hover:text-heading focus:bg-primary-softer focus:text-heading"
					aria-label={ __( 'Clear', 'radius-hotel-booking' ) }
				>
					<X className="h-3.5 w-3.5" aria-hidden="true" />
				</button>
			) : null }
		</div>
	);
}

/**
 * A calendar-day picker.
 *
 * @param {Object}   props               Props.
 * @param {string}   props.value         `Y-m-d`, or ''.
 * @param {Function} props.onChange      Called with the new `Y-m-d` ('' when cleared).
 * @param {string}   props.min           Earliest day (`Y-m-d`).
 * @param {string}   props.max           Latest day (`Y-m-d`).
 * @param {boolean}  props.clearable     Show a clear button.
 * @param {boolean}  props.yearDropdown  Month and year menus (birth dates, far-off days).
 * @param {string}   props.placeholder   Placeholder.
 * @param {string}   props.align         Popover alignment.
 * @param {string}   props.className     Extra classes for the wrapper.
 * @return {JSX.Element} Picker.
 */
export function DatePicker( {
	value = '',
	onChange,
	min,
	max,
	clearable = false,
	yearDropdown = false,
	placeholder,
	align = 'start',
	className,
	...props
} ) {
	const [ open, setOpen ] = useState( false );
	const selected = ymdToDate( value );
	const today = siteToday();
	const todayAllowed = ( ! min || today >= min ) && ( ! max || today <= max );
	const year = Number( today.slice( 0, 4 ) );

	const disabled = [];
	if ( min ) {
		disabled.push( { before: ymdToDate( min ) } );
	}
	if ( max ) {
		disabled.push( { after: ymdToDate( max ) } );
	}

	const pick = ( ymd ) => {
		onChange?.( ymd );
		setOpen( false );
	};

	return (
		<Popover open={ open } onOpenChange={ setOpen }>
			<Trigger
				icon={ CalendarDays }
				label={ value ? formatDate( value ) : '' }
				placeholder={
					placeholder || __( 'Pick a date', 'radius-hotel-booking' )
				}
				onClear={ clearable ? () => onChange?.( '' ) : undefined }
				className={ className }
				{ ...props }
			/>
			<PopoverContent align={ align } className="w-auto p-3">
				<Calendar
					mode="single"
					selected={ selected }
					defaultMonth={
						selected || ymdToDate( min ) || ymdToDate( today )
					}
					disabled={ disabled }
					onSelect={ ( date ) => date && pick( dateToYmd( date ) ) }
					{ ...( yearDropdown
						? {
								captionLayout: 'dropdown',
								startMonth: min
									? ymdToDate( min )
									: new Date( year - 100, 0, 1 ),
								endMonth: max
									? ymdToDate( max )
									: new Date( year + 10, 11, 31 ),
						  }
						: {} ) }
				/>
				<div className="mt-2 flex justify-between gap-2 border-t border-border pt-2">
					<Button
						type="button"
						variant="ghost"
						size="sm"
						disabled={ ! todayAllowed }
						onClick={ () => pick( today ) }
					>
						{ __( 'Today', 'radius-hotel-booking' ) }
					</Button>
					{ clearable && value ? (
						<Button
							type="button"
							variant="ghost"
							size="sm"
							onClick={ () => pick( '' ) }
						>
							{ __( 'Clear', 'radius-hotel-booking' ) }
						</Button>
					) : null }
				</div>
			</PopoverContent>
		</Popover>
	);
}

/**
 * Whether the site shows times on a 12-hour clock.
 *
 * @return {boolean} 12-hour clock.
 */
function twelveHour() {
	return ! formatTime( '2000-01-01 13:00' ).includes( '13' );
}

/**
 * Whether `HH:MM` lies in [min, max]; a range whose end is before its start
 * runs past midnight (20:00 → 02:00).
 *
 * @param {string} time Time.
 * @param {string} min  Earliest.
 * @param {string} max  Latest.
 * @return {boolean} Allowed.
 */
function inRange( time, min, max ) {
	if ( min && max && max < min ) {
		return time >= min || time <= max;
	}
	return ( ! min || time >= min ) && ( ! max || time <= max );
}

/**
 * A time-of-day picker: an hour column and a minute column.
 *
 * @param {Object}   props             Props.
 * @param {string}   props.value       `HH:MM`, or ''.
 * @param {Function} props.onChange    Called with the new `HH:MM` ('' when cleared).
 * @param {string}   props.min         Earliest time (`HH:MM`).
 * @param {string}   props.max         Latest time (`HH:MM`).
 * @param {number}   props.minuteStep  Minutes between choices (default 5).
 * @param {boolean}  props.clearable   Show a clear button.
 * @param {string}   props.placeholder Placeholder.
 * @param {string}   props.align       Popover alignment.
 * @param {string}   props.className   Extra classes for the wrapper.
 * @return {JSX.Element} Picker.
 */
export function TimePicker( {
	value = '',
	onChange,
	min,
	max,
	minuteStep = 5,
	clearable = false,
	placeholder,
	align = 'start',
	className,
	...props
} ) {
	const [ open, setOpen ] = useState( false );
	const hoursRef = useRef( null );
	const minutesRef = useRef( null );
	const valid = /^\d{2}:\d{2}/.test( value );
	const hour = valid ? Number( value.slice( 0, 2 ) ) : null;
	const minute = valid ? Number( value.slice( 3, 5 ) ) : null;
	const h12 = twelveHour();

	const step = Math.max( 1, Math.min( 30, Number( minuteStep ) || 5 ) );
	const minutes = [];
	for ( let m = 0; m < 60; m += step ) {
		minutes.push( m );
	}
	// A saved value off the step (08:07) still shows as selected.
	if ( minute !== null && ! minutes.includes( minute ) ) {
		minutes.push( minute );
		minutes.sort( ( a, b ) => a - b );
	}

	const hourAllowed = ( h ) =>
		minutes.some( ( m ) =>
			inRange( `${ pad( h ) }:${ pad( m ) }`, min, max )
		);

	// Bring the selected hour and minute into view when the popover opens.
	useEffect( () => {
		if ( ! open ) {
			return;
		}
		const frame = window.requestAnimationFrame( () => {
			[ hoursRef, minutesRef ].forEach( ( ref ) => {
				const node = ref.current?.querySelector( '[data-selected]' );
				if ( node && ref.current ) {
					ref.current.scrollTop =
						node.offsetTop - ref.current.clientHeight / 2 + 16;
				}
			} );
		} );
		return () => window.cancelAnimationFrame( frame );
	}, [ open ] );

	const setHour = ( h ) => {
		// Keep the minute; if that time is out of range, take the hour's first allowed one.
		const keep = minute ?? 0;
		const next = inRange( `${ pad( h ) }:${ pad( keep ) }`, min, max )
			? keep
			: minutes.find( ( m ) =>
					inRange( `${ pad( h ) }:${ pad( m ) }`, min, max )
			  ) ?? 0;
		onChange?.( `${ pad( h ) }:${ pad( next ) }` );
	};

	const setMinute = ( m ) => {
		onChange?.( `${ pad( hour ?? 0 ) }:${ pad( m ) }` );
		setOpen( false );
	};

	const hourLabel = ( h ) =>
		h12 ? `${ h % 12 || 12 } ${ h < 12 ? 'AM' : 'PM' }` : pad( h );

	// "Now", rounded up to the next step (14:37 → 15:00 every 30 minutes),
	// so it is a time the picker offers; null when that runs past midnight.
	const [ nowH, nowM ] = siteNowTime().split( ':' ).map( Number );
	const nowUp = Math.ceil( ( nowH * 60 + nowM ) / step ) * step;
	const now =
		nowUp < 24 * 60
			? `${ pad( Math.floor( nowUp / 60 ) ) }:${ pad( nowUp % 60 ) }`
			: null;
	const column =
		'max-h-56 min-w-[4.5rem] snap-y space-y-0.5 overflow-y-auto overscroll-contain p-1';
	const cell = ( active ) =>
		cn(
			'block w-full whitespace-nowrap rounded-md border-0 px-3 py-1.5 text-center text-sm tabular-nums disabled:cursor-not-allowed disabled:opacity-30',
			active
				? 'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground'
				: 'bg-transparent text-foreground hover:bg-primary-soft hover:text-foreground focus:bg-primary-soft focus:text-foreground'
		);

	return (
		<Popover open={ open } onOpenChange={ setOpen }>
			<Trigger
				icon={ Clock }
				label={ valid ? formatTime( `2000-01-01 ${ value }` ) : '' }
				placeholder={
					placeholder || __( 'Pick a time', 'radius-hotel-booking' )
				}
				onClear={ clearable ? () => onChange?.( '' ) : undefined }
				className={ className }
				{ ...props }
			/>
			<PopoverContent align={ align } className="w-auto p-2">
				<div className="flex divide-x divide-border">
					<div
						ref={ hoursRef }
						className={ column }
						role="listbox"
						aria-label={ __( 'Hour', 'radius-hotel-booking' ) }
					>
						{ Array.from( { length: 24 }, ( _, h ) => (
							<button
								key={ h }
								type="button"
								role="option"
								aria-selected={ h === hour }
								data-selected={ h === hour ? '' : undefined }
								disabled={ ! hourAllowed( h ) }
								onClick={ () => setHour( h ) }
								className={ cell( h === hour ) }
							>
								{ hourLabel( h ) }
							</button>
						) ) }
					</div>
					<div
						ref={ minutesRef }
						className={ column }
						role="listbox"
						aria-label={ __( 'Minute', 'radius-hotel-booking' ) }
					>
						{ minutes.map( ( m ) => (
							<button
								key={ m }
								type="button"
								role="option"
								aria-selected={ m === minute }
								data-selected={ m === minute ? '' : undefined }
								disabled={
									! inRange(
										`${ pad( hour ?? 0 ) }:${ pad( m ) }`,
										min,
										max
									)
								}
								onClick={ () => setMinute( m ) }
								className={ cell( m === minute ) }
							>
								{ pad( m ) }
							</button>
						) ) }
					</div>
				</div>
				<div className="mt-2 flex justify-between gap-2 border-t border-border pt-2">
					<Button
						type="button"
						variant="ghost"
						size="sm"
						disabled={ ! now || ! inRange( now, min, max ) }
						onClick={ () => {
							onChange?.( now );
							setOpen( false );
						} }
					>
						{ __( 'Now', 'radius-hotel-booking' ) }
					</Button>
					{ clearable && valid ? (
						<Button
							type="button"
							variant="ghost"
							size="sm"
							onClick={ () => {
								onChange?.( '' );
								setOpen( false );
							} }
						>
							{ __( 'Clear', 'radius-hotel-booking' ) }
						</Button>
					) : null }
				</div>
			</PopoverContent>
		</Popover>
	);
}

/**
 * A date and a time side by side, for one `Y-m-dTHH:MM` value (the native
 * datetime-local format).
 *
 * @param {Object}   props           Props.
 * @param {string}   props.value     `Y-m-dTHH:MM`, or ''.
 * @param {Function} props.onChange  Called with the new value.
 * @param {string}   props.min       Earliest day (`Y-m-d`).
 * @param {string}   props.max       Latest day (`Y-m-d`).
 * @param {number}   props.minuteStep Minutes between time choices.
 * @param {string}   props.className Extra classes for the wrapper.
 * @return {JSX.Element} Picker.
 */
export function DateTimePicker( {
	value = '',
	onChange,
	min,
	max,
	minuteStep,
	className,
	disabled,
	id,
	...props
} ) {
	const [ day = '', time = '' ] = String( value ).split( /[T ]/ );
	const join = ( d, t ) =>
		onChange?.( d ? `${ d }T${ ( t || '00:00' ).slice( 0, 5 ) }` : '' );

	return (
		<div className={ cn( 'flex gap-2', className ) }>
			<DatePicker
				id={ id }
				value={ day }
				min={ min }
				max={ max }
				disabled={ disabled }
				onChange={ ( d ) => join( d, time ) }
				className="min-w-0 flex-1"
				{ ...props }
			/>
			<TimePicker
				value={ time.slice( 0, 5 ) }
				minuteStep={ minuteStep }
				disabled={ disabled || ! day }
				onChange={ ( t ) => join( day || siteToday(), t ) }
				className="w-32 shrink-0"
				aria-label={ __( 'Time', 'radius-hotel-booking' ) }
			/>
		</div>
	);
}

export default DatePicker;
