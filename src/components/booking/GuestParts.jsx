/**
 * Pieces both faces of the website's booking flow share (GuestBookingSteps,
 * GuestBookingOnePage): the stay being booked, the way back, and the prompt
 * before the browser's Back leaves a booking in progress.
 */
import { useEffect, useRef, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AlertTriangle,
	ArrowLeft,
	ArrowRight,
	CalendarDays,
	Clock,
	Loader2,
	Minus,
	Plus,
	Users,
	X,
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
 * The party in words: "2 adults · 1 child".
 *
 * @param {Object} search `{ adults, children }`.
 * @return {string} Label.
 */
export function partyLabel( search ) {
	return [
		sprintf(
			/* translators: %d: number of adults. */
			_n(
				'%d adult',
				'%d adults',
				Number( search.adults ),
				'radius-hotel-booking'
			),
			Number( search.adults )
		),
		Number( search.children )
			? sprintf(
					/* translators: %d: number of children. */
					_n(
						'%d child',
						'%d children',
						Number( search.children ),
						'radius-hotel-booking'
					),
					Number( search.children )
			  )
			: null,
	]
		.filter( Boolean )
		.join( ' · ' );
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
	const party = partyLabel( search );
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
 * Without a choice of room (Settings → Website → Guests choose their room
 * off): the flexible rate's arrival time is asked in StaySummary, then this
 * holds the first free room at that time.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.rate    The chosen rate (`available`, `reasons`).
 * @param {boolean}  props.pending A hold is under way.
 * @param {Function} props.onHold  Hold the first free room.
 * @return {JSX.Element} Button, or why the rate cannot be booked.
 */
export function FirstFreeRoom( { rate, pending, onHold } ) {
	if ( ! rate.available ) {
		return (
			<p className="m-0 text-sm text-muted-foreground">
				{ rate.reasons?.[ 0 ]?.message ||
					__(
						'This rate is no longer available.',
						'radius-hotel-booking'
					) }
			</p>
		);
	}
	return (
		<div className="flex justify-end">
			<Button
				type="button"
				className="h-11 min-w-[9rem]"
				onClick={ onHold }
				disabled={ pending }
			>
				{ pending ? (
					<Loader2 className="animate-spin" aria-hidden="true" />
				) : null }
				{ pending
					? __( 'Holding a room for you…', 'radius-hotel-booking' )
					: __( 'Book at this time', 'radius-hotel-booking' ) }
			</Button>
		</div>
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
 * How many rooms (Settings → Website → Guests choose their room off): asked
 * when a rate is chosen, so a guest booking several rooms enters the number
 * once instead of coming back to the rates for each room. Light on purpose,
 * like LeavePrompt: an overlay, focus on the number, Escape closes.
 *
 * @param {Object}   props           Props.
 * @param {Object}   props.type      The room type (`name`).
 * @param {Object}   props.rate      The rate (`name`).
 * @param {number}   props.most      Most rooms that can be taken (free and allowed).
 * @param {Object}   props.search    `{ adults, children }`: the party of each room.
 * @param {Function} props.onConfirm Called with the number of rooms.
 * @param {Function} props.onCancel  Close without holding.
 * @return {JSX.Element} Prompt.
 */
export function RoomCountPrompt( {
	type,
	rate,
	most,
	search,
	onConfirm,
	onCancel,
} ) {
	const [ count, setCount ] = useState( 1 );
	const inputRef = useRef( null );
	// The latest onCancel, so the flow re-rendering (hold timer, refetch)
	// does not refocus the number while the guest types.
	const cancelRef = useRef( onCancel );
	cancelRef.current = onCancel;
	useEffect( () => {
		inputRef.current?.focus();
		inputRef.current?.select();
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				cancelRef.current();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [] );

	const fit = ( value ) =>
		Math.min( most, Math.max( 1, Math.floor( Number( value ) || 1 ) ) );
	// Not a submit button: themes restyle `button[type=submit]` (size, corners)
	// over the plugin's classes. Enter in the number still books (form submit).
	const submit = ( event ) => {
		event.preventDefault();
		onConfirm( fit( count ) );
	};
	const stepClass =
		'flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-border bg-white p-0 text-heading shadow-none transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

	return (
		<div
			className="fixed inset-0 z-[100001] flex items-center justify-center bg-black/50 p-4"
			onClick={ ( event ) => {
				if ( event.target === event.currentTarget ) {
					onCancel();
				}
			} }
			role="presentation"
		>
			<form
				role="dialog"
				aria-modal="true"
				aria-labelledby="rtbp-count-title"
				aria-describedby="rtbp-count-text"
				className="relative w-full max-w-sm rounded-2xl bg-card p-6 shadow-xl"
				onSubmit={ submit }
			>
				<button
					type="button"
					className="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full border-0 bg-transparent p-0 text-muted-foreground shadow-none hover:bg-muted hover:text-heading focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
					onClick={ onCancel }
					aria-label={ __( 'Close', 'radius-hotel-booking' ) }
				>
					<X className="h-4 w-4" aria-hidden="true" />
				</button>
				<div className="space-y-1.5 pr-8">
					<h2
						id="rtbp-count-title"
						className="m-0 text-lg font-bold text-heading"
					>
						{ __( 'How many rooms?', 'radius-hotel-booking' ) }
					</h2>
					<p
						id="rtbp-count-text"
						className="m-0 text-sm text-muted-foreground"
					>
						{ type.name } · { rate.name }
					</p>
				</div>
				<div className="mt-5 flex items-center justify-center gap-3">
					<button
						type="button"
						className={ stepClass }
						onClick={ () => setCount( fit( Number( count ) - 1 ) ) }
						disabled={ fit( count ) <= 1 }
						aria-label={ __(
							'One room less',
							'radius-hotel-booking'
						) }
					>
						<Minus className="h-4 w-4" aria-hidden="true" />
					</button>
					<label htmlFor="rtbp-room-count" className="sr-only">
						{ __( 'Number of rooms', 'radius-hotel-booking' ) }
					</label>
					<input
						ref={ inputRef }
						id="rtbp-room-count"
						type="number"
						inputMode="numeric"
						min={ 1 }
						max={ most }
						step={ 1 }
						value={ count }
						onChange={ ( event ) => setCount( event.target.value ) }
						onBlur={ () => setCount( fit( count ) ) }
						className="h-12 w-20 rounded-lg border border-border bg-white px-2 text-center text-xl font-bold text-heading [appearance:textfield] focus-visible:outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus-visible:ring-2 focus-visible:ring-ring"
					/>
					<button
						type="button"
						className={ stepClass }
						onClick={ () => setCount( fit( Number( count ) + 1 ) ) }
						disabled={ fit( count ) >= most }
						aria-label={ __(
							'One room more',
							'radius-hotel-booking'
						) }
					>
						<Plus className="h-4 w-4" aria-hidden="true" />
					</button>
				</div>
				<p className="m-0 mt-3 text-center text-sm text-muted-foreground">
					{ sprintf(
						/* translators: %d: most rooms the guest can take. */
						_n(
							'Up to %d room.',
							'Up to %d rooms.',
							most,
							'radius-hotel-booking'
						),
						most
					) }{ ' ' }
					{ sprintf(
						/* translators: %s: the party, e.g. 2 adults · 1 child. */
						__( 'Each room: %s.', 'radius-hotel-booking' ),
						partyLabel( search )
					) }
				</p>
				<div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
					<Button
						type="button"
						variant="outline"
						className="h-11 rounded-lg text-sm font-semibold"
						onClick={ onCancel }
					>
						{ __( 'Cancel', 'radius-hotel-booking' ) }
					</Button>
					<Button
						type="button"
						onClick={ submit }
						className="h-11 min-w-[9rem] rounded-lg text-sm font-semibold normal-case tracking-normal"
					>
						{ sprintf(
							/* translators: %d: number of rooms. */
							_n(
								'Book %d room',
								'Book %d rooms',
								fit( count ),
								'radius-hotel-booking'
							),
							fit( count )
						) }
					</Button>
				</div>
			</form>
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
