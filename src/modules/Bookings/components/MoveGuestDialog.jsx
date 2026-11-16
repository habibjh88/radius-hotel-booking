/**
 * Move a checked-in guest to another room during the stay (a fault in the
 * room: AC, power…). Same room type, free from now to the end of the stay.
 * The stay is split at the move: the booking total and the payments do not
 * change, the price is shared between the two rooms by time. The room left
 * goes to maintenance with the reason, unless unticked — offered only to
 * staff who may change room states (`rooms.manage`). The server checks the
 * new room again under its lock; when the room left has bookings still to
 * come, a warning lists them.
 */
import { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useAccess } from '@/lib/access';
import { formatDate, formatDateRange } from '@/lib/format';
import { toast } from '@/lib/toast';
import { useFreeRoomsFor } from '../api';

/**
 * @param {Object}      props           Props.
 * @param {Object|null} props.line      The checked-in line (null = closed).
 * @param {Function}    props.onClose   Close.
 * @param {Function}    props.onConfirm Called with `{ roomId, reason, maintenance }`; returns a promise of the answer (`{ upcoming }`).
 * @return {JSX.Element} Dialog.
 */
export default function MoveGuestDialog( { line, onClose, onConfirm } ) {
	const rooms = useFreeRoomsFor( line, 'move' );
	const canSetState = useAccess( 'rooms.manage' ) !== 'locked';
	const [ room, setRoom ] = useState( '' );
	const [ reason, setReason ] = useState( '' );
	const [ maintenance, setMaintenance ] = useState( canSetState );
	const [ errors, setErrors ] = useState( {} );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		setRoom( '' );
		setReason( '' );
		setMaintenance( canSetState );
		setErrors( {} );
	}, [ line?.id, canSetState ] );

	const confirm = async () => {
		const next = {};
		if ( ! room ) {
			next.room = __(
				'Choose the room the guest moves to.',
				'radius-hotel-booking'
			);
		}
		if ( reason.trim().length < 3 ) {
			next.reason = __(
				'Give a reason (3 to 191 characters).',
				'radius-hotel-booking'
			);
		}
		setErrors( next );
		if ( Object.keys( next ).length ) {
			return;
		}
		setBusy( true );
		try {
			const result = await onConfirm( {
				roomId: Number( room ),
				reason: reason.trim(),
				maintenance: canSetState && maintenance,
			} );
			const upcoming = result?.upcoming || [];
			if ( upcoming.length ) {
				toast.warning(
					sprintf(
						/* translators: 1: room number, 2: booking references with their dates. */
						__(
							'Room %1$s is now in maintenance and has bookings to come: %2$s. Give them another room, or set it back to available once repaired.',
							'radius-hotel-booking'
						),
						line.room_number,
						upcoming
							.map(
								( item ) =>
									`${ item.reference } (${ formatDate(
										item.start
									) })`
							)
							.join( ', ' )
					),
					{ duration: 15000 }
				);
			}
			onClose();
		} catch ( err ) {
			// The caller shows the error; the server's field errors show here.
			setErrors( {
				room: err?.errors?.room_id,
				reason: err?.errors?.reason,
			} );
			if ( 'room_unavailable' === err?.code ) {
				setRoom( '' );
				rooms.refetch();
			}
		} finally {
			setBusy( false );
		}
	};

	const free = rooms.data || [];

	return (
		<Dialog
			open={ Boolean( line ) }
			onOpenChange={ ( open ) => ! open && onClose() }
		>
			<DialogContent className="rtbp-root max-w-md">
				<DialogHeader>
					<DialogTitle>
						{ line
							? sprintf(
									/* translators: %s: room number. */
									__(
										'Move the guest out of room %s',
										'radius-hotel-booking'
									),
									line.room_number
							  )
							: '' }
					</DialogTitle>
					<DialogDescription>
						{ line
							? sprintf(
									/* translators: %s: stay dates. */
									__(
										'%s · the booking total and payments stay the same; the price is shared between the two rooms by time.',
										'radius-hotel-booking'
									),
									formatDateRange( line.start, line.end )
							  )
							: '' }
					</DialogDescription>
				</DialogHeader>

				<Field
					label={ __( 'New room', 'radius-hotel-booking' ) }
					description={ __(
						'Free rooms of the same type, from now to the end of the stay.',
						'radius-hotel-booking'
					) }
					error={ errors.room }
					required
				>
					<Select
						value={ room }
						onValueChange={ setRoom }
						disabled={ rooms.isPending || ! free.length }
					>
						<SelectTrigger>
							<SelectValue
								placeholder={
									rooms.isPending
										? __(
												'Loading free rooms…',
												'radius-hotel-booking'
										  )
										: __(
												'Choose a room',
												'radius-hotel-booking'
										  )
								}
							/>
						</SelectTrigger>
						<SelectContent className="rtbp-root">
							{ free.map( ( option ) => (
								<SelectItem
									key={ option.id }
									value={ String( option.id ) }
								>
									{ option.floor
										? sprintf(
												/* translators: 1: room number, 2: floor name. */
												__(
													'Room %1$s · %2$s',
													'radius-hotel-booking'
												),
												option.number,
												option.floor
										  )
										: sprintf(
												/* translators: %s: room number. */
												__(
													'Room %s',
													'radius-hotel-booking'
												),
												option.number
										  ) }
								</SelectItem>
							) ) }
						</SelectContent>
					</Select>
				</Field>
				{ rooms.data && ! free.length ? (
					<p className="m-0 rounded-lg border border-warning bg-warning-soft p-3 text-sm text-heading">
						{ __(
							'No other room of this type is free until the end of this stay.',
							'radius-hotel-booking'
						) }
					</p>
				) : null }

				<Field
					label={ __( 'Reason', 'radius-hotel-booking' ) }
					description={ __(
						'For example: air conditioning not working. Kept in the history.',
						'radius-hotel-booking'
					) }
					error={ errors.reason }
					required
				>
					<Textarea
						rows={ 2 }
						className="min-h-[4.5rem]"
						maxLength={ 191 }
						value={ reason }
						onChange={ ( event ) =>
							setReason( event.target.value )
						}
					/>
				</Field>

				{ canSetState ? (
					<div className="flex items-start gap-3">
						<Checkbox
							id="rtbp-move-maintenance"
							checked={ maintenance }
							onCheckedChange={ ( value ) =>
								setMaintenance( true === value )
							}
							className="mt-0.5"
						/>
						<Label
							htmlFor="rtbp-move-maintenance"
							className="cursor-pointer text-sm font-normal leading-snug text-heading"
						>
							{ line
								? sprintf(
										/* translators: %s: room number. */
										__(
											'Put room %s in maintenance, with this reason',
											'radius-hotel-booking'
										),
										line.room_number
								  )
								: '' }
						</Label>
					</div>
				) : (
					<p className="m-0 text-sm text-muted-foreground">
						{ line
							? sprintf(
									/* translators: %s: room number. */
									__(
										'Ask a manager to put room %s in maintenance: room states are locked for you.',
										'radius-hotel-booking'
									),
									line.room_number
							  )
							: '' }
					</p>
				) }

				<DialogFooter>
					<Button type="button" variant="ghost" onClick={ onClose }>
						{ __( 'Cancel', 'radius-hotel-booking' ) }
					</Button>
					<Button
						type="button"
						disabled={ busy || ! free.length }
						onClick={ confirm }
					>
						{ busy
							? __( 'Moving…', 'radius-hotel-booking' )
							: __( 'Move the guest', 'radius-hotel-booking' ) }
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	);
}
