/**
 * The booking form in a popup (`[rtbp_booking display="modal"]`): a button
 * where the shortcode sits; the form opens over the page. Any link to
 * `#rtbp-booking` (or element with `data-rtbp-booking`) on the page opens it
 * too — a menu item, a theme's header button.
 *
 * Light on purpose (no Radix dialog in the website's bundle). The popup is
 * mounted on first open and then only hidden, so a guest who closes it finds
 * their booking as they left it (held rooms stay held until they expire).
 * It renders in a portal on `<body>`, out of any theme or builder container
 * whose transform or overflow would trap a fixed element.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { __ } from '@wordpress/i18n';
import { X } from 'lucide-react';

// The same look as the template's fallback link (templates/embeds/booking.php).
const BUTTON =
	'rtbp-book-now inline-flex min-h-[44px] cursor-pointer items-center justify-center rounded-lg bg-primary px-6 py-2.5 text-base font-semibold text-primary-foreground no-underline shadow-sm hover:bg-primary-hover hover:text-primary-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';

const OPENERS = 'a[href$="#rtbp-booking"], [data-rtbp-booking]';

// Only the first popup on a page answers the page's own links.
let claimed = false;

/**
 * @param {Object}   props          Props.
 * @param {string}   props.label    The button's text.
 * @param {Function} props.children `( open ) => JSX`: the form.
 * @return {JSX.Element} Button and popup.
 */
export default function BookingModal( { label, children } ) {
	const [ open, setOpen ] = useState( false );
	const [ used, setUsed ] = useState( false );
	const triggerRef = useRef( null );
	const closeRef = useRef( null );
	const opener = useRef( null );

	const show = useCallback( ( from ) => {
		opener.current = from || triggerRef.current;
		setUsed( true );
		setOpen( true );
	}, [] );
	const hide = useCallback( () => setOpen( false ), [] );

	// The page's own "Book now" links.
	useEffect( () => {
		if ( claimed ) {
			return undefined;
		}
		claimed = true;
		const onClick = ( event ) => {
			const link = event.target.closest?.( OPENERS );
			if ( ! link ) {
				return;
			}
			event.preventDefault();
			show( link );
		};
		document.addEventListener( 'click', onClick );
		if ( '#rtbp-booking' === window.location.hash ) {
			show( null );
		}
		return () => {
			document.removeEventListener( 'click', onClick );
			claimed = false;
		};
	}, [ show ] );

	// Open: the page stops scrolling, Escape closes, focus moves in; closed:
	// focus goes back to what opened it.
	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const root = document.documentElement;
		const overflow = root.style.overflow;
		root.style.overflow = 'hidden';
		closeRef.current?.focus();
		const onKey = ( event ) => {
			// The leave prompt, the photo lightbox and the room details answer Escape themselves.
			if (
				'Escape' === event.key &&
				! document.querySelector(
					'.rtbp-modal [role="alertdialog"], .rtbp-lightbox, [data-rtbp-nested-dialog]'
				)
			) {
				hide();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => {
			root.style.overflow = overflow;
			document.removeEventListener( 'keydown', onKey );
			opener.current?.focus?.();
		};
	}, [ open, hide ] );

	return (
		<>
			<button
				ref={ triggerRef }
				type="button"
				className={ BUTTON }
				aria-haspopup="dialog"
				aria-expanded={ open }
				onClick={ () => show( triggerRef.current ) }
			>
				{ label }
			</button>
			{ used
				? createPortal(
						// Utilities are scoped *under* .rtbp-root, so the overlay is its child.
						<div className="rtbp-root rtbp-modal">
							<div
								className={ `fixed inset-0 z-[100000] items-stretch justify-center bg-black/60 sm:items-start sm:p-6 ${
									open ? 'flex' : 'hidden'
								}` }
								onMouseDown={ ( event ) => {
									if (
										event.target === event.currentTarget
									) {
										hide();
									}
								} }
							>
								<div
									role="dialog"
									aria-modal="true"
									aria-label={ __(
										'Book your stay',
										'radius-hotel-booking'
									) }
									className="relative flex h-full w-full max-w-[1100px] flex-col overflow-clip bg-app shadow-2xl sm:h-auto sm:max-h-[calc(100vh-3rem)] sm:rounded-2xl"
								>
									<div className="flex shrink-0 items-center justify-between gap-3 border-b border-border bg-card px-4 py-2 sm:px-6">
										<h2 className="m-0 text-lg font-bold text-heading">
											{ __(
												'Book your stay',
												'radius-hotel-booking'
											) }
										</h2>
										<button
											ref={ closeRef }
											type="button"
											className="inline-flex h-11 w-11 cursor-pointer items-center justify-center rounded-full bg-transparent p-0 text-muted-foreground hover:bg-muted hover:text-heading focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
											aria-label={ __(
												'Close',
												'radius-hotel-booking'
											) }
											onClick={ hide }
										>
											<X
												className="h-5 w-5"
												aria-hidden="true"
											/>
										</button>
									</div>
									<div
										data-rtbp-scroll
										className="min-h-0 flex-1 overflow-y-auto p-4 [--rtbp-sticky-top:1rem] sm:p-6"
									>
										{ children( open ) }
									</div>
								</div>
							</div>
						</div>,
						document.body
				  )
				: null }
		</>
	);
}
