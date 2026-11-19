/**
 * The rates of the search, grouped by room type (2.2–2.4), then "other stay
 * options": rates that would fit different dates, each with a switch to
 * those dates.
 */
import { useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	BedDouble,
	ChevronDown,
	ChevronUp,
	DoorOpen,
	Users,
} from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import RateCard from './RateCard';
import { localDate, windowLabel } from './rates';

/**
 * @param {Object}   props           Props.
 * @param {Object}   props.data      The availability search result.
 * @param {Object}   props.choice    `{ room_type_id, rate_plan_id }` or null.
 * @param {Function} props.onSelect  Called with `( roomType, rate )`.
 * @param {Function} props.onDates   Called with `{ departure }` to switch dates.
 * @param {boolean}  props.compact   The desk's new booking screen: each room
 *                                   type a numbered block, its rates as radio
 *                                   cards, and its header sticky while its
 *                                   rates scroll past, so it is always clear
 *                                   which type a rate is of.
 * @param {string}   props.layout    With `compact` (Settings → Booking rules):
 *                                   `columns` = room types two per row, rates
 *                                   one per line; `wide` = one room type per
 *                                   row, rates two per line.
 * @return {JSX.Element} List.
 */
export default function RateList( {
	data,
	choice,
	onSelect,
	onDates,
	compact = false,
	layout = 'columns',
} ) {
	const wide = 'wide' === layout;
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
		<div className="space-y-4">
			<div
				className={ cn(
					! compact && 'space-y-4',
					compact && wide && 'space-y-[30px]',
					// Two columns only where each still fits a rate card.
					compact &&
						! wide &&
						'grid items-start gap-[30px] min-[1400px]:grid-cols-2'
				) }
				role={ compact ? 'radiogroup' : undefined }
				aria-label={
					compact ? __( 'Rates', 'radius-hotel-booking' ) : undefined
				}
			>
				{ types.map( ( type, index ) => {
					const rates = type.rates || [];
					return (
						<section
							key={ type.id }
							aria-label={ type.name }
							className={
								compact
									? // No overflow-hidden: it would stop the header sticking.
									  'rounded-xl border-2 border-solid border-[color-mix(in_srgb,var(--primary)_45%,#ffffff)] bg-card shadow-sm'
									: 'overflow-hidden rounded-xl border border-border bg-card'
							}
						>
							{ compact ? (
								<div className="sticky top-[calc(var(--rtbp-top)+64px)] z-10 flex items-center gap-3 rounded-t-[10px] border-0 border-b border-solid border-[color-mix(in_srgb,var(--primary)_25%,#ffffff)] bg-primary-softer px-4 py-3 shadow-[0_1px_0_rgba(0,0,0,0.02)] sm:top-[calc(var(--rtbp-top)+72px)]">
									{ /* Its place in the list, so staff keep track while scrolling. */ }
									<span
										className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground"
										aria-hidden="true"
									>
										{ index + 1 }
									</span>
									<div className="min-w-0 flex-1">
										<h3 className="m-0 truncate text-base font-bold leading-tight text-heading">
											{ type.name }
										</h3>
										<p className="m-0 mt-1 flex flex-wrap gap-1.5 text-[11px] font-semibold">
											<span className="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-white px-2 py-0.5 text-primary">
												<DoorOpen
													className="h-3 w-3"
													aria-hidden="true"
												/>
												{ sprintf(
													/* translators: %d: number of rooms. */
													_n(
														'%d room',
														'%d rooms',
														type.rooms_sellable,
														'radius-hotel-booking'
													),
													type.rooms_sellable
												) }
											</span>
											<span className="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-white px-2 py-0.5 text-primary">
												<Users
													className="h-3 w-3"
													aria-hidden="true"
												/>
												{ sprintf(
													/* translators: %d: most adults in a room. */
													_n(
														'Up to %d adult',
														'Up to %d adults',
														type.max_adults,
														'radius-hotel-booking'
													),
													type.max_adults
												) }
											</span>
										</p>
									</div>
									<span className="hidden shrink-0 text-xs font-semibold text-muted-foreground sm:block">
										{ sprintf(
											/* translators: %d: number of rates of this room type. */
											_n(
												'%d rate',
												'%d rates',
												rates.length,
												'radius-hotel-booking'
											),
											rates.length
										) }
									</span>
								</div>
							) : (
								<div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-border bg-muted/60 px-4 py-2.5">
									<h3 className="m-0 text-sm font-bold text-heading">
										{ type.name }
									</h3>
									<p className="m-0 text-xs text-muted-foreground">
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
							) }
							{ rates.length ? (
								<ul
									className={
										compact
											? cn(
													'm-0 grid list-none gap-2 p-3',
													wide && 'sm:grid-cols-2'
											  )
											: 'm-0 list-none divide-y divide-border p-0'
									}
								>
									{ rates.map( ( rate ) => (
										<RateCard
											key={ rate.rate_plan_id }
											compact={ compact }
											rate={ rate }
											selected={
												choice?.room_type_id ===
													type.id &&
												choice?.rate_plan_id ===
													rate.rate_plan_id
											}
											onSelect={ ( picked ) =>
												onSelect( type, picked )
											}
										/>
									) ) }
								</ul>
							) : (
								<p className="m-0 px-4 py-3 text-sm text-muted-foreground">
									{ __(
										'No rate matches these dates.',
										'radius-hotel-booking'
									) }
								</p>
							) }
						</section>
					);
				} ) }
			</div>

			<OtherOptions other={ other } onDates={ onDates } />
		</div>
	);
}

/**
 * "Other stay options": rates that would fit different dates, each with a
 * switch to those dates (behind a toggle).
 *
 * @param {Object}   props         Props.
 * @param {Object[]} props.other   The search's `other_options`.
 * @param {Function} props.onDates Called with `{ departure }` to switch dates.
 * @return {JSX.Element|null} Section.
 */
export function OtherOptions( { other, onDates } ) {
	const [ showOther, setShowOther ] = useState( false );
	if ( ! other?.length ) {
		return null;
	}
	return (
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
					<ChevronDown className="h-4 w-4" aria-hidden="true" />
				) }
				{ sprintf(
					/* translators: %d: number of other stay options. */
					__( 'Other stay options (%d)', 'radius-hotel-booking' ),
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
	);
}
