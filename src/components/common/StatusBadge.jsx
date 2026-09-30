/**
 * Status badge: `<StatusBadge domain="stay" value={ line.status } />`.
 * Colour and wording come from src/lib/status.js — never pick them locally.
 * A dot plus the text label, so status is never shown by colour alone.
 */
import { TONE_CLASSES, TONE_COLOR, getStatus } from '@/lib/status';
import { cn } from '@/lib/utils';

/**
 * @param {Object} props           Props.
 * @param {string} props.domain    'stay' | 'payment' | 'room' | 'availability' | 'standing' | 'note' | 'sync'.
 * @param {string} props.value     Status value.
 * @param {string} props.className Extra classes.
 * @return {JSX.Element} Badge.
 */
export default function StatusBadge( { domain, value, className } ) {
	const status = getStatus( domain, value );
	const classes = TONE_CLASSES[ status.tone ] || TONE_CLASSES.neutral;

	return (
		<span
			className={ cn(
				'inline-flex max-w-full items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold',
				classes[ status.outline ? 1 : 0 ],
				className
			) }
		>
			<span
				className="h-1.5 w-1.5 shrink-0 rounded-full"
				style={ { background: TONE_COLOR[ status.tone ] } }
				aria-hidden="true"
			/>
			{ status.label }
		</span>
	);
}
