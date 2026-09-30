/**
 * Add a room to a booking, or change one (3.11, 3.12), with the booking
 * flow's own pieces: dates and guests → rates by room type → room. An edit
 * starts from the room as booked; the search leaves that stay out, so its
 * own room shows free and the occupancy price is counted without it.
 *
 * The price follows the freeze rule (3.16): keeping the rate plan, the room
 * type and the times keeps the price the room was sold at; changing one of
 * them takes today's price for the new stay. The server checks everything
 * again under its lock; a price that moved meanwhile is shown to accept.
 */
import { useEffect, useMemo, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { AlertTriangle } from 'lucide-react';

import Money from '@/components/common/Money';
import DatesBar from '@/components/booking/DatesBar';
import RateList from '@/components/booking/RateList';
import RoomPicker from '@/components/booking/RoomPicker';
import { searchIsValid, useAvailability } from '@/components/booking/api';
import {
	checkinRange,
	defaultCheckin,
	localDate,
	windowLabel,
} from '@/components/booking/rates';
import { Button } from '@/components/ui/button';
import {
	Sheet,
	SheetContent,
	SheetDescription,
	SheetFooter,
	SheetHeader,
	SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate, formatMoney, siteToday } from '@/lib/format';
import { toast, toastError } from '@/lib/toast';
import { useLineChange } from '../api';

/**
 * Where the search starts: the room being edited, or the booking's first
 * room still to come (a room added usually shares the stay).
 *
 * @param {Object}      booking Booking.
 * @param {Object|null} line    The room edited (null = adding).
 * @return {Object} Search.
 */
function startSearch( booking, line ) {
	const from =
		line ||
		( booking.lines || [] ).find( ( item ) =>
			[ 'pending', 'confirmed', 'checked_in' ].includes( item.status )
		);
	const today = siteToday();
	if ( ! from || localDate( from.start ) < today ) {
		return {
			arrival: today,
			departure: today,
			adults: 1,
			children: 0,
			checkin_time: defaultCheckin( today ),
		};
	}
	return {
		arrival: localDate( from.start ),
		departure: localDate( from.end ),
		adults: line ? line.adults : 1,
		children: line ? line.children : 0,
		checkin_time: String( from.start ).slice( 11, 16 ),
	};
}

/**
 * The chosen rate in the latest search.
 *
 * @param {Object|undefined} data   Search result.
 * @param {Object|null}      choice `{ room_type_id, rate_plan_id }`.
 * @return {{type: Object, rate: Object}|null} Choice, or null.
 */
const findChoice = ( data, choice ) => {
	const type = ( data?.room_types || [] ).find(
		( t ) => t.id === choice?.room_type_id
	);
	const rate = type?.rates?.find(
		( r ) => r.rate_plan_id === choice?.rate_plan_id
	);
	return type && rate ? { type, rate } : null;
};

/**
 * @param {Object}      props         Props.
 * @param {Object}      props.booking The booking (with its lines).
 * @param {Object|null} props.line    The room to change (null = add a room).
 * @param {boolean}     props.open    Open.
 * @param {Function}    props.onClose Close.
 * @return {JSX.Element} Sheet.
 */
export default function LineEditor( { booking, line, open, onClose } ) {
	const [ search, setSearch ] = useState( () =>
		startSearch( booking, line )
	);
	const [ choice, setChoice ] = useState( null );
	const [ roomId, setRoomId ] = useState( 0 );
	// The server's newer price, to accept before saving: `{ from, to }`.
	const [ priceChange, setPriceChange ] = useState( null );
	const change = useLineChange( booking.id );

	// Each opening starts from the room as it is now.
	useEffect( () => {
		if ( ! open ) {
			return;
		}
		setSearch( startSearch( booking, line ) );
		setChoice(
			line
				? {
						room_type_id: line.room_type_id,
						rate_plan_id: line.rate_plan_id,
				  }
				: null
		);
		setRoomId( line ? line.room_id : 0 );
		setPriceChange( null );
	}, [ open, line?.id ] ); // Only on opening: later edits of the booking must not reset the form.

	const availability = useAvailability( {
		...search,
		exclude_booking_line: line?.id || 0,
		// Closed: no search.
		...( open ? {} : { arrival: '' } ),
	} );
	const chosen = findChoice( availability.data, choice );
	const checkin = checkinRange( availability.data, chosen?.rate );

	// The booking's other rooms for an overlapping time are marked, not offered.
	const taken = useMemo( () => {
		if ( ! chosen?.rate?.window ) {
			return [];
		}
		const start = new Date( chosen.rate.window.start );
		const end = new Date( chosen.rate.window.end );
		return ( booking.lines || [] )
			.filter(
				( item ) =>
					item.id !== line?.id &&
					[ 'pending', 'confirmed', 'checked_in' ].includes(
						item.status
					) &&
					new Date( item.start ) < end &&
					start < new Date( item.end )
			)
			.map( ( item ) => item.room_id );
	}, [ chosen, booking.lines, line?.id ] );

	// The freeze rule, as the server applies it.
	const keepsPrice =
		Boolean( line && chosen?.rate?.window ) &&
		chosen.type.id === line.room_type_id &&
		chosen.rate.rate_plan_id === line.rate_plan_id &&
		new Date( chosen.rate.window.start ).getTime() ===
			new Date( line.start ).getTime() &&
		new Date( chosen.rate.window.end ).getTime() ===
			new Date( line.end ).getTime();
	const price = keepsPrice ? line.total : chosen?.rate?.price?.total;
	const roomNumber = ( () => {
		for ( const floor of chosen?.type?.floors || [] ) {
			const room = ( floor.rooms || [] ).find( ( r ) => r.id === roomId );
			if ( room ) {
				return room.number;
			}
		}
		return '';
	} )();
	const ready = Boolean(
		chosen?.rate?.available !== false && chosen && roomId && roomNumber
	);

	const onDates = ( fields ) =>
		setSearch( ( prev ) => {
			const next = { ...prev, ...fields };
			if ( fields.arrival && ! ( 'checkin_time' in fields ) ) {
				next.checkin_time = defaultCheckin( fields.arrival, checkin );
			}
			return next;
		} );

	const save = async ( accept = false ) => {
		const { type, rate } = chosen;
		const body = {
			room_id: roomId,
			room_type_id: type.id,
			rate_plan_id: rate.rate_plan_id,
			arrival: search.arrival,
			units: rate.units,
			...( rate.checkin ? { checkin_time: search.checkin_time } : {} ),
			adults: Number( search.adults ),
			children: Number( search.children ),
			// The price shown: the server refuses if it moved (price_changed).
			...( keepsPrice ? {} : { expected_total: price } ),
			...( accept ? { accept_new_price: true } : {} ),
		};
		try {
			const { message } = await change.mutateAsync(
				line
					? { kind: 'edit', lineId: line.id, body }
					: { kind: 'add', body }
			);
			toast.success( message );
			onClose();
		} catch ( err ) {
			if ( 'price_changed' === err?.code ) {
				setPriceChange( { from: price, to: err.data?.quote?.total } );
			} else if ( 'room_unavailable' === err?.code ) {
				toast.error( err.message );
				setRoomId( 0 );
				availability.refetch();
			} else {
				toastError( err );
			}
		}
	};

	let rates;
	if ( ! searchIsValid( search ) ) {
		rates = (
			<p className="m-0 text-sm text-muted-foreground">
				{ __(
					'Choose the dates and the number of guests.',
					'radius-hotel-booking'
				) }
			</p>
		);
	} else if ( availability.isPending ) {
		rates = (
			<div className="grid gap-3 md:grid-cols-2" role="status">
				<Skeleton className="h-40 w-full rounded-lg" />
				<Skeleton className="h-40 w-full rounded-lg" />
			</div>
		);
	} else if ( availability.error ) {
		rates = (
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
		rates = (
			<RateList
				data={ availability.data }
				choice={ choice }
				onSelect={ ( type, rate ) => {
					setChoice( {
						room_type_id: type.id,
						rate_plan_id: rate.rate_plan_id,
					} );
					// Another room type has other rooms.
					if ( type.id !== chosen?.type?.id ) {
						setRoomId(
							line && type.id === line.room_type_id
								? line.room_id
								: 0
						);
					}
					setPriceChange( null );
				} }
				onDates={ onDates }
			/>
		);
	}

	return (
		<Sheet open={ open } onOpenChange={ ( next ) => ! next && onClose() }>
			<SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-3xl">
				<SheetHeader className="border-b border-border px-5 py-4 pr-14 text-left">
					<SheetTitle>
						{ line
							? sprintf(
									/* translators: %s: room number. */
									__(
										'Change room %s',
										'radius-hotel-booking'
									),
									line.room_number
							  )
							: __( 'Add a room', 'radius-hotel-booking' ) }
					</SheetTitle>
					<SheetDescription>
						{ line
							? __(
									'Keep the rate, room type and times to keep the price. Changing one of them takes today’s price.',
									'radius-hotel-booking'
							  )
							: sprintf(
									/* translators: %s: booking reference. */
									__(
										'A room for booking %s, at today’s price.',
										'radius-hotel-booking'
									),
									booking.reference
							  ) }
					</SheetDescription>
				</SheetHeader>

				<div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5">
					<DatesBar
						search={ search }
						onChange={ onDates }
						checkin={ checkin }
					/>
					<section className="space-y-2">
						<h3 className="m-0 text-sm font-semibold text-heading">
							{ __( 'Rate', 'radius-hotel-booking' ) }
						</h3>
						{ rates }
					</section>
					{ chosen ? (
						<section className="space-y-2">
							<h3 className="m-0 text-sm font-semibold text-heading">
								{ sprintf(
									/* translators: 1: room type, 2: rate name. */
									__(
										'Room: %1$s, %2$s',
										'radius-hotel-booking'
									),
									chosen.type.name,
									chosen.rate.name
								) }
							</h3>
							{ chosen.rate.available ? (
								<RoomPicker
									type={ chosen.type }
									rate={ chosen.rate }
									taken={ taken }
									pending={ 0 }
									selected={ roomId }
									onPick={ ( room ) => {
										setRoomId( room.id );
										setPriceChange( null );
									} }
								/>
							) : (
								<p className="m-0 text-sm text-muted-foreground">
									{ chosen.rate.reasons?.[ 0 ]?.message ||
										__(
											'This rate is not available for these dates.',
											'radius-hotel-booking'
										) }
								</p>
							) }
						</section>
					) : null }
				</div>

				<SheetFooter className="flex-col gap-3 border-t border-border px-5 py-4 sm:flex-col sm:space-x-0">
					{ ready ? (
						<div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
							<span className="text-heading">
								{ sprintf(
									/* translators: 1: room number, 2: rate name, 3: date, 4: times. */
									__(
										'Room %1$s · %2$s · %3$s %4$s',
										'radius-hotel-booking'
									),
									roomNumber,
									chosen.rate.name,
									formatDate( search.arrival ),
									windowLabel( chosen.rate.window )
								) }
							</span>
							<span className="font-semibold text-heading">
								<Money value={ price } />{ ' ' }
								<span className="text-xs font-normal text-muted-foreground">
									{ keepsPrice
										? __(
												'price kept',
												'radius-hotel-booking'
										  )
										: __(
												'today’s price',
												'radius-hotel-booking'
										  ) }
								</span>
							</span>
						</div>
					) : null }
					{ priceChange ? (
						<div
							className="space-y-3 rounded-lg border border-warning bg-warning-soft p-3 text-sm"
							role="alert"
						>
							<p className="m-0 flex items-start gap-2 font-semibold text-heading">
								<AlertTriangle
									className="mt-0.5 h-4 w-4 shrink-0 text-warning"
									aria-hidden="true"
								/>
								{ sprintf(
									/* translators: 1: old price, 2: new price. */
									__(
										'The price changed from %1$s to %2$s.',
										'radius-hotel-booking'
									),
									formatMoney( priceChange.from ),
									formatMoney( priceChange.to )
								) }
							</p>
							<Button
								type="button"
								disabled={ change.isPending }
								onClick={ () => save( true ) }
							>
								{ __(
									'Accept the new price and save',
									'radius-hotel-booking'
								) }
							</Button>
						</div>
					) : null }
					<div className="flex flex-wrap justify-end gap-2">
						<Button
							type="button"
							variant="ghost"
							onClick={ onClose }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						{ ! priceChange ? (
							<Button
								type="button"
								disabled={ ! ready || change.isPending }
								onClick={ () => save() }
							>
								{ line
									? __(
											'Save changes',
											'radius-hotel-booking'
									  )
									: __( 'Add room', 'radius-hotel-booking' ) }
							</Button>
						) : null }
					</div>
				</SheetFooter>
			</SheetContent>
		</Sheet>
	);
}
