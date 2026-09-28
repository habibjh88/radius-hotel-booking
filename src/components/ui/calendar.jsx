/**
 * Calendar (react-day-picker v9) styled with the design tokens. Works in plain
 * calendar days; convert with ymdToDate()/dateToYmd() from src/lib/format.js.
 * "Today" is the hotel's today (site time zone), and weeks start on the
 * WordPress "Week starts on" setting.
 */
import { DayPicker } from 'react-day-picker';
import { enUS, fr } from 'react-day-picker/locale';
import { ChevronLeft, ChevronRight } from 'lucide-react';

import { siteToday, ymdToDate } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * date-fns locale for the site language (French or English for now).
 *
 * @return {Object} Locale.
 */
function siteLocale() {
	const locale =
		window.radius_hotel_booking_param?.format?.locale ||
		window.radius_hotel_booking_site_param?.format?.locale ||
		'';
	return String( locale ).toLowerCase().startsWith( 'fr' ) ? fr : enUS;
}

/**
 * @param {Object} props           DayPicker props.
 * @param {string} props.className Extra classes.
 * @return {JSX.Element} Calendar.
 */
export function Calendar( { className, classNames, ...props } ) {
	const params = window.radius_hotel_booking_param || {};

	return (
		<DayPicker
			locale={ siteLocale() }
			weekStartsOn={
				Number.isInteger( params.start_of_week )
					? params.start_of_week
					: 1
			}
			today={ ymdToDate( siteToday() ) }
			showOutsideDays
			className={ cn( 'p-1', className ) }
			classNames={ {
				months: 'relative flex flex-col gap-4 sm:flex-row sm:gap-6',
				month: 'space-y-3',
				month_caption: 'flex h-8 items-center justify-center',
				caption_label: 'text-sm font-semibold capitalize text-heading',
				nav: 'absolute inset-x-0 top-0 flex h-8 items-center justify-between',
				button_previous:
					'flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-heading hover:bg-primary-softer disabled:opacity-40',
				button_next:
					'flex h-8 w-8 items-center justify-center rounded-md border border-border bg-card text-heading hover:bg-primary-softer disabled:opacity-40',
				month_grid: 'w-full border-collapse',
				weekdays: 'flex',
				weekday:
					'w-9 text-[11px] font-medium uppercase text-muted-foreground',
				week: 'mt-1 flex w-full',
				day: 'relative h-9 w-9 p-0 text-center text-sm',
				day_button:
					'h-9 w-9 rounded-md border-0 bg-transparent text-sm tabular-nums text-foreground hover:bg-primary-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
				today: '[&>button]:font-bold [&>button]:text-primary',
				selected:
					'[&>button]:bg-primary [&>button]:text-primary-foreground [&>button]:hover:bg-primary-hover',
				range_start: 'rounded-l-md bg-primary-soft',
				range_end: 'rounded-r-md bg-primary-soft',
				range_middle:
					'bg-primary-soft [&>button]:!bg-transparent [&>button]:!text-heading [&>button]:rounded-none',
				outside:
					'[&>button]:text-muted-foreground [&>button]:opacity-50',
				disabled: '[&>button]:opacity-40 [&>button]:line-through',
				hidden: 'invisible',
				...classNames,
			} }
			components={ {
				Chevron: ( { orientation } ) =>
					orientation === 'left' ? (
						<ChevronLeft className="h-4 w-4" aria-hidden="true" />
					) : (
						<ChevronRight className="h-4 w-4" aria-hidden="true" />
					),
			} }
			{ ...props }
		/>
	);
}
