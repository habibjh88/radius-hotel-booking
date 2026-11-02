/**
 * A guest's name that opens their record — every stay, notes and edit
 * history (R1-T11). Plain text when the viewer may not open guests or the
 * booking has no guest.
 *
 *   <GuestLink id={ row.guest_id } name={ row.guest } />
 */
import { Link } from 'react-router-dom';
import { __, sprintf } from '@wordpress/i18n';

import { useAccess } from '@/lib/access';
import { cn } from '@/lib/utils';

/**
 * @param {Object} props           Props.
 * @param {number} props.id        Guest id (0 = none).
 * @param {string} props.name      Name shown.
 * @param {string} props.className Extra classes.
 * @return {JSX.Element} Link or text.
 */
export default function GuestLink( { id, name, className } ) {
	const canOpen = useAccess( 'page.guests' ) !== 'locked';
	const label = name || '—';
	if ( ! id || ! canOpen ) {
		return <span className={ className }>{ label }</span>;
	}
	return (
		<Link
			to={ `/guests/${ id }` }
			className={ cn(
				'text-inherit no-underline hover:text-primary hover:underline',
				className
			) }
			// Rows open their booking on click: the name opens the guest instead.
			onClick={ ( event ) => event.stopPropagation() }
			title={ sprintf(
				/* translators: %s: guest name. */
				__(
					'Open %s: stays, notes and history',
					'radius-hotel-booking'
				),
				label
			) }
		>
			{ label }
		</Link>
	);
}
