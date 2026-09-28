/**
 * Top bar: page title and description (from the route table), the page's own
 * actions, and today's date in the hotel's time zone.
 */
import { useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { CalendarDays, Menu, Search } from 'lucide-react';

import { matchRoute } from '@/admin/routes';
import { isMac } from './shortcuts';
import { useCurrentPageActions } from './PageActions';

/**
 * Today's date, formatted in the site's time zone and the user's locale.
 *
 * @return {string} Date label.
 */
function todayLabel() {
	const params = window.radius_hotel_booking_param || {};
	const locale = document.documentElement.lang || undefined;
	const options = { weekday: 'long', day: 'numeric', month: 'long' };

	try {
		return new Intl.DateTimeFormat( locale, {
			...options,
			timeZone: params.timezone || undefined,
		} ).format( new Date() );
	} catch ( e ) {
		// Unknown zone string (e.g. a raw UTC offset): fall back to the browser's.
		return new Intl.DateTimeFormat( locale, options ).format( new Date() );
	}
}

/**
 * @param {Object}   props            Props.
 * @param {Function} props.onOpenMenu   Opens the mobile drawer.
 * @param {Function} props.onOpenSearch Opens the command palette.
 * @param {boolean}  props.isDesktop    Hides the menu button on desktop.
 * @return {JSX.Element} Top bar.
 */
export default function Topbar( { onOpenMenu, onOpenSearch, isDesktop } ) {
	const location = useLocation();
	const route = matchRoute( location.pathname );
	const actions = useCurrentPageActions();

	const title = route?.title || route?.label || __( 'Page not found', 'radius-hotel-booking' );

	return (
		<header className="rtbp-shell-topbar flex min-h-[72px] items-center gap-4 border-b border-border bg-card px-4 md:px-8">
			{ ! isDesktop ? (
				<button
					type="button"
					onClick={ onOpenMenu }
					className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-heading hover:bg-muted"
					aria-label={ __( 'Open menu', 'radius-hotel-booking' ) }
				>
					<Menu className="h-5 w-5" aria-hidden="true" />
				</button>
			) : null }

			<div className="min-w-0 flex-1 py-3">
				<h1 className="m-0 truncate p-0 text-lg font-bold leading-6 text-heading">
					{ title }
				</h1>
				{ route?.description ? (
					<p className="m-0 mt-0.5 hidden truncate text-[13px] text-muted-foreground sm:block">
						{ route.description }
					</p>
				) : null }
			</div>

			<div className="flex shrink-0 items-center gap-3">
				<button
					type="button"
					onClick={ onOpenSearch }
					className="flex h-10 items-center gap-2 rounded-lg border border-border bg-card px-3 text-[13px] text-muted-foreground transition-colors hover:border-primary hover:text-heading"
					aria-label={ __( 'Search and go to a screen', 'radius-hotel-booking' ) }
				>
					<Search className="h-4 w-4" aria-hidden="true" />
					<span className="hidden md:inline">{ __( 'Search…', 'radius-hotel-booking' ) }</span>
					<kbd className="hidden rounded border border-border bg-muted px-1.5 font-sans text-[11px] font-medium md:inline">
						{ isMac() ? '⌘K' : 'Ctrl K' }
					</kbd>
				</button>
				<span className="hidden items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-[13px] font-medium text-heading lg:inline-flex">
					<CalendarDays
						className="h-4 w-4 text-primary"
						aria-hidden="true"
					/>
					{ todayLabel() }
				</span>
				{ actions }
			</div>
		</header>
	);
}
