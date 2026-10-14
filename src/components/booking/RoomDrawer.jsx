/**
 * The desk's room step as a drawer from the right: choosing a rate opens it
 * over the rates (which stay in view behind it), with the floor plan, "Pick
 * any" and the hold bar. It stays open after a hold so more rooms can be
 * ticked; Done closes it and goes on to the booking's rooms and the guest.
 *
 * Loaded lazily by BookingFlow (desk only), so the Radix dialog stays out of
 * the website's bundle.
 */
import { useRef } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Check, Clock } from 'lucide-react';

import Money from '@/components/common/Money';
import { Button } from '@/components/ui/button';
import {
	Sheet,
	SheetContent,
	SheetDescription,
	SheetTitle,
} from '@/components/ui/sheet';
import { formatDateRange } from '@/lib/format';

import HoldChip from './HoldChip';
import RoomSelection from './RoomSelection';

/**
 * @param {Object}   props         Props.
 * @param {Object}   props.chosen  `{ type, rate }` of the chosen rate, or null (closed).
 * @param {number[]} props.taken   Room ids already in this booking for this time.
 * @param {boolean}  props.pending A hold is on its way.
 * @param {number}   props.left    Rooms the booking may still take.
 * @param {Function} props.onHold  Holds the ticked rooms (see RoomSelection).
 * @param {Object[]} props.lines   The booking's rooms so far.
 * @param {Object}   props.holds   The holds (`expiresAt`, `expired`).
 * @param {Function} props.onClose Closes the drawer (back to the rates).
 * @param {Function} props.onDone  Closes it and goes on to the guest.
 * @return {JSX.Element} Drawer.
 */
export default function RoomDrawer( {
	chosen,
	taken,
	pending,
	left,
	onHold,
	lines,
	holds,
	onClose,
	onDone,
} ) {
	// Keep showing the last rate while the drawer slides closed.
	const last = useRef( chosen );
	if ( chosen ) {
		last.current = chosen;
	}
	const shown = chosen || last.current;
	const type = shown?.type;
	const rate = shown?.rate;
	// The rooms already held at this rate (for the summary at the top).
	const here = rate
		? lines.filter(
				( line ) =>
					line.room_type_id === type.id &&
					line.rate_plan_id === rate.rate_plan_id
		  )
		: [];
	const total = lines.reduce(
		( sum, line ) => sum + Number( line.total || 0 ),
		0
	);
	const times = rate?.window
		? formatDateRange( rate.window.start, rate.window.end )
		: '';

	return (
		<Sheet
			open={ Boolean( chosen ) }
			onOpenChange={ ( open ) => ! open && onClose() }
		>
			<SheetContent
				side="right"
				overlayClassName="bg-black/30"
				className="flex w-full flex-col gap-0 p-0 sm:max-w-2xl"
				aria-describedby={ undefined }
			>
				{ shown ? (
					<>
						<div className="space-y-1 border-b border-border px-5 py-4 pr-14">
							<SheetTitle className="m-0 text-lg font-bold text-heading">
								{ sprintf(
									/* translators: 1: room type, 2: rate name. */
									__( '%1$s · %2$s', 'radius-hotel-booking' ),
									type.name,
									rate.name
								) }
							</SheetTitle>
							<SheetDescription className="m-0 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
								{ times ? (
									<span className="inline-flex items-center gap-1">
										<Clock
											className="h-3.5 w-3.5"
											aria-hidden="true"
										/>
										{ times }
									</span>
								) : null }
								{ rate.price?.total !== undefined ? (
									<span>
										<Money value={ rate.price.total } />{ ' ' }
										{ __(
											'per room',
											'radius-hotel-booking'
										) }
									</span>
								) : null }
							</SheetDescription>
						</div>

						<div className="flex-1 space-y-4 overflow-y-auto px-5 py-4">
							{ lines.length ? (
								<div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-primary/30 bg-primary-softer px-3 py-2 text-sm">
									<span className="inline-flex items-center gap-2 font-medium text-heading">
										<Check
											className="h-4 w-4 text-primary"
											aria-hidden="true"
										/>
										{ sprintf(
											/* translators: 1: rooms in the booking, 2: rooms at this rate. */
											_n(
												'%1$d room in this booking (%2$d here)',
												'%1$d rooms in this booking (%2$d here)',
												lines.length,
												'radius-hotel-booking'
											),
											lines.length,
											here.length
										) }
										<span className="text-muted-foreground">
											·
										</span>
										<Money value={ total } />
									</span>
									<HoldChip
										expiresAt={ holds.expiresAt }
										expired={ holds.expired }
									/>
								</div>
							) : null }

							{ rate.available ? (
								<RoomSelection
									type={ type }
									rate={ rate }
									taken={ taken }
									pending={ pending }
									left={ left }
									onHold={ onHold }
								/>
							) : (
								<p className="m-0 text-sm text-muted-foreground">
									{ rate.reasons?.[ 0 ]?.message ||
										__(
											'This rate is no longer available.',
											'radius-hotel-booking'
										) }
								</p>
							) }
						</div>

						<div className="flex items-center justify-between gap-2 border-t border-border px-5 py-3">
							<Button
								type="button"
								variant="ghost"
								onClick={ onClose }
							>
								{ __( 'Other rates', 'radius-hotel-booking' ) }
							</Button>
							<Button
								type="button"
								variant={ lines.length ? 'default' : 'outline' }
								disabled={ ! lines.length }
								onClick={ onDone }
							>
								{ lines.length
									? __(
											'Done, continue',
											'radius-hotel-booking'
									  )
									: __(
											'Hold rooms to continue',
											'radius-hotel-booking'
									  ) }
							</Button>
						</div>
					</>
				) : null }
			</SheetContent>
		</Sheet>
	);
}
