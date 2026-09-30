/**
 * One rate of a room type in the booking flow (2.2–2.4): its window, the
 * live price with "why this price?", how many rooms are free, and Select —
 * or, when it cannot be sold, greyed out with the reason.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { Check, Clock } from 'lucide-react';

import Money from '@/components/common/Money';
import { PriceBreakdownPopover } from '@/components/common/PriceBreakdown';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { windowLabel } from './rates';

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.rate     A rate from the availability search.
 * @param {boolean}  props.selected This rate is the current choice.
 * @param {Function} props.onSelect Called when chosen (absent: no button).
 * @param {string}   props.action   Button text (default "Select").
 * @return {JSX.Element} Card.
 */
export default function RateCard( { rate, selected, onSelect, action } ) {
	const available = rate.available;
	const reason = rate.reasons?.[ 0 ]?.message || '';

	return (
		<div
			className={ cn(
				'flex min-w-[13rem] shrink-0 snap-start flex-col gap-2 rounded-lg border bg-white p-4 md:min-w-0',
				selected
					? 'border-primary ring-2 ring-primary'
					: 'border-border',
				! available && 'bg-muted opacity-70'
			) }
		>
			<div className="flex items-start justify-between gap-2">
				<p className="m-0 text-sm font-semibold text-heading">
					{ rate.name }
				</p>
				{ selected ? (
					<Check
						className="h-4 w-4 shrink-0 text-primary"
						aria-label={ __( 'Selected', 'radius-hotel-booking' ) }
					/>
				) : null }
			</div>
			{ rate.window ? (
				<p className="m-0 flex items-center gap-1.5 text-sm text-muted-foreground">
					<Clock
						className="h-3.5 w-3.5 shrink-0"
						aria-hidden="true"
					/>
					{ windowLabel( rate.window ) }
				</p>
			) : null }

			{ available ? (
				<>
					<p className="m-0 text-lg font-semibold text-heading">
						<Money value={ rate.price?.total } />
					</p>
					<PriceBreakdownPopover quote={ rate.price } />
					<p className="m-0 text-xs text-muted-foreground">
						{ sprintf(
							/* translators: %d: number of rooms free for this rate. */
							_n(
								'%d room free',
								'%d rooms free',
								rate.free_count,
								'radius-hotel-booking'
							),
							rate.free_count
						) }
					</p>
					{ onSelect ? (
						<Button
							type="button"
							size="sm"
							variant={ selected ? 'default' : 'outline' }
							aria-pressed={ selected }
							onClick={ () => onSelect( rate ) }
							className="mt-auto"
						>
							{ selected
								? __( 'Selected', 'radius-hotel-booking' )
								: action ||
								  __( 'Select', 'radius-hotel-booking' ) }
						</Button>
					) : null }
				</>
			) : (
				<>
					<p className="m-0 text-sm font-semibold text-muted-foreground">
						{ __( 'Unavailable', 'radius-hotel-booking' ) }
					</p>
					<p className="m-0 text-xs text-muted-foreground">
						{ reason }
					</p>
				</>
			) }
		</div>
	);
}
