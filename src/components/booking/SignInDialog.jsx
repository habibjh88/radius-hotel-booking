/**
 * The booking page's account popup: a guest signs in, or creates an account,
 * without leaving the page (and their room holds); the form then fills in
 * from their account.
 *
 * Sign in — `POST public/account/sign-in` (Frontend\GuestAccount::signIn())
 * signs in through WordPress itself, so a phone number written any way, an
 * e-mail or a username all work, and security plugins apply as on
 * wp-login.php. Only this site's pages may call it, wrong passwords pause the
 * login, and the answer never says whether an account exists.
 *
 * Create an account — shown while registration is open
 * (GuestAccount::registrationOpen()): `POST public/account/register` makes a
 * guest account from the name, phone, e-mail and password, and signs it in.
 * A field nobody sees (`website`) turns bots away.
 *
 * The cookies come with the answer; the account is then asked again
 * (`public-account`). Light like ExpressCheckout (no Radix dialog in the
 * website's bundle), in a portal on `<body>`, and loaded only when it opens.
 */
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useQueryClient } from '@tanstack/react-query';
import { __, sprintf } from '@wordpress/i18n';
import {
	Eye,
	EyeOff,
	Loader2,
	LockKeyhole,
	UserRoundPlus,
	X,
} from 'lucide-react';

import { get, post } from '@/api/client';
import { Field } from '@/components/common/Form';
import PhoneField from '@/components/common/Phone';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { isPhoneValid } from '@/lib/phone';
import { cn } from '@/lib/utils';
import HotelBrand, { hasHotelBrand } from './HotelBrand';

/**
 * A WordPress link that brings the visitor back to this page afterwards.
 *
 * @param {string} url WordPress URL.
 * @return {string} URL.
 */
const backHere = ( url ) =>
	url
		? `${ url }${
				url.includes( '?' ) ? '&' : '?'
		  }redirect_to=${ encodeURIComponent( window.location.href ) }`
		: '';

/**
 * The field errors of an API error (`{ first_message }` or a string).
 *
 * @param {Object} err API error.
 * @return {Object<string, string>} Field => message.
 */
const fieldErrors = ( err ) => {
	const out = {};
	Object.entries( err?.errors || {} ).forEach( ( [ key, value ] ) => {
		out[ key ] = value?.first_message || String( value );
	} );
	return out;
};

const linkClass = 'font-medium text-primary underline-offset-2 hover:underline';

/**
 * A password input with a show / hide button.
 *
 * @param {Object}   props              Props.
 * @param {Object}   props.inputRef     Ref.
 * @param {string}   props.value        Value.
 * @param {Function} props.onChange     Called with the value.
 * @param {string}   props.autoComplete `current-password` | `new-password`.
 * @param {boolean}  props.invalid      Has an error.
 * @return {JSX.Element} Input.
 */
function PasswordInput( { inputRef, value, onChange, autoComplete, invalid } ) {
	const [ shown, setShown ] = useState( false );
	return (
		<div className="relative">
			<Input
				ref={ inputRef }
				name="password"
				type={ shown ? 'text' : 'password' }
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
				autoComplete={ autoComplete }
				autoCapitalize="none"
				autoCorrect="off"
				spellCheck={ false }
				aria-invalid={ invalid }
				className="h-11 pr-11"
			/>
			<button
				type="button"
				className="absolute inset-y-0 right-0 m-0 inline-flex w-11 cursor-pointer items-center justify-center rounded-r-md border-0 bg-transparent p-0 text-muted-foreground hover:text-heading focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
				aria-label={
					shown
						? __( 'Hide password', 'radius-hotel-booking' )
						: __( 'Show password', 'radius-hotel-booking' )
				}
				aria-pressed={ shown }
				onClick={ () => setShown( ( on ) => ! on ) }
			>
				{ shown ? (
					<EyeOff className="h-4 w-4" aria-hidden="true" />
				) : (
					<Eye className="h-4 w-4" aria-hidden="true" />
				) }
			</button>
		</div>
	);
}

/**
 * "Keep me signed in".
 *
 * @param {Object}   props          Props.
 * @param {boolean}  props.checked  Checked.
 * @param {Function} props.onChange Called with the new state.
 * @return {JSX.Element} Checkbox.
 */
function RememberBox( { checked, onChange } ) {
	return (
		<label className="flex min-h-[44px] cursor-pointer items-center gap-2 text-sm">
			<input
				type="checkbox"
				className="h-4 w-4 shrink-0 accent-primary"
				checked={ checked }
				onChange={ ( event ) => onChange( event.target.checked ) }
			/>
			{ __( 'Keep me signed in', 'radius-hotel-booking' ) }
		</label>
	);
}

/**
 * The submit button, with its busy state.
 *
 * @param {Object}  props          Props.
 * @param {boolean} props.busy     Busy.
 * @param {string}  props.label    Label.
 * @param {string}  props.busyText Label while busy.
 * @return {JSX.Element} Button.
 */
function SubmitButton( { busy, label, busyText } ) {
	return (
		<Button
			type="submit"
			className="h-11 w-full text-base"
			disabled={ busy }
			aria-busy={ busy }
		>
			{ busy ? (
				<>
					<Loader2
						className="mr-2 h-4 w-4 animate-spin"
						aria-hidden="true"
					/>
					{ busyText }
				</>
			) : (
				label
			) }
		</Button>
	);
}

/**
 * An error banner for the whole form.
 *
 * @param {Object} props         Props.
 * @param {string} props.message Message ('' = none).
 * @return {JSX.Element|null} Banner.
 */
function FormMessage( { message } ) {
	if ( ! message ) {
		return null;
	}
	return (
		<p
			className="m-0 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2.5 text-sm text-destructive"
			role="alert"
		>
			{ message }
		</p>
	);
}

/**
 * The sign-in form.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.settings accountSettings().
 * @param {Function} props.done     Called once signed in.
 * @param {Function} props.setBusy  Report the busy state to the popup.
 * @param {string}   props.prefill  The login to start with.
 * @return {JSX.Element} Form.
 */
function SignInForm( { settings, done, setBusy, prefill = '' } ) {
	const [ login, setLogin ] = useState( prefill );
	const [ password, setPassword ] = useState( '' );
	const [ remember, setRemember ] = useState( false );
	const [ errors, setErrors ] = useState( {} );
	const [ message, setMessage ] = useState( '' );
	const [ busy, setOwnBusy ] = useState( false );
	const loginRef = useRef( null );
	const passwordRef = useRef( null );

	useEffect( () => {
		( prefill ? passwordRef : loginRef ).current?.focus();
	}, [ prefill ] );

	const submit = async ( event ) => {
		event.preventDefault();
		if ( busy ) {
			return;
		}
		const missing = {};
		if ( ! login.trim() ) {
			missing.login = __(
				'Enter your phone number, e-mail or username.',
				'radius-hotel-booking'
			);
		}
		if ( ! password ) {
			missing.password = __(
				'Enter your password.',
				'radius-hotel-booking'
			);
		}
		setMessage( '' );
		setErrors( missing );
		if ( missing.login || missing.password ) {
			( missing.login ? loginRef : passwordRef ).current?.focus();
			return;
		}
		setOwnBusy( true );
		setBusy( true );
		try {
			await post( 'public/account/sign-in', {
				login: login.trim(),
				password,
				remember,
			} );
			await done();
		} catch ( err ) {
			setPassword( '' );
			const field = fieldErrors( err );
			setErrors( field.login ? { login: field.login } : {} );
			setMessage(
				err?.message ||
					__(
						'We could not sign you in. Please try again.',
						'radius-hotel-booking'
					)
			);
			passwordRef.current?.focus();
		} finally {
			setOwnBusy( false );
			setBusy( false );
		}
	};

	return (
		<form onSubmit={ submit } noValidate className="space-y-4">
			<FormMessage message={ message } />
			<Field
				label={ __(
					'Phone number, e-mail or username',
					'radius-hotel-booking'
				) }
				error={ errors.login }
			>
				<Input
					ref={ loginRef }
					name="username"
					value={ login }
					onChange={ ( event ) => {
						setLogin( event.target.value );
						setErrors( ( prev ) => ( { ...prev, login: '' } ) );
					} }
					autoComplete="username"
					autoCapitalize="none"
					autoCorrect="off"
					spellCheck={ false }
					maxLength={ 190 }
					aria-invalid={ !! errors.login }
					className="h-11"
				/>
			</Field>
			<Field
				label={ __( 'Password', 'radius-hotel-booking' ) }
				error={ errors.password }
			>
				<PasswordInput
					inputRef={ passwordRef }
					value={ password }
					onChange={ ( next ) => {
						setPassword( next );
						setErrors( ( prev ) => ( { ...prev, password: '' } ) );
					} }
					autoComplete="current-password"
					invalid={ !! errors.password }
				/>
			</Field>
			<div className="flex flex-wrap items-center justify-between gap-2 text-sm">
				<RememberBox checked={ remember } onChange={ setRemember } />
				{ settings.lostPassword ? (
					<a
						href={ backHere( settings.lostPassword ) }
						className={ linkClass }
					>
						{ __(
							'Forgot your password?',
							'radius-hotel-booking'
						) }
					</a>
				) : null }
			</div>
			<SubmitButton
				busy={ busy }
				label={ __( 'Sign in', 'radius-hotel-booking' ) }
				busyText={ __( 'Signing in…', 'radius-hotel-booking' ) }
			/>
		</form>
	);
}

/**
 * The "Create an account" form.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.settings accountSettings().
 * @param {Function} props.done     Called once the account is made and signed in.
 * @param {Function} props.setBusy  Report the busy state to the popup.
 * @param {Function} props.toSignIn Switch to the sign-in tab with a login.
 * @return {JSX.Element} Form.
 */
function RegisterForm( { settings, done, setBusy, toSignIn } ) {
	const min = Number( settings.minPassword ) || 8;
	const [ values, setValues ] = useState( {
		first_name: '',
		last_name: '',
		phone: '',
		email: '',
		password: '',
		website: '',
	} );
	const [ remember, setRemember ] = useState( false );
	const [ errors, setErrors ] = useState( {} );
	const [ message, setMessage ] = useState( '' );
	const [ exists, setExists ] = useState( false );
	const [ busy, setOwnBusy ] = useState( false );
	const firstRef = useRef( null );
	const formRef = useRef( null );

	useEffect( () => {
		firstRef.current?.focus();
	}, [] );

	const set = ( key ) => ( value ) => {
		setValues( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setErrors( ( prev ) => ( { ...prev, [ key ]: '' } ) );
	};

	// The first field with an error gets the focus.
	const focusError = ( found ) => {
		const key = [
			'first_name',
			'last_name',
			'phone',
			'email',
			'password',
		].find( ( name ) => found[ name ] );
		formRef.current
			?.querySelector(
				'phone' === key
					? '[data-field="phone"] input'
					: `[name="${ key }"]`
			)
			?.focus();
	};

	const submit = async ( event ) => {
		event.preventDefault();
		if ( busy ) {
			return;
		}
		const found = {};
		if ( ! values.first_name.trim() ) {
			found.first_name = __(
				'Enter your first name.',
				'radius-hotel-booking'
			);
		}
		if ( ! values.last_name.trim() ) {
			found.last_name = __(
				'Enter your last name.',
				'radius-hotel-booking'
			);
		}
		if (
			values.phone.replace( /\D/g, '' ).length < 8 ||
			! isPhoneValid( values.phone )
		) {
			found.phone = __(
				'Enter a phone number we can reach you on.',
				'radius-hotel-booking'
			);
		}
		if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( values.email.trim() ) ) {
			found.email = __(
				'Enter a valid e-mail address. We send your booking and password reset there.',
				'radius-hotel-booking'
			);
		}
		if ( values.password.length < min ) {
			found.password = sprintf(
				/* translators: %d: fewest characters. */
				__(
					'Choose a password of at least %d characters.',
					'radius-hotel-booking'
				),
				min
			);
		}
		setMessage( '' );
		setExists( false );
		setErrors( found );
		if ( Object.keys( found ).length ) {
			focusError( found );
			return;
		}
		setOwnBusy( true );
		setBusy( true );
		try {
			await post( 'public/account/register', {
				...values,
				first_name: values.first_name.trim(),
				last_name: values.last_name.trim(),
				email: values.email.trim(),
				remember,
			} );
			await done();
		} catch ( err ) {
			const field = fieldErrors( err );
			setErrors( field );
			setExists( 'account_exists' === err?.code );
			setMessage(
				Object.keys( field ).length
					? ''
					: err?.message ||
							__(
								'We could not create your account. Please try again.',
								'radius-hotel-booking'
							)
			);
			focusError( field );
		} finally {
			setOwnBusy( false );
			setBusy( false );
		}
	};

	const text = ( key, label, props = {} ) => (
		<Field label={ label } error={ errors[ key ] } required>
			<Input
				ref={ 'first_name' === key ? firstRef : undefined }
				name={ key }
				value={ values[ key ] }
				onChange={ ( event ) => set( key )( event.target.value ) }
				aria-invalid={ !! errors[ key ] }
				className="h-11"
				{ ...props }
			/>
		</Field>
	);

	return (
		<form
			ref={ formRef }
			onSubmit={ submit }
			noValidate
			className="space-y-4"
		>
			<FormMessage message={ message } />
			{ exists ? (
				<button
					type="button"
					className={ cn(
						linkClass,
						'm-0 -mt-2 cursor-pointer border-0 bg-transparent p-0 text-sm'
					) }
					onClick={ () =>
						toSignIn( values.email.trim() || values.phone )
					}
				>
					{ __( 'Sign in to your account', 'radius-hotel-booking' ) }
				</button>
			) : null }
			<div className="grid gap-4 sm:grid-cols-2">
				{ text(
					'first_name',
					__( 'First name', 'radius-hotel-booking' ),
					{ autoComplete: 'given-name', maxLength: 100 }
				) }
				{ text(
					'last_name',
					__( 'Last name', 'radius-hotel-booking' ),
					{ autoComplete: 'family-name', maxLength: 100 }
				) }
			</div>
			<Field
				label={ __( 'Phone', 'radius-hotel-booking' ) }
				description={ __(
					'You can sign in with it.',
					'radius-hotel-booking'
				) }
				error={ errors.phone }
				required
			>
				<div data-field="phone">
					<PhoneField
						value={ values.phone }
						onChange={ ( next ) => set( 'phone' )( next || '' ) }
					/>
				</div>
			</Field>
			{ text( 'email', __( 'E-mail', 'radius-hotel-booking' ), {
				type: 'email',
				inputMode: 'email',
				autoComplete: 'email',
				autoCapitalize: 'none',
				spellCheck: false,
				maxLength: 100,
			} ) }
			<Field
				label={ __( 'Password', 'radius-hotel-booking' ) }
				description={ sprintf(
					/* translators: %d: fewest characters. */
					__( 'At least %d characters.', 'radius-hotel-booking' ),
					min
				) }
				error={ errors.password }
				required
			>
				<PasswordInput
					value={ values.password }
					onChange={ set( 'password' ) }
					autoComplete="new-password"
					invalid={ !! errors.password }
				/>
			</Field>
			{ /* Bots fill every field; people never see this one. */ }
			<div
				aria-hidden="true"
				style={ {
					position: 'absolute',
					left: '-10000px',
					width: '1px',
					height: '1px',
					overflow: 'hidden',
				} }
			>
				<input
					type="text"
					name="website"
					tabIndex={ -1 }
					autoComplete="off"
					value={ values.website }
					onChange={ ( event ) =>
						setValues( ( prev ) => ( {
							...prev,
							website: event.target.value,
						} ) )
					}
				/>
			</div>
			<RememberBox checked={ remember } onChange={ setRemember } />
			<SubmitButton
				busy={ busy }
				label={ __( 'Create my account', 'radius-hotel-booking' ) }
				busyText={ __(
					'Creating your account…',
					'radius-hotel-booking'
				) }
			/>
		</form>
	);
}

/**
 * @param {Object}   props               Props.
 * @param {Object}   props.settings      accountSettings().
 * @param {Function} props.onClose       Close (also after signing in).
 * @param {Function} [props.onSignedIn]  Called once the account is loaded.
 * @param {string}   [props.initialMode] `signin` | `register`.
 * @return {JSX.Element} Popup.
 */
export default function SignInDialog( {
	settings,
	onClose,
	onSignedIn,
	initialMode = 'signin',
} ) {
	const canRegister = Boolean( settings.register );
	const [ mode, setMode ] = useState(
		canRegister && 'register' === initialMode ? 'register' : 'signin'
	);
	// The login to start the sign-in form with (after "account exists").
	const [ prefill, setPrefill ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const queryClient = useQueryClient();
	const dialogRef = useRef( null );
	const isRegister = 'register' === mode;
	// Settings → General's logo and hotel name; neither set: the round icon.
	const branded = hasHotelBrand();
	const closeButton = (
		<button
			type="button"
			className="inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg border-0 bg-muted p-0 text-heading transition hover:bg-heading hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
			aria-label={ __( 'Close', 'radius-hotel-booking' ) }
			onClick={ onClose }
		>
			<X className="h-4 w-4" aria-hidden="true" />
		</button>
	);

	// Escape closes, the page stops scrolling, Tab stays inside, focus goes back on close.
	useEffect( () => {
		const back = document.activeElement;
		const root = document.documentElement;
		const overflow = root.style.overflow;
		root.style.overflow = 'hidden';
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				onClose();
				return;
			}
			if ( 'Tab' !== event.key || ! dialogRef.current ) {
				return;
			}
			const items = Array.from(
				dialogRef.current.querySelectorAll(
					'a[href], button:not([disabled]), input:not([disabled]):not([tabindex="-1"]), select:not([disabled])'
				)
			);
			const first = items[ 0 ];
			const last = items[ items.length - 1 ];
			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last?.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first?.focus();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => {
			root.style.overflow = overflow;
			document.removeEventListener( 'keydown', onKey );
			back?.focus?.();
		};
	}, [ onClose ] );

	// The new cookies come with the next request: ask who is signed in.
	const done = async () => {
		const account = await queryClient.fetchQuery( {
			queryKey: [ 'public-account' ],
			queryFn: () => get( 'public/account' ).then( ( { data } ) => data ),
			staleTime: 0,
		} );
		if ( ! account?.signed_in ) {
			// Cookies refused (blocked by the browser, a security plugin…): the full page.
			window.location.assign( backHere( settings.loginUrl ) );
			return;
		}
		onSignedIn?.( account );
		onClose();
	};

	const switchTo = ( next, login = '' ) => {
		if ( busy ) {
			return;
		}
		setPrefill( login );
		setMode( next );
	};

	const tab = ( key, label ) => (
		<button
			type="button"
			role="tab"
			id={ `rtbp-account-tab-${ key }` }
			aria-selected={ mode === key }
			aria-controls="rtbp-account-panel"
			tabIndex={ mode === key ? 0 : -1 }
			onClick={ () => switchTo( key ) }
			onKeyDown={ ( event ) => {
				if ( [ 'ArrowLeft', 'ArrowRight' ].includes( event.key ) ) {
					event.preventDefault();
					const next = 'signin' === key ? 'register' : 'signin';
					switchTo( next );
					document
						.getElementById( `rtbp-account-tab-${ next }` )
						?.focus();
				}
			} }
			className={ cn(
				'm-0 flex-1 cursor-pointer rounded-md border-0 px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
				mode === key
					? 'bg-card text-heading shadow-sm'
					: 'bg-transparent text-muted-foreground hover:text-heading'
			) }
		>
			{ label }
		</button>
	);

	return createPortal(
		// Utilities are scoped *under* .rtbp-root, so the overlay is its child.
		<div className="rtbp-root" data-rtbp-nested-dialog>
			<div
				className="fixed inset-0 z-[100020] flex items-end justify-center bg-black/60 duration-200 animate-in fade-in-0 motion-reduce:animate-none sm:items-center sm:p-6"
				onMouseDown={ ( event ) => {
					if ( event.target === event.currentTarget && ! busy ) {
						onClose();
					}
				} }
			>
				<div
					ref={ dialogRef }
					role="dialog"
					aria-modal="true"
					aria-labelledby="rtbp-sign-in-title"
					aria-describedby="rtbp-sign-in-intro"
					className="max-h-[100dvh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-card p-6 shadow-2xl duration-200 animate-in fade-in-0 zoom-in-95 motion-reduce:animate-none sm:max-h-[calc(100dvh-3rem)] sm:rounded-2xl sm:p-8"
				>
					{ branded ? (
						<HotelBrand
							close={ closeButton }
							bleed="-mx-6 -mt-6 sm:-mx-8 sm:-mt-8"
							inset="px-6 sm:px-8"
						/>
					) : (
						<div className="flex items-start justify-between gap-3">
							<span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary">
								{ isRegister ? (
									<UserRoundPlus
										className="h-5 w-5"
										aria-hidden="true"
									/>
								) : (
									<LockKeyhole
										className="h-5 w-5"
										aria-hidden="true"
									/>
								) }
							</span>
							{ closeButton }
						</div>
					) }
					<h2
						id="rtbp-sign-in-title"
						className={ cn(
							'mb-1 text-xl font-bold text-heading',
							! branded && 'mt-4'
						) }
					>
						{ isRegister
							? __(
									'Create your account',
									'radius-hotel-booking'
							  )
							: __(
									'Sign in to your account',
									'radius-hotel-booking'
							  ) }
					</h2>
					<p
						id="rtbp-sign-in-intro"
						className="m-0 mb-5 text-sm text-muted-foreground"
					>
						{ isRegister
							? __(
									'Book faster next time: your details are filled in for you. You stay on this page.',
									'radius-hotel-booking'
							  )
							: __(
									'Your details are filled in for you, and you stay on this page.',
									'radius-hotel-booking'
							  ) }
					</p>

					{ canRegister ? (
						<div
							role="tablist"
							aria-label={ __(
								'Your account',
								'radius-hotel-booking'
							) }
							className="mb-5 flex gap-1 rounded-lg bg-muted p-1"
						>
							{ tab(
								'signin',
								__( 'Sign in', 'radius-hotel-booking' )
							) }
							{ tab(
								'register',
								__(
									'Create an account',
									'radius-hotel-booking'
								)
							) }
						</div>
					) : null }

					<div
						id="rtbp-account-panel"
						role={ canRegister ? 'tabpanel' : undefined }
						aria-labelledby={
							canRegister
								? `rtbp-account-tab-${ mode }`
								: undefined
						}
					>
						{ isRegister ? (
							<RegisterForm
								settings={ settings }
								done={ done }
								setBusy={ setBusy }
								toSignIn={ ( login ) =>
									switchTo( 'signin', login )
								}
							/>
						) : (
							<SignInForm
								key={ prefill }
								settings={ settings }
								done={ done }
								setBusy={ setBusy }
								prefill={ prefill }
							/>
						) }
					</div>

					{ isRegister || canRegister || settings.registerUrl ? (
						<p className="m-0 mt-5 border-t border-border pt-4 text-center text-sm text-muted-foreground">
							{ isRegister ? (
								<>
									{ __(
										'Already have an account?',
										'radius-hotel-booking'
									) }{ ' ' }
									<button
										type="button"
										className={ cn(
											linkClass,
											'm-0 cursor-pointer border-0 bg-transparent p-0'
										) }
										onClick={ () => switchTo( 'signin' ) }
									>
										{ __(
											'Sign in',
											'radius-hotel-booking'
										) }
									</button>
								</>
							) : null }
							{ ! isRegister && canRegister ? (
								<>
									{ __(
										'New here?',
										'radius-hotel-booking'
									) }{ ' ' }
									<button
										type="button"
										className={ cn(
											linkClass,
											'm-0 cursor-pointer border-0 bg-transparent p-0'
										) }
										onClick={ () => switchTo( 'register' ) }
									>
										{ __(
											'Create an account',
											'radius-hotel-booking'
										) }
									</button>
								</>
							) : null }
							{ ! isRegister &&
							! canRegister &&
							settings.registerUrl ? (
								<>
									{ __(
										'New here?',
										'radius-hotel-booking'
									) }{ ' ' }
									<a
										href={ backHere(
											settings.registerUrl
										) }
										className={ linkClass }
									>
										{ __(
											'Create an account',
											'radius-hotel-booking'
										) }
									</a>
								</>
							) : null }
						</p>
					) : null }
				</div>
			</div>
		</div>,
		document.body
	);
}
