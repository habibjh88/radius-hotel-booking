/**
 * The rates of the search, grouped by room type (2.2–2.4), then "other stay
 * options": rates that would fit different dates, each with a switch to
 * those dates.
 */
import { useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { BedDouble, ChevronDown, ChevronUp } from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import { Button } from '@/components/ui/button';
import RateCard from './RateCard';
import { localDate, windowLabel } from './rates';

/**
 * @param {Object}   props           Props.
 * @param {Object}   props.data      The availability search result.
 * @param {Object}   props.choice    `{ room_type_id, rate_plan_id }` or null.
 * @param {Function} props.onSelect  Called with `( roomType, rate )`.
 * @param {Function} props.onDates   Called with `{ departure }` to switch dates.
 * @return {JSX.Element} List.
 */
export default function RateList( { data, choice, onSelect, onDates } ) {
	const [ showOther, setShowOther ] = useState( false );
	const types = data?.room_types || [];
	const other = data?.other_options || [];

	if ( ! types.length ) {
		return (
			<EmptyState
				icon={ BedDouble }
				title={ __( 'No room types to sell', 'radius-hotel-booking' ) }
				description={ __(
					'Add a room type with rooms and rates under Rooms & floors and Rate plans.',
					'radius-hotel-booking'
				) }
			/>
		);
	}

	return (
		<div className="space-y-6">
			{ types.map( ( type ) => {
				const rates = type.rates || [];
				return (
					<section key={ type.id } className="space-y-3">
						<div className="flex flex-wrap items-baseline justify-between gap-2">
							<h3 className="m-0 text-base font-semibold text-heading">
								{ type.name }
							</h3>
							<p className="m-0 text-sm text-muted-foreground">
								{ sprintf(
									/* translators: 1: number of rooms, 2: most adults. */
									_n(
										'%1$d room · up to %2$d adults',
										'%1$d rooms · up to %2$d adults',
										type.rooms_sellable,
										'radius-hotel-booking'
									),
									type.rooms_sellable,
									type.max_adults
								) }
							</p>
						</div>
						{ rates.length ? (
							// A row that scrolls sideways on a phone, a grid from md up.
							<div className="-mx-1 flex snap-x gap-3 overflow-x-auto px-1 pb-1 md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0">
								{ rates.map( ( rate ) => (
									<RateCard
										key={ rate.rate_plan_id }
										rate={ rate }
										selected={
											choice?.room_type_id === type.id &&
											choice?.rate_plan_id ===
												rate.rate_plan_id
										}
										onSelect={ ( picked ) =>
											onSelect( type, picked )
										}
									/>
								) ) }
							</div>
						) : (
							<p className="m-0 text-sm text-muted-foreground">
								{ __(
									'No rate matches these dates.',
									'radius-hotel-booking'
								) }
							</p>
						) }
					</section>
				);
			} ) }

			{ other.length ? (
				<section className="space-y-3 border-t border-border pt-4">
					<Button
						type="button"
						variant="ghost"
						size="sm"
						onClick={ () => setShowOther( ( open ) => ! open ) }
						aria-expanded={ showOther }
					>
						{ showOther ? (
							<ChevronUp className="h-4 w-4" aria-hidden="true" />
						) : (
							<ChevronDown
								className="h-4 w-4"
								aria-hidden="true"
							/>
						) }
						{ sprintf(
							/* translators: %d: number of other stay options. */
							__(
								'Other stay options (%d)',
								'radius-hotel-booking'
							),
							other.length
						) }
					</Button>
					{ showOther ? (
						<ul className="m-0 list-none space-y-2 p-0">
							{ other.map( ( rate ) => (
								<li
									key={ `${ rate.room_type_id }-${ rate.rate_plan_id }` }
									className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3"
								>
									<div className="min-w-0">
										<p className="m-0 text-sm font-semibold text-heading">
											{ sprintf(
												/* translators: 1: rate name, 2: room type name. */
												__(
													'%1$s · %2$s',
													'radius-hotel-booking'
												),
												rate.name,
												rate.room_type_name
											) }
										</p>
										<p className="m-0 text-xs text-muted-foreground">
											{ windowLabel( rate.window ) }
										</p>
									</div>
									{ rate.window ? (
										<Button
											type="button"
											size="sm"
											variant="outline"
											onClick={ () =>
												onDates( {
													departure: localDate(
														rate.window.end
													),
												} )
											}
										>
											{ __(
												'Use these dates',
												'radius-hotel-booking'
											) }
										</Button>
									) : null }
								</li>
							) ) }
						</ul>
					) : null }
				</section>
			) : null }
		</div>
	);
}
