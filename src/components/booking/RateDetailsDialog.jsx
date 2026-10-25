/**
 * A rate's details in a popup ("Rate details" under each rate of the booking
 * form): its name, its features (Non-refundable…), when guests check in and
 * out (typed on the rate plan, else worked out from its times), and the
 * rate plan's policy text (Rate plans → Policy).
 *
 * Light on purpose like RoomDetailsDialog (no Radix dialog in the website's
 * bundle), in a portal on `<body>` above the booking popup.
 */
import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { __, sprintf } from '@wordpress/i18n';
import { CircleCheck, X } from 'lucide-react';

import { stayRules } from '@/lib/stayWindow';

/**
 * The check-in / check-out lines: the ones typed on the rate plan, else
 * worked out from the plan's times (`stayRules()`).
 *
 * @param {Object} rate The rate (`checkin_rules`, `window`, `checkin`).
 * @return {string[]} Lines.
 */
function rulesOf( rate ) {
	if ( rate.checkin_rules?.length ) {
		return rate.checkin_rules;
	}
	if ( rate.checkin ) {
		return stayRules( {
			type: 'flexible',
			duration_minutes: rate.window?.minutes,
			checkin_from: rate.checkin.from,
			checkin_until: rate.checkin.until,
		} );
	}
	// The window's wall-clock times (`2026-10-27T20:00:00+00:00` → `20:00`).
	return rate.window
		? stayRules( {
				type: 'fixed',
				start_time: String( rate.window.start ).slice( 11, 16 ),
				end_time: String( rate.window.end ).slice( 11, 16 ),
		  } )
		: [];
}

/**
 * @param {Object}   props         Props.
 * @param {Object}   props.rate    The rate (`name`, `features`, `policy`, `window`, `checkin`).
 * @param {Function} props.onClose Close.
 * @return {JSX.Element} Popup.
 */
export default function RateDetailsDialog( { rate, onClose } ) {
	const closeRef = useRef( null );
	const titleId = `rtbp-rate-${ rate.rate_plan_id }`;
	const rules = rulesOf( rate );

	// Escape closes, the page stops scrolling, focus moves in and goes back on close.
	useEffect( () => {
		const back = document.activeElement;
		const root = document.documentElement;
		const overflow = root.style.overflow;
		root.style.overflow = 'hidden';
		closeRef.current?.focus();
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => {
			root.style.overflow = overflow;
			document.removeEventListener( 'keydown', onKey );
			back?.focus?.();
		};
	}, [ onClose ] );

	return createPortal(
		// Utilities are scoped *under* .rtbp-root, so the overlay is its child.
		<div className="rtbp-root" data-rtbp-nested-dialog>
			<div
				className="fixed inset-0 z-[100020] flex items-end justify-center bg-black/60 duration-200 animate-in fade-in-0 motion-reduce:animate-none sm:items-center sm:p-6"
				onMouseDown={ ( event ) => {
					if ( event.target === event.currentTarget ) {
						onClose();
					}
				} }
			>
				<div
					role="dialog"
					aria-modal="true"
					aria-labelledby={ titleId }
					className="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-clip rounded-t-2xl bg-card shadow-2xl duration-200 animate-in fade-in-0 zoom-in-95 motion-reduce:animate-none sm:max-h-[min(40rem,calc(100vh-3rem))] sm:rounded-2xl"
				>
					<div className="flex shrink-0 items-start justify-between gap-3 px-5 py-4">
						<h2
							id={ titleId }
							className="m-0 text-lg font-bold text-heading sm:text-xl"
						>
							{ rate.name }
						</h2>
						<button
							ref={ closeRef }
							type="button"
							className="inline-flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-lg border-0 bg-muted p-0 text-heading transition hover:bg-heading hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
							aria-label={ __( 'Close', 'radius-hotel-booking' ) }
							onClick={ onClose }
						>
							<X className="h-5 w-5" aria-hidden="true" />
						</button>
					</div>

					{ rate.features?.length ? (
						<ul className="m-0 flex shrink-0 list-none flex-wrap gap-x-5 gap-y-1.5 bg-muted px-5 py-3">
							{ rate.features.map( ( feature ) => (
								<li
									key={ feature }
									className="m-0 flex items-center gap-1.5 text-sm text-foreground"
								>
									<CircleCheck
										className="h-4 w-4 shrink-0 text-muted-foreground"
										aria-hidden="true"
									/>
									{ feature }
								</li>
							) ) }
						</ul>
					) : null }

					<div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
						{ rules.length ? (
							<section className="space-y-2">
								<h3 className="m-0 text-base font-bold text-heading">
									{ __(
										'Check-in & check-out',
										'radius-hotel-booking'
									) }
								</h3>
								<ul className="m-0 list-disc space-y-1 pl-5 text-sm text-foreground">
									{ rules.map( ( rule ) => (
										<li key={ rule } className="m-0">
											{ rule }
										</li>
									) ) }
								</ul>
							</section>
						) : null }
						{ rate.policy ? (
							<section className="space-y-2">
								<h3 className="m-0 text-base font-bold text-heading">
									{ __( 'Policy', 'radius-hotel-booking' ) }
								</h3>
								<div
									className="space-y-2 text-sm text-foreground [&_a]:text-primary [&_a]:underline [&_ol]:m-0 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pl-5 [&_p]:m-0 [&_ul]:m-0 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5"
									// Basic formatting only: cleaned with wp_kses when saved and again when sent.
									dangerouslySetInnerHTML={ {
										__html: rate.policy,
									} }
								/>
							</section>
						) : null }
					</div>
				</div>
			</div>
		</div>,
		document.body
	);
}
