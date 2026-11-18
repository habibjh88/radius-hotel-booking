/**
 * One rate of a room type in the desk's booking flow (2.2–2.4), as a compact
 * row: name and window on the left; the price (with the regular price struck
 * through and the saving, as on the website), the rooms free and Select on
 * the right. A rate that cannot be sold is greyed out with the reason.
 * `compact`: the new booking screen's card — the whole card is the choice
 * (a radio), as on the hotel's old desk screen.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { Check, Clock } from 'lucide-react';

import Money from '@/components/common/Money';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { regularPrice, windowLabel } from './rates';

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.rate     A rate from the availability search.
 * @param {boolean}  props.selected This rate is the current choice.
 * @param {Function} props.onSelect Called when chosen (absent: no button).
 * @param {string}   props.action   Button text (default "Select").
 * @param {boolean}  props.compact  A radio card (the desk's new booking screen).
 * @return {JSX.Element} Row.
 */
export default function RateCard( {
	rate,
	selected,
	onSelect,
	action,
	compact = false,
} ) {
	const available = rate.available;
	const regular = available ? regularPrice( rate.price ) : null;
	const saved = regular
		? Math.round( ( 1 - Number( rate.price.total ) / regular ) * 100 )
		: 0;

	if ( compact ) {
		return (
			<li>
				<button
					type="button"
					role="radio"
					aria-checked={ Boolean( selected ) }
					disabled={ ! available }
					onClick={ () => onSelect?.( rate ) }
					className={ cn(
						'flex w-full items-start gap-3 rounded-lg border bg-card p-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
						selected
							? 'border-primary bg-primary-softer shadow-[inset_0_0_0_1px_var(--primary)]'
							: 'border-border',
						available
							? 'cursor-pointer hover:border-primary'
							: 'cursor-not-allowed bg-muted/40'
					) }
				>
					<span
						className={ cn(
							'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border',
							selected ? 'border-primary' : 'border-input'
						) }
						aria-hidden="true"
					>
						{ selected ? (
							<span className="h-2 w-2 rounded-full bg-primary" />
						) : null }
					</span>
					<span className="min-w-0 flex-1">
						<span
							className={ cn(
								'flex flex-wrap items-center gap-1.5 text-sm font-semibold leading-snug',
								available
									? 'text-heading'
									: 'text-muted-foreground'
							) }
						>
							{ rate.name }
							{ rate.checkin ? (
								<span className="rounded-full bg-primary-soft px-1.5 py-0.5 text-[10px] font-semibold text-primary">
									{ __(
										'Flexible check-in',
										'radius-hotel-booking'
									) }
								</span>
							) : null }
						</span>
						{ rate.window ? (
							<span className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
								<Clock
									className="h-3 w-3 shrink-0"
									aria-hidden="true"
								/>
								{ windowLabel( rate.window ) }
							</span>
						) : null }
						{ ! available ? (
							<span className="mt-0.5 block text-xs text-muted-foreground">
								{ rate.reasons?.[ 0 ]?.message ||
									__(
										'Unavailable',
										'radius-hotel-booking'
									) }
							</span>
						) : null }
					</span>
					{ available ? (
						<span className="shrink-0 text-right">
							<span className="block text-sm font-bold text-heading">
								<Money value={ rate.price?.total } />
							</span>
							{ regular ? (
								<span className="block text-[11px] text-muted-foreground">
									<del>
										<span className="sr-only">
											{ __(
												'Regular price:',
												'radius-hotel-booking'
											) }{ ' ' }
										</span>
										<Money value={ regular } />
									</del>
									{ saved ? (
										<span className="ml-1 font-semibold text-success">
											{ sprintf(
												/* translators: %d: discount in percent. */
												__(
													'−%d%%',
													'radius-hotel-booking'
												),
												saved
											) }
										</span>
									) : null }
								</span>
							) : null }
							<span className="block text-[11px] text-muted-foreground">
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
							</span>
						</span>
					) : null }
				</button>
			</li>
		);
	}

	return (
		<li
			className={ cn(
				'flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 transition-colors',
				selected &&
					'bg-primary-softer shadow-[inset_3px_0_0_0_var(--primary)]',
				! available && 'bg-muted/40'
			) }
		>
			<div className="min-w-0 flex-1">
				<p
					className={ cn(
						'm-0 flex flex-wrap items-center gap-2 text-sm font-semibold',
						available ? 'text-heading' : 'text-muted-foreground'
					) }
				>
					{ rate.name }
					{ rate.checkin ? (
						<span className="rounded-full bg-primary-soft px-2 py-0.5 text-[11px] font-semibold text-primary">
							{ __(
								'Flexible check-in',
								'radius-hotel-booking'
							) }
						</span>
					) : null }
				</p>
				{ rate.window ? (
					<p className="m-0 mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
						<Clock
							className="h-3.5 w-3.5 shrink-0"
							aria-hidden="true"
						/>
						{ windowLabel( rate.window ) }
					</p>
				) : null }
				{ ! available ? (
					<p className="m-0 mt-0.5 text-xs text-muted-foreground">
						{ rate.reasons?.[ 0 ]?.message ||
							__( 'Unavailable', 'radius-hotel-booking' ) }
					</p>
				) : null }
			</div>

			{ available ? (
				<div className="flex items-center gap-4">
					<div className="text-right">
						<p className="m-0 flex flex-wrap items-baseline justify-end gap-x-1.5 leading-tight">
							{ regular ? (
								<del className="text-xs text-muted-foreground">
									<span className="sr-only">
										{ __(
											'Regular price:',
											'radius-hotel-booking'
										) }{ ' ' }
									</span>
									<Money value={ regular } />
								</del>
							) : null }
							<span className="text-base font-bold text-heading">
								<Money value={ rate.price?.total } />
							</span>
							{ saved ? (
								<span className="rounded-full bg-success-soft px-1.5 py-0.5 text-[11px] font-semibold text-success">
									{ sprintf(
										/* translators: %d: discount in percent. */
										__( '−%d%%', 'radius-hotel-booking' ),
										saved
									) }
								</span>
							) : null }
						</p>
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
					</div>
					{ onSelect ? (
						<Button
							type="button"
							size="sm"
							variant={ selected ? 'default' : 'outline' }
							aria-pressed={ selected }
							onClick={ () => onSelect( rate ) }
							className="min-w-[6.5rem]"
						>
							{ selected ? <Check aria-hidden="true" /> : null }
							{ selected
								? __( 'Selected', 'radius-hotel-booking' )
								: action ||
								  __( 'Select', 'radius-hotel-booking' ) }
						</Button>
					) : null }
				</div>
			) : null }
		</li>
	);
}
