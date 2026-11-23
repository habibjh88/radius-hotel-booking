/**
 * The booking form's account line (Settings → Guest accounts): who is signed
 * in ("Signed in as Awa Koné · Sign out"), or an invitation to sign in so the
 * details are filled in. With "Require sign-in", `SignInGate` replaces the
 * form until the visitor signs in. "Sign in" opens SignInDialog, so the
 * guest stays on the page. Its buttons (Express booking, Sign in, Create an
 * account) are each switched in Settings → Guest accounts; express booking
 * here fills the guest details before the rooms are chosen.
 *
 * Who is signed in comes from `GET public/account` (useAccount), not from the
 * page: booking pages may be cached.
 */
import { Fragment, Suspense, lazy, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { __, sprintf } from '@wordpress/i18n';
import { CircleCheck, LogIn, UserRound, Zap } from 'lucide-react';

import { get } from '@/api/client';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
	RECORD_FIELDS,
	knownPatch,
	markExpressOffered,
	recognisedPatch,
} from './expressPatch';

// The sign-in popup, loaded the first time it opens.
const SignInDialog = lazy( () => import( './SignInDialog' ) );
// Express booking, loaded the first time it opens.
const ExpressCheckout = lazy( () => import( './ExpressCheckout' ) );

const linkClass = 'font-medium text-primary underline-offset-2 hover:underline';
const buttonReset = 'm-0 cursor-pointer border-0 bg-transparent p-0';

/**
 * "Sign in": opens the popup, so the guest stays on the page (and keeps the
 * rooms they hold). The WordPress sign-in page stays the link's address, for
 * a new tab or a browser without scripts.
 *
 * `mode="register"` opens it on "Create an account" (its address is then
 * WordPress's sign-up page).
 *
 * @param {Object}  props           Props.
 * @param {Object}  props.settings  accountSettings().
 * @param {string}  props.className Link classes.
 * @param {string}  props.mode      `signin` | `register`.
 * @param {Object}  props.children  Label.
 * @return {JSX.Element} Link and popup.
 */
function SignInLink( { settings, className, mode = 'signin', children } ) {
	const [ open, setOpen ] = useState( false );
	const href =
		'register' === mode && settings.registerUrl
			? settings.registerUrl
			: settings.loginUrl;
	return (
		<>
			<a
				href={ backHere( href ) }
				className={ className }
				onClick={ ( event ) => {
					// A new tab or window keeps the page's own link.
					if (
						event.metaKey ||
						event.ctrlKey ||
						event.shiftKey ||
						event.altKey ||
						0 !== event.button
					) {
						return;
					}
					event.preventDefault();
					setOpen( true );
				} }
			>
				{ children }
			</a>
			{ open ? (
				<Suspense fallback={ null }>
					<SignInDialog
						settings={ settings }
						initialMode={ mode }
						onClose={ () => setOpen( false ) }
					/>
				</Suspense>
			) : null }
		</>
	);
}

/**
 * Settings → Guest accounts, as the page was given them.
 *
 * @return {{ requireSignIn: boolean, showSignIn: boolean, loginUrl: string, lostPassword: string, registerUrl: string, register: boolean, minPassword: number, showRegister: boolean, showExpress: boolean, express: boolean }} Settings.
 */
export function accountSettings() {
	return {
		requireSignIn: false,
		showSignIn: true,
		loginUrl: '',
		lostPassword: '',
		registerUrl: '',
		register: false,
		showRegister: true,
		showExpress: true,
		express: false,
		minPassword: 8,
		...( window.radius_hotel_booking_site_param?.account || {} ),
	};
}

/**
 * The signed-in visitor: `{ signed_in, token?, name?, logout_url?, details? }`.
 *
 * @param {boolean} enabled Ask (the website flow only).
 * @return {Object} React Query result.
 */
export function useAccount( enabled = true ) {
	return useQuery( {
		queryKey: [ 'public-account' ],
		queryFn: () => get( 'public/account' ).then( ( { data } ) => data ),
		enabled,
		staleTime: Infinity,
		retry: false,
	} );
}

/**
 * A WordPress link that brings the visitor back to this page afterwards.
 *
 * @param {string} url WordPress URL (login, logout, register).
 * @return {string} URL.
 */
const backHere = ( url ) =>
	url
		? `${ url }${
				url.includes( '?' ) ? '&' : '?'
		  }redirect_to=${ encodeURIComponent( window.location.href ) }`
		: '';

/**
 * @param {Object}  props         Props.
 * @param {Object}  props.account useAccount()'s data.
 * @param {boolean} props.checked The account was asked for (nothing shows
 *                                before, so a signed-in guest never sees
 *                                "Sign in" flash first).
 * @param {Object}   props.guestForm   The booking form's guest details
 *                                     (express booking fills them early).
 * @param {Function} props.onGuestForm Called with a patch for them; without
 *                                     it, no "Express booking" here.
 * @param {boolean}  props.picksRoom   Guests choose their room (Settings →
 *                                     Website); off, the steps say one is
 *                                     chosen for them.
 * @return {JSX.Element|null} Line.
 */
export default function AccountBar( {
	account,
	checked = true,
	guestForm,
	onGuestForm,
	picksRoom = true,
} ) {
	const settings = accountSettings();
	const [ express, setExpress ] = useState( false );
	// Express booking found no guest with that number.
	const [ newPhone, setNewPhone ] = useState( false );
	if ( ! checked ) {
		return null;
	}
	if ( account?.signed_in ) {
		return (
			<div className="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-border bg-card px-4 py-2.5 text-sm text-foreground">
				<UserRound
					className="h-4 w-4 shrink-0 text-muted-foreground"
					aria-hidden="true"
				/>
				<span className="min-w-0 flex-1">
					{ sprintf(
						/* translators: %s: the signed-in guest's name. */
						__(
							'Signed in as %s. Your details are filled in for you.',
							'radius-hotel-booking'
						),
						account.name
					) }
				</span>
				{ account.logout_url ? (
					<a
						href={ backHere( account.logout_url ) }
						className="font-medium text-primary underline-offset-2 hover:underline"
					>
						{ __( 'Sign out', 'radius-hotel-booking' ) }
					</a>
				) : null }
			</div>
		);
	}
	const canSignIn = settings.showSignIn && Boolean( settings.loginUrl );
	const canRegister = settings.showRegister && settings.register;
	const canExpress = settings.showExpress && settings.express && onGuestForm;

	// Express booking already used above the rooms: the details are waiting.
	if ( canExpress && guestForm?.express_token ) {
		const name = guestForm.express_hint?.name || guestForm.first_name;
		return (
			<div className="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-success/40 bg-success/10 px-4 py-2.5 text-sm text-foreground">
				<CircleCheck
					className="h-4 w-4 shrink-0 text-success"
					aria-hidden="true"
				/>
				<span className="min-w-0 flex-1">
					{ name
						? sprintf(
								/* translators: %s: the guest's name (may be masked, e.g. "H••• R•••"). */
								__(
									'Welcome back, %s! Your details are ready for checkout. Now choose your room.',
									'radius-hotel-booking'
								),
								name
						  )
						: __(
								'Welcome back! Your details are ready for checkout. Now choose your room.',
								'radius-hotel-booking'
						  ) }
				</span>
				<button
					type="button"
					className={ cn( linkClass, buttonReset ) }
					onClick={ () => onGuestForm( RECORD_FIELDS ) }
				>
					{ __( 'Not you?', 'radius-hotel-booking' ) }
				</button>
			</div>
		);
	}
	if ( ! canSignIn && ! canRegister && ! canExpress ) {
		return null;
	}

	let intro = __(
		'Booked with us before? Sign in and we will fill in your details.',
		'radius-hotel-booking'
	);
	if ( canRegister && canSignIn ) {
		intro = __(
			'Sign in and we will fill in your details, or create an account to book faster next time.',
			'radius-hotel-booking'
		);
	} else if ( canRegister ) {
		intro = __(
			'Create an account to book faster next time.',
			'radius-hotel-booking'
		);
	}

	const openExpress = () => {
		markExpressOffered();
		setNewPhone( false );
		setExpress( true );
	};

	const items = [];
	if ( canExpress && newPhone ) {
		items.push(
			<button
				key="express"
				type="button"
				className={ cn( linkClass, buttonReset ) }
				onClick={ openExpress }
			>
				{ __( 'Express booking', 'radius-hotel-booking' ) }
			</button>
		);
	}
	if ( canSignIn ) {
		items.push(
			<SignInLink
				key="signin"
				settings={ settings }
				className={ linkClass }
			>
				{ __( 'Sign in', 'radius-hotel-booking' ) }
			</SignInLink>
		);
	}
	if ( canRegister ) {
		items.push(
			<SignInLink
				key="register"
				settings={ settings }
				mode="register"
				className={ linkClass }
			>
				{ __( 'Create an account', 'radius-hotel-booking' ) }
			</SignInLink>
		);
	}

	const expressDialog = express ? (
		<Suspense fallback={ null }>
			<ExpressCheckout
				phone={ guestForm?.phone }
				onKnown={ ( details, token ) => {
					setExpress( false );
					onGuestForm( knownPatch( details, token ) );
				} }
				onRecognised={ ( result ) => {
					setExpress( false );
					onGuestForm( recognisedPatch( result, guestForm?.phone ) );
				} }
				onNew={ ( phone ) => {
					setExpress( false );
					setNewPhone( true );
					if ( phone ) {
						onGuestForm( { phone } );
					}
				} }
				onClose={ () => setExpress( false ) }
			/>
		</Suspense>
	) : null;

	// Express booking on: a panel that stands out, with one big button and the
	// steps spelled out (guests found the small link hard to notice and use).
	if ( canExpress && ! newPhone ) {
		const steps = [
			__( 'Click “Express booking”.', 'radius-hotel-booking' ),
			__( 'Enter your phone number.', 'radius-hotel-booking' ),
			picksRoom
				? __(
						'Your details are filled in for you. Then choose your room.',
						'radius-hotel-booking'
				  )
				: __(
						'Your details are filled in for you. Then your room is chosen automatically for you.',
						'radius-hotel-booking'
				  ),
		];
		return (
			<div className="mb-4 rounded-xl border-2 border-[color-mix(in_srgb,var(--primary)_45%,#ffffff)] bg-primary-soft p-4 text-foreground sm:p-5">
				<div className="flex flex-col gap-4 sm:flex-row sm:items-center">
					<span className="hidden h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground sm:flex">
						<Zap className="h-6 w-6" aria-hidden="true" />
					</span>
					<div className="min-w-0 flex-1">
						<p className="m-0 text-base font-bold text-heading">
							{ __(
								'Booked with us before? Book in a few seconds.',
								'radius-hotel-booking'
							) }
						</p>
						<ol className="m-0 mt-2 list-none space-y-1 p-0 text-sm">
							{ steps.map( ( step, index ) => (
								<li
									key={ index }
									className="flex items-start gap-2"
								>
									<span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
										{ index + 1 }
									</span>
									<span>{ step }</span>
								</li>
							) ) }
						</ol>
					</div>
					<Button
						type="button"
						onClick={ openExpress }
						className="h-12 w-full shrink-0 gap-2 px-6 text-base font-semibold shadow-md sm:w-auto"
					>
						<Zap className="h-5 w-5" aria-hidden="true" />
						{ __( 'Express booking', 'radius-hotel-booking' ) }
					</Button>
				</div>
				<p className="m-0 mt-3 border-t border-[color-mix(in_srgb,var(--primary)_25%,#ffffff)] pt-3 text-sm text-muted-foreground">
					{ __(
						'First time here? Just choose your dates and room below.',
						'radius-hotel-booking'
					) }
					{ items.length ? (
						<>
							{ ' ' }
							{ __( 'You can also:', 'radius-hotel-booking' ) }
							{ items.map( ( item, index ) => (
								<Fragment key={ item.key }>
									{ index ? ' · ' : ' ' }
									{ item }
								</Fragment>
							) ) }
						</>
					) : null }
				</p>
				{ expressDialog }
			</div>
		);
	}

	return (
		<div className="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-border bg-card px-4 py-2.5 text-sm text-foreground">
			<LogIn
				className="h-4 w-4 shrink-0 text-muted-foreground"
				aria-hidden="true"
			/>
			<span className="min-w-0 flex-1">
				{ newPhone
					? __(
							'We do not know this number yet. Choose your room, then enter your details at checkout.',
							'radius-hotel-booking'
					  )
					: intro }
			</span>
			<span className="flex shrink-0 flex-wrap items-center gap-3">
				{ items.map( ( item, index ) => (
					<Fragment key={ item.key }>
						{ index ? (
							<span
								className="h-4 w-px bg-border"
								aria-hidden="true"
							/>
						) : null }
						{ item }
					</Fragment>
				) ) }
			</span>
			{ expressDialog }
		</div>
	);
}

/**
 * Settings → Require sign-in: what a visitor who is not signed in sees
 * instead of the booking form.
 *
 * @return {JSX.Element} Gate.
 */
export function SignInGate() {
	const settings = accountSettings();
	return (
		<div className="mx-auto max-w-md space-y-4 rounded-2xl border border-border bg-card p-6 text-center shadow-sm">
			<span className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary-soft text-primary">
				<LogIn className="h-6 w-6" aria-hidden="true" />
			</span>
			<div className="space-y-1">
				<h2 className="m-0 text-lg font-bold text-heading">
					{ __( 'Sign in to book', 'radius-hotel-booking' ) }
				</h2>
				<p className="m-0 text-sm text-muted-foreground">
					{ __(
						'Please sign in to your account to book a room. Your details are then filled in for you.',
						'radius-hotel-booking'
					) }
				</p>
			</div>
			<div className="flex flex-col gap-2 sm:flex-row sm:justify-center">
				<SignInLink
					settings={ settings }
					className={ buttonVariants( { className: 'h-11' } ) }
				>
					{ __( 'Sign in', 'radius-hotel-booking' ) }
				</SignInLink>
				{ settings.register ? (
					<SignInLink
						settings={ settings }
						mode="register"
						className={ buttonVariants( {
							variant: 'outline',
							className: 'h-11',
						} ) }
					>
						{ __( 'Create an account', 'radius-hotel-booking' ) }
					</SignInLink>
				) : null }
				{ ! settings.register && settings.registerUrl ? (
					<Button asChild variant="outline" className="h-11">
						<a href={ backHere( settings.registerUrl ) }>
							{ __(
								'Create an account',
								'radius-hotel-booking'
							) }
						</a>
					</Button>
				) : null }
			</div>
		</div>
	);
}
