/**
 * The hold's countdown (2.14): how long the chosen rooms stay held. Amber in
 * the last two minutes; once expired, the rooms are checked again when the
 * booking is confirmed.
 */
import { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Timer } from 'lucide-react';

import { cn } from '@/lib/utils';

/**
 * @param {Object}  props           Props.
 * @param {string}  props.expiresAt ISO 8601.
 * @param {boolean} props.expired   The holds ran out.
 * @return {JSX.Element|null} Chip.
 */
export default function HoldChip( { expiresAt, expired } ) {
	const [ now, setNow ] = useState( Date.now() );
	useEffect( () => {
		const timer = window.setInterval( () => setNow( Date.now() ), 1000 );
		return () => window.clearInterval( timer );
	}, [] );

	if ( ! expiresAt ) {
		return null;
	}
	const left = Math.max( 0, new Date( expiresAt ).getTime() - now );
	const minutes = Math.floor( left / 60000 );
	const seconds = Math.floor( ( left % 60000 ) / 1000 );
	const over = expired || left <= 0;

	return (
		<span
			className={ cn(
				'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold',
				over && 'bg-muted text-muted-foreground',
				! over && left <= 120000 && 'bg-warning-soft text-warning',
				! over && left > 120000 && 'bg-primary-soft text-primary'
			) }
			role="timer"
			aria-live="off"
		>
			<Timer className="h-3.5 w-3.5" aria-hidden="true" />
			{ over
				? __(
						'Hold expired: rooms are checked again on confirm',
						'radius-hotel-booking'
				  )
				: sprintf(
						/* translators: 1: minutes, 2: seconds. */
						__(
							'Rooms held for %1$d:%2$s',
							'radius-hotel-booking'
						),
						minutes,
						String( seconds ).padStart( 2, '0' )
				  ) }
		</span>
	);
}
