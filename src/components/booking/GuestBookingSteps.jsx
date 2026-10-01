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
import { useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { AlertTriangle, ArrowLeft, Check, Plus } from 'lucide-react';

import Money from '@/components/common/Money';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateRange } from '@/lib/format';
import { cn } from '@/lib/utils';
import BookingLines from './BookingLines';
import DatesBar from './DatesBar';
import GuestForm, { guestFormErrors } from './GuestForm';
import HoldChip from './HoldChip';
import RateList from './RateList';
import RoomPicker from './RoomPicker';

/**
 * The step indicator.
 *
 * @param {Object}   props       Props.
 * @param {Object[]} props.steps `{ key, label }`.
 * @param {string}   props.step  Current step.
 * @return {JSX.Element} Steps.
 */
function Progress( { steps, step } ) {
	const current = steps.findIndex( ( s ) => s.key === step );
	return (
		<ol
			className="flex items-center gap-2 overflow-x-auto text-xs font-semibold"
			aria-label={ __( 'Booking steps', 'radius-hotel-booking' ) }
		>
			{ steps.map( ( s, index ) => (
				<li
					key={ s.key }
					className={ cn(
						'flex shrink-0 items-center gap-1.5',
						index === current
							? 'text-heading'
							: 'text-muted-foreground'
					) }
					aria-current={ index === current ? 'step' : undefined }
				>
					<span
						className={ cn(
							'flex h-6 w-6 items-center justify-center rounded-full border',
							index < current &&
								'border-primary bg-primary text-primary-foreground',
							index === current && 'border-primary text-primary',
							index > current && 'border-border'
						) }
					>
						{ index < current ? (
							<Check className="h-3.5 w-3.5" aria-hidden="true" />
						) : (
							index + 1
						) }
					</span>
					{ s.label }
					{ index < steps.length - 1 ? (
						<span
							className="mx-1 h-px w-4 bg-border"
							aria-hidden="true"
						/>
					) : null }
				</li>
			) ) }
		</ol>
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
		onPick,
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
	} = props;
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
		window.scrollTo( { top: 0, behavior: 'smooth' } );
	}, [ step ] );

	const steps = [
		{ key: 'rates', label: __( 'Rates', 'radius-hotel-booking' ) },
		...( picks
			? [ { key: 'room', label: __( 'Room', 'radius-hotel-booking' ) } ]
			: [] ),
		{ key: 'details', label: __( 'Your details', 'radius-hotel-booking' ) },
		{ key: 'review', label: __( 'Review', 'radius-hotel-booking' ) },
	];

	const toReview = () => {
		const errors = guestFormErrors( guest );
		setShown( errors );
		if ( ! Object.keys( errors ).length ) {
			setStep( 'review' );
		}
	};

	let body = null;
	if ( 'rates' === step ) {
		let results;
		if ( availability.isPending ) {
			results = (
				<div className="grid gap-3" role="status">
					<Skeleton className="h-32 w-full rounded-lg" />
					<Skeleton className="h-32 w-full rounded-lg" />
				</div>
			);
		} else if ( availability.error ) {
			results = (
				<div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-4">
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
		} else if ( ! availability.data?.room_types?.length ) {
			results = (
				<p className="m-0 rounded-lg border border-border p-4 text-sm text-muted-foreground">
					{ __(
						'No room is free for these dates. Try other dates.',
						'radius-hotel-booking'
					) }
				</p>
			);
		} else {
			results = (
				<RateList
					data={ availability.data }
					choice={ choice }
					onSelect={ onSelect }
					onDates={ onSearch }
				/>
			);
		}
		const onlyType = search.room_type_id
			? availability.data?.room_types?.find(
					( t ) => t.id === Number( search.room_type_id )
			  )
			: null;
		body = (
			<div className="space-y-4">
				{ search.room_type_id ? (
					<p className="m-0 flex flex-wrap items-center gap-2 text-sm">
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
						<button
							type="button"
							className="min-h-[44px] font-semibold text-primary underline"
							onClick={ () => onSearch( { room_type_id: 0 } ) }
						>
							{ __( 'See all rooms', 'radius-hotel-booking' ) }
						</button>
					</p>
				) : null }
				<div className="rounded-xl border border-border bg-card p-4">
					<DatesBar
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
				<h2 className="m-0 text-lg font-bold text-heading">
					{ __( 'Available rooms', 'radius-hotel-booking' ) }
				</h2>
				{ results }
			</div>
		);
	} else if ( 'room' === step && chosen ) {
		body = (
			<div className="space-y-4">
				<Button
					type="button"
					variant="ghost"
					className="h-11 px-2"
					onClick={ () => {
						onClearChoice();
						setStep( 'rates' );
					} }
				>
					<ArrowLeft aria-hidden="true" />
					{ __( 'Other rates', 'radius-hotel-booking' ) }
				</Button>
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
				{ chosen.rate.available ? (
					<RoomPicker
						type={ chosen.type }
						rate={ chosen.rate }
						taken={ taken }
						pending={ pending }
						onPick={ onPick }
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
						onClick={ () => {
							onClearChoice();
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
					/>
				</section>
			</div>
		);
	} else if ( 'review' === step ) {
		body = (
			<div className="space-y-5">
				<Button
					type="button"
					variant="ghost"
					className="h-11 px-2"
					onClick={ () => setStep( 'details' ) }
				>
					<ArrowLeft aria-hidden="true" />
					{ __( 'Change my details', 'radius-hotel-booking' ) }
				</Button>
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
							`${ guest.first_name } ${ guest.last_name }`.trim(),
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
		<div className="space-y-5 pb-28 md:pb-0">
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
			{ action || count ? (
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
