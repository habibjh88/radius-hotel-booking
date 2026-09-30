/**
 * How a block's period reads: "1 July 2027 – 2 July 2027" for whole days
 * (the last day blocked, not the midnight after it), one date for a single
 * day, or the exact times otherwise.
 */
import { addDaysYmd, formatDate, formatDateRange } from '@/lib/format';

/**
 * @param {Object} block `{ start_at, end_at, whole_days }` (local `Y-m-d H:i`).
 * @return {string} Period.
 */
export function blockPeriod( block ) {
	if ( ! block.whole_days ) {
		return formatDateRange( block.start_at, block.end_at );
	}
	const first = block.start_at.slice( 0, 10 );
	const last = addDaysYmd( block.end_at.slice( 0, 10 ), -1 );
	return first === last
		? formatDate( first )
		: `${ formatDate( first ) } – ${ formatDate( last ) }`;
}
