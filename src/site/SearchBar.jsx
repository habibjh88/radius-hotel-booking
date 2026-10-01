/**
 * The search bar on the website (M04, 4.1, 4.2): arrival and departure,
 * adults and children (the desk's own `DatesBar`), the rooms count when
 * Settings → Public booking asks for it, and *Find a room*. It sends the
 * guest to the booking page with the search in the URL; the booking flow
 * there (M04 T2b) starts from it.
 *
 * The dates follow the website's rules: today only while same-day bookings
 * are open, nothing beyond the booking window.
 */
import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Search } from 'lucide-react';

import DatesBar, { Stepper } from '@/components/booking/DatesBar';
import {
	guestDateLimits,
	searchFromUrl,
	siteBookingRules,
} from '@/components/booking/guestLimits';
import { Button } from '@/components/ui/button';

/**
 * @return {JSX.Element} Search bar.
 */
export default function SearchBar() {
	const rules = siteBookingRules();
	const limits = guestDateLimits( rules );
	const [ search, setSearch ] = useState( () =>
		searchFromUrl( rules, limits )
	);
	const ready =
		search.arrival &&
		search.departure &&
		search.departure >= search.arrival;

	const submit = ( event ) => {
		event.preventDefault();
		if ( ! ready ) {
			return;
		}
		const target = new URL(
			rules.resultsUrl || window.location.href,
			window.location.href
		);
		const query = {
			arrival: search.arrival,
			departure: search.departure,
			adults: search.adults,
			children: search.children,
			...( rules.showRoomsField ? { rooms: search.rooms } : {} ),
		};
		Object.entries( query ).forEach( ( [ key, value ] ) =>
			target.searchParams.set( key, String( value ) )
		);
		window.location.assign( target.toString() );
	};

	return (
		<form
			onSubmit={ submit }
			className="space-y-4 rounded-xl border border-border bg-card p-4 text-foreground shadow-sm sm:p-5"
			aria-label={ __( 'Find a room', 'radius-hotel-booking' ) }
		>
			<DatesBar
				search={ search }
				limits={ limits }
				onChange={ ( changes ) =>
					setSearch( ( prev ) => ( { ...prev, ...changes } ) )
				}
				checkin={ null }
			/>
			<div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
				{ rules.showRoomsField ? (
					<div className="sm:w-48">
						<Stepper
							label={ __( 'Rooms', 'radius-hotel-booking' ) }
							value={ Number( search.rooms ) }
							min={ 1 }
							max={ 10 }
							onChange={ ( rooms ) =>
								setSearch( ( prev ) => ( { ...prev, rooms } ) )
							}
						/>
					</div>
				) : (
					<span />
				) }
				<Button
					type="submit"
					size="lg"
					disabled={ ! ready }
					className="h-12 w-full sm:w-auto sm:min-w-[12rem]"
				>
					<Search aria-hidden="true" />
					{ __( 'Find a room', 'radius-hotel-booking' ) }
				</Button>
			</div>
		</form>
	);
}
