/**
 * How `BookingFlow mode="guest"` looks on the website (M04, 4.3–4.10): a
 * stepper — Rates (with the dates) → Room (only when guests pick their room)
 * → Your details → Review — with a sticky summary and button at the bottom of
 * a phone screen, 44 px tap targets, and one step on screen at a time.
 *
 * Presentation only: the search, holds, rooms and the booking itself stay in
 * `BookingFlow` (one flow for the desk and the website; this is its guest
 * face, not a fork). The parent passes its state and handlers.
 */
import { useEffect, useRef, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { CalendarDays, Check, Filter, Plus, X } from 'lucide-react';

import Money from '@/components/common/Money';
import { Button } from '@/components/ui/button';
import { formatDateRange } from '@/lib/format';
import { cn } from '@/lib/utils';
import BookingLines from './BookingLines';
import GuestForm, { guestFormErrors } from './GuestForm';
import GuestSearchPanel from './GuestSearchPanel';
import HoldChip from './HoldChip';
import RoomSelection from './RoomSelection';
import RoomTypeResults from './RoomTypeResults';
import { searchIsValid } from './api';
import {
	BackButton,
	LeavePrompt,
	StaySummary,
	scrollToTop,
} from './GuestParts';
import { useLeaveGuard } from './leaveGuard';

/**
 * The step indicator: numbered steps joined by a line that fills as the
 * guest moves on; on a phone, "Step 2 of 4" with the step's name and a bar.
 *
 * @param {Object}   props       Props.
 * @param {Object[]} props.steps `{ key, label, hint }`.
 * @param {string}   props.step  Current step.
 * @return {JSX.Element} Steps.
 */
function Progress( { steps, step } ) {
	const current = Math.max(
		0,
		steps.findIndex( ( s ) => s.key === step )
	);
	const percent = ( ( current + 1 ) / steps.length ) * 100;

	return (
		<nav
			className="rounded-2xl border border-border bg-card px-4 py-3 shadow-sm sm:px-6 sm:py-4"
			aria-label={ __( 'Booking steps', 'radius-hotel-booking' ) }
		>
			{ /* Phone: where the guest is, and a bar. */ }
			<div className="sm:hidden">
				<div className="flex items-baseline justify-between gap-3">
					<span className="text-sm font-bold text-heading">
						{ steps[ current ]?.label }
					</span>
					<span className="text-xs font-semibold text-muted-foreground">
						{ sprintf(
							/* translators: 1: current step number, 2: number of steps. */
							__( 'Step %1$d of %2$d', 'radius-hotel-booking' ),
							current + 1,
							steps.length
						) }
					</span>
				</div>
				<div
					className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
					role="progressbar"
					aria-valuemin={ 1 }
					aria-valuemax={ steps.length }
					aria-valuenow={ current + 1 }
				>
					<div
						className="h-full rounded-full bg-primary transition-all duration-500"
						style={ { width: `${ percent }%` } }
					/>
				</div>
			</div>

			{ /* Wider screens: every step. */ }
			<ol className="m-0 hidden list-none items-center p-0 sm:flex">
				{ steps.map( ( s, index ) => {
					const done = index < current;
					const active = index === current;
					return (
						<li
							key={ s.key }
							className={ cn(
								'flex items-center',
								index < steps.length - 1 && 'flex-1'
							) }
							aria-current={ active ? 'step' : undefined }
						>
							<div className="flex shrink-0 items-center gap-3">
								<span
									className={ cn(
										'flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold transition-all duration-300',
										done &&
											'bg-primary text-primary-foreground',
										active &&
											'bg-primary text-primary-foreground ring-4 ring-primary-soft',
										! done &&
											! active &&
											'border-2 border-border bg-white text-muted-foreground'
									) }
								>
									{ done ? (
										<Check
											className="h-4 w-4"
											strokeWidth={ 3 }
											aria-hidden="true"
										/>
									) : (
										index + 1
									) }
								</span>
								<span className="min-w-0">
									<span
										className={ cn(
											'block text-sm font-semibold leading-tight',
											active || done
												? 'text-heading'
												: 'text-muted-foreground'
										) }
									>
										{ s.label }
									</span>
									<span className="block text-xs text-muted-foreground">
										{ done
											? __(
													'Done',
													'radius-hotel-booking'
											  )
											: s.hint }
									</span>
								</span>
							</div>
							{ index < steps.length - 1 ? (
								<span
									className="mx-4 h-0.5 min-w-[1.5rem] flex-1 overflow-hidden rounded-full bg-border"
									aria-hidden="true"
								>
									<span
										className={ cn(
											'block h-full bg-primary transition-all duration-500',
											done ? 'w-full' : 'w-0'
										) }
									/>
								</span>
							) : null }
						</li>
					);
				} ) }
			</ol>
		</nav>
	);
}

/**
 * @param {Object} props Everything from BookingFlow (see the call there).
 * @return {JSX.Element} The guest flow.
 */
export default function GuestBookingSteps( props ) {
	const {
		rules,
		search,
		limits,
		onSearch,
		checkin,
		availability,
		choice,
		chosen,
		onSelect,
		onClearChoice,
		taken,
		pending,
		onHoldRooms,
		left,
		onAddAnother,
		lines,
		onRemove,
		failed,
		holds,
		total,
		guest,
		onGuest,
		guestErrors,
		consent,
		onConsent,
		priceChange,
		submitting,
		onConfirm,
		visible = true,
		roomDetails = 'page',
		roomCard = 'classic',
		ratesShown = 4,
		priceBreakdown = false,
	} = props;
	const rootRef = useRef( null );
	const picks = false !== rules.guestPicksRoom;
	const [ step, setStep ] = useState( 'rates' );
	const [ shown, setShown ] = useState( {} );

	// A rate chosen: pick the room (or, without a choice of room, it was held already).
	useEffect( () => {
		if ( chosen && picks && 'rates' === step ) {
			setStep( 'room' );
		}
	}, [ chosen, picks, step ] );
	// A room held: on to the details.
	const count = lines.length;
	const [ seen, setSeen ] = useState( 0 );
	useEffect( () => {
		if ( count > seen ) {
			setStep( 'details' );
		}
		setSeen( count );
	}, [ count, seen ] );
	// Nothing left in the booking: back to the rates.
	useEffect( () => {
		if ( ! count && [ 'details', 'review' ].includes( step ) ) {
			setStep( 'rates' );
		}
	}, [ count, step ] );
	useEffect( () => {
		scrollToTop( rootRef.current );
	}, [ step ] );
	// The server refused the guest's details (e.g. a phone on file under
	// another name): back to the form, where the fields show why.
	const detailErrors = Object.keys( guestErrors ).filter(
		( key ) => 'consent' !== key
	).length;
	useEffect( () => {
		if ( detailErrors && 'review' === step ) {
			setStep( 'details' );
		}
	}, [ detailErrors, step ] );
	// Rooms are shown before any date is picked; choosing one asks for the dates.
	const datesPicked = searchIsValid( search );
	const [ askDates, setAskDates ] = useState( false );
	const panelRef = useRef( null );
	useEffect( () => {
		if ( datesPicked ) {
			setAskDates( false );
		}
	}, [ datesPicked ] );
	const selectRate = ( type, rate ) => {
		if ( datesPicked ) {
			onSelect( type, rate );
			return;
		}
		setAskDates( true );
		panelRef.current?.scrollIntoView( {
			behavior: 'smooth',
			block: 'center',
		} );
		// Open the arrival calendar straight away.
		const arrival = panelRef.current?.querySelector( 'button' );
		if ( arrival ) {
			arrival.focus( { preventScroll: true } );
			arrival.click();
		}
	};
	// The browser's Back does what the page's back button does on each step.
	const goBack = () => {
		if ( 'review' === step ) {
			setStep( 'details' );
			return true;
		}
		if ( 'details' === step ) {
			// Back to the rooms of the rate the last ones came from.
			if ( picks && left > 0 ) {
				onAddAnother();
			} else {
				onClearChoice();
			}
			setStep( 'rates' );
			return true;
		}
		if ( 'room' === step ) {
			onClearChoice();
			setStep( 'rates' );
			return true;
		}
		return false;
	};

	const steps = [
		{
			key: 'rates',
			label: __( 'Rates', 'radius-hotel-booking' ),
			hint: __( 'Dates and stay', 'radius-hotel-booking' ),
		},
		...( picks
			? [
					{
						key: 'room',
						label: __( 'Room', 'radius-hotel-booking' ),
						hint: __( 'Pick your rooms', 'radius-hotel-booking' ),
					},
			  ]
			: [] ),
		{
			key: 'details',
			label: __( 'Your details', 'radius-hotel-booking' ),
			hint: __( 'Name and contact', 'radius-hotel-booking' ),
		},
		{
			key: 'review',
			label: __( 'Review', 'radius-hotel-booking' ),
			hint: __( 'Confirm booking', 'radius-hotel-booking' ),
		},
	];

	// One history entry per step past the first (and one while rooms are held
	// on the first): the browser's Back steps back, and asks first when it
	// would leave the page.
	const level = Math.max(
		steps.findIndex( ( item ) => item.key === step ),
		count > 0 ? 1 : 0
	);
	// Hidden (a closed popup): nothing to guard until it opens again.
	const guard = useLeaveGuard( visible ? level : 0, goBack );

	const toReview = () => {
		const errors = guestFormErrors( guest, rules.guestFields );
		setShown( errors );
		if ( ! Object.keys( errors ).length ) {
			setStep( 'review' );
		}
	};

	let body = null;
	if ( 'rates' === step ) {
		const onlyType = search.room_type_id
			? availability.data?.room_types?.find(
					( t ) => t.id === Number( search.room_type_id )
			  )
			: null;
		body = (
			<div className="space-y-6">
				{ search.room_type_id ? (
					<div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/20 bg-primary-softer px-4 py-2.5">
						<p className="m-0 flex items-center gap-2 text-sm text-heading">
							<Filter
								className="h-4 w-4 shrink-0 text-primary"
								aria-hidden="true"
							/>
							{ onlyType
								? sprintf(
										/* translators: %s: room type name. */
										__(
											'Showing %s only.',
											'radius-hotel-booking'
										),
										onlyType.name
								  )
								: __(
										'Showing one room type only.',
										'radius-hotel-booking'
								  ) }
						</p>
						<button
							type="button"
							className="inline-flex min-h-[36px] items-center gap-1.5 rounded-full border border-primary/30 bg-white px-3.5 py-1 text-sm font-semibold normal-case tracking-normal text-primary no-underline shadow-none transition-colors hover:border-primary hover:bg-primary hover:text-primary-foreground focus:border-primary focus:bg-primary focus:text-primary-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
							onClick={ () => onSearch( { room_type_id: 0 } ) }
						>
							<X className="h-3.5 w-3.5" aria-hidden="true" />
							{ __( 'See all rooms', 'radius-hotel-booking' ) }
						</button>
					</div>
				) : null }
				{ askDates ? (
					<p
						className="m-0 flex items-center gap-2 rounded-lg border border-warning bg-warning-soft p-3 text-sm font-medium text-heading"
						role="alert"
					>
						<CalendarDays
							className="h-4 w-4 shrink-0 text-warning"
							aria-hidden="true"
						/>
						{ __(
							'Please choose your arrival and departure dates first, then select your room.',
							'radius-hotel-booking'
						) }
					</p>
				) : null }
				<div ref={ panelRef } className="scroll-mt-24">
					<GuestSearchPanel
						askDates={ askDates }
						search={ search }
						limits={ limits }
						onChange={ onSearch }
						checkin={ checkin }
					/>
				</div>
				{ pending && ! picks ? (
					<p
						className="m-0 text-sm text-muted-foreground"
						role="status"
					>
						{ __(
							'Holding a room for you…',
							'radius-hotel-booking'
						) }
					</p>
				) : null }
				<RoomTypeResults
					availability={ availability }
					needsDates={ ! datesPicked }
					choice={ choice }
					onSelect={ selectRate }
					roomDetails={ roomDetails }
					roomCard={ roomCard }
					ratesShown={ ratesShown }
					priceBreakdown={ priceBreakdown }
					// The rooms already added for these dates show on their rates.
					lines={ lines.filter(
						( line ) => line.arrival === search.arrival
					) }
				/>
			</div>
		);
	} else if ( 'room' === step && chosen ) {
		body = (
			<div className="space-y-4">
				<BackButton
					label={ __(
						'Back to other rates',
						'radius-hotel-booking'
					) }
					onClick={ () => {
						onClearChoice();
						setStep( 'rates' );
					} }
				/>
				<h2 className="m-0 text-lg font-bold text-heading">
					{ sprintf(
						/* translators: 1: room type, 2: rate name. */
						__(
							'Choose your room: %1$s, %2$s',
							'radius-hotel-booking'
						),
						chosen.type.name,
						chosen.rate.name
					) }
				</h2>
				{ chosen.rate.window ? (
					<StaySummary
						rate={ chosen.rate }
						search={ search }
						onChange={ () => {
							onClearChoice();
							setStep( 'rates' );
						} }
					/>
				) : null }
				{ chosen.rate.available ? (
					<RoomSelection
						type={ chosen.type }
						rate={ chosen.rate }
						taken={ taken }
						pending={ Boolean( pending ) }
						left={ left }
						onHold={ onHoldRooms }
						skip={
							count
								? {
										label: __(
											'Continue with my booking',
											'radius-hotel-booking'
										),
										onClick: () => {
											onClearChoice();
											setStep( 'details' );
										},
								  }
								: undefined
						}
					/>
				) : (
					<p className="m-0 text-sm text-muted-foreground">
						{ chosen.rate.reasons?.[ 0 ]?.message ||
							__(
								'This rate is no longer available.',
								'radius-hotel-booking'
							) }
					</p>
				) }
			</div>
		);
	} else if ( 'details' === step ) {
		body = (
			<div className="space-y-5">
				<section className="space-y-3 rounded-xl border border-border bg-card p-4">
					<div className="flex flex-wrap items-center justify-between gap-2">
						<h2 className="m-0 text-base font-bold text-heading">
							{ _n(
								'Your room',
								'Your rooms',
								count,
								'radius-hotel-booking'
							) }
						</h2>
						<HoldChip
							expiresAt={ holds.expiresAt }
							expired={ holds.expired }
						/>
					</div>
					<BookingLines
						lines={ lines }
						onRemove={ onRemove }
						failed={ failed }
					/>
					<Button
						type="button"
						variant="outline"
						className="h-11"
						disabled={ left < 1 }
						onClick={ () => {
							// Straight back to the rate the last rooms came from.
							onAddAnother();
							setStep( 'rates' );
						} }
					>
						<Plus aria-hidden="true" />
						{ __( 'Add another room', 'radius-hotel-booking' ) }
					</Button>
				</section>
				<section className="space-y-3 rounded-xl border border-border bg-card p-4">
					<h2 className="m-0 text-base font-bold text-heading">
						{ __( 'Your details', 'radius-hotel-booking' ) }
					</h2>
					<GuestForm
						value={ guest }
						onChange={ ( fields ) => {
							// A field being fixed loses its message at once.
							setShown( ( prev ) => {
								const next = { ...prev };
								Object.keys( fields ).forEach(
									( key ) => delete next[ key ]
								);
								return next;
							} );
							onGuest( fields );
						} }
						errors={ { ...shown, ...guestErrors } }
						idTypes={ rules.idTypes || {} }
						fields={ rules.guestFields }
						texts={ rules.guestFieldText }
					/>
				</section>
			</div>
		);
	} else if ( 'review' === step ) {
		body = (
			<div className="space-y-5">
				<BackButton
					label={ __( 'Change my details', 'radius-hotel-booking' ) }
					onClick={ () => setStep( 'details' ) }
				/>
				<section className="space-y-3 rounded-xl border border-border bg-card p-4">
					<h2 className="m-0 text-base font-bold text-heading">
						{ __( 'Your booking', 'radius-hotel-booking' ) }
					</h2>
					<ul className="m-0 list-none space-y-2 p-0">
						{ lines.map( ( line ) => (
							<li
								key={ line.key }
								className="flex justify-between gap-3 text-sm"
							>
								<span>
									<span className="block font-semibold text-heading">
										{ sprintf(
											/* translators: 1: room number, 2: room type, 3: rate name. */
											__(
												'Room %1$s · %2$s, %3$s',
												'radius-hotel-booking'
											),
											line.room_number,
											line.room_type_name,
											line.rate_name
										) }
									</span>
									<span className="block text-muted-foreground">
										{ line.window
											? formatDateRange(
													line.window.start,
													line.window.end
											  )
											: line.arrival_label }
									</span>
								</span>
								<span className="shrink-0 font-semibold">
									<Money value={ line.total } />
								</span>
							</li>
						) ) }
					</ul>
					<p className="m-0 border-t border-border pt-2 text-sm">
						{ [
							`${ guest.first_name } ${ guest.last_name }`.trim() ||
								guest.express_hint?.name,
							guest.phone,
							guest.email,
						]
							.filter( Boolean )
							.join( ' · ' ) }
					</p>
					{ guest.special_requests ? (
						<p className="m-0 whitespace-pre-line text-sm text-muted-foreground">
							{ guest.special_requests }
						</p>
					) : null }
				</section>
				{ priceChange ? (
					<p
						className="m-0 rounded-lg border border-warning bg-warning-soft p-3 text-sm"
						role="alert"
					>
						{ __(
							'A price changed since you chose it; the new total is shown. Confirm again to book at this price.',
							'radius-hotel-booking'
						) }
					</p>
				) : null }
				<p className="m-0 text-sm text-muted-foreground">
					{ __(
						'Your room is held while you book. Payment instructions and your invoice are on the next page and in your e-mail.',
						'radius-hotel-booking'
					) }
				</p>
				{ rules.privacyConsent ? (
					<label className="flex min-h-[44px] items-start gap-3 text-sm">
						<input
							type="checkbox"
							className="mt-0.5 h-5 w-5 shrink-0 accent-primary"
							checked={ consent }
							onChange={ ( event ) =>
								onConsent( event.target.checked )
							}
						/>
						<span>
							{ __(
								'I agree that the hotel keeps my details for this booking',
								'radius-hotel-booking'
							) }
							{ rules.privacyUrl ? (
								<>
									{ ' (' }
									<a
										href={ rules.privacyUrl }
										target="_blank"
										rel="noopener noreferrer"
										className="text-primary underline"
									>
										{ __(
											'privacy policy',
											'radius-hotel-booking'
										) }
									</a>
									{ ')' }
								</>
							) : null }
							.
						</span>
					</label>
				) : null }
				{ guestErrors.consent ? (
					<p className="m-0 text-sm text-destructive" role="alert">
						{ guestErrors.consent }
					</p>
				) : null }
			</div>
		);
	}

	// The sticky bar: what is booked so far and the next step's button.
	const action =
		'details' === step
			? {
					label: __( 'Continue', 'radius-hotel-booking' ),
					onClick: toReview,
					disabled: holds.expired,
			  }
			: 'review' === step
			? {
					label: submitting
						? __( 'Booking…', 'radius-hotel-booking' )
						: __( 'Confirm my booking', 'radius-hotel-booking' ),
					onClick: onConfirm,
					disabled:
						submitting ||
						holds.expired ||
						( rules.privacyConsent && ! consent ),
			  }
			: null;

	return (
		<div ref={ rootRef } className="space-y-5 pb-28 md:pb-0">
			{ guard.asking ? (
				<LeavePrompt onStay={ guard.stay } onLeave={ guard.leave } />
			) : null }
			<Progress steps={ steps } step={ step } />
			{ holds.expired && count ? (
				<p
					className="m-0 rounded-lg border border-destructive bg-destructive-soft p-3 text-sm text-destructive"
					role="alert"
				>
					{ __(
						'Your rooms are no longer held. Remove them and choose again.',
						'radius-hotel-booking'
					) }
				</p>
			) : null }
			{ body }
			{ ( action || count ) && 'room' !== step ? (
				<div className="fixed inset-x-0 bottom-0 z-[100000] border-t border-border bg-card/95 p-3 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] backdrop-blur md:static md:rounded-xl md:border md:shadow-none">
					<div className="mx-auto flex max-w-3xl items-center justify-between gap-3">
						<div className="min-w-0 text-sm">
							{ count ? (
								<>
									<span className="block text-muted-foreground">
										{ sprintf(
											/* translators: %d: number of rooms. */
											_n(
												'%d room',
												'%d rooms',
												count,
												'radius-hotel-booking'
											),
											count
										) }
									</span>
									<span className="block text-base font-bold text-heading">
										<Money value={ total } />
									</span>
								</>
							) : null }
						</div>
						{ action ? (
							<Button
								type="button"
								size="lg"
								className="h-12 min-w-[10rem]"
								disabled={ action.disabled }
								onClick={ action.onClick }
							>
								{ action.label }
							</Button>
						) : count && 'details' !== step ? (
							<Button
								type="button"
								size="lg"
								variant="outline"
								className="h-12"
								onClick={ () => setStep( 'details' ) }
							>
								{ __(
									'Continue with my booking',
									'radius-hotel-booking'
								) }
							</Button>
						) : null }
					</div>
				</div>
			) : null }
		</div>
	);
}
