/**
 * The one-page face of `BookingFlow mode="guest"` (`[rtbp_booking
 * layout="onepage"]`): dates, rates, the room, the guest's details and
 * Confirm, all on one page, each part opening once the one before it is done.
 * No review step: the summary sits above Confirm.
 *
 * Presentation only, like GuestBookingSteps: the search, holds, rooms and the
 * booking itself stay in `BookingFlow`, which passes the same props to both.
 */
import { useEffect, useRef, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { CalendarDays, Filter, X } from 'lucide-react';

import Money from '@/components/common/Money';
import { Button } from '@/components/ui/button';
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
	scrollToPart,
} from './GuestParts';
import { useLeaveGuard } from './leaveGuard';

/**
 * One numbered part of the page.
 *
 * @param {Object}           props          Props.
 * @param {number}           props.number   Its number.
 * @param {string}           props.title    Heading.
 * @param {JSX.Element}      [props.aside]  Shown at the heading's end.
 * @param {Object}           [props.boxRef] Ref to the section.
 * @param {JSX.Element}      props.children Content.
 * @return {JSX.Element} Section.
 */
function Part( { number, title, aside = null, boxRef, children } ) {
	return (
		<section
			ref={ boxRef }
			// A very soft tint (a trace of the brand colour fading to near
			// white), so the white cards inside each part still stand out.
			className="scroll-mt-24 space-y-4 rounded-2xl border border-border/70 bg-[linear-gradient(180deg,color-mix(in_srgb,var(--primary)_2.5%,#ffffff)_0%,#fafbfd_12rem,#fafbfd_100%)] p-4 shadow-[0_1px_2px_rgba(16,24,40,0.04)] sm:p-6"
		>
			<div className="flex flex-wrap items-center justify-between gap-3">
				<h2 className="m-0 flex items-center gap-3 text-lg font-bold text-heading">
					<span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground">
						{ number }
					</span>
					{ title }
				</h2>
				{ aside }
			</div>
			{ children }
		</section>
	);
}

/**
 * Scroll a part into view (inside the popup's body when there is one).
 *
 * @param {Object} ref A section ref.
 * @return {void}
 */
const reveal = ( ref ) =>
	window.setTimeout( () => scrollToPart( ref.current ), 50 );

/**
 * @param {Object} props Everything from BookingFlow (see the call there).
 * @return {JSX.Element} The guest flow on one page.
 */
export default function GuestBookingOnePage( props ) {
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
	const picks = false !== rules.guestPicksRoom;
	const count = lines.length;
	const [ shown, setShown ] = useState( {} );
	const datesRef = useRef( null );
	const roomRef = useRef( null );
	const detailsRef = useRef( null );

	// Rooms are shown before any date is picked; choosing one asks for the dates.
	const datesPicked = searchIsValid( search );
	const [ askDates, setAskDates ] = useState( false );
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
		reveal( datesRef );
		// Open the arrival calendar straight away.
		const arrival = datesRef.current?.querySelector( 'button' );
		if ( arrival ) {
			arrival.focus( { preventScroll: true } );
			arrival.click();
		}
	};

	// A rate chosen: its rooms open right under the heading of part 2.
	const chosenKey = chosen
		? `${ chosen.type.id }-${ chosen.rate.rate_plan_id }`
		: '';
	useEffect( () => {
		if ( chosenKey && picks ) {
			reveal( roomRef );
		}
	}, [ chosenKey, picks ] );
	// A room held: on to the details.
	const [ seen, setSeen ] = useState( 0 );
	useEffect( () => {
		if ( count > seen ) {
			reveal( detailsRef );
		}
		setSeen( count );
	}, [ count, seen ] );
	// The server refused the guest's details: show the form, where the fields say why.
	const detailErrors = Object.keys( guestErrors ).filter(
		( key ) => 'consent' !== key
	).length;
	useEffect( () => {
		if ( detailErrors ) {
			reveal( detailsRef );
		}
	}, [ detailErrors ] );

	// The browser's Back closes an open room choice; with rooms held it asks
	// before leaving the page. Hidden (a closed popup): nothing to guard.
	const guard = useLeaveGuard( visible && ( count || chosen ) ? 1 : 0, () => {
		if ( chosen ) {
			onClearChoice();
			return true;
		}
		return false;
	} );

	const confirm = () => {
		const errors = guestFormErrors( guest );
		setShown( errors );
		if ( Object.keys( errors ).length ) {
			reveal( detailsRef );
			return;
		}
		onConfirm();
	};

	const onlyType = search.room_type_id
		? availability.data?.room_types?.find(
				( t ) => t.id === Number( search.room_type_id )
		  )
		: null;

	return (
		<div className="space-y-5 pb-28 md:pb-0">
			{ guard.asking ? (
				<LeavePrompt onStay={ guard.stay } onLeave={ guard.leave } />
			) : null }
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

			<Part
				number={ 1 }
				title={ __( 'Your stay', 'radius-hotel-booking' ) }
				boxRef={ datesRef }
			>
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
				<GuestSearchPanel
					bare
					askDates={ askDates }
					search={ search }
					limits={ limits }
					onChange={ onSearch }
					checkin={ checkin }
				/>
			</Part>

			<Part
				number={ 2 }
				title={ __( 'Choose your room', 'radius-hotel-booking' ) }
				boxRef={ roomRef }
			>
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

				{ chosen && picks ? (
					<div className="space-y-4 rounded-xl border border-primary/30 p-4">
						<div className="flex flex-wrap items-center justify-between gap-3">
							<h3 className="m-0 text-base font-bold text-heading">
								{ sprintf(
									/* translators: 1: room type, 2: rate name. */
									__(
										'Choose your room: %1$s, %2$s',
										'radius-hotel-booking'
									),
									chosen.type.name,
									chosen.rate.name
								) }
							</h3>
							<BackButton
								label={ __(
									'Back to other rates',
									'radius-hotel-booking'
								) }
								onClick={ onClearChoice }
							/>
						</div>
						{ chosen.rate.window ? (
							<StaySummary
								rate={ chosen.rate }
								search={ search }
								onChange={ () => {
									onClearChoice();
									reveal( datesRef );
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
				) : (
					<>
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
						{ count ? (
							<p className="m-0 text-sm text-muted-foreground">
								{ __(
									'To add another room, choose a rate below.',
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
					</>
				) }
			</Part>

			{ count ? (
				<Part
					number={ 3 }
					title={ __( 'Your details', 'radius-hotel-booking' ) }
					boxRef={ detailsRef }
					aside={
						<HoldChip
							expiresAt={ holds.expiresAt }
							expired={ holds.expired }
						/>
					}
				>
					<div className="space-y-2">
						<h3 className="m-0 text-sm font-semibold text-heading">
							{ _n(
								'Your room',
								'Your rooms',
								count,
								'radius-hotel-booking'
							) }
						</h3>
						<BookingLines
							lines={ lines }
							onRemove={ onRemove }
							failed={ failed }
						/>
					</div>
					{ /* The form on white, like the cards of the other parts. */ }
					<div className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6">
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
						/>
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
							<p
								className="m-0 text-sm text-destructive"
								role="alert"
							>
								{ guestErrors.consent }
							</p>
						) : null }
					</div>
				</Part>
			) : null }

			{ count ? (
				<div className="fixed inset-x-0 bottom-0 z-[100000] border-t border-border bg-card/95 p-3 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] backdrop-blur md:static md:rounded-xl md:border md:shadow-none">
					<div className="mx-auto flex max-w-3xl items-center justify-between gap-3">
						<div className="min-w-0 text-sm">
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
						</div>
						<Button
							type="button"
							size="lg"
							className="h-12 min-w-[10rem]"
							disabled={
								submitting ||
								holds.expired ||
								( rules.privacyConsent && ! consent )
							}
							onClick={ confirm }
						>
							{ submitting
								? __( 'Booking…', 'radius-hotel-booking' )
								: __(
										'Confirm my booking',
										'radius-hotel-booking'
								  ) }
						</Button>
					</div>
				</div>
			) : null }
		</div>
	);
}
