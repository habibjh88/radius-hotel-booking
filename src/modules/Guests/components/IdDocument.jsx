/**
 * The guest's identity document (9.6): type and masked number (`••••3456`).
 * With `guests.view_id`, *Show* fetches the full number — a logged read —
 * and hides it again after a minute or on *Hide*. Nothing is cached.
 */
import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Eye, EyeOff, IdCard } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { toastError } from '@/lib/toast';
import { revealIdNumber } from '../api';

const SHOWN_FOR_MS = 60000;

/**
 * @param {Object} props       Props.
 * @param {Object} props.guest Guest (detail shape).
 * @return {JSX.Element} Card body.
 */
export default function IdDocument( { guest } ) {
	const [ number, setNumber ] = useState( null );
	const [ loading, setLoading ] = useState( false );

	// Hide again after a minute, and whenever the guest changes.
	useEffect( () => {
		if ( null === number ) {
			return undefined;
		}
		const timer = setTimeout( () => setNumber( null ), SHOWN_FOR_MS );
		return () => clearTimeout( timer );
	}, [ number ] );
	useEffect( () => setNumber( null ), [ guest.id, guest.id_number_masked ] );

	if ( ! guest.has_id ) {
		return (
			<p className="m-0 text-sm text-muted-foreground">
				{ guest.id_type
					? guest.id_type_label
					: __( 'No document on file.', 'radius-hotel-booking' ) }
			</p>
		);
	}

	const show = async () => {
		setLoading( true );
		try {
			const data = await revealIdNumber( guest.id );
			setNumber( data.id_number );
		} catch ( err ) {
			toastError( err );
		} finally {
			setLoading( false );
		}
	};

	return (
		<div className="flex flex-wrap items-center justify-between gap-3">
			<div className="flex min-w-0 items-center gap-3">
				<IdCard
					className="h-5 w-5 shrink-0 text-muted-foreground"
					aria-hidden="true"
				/>
				<div className="min-w-0">
					<p className="m-0 text-xs text-muted-foreground">
						{ guest.id_type_label }
					</p>
					<p
						className="m-0 font-mono text-sm font-semibold text-heading"
						aria-live="polite"
					>
						{ number ?? guest.id_number_masked }
					</p>
				</div>
			</div>
			{ guest.can_view_id ? (
				<Button
					type="button"
					variant="outline"
					size="sm"
					disabled={ loading }
					onClick={ () => ( number ? setNumber( null ) : show() ) }
				>
					{ number ? (
						<EyeOff className="h-4 w-4" aria-hidden="true" />
					) : (
						<Eye className="h-4 w-4" aria-hidden="true" />
					) }
					{ number
						? __( 'Hide', 'radius-hotel-booking' )
						: __( 'Show number', 'radius-hotel-booking' ) }
				</Button>
			) : null }
		</div>
	);
}
