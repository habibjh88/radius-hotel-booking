/**
 * Pieces both room type cards of the booking form share (RoomTypeResults:
 * the classic card and the split card, `[rtbp_booking room_card]`): the
 * photo that opens the gallery, the "Room details" link or button, the facts
 * (guests, beds, size), a rate's price and free rooms, and why a rate cannot
 * be booked.
 */
import { useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	BedDouble,
	ExternalLink,
	Images,
	Info,
	Maximize2,
	Users,
} from 'lucide-react';

import Money from '@/components/common/Money';
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from '@/components/ui/popover';
import { formatDate, formatMoney, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';

// From this many rooms left, the count turns into a warning.
export const FEW_LEFT = 3;

/**
 * What to tell a guest about a rate that does not fit their dates: a short
 * label and the note behind the info icon. A rate sold one at a time (not
 * "for several days or nights") covers one day or one night.
 *
 * @param {Object} rate The rate (`multi_unit`, `window`, `reasons`).
 * @return {{label: string, note: string}} Texts.
 */
export function singleStay( rate ) {
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
export function RateInfo( { text } ) {
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
 * "Up to 2 adults · 1 child".
 *
 * @param {Object} type Room type (`max_adults`, `max_children`).
 * @return {string} Label.
 */
export function guestsLabel( type ) {
	return [
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
}

/**
 * How long the stay is, for the breakdown's heading: "8.5 hours", "2 days".
 *
 * @param {Object} rate The rate (`window.minutes`: the whole stay).
 * @return {string} Label ('' when unknown).
 */
function stayLength( rate ) {
	const minutes = Number( rate.window?.minutes || 0 );
	if ( ! minutes ) {
		return '';
	}
	if ( minutes < 1440 ) {
		const hours = Math.round( minutes / 6 ) / 10;
		return sprintf(
			/* translators: %s: number of hours, e.g. 8.5. */
			_n(
				'%s hour',
				'%s hours',
				Math.ceil( hours ),
				'radius-hotel-booking'
			),
			String( hours )
		);
	}
	const days = Math.round( minutes / 1440 );
	return sprintf(
		/* translators: %d: number of 24-hour days. */
		_n( '%d day', '%d days', days, 'radius-hotel-booking' ),
		days
	);
}

/**
 * The ⓘ beside a rate's price and its popover: for each night or day, the
 * window (or the date, for several) with its regular price, then every
 * step that changed it (a sale price, a promotion…) with the price after
 * it and what it saved or added, then the total.
 *
 * @param {Object} props      Props.
 * @param {Object} props.rate An available rate (`price.steps`, `window`).
 * @return {JSX.Element|null} Trigger and popover.
 */
function StayBreakdown( { rate } ) {
	const units = rate.price?.steps || [];
	if ( ! units.length ) {
		return null;
	}
	const several = units.length > 1;
	const times = rate.window
		? sprintf(
				/* translators: 1: start time, 2: end time. */
				__( '%1$s – %2$s', 'radius-hotel-booking' ),
				formatTime( rate.window.start ),
				formatTime( rate.window.end )
		  )
		: '';
	const length = stayLength( rate );
	const row = 'm-0 flex items-baseline justify-between gap-4 px-4 py-2.5';

	return (
		<Popover>
			<PopoverTrigger asChild>
				<button
					type="button"
					className="inline-flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center self-center rounded-full border-0 bg-transparent p-0 text-muted-foreground hover:text-heading focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
					aria-label={ __(
						'Price breakdown',
						'radius-hotel-booking'
					) }
				>
					<Info className="h-4 w-4" aria-hidden="true" />
				</button>
			</PopoverTrigger>
			<PopoverContent
				align="end"
				className="w-72 max-w-[calc(100vw-2rem)] p-0 text-sm"
			>
				<p className={ cn( row, 'font-semibold text-heading' ) }>
					<span>
						{ __( 'Price breakdown', 'radius-hotel-booking' ) }
					</span>
					{ length ? <span>{ length }</span> : null }
				</p>
				<ul className="m-0 list-none divide-y divide-border border-y border-border p-0">
					{ units.map( ( unit, index ) =>
						unit.steps.map( ( step, at ) => {
							const delta =
								null === step.before
									? null
									: step.after - step.before;
							if ( null !== delta && Math.abs( delta ) < 0.005 ) {
								return null;
							}
							let label = step.label;
							if ( 'base' === step.step ) {
								label = several
									? formatDate( unit.date )
									: times || step.label;
							}
							return (
								<li
									key={ `${ index }-${ at }` }
									className={ cn( row, 'text-foreground' ) }
								>
									<span className="min-w-0">
										{ label }
										{ null === delta ? null : (
											<span
												className={ cn(
													'block text-xs',
													delta < 0
														? 'text-success'
														: 'text-muted-foreground'
												) }
											>
												{ delta < 0
													? sprintf(
															/* translators: %s: amount saved by this step, e.g. a sale price. */
															__(
																'You save %s',
																'radius-hotel-booking'
															),
															formatMoney(
																-delta
															)
													  )
													: sprintf(
															/* translators: %s: amount added by this step. */
															__(
																'+ %s',
																'radius-hotel-booking'
															),
															formatMoney( delta )
													  ) }
											</span>
										) }
									</span>
									{ /* The price after this step: a sale price reads as the new price, not the saving. */ }
									<span className="shrink-0 tabular-nums">
										<Money value={ step.after } />
									</span>
								</li>
							);
						} )
					) }
				</ul>
				<p className={ cn( row, 'font-semibold text-heading' ) }>
					<span>{ __( 'Total', 'radius-hotel-booking' ) }</span>
					<Money value={ rate.price.total } />
				</p>
			</PopoverContent>
		</Popover>
	);
}

/**
 * A rate's selling price and how many rooms are free at it. With
 * `breakdown`, an ⓘ beside the price opens how it is made
 * (`[rtbp_booking price_breakdown="yes"]`).
 *
 * @param {Object}  props             Props.
 * @param {Object}  props.rate        An available rate.
 * @param {string}  [props.align]     `right` on wide screens (the classic row) or `left`.
 * @param {boolean} [props.breakdown] Show the ⓘ and its breakdown.
 * @return {JSX.Element} Price.
 */
export function RatePrice( { rate, align = 'right', breakdown = false } ) {
	const few = rate.free_count <= FEW_LEFT;
	return (
		<div
			className={ cn(
				'text-left',
				'right' === align && 'sm:text-right'
			) }
		>
			<p
				className={ cn(
					'm-0 flex flex-wrap items-baseline gap-x-1.5 leading-tight',
					'right' === align && 'sm:justify-end'
				) }
			>
				<span className="text-lg font-bold text-heading">
					<Money value={ rate.price?.total } />
				</span>
				{ breakdown ? <StayBreakdown rate={ rate } /> : null }
			</p>
			<p
				className={ cn(
					'm-0 text-xs',
					few ? 'font-semibold text-warning' : 'text-muted-foreground'
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
	);
}

/**
 * The room type's photo: a click opens its gallery (src/gallery), with the
 * number of photos and of rooms free on it.
 *
 * @param {Object} props           Props.
 * @param {Object} props.type      Room type.
 * @param {number} props.free      Rooms free (0 hides the badge).
 * @param {string} props.className Size and shape of the frame.
 * @return {JSX.Element} Photo.
 */
export function RoomPhoto( { type, free, className } ) {
	const details = type.details || {};
	const photos = Array.isArray( details.gallery ) ? details.gallery : [];
	return (
		<div
			className={ cn(
				'group relative shrink-0 overflow-hidden bg-muted',
				className
			) }
			// The room type page's lightbox (src/gallery) opens on these.
			{ ...( photos.length
				? {
						'data-rtbp-gallery': JSON.stringify( photos ),
						'data-rtbp-gallery-title': type.name,
				  }
				: {} ) }
		>
			{ details.image && photos.length ? (
				<button
					type="button"
					className="absolute inset-0 m-0 block h-full w-full cursor-zoom-in border-0 bg-transparent p-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
					data-rtbp-gallery-index="0"
					aria-label={ sprintf(
						/* translators: %s: room type name. */
						__( 'View photos of %s', 'radius-hotel-booking' ),
						type.name
					) }
				>
					<img
						src={ details.image }
						alt=""
						loading="lazy"
						className="m-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
					/>
				</button>
			) : null }
			{ details.image && ! photos.length ? (
				<img
					src={ details.image }
					alt=""
					loading="lazy"
					className="absolute inset-0 m-0 h-full w-full object-cover"
				/>
			) : null }
			{ photos.length > 1 ? (
				<span
					className="rtbp-gallery-button pointer-events-none absolute bottom-2 right-2 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-heading shadow-sm"
					aria-hidden="true"
				>
					<Images className="h-3.5 w-3.5" />
					{ photos.length }
				</span>
			) : null }
			{ details.image ? null : (
				<div className="absolute inset-0 flex items-center justify-center text-muted-foreground">
					<BedDouble className="h-10 w-10" aria-hidden="true" />
				</div>
			) }
			{ free ? (
				<span className="pointer-events-none absolute left-3 top-3 rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-heading shadow-sm">
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
	);
}

/**
 * "Room details": a button opening the popup (`room_details="popup"`) or a
 * link to the room type's page in a new tab.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.type        Room type.
 * @param {string}   props.roomDetails `page` or `popup`.
 * @param {Function} props.onOpen      Opens the popup.
 * @param {string}   [props.className] Extra classes (size).
 * @return {JSX.Element|null} Link or button.
 */
export function RoomDetailsLink( { type, roomDetails, onOpen, className } ) {
	const details = type.details || {};
	return (
		<>
			{ 'popup' === roomDetails ? (
				<button
					type="button"
					className={ cn(
						'inline-flex cursor-pointer items-center gap-1 border-0 bg-transparent p-0 text-xs font-semibold normal-case tracking-normal text-primary no-underline shadow-none hover:bg-transparent hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
						className
					) }
					aria-haspopup="dialog"
					onClick={ onOpen }
				>
					{ __( 'Room details', 'radius-hotel-booking' ) }
					<Info className="h-3 w-3" aria-hidden="true" />
				</button>
			) : null }
			{ 'popup' !== roomDetails && details.url ? (
				<a
					href={ details.url }
					target="_blank"
					rel="noopener noreferrer"
					className={ cn(
						'inline-flex items-center gap-1 text-xs font-semibold text-primary no-underline hover:underline',
						className
					) }
				>
					{ __( 'Room details', 'radius-hotel-booking' ) }
					<ExternalLink className="h-3 w-3" aria-hidden="true" />
					<span className="sr-only">
						{ __( '(opens in a new tab)', 'radius-hotel-booking' ) }
					</span>
				</a>
			) : null }
		</>
	);
}

/**
 * Guests, beds and size, each with its icon.
 *
 * @param {Object} props        Props.
 * @param {Object} props.type   Room type.
 * @param {string} props.guests guestsLabel().
 * @return {JSX.Element} Facts.
 */
export function RoomFacts( { type, guests } ) {
	const details = type.details || {};
	return (
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
	);
}
