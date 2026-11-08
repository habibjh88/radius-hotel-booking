/**
 * Settings → Booking rules → Booking reference: give existing bookings the
 * saved format (administrators). Shows what would change first, then
 * renumbers on confirm (`GET` / `POST settings/booking/renumber`).
 */
import { useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ArrowRight, ListOrdered } from 'lucide-react';

import { get, post } from '@/api/client';
import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import { toast, toastError } from '@/lib/toast';

/**
 * Old → new reference pairs.
 *
 * @param {Object} props      Props.
 * @param {Array}  props.rows `[ { from, to } ]`.
 * @return {JSX.Element} List.
 */
function Pairs( { rows } ) {
	return (
		<ul className="m-0 list-none space-y-1.5 rounded-lg border border-border bg-muted/40 p-3">
			{ rows.map( ( row ) => (
				<li
					key={ row.from }
					className="flex items-center gap-2 text-sm tabular-nums"
				>
					<span className="text-muted-foreground line-through decoration-muted-foreground/50">
						{ row.from }
					</span>
					<ArrowRight
						className="h-3.5 w-3.5 shrink-0 text-muted-foreground"
						aria-hidden="true"
					/>
					<span className="font-semibold text-heading">
						{ row.to }
					</span>
				</li>
			) ) }
		</ul>
	);
}

/**
 * @param {Object}  props       Props.
 * @param {boolean} props.dirty The format has unsaved changes (renumbering uses the saved one).
 * @return {JSX.Element|null} Control.
 */
export default function RenumberBookings( { dirty } ) {
	const [ preview, setPreview ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	if ( ! window.radius_hotel_booking_param?.current_user?.is_admin ) {
		return null;
	}

	const open = () => {
		setBusy( true );
		get( 'settings/booking/renumber' )
			.then( ( { data } ) => setPreview( data ) )
			.catch( toastError )
			.finally( () => setBusy( false ) );
	};

	const apply = () => {
		setBusy( true );
		post( 'settings/booking/renumber' )
			.then( ( { message } ) => {
				toast.success( message );
				setPreview( null );
			} )
			.catch( toastError )
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="flex flex-col gap-3 rounded-lg border border-border p-4 sm:flex-row sm:items-center sm:justify-between">
			<div className="min-w-0">
				<p className="m-0 text-sm font-semibold text-heading">
					{ __( 'Existing bookings', 'radius-hotel-booking' ) }
				</p>
				<p className="m-0 text-sm text-muted-foreground">
					{ dirty
						? __(
								'Save the new format first, then renumber the existing bookings to match it.',
								'radius-hotel-booking'
						  )
						: __(
								'They keep their reference until you renumber them to the saved format. Each keeps its number.',
								'radius-hotel-booking'
						  ) }
				</p>
			</div>
			<Button
				type="button"
				variant="outline"
				className="shrink-0"
				disabled={ busy || dirty }
				onClick={ open }
			>
				<ListOrdered className="h-4 w-4" aria-hidden="true" />
				{ __( 'Renumber existing bookings', 'radius-hotel-booking' ) }
			</Button>

			<Dialog
				open={ !! preview }
				onOpenChange={ ( next ) =>
					! next && ! busy && setPreview( null )
				}
			>
				<DialogContent className="max-w-md">
					<DialogHeader className="text-left">
						<DialogTitle>
							{ __(
								'Renumber existing bookings?',
								'radius-hotel-booking'
							) }
						</DialogTitle>
						<DialogDescription>
							{ preview?.changes
								? sprintf(
										/* translators: 1: bookings to renumber, 2: all bookings. */
										_n(
											'%1$d of %2$d booking gets the new format.',
											'%1$d of %2$d bookings get the new format.',
											preview.changes,
											'radius-hotel-booking'
										),
										preview.changes,
										preview.total
								  )
								: __(
										'Every booking already has the saved format.',
										'radius-hotel-booking'
								  ) }
						</DialogDescription>
					</DialogHeader>

					{ preview?.examples?.length ? (
						<Pairs rows={ preview.examples } />
					) : null }

					{ preview?.conflicts ? (
						<div className="space-y-2">
							<p className="m-0 text-sm text-warning">
								{ sprintf(
									/* translators: %d: number of bookings. */
									_n(
										'%d booking keeps its reference: another booking would get the same one.',
										'%d bookings keep their reference: other bookings would get the same ones.',
										preview.conflicts,
										'radius-hotel-booking'
									),
									preview.conflicts
								) }
							</p>
							<Pairs rows={ preview.conflict_examples } />
						</div>
					) : null }

					{ preview?.changes ? (
						<p className="m-0 text-sm text-muted-foreground">
							{ __(
								'The old reference stays searchable, so guests quoting it from an e-mail or a payment message are still found. Invoices already issued keep the reference printed on them.',
								'radius-hotel-booking'
							) }
						</p>
					) : null }

					<DialogFooter>
						<Button
							type="button"
							variant="ghost"
							disabled={ busy }
							onClick={ () => setPreview( null ) }
						>
							{ preview?.changes
								? __( 'Go back', 'radius-hotel-booking' )
								: __( 'Close', 'radius-hotel-booking' ) }
						</Button>
						{ preview?.changes ? (
							<Button
								type="button"
								disabled={ busy }
								onClick={ apply }
							>
								{ sprintf(
									/* translators: %d: number of bookings. */
									_n(
										'Renumber %d booking',
										'Renumber %d bookings',
										preview.changes,
										'radius-hotel-booking'
									),
									preview.changes
								) }
							</Button>
						) : null }
					</DialogFooter>
				</DialogContent>
			</Dialog>
		</div>
	);
}
