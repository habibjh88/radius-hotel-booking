/**
 * Stops unsaved changes from being lost on the way out of a screen.
 *
 *   <LeaveGuard when={ isDirty } onSave={ saveAndReport } />
 *
 * While `when` is true:
 * - a click on any link to another screen of the app (sidebar, "Manage
 *   list", breadcrumbs …) is held, and a dialog offers: keep editing, leave
 *   without saving, or (with `onSave`) save and leave;
 * - reloading, closing the tab or leaving wp-admin asks the browser's own
 *   "Leave site?" question.
 *
 * HashRouter has no navigation blocker, so links are caught where the click
 * starts (capture phase, before React Router sees it). `onSave` resolves to
 * true when the save worked; the visitor then goes where they clicked.
 */
import { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { AlertTriangle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogTitle,
} from '@/components/ui/dialog';

/**
 * The in-app target of a click, or null when it does not leave the screen.
 *
 * @param {MouseEvent} event Click.
 * @return {string|null} The `#/…` hash to go to.
 */
function targetOf( event ) {
	if (
		event.defaultPrevented ||
		0 !== event.button ||
		event.metaKey ||
		event.ctrlKey ||
		event.shiftKey ||
		event.altKey
	) {
		return null; // A new tab or window keeps this screen as it is.
	}
	const link = event.target.closest?.( 'a[href]' );
	if (
		! link ||
		( link.target && '_self' !== link.target ) ||
		link.hasAttribute( 'download' )
	) {
		return null;
	}
	const url = new URL( link.getAttribute( 'href' ), window.location.href );
	const here = new URL( window.location.href );
	if (
		url.origin !== here.origin ||
		url.pathname !== here.pathname ||
		url.search !== here.search
	) {
		return null; // Another page: the browser's own warning covers it.
	}
	// The same screen (only its `?tab=` / `?section=` changes) keeps the edits.
	const path = ( hash ) => hash.split( '?' )[ 0 ];
	if ( ! url.hash || path( url.hash ) === path( here.hash ) ) {
		return null;
	}
	return url.hash;
}

/**
 * @param {Object}   props        Props.
 * @param {boolean}  props.when   There are unsaved changes.
 * @param {Function} props.onSave Optional: save now; resolves to true when it worked.
 * @return {JSX.Element} The dialog (closed until needed).
 */
export default function LeaveGuard( { when, onSave } ) {
	const [ pending, setPending ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const bypass = useRef( false );
	const keepRef = useRef( null );

	useEffect( () => {
		if ( ! when ) {
			return undefined;
		}
		const onClick = ( event ) => {
			if ( bypass.current ) {
				return;
			}
			const hash = targetOf( event );
			if ( ! hash ) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			setPending( hash );
		};
		const onUnload = ( event ) => {
			event.preventDefault();
			// Older browsers need a value; the text itself is not shown.
			event.returnValue = '';
		};
		document.addEventListener( 'click', onClick, true );
		window.addEventListener( 'beforeunload', onUnload );
		return () => {
			document.removeEventListener( 'click', onClick, true );
			window.removeEventListener( 'beforeunload', onUnload );
		};
	}, [ when ] );

	const go = () => {
		const hash = pending;
		setPending( null );
		bypass.current = true;
		window.location.hash = hash;
		// Ready again for the next screen's own guard.
		window.setTimeout( () => {
			bypass.current = false;
		}, 0 );
	};

	const saveAndGo = async () => {
		setSaving( true );
		try {
			if ( await onSave() ) {
				go();
			} else {
				setPending( null ); // The form shows what to fix.
			}
		} finally {
			setSaving( false );
		}
	};

	return (
		<Dialog
			open={ Boolean( pending ) }
			onOpenChange={ ( open ) =>
				! open && ! saving && setPending( null )
			}
		>
			<DialogContent
				className="w-[calc(100vw-2rem)] gap-0 overflow-hidden p-0 sm:max-w-[34rem]"
				// The safe choice has the focus: Enter never throws changes away.
				onOpenAutoFocus={ ( event ) => {
					event.preventDefault();
					// After the buttons are in place.
					window.requestAnimationFrame( () =>
						keepRef.current?.focus()
					);
				} }
			>
				<div className="flex gap-4 p-6 pr-12">
					<span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-warning-soft text-warning">
						<AlertTriangle className="h-5 w-5" aria-hidden="true" />
					</span>
					<div className="min-w-0 space-y-1.5 pt-0.5">
						<DialogTitle className="m-0 text-lg font-bold leading-snug text-heading">
							{ __(
								'Leave without saving?',
								'radius-hotel-booking'
							) }
						</DialogTitle>
						<DialogDescription className="m-0 text-sm leading-relaxed text-muted-foreground">
							{ __(
								'You have unsaved changes on this page. If you leave now, they are lost.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</div>
				</div>
				{ /* "Keep editing" comes first, so it has the focus (Enter keeps the
				changes); "Leave without saving" is drawn on the left. */ }
				<div className="flex flex-col gap-2 border-t border-border bg-muted/40 px-6 py-4 sm:flex-row-reverse sm:items-center sm:justify-between">
					<div className="flex flex-col gap-2 sm:flex-row">
						<Button
							ref={ keepRef }
							type="button"
							variant="outline"
							onClick={ () => setPending( null ) }
							disabled={ saving }
						>
							{ __( 'Keep editing', 'radius-hotel-booking' ) }
						</Button>
						{ onSave ? (
							<Button
								type="button"
								onClick={ saveAndGo }
								disabled={ saving }
							>
								{ saving
									? __( 'Saving…', 'radius-hotel-booking' )
									: __(
											'Save and leave',
											'radius-hotel-booking'
									  ) }
							</Button>
						) : null }
					</div>
					<Button
						type="button"
						variant="ghost"
						className="text-destructive hover:bg-destructive-soft hover:text-destructive focus:bg-destructive-soft focus:text-destructive sm:-ml-3"
						onClick={ go }
						disabled={ saving }
					>
						{ __( 'Leave without saving', 'radius-hotel-booking' ) }
					</Button>
				</div>
			</DialogContent>
		</Dialog>
	);
}
