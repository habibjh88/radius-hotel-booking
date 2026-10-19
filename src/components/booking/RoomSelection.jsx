/**
 * Choose several rooms of one rate at once (a group): a status line says how
 * many rooms are free and how many the booking can take, the guest ticks rooms
 * on the floor plan, then holds them all with one click. The same component serves the desk and the website.
 *
 * The parent holds the rooms (`onHold( rooms )`, all or none) and passes the
 * room ids still ticked after a refusal back through `onHeld`'s return value:
 * on success the ticks are cleared, on failure the room that was taken is
 * unticked and the rest stay.
 */
import { useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { BedDouble, Loader2, X } from 'lucide-react';

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

	const hold = async () => {
		const rooms = free.filter( ( r ) => picked.includes( r.id ) );
		const keep = await onHold( rooms );
		setPicked( Array.isArray( keep ) ? keep : [] );
	};

	const count = picked.length;
	const each = rate.price?.total;
	const full = count >= left;

	return (
		<div className="space-y-4">
			<div
				className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-border bg-muted/40 px-3 py-2.5 text-sm"
				role="status"
			>
				<span className="inline-flex items-center gap-2 font-semibold text-heading">
					<span
						className={ `h-2.5 w-2.5 shrink-0 rounded-full ${
							free.length ? 'bg-success' : 'bg-muted-foreground'
						}` }
						aria-hidden="true"
					/>
					{ sprintf(
						/* translators: %d: number of free rooms. */
						_n(
							'%d room free',
							'%d rooms free',
							free.length,
							'radius-hotel-booking'
						),
						free.length
					) }
				</span>
				{ limit ? (
					<>
						<span
							className="hidden h-4 w-px bg-border sm:block"
							aria-hidden="true"
						/>
						<span className="inline-flex items-center gap-2 text-muted-foreground">
							<BedDouble
								className="h-4 w-4 shrink-0"
								aria-hidden="true"
							/>
							{ sprintf(
								/* translators: %d: rooms the booking may still take. */
								_n(
									'You can book up to %d room',
									'You can book up to %d rooms',
									limit,
									'radius-hotel-booking'
								),
								limit
							) }
						</span>
					</>
				) : null }
			</div>

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
