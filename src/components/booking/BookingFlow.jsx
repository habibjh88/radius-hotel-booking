/**
 * The booking flow (M02; M04 mounts it with `mode="guest"`): dates and
 * guests → rates by room type → room (held on pick; several rooms per
 * booking) → guest (find or new, banned banner) → summary and confirm →
 * the booking's reference. One search drives every step; a chosen rate is kept by its
 * ids, so a new search updates its price and availability in place. The
 * check-in time is always part of the search (fixed plans ignore it): today
 * it starts at the next half hour, later days at 08:00.
 */
import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { __, sprintf } from '@wordpress/i18n';
import { AlertTriangle } from 'lucide-react';

import Panel from '@/components/common/Panel';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate, siteToday } from '@/lib/format';
import { toast, toastError } from '@/lib/toast';
import { post } from '@/api/client';
import BookingDone from './BookingDone';
import BookingLines from './BookingLines';
import DatesBar from './DatesBar';
import GuestStep, { EMPTY_GUEST } from './GuestStep';
import HoldChip from './HoldChip';
import RateList from './RateList';
import RoomPicker from './RoomPicker';
import SummaryStep from './SummaryStep';
import { AVAILABILITY_KEY, searchIsValid, useAvailability } from './api';
import { useHolds } from './holds';
import { checkinRange, defaultCheckin } from './rates';

/**
 * The chosen rate, looked up in the latest search.
 *
 * @param {Object|undefined} data   Search result.
 * @param {Object|null}      choice `{ room_type_id, rate_plan_id }`.
 * @return {{type: Object, rate: Object}|null} The room type and rate, or null.
 */
const findChoice = ( data, choice ) => {
	if ( ! data || ! choice ) {
		return null;
	}
	const type = ( data.room_types || [] ).find(
		( t ) => t.id === choice.room_type_id
	);
	const rate = type?.rates?.find(
		( r ) => r.rate_plan_id === choice.rate_plan_id
	);
	return type && rate ? { type, rate } : null;
};

/**
 * Whether two windows (`{ start_gmt, end_gmt }`) overlap.
 *
 * @param {Object} a Window.
 * @param {Object} b Window.
 * @return {boolean} Overlap.
 */
const overlaps = ( a, b ) =>
	Boolean( a && b ) &&
	new Date( a.start_gmt ) < new Date( b.end_gmt ) &&
	new Date( b.start_gmt ) < new Date( a.end_gmt );

/**
 * @param {Object} props      Props.
 * @param {string} props.mode `desk` (staff) or `guest` (the website, M04).
 * @return {JSX.Element} Flow.
 */
export default function BookingFlow( { mode = 'desk' } ) {
	const [ search, setSearch ] = useState( () => {
		const today = siteToday();
		return {
			arrival: today,
			departure: today,
			adults: 1,
			children: 0,
			// Always sent: fixed plans ignore it, flexible plans start there.
			checkin_time: defaultCheckin( today ),
		};
	} );
	// Until staff set the time themselves, it follows the arrival date.
	const [ timeTouched, setTimeTouched ] = useState( false );
	const [ choice, setChoice ] = useState( null );
	// The booking's rooms so far (2.12), each held (2.14).
	const [ lines, setLines ] = useState( [] );
	const [ pending, setPending ] = useState( 0 );
	// The guest (2.7–2.9): an existing one, or the fields of a new one.
	const [ guest, setGuest ] = useState( {
		mode: 'find',
		guest: null,
		fields: EMPTY_GUEST,
	} );
	// Summary (2.10, 2.11) and the answer of Confirm.
	const [ payment, setPayment ] = useState( 'unpaid' );
	// *Paid now*: how it was paid (a ledger row, M05) and its reference.
	const [ paidWith, setPaidWith ] = useState( { method: '', reference: '' } );
	const [ note, setNote ] = useState( '' );
	const [ submitting, setSubmitting ] = useState( false );
	const [ failed, setFailed ] = useState( -1 );
	const [ priceChange, setPriceChange ] = useState( null );
	const [ guestErrors, setGuestErrors ] = useState( {} );
	// A new guest's phone or e-mail already on file under another name.
	const [ guestMatch, setGuestMatch ] = useState( null );
	const [ done, setDone ] = useState( null );
	const queryClient = useQueryClient();
	const holds = useHolds();
	// With the token, the booking's own holds do not count as busy.
	const availability = useAvailability( {
		...search,
		hold_token: holds.token,
	} );
	const chosen = findChoice( availability.data, choice );
	const checkin = checkinRange( availability.data, chosen?.rate );

	// Keep the default time inside the flexible plan's allowed range.
	useEffect( () => {
		if ( ! checkin || timeTouched ) {
			return;
		}
		const fitted = defaultCheckin( search.arrival, checkin );
		if ( fitted !== search.checkin_time ) {
			setSearch( ( prev ) => ( { ...prev, checkin_time: fitted } ) );
		}
	}, [ checkin, timeTouched, search.arrival, search.checkin_time ] );

	const change = ( fields ) => {
		if ( 'checkin_time' in fields ) {
			setTimeTouched( true );
		}
		setSearch( ( prev ) => {
			const next = { ...prev, ...fields };
			if ( fields.arrival && ! timeTouched ) {
				next.checkin_time = defaultCheckin( fields.arrival, checkin );
			}
			return next;
		} );
	};

	const select = ( type, rate ) =>
		setChoice( {
			room_type_id: type.id,
			rate_plan_id: rate.rate_plan_id,
		} );

	// Rooms already in this booking for an overlapping time.
	const taken = chosen
		? lines
				.filter( ( line ) =>
					overlaps( line.window, chosen.rate.window )
				)
				.map( ( line ) => line.room_id )
		: [];

	// Picking a room holds it and adds a line; the next room starts from the rates again.
	const pick = async ( room ) => {
		const { type, rate } = chosen;
		setPending( room.id );
		try {
			const hold = await holds.place( {
				room_id: room.id,
				room_type_id: type.id,
				rate_plan_id: rate.rate_plan_id,
				arrival: search.arrival,
				units: rate.units,
				...( rate.checkin
					? { checkin_time: search.checkin_time }
					: {} ),
			} );
			setLines( ( prev ) => [
				...prev,
				{
					key: hold.id,
					hold_id: hold.id,
					room_id: room.id,
					room_number: hold.room,
					room_type_id: type.id,
					room_type_name: type.name,
					rate_plan_id: rate.rate_plan_id,
					rate_name: rate.name,
					arrival: search.arrival,
					arrival_label: formatDate( search.arrival ),
					units: rate.units,
					checkin_time: rate.checkin ? search.checkin_time : '',
					adults: Number( search.adults ),
					children: Number( search.children ),
					window: hold.window,
					total: hold.total,
				},
			] );
			setChoice( null );
			toast.success(
				sprintf(
					/* translators: %s: room number. */
					__(
						'Room %s is held for this booking.',
						'radius-hotel-booking'
					),
					hold.room
				)
			);
		} catch ( err ) {
			if ( 'room_unavailable' === err?.code ) {
				// Someone was quicker (2.13): say so and show the rooms as they are now.
				toast.error(
					sprintf(
						/* translators: %s: room number. */
						__(
							'Room %s was just taken. Choose another room.',
							'radius-hotel-booking'
						),
						room.number
					)
				);
				availability.refetch();
			} else {
				toastError( err );
			}
		} finally {
			setPending( 0 );
		}
	};

	const remove = ( line ) => {
		holds.release( line.hold_id );
		setLines( ( prev ) =>
			prev.filter( ( item ) => item.key !== line.key )
		);
		setFailed( -1 );
		setPriceChange( null );
	};

	// What still stops Confirm (shown under the button).
	const newGuest = guest.fields;
	const guestReady = guest.guest
		? true
		: 'new' === guest.mode &&
		  newGuest.first_name.trim() &&
		  newGuest.last_name.trim() &&
		  newGuest.phone.replace( /\D/g, '' ).length >= 8;
	let blocked = '';
	if ( ! lines.length ) {
		blocked = __( 'Add a room first.', 'radius-hotel-booking' );
	} else if ( ! guestReady ) {
		blocked =
			guest.guest || 'new' === guest.mode
				? __(
						"Enter the guest's first name, last name and phone.",
						'radius-hotel-booking'
				  )
				: __(
						'Choose the guest or add a new one.',
						'radius-hotel-booking'
				  );
	}
	const banned = 'banned' === guest.guest?.standing;
	const total = lines.reduce(
		( sum, line ) => sum + Number( line.total ),
		0
	);

	// Accepting a new price just sends again: the line already carries the new
	// quote as its expected total, so any other price that moved is refused and shown too.
	const confirm = async () => {
		setSubmitting( true );
		setGuestErrors( {} );
		setGuestMatch( null );
		setPriceChange( null );
		try {
			const { data } = await post( 'bookings', {
				hold_token: holds.token,
				lines: lines.map( ( line ) => ( {
					room_id: line.room_id,
					room_type_id: line.room_type_id,
					rate_plan_id: line.rate_plan_id,
					arrival: line.arrival,
					units: line.units,
					...( line.checkin_time
						? { checkin_time: line.checkin_time }
						: {} ),
					adults: line.adults,
					children: line.children,
					// The price shown: the server refuses if it moved (price_changed).
					expected_total: line.total,
				} ) ),
				...( guest.guest
					? { guest_id: guest.guest.id }
					: { guest: guest.fields } ),
				payment_state: payment,
				...( 'paid' === payment
					? {
							payment_method: paidWith.method,
							payment_reference: paidWith.reference,
					  }
					: {} ),
				note,
			} );
			// The booking consumed the holds.
			holds.releaseAll( false );
			window.scrollTo( { top: 0 } );
			setDone( {
				booking: data.booking,
				// The guest the server used, not what was typed.
				guestName:
					data.booking.guest?.name ||
					( guest.guest
						? guest.guest.name
						: `${ newGuest.first_name } ${ newGuest.last_name }`.trim() ),
			} );
			queryClient.invalidateQueries( { queryKey: AVAILABILITY_KEY } );
			queryClient.invalidateQueries( { queryKey: [ 'guests' ] } );
		} catch ( err ) {
			const index = Number.isInteger( err?.data?.index )
				? err.data.index
				: -1;
			if ( 'price_changed' === err?.code && index >= 0 ) {
				// Show the new price and ask to accept it; the form stays as it is.
				setFailed( index );
				setPriceChange( {
					index,
					room: lines[ index ]?.room_number,
					from: lines[ index ]?.total,
					to: err.data.quote?.total,
				} );
				setLines( ( prev ) =>
					prev.map( ( line, i ) =>
						i === index
							? { ...line, total: err.data.quote?.total }
							: line
					)
				);
			} else if (
				'guest_exists' === err?.code &&
				err.data?.guests?.[ 0 ]
			) {
				// The phone or e-mail belongs to someone else: use them, or correct the details.
				setGuestMatch( {
					message: err.message,
					guest: err.data.guests[ 0 ],
				} );
			} else if ( 'guest_banned' === err?.code ) {
				toast.error( err.message );
				setGuest( ( prev ) =>
					prev.guest
						? {
								...prev,
								guest: { ...prev.guest, standing: 'banned' },
						  }
						: prev
				);
			} else if ( index >= 0 ) {
				// A room was taken, a rate closed, the time passed… (2.13): mark the line, keep the rest.
				setFailed( index );
				toast.error( err.message );
				availability.refetch();
			} else if ( err?.errors && Object.keys( err.errors ).length ) {
				// The new guest's details.
				const mapped = {};
				Object.entries( err.errors ).forEach( ( [ field, value ] ) => {
					mapped[ field ] = value?.first_message || String( value );
				} );
				setGuestErrors( mapped );
				toast.error( err.message );
			} else {
				toastError( err );
			}
		} finally {
			setSubmitting( false );
		}
	};

	// Start again for the next guest.
	const another = () => {
		setDone( null );
		setLines( [] );
		setChoice( null );
		setGuest( { mode: 'find', guest: null, fields: EMPTY_GUEST } );
		setPayment( 'unpaid' );
		setPaidWith( { method: '', reference: '' } );
		setNote( '' );
		setFailed( -1 );
		setPriceChange( null );
		setGuestErrors( {} );
		setGuestMatch( null );
	};

	if ( done ) {
		return (
			<Panel>
				<BookingDone
					booking={ done.booking }
					guestName={ done.guestName }
					onAnother={ another }
				/>
			</Panel>
		);
	}

	let body;
	if ( ! searchIsValid( search ) ) {
		body = (
			<p className="m-0 text-sm text-muted-foreground">
				{ __(
					'Choose the dates and the number of guests.',
					'radius-hotel-booking'
				) }
			</p>
		);
	} else if ( availability.isPending ) {
		body = (
			<div className="grid gap-3 md:grid-cols-3" role="status">
				<Skeleton className="h-40 w-full rounded-lg" />
				<Skeleton className="h-40 w-full rounded-lg" />
				<Skeleton className="h-40 w-full rounded-lg" />
			</div>
		);
	} else if ( availability.error ) {
		body = (
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
					size="sm"
					onClick={ () => availability.refetch() }
				>
					{ __( 'Try again', 'radius-hotel-booking' ) }
				</Button>
			</div>
		);
	} else {
		body = (
			<RateList
				data={ availability.data }
				choice={ choice }
				onSelect={ select }
				onDates={ change }
			/>
		);
	}

	return (
		<div className="space-y-4" data-mode={ mode }>
			<Panel title={ __( 'Dates and guests', 'radius-hotel-booking' ) }>
				<DatesBar
					search={ search }
					onChange={ change }
					checkin={ checkin }
				/>
			</Panel>
			<Panel
				title={ __( 'Rates', 'radius-hotel-booking' ) }
				description={ __(
					'Every rate for these dates, by room type.',
					'radius-hotel-booking'
				) }
				actions={
					availability.isFetching && ! availability.isPending ? (
						<span
							className="text-xs text-muted-foreground"
							role="status"
						>
							{ __( 'Updating…', 'radius-hotel-booking' ) }
						</span>
					) : null
				}
			>
				{ body }
			</Panel>

			{ chosen ? (
				<Panel
					title={ sprintf(
						/* translators: 1: room type, 2: rate name. */
						__(
							'Choose a room: %1$s, %2$s',
							'radius-hotel-booking'
						),
						chosen.type.name,
						chosen.rate.name
					) }
					description={ __(
						'Picking a room holds it while the booking is completed.',
						'radius-hotel-booking'
					) }
				>
					{ chosen.rate.available ? (
						<RoomPicker
							type={ chosen.type }
							rate={ chosen.rate }
							taken={ taken }
							pending={ pending }
							onPick={ pick }
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
				</Panel>
			) : null }

			{ lines.length ? (
				<Panel
					title={ __(
						'Rooms in this booking',
						'radius-hotel-booking'
					) }
					description={ __(
						'To add another room, choose a rate above.',
						'radius-hotel-booking'
					) }
					actions={
						<HoldChip
							expiresAt={ holds.expiresAt }
							expired={ holds.expired }
						/>
					}
				>
					<BookingLines
						lines={ lines }
						onRemove={ remove }
						failed={ failed }
					/>
				</Panel>
			) : null }

			{ lines.length ? (
				<Panel
					title={ __( 'Guest', 'radius-hotel-booking' ) }
					description={ __(
						'Find the guest, or add them with their identity document.',
						'radius-hotel-booking'
					) }
				>
					{ guestMatch ? (
						<div
							className="mb-4 space-y-3 rounded-lg border border-warning bg-warning-soft p-3 text-sm"
							role="alert"
						>
							<p className="m-0 font-semibold text-heading">
								{ guestMatch.message }
							</p>
							<Button
								type="button"
								size="sm"
								onClick={ () => {
									setGuest( {
										mode: 'find',
										guest: {
											id: guestMatch.guest.id,
											reference:
												guestMatch.guest.reference,
											name: guestMatch.guest.name,
											standing: '',
										},
										fields: EMPTY_GUEST,
									} );
									setGuestMatch( null );
								} }
							>
								{ sprintf(
									/* translators: %s: guest name. */
									__( 'Use %s', 'radius-hotel-booking' ),
									guestMatch.guest.name
								) }
							</Button>
						</div>
					) : null }
					<GuestStep
						value={ guest }
						onChange={ ( next ) => {
							setGuest( next );
							setGuestErrors( {} );
						} }
						errors={ guestErrors }
					/>
				</Panel>
			) : null }

			{ lines.length ? (
				<Panel title={ __( 'Summary', 'radius-hotel-booking' ) }>
					<SummaryStep
						total={ total }
						payment={ payment }
						paidWith={ paidWith }
						note={ note }
						onChange={ ( changed ) => {
							if ( 'paidWith' in changed ) {
								setPaidWith( ( prev ) => ( {
									...prev,
									...changed.paidWith,
								} ) );
							}
							if ( 'payment' in changed ) {
								setPayment( changed.payment );
							}
							if ( 'note' in changed ) {
								setNote( changed.note );
							}
						} }
						blocked={ blocked }
						banned={ banned }
						priceChange={ priceChange }
						submitting={ submitting }
						onConfirm={ confirm }
					/>
				</Panel>
			) : null }
		</div>
	);
}
