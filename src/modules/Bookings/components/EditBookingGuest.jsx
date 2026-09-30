/**
 * The guest billing panel's *Edit* (3.14): the guest's contact and identity
 * document, changed from the booking with M09's own dialog and service, so
 * the change is logged against the guest exactly as from the guest record.
 * The guest's full record is loaded only when the dialog opens.
 */
import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Loader2, Pencil } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { toastError } from '@/lib/toast';
import EditGuestDialog from '@/modules/Guests/components/EditGuestDialog';
import { useGuest } from '@/modules/Guests/api';

/**
 * Loads the guest, then shows the dialog.
 *
 * @param {Object}   props         Props.
 * @param {number}   props.guestId Guest id.
 * @param {Function} props.onClose Close.
 * @return {JSX.Element|null} Dialog.
 */
function Loaded( { guestId, onClose } ) {
	const { data, error } = useGuest( guestId );
	useEffect( () => {
		if ( error ) {
			toastError( error );
			onClose();
		}
	}, [ error, onClose ] );
	if ( error ) {
		return null;
	}
	if ( ! data ) {
		return (
			<Loader2
				className="h-4 w-4 animate-spin text-muted-foreground"
				role="status"
				aria-label={ __( 'Loading…', 'radius-hotel-booking' ) }
			/>
		);
	}
	return (
		<EditGuestDialog
			guest={ data.guest }
			idTypes={ data.idTypes }
			onClose={ onClose }
		/>
	);
}

/**
 * @param {Object} props         Props.
 * @param {number} props.guestId Guest id.
 * @return {JSX.Element} Button (and the dialog while open).
 */
export default function EditBookingGuest( { guestId } ) {
	const [ open, setOpen ] = useState( false );
	return (
		<>
			<Button
				type="button"
				variant="outline"
				size="sm"
				onClick={ () => setOpen( true ) }
			>
				<Pencil className="h-4 w-4" aria-hidden="true" />
				{ __( 'Edit', 'radius-hotel-booking' ) }
			</Button>
			{ open ? (
				<Loaded
					guestId={ guestId }
					onClose={ () => setOpen( false ) }
				/>
			) : null }
		</>
	);
}
