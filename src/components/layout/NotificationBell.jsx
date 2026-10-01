/**
 * The new-booking alert (M01, 1.12, 17.1, 17.2), in the top bar of every
 * staff screen: it polls `notifications/poll` every `pollSeconds`
 * (Settings → Notifications; ADR-006), and for each booking someone else made
 * — on the website or at another desk — shows a toast with *View* and plays
 * the hotel's sound or the built-in chime. The lists and the dashboard reload.
 * The bell shows how many bookings await approval and opens that tab.
 *
 * Browsers only play sound after a click or key press on the page: the first
 * one unlocks it (`unlockAudio`). With *Show an alert* off, the bell still
 * counts and the lists still refresh, quietly.
 */
import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Bell } from 'lucide-react';
import { toast } from 'sonner';

import { get } from '@/api/client';
import { useAccess } from '@/lib/access';
import { formatDateTime } from '@/lib/format';
import { playNotificationSound, unlockAudio } from '@/lib/sound';

/**
 * The alert settings from the admin payload.
 *
 * @return {{alert: boolean, sound: boolean, seconds: number, url: string}} Settings.
 */
function settings() {
	const params = window.radius_hotel_booking_param || {};
	const notifications = params.settings?.notifications || {};
	return {
		alert: false !== notifications.newBookingAlert,
		sound: false !== notifications.playSound,
		seconds: Math.min(
			300,
			Math.max( 10, Number( notifications.pollSeconds ) || 30 )
		),
		url: params.notify_sound_url || '',
	};
}

/**
 * @return {JSX.Element|null} Bell.
 */
export default function NotificationBell() {
	const allowed = useAccess( 'page.dashboard' ) !== 'locked';
	const navigate = useNavigate();
	const client = useQueryClient();
	const cursor = useRef( 0 );
	const [ awaiting, setAwaiting ] = useState( 0 );

	useEffect( () => unlockAudio(), [] );

	useEffect( () => {
		if ( ! allowed ) {
			return undefined;
		}
		const config = settings();
		let stopped = false;

		const poll = () =>
			get( 'notifications/poll', { since: cursor.current } )
				.then( ( { data } ) => {
					if ( stopped ) {
						return;
					}
					const first = 0 === cursor.current;
					cursor.current = Math.max( cursor.current, data.cursor );
					setAwaiting( data.awaiting );
					if ( first || ! data.bookings.length ) {
						return;
					}
					client.invalidateQueries( { queryKey: [ 'bookings' ] } );
					client.invalidateQueries( { queryKey: [ 'dashboard' ] } );
					if ( ! config.alert ) {
						return;
					}
					// Oldest first, so the newest toast ends on top.
					[ ...data.bookings ].reverse().forEach( ( booking ) =>
						toast(
							sprintf(
								/* translators: %s: booking reference. */
								__( 'New booking %s', 'radius-hotel-booking' ),
								booking.reference
							),
							{
								description: [
									booking.guest,
									sprintf(
										/* translators: %d: number of rooms. */
										_n(
											'%d room',
											'%d rooms',
											booking.rooms,
											'radius-hotel-booking'
										),
										booking.rooms
									),
									booking.first_start
										? formatDateTime( booking.first_start )
										: '',
								]
									.filter( Boolean )
									.join( ' · ' ),
								duration: 15000,
								action: {
									label: __( 'View', 'radius-hotel-booking' ),
									onClick: () =>
										navigate( `/bookings/${ booking.id }` ),
								},
							}
						)
					);
					if ( config.sound ) {
						playNotificationSound( config.url );
					}
				} )
				// A failed poll (offline, signed out) is retried at the next tick.
				.catch( () => {} );

		poll();
		const timer = window.setInterval( poll, config.seconds * 1000 );
		return () => {
			stopped = true;
			window.clearInterval( timer );
		};
	}, [ allowed, client, navigate ] );

	if ( ! allowed ) {
		return null;
	}

	const label = awaiting
		? sprintf(
				/* translators: %d: number of bookings awaiting approval. */
				_n(
					'%d booking awaiting approval',
					'%d bookings awaiting approval',
					awaiting,
					'radius-hotel-booking'
				),
				awaiting
		  )
		: __( 'No booking awaiting approval', 'radius-hotel-booking' );

	return (
		<button
			type="button"
			onClick={ () => navigate( '/bookings?tab=awaiting' ) }
			className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-heading transition-colors hover:border-primary"
			aria-label={ label }
			title={ label }
		>
			<Bell className="h-5 w-5" aria-hidden="true" />
			{ awaiting ? (
				<span className="absolute -right-1.5 -top-1.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-destructive px-1 text-[11px] font-bold leading-none text-white">
					{ awaiting > 99 ? '99+' : awaiting }
				</span>
			) : null }
		</button>
	);
}
