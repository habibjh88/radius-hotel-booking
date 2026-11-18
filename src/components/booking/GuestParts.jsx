/**
 * Pieces both faces of the website's booking flow share (GuestBookingSteps,
 * GuestBookingOnePage): the stay being booked, the way back, and the prompt
 * before the browser's Back leaves a booking in progress.
 */
import { useEffect, useRef } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AlertTriangle,
	ArrowLeft,
	ArrowRight,
	CalendarDays,
	Clock,
	Users,
} from 'lucide-react';

import { TimePicker } from '@/components/common/DatePicker';
import { Button } from '@/components/ui/button';
import { formatDate, formatTime } from '@/lib/format';

/**
 * The arrival-time help: the stay is counted from it, plus the allowed range
 * when the plan restricts it (same strings as the desk's ArrivalTimeField).
 *
 * @param {Object} checkin  `{ from, until }`.
 * @param {string} rateName The rate's name.
 * @return {string} Help text.
 */
function arrivalHelp( checkin, rateName ) {
	const counted = sprintf(
		/* translators: %s: rate plan name, e.g. 24 h. */
		__( 'The %s stay is counted from this time.', 'radius-hotel-booking' ),
		rateName
	);
	if ( '00:00' === checkin.from && '23:30' === checkin.until ) {
		return counted;
	}
	return sprintf(
		/* translators: 1: sentence about the stay, 2: earliest arrival time, 3: latest arrival time. */
		__( '%1$s Arrival between %2$s and %3$s.', 'radius-hotel-booking' ),
		counted,
		checkin.from,
		checkin.until
	);
}

/**
 * The stay being booked, at the top of the Room step: check-in and check-out
 * (date and time), the rate and the party, and a way back to change them.
 * A flexible rate also asks its arrival time here, first, highlighted and
 * focused: the stay (and the price) is counted from it, so it is chosen
 * with the rate.
 *
 * @param {Object}   props               Props.
 * @param {Object}   props.rate          The chosen rate (`name`, `window { start, end }`, `checkin`).
 * @param {Object}   props.search        `{ adults, children, checkin_time }`.
 * @param {Function} props.onChange      Back to the dates.
 * @param {Function} props.onCheckinTime Called with a new `HH:MM` (flexible rates).
 * @return {JSX.Element} Summary.
 */
export function StaySummary( { rate, search, onChange, onCheckinTime } ) {
	const asksTime = Boolean( rate.checkin && onCheckinTime );
	// A flexible rate: focus the time first, once the step has scrolled in
	// (no second scroll), so the guest sets it before picking a room.
	useEffect( () => {
		if ( ! asksTime ) {
			return undefined;
		}
		const timer = window.setTimeout( () => {
			document
				.getElementById( 'rtbp-arrival-time' )
				?.focus( { preventScroll: true } );
		}, 450 );
		return () => window.clearTimeout( timer );
	}, [ asksTime, rate.rate_plan_id ] );
	const party = [
		sprintf(
			/* translators: %d: number of adults. */
			_n(
				'%d adult',
				'%d adults',
				search.adults,
				'radius-hotel-booking'
			),
			search.adults
		),
		Number( search.children )
			? sprintf(
					/* translators: %d: number of children. */
					_n(
						'%d child',
						'%d children',
						search.children,
						'radius-hotel-booking'
					),
					search.children
			  )
			: null,
	]
		.filter( Boolean )
		.join( ' · ' );
	const end = ( label, value ) => (
		<div className="min-w-0">
			<span className="block text-xs font-semibold uppercase tracking-wide text-muted-foreground">
				{ label }
			</span>
			<span className="block text-base font-bold text-heading">
				{ formatDate( value ) }
			</span>
			<span className="block text-sm text-muted-foreground">
				{ formatTime( value ) }
			</span>
		</div>
	);

	return (
		<section
			className="rounded-2xl border border-primary/25 bg-primary-softer p-4 sm:p-5"
			aria-label={ __( 'Your stay', 'radius-hotel-booking' ) }
		>
			<div className="flex flex-wrap items-center justify-between gap-3">
				<span className="inline-flex items-center gap-2 text-sm font-bold text-primary">
					<CalendarDays className="h-4 w-4" aria-hidden="true" />
					{ __( 'Your stay', 'radius-hotel-booking' ) }
				</span>
				<Button
					type="button"
					variant="outline"
					size="sm"
					className="h-9 rounded-full border-primary/30 bg-white px-4 font-semibold text-primary hover:border-primary hover:bg-white hover:text-primary"
					onClick={ onChange }
				>
					{ __( 'Change dates', 'radius-hotel-booking' ) }
				</Button>
			</div>
			{ asksTime ? (
				<div className="mt-3 rounded-xl border-2 border-primary bg-white p-3 shadow-sm transition-shadow focus-within:ring-4 focus-within:ring-primary-soft sm:p-4">
					<div className="flex flex-wrap items-center gap-3">
						<span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
							<Clock className="h-5 w-5" aria-hidden="true" />
						</span>
						<div className="min-w-0 flex-1 basis-48">
							<label
								htmlFor="rtbp-arrival-time"
								className="block text-base font-bold text-heading"
							>
								{ __(
									'Choose your arrival time',
									'radius-hotel-booking'
								) }
							</label>
							<span className="block text-sm text-muted-foreground">
								{ arrivalHelp( rate.checkin, rate.name ) }
							</span>
						</div>
						<TimePicker
							id="rtbp-arrival-time"
							className="w-full text-base font-semibold sm:w-40"
							minuteStep={ 30 }
							min={ rate.checkin.from }
							max={ rate.checkin.until }
							value={ search.checkin_time }
							onChange={ onCheckinTime }
						/>
					</div>
				</div>
			) : null }
			<div className="mt-3 flex flex-wrap items-center gap-x-6 gap-y-3">
				{ end(
					__( 'Check-in', 'radius-hotel-booking' ),
					rate.window.start
				) }
				<ArrowRight
					className="h-5 w-5 shrink-0 text-primary"
					aria-hidden="true"
				/>
				{ end(
					__( 'Check-out', 'radius-hotel-booking' ),
					rate.window.end
				) }
				<div className="min-w-0 border-primary/20 sm:ml-auto sm:border-l sm:pl-6">
					<span className="block text-sm font-semibold text-heading">
						{ rate.name }
					</span>
					<span className="flex items-center gap-1.5 text-sm text-muted-foreground">
						<Users className="h-4 w-4" aria-hidden="true" />
						{ party }
					</span>
				</div>
			</div>
		</section>
	);
}

/**
 * The way back to the previous step: a clear, full-size button, so a guest
 * reaches for it rather than the browser's Back.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Label.
 * @param {Function} props.onClick  Called on click.
 * @return {JSX.Element} Button.
 */
export function BackButton( { label, onClick } ) {
	return (
		<Button
			type="button"
			variant="outline"
			className="h-11 gap-2 rounded-full border-primary/30 bg-white px-5 font-semibold text-primary shadow-sm hover:border-primary hover:bg-primary-soft hover:text-primary focus-visible:ring-2 focus-visible:ring-ring"
			onClick={ onClick }
		>
			<ArrowLeft className="h-4 w-4" aria-hidden="true" />
			{ label }
		</Button>
	);
}

/**
 * Asked when the browser's Back would leave a booking in progress. Light on
 * purpose (no Radix dialog in the website's bundle): an overlay, focus on
 * *Stay*, Escape stays.
 *
 * @param {Object}   props         Props.
 * @param {Function} props.onStay  Keep booking.
 * @param {Function} props.onLeave Leave and start again.
 * @return {JSX.Element} Prompt.
 */
export function LeavePrompt( { onStay, onLeave } ) {
	const stayRef = useRef( null );
	useEffect( () => {
		stayRef.current?.focus();
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				onStay();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [ onStay ] );

	return (
		<div className="fixed inset-0 z-[100001] flex items-center justify-center bg-black/50 p-4">
			<div
				role="alertdialog"
				aria-modal="true"
				aria-labelledby="rtbp-leave-title"
				aria-describedby="rtbp-leave-text"
				className="w-full max-w-md rounded-2xl bg-card p-6 shadow-xl"
			>
				<div className="flex items-start gap-3">
					<span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-warning-soft text-warning">
						<AlertTriangle className="h-5 w-5" aria-hidden="true" />
					</span>
					<div className="min-w-0 space-y-1.5">
						<h2
							id="rtbp-leave-title"
							className="m-0 text-lg font-bold text-heading"
						>
							{ __(
								'Leave your booking?',
								'radius-hotel-booking'
							) }
						</h2>
						<p
							id="rtbp-leave-text"
							className="m-0 text-sm text-muted-foreground"
						>
							{ __(
								'Your booking is not finished. If you leave now, the rooms held for you are released and you will start again from the beginning.',
								'radius-hotel-booking'
							) }
						</p>
					</div>
				</div>
				<div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
					<Button
						type="button"
						variant="outline"
						className="h-11"
						onClick={ onLeave }
					>
						{ __(
							'Leave and start again',
							'radius-hotel-booking'
						) }
					</Button>
					<Button
						ref={ stayRef }
						type="button"
						className="h-11"
						onClick={ onStay }
					>
						{ __( 'Continue my booking', 'radius-hotel-booking' ) }
					</Button>
				</div>
			</div>
		</div>
	);
}

/**
 * Scroll to the top of the flow's scroll box: the popup's body when the form
 * is in one (`data-rtbp-scroll`), else the page.
 *
 * @param {HTMLElement|null} node An element inside the flow.
 * @return {void}
 */
export function scrollToTop( node ) {
	const box = node?.closest( '[data-rtbp-scroll]' );
	( box || window ).scrollTo( { top: 0, behavior: 'smooth' } );
}

/**
 * Bring a part of the flow to the top of its scroll box: inside the popup,
 * only the popup's body scrolls (scrollIntoView would move the page behind
 * it and the popup's frame too).
 *
 * @param {HTMLElement|null} node The part.
 * @return {void}
 */
export function scrollToPart( node ) {
	if ( ! node ) {
		return;
	}
	const box = node.closest( '[data-rtbp-scroll]' );
	if ( ! box ) {
		node.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		return;
	}
	const top =
		box.scrollTop +
		node.getBoundingClientRect().top -
		box.getBoundingClientRect().top -
		16;
	box.scrollTo( { top: Math.max( 0, top ), behavior: 'smooth' } );
}
