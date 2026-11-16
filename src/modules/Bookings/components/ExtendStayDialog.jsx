/**
 * Extend a checked-in guest's stay in the same room: the slots of every rate
 * plan the room type sells that start when the stay ends (or a few hours
 * later — that gap is held for the guest, not charged), with their price. A
 * slot the room is already booked for is shown but cannot be picked: move
 * the guest to another room first. The server checks the room again under
 * its lock.
 */
import { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import Money from '@/components/common/Money';
import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import { formatDateRange, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useExtendOptions } from '../api';

/**
 * @param {Object}      props           Props.
 * @param {Object|null} props.line      The checked-in line (`id`, `room_number`, `end`; null = closed).
 * @param {Function}    props.onClose   Close.
 * @param {Function}    props.onConfirm Called with `{ ratePlanId, expectedTotal }`; returns a promise.
 * @return {JSX.Element} Dialog.
 */
export default function ExtendStayDialog( { line, onClose, onConfirm } ) {
	const options = useExtendOptions( line );
	const [ picked, setPicked ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => setPicked( null ), [ line?.id ] );

	const list = options.data || [];
	const choice = list.find( ( option ) => option.rate_plan_id === picked );

	const confirm = async () => {
		if ( ! choice ) {
			return;
		}
		setBusy( true );
		try {
			await onConfirm( {
				ratePlanId: choice.rate_plan_id,
				expectedTotal: choice.total,
			} );
			onClose();
		} catch ( err ) {
			// The caller shows the error; a moved price or a room taken meanwhile: fresh options.
			if (
				[ 'price_changed', 'room_unavailable', 'no_slot' ].includes(
					err?.code
				)
			) {
				setPicked( null );
				options.refetch();
			}
		} finally {
			setBusy( false );
		}
	};

	return (
		<Dialog
			open={ Boolean( line ) }
			onOpenChange={ ( open ) => ! open && onClose() }
		>
			<DialogContent className="rtbp-root max-w-lg">
				<DialogHeader>
					<DialogTitle>
						{ line
							? sprintf(
									/* translators: %s: room number. */
									__(
										'Extend the stay in room %s',
										'radius-hotel-booking'
									),
									line.room_number
							  )
							: '' }
					</DialogTitle>
					<DialogDescription>
						{ line
							? sprintf(
									/* translators: %s: date and time the stay ends now. */
									__(
										'The stay ends %s. Choose what the guest takes next; the guest keeps the same room and the price is added to the booking.',
										'radius-hotel-booking'
									),
									formatDateTime( line.end )
							  )
							: '' }
					</DialogDescription>
				</DialogHeader>

				{ options.isPending ? (
					<p className="m-0 text-sm text-muted-foreground">
						{ __(
							'Loading the next slots…',
							'radius-hotel-booking'
						) }
					</p>
				) : null }
				{ options.error ? (
					<p className="m-0 text-sm text-destructive">
						{ options.error.message }
					</p>
				) : null }
				{ options.data && ! list.length ? (
					<p className="m-0 rounded-lg border border-warning bg-warning-soft p-3 text-sm text-heading">
						{ __(
							'No rate plan of this room type has a slot right after this stay.',
							'radius-hotel-booking'
						) }
					</p>
				) : null }

				{ list.length ? (
					<div
						role="radiogroup"
						aria-label={ __( 'Next slot', 'radius-hotel-booking' ) }
						className="max-h-[50vh] space-y-2 overflow-y-auto"
					>
						{ list.map( ( option ) => {
							const selected = picked === option.rate_plan_id;
							return (
								<button
									key={ option.rate_plan_id }
									type="button"
									role="radio"
									aria-checked={ selected }
									disabled={ ! option.free }
									onClick={ () =>
										setPicked( option.rate_plan_id )
									}
									className={ cn(
										'flex w-full items-start justify-between gap-3 rounded-lg border px-3 py-2.5 text-left',
										selected
											? 'border-primary bg-primary-soft'
											: 'border-border bg-card hover:border-primary',
										! option.free &&
											'cursor-not-allowed bg-muted opacity-70 hover:border-border'
									) }
								>
									<span className="min-w-0">
										<span className="block text-sm font-semibold text-heading">
											{ option.name }
										</span>
										<span className="block text-xs text-muted-foreground">
											{ formatDateRange(
												option.start,
												option.end
											) }
										</span>
										{ option.free &&
										option.gap_minutes > 0 ? (
											<span className="block text-xs text-muted-foreground">
												{ sprintf(
													/* translators: %s: date and time. */
													__(
														'The room stays held for the guest from %s (not charged).',
														'radius-hotel-booking'
													),
													formatDateTime( line.end )
												) }
											</span>
										) : null }
										{ ! option.free ? (
											<span className="block text-xs font-medium text-destructive">
												{ option.blocked_by &&
												/^[A-Z0-9-]+$/.test(
													option.blocked_by
												)
													? sprintf(
															/* translators: %s: booking reference. */
															__(
																'Room booked by %s. Move the guest to another room first.',
																'radius-hotel-booking'
															),
															option.blocked_by
													  )
													: __(
															'The room is not free for this slot.',
															'radius-hotel-booking'
													  ) }
											</span>
										) : null }
									</span>
									<span className="shrink-0 text-sm font-semibold text-heading">
										+<Money value={ option.total } />
									</span>
								</button>
							);
						} ) }
					</div>
				) : null }

				<DialogFooter>
					<Button type="button" variant="ghost" onClick={ onClose }>
						{ __( 'Cancel', 'radius-hotel-booking' ) }
					</Button>
					<Button
						type="button"
						disabled={ busy || ! choice }
						onClick={ confirm }
					>
						{ busy
							? __( 'Extending…', 'radius-hotel-booking' )
							: __( 'Extend stay', 'radius-hotel-booking' ) }
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	);
}
