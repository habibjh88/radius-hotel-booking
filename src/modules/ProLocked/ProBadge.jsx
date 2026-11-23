/**
 * The small lock + PRO mark next to a locked Pro item.
 */
import { __ } from '@wordpress/i18n';
import { Lock } from 'lucide-react';

import { cn } from '@/lib/utils';

/**
 * @param {Object}  props           Props.
 * @param {boolean} props.iconOnly  Only the lock (collapsed sidebar).
 * @param {string}  props.className Extra classes.
 * @return {JSX.Element} Badge.
 */
export default function ProBadge( { iconOnly = false, className } ) {
	return (
		<span
			className={ cn(
				'inline-flex shrink-0 items-center gap-1 rounded-md bg-warning-soft px-1.5 py-0.5 text-[10px] font-bold uppercase leading-none tracking-wide text-warning',
				className
			) }
			title={ __(
				'Available in Radius Hotel Booking Pro',
				'radius-hotel-booking'
			) }
		>
			<Lock className="h-3 w-3" aria-hidden="true" />
			{ iconOnly ? (
				<span className="sr-only">
					{ __( 'Pro', 'radius-hotel-booking' ) }
				</span>
			) : (
				__( 'Pro', 'radius-hotel-booking' )
			) }
		</span>
	);
}
