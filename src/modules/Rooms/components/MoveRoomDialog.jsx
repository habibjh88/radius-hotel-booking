/**
 * Move a room to another room type (feature 6.11). The server is asked
 * first; it lists the room's upcoming bookings, which keep their lines and
 * make the room busy under its new type. Then the move is confirmed.
 */
import { useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { AlertTriangle } from 'lucide-react';

import { Button } from '@/components/ui/button';
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
import { formatDateTime } from '@/lib/format';
import { toast, toastError } from '@/lib/toast';
import { useMoveRoom, useRoomTypes } from '../api';

/**
 * @param {Object}   props         Props.
 * @param {Object}   props.type    Current room type.
 * @param {Object}   props.room    Room.
 * @param {Function} props.onClose Close.
 * @return {JSX.Element} Dialog.
 */
export default function MoveRoomDialog( { type, room, onClose } ) {
	const { data: types = [] } = useRoomTypes();
	const move = useMoveRoom();
	const [ target, setTarget ] = useState( '' );
	const [ check, setCheck ] = useState( null );
	const [ error, setError ] = useState( '' );
	const others = types.filter( ( option ) => option.id !== type.id );

	const ask = async ( extra = {} ) => {
		setError( '' );
		try {
			const result = await move.mutateAsync( {
				id: room.id,
				room_type_id: Number( target ),
				...extra,
			} );
			if ( result.moved ) {
				toast.success(
					sprintf(
						/* translators: 1: room number, 2: room type name. */
						__(
							'Room %1$s moved to %2$s.',
							'radius-hotel-booking'
						),
						room.number,
						result.to.name
					)
				);
				onClose();
				return;
			}
			setCheck( result );
		} catch ( e ) {
			// New bookings since the warning: show them and ask again.
			if ( e?.code === 'move_needs_confirm' && e?.data ) {
				setCheck( ( prev ) => ( { ...prev, ...e.data } ) );
				setError( e.message );
				return;
			}
			const field = e?.errors?.room_type_id?.first_message;
			if ( field ) {
				setError( field );
			} else {
				toastError( e );
			}
		}
	};

	const submit = ( event ) => {
		event.preventDefault();
		if ( ! target ) {
			setError(
				__(
					'Choose the room type to move the room to.',
					'radius-hotel-booking'
				)
			);
			return;
		}
		if ( check ) {
			ask( {
				confirm: true,
				// The bookings the user was shown; any other one asks again.
				booking_ids: ( check.bookings || [] ).map(
					( booking ) => booking.id
				),
				bookings: check.count,
			} );
		} else {
			ask();
		}
	};

	return (
		<Dialog open onOpenChange={ ( open ) => ! open && onClose() }>
			<DialogContent className="max-h-[90vh] max-w-md overflow-y-auto">
				<form onSubmit={ submit } noValidate className="space-y-4">
					<DialogHeader>
						<DialogTitle>
							{ sprintf(
								/* translators: %s: room number. */
								__( 'Move room %s', 'radius-hotel-booking' ),
								room.number
							) }
						</DialogTitle>
						<DialogDescription>
							{ sprintf(
								/* translators: %s: room type name. */
								__(
									'The room leaves %s and is sold under the room type you choose.',
									'radius-hotel-booking'
								),
								type.name
							) }
						</DialogDescription>
					</DialogHeader>

					<div className="space-y-1.5">
						<Label
							htmlFor="rtbp-move-type"
							className="text-sm font-semibold text-heading"
						>
							{ __( 'Move to', 'radius-hotel-booking' ) }
						</Label>
						<Select
							value={ target }
							onValueChange={ ( value ) => {
								setTarget( value );
								setCheck( null );
								setError( '' );
							} }
						>
							<SelectTrigger id="rtbp-move-type">
								<SelectValue
									placeholder={ __(
										'Choose a room type',
										'radius-hotel-booking'
									) }
								/>
							</SelectTrigger>
							<SelectContent>
								{ others.map( ( option ) => (
									<SelectItem
										key={ option.id }
										value={ String( option.id ) }
									>
										{ option.name }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>
						{ ! others.length ? (
							<p className="m-0 text-xs text-muted-foreground">
								{ __(
									'There is no other room type yet.',
									'radius-hotel-booking'
								) }
							</p>
						) : null }
					</div>

					{ check ? (
						check.count ? (
							<div className="space-y-2 rounded-lg border border-warning bg-warning-soft p-3 text-sm">
								<p className="m-0 flex items-start gap-2 font-semibold text-heading">
									<AlertTriangle
										className="mt-0.5 h-4 w-4 shrink-0 text-warning"
										aria-hidden="true"
									/>
									{ sprintf(
										/* translators: %d: number of bookings. */
										_n(
											'This room has %d upcoming booking. It keeps the room, which becomes busy under the new type.',
											'This room has %d upcoming bookings. They keep the room, which becomes busy under the new type.',
											check.count,
											'radius-hotel-booking'
										),
										check.count
									) }
								</p>
								{ check.bookings?.length ? (
									<ul className="m-0 list-none space-y-1 p-0">
										{ check.bookings.map( ( booking ) => (
											<li
												key={ booking.id }
												className="m-0 text-xs text-heading"
											>
												<span className="font-semibold">
													{ booking.reference }
												</span>
												{ booking.guest
													? ` · ${ booking.guest }`
													: '' }
												{ booking.start
													? ` · ${ formatDateTime(
															booking.start
													  ) }`
													: '' }
											</li>
										) ) }
									</ul>
								) : null }
							</div>
						) : (
							<p className="m-0 rounded-lg border border-border bg-muted p-3 text-sm text-heading">
								{ __(
									'No upcoming bookings. The room can be moved.',
									'radius-hotel-booking'
								) }
							</p>
						)
					) : null }

					{ error ? (
						<p
							role="alert"
							className="m-0 text-xs text-destructive"
						>
							{ error }
						</p>
					) : null }

					<DialogFooter className="gap-2">
						<Button
							type="button"
							variant="ghost"
							onClick={ onClose }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						<Button
							type="submit"
							disabled={ move.isPending || ! others.length }
						>
							{ check
								? __( 'Move room', 'radius-hotel-booking' )
								: __( 'Continue', 'radius-hotel-booking' ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
