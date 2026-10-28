/**
 * Express checkout (Settings → Guest accounts): a returning guest enters
 * their phone number and, when the hotel knows it, the 6-digit code sent to
 * them; their details then fill the form (Frontend\ExpressCheckout). A new
 * number only fills the phone field. Nothing personal is shown before the
 * code: a phone number alone proves nothing. With verification off, a known
 * number is `recognised` at once: a masked hint, and the server fills the
 * name, e-mail and document on file at booking.
 *
 * Light on purpose like RateDetailsDialog (no Radix dialog in the website's
 * bundle), in a portal on `<body>`, and loaded only when it opens.
 */
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { __, sprintf } from '@wordpress/i18n';
import { X } from 'lucide-react';

import { post } from '@/api/client';
import { Field } from '@/components/common/Form';
import PhoneField from '@/components/common/Phone';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { invalidPhoneMessage, isPhoneValid } from '@/lib/phone';

/**
 * @param {Object}   props          Props.
 * @param {string}   props.phone    The phone already in the form.
 * @param {Function} props.onKnown  Called with `( details, token )` after the code.
 * @param {Function} props.onRecognised Called with `{ token, hint, phone }` (no code asked).
 * @param {Function} props.onNew    Called with the phone (E.164) of a number to type details for.
 * @param {Function} props.onClose  Close without filling anything.
 * @return {JSX.Element} Popup.
 */
export default function ExpressCheckout( {
	phone: initial,
	onKnown,
	onRecognised,
	onNew,
	onClose,
} ) {
	const [ phone, setPhone ] = useState( initial || '' );
	const [ code, setCode ] = useState( '' );
	// `{ challenge, channel, to, phone }` once a code was sent.
	const [ sent, setSent ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const firstRef = useRef( null );
	const codeRef = useRef( null );

	// Escape closes, the page stops scrolling, focus goes back on close.
	useEffect( () => {
		const back = document.activeElement;
		const root = document.documentElement;
		const overflow = root.style.overflow;
		root.style.overflow = 'hidden';
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
	useEffect( () => {
		( sent ? codeRef : firstRef ).current
			?.querySelector?.( 'input' )
			?.focus();
	}, [ sent ] );

	const start = async ( event ) => {
		event?.preventDefault();
		if ( ! isPhoneValid( phone ) ) {
			setError( invalidPhoneMessage() );
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			const { data } = await post( 'public/express/start', { phone } );
			if ( 'code_sent' === data.status ) {
				setCode( '' );
				setSent( data );
			} else if ( 'recognised' === data.status ) {
				onRecognised( data );
			} else {
				onNew( data.phone || phone );
			}
		} catch ( err ) {
			setError( err?.errors?.phone?.first_message || err.message );
		} finally {
			setBusy( false );
		}
	};

	const verify = async ( event ) => {
		event.preventDefault();
		if ( ! /^\d{6}$/.test( code.replace( /\D/g, '' ) ) ) {
			setError( __( 'Enter the 6-digit code.', 'radius-hotel-booking' ) );
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			const { data } = await post( 'public/express/verify', {
				challenge: sent.challenge,
				code,
			} );
			onKnown( data.details, data.token );
		} catch ( err ) {
			if ( 'code_expired' === err?.code ) {
				// Start again from the number.
				setSent( null );
			}
			setError( err.message );
		} finally {
			setBusy( false );
		}
	};

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
					aria-labelledby="rtbp-express-title"
					className="w-full max-w-md rounded-t-2xl bg-card p-6 shadow-2xl duration-200 animate-in fade-in-0 zoom-in-95 motion-reduce:animate-none sm:rounded-2xl"
				>
					<div className="mb-1 flex items-start justify-between gap-3">
						<h2
							id="rtbp-express-title"
							className="m-0 text-xl font-bold text-heading"
						>
							{ __( 'Express checkout', 'radius-hotel-booking' ) }
						</h2>
						<button
							type="button"
							className="inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg border-0 bg-muted p-0 text-heading transition hover:bg-heading hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
							aria-label={ __( 'Close', 'radius-hotel-booking' ) }
							onClick={ onClose }
						>
							<X className="h-4 w-4" aria-hidden="true" />
						</button>
					</div>

					{ ! sent ? (
						<form
							onSubmit={ start }
							noValidate
							className="space-y-5"
						>
							<p className="m-0 text-sm text-muted-foreground">
								{ __(
									'Enter your phone number so we can recognise you and speed up your booking.',
									'radius-hotel-booking'
								) }
							</p>
							<div ref={ firstRef }>
								<Field
									label={ __(
										'Phone number',
										'radius-hotel-booking'
									) }
									error={ error }
								>
									<PhoneField
										value={ phone }
										onChange={ ( next ) => {
											setPhone( next );
											setError( '' );
										} }
									/>
								</Field>
							</div>
							<Button
								type="submit"
								className="h-11 w-full"
								disabled={ busy }
							>
								{ __( 'Continue', 'radius-hotel-booking' ) }
							</Button>
							<button
								type="button"
								className="m-0 block w-full cursor-pointer border-0 bg-transparent p-0 text-center text-sm font-medium text-muted-foreground underline-offset-2 hover:text-heading hover:underline"
								onClick={ onClose }
							>
								{ __(
									'Fill in my details myself',
									'radius-hotel-booking'
								) }
							</button>
						</form>
					) : (
						<form
							onSubmit={ verify }
							noValidate
							className="space-y-5"
						>
							<p className="m-0 text-sm text-muted-foreground">
								{ sprintf(
									/* translators: 1: e-mail or SMS, 2: where it went, e.g. "a•••@gmail.com". */
									__(
										'Welcome back! We sent a 6-digit code by %1$s to %2$s. Enter it to fill in your details.',
										'radius-hotel-booking'
									),
									sent.channel,
									sent.to
								) }
							</p>
							<div ref={ codeRef }>
								<Field
									label={ __(
										'Code',
										'radius-hotel-booking'
									) }
									error={ error }
								>
									<Input
										value={ code }
										onChange={ ( event ) => {
											setCode(
												event.target.value
													.replace( /\D/g, '' )
													.slice( 0, 6 )
											);
											setError( '' );
										} }
										inputMode="numeric"
										autoComplete="one-time-code"
										placeholder="123456"
										className="h-11 text-center text-lg tracking-[0.4em]"
									/>
								</Field>
							</div>
							<Button
								type="submit"
								className="h-11 w-full"
								disabled={ busy }
							>
								{ __(
									'Fill in my details',
									'radius-hotel-booking'
								) }
							</Button>
							<div className="flex flex-wrap justify-between gap-2 text-sm">
								<button
									type="button"
									className="m-0 cursor-pointer border-0 bg-transparent p-0 font-medium text-primary underline-offset-2 hover:underline"
									onClick={ () => start() }
									disabled={ busy }
								>
									{ __(
										'Send a new code',
										'radius-hotel-booking'
									) }
								</button>
								<button
									type="button"
									className="m-0 cursor-pointer border-0 bg-transparent p-0 font-medium text-muted-foreground underline-offset-2 hover:text-heading hover:underline"
									onClick={ () => {
										setSent( null );
										setError( '' );
									} }
								>
									{ __(
										'Use another number',
										'radius-hotel-booking'
									) }
								</button>
							</div>
						</form>
					) }
				</div>
			</div>
		</div>,
		document.body
	);
}
