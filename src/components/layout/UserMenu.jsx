/**
 * The account menu at the top right (R1-T6): the signed-in user's name and
 * role, the items add-ons register on `rtbp.user.menu` (`{ key, label, icon,
 * to }`, `to` a dashboard route — My profile, My payroll), Language, and
 * *Sign out*.
 */
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { Check, ChevronDown, Languages, LogOut } from 'lucide-react';

import { post } from '@/api/client';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuLabel,
	DropdownMenuSeparator,
	DropdownMenuSub,
	DropdownMenuSubContent,
	DropdownMenuSubTrigger,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { toastError } from '@/lib/toast';

/**
 * Initials for the avatar.
 *
 * @param {string} name Display name.
 * @return {string} Up to two letters.
 */
const initials = ( name = '' ) =>
	name
		.split( /\s+/ )
		.filter( Boolean )
		.slice( 0, 2 )
		.map( ( part ) => part[ 0 ].toUpperCase() )
		.join( '' ) || '?';

/**
 * The sign-out link, coming back to the page the app runs on (the hotel
 * dashboard page shows its sign-in screen; wp-admin goes to the login).
 *
 * @param {string} base `wp_logout_url()` (carries its nonce).
 * @return {string} URL.
 */
const signOutUrl = ( base ) => {
	try {
		const url = new URL( base, window.location.href );
		url.searchParams.set(
			'redirect_to',
			window.location.href.split( '#' )[ 0 ]
		);
		return url.toString();
	} catch ( e ) {
		return base;
	}
};

/**
 * @return {JSX.Element} Menu.
 */
export default function UserMenu() {
	const navigate = useNavigate();
	const user = window.radius_hotel_booking_param?.current_user || {};
	// Controlled, so the items are read again each time the menu opens.
	const [ open, setOpen ] = useState( false );
	const [ saving, setSaving ] = useState( '' );
	const items = (
		applyFilters( 'rtbp.user.menu', [], { user } ) || []
	).filter( ( item ) => item && item.key && item.label && item.to );
	const languages = Array.isArray( user.languages ) ? user.languages : [];
	const current = languages.find( ( row ) => row.locale === user.locale );

	// Save the language, then reload: every string and date comes in it.
	const pickLanguage = async ( locale ) => {
		if ( locale === user.locale || saving ) {
			return;
		}
		setSaving( locale );
		try {
			await post( 'me/language', { locale } );
			window.location.reload();
		} catch ( error ) {
			setSaving( '' );
			toastError( error );
		}
	};

	return (
		<DropdownMenu open={ open } onOpenChange={ setOpen }>
			<DropdownMenuTrigger asChild>
				<button
					type="button"
					className="flex h-10 min-w-0 items-center gap-2.5 rounded-lg border border-border bg-card py-1 pl-1 pr-2 text-left hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
					aria-label={ __( 'Account menu', 'radius-hotel-booking' ) }
				>
					<span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary">
						{ initials( user.name ) }
					</span>
					<span className="hidden min-w-0 max-w-[160px] md:block">
						<span className="block truncate text-[13px] font-semibold leading-tight text-heading">
							{ user.name }
						</span>
						{ user.role ? (
							<span className="block truncate text-[11px] leading-tight text-muted-foreground">
								{ user.role }
							</span>
						) : null }
					</span>
					<ChevronDown
						className="hidden h-4 w-4 shrink-0 text-muted-foreground md:block"
						aria-hidden="true"
					/>
				</button>
			</DropdownMenuTrigger>
			<DropdownMenuContent className="rtbp-root w-60" align="end">
				<DropdownMenuLabel className="font-normal">
					<span className="block truncate text-sm font-semibold text-heading">
						{ user.name }
					</span>
					<span className="block truncate text-xs text-muted-foreground">
						{ [ user.role, user.email ]
							.filter( Boolean )
							.join( ' · ' ) }
					</span>
				</DropdownMenuLabel>
				{ items.length ? <DropdownMenuSeparator /> : null }
				{ items.map( ( item ) => {
					const Icon = item.icon;
					return (
						<DropdownMenuItem
							key={ item.key }
							onSelect={ () => navigate( item.to ) }
						>
							{ Icon ? <Icon className="h-4 w-4" /> : null }
							{ item.label }
						</DropdownMenuItem>
					);
				} ) }
				{ languages.length > 1 ? (
					<DropdownMenuSub>
						<DropdownMenuSubTrigger>
							<Languages className="h-4 w-4" />
							<span className="flex-1">
								{ __( 'Language', 'radius-hotel-booking' ) }
							</span>
							{ current ? (
								<span className="text-xs text-muted-foreground">
									{ current.flag || current.label }
								</span>
							) : null }
						</DropdownMenuSubTrigger>
						<DropdownMenuSubContent className="rtbp-root w-48">
							{ languages.map( ( row ) => (
								<DropdownMenuItem
									key={ row.locale }
									disabled={ Boolean( saving ) }
									onSelect={ ( event ) => {
										event.preventDefault();
										pickLanguage( row.locale );
									} }
								>
									{ row.flag ? (
										<span aria-hidden="true">
											{ row.flag }
										</span>
									) : null }
									<span
										className="flex-1"
										lang={ row.locale.replace( '_', '-' ) }
									>
										{ row.label }
									</span>
									{ row.locale === user.locale ? (
										<Check className="h-4 w-4 text-primary" />
									) : null }
								</DropdownMenuItem>
							) ) }
						</DropdownMenuSubContent>
					</DropdownMenuSub>
				) : null }
				{ user.logout_url ? (
					<>
						<DropdownMenuSeparator />
						<DropdownMenuItem
							onSelect={ () => {
								window.location.href = signOutUrl(
									user.logout_url
								);
							} }
						>
							<LogOut className="h-4 w-4" />
							{ __( 'Sign out', 'radius-hotel-booking' ) }
						</DropdownMenuItem>
					</>
				) : null }
			</DropdownMenuContent>
		</DropdownMenu>
	);
}
