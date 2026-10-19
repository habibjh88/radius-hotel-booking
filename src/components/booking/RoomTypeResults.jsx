/**
 * The website's search results (M04): one card per room type — photo,
 * description, facts and amenities — with its rates listed inside it, each
 * with its times, live price (the regular price struck through when it is
 * lower), rooms left and Select. Then the stays that
 * would fit other dates.
 *
 * The desk keeps its compact RateList; this is the guest's face of the same
 * search result.
 */
import { useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AlertTriangle,
	BedDouble,
	CalendarDays,
	Check,
	ChevronDown,
	Clock,
	ExternalLink,
	Info,
	Loader2,
	Maximize2,
	Users,
} from 'lucide-react';

import Money from '@/components/common/Money';
import { Button } from '@/components/ui/button';
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { regularPrice, windowLabel } from './rates';

// Rates shown before "Show all rates", and amenities before "+N".
const FIRST_RATES = 4;
const FIRST_AMENITIES = 5;
// From this many rooms left, the count turns into a warning.
const FEW_LEFT = 3;

/**
 * What to tell a guest about a rate that does not fit their dates: a short
 * label and the note behind the info icon. A rate sold one at a time (not
 * "for several days or nights") covers one day or one night.
 *
 * @param {Object} rate The rate (`multi_unit`, `window`, `reasons`).
 * @return {{label: string, note: string}} Texts.
 */
function singleStay( rate ) {
	if ( rate.multi_unit ) {
		const message =
			rate.reasons?.[ 0 ]?.message ||
			__(
				'This rate does not match the dates chosen.',
				'radius-hotel-booking'
			);
		return { label: message, note: message };
	}
	return rate.window?.crosses_midnight
		? {
				label: __( 'One night only', 'radius-hotel-booking' ),
				note: __(
					'This package is made for a one-night booking. To book it, choose a departure the day after your arrival.',
					'radius-hotel-booking'
				),
		  }
		: {
				label: __( 'One-day booking only', 'radius-hotel-booking' ),
				note: __(
					'This package is made for a one-day booking. To book it, choose the same day for arrival and departure.',
					'radius-hotel-booking'
				),
		  };
}

/**
 * Why a rate cannot be booked for these dates, behind an info icon: opens on
 * hover (mouse) and on tap or click (touch, keyboard).
 *
 * @param {Object} props      Props.
 * @param {string} props.text The explanation.
 * @return {JSX.Element} Icon and its note.
 */
function RateInfo( { text } ) {
	const [ open, setOpen ] = useState( false );
	return (
		<Popover open={ open } onOpenChange={ setOpen }>
			<PopoverTrigger asChild>
				<button
					type="button"
					className="inline-flex h-6 w-6 items-center justify-center rounded-full border-0 bg-transparent p-0 text-muted-foreground shadow-none hover:bg-primary-softer hover:text-primary focus:bg-primary-softer focus:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
					aria-label={ __(
						'Why can I not book this?',
						'radius-hotel-booking'
					) }
					onMouseEnter={ () => setOpen( true ) }
					onMouseLeave={ () => setOpen( false ) }
				>
					<Info className="h-4 w-4" aria-hidden="true" />
				</button>
			</PopoverTrigger>
			<PopoverContent
				side="top"
				align="start"
				className="w-72 p-3 text-sm leading-relaxed text-foreground"
				onOpenAutoFocus={ ( event ) => event.preventDefault() }
			>
				{ text }
			</PopoverContent>
		</Popover>
	);
}

/**
 * One rate inside a room type card.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.rate     The rate.
 * @param {boolean}  props.selected This rate is the current choice.
 * @param {Function} props.onSelect Called when chosen.
 * @return {JSX.Element} Row.
 */
function RateRow( { rate, selected, onSelect } ) {
	const available = rate.available;
	const few = available && rate.free_count <= FEW_LEFT;
	const regular = available ? regularPrice( rate.price ) : null;
	const saved = regular
		? Math.round( ( 1 - Number( rate.price.total ) / regular ) * 100 )
		: 0;

	return (
		<li
			className={ cn(
				'flex flex-col gap-3 px-4 py-3.5 transition-colors sm:flex-row sm:items-center sm:gap-4 sm:px-5',
				selected && 'bg-primary-softer',
				! available && 'opacity-60'
			) }
		>
			<div className="min-w-0 flex-1 space-y-1">
				<p className="m-0 flex flex-wrap items-center gap-2 text-sm font-semibold text-heading">
					{ rate.name }
					{ rate.oneDay ? (
						<RateInfo text={ singleStay( rate ).note } />
					) : null }
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
					<p className="m-0 flex items-center gap-1.5 text-sm text-muted-foreground">
						<Clock
							className="h-3.5 w-3.5 shrink-0"
							aria-hidden="true"
						/>
						{ windowLabel( rate.window ) }
					</p>
				) : null }
				{ ! available ? (
					<p className="m-0 text-xs text-muted-foreground">
						{ rate.oneDay
							? singleStay( rate ).label
							: rate.reasons?.[ 0 ]?.message ||
							  __( 'Unavailable', 'radius-hotel-booking' ) }
					</p>
				) : null }
			</div>

			{ available ? (
				<div className="flex items-center justify-between gap-4 sm:justify-end">
					<div className="text-left sm:text-right">
						<p className="m-0 flex flex-wrap items-baseline gap-x-2 leading-tight sm:justify-end">
							{ regular ? (
								<del className="text-sm font-normal text-muted-foreground">
									<span className="sr-only">
										{ __(
											'Regular price:',
											'radius-hotel-booking'
										) }{ ' ' }
									</span>
									<Money value={ regular } />
								</del>
							) : null }
							<span className="text-lg font-bold text-heading">
								{ regular ? (
									<span className="sr-only">
										{ __( 'Now:', 'radius-hotel-booking' ) }{ ' ' }
									</span>
								) : null }
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
						<p
							className={ cn(
								'm-0 text-xs',
								few
									? 'font-semibold text-warning'
									: 'text-muted-foreground'
							) }
						>
							{ few
								? sprintf(
										/* translators: %d: rooms left at this rate. */
										_n(
											'Only %d room left',
											'Only %d rooms left',
											rate.free_count,
											'radius-hotel-booking'
										),
										rate.free_count
								  )
								: sprintf(
										/* translators: %d: rooms free at this rate. */
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
					<Button
						type="button"
						className="h-11 min-w-[7.5rem]"
						variant={ selected ? 'default' : 'outline' }
						aria-pressed={ selected }
						onClick={ () => onSelect( rate ) }
					>
						{ selected ? <Check aria-hidden="true" /> : null }
						{ selected
							? __( 'Selected', 'radius-hotel-booking' )
							: __( 'Select', 'radius-hotel-booking' ) }
					</Button>
				</div>
			) : null }
		</li>
	);
}

/**
 * A room type and its rates.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.type     Room type from the search.
 * @param {Object[]} props.other    This type's rates that do not fit the dates (shown greyed out).
 * @param {Object}   props.choice   `{ room_type_id, rate_plan_id }` or null.
 * @param {Function} props.onSelect Called with `( type, rate )`.
 * @return {JSX.Element} Card.
 */
function RoomTypeCard( { type, other = [], choice, onSelect } ) {
	const [ allRates, setAllRates ] = useState( false );
	const details = type.details || {};
	// Bookable rates first, cheapest first; the rest after, as sent; last,
	// the rates made for other dates (a day rate on a stay of several nights).
	const rates = [
		...( type.rates || [] ),
		...other.map( ( rate ) => ( {
			...rate,
			available: false,
			oneDay: true,
		} ) ),
	].sort( ( a, b ) => {
		if ( Boolean( a.oneDay ) !== Boolean( b.oneDay ) ) {
			return a.oneDay ? 1 : -1;
		}
		if ( a.available !== b.available ) {
			return a.available ? -1 : 1;
		}
		return a.available
			? Number( a.price?.total || 0 ) - Number( b.price?.total || 0 )
			: 0;
	} );
	const open = rates.filter( ( rate ) => rate.available );
	const from = open.length
		? Math.min(
				...open.map( ( rate ) => Number( rate.price?.total || 0 ) )
		  )
		: null;
	const free = open.reduce(
		( most, rate ) => Math.max( most, rate.free_count || 0 ),
		0
	);
	const selectedIndex = rates.findIndex(
		( rate ) =>
			choice?.room_type_id === type.id &&
			choice?.rate_plan_id === rate.rate_plan_id
	);
	// The chosen rate stays in view even when it is past the first few.
	const shown =
		allRates || selectedIndex >= FIRST_RATES
			? rates
			: rates.slice( 0, FIRST_RATES );
	const amenities = details.amenities || [];

	const guests = [
		sprintf(
			/* translators: %d: most adults. */
			_n(
				'Up to %d adult',
				'Up to %d adults',
				type.max_adults,
				'radius-hotel-booking'
			),
			type.max_adults
		),
		type.max_children
			? sprintf(
					/* translators: %d: most children. */
					_n(
						'%d child',
						'%d children',
						type.max_children,
						'radius-hotel-booking'
					),
					type.max_children
			  )
			: null,
	]
		.filter( Boolean )
		.join( ' · ' );

	return (
		<article className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
			<div className="flex flex-col sm:flex-row">
				<div className="relative aspect-[16/10] shrink-0 bg-muted sm:aspect-auto sm:min-h-[12rem] sm:w-56">
					{ details.image ? (
						<img
							src={ details.image }
							alt=""
							loading="lazy"
							className="absolute inset-0 m-0 h-full w-full object-cover"
						/>
					) : (
						<div className="absolute inset-0 flex items-center justify-center text-muted-foreground">
							<BedDouble
								className="h-10 w-10"
								aria-hidden="true"
							/>
						</div>
					) }
					{ free ? (
						<span className="absolute left-3 top-3 rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-heading shadow-sm">
							{ sprintf(
								/* translators: %d: rooms available. */
								_n(
									'%d room available',
									'%d rooms available',
									free,
									'radius-hotel-booking'
								),
								free
							) }
						</span>
					) : null }
				</div>

				<div className="flex min-w-0 flex-1 flex-col gap-3 p-4 sm:p-5">
					<div className="flex flex-wrap items-start justify-between gap-3">
						<div className="min-w-0">
							<h3 className="m-0 text-lg font-bold leading-snug text-heading">
								{ type.name }
							</h3>
							{ details.url ? (
								<a
									href={ details.url }
									target="_blank"
									rel="noopener noreferrer"
									className="inline-flex items-center gap-1 text-xs font-semibold text-primary no-underline hover:underline"
								>
									{ __(
										'Room details',
										'radius-hotel-booking'
									) }
									<ExternalLink
										className="h-3 w-3"
										aria-hidden="true"
									/>
									<span className="sr-only">
										{ __(
											'(opens in a new tab)',
											'radius-hotel-booking'
										) }
									</span>
								</a>
							) : null }
						</div>
						{ null !== from ? (
							<div className="text-right">
								<span className="block text-xs text-muted-foreground">
									{ __( 'From', 'radius-hotel-booking' ) }
								</span>
								<span className="block text-xl font-bold leading-tight text-heading">
									<Money value={ from } />
								</span>
							</div>
						) : null }
					</div>

					{ details.short_description ? (
						<p className="m-0 line-clamp-2 text-sm text-muted-foreground">
							{ details.short_description }
						</p>
					) : null }

					<ul className="m-0 flex list-none flex-wrap gap-x-4 gap-y-1.5 p-0 text-sm text-foreground">
						<li className="inline-flex items-center gap-1.5">
							<Users
								className="h-4 w-4 text-muted-foreground"
								aria-hidden="true"
							/>
							{ guests }
						</li>
						{ details.bed_info ? (
							<li className="inline-flex items-center gap-1.5">
								<BedDouble
									className="h-4 w-4 text-muted-foreground"
									aria-hidden="true"
								/>
								{ details.bed_info }
							</li>
						) : null }
						{ details.size_m2 ? (
							<li className="inline-flex items-center gap-1.5">
								<Maximize2
									className="h-4 w-4 text-muted-foreground"
									aria-hidden="true"
								/>
								{ sprintf(
									/* translators: %s: room size in square metres. */
									__( '%s m²', 'radius-hotel-booking' ),
									details.size_m2
								) }
							</li>
						) : null }
					</ul>

					{ amenities.length ? (
						<ul className="m-0 flex list-none flex-wrap gap-1.5 p-0">
							{ amenities
								.slice( 0, FIRST_AMENITIES )
								.map( ( amenity ) => (
									<li
										key={ amenity }
										className="rounded-full border border-border bg-muted/50 px-2.5 py-0.5 text-xs text-foreground"
									>
										{ amenity }
									</li>
								) ) }
							{ amenities.length > FIRST_AMENITIES ? (
								<li className="px-1 py-0.5 text-xs font-semibold text-muted-foreground">
									{ sprintf(
										/* translators: %d: number of other amenities. */
										__(
											'+%d more',
											'radius-hotel-booking'
										),
										amenities.length - FIRST_AMENITIES
									) }
								</li>
							) : null }
						</ul>
					) : null }
				</div>
			</div>

			<div className="border-t border-border">
				<p className="m-0 bg-muted/40 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:px-5">
					{ __( 'Choose your stay', 'radius-hotel-booking' ) }
				</p>
				{ rates.length ? (
					<ul className="m-0 list-none divide-y divide-border p-0">
						{ shown.map( ( rate ) => (
							<RateRow
								key={ rate.rate_plan_id }
								rate={ rate }
								selected={
									choice?.room_type_id === type.id &&
									choice?.rate_plan_id === rate.rate_plan_id
								}
								onSelect={ ( picked ) =>
									onSelect( type, picked )
								}
							/>
						) ) }
					</ul>
				) : (
					<p className="m-0 px-4 py-3 text-sm text-muted-foreground sm:px-5">
						{ __(
							'No rate matches these dates.',
							'radius-hotel-booking'
						) }
					</p>
				) }
				{ shown.length < rates.length ? (
					<button
						type="button"
						className="flex min-h-[44px] w-full items-center justify-center gap-1.5 border-0 border-t border-border bg-transparent text-sm font-semibold normal-case text-primary hover:bg-primary-softer hover:text-primary focus:bg-primary-softer focus:text-primary"
						onClick={ () => setAllRates( true ) }
					>
						<ChevronDown className="h-4 w-4" aria-hidden="true" />
						{ sprintf(
							/* translators: %d: number of rates not shown yet. */
							_n(
								'Show %d more rate',
								'Show %d more rates',
								rates.length - shown.length,
								'radius-hotel-booking'
							),
							rates.length - shown.length
						) }
					</button>
				) : null }
			</div>
		</article>
	);
}

/**
 * @param {Object}   props              Props.
 * @param {Object}   props.availability The React Query result of the search.
 * @param {boolean}  props.needsDates   No dates picked yet: the prices are only a preview.
 * @param {Object}   props.choice       `{ room_type_id, rate_plan_id }` or null.
 * @param {Function} props.onSelect     Called with `( type, rate )`.
 * @return {JSX.Element} Results.
 */
export default function RoomTypeResults( {
	availability,
	needsDates = false,
	choice,
	onSelect,
} ) {
	const types = availability.data?.room_types || [];
	const updating = availability.isFetching && ! availability.isPending;

	let body;
	if ( availability.isPending ) {
		body = (
			<div className="space-y-4" role="status">
				{ [ 0, 1 ].map( ( key ) => (
					<div
						key={ key }
						className="flex flex-col overflow-hidden rounded-2xl border border-border sm:flex-row"
					>
						<Skeleton className="aspect-[16/10] rounded-none sm:aspect-auto sm:h-48 sm:w-56" />
						<div className="flex-1 space-y-3 p-5">
							<Skeleton className="h-5 w-1/2" />
							<Skeleton className="h-4 w-full" />
							<Skeleton className="h-4 w-2/3" />
						</div>
					</div>
				) ) }
				<span className="sr-only">
					{ __( 'Loading rooms…', 'radius-hotel-booking' ) }
				</span>
			</div>
		);
	} else if ( availability.error ) {
		body = (
			<div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-destructive/40 bg-destructive-soft p-4">
				<p className="m-0 flex items-center gap-2 text-sm text-destructive">
					<AlertTriangle className="h-4 w-4" aria-hidden="true" />
					{ availability.error.message ||
						__(
							'The rooms could not be loaded.',
							'radius-hotel-booking'
						) }
				</p>
				<Button
					type="button"
					variant="outline"
					onClick={ () => availability.refetch() }
				>
					{ __( 'Try again', 'radius-hotel-booking' ) }
				</Button>
			</div>
		);
	} else if ( ! types.length ) {
		body = (
			<div className="rounded-2xl border border-dashed border-border px-6 py-10 text-center">
				<BedDouble
					className="mx-auto h-8 w-8 text-muted-foreground"
					aria-hidden="true"
				/>
				<p className="m-0 mt-3 font-semibold text-heading">
					{ __(
						'No room is free for these dates',
						'radius-hotel-booking'
					) }
				</p>
				<p className="m-0 mt-1 text-sm text-muted-foreground">
					{ __(
						'Try other dates or fewer guests.',
						'radius-hotel-booking'
					) }
				</p>
			</div>
		);
	} else {
		body = (
			<div
				className={ cn(
					'space-y-5 transition-opacity',
					updating && 'pointer-events-none opacity-60'
				) }
				aria-busy={ updating }
			>
				{ types.map( ( type ) => (
					<RoomTypeCard
						key={ type.id }
						type={ type }
						other={ (
							availability.data?.other_options || []
						).filter( ( rate ) => rate.room_type_id === type.id ) }
						choice={ choice }
						onSelect={ onSelect }
					/>
				) ) }
			</div>
		);
	}

	return (
		<section className="space-y-4">
			<div className="flex flex-wrap items-end justify-between gap-2">
				<div>
					<h2 className="m-0 text-xl font-bold text-heading">
						{ __( 'Available rooms', 'radius-hotel-booking' ) }
					</h2>
					{ needsDates && types.length ? (
						<p className="m-0 flex items-center gap-1.5 text-sm text-muted-foreground">
							<CalendarDays
								className="h-4 w-4 shrink-0 text-primary"
								aria-hidden="true"
							/>
							{ __(
								'Choose your dates to see the prices and free rooms for your stay.',
								'radius-hotel-booking'
							) }
						</p>
					) : types.length && ! availability.isPending ? (
						<p className="m-0 text-sm text-muted-foreground">
							{ sprintf(
								/* translators: %d: number of room types. */
								_n(
									'%d room type matches your search',
									'%d room types match your search',
									types.length,
									'radius-hotel-booking'
								),
								types.length
							) }
						</p>
					) : null }
				</div>
				{ updating ? (
					<span
						className="inline-flex items-center gap-1.5 text-sm text-muted-foreground"
						role="status"
					>
						<Loader2
							className="h-4 w-4 animate-spin"
							aria-hidden="true"
						/>
						{ __( 'Updating prices…', 'radius-hotel-booking' ) }
					</span>
				) : null }
			</div>
			{ body }
		</section>
	);
}
