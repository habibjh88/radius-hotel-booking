/**
 * Check a room in (3.9): keep the booked room, or move the guest to another
 * free room of the same type for the same stay (the price does not change).
 * The rooms offered come from the server; it checks again under the lock.
 */
import { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { formatDateRange } from '@/lib/format';
import { useFreeRoomsFor } from '../api';

/**
 * @param {Object}      props           Props.
 * @param {Object|null} props.line      The line to check in (null = closed).
 * @param {Function}    props.onClose   Close.
 * @param {Function}    props.onConfirm Called with the room id (0 = keep); returns a promise.
 * @return {JSX.Element} Dialog.
 */
export default function CheckInDialog( { line, onClose, onConfirm } ) {
	const rooms = useFreeRoomsFor( line );
	const [ room, setRoom ] = useState( 'keep' );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		setRoom( 'keep' );
	}, [ line?.id ] );

	const confirm = async () => {
		setBusy( true );
		try {
			await onConfirm( 'keep' === room ? 0 : Number( room ) );
			onClose();
		} catch ( e ) {
			// The caller shows the error; the dialog stays open to choose again.
		} finally {
			setBusy( false );
		}
	};

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
										'Check in room %s',
										'radius-hotel-booking'
									),
									line.room_number
							  )
							: '' }
					</DialogTitle>
					<DialogDescription>
						{ line ? formatDateRange( line.start, line.end ) : '' }
					</DialogDescription>
				</DialogHeader>
				<Field
					label={ __( 'Room', 'radius-hotel-booking' ) }
					description={ __(
						'Move the guest to another free room of the same type if needed. The price stays the same.',
						'radius-hotel-booking'
					) }
				>
					<Select value={ room } onValueChange={ setRoom }>
						<SelectTrigger>
							<SelectValue />
						</SelectTrigger>
						<SelectContent>
							<SelectItem value="keep">
								{ line
									? sprintf(
											/* translators: %s: room number. */
											__(
												'Keep room %s',
												'radius-hotel-booking'
											),
											line.room_number
									  )
									: '' }
							</SelectItem>
							{ ( rooms.data || [] ).map( ( option ) => (
								<SelectItem
									key={ option.id }
									value={ String( option.id ) }
								>
									{ sprintf(
										/* translators: 1: room number, 2: floor name. */
										__(
											'Room %1$s · %2$s',
											'radius-hotel-booking'
										),
										option.number,
										option.floor
									) }
								</SelectItem>
							) ) }
						</SelectContent>
					</Select>
				</Field>
				{ rooms.data && ! rooms.data.length ? (
					<p className="m-0 text-xs text-muted-foreground">
						{ __(
							'No other room of this type is free for this stay.',
							'radius-hotel-booking'
						) }
					</p>
				) : null }
				<DialogFooter>
					<Button type="button" variant="ghost" onClick={ onClose }>
						{ __( 'Cancel', 'radius-hotel-booking' ) }
					</Button>
					<Button type="button" disabled={ busy } onClick={ confirm }>
						{ __( 'Check in', 'radius-hotel-booking' ) }
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	);
}
