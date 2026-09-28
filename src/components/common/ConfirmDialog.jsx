/**
 * Confirmation dialog for actions that change something important: decline
 * or cancel a booking, void a payment, ban a guest.
 *
 *   <ConfirmDialog
 *       open={ open } onOpenChange={ setOpen }
 *       title={ __( 'Cancel this booking?', 'radius-hotel-booking' ) }
 *       description={ __( 'The room is released immediately.', 'radius-hotel-booking' ) }
 *       confirmLabel={ __( 'Cancel booking', 'radius-hotel-booking' ) }
 *       destructive
 *       requireReason
 *       onConfirm={ ( reason ) => cancel.mutateAsync( { reason } ) }
 *   />
 *
 * `onConfirm` may return a promise: the dialog shows a busy state, closes on
 * success, and stays open showing the error on failure.
 */
import { useEffect, useId, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
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
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/**
 * @param {Object}   props                Props.
 * @param {boolean}  props.open           Open state.
 * @param {Function} props.onOpenChange   Open-state setter.
 * @param {string}   props.title          Question, e.g. "Cancel this booking?".
 * @param {string}   props.description    What will happen.
 * @param {string}   props.confirmLabel   Confirm button text (a verb).
 * @param {string}   props.cancelLabel    Dismiss button text.
 * @param {boolean}  props.destructive    Red confirm button and warning icon.
 * @param {boolean}  props.requireReason  Ask for a reason before confirming.
 * @param {string}   props.reasonLabel    Label of the reason field.
 * @param {number}   props.reasonMinLength Minimum reason length.
 * @param {Function} props.onConfirm      Called with the reason ('' when not asked).
 * @return {JSX.Element} Dialog.
 */
export default function ConfirmDialog( {
	open,
	onOpenChange,
	title,
	description,
	confirmLabel = __( 'Confirm', 'radius-hotel-booking' ),
	cancelLabel = __( 'Go back', 'radius-hotel-booking' ),
	destructive = false,
	requireReason = false,
	reasonLabel = __( 'Reason', 'radius-hotel-booking' ),
	reasonMinLength = 3,
	onConfirm,
} ) {
	const reasonId = useId();
	const [ reason, setReason ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	// A fresh form each time the dialog opens.
	useEffect( () => {
		if ( open ) {
			setReason( '' );
			setError( '' );
			setBusy( false );
		}
	}, [ open ] );

	const reasonOk = ! requireReason || reason.trim().length >= reasonMinLength;

	const confirm = async () => {
		if ( ! reasonOk || busy ) {
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			await onConfirm?.( reason.trim() );
			onOpenChange( false );
		} catch ( e ) {
			setError(
				e?.message ||
					__(
						'Something went wrong. Please try again.',
						'radius-hotel-booking'
					)
			);
		} finally {
			setBusy( false );
		}
	};

	return (
		<Dialog
			open={ open }
			onOpenChange={ ( next ) => ! busy && onOpenChange( next ) }
		>
			<DialogContent className="max-w-md">
				<DialogHeader className="flex-row items-start gap-3 space-y-0 text-left">
					{ destructive ? (
						<span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-destructive-soft text-destructive">
							<AlertTriangle
								className="h-5 w-5"
								aria-hidden="true"
							/>
						</span>
					) : null }
					<div className="min-w-0 space-y-1">
						<DialogTitle className="m-0 text-base font-semibold text-heading">
							{ title }
						</DialogTitle>
						{ description ? (
							<DialogDescription className="m-0 text-[13px] text-muted-foreground">
								{ description }
							</DialogDescription>
						) : null }
					</div>
				</DialogHeader>

				{ requireReason ? (
					<div className="space-y-1.5">
						<Label
							htmlFor={ reasonId }
							className="text-sm font-semibold text-heading"
						>
							{ reasonLabel }
						</Label>
						<Textarea
							id={ reasonId }
							value={ reason }
							rows={ 3 }
							onChange={ ( event ) =>
								setReason( event.target.value )
							}
							disabled={ busy }
						/>
						<p className="m-0 text-xs text-muted-foreground">
							{ sprintf(
								/* translators: %d: minimum number of characters. */
								__(
									'At least %d characters. Staff and the activity log will see it.',
									'radius-hotel-booking'
								),
								reasonMinLength
							) }
						</p>
					</div>
				) : null }

				{ error ? (
					<p
						role="alert"
						className="m-0 rounded-lg bg-destructive-soft px-3 py-2 text-[13px] text-destructive"
					>
						{ error }
					</p>
				) : null }

				<DialogFooter className="gap-2 sm:gap-0">
					<Button
						variant="outline"
						onClick={ () => onOpenChange( false ) }
						disabled={ busy }
					>
						{ cancelLabel }
					</Button>
					<Button
						variant={ destructive ? 'destructive' : 'default' }
						onClick={ confirm }
						disabled={ ! reasonOk || busy }
						className={ cn( busy && 'cursor-wait' ) }
					>
						{ busy
							? __( 'Working…', 'radius-hotel-booking' )
							: confirmLabel }
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	);
}
