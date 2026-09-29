/**
 * "Why is it this price?" — the pricing steps of a quote (booking-engine §6,
 * feature 7.16), as the server returned them: for each night or day, every
 * step that changed the price, from the base to the final amount.
 *
 *   <PriceBreakdown quote={ quote } />              // inline
 *   <PriceBreakdownPopover quote={ quote } />       // a "Why this price?" link
 *
 * `quote` is `{ total, unit_prices, steps: [ { date, steps: [ { step,
 * label, before, after } ] } ] }` from `POST pricing/quote` (and, from M02,
 * a booking line's frozen `price_breakdown`). Also on `window.rtbp.ui`.
 */
import { __ } from '@wordpress/i18n';
import { Info } from 'lucide-react';

import Money from '@/components/common/Money';
import { Button } from '@/components/ui/button';
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from '@/components/ui/popover';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * One unit's steps.
 *
 * @param {Object}  props       Props.
 * @param {Object}  props.unit  `{ date, steps }`.
 * @param {boolean} props.dated Show the date heading (several units).
 * @return {JSX.Element} List.
 */
function UnitSteps( { unit, dated } ) {
	return (
		<div className="space-y-1">
			{ dated ? (
				<p className="m-0 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
					{ formatDate( unit.date ) }
				</p>
			) : null }
			<ol className="m-0 list-none space-y-1 p-0">
				{ unit.steps.map( ( step, index ) => {
					const delta =
						step.before === null ? null : step.after - step.before;
					return (
						<li
							key={ `${ step.step }-${ index }` }
							className="m-0 flex items-baseline justify-between gap-3 text-sm"
						>
							<span className="min-w-0 text-heading">
								{ step.label }
							</span>
							<span className="flex shrink-0 items-baseline gap-2 tabular-nums">
								{ delta !== null &&
								Math.abs( delta ) > 0.000001 ? (
									<span
										className={ cn(
											'text-xs',
											delta < 0
												? 'text-success'
												: 'text-warning'
										) }
									>
										{ delta < 0 ? '−' : '+' }
										<Money value={ Math.abs( delta ) } />
									</span>
								) : null }
								<span
									className={ cn(
										index === unit.steps.length - 1
											? 'font-semibold text-heading'
											: 'text-muted-foreground'
									) }
								>
									<Money value={ step.after } />
								</span>
							</span>
						</li>
					);
				} ) }
			</ol>
		</div>
	);
}

/**
 * The breakdown, inline.
 *
 * @param {Object} props           Props.
 * @param {Object} props.quote     Quote (`total`, `steps`).
 * @param {string} props.className Extra classes.
 * @return {JSX.Element|null} Breakdown.
 */
export function PriceBreakdown( { quote, className } ) {
	const units = quote?.steps || [];
	if ( ! units.length ) {
		return null;
	}
	const several = units.length > 1;
	return (
		<div className={ cn( 'space-y-3', className ) }>
			{ units.map( ( unit ) => (
				<UnitSteps key={ unit.date } unit={ unit } dated={ several } />
			) ) }
			{ several ? (
				<p className="m-0 flex items-baseline justify-between gap-3 border-t border-border pt-2 text-sm font-semibold text-heading">
					<span>{ __( 'Total', 'radius-hotel-booking' ) }</span>
					<Money value={ quote.total } />
				</p>
			) : null }
		</div>
	);
}

/**
 * A "Why this price?" link opening the breakdown in a popover.
 *
 * @param {Object} props       Props.
 * @param {Object} props.quote Quote.
 * @param {string} props.label Link text.
 * @return {JSX.Element|null} Trigger.
 */
export function PriceBreakdownPopover( { quote, label } ) {
	if ( ! quote?.steps?.length ) {
		return null;
	}
	return (
		<Popover>
			<PopoverTrigger asChild>
				<Button
					type="button"
					variant="link"
					size="sm"
					className="h-auto gap-1 p-0"
				>
					<Info className="h-3.5 w-3.5" aria-hidden="true" />
					{ label || __( 'Why this price?', 'radius-hotel-booking' ) }
				</Button>
			</PopoverTrigger>
			<PopoverContent
				align="start"
				className="w-80 max-w-[calc(100vw-2rem)]"
			>
				<p className="m-0 mb-2 text-sm font-semibold text-heading">
					{ __( 'How this price is made', 'radius-hotel-booking' ) }
				</p>
				<PriceBreakdown quote={ quote } />
			</PopoverContent>
		</Popover>
	);
}

export default PriceBreakdown;
