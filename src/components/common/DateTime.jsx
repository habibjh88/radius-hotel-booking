/**
 * A date or time in the hotel's time zone and date format, as a semantic
 * <time> element with the full date and time on hover.
 *
 *   <DateTime value={ booking.created_at } />                   date
 *   <DateTime value={ line.start_at } show="time" />             time
 *   <DateTime value={ line.start_at } show="datetime" />         both
 *   <DateTime value={ line.start_at } end={ line.end_at } />     stay window
 *
 * Values: ISO 8601 with offset (API) or site-local `Y-m-d[ H:i[:s]]`.
 */
import {
	formatDate,
	formatDateRange,
	formatDateTime,
	formatTime,
} from '@/lib/format';
import { cn } from '@/lib/utils';

const FORMATTERS = {
	date: formatDate,
	time: formatTime,
	datetime: formatDateTime,
};

/**
 * @param {Object} props           Props.
 * @param {string} props.value     Date value.
 * @param {string} props.end       End of a range (renders a stay window).
 * @param {string} props.show      'date' | 'time' | 'datetime'.
 * @param {string} props.className Extra classes.
 * @return {JSX.Element|null} Time.
 */
export default function DateTime( { value, end, show = 'date', className } ) {
	if ( ! value ) {
		return null;
	}

	const text = end
		? formatDateRange( value, end )
		: ( FORMATTERS[ show ] || formatDate )( value );
	if ( ! text ) {
		return null;
	}

	const full = end ? formatDateRange( value, end ) : formatDateTime( value );
	// A machine-readable value for <time>: ISO strings pass through; site-local
	// strings are already wall-clock values.
	const machine = String( value ).replace( ' ', 'T' );

	return (
		<time
			dateTime={ machine }
			title={ full }
			className={ cn(
				// A single date never breaks; a stay window may wrap at its arrow.
				end ? 'tabular-nums' : 'whitespace-nowrap tabular-nums',
				className
			) }
		>
			{ text }
		</time>
	);
}
