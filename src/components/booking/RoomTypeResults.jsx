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
import { lazy, Suspense, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AlertTriangle,
	BedDouble,
	CalendarDays,
	Check,
	ChevronDown,
	Clock,
	Loader2,
} from 'lucide-react';

import Money from '@/components/common/Money';
import LucideIcon from '@/components/icons/LucideIcon';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import {
	RateInfo,
	RatePrice,
	RoomDetailsLink,
	RoomFacts,
	RoomPhoto,
	guestsLabel,
	singleStay,
} from './RoomCardParts';
import { windowLabel } from './rates';

// `room_details="popup"` only: loaded on first open.
const RoomDetailsDialog = lazy( () => import( './RoomDetailsDialog' ) );

// Amenities before "+N".
const FIRST_AMENITIES = 5;
// The split card lists amenities down its side: a few more fit.
const SIDE_AMENITIES = 6;

/**
 * What a rate's price covers: "Total for 8.5 hours", "Total for 2 days".
 *
 * @param {Object} rate The rate (`window.minutes`: the whole stay).
 * @return {string} Label.
 */
function totalFor( rate ) {
	const minutes = Number( rate.window?.minutes || 0 );
	if ( ! minutes ) {
		return __( 'Total price', 'radius-hotel-booking' );
	}
	if ( minutes < 1440 ) {
		const hours = Math.round( minutes / 6 ) / 10;
		return sprintf(
			/* translators: %s: number of hours, e.g. 8.5. */
			_n(
				'Total for %s hour',
				'Total for %s hours',
				Math.ceil( hours ),
				'radius-hotel-booking'
			),
			String( hours )
		);
	}
	const days = Math.round( minutes / 1440 );
	return sprintf(
		/* translators: %d: number of 24-hour days. */
		_n(
			'Total for %d day',
			'Total for %d days',
			days,
			'radius-hotel-booking'
		),
		days
	);
}

/**
 * The rate's name, badges, times and (when it cannot be booked) why.
 *
 * @param {Object}   props       Props.
 * @param {Object}   props.rate  The rate.
 * @param {boolean}  props.large Bigger name (the split card).
 * @param {string[]} props.added Rooms of this rate already in the booking (numbers).
 * @return {JSX.Element} Summary.
 */
function RateSummary( { rate, large = false, added = [] } ) {
	return (
		<div className="min-w-0 flex-1 space-y-1">
			<p
				className={ cn(
					'm-0 flex flex-wrap items-center gap-2 font-semibold text-heading',
					large ? 'text-base' : 'text-sm'
				) }
			>
				{ rate.name }
				{ rate.oneDay ? (
					<RateInfo text={ singleStay( rate ).note } />
				) : null }
				{ rate.checkin ? (
					<span className="rounded-full bg-primary-soft px-2 py-0.5 text-[11px] font-semibold text-primary">
						{ __( 'Flexible check-in', 'radius-hotel-booking' ) }
					</span>
				) : null }
				{ added.length ? (
					<span className="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-[11px] font-semibold text-success">
						<Check
							className="h-3 w-3"
							strokeWidth={ 3 }
							aria-hidden="true"
						/>
						{ sprintf(
							/* translators: %s: room numbers, e.g. "A12, A13". */
							_n(
								'Room %s added',
								'Rooms %s added',
								added.length,
								'radius-hotel-booking'
							),
							added.join( ', ' )
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
			{ ! rate.available ? (
				<p className="m-0 text-xs text-muted-foreground">
					{ rate.oneDay
						? singleStay( rate ).label
						: rate.reasons?.[ 0 ]?.message ||
						  __( 'Unavailable', 'radius-hotel-booking' ) }
				</p>
			) : null }
		</div>
	);
}

/**
 * Select / Selected.
 *
 * @param {Object}   props          Props.
 * @param {boolean}  props.selected The current choice.
 * @param {Function} props.onClick  Choose it.
 * @param {string}   props.className Extra classes.
 * @param {boolean}  props.added    Rooms of this rate are already in the booking.
 * @return {JSX.Element} Button.
 */
function SelectButton( { selected, onClick, className, added = false } ) {
	let label = __( 'Select', 'radius-hotel-booking' );
	if ( selected ) {
		label = __( 'Selected', 'radius-hotel-booking' );
	} else if ( added ) {
		label = __( 'Add more', 'radius-hotel-booking' );
	}
	return (
		<Button
			type="button"
			className={ cn( 'h-11 min-w-[7.5rem]', className ) }
			variant={ selected ? 'default' : 'outline' }
			aria-pressed={ selected }
			onClick={ onClick }
		>
			{ selected ? <Check aria-hidden="true" /> : null }
			{ label }
		</Button>
	);
}

/**
 * One rate as its own card (the split room type card): name and times on
 * the left; what the price covers, the price and Select on the right.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.rate     The rate.
 * @param {boolean}  props.selected This rate is the current choice.
 * @param {Function} props.onSelect Called when chosen.
 * @param {string[]} props.added    Rooms of this rate already in the booking.
 * @return {JSX.Element} Card.
 */
function RateCard( { rate, selected, onSelect, added = [] } ) {
	const available = rate.available;
	return (
		<li
			className={ cn(
				'flex flex-col gap-4 rounded-xl border bg-card p-4 transition-all duration-200 sm:flex-row sm:items-center sm:justify-between sm:p-5',
				selected && 'border-primary shadow-md ring-2 ring-primary/15',
				! selected &&
					added.length &&
					'border-success/50 bg-success-soft/40',
				! selected && ! added.length && 'border-border',
				available &&
					! selected &&
					'hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md',
				! available && 'bg-muted/30 opacity-70'
			) }
		>
			<RateSummary rate={ rate } large added={ added } />
			{ available ? (
				<div className="flex shrink-0 flex-col gap-3 border-t border-border pt-3 sm:flex-row sm:items-center sm:gap-5 sm:border-0 sm:pt-0 sm:text-right">
					<div className="space-y-0.5">
						<span className="block text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
							{ totalFor( rate ) }
						</span>
						<RatePrice rate={ rate } />
					</div>
					<SelectButton
						selected={ selected }
						added={ added.length > 0 }
						onClick={ () => onSelect( rate ) }
						className="w-full sm:w-auto"
					/>
				</div>
			) : null }
		</li>
	);
}

/**
 * One rate inside a room type card.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.rate     The rate.
 * @param {boolean}  props.selected This rate is the current choice.
 * @param {Function} props.onSelect Called when chosen.
 * @param {string[]} props.added    Rooms of this rate already in the booking.
 * @return {JSX.Element} Row.
 */
function RateRow( { rate, selected, onSelect, added = [] } ) {
	const available = rate.available;

	return (
		<li
			className={ cn(
				'flex flex-col gap-3 px-4 py-3.5 transition-colors sm:flex-row sm:items-center sm:gap-4 sm:px-5',
				selected && 'bg-primary-softer',
				! selected && added.length && 'bg-success-soft/40',
				! available && 'opacity-60'
			) }
		>
			<RateSummary rate={ rate } added={ added } />

			{ available ? (
				<div className="flex items-center justify-between gap-4 sm:justify-end">
					<RatePrice rate={ rate } />
					<SelectButton
						selected={ selected }
						added={ added.length > 0 }
						onClick={ () => onSelect( rate ) }
					/>
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
 * @param {string}   props.roomDetails `page` (link to its page) or `popup`.
 * @param {string}   props.card     `classic` (rates as rows under the type) or
 *                                  `split` (photo and facts beside the rates, each a card).
 * @param {number}   props.firstRates Rates before "Show more rates" (0: all, no button).
 * @return {JSX.Element} Card.
 */
function RoomTypeCard( {
	type,
	other = [],
	choice,
	onSelect,
	roomDetails = 'page',
	card = 'classic',
	firstRates = 4,
	booked = {},
} ) {
	const [ allRates, setAllRates ] = useState( false );
	// Rooms of each of this type's rates already in the booking (numbers).
	const addedTo = ( rate ) => booked[ rate.rate_plan_id ] || [];
	const addedCount = Object.values( booked ).reduce(
		( sum, rooms ) => sum + rooms.length,
		0
	);
	const [ showDetails, setShowDetails ] = useState( false );
	const details = type.details || {};
	// The order set on the room type's Rates tab (as sent), with bookable rates
	// first; then the unavailable ones; last, the rates made for other dates
	// (a day rate on a stay of several nights). The sort is stable.
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
		if ( Boolean( a.available ) !== Boolean( b.available ) ) {
			return a.available ? -1 : 1;
		}
		return 0;
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
	// …and so do the rates rooms were already added from.
	const lastAdded = rates.reduce(
		( last, rate, index ) =>
			( booked[ rate.rate_plan_id ] || [] ).length ? index : last,
		-1
	);
	const shown =
		allRates ||
		! firstRates ||
		selectedIndex >= firstRates ||
		lastAdded >= firstRates
			? rates
			: rates.slice( 0, firstRates );
	const amenities = details.amenities || [];
	// The type's highlighted amenities (still offered), else the first ones.
	const highlights = ( details.highlights || [] ).filter( ( name ) =>
		amenities.includes( name )
	);
	const firstOf = ( count ) =>
		highlights.length ? highlights : amenities.slice( 0, count );
	const sideAmenities = firstOf( SIDE_AMENITIES );
	const cardAmenities = firstOf( FIRST_AMENITIES );
	// Name => Lucide icon, as on the room type page.
	const icons = details.amenity_icons || {};

	const guests = guestsLabel( type );
	const isSelected = ( rate ) =>
		choice?.room_type_id === type.id &&
		choice?.rate_plan_id === rate.rate_plan_id;
	const dialog = showDetails ? (
		<Suspense fallback={ null }>
			<RoomDetailsDialog
				type={ type }
				guests={ guests }
				onClose={ () => setShowDetails( false ) }
			/>
		</Suspense>
	) : null;
	const fromPrice =
		null !== from ? (
			<div className="shrink-0 whitespace-nowrap text-right">
				<span className="block text-xs text-muted-foreground">
					{ __( 'From', 'radius-hotel-booking' ) }
				</span>
				<span className="block text-xl font-bold leading-tight text-heading">
					<Money value={ from } />
				</span>
			</div>
		) : null;
	const moreRates =
		shown.length < rates.length
			? sprintf(
					/* translators: %d: number of rates not shown yet. */
					_n(
						'Show %d more rate',
						'Show %d more rates',
						rates.length - shown.length,
						'radius-hotel-booking'
					),
					rates.length - shown.length
			  )
			: '';
	const noRate = __( 'No rate matches these dates.', 'radius-hotel-booking' );
	// "2 rooms in your booking", next to the type's name.
	const inBooking = addedCount ? (
		<span className="inline-flex items-center gap-1 rounded-full bg-success-soft px-2.5 py-0.5 align-middle text-xs font-semibold text-success">
			<Check
				className="h-3.5 w-3.5"
				strokeWidth={ 3 }
				aria-hidden="true"
			/>
			{ sprintf(
				/* translators: %d: rooms of this type in the booking. */
				_n(
					'%d room in your booking',
					'%d rooms in your booking',
					addedCount,
					'radius-hotel-booking'
				),
				addedCount
			) }
		</span>
	) : null;

	if ( 'split' === card ) {
		return (
			<article className="rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-6">
				{ /* The price keeps its place on the right; a long name or
				   description wraps beside it. */ }
				<div className="mb-5 flex items-start justify-between gap-4 sm:gap-6">
					<div className="min-w-0 flex-1 space-y-1">
						<h3 className="m-0 flex flex-wrap items-center gap-x-3 gap-y-1 text-xl font-bold leading-snug text-heading sm:text-2xl">
							{ type.name }
							{ inBooking }
						</h3>
						{ details.short_description ? (
							<p className="m-0 line-clamp-2 text-sm text-muted-foreground">
								{ details.short_description }
							</p>
						) : null }
					</div>
					{ fromPrice }
				</div>

				<div className="grid gap-6 lg:grid-cols-[minmax(0,19rem)_minmax(0,1fr)]">
					{ /* Stays in view while the guest scrolls a long list of rates.
					   Below a theme's sticky header: `--rtbp-sticky-top` (6rem by
					   default; 1rem inside the booking popup). */ }
					<aside className="min-w-0 space-y-4 lg:sticky lg:top-[var(--rtbp-sticky-top,6rem)] lg:self-start">
						<RoomPhoto
							type={ type }
							free={ free }
							className="aspect-[4/3] rounded-xl"
						/>
						<RoomFacts type={ type } guests={ guests } />
						{ amenities.length ? (
							<ul className="m-0 grid list-none grid-cols-2 gap-x-3 gap-y-2 border-t border-border p-0 pt-4">
								{ sideAmenities.map( ( amenity ) => (
									<li
										key={ amenity }
										className="flex min-w-0 items-center gap-2 text-sm text-foreground"
									>
										<LucideIcon
											name={ icons[ amenity ] }
											className="h-4 w-4 text-primary"
										/>
										<span className="truncate">
											{ amenity }
										</span>
									</li>
								) ) }
								{ amenities.length > sideAmenities.length ? (
									<li className="col-span-2 text-xs font-semibold text-muted-foreground">
										{ sprintf(
											/* translators: %d: number of other amenities. */
											__(
												'+%d more',
												'radius-hotel-booking'
											),
											amenities.length -
												sideAmenities.length
										) }
									</li>
								) : null }
							</ul>
						) : null }
						<RoomDetailsLink
							type={ type }
							roomDetails={ roomDetails }
							onOpen={ () => setShowDetails( true ) }
							className="text-sm"
						/>
					</aside>

					<div className="min-w-0 space-y-3">
						<p className="m-0 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
							{ __( 'Choose your stay', 'radius-hotel-booking' ) }
						</p>
						{ rates.length ? (
							<ul className="m-0 list-none space-y-3 p-0">
								{ shown.map( ( rate ) => (
									<RateCard
										key={ rate.rate_plan_id }
										rate={ rate }
										selected={ isSelected( rate ) }
										added={ addedTo( rate ) }
										added={ addedTo( rate ) }
										onSelect={ ( picked ) =>
											onSelect( type, picked )
										}
									/>
								) ) }
							</ul>
						) : (
							<p className="m-0 rounded-xl border border-dashed border-border p-4 text-sm text-muted-foreground">
								{ noRate }
							</p>
						) }
						{ moreRates ? (
							<button
								type="button"
								className="flex min-h-[44px] w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-border bg-transparent text-sm font-semibold normal-case text-primary hover:border-primary/40 hover:bg-primary-softer hover:text-primary focus:bg-primary-softer focus:text-primary"
								onClick={ () => setAllRates( true ) }
							>
								<ChevronDown
									className="h-4 w-4"
									aria-hidden="true"
								/>
								{ moreRates }
							</button>
						) : null }
					</div>
				</div>
				{ dialog }
			</article>
		);
	}

	return (
		<article className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
			<div className="flex flex-col sm:flex-row">
				<RoomPhoto
					type={ type }
					free={ free }
					className="aspect-[16/10] sm:aspect-auto sm:min-h-[12rem] sm:w-56"
				/>

				<div className="flex min-w-0 flex-1 flex-col gap-3 p-4 sm:p-5">
					<div className="flex items-start justify-between gap-4">
						<div className="min-w-0 flex-1">
							<h3 className="m-0 flex flex-wrap items-center gap-x-3 gap-y-1 text-lg font-bold leading-snug text-heading">
								{ type.name }
								{ inBooking }
							</h3>
							<RoomDetailsLink
								type={ type }
								roomDetails={ roomDetails }
								onOpen={ () => setShowDetails( true ) }
							/>
						</div>
						{ fromPrice }
					</div>

					{ details.short_description ? (
						<p className="m-0 line-clamp-2 text-sm text-muted-foreground">
							{ details.short_description }
						</p>
					) : null }

					<RoomFacts type={ type } guests={ guests } />

					{ amenities.length ? (
						<ul className="m-0 flex list-none flex-wrap gap-1.5 p-0">
							{ cardAmenities.map( ( amenity ) => (
								<li
									key={ amenity }
									className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/50 px-2.5 py-0.5 text-xs text-foreground"
								>
									<LucideIcon
										name={ icons[ amenity ] }
										className="h-3.5 w-3.5 text-primary"
									/>
									{ amenity }
								</li>
							) ) }
							{ amenities.length > cardAmenities.length ? (
								<li className="px-1 py-0.5 text-xs font-semibold text-muted-foreground">
									{ sprintf(
										/* translators: %d: number of other amenities. */
										__(
											'+%d more',
											'radius-hotel-booking'
										),
										amenities.length - cardAmenities.length
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
								selected={ isSelected( rate ) }
								onSelect={ ( picked ) =>
									onSelect( type, picked )
								}
							/>
						) ) }
					</ul>
				) : (
					<p className="m-0 px-4 py-3 text-sm text-muted-foreground sm:px-5">
						{ noRate }
					</p>
				) }
				{ moreRates ? (
					<button
						type="button"
						className="flex min-h-[44px] w-full items-center justify-center gap-1.5 border-0 border-t border-border bg-transparent text-sm font-semibold normal-case text-primary hover:bg-primary-softer hover:text-primary focus:bg-primary-softer focus:text-primary"
						onClick={ () => setAllRates( true ) }
					>
						<ChevronDown className="h-4 w-4" aria-hidden="true" />
						{ moreRates }
					</button>
				) : null }
			</div>
			{ dialog }
		</article>
	);
}

/**
 * @param {Object}   props              Props.
 * @param {Object}   props.availability The React Query result of the search.
 * @param {boolean}  props.needsDates   No dates picked yet: the prices are only a preview.
 * @param {Object}   props.choice       `{ room_type_id, rate_plan_id }` or null.
 * @param {Function} props.onSelect     Called with `( type, rate )`.
 * @param {string}   props.roomDetails  What "Room details" opens: `page` or `popup`.
 * @param {string}   props.roomCard     Room type card: `classic` or `split`.
 * @param {number}   props.ratesShown   Rates per room type before "Show more rates" (0: all).
 * @param {Object[]} props.lines        The booking's rooms (`room_type_id`, `rate_plan_id`, `room_number`)
 *                                      for these dates: their rates are marked as added.
 * @return {JSX.Element} Results.
 */
export default function RoomTypeResults( {
	availability,
	needsDates = false,
	choice,
	onSelect,
	roomDetails = 'page',
	roomCard = 'classic',
	ratesShown = 4,
	lines = [],
} ) {
	// Type id => rate plan id => room numbers already in the booking.
	const booked = {};
	lines.forEach( ( line ) => {
		const byRate = ( booked[ line.room_type_id ] =
			booked[ line.room_type_id ] || {} );
		byRate[ line.rate_plan_id ] = [
			...( byRate[ line.rate_plan_id ] || [] ),
			line.room_number,
		];
	} );
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
						roomDetails={ roomDetails }
						card={ roomCard }
						firstRates={ ratesShown }
						booked={ booked[ type.id ] }
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
