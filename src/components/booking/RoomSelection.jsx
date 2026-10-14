/**
 * Choose several rooms of one rate at once (a group): tick rooms on the floor
 * plan, or let "Pick any" tick the first free ones, then hold them all with
 * one click. The same component serves the desk and the website.
 *
 * The parent holds the rooms (`onHold( rooms )`, all or none) and passes the
 * room ids still ticked after a refusal back through `onHeld`'s return value:
 * on success the ticks are cleared, on failure the room that was taken is
 * unticked and the rest stay.
 */
import { useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Loader2, Minus, Plus, X } from 'lucide-react';

import Money from '@/components/common/Money';
import { Button } from '@/components/ui/button';

import RoomPicker, { freeRooms } from './RoomPicker';

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.type     The room type (with `floors`).
 * @param {Object}   props.rate     The chosen rate.
 * @param {number[]} props.taken    Room ids already in this booking for this time.
 * @param {boolean}  props.pending  A hold is on its way.
 * @param {number}   props.left     Rooms the booking may still take.
 * @param {Function} props.onHold   Called with the ticked rooms; resolves to the room ids to keep ticked.
 * @param {Object}   props.skip     Optional `{ label, onClick }` shown while nothing is ticked.
 * @return {JSX.Element} Selection.
 */
export default function RoomSelection( {
	type,
	rate,
	taken,
	pending,
	left,
	onHold,
	skip,
} ) {
	const [ picked, setPicked ] = useState( [] );
	const free = freeRooms( type, rate, taken );
	const limit = Math.max( 0, Math.min( left, free.length ) );
	const [ wanted, setQuick ] = useState( 2 );
	// Kept inside what can be picked as the free rooms change.
	const quick = Math.max( 1, Math.min( wanted, limit ) );

	// A room that stopped being free (taken, held elsewhere) loses its tick.
	const freeKey = free.map( ( r ) => r.id ).join( ',' );
	useEffect( () => {
		setPicked( ( prev ) =>
			prev.filter( ( id ) => free.some( ( r ) => r.id === id ) )
		);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ freeKey ] );

	const toggle = ( room ) =>
		setPicked( ( prev ) => {
			if ( prev.includes( room.id ) ) {
				return prev.filter( ( id ) => id !== room.id );
			}
			return prev.length >= left ? prev : [ ...prev, room.id ];
		} );

	const pickAny = () =>
		setPicked( free.slice( 0, quick ).map( ( r ) => r.id ) );

	const hold = async () => {
		const rooms = free.filter( ( r ) => picked.includes( r.id ) );
		const keep = await onHold( rooms );
		setPicked( Array.isArray( keep ) ? keep : [] );
	};

	const count = picked.length;
	const each = rate.price?.total;
	const full = count >= left;
	const step = ( delta ) =>
		setQuick( Math.max( 1, Math.min( limit, quick + delta ) ) );

	return (
		<div className="space-y-4">
			{ limit > 1 ? (
				<div className="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-muted/40 p-2">
					<span className="px-1 text-sm font-medium text-heading">
						{ __( 'Pick any', 'radius-hotel-booking' ) }
					</span>
					<div className="flex items-center gap-1">
						<Button
							type="button"
							variant="outline"
							size="icon"
							className="h-9 w-9"
							disabled={ quick <= 1 }
							onClick={ () => step( -1 ) }
							aria-label={ __(
								'Fewer rooms',
								'radius-hotel-booking'
							) }
						>
							<Minus aria-hidden="true" />
						</Button>
						<span
							className="w-8 text-center text-sm font-semibold tabular-nums text-heading"
							aria-live="polite"
						>
							{ quick }
						</span>
						<Button
							type="button"
							variant="outline"
							size="icon"
							className="h-9 w-9"
							disabled={ quick >= limit }
							onClick={ () => step( 1 ) }
							aria-label={ __(
								'More rooms',
								'radius-hotel-booking'
							) }
						>
							<Plus aria-hidden="true" />
						</Button>
					</div>
					<Button
						type="button"
						variant="outline"
						size="sm"
						className="h-9"
						onClick={ pickAny }
						disabled={ pending }
					>
						{ sprintf(
							/* translators: %d: number of rooms. */
							_n(
								'Select %d free room',
								'Select %d free rooms',
								quick,
								'radius-hotel-booking'
							),
							quick
						) }
					</Button>
					<span className="px-1 text-xs text-muted-foreground">
						{ sprintf(
							/* translators: 1: free rooms, 2: rooms the booking may still take. */
							__(
								'%1$d free · up to %2$d in this booking',
								'radius-hotel-booking'
							),
							free.length,
							left
						) }
					</span>
				</div>
			) : null }

			<RoomPicker
				type={ type }
				rate={ rate }
				taken={ taken }
				pending={ pending ? -1 : 0 }
				onToggle={ toggle }
				picked={ picked }
			/>

			{ full && count ? (
				<p className="m-0 text-xs text-muted-foreground" role="status">
					{ __(
						'That is as many rooms as this booking can take.',
						'radius-hotel-booking'
					) }
				</p>
			) : null }

			{ count || skip ? (
				<div className="sticky bottom-3 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card/95 p-3 shadow-lg backdrop-blur">
					<div className="min-w-0 text-sm">
						{ count ? (
							<>
								<span className="block font-semibold text-heading">
									{ sprintf(
										/* translators: %d: number of rooms. */
										_n(
											'%d room selected',
											'%d rooms selected',
											count,
											'radius-hotel-booking'
										),
										count
									) }
								</span>
								{ each !== undefined && each !== null ? (
									<span className="block text-muted-foreground">
										{ sprintf(
											/* translators: %d: number of rooms. */
											__(
												'%d ×',
												'radius-hotel-booking'
											),
											count
										) }{ ' ' }
										<Money value={ each } />
									</span>
								) : null }
							</>
						) : (
							<span className="text-muted-foreground">
								{ __(
									'Tap rooms to select them.',
									'radius-hotel-booking'
								) }
							</span>
						) }
					</div>
					<div className="flex flex-wrap items-center gap-2">
						{ count ? (
							<Button
								type="button"
								variant="ghost"
								size="sm"
								onClick={ () => setPicked( [] ) }
								disabled={ pending }
							>
								<X aria-hidden="true" />
								{ __( 'Clear', 'radius-hotel-booking' ) }
							</Button>
						) : null }
						{ count ? (
							<Button
								type="button"
								className="h-11 min-w-[9rem]"
								onClick={ hold }
								disabled={ pending }
							>
								{ pending ? (
									<Loader2
										className="animate-spin"
										aria-hidden="true"
									/>
								) : null }
								{ sprintf(
									/* translators: %d: number of rooms. */
									_n(
										'Hold %d room',
										'Hold %d rooms',
										count,
										'radius-hotel-booking'
									),
									count
								) }
							</Button>
						) : (
							<Button
								type="button"
								variant="outline"
								className="h-11"
								onClick={ skip.onClick }
							>
								{ skip.label }
							</Button>
						) }
					</div>
				</div>
			) : null }
		</div>
	);
}
