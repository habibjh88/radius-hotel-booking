/**
 * The booking form's account line (Settings → Guest accounts): who is signed
 * in ("Signed in as Awa Koné · Sign out"), or an invitation to sign in so the
 * details are filled in. With "Require sign-in", `SignInGate` replaces the
 * form until the visitor signs in.
 *
 * Who is signed in comes from `GET public/account` (useAccount), not from the
 * page: booking pages may be cached.
 */
import { useQuery } from '@tanstack/react-query';
import { __, sprintf } from '@wordpress/i18n';
import { LogIn, UserRound } from 'lucide-react';

import { get } from '@/api/client';
import { Button } from '@/components/ui/button';

/**
 * Settings → Guest accounts, as the page was given them.
 *
 * @return {{ requireSignIn: boolean, showSignIn: boolean, loginUrl: string, registerUrl: string }} Settings.
 */
export function accountSettings() {
	return {
		requireSignIn: false,
		showSignIn: true,
		loginUrl: '',
		registerUrl: '',
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
 * @return {JSX.Element|null} Line.
 */
export default function AccountBar( { account, checked = true } ) {
	const settings = accountSettings();
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
	if ( ! settings.showSignIn || ! settings.loginUrl ) {
		return null;
	}
	return (
		<div className="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-border bg-card px-4 py-2.5 text-sm text-foreground">
			<LogIn
				className="h-4 w-4 shrink-0 text-muted-foreground"
				aria-hidden="true"
			/>
			<span className="min-w-0 flex-1">
				{ __(
					'Booked with us before? Sign in and we will fill in your details.',
					'radius-hotel-booking'
				) }
			</span>
			<a
				href={ backHere( settings.loginUrl ) }
				className="font-medium text-primary underline-offset-2 hover:underline"
			>
				{ __( 'Sign in', 'radius-hotel-booking' ) }
			</a>
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
				<Button asChild className="h-11">
					<a href={ backHere( settings.loginUrl ) }>
						{ __( 'Sign in', 'radius-hotel-booking' ) }
					</a>
				</Button>
				{ settings.registerUrl ? (
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
