/**
 * The new-booking alert (M01, 1.12, 17.1, 17.2), in the top bar of every
 * staff screen: it polls `notifications/poll` every `pollSeconds`
 * (Settings → Notifications; ADR-006), and for each booking someone else made
 * — on the website or at another desk — shows a toast with *View* and plays
 * the hotel's sound or the built-in chime. The lists and the dashboard reload.
 * The bell counts the bookings awaiting approval and the rooms the desk
 * forgot to check in or out (`late`, Settings → Notifications → reminders);
 * it opens a list of both. A late room also shows an alert when it becomes
 * late, and again every 30 minutes while it stays late (remembered for the
 * browser tab, so a reload does not repeat them all).
 *
 * Browsers only play sound after a click or key press on the page: the first
 * one unlocks it (`unlockAudio`). With *Show an alert* off, the bell still
 * counts and the lists still refresh, quietly.
 */
import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Bell,
	CheckCircle2,
	ClipboardCheck,
	LogIn,
	LogOut,
} from 'lucide-react';
import { toast } from 'sonner';

import { get } from '@/api/client';
import { useAccess } from '@/lib/access';
import { formatDateTime, formatTime, formatTimeAgo } from '@/lib/format';
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from '@/components/ui/popover';
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

// A late room is shown again after this long while it stays late.
const REMIND_EVERY = 30 * 60 * 1000;
const REMINDED_KEY = 'rtbp_late_reminded';
// Most reminder alerts at once; the rest are summed up in one.
const MAX_TOASTS = 3;

/**
 * When each late room was last shown (`kind:line_id` → ms), for this tab.
 *
 * @return {Object} Map.
 */
function readReminded() {
	try {
		return JSON.parse(
			window.sessionStorage.getItem( REMINDED_KEY ) || '{}'
		);
	} catch ( e ) {
		return {};
	}
}

/**
 * @param {Object} map Map to keep.
 */
function writeReminded( map ) {
	try {
		window.sessionStorage.setItem( REMINDED_KEY, JSON.stringify( map ) );
	} catch ( e ) {
		// Private mode or blocked storage: alerts may repeat after a reload.
	}
}

/**
 * "Room A3 · RT26-063".
 *
 * @param {Object} item Late room.
 * @return {string} Title.
 */
const lateTitle = ( item ) =>
	sprintf(
		/* translators: 1: room number, 2: booking reference. */
		__( 'Room %1$s · %2$s', 'radius-hotel-booking' ),
		item.room,
		item.reference
	);

/**
 * "Not checked in: due 08:30 (2 hours ago)".
 *
 * @param {Object} item Late room.
 * @return {string} Line.
 */
const lateLine = ( item ) =>
	sprintf(
		'check_out' === item.kind
			? /* translators: 1: time, 2: how long ago. */
			  __(
					'Still in house: check-out was due %1$s (%2$s)',
					'radius-hotel-booking'
			  )
			: /* translators: 1: time, 2: how long ago. */
			  __(
					'Not checked in: arrival was due %1$s (%2$s)',
					'radius-hotel-booking'
			  ),
		// Within a day: "08:30 (2 hours ago)"; older: the date and time.
		item.minutes < 24 * 60
			? formatTime( item.due )
			: formatDateTime( item.due ),
		item.minutes < 24 * 60
			? formatTimeAgo( item.due )
			: sprintf(
					/* translators: %d: number of days. */
					_n(
						'%d day ago',
						'%d days ago',
						Math.floor( item.minutes / ( 24 * 60 ) ),
						'radius-hotel-booking'
					),
					Math.floor( item.minutes / ( 24 * 60 ) )
			  )
	);

/**
 * @return {JSX.Element|null} Bell.
 */
export default function NotificationBell() {
	const allowed = useAccess( 'page.dashboard' ) !== 'locked';
	const navigate = useNavigate();
	const client = useQueryClient();
	const cursor = useRef( 0 );
	const [ awaiting, setAwaiting ] = useState( 0 );
	const [ late, setLate ] = useState( { total: 0, items: [] } );
	const [ open, setOpen ] = useState( false );

	useEffect( () => unlockAudio(), [] );

	useEffect( () => {
		if ( ! allowed ) {
			return undefined;
		}
		const config = settings();
		let stopped = false;

		// The late rooms: kept for the list, an alert for each newly late one
		// (or one late again for 30 minutes), at most MAX_TOASTS at once.
		const remind = ( result ) => {
			setLate( result );
			const now = Date.now();
			const shown = readReminded();
			const keys = new Set();
			const due = result.items.filter( ( item ) => {
				const key = `${ item.kind }:${ item.line_id }`;
				keys.add( key );
				return ! shown[ key ] || now - shown[ key ] >= REMIND_EVERY;
			} );
			// Forget the rooms that are done.
			Object.keys( shown ).forEach( ( key ) => {
				if ( ! keys.has( key ) ) {
					delete shown[ key ];
				}
			} );
			due.forEach( ( item ) => {
				shown[ `${ item.kind }:${ item.line_id }` ] = now;
			} );
			writeReminded( shown );
			due.slice( 0, MAX_TOASTS ).forEach( ( item ) =>
				toast.warning( lateTitle( item ), {
					description: [ item.guest, lateLine( item ) ]
						.filter( Boolean )
						.join( ' · ' ),
					duration: 20000,
					action: {
						label: __( 'Open', 'radius-hotel-booking' ),
						onClick: () =>
							navigate( `/bookings/${ item.booking_id }` ),
					},
				} )
			);
			if ( due.length > MAX_TOASTS ) {
				toast.warning(
					sprintf(
						/* translators: %d: number of rooms. */
						_n(
							'%d more room not checked in or out on time',
							'%d more rooms not checked in or out on time',
							due.length - MAX_TOASTS,
							'radius-hotel-booking'
						),
						due.length - MAX_TOASTS
					),
					{
						description: __(
							'Open the bell to see them all.',
							'radius-hotel-booking'
						),
						duration: 20000,
					}
				);
			}
		};

		const poll = () =>
			get( 'notifications/poll', { since: cursor.current } )
				.then( ( { data } ) => {
					if ( stopped ) {
						return;
					}
					const first = 0 === cursor.current;
					cursor.current = Math.max( cursor.current, data.cursor );
					setAwaiting( data.awaiting );
					remind( data.late || { total: 0, items: [] } );
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

	const count = awaiting + late.total;
	const label = count
		? sprintf(
				/* translators: %d: number of things that need attention. */
				_n(
					'%d thing needs attention',
					'%d things need attention',
					count,
					'radius-hotel-booking'
				),
				count
		  )
		: __( 'Nothing needs attention', 'radius-hotel-booking' );
	const go = ( to ) => {
		setOpen( false );
		navigate( to );
	};

	return (
		<Popover open={ open } onOpenChange={ setOpen }>
			<PopoverTrigger asChild>
				<button
					type="button"
					className="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border sm:h-10 sm:w-10 border-border bg-card text-heading transition-colors hover:border-primary"
					aria-label={ label }
					title={ label }
				>
					<Bell className="h-5 w-5" aria-hidden="true" />
					{ count ? (
						<span className="absolute -right-1.5 -top-1.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-destructive px-1 text-[11px] font-bold leading-none text-white">
							{ count > 99 ? '99+' : count }
						</span>
					) : null }
				</button>
			</PopoverTrigger>
			<PopoverContent
				align="end"
				className="rtbp-root w-[22rem] max-w-[calc(100vw-2rem)] p-0"
			>
				<div className="border-b border-border px-4 py-3">
					<p className="m-0 text-sm font-semibold text-heading">
						{ __( 'Needs attention', 'radius-hotel-booking' ) }
					</p>
				</div>
				<div className="max-h-[60vh] overflow-y-auto">
					{ awaiting ? (
						<button
							type="button"
							onClick={ () => go( '/bookings?tab=awaiting' ) }
							className="flex w-full items-center gap-3 border-0 border-b border-border bg-transparent px-4 py-3 text-left hover:bg-muted"
						>
							<span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-info-soft text-info">
								<ClipboardCheck
									className="h-4 w-4"
									aria-hidden="true"
								/>
							</span>
							<span className="text-sm font-medium text-heading">
								{ sprintf(
									/* translators: %d: number of bookings awaiting approval. */
									_n(
										'%d booking awaiting approval',
										'%d bookings awaiting approval',
										awaiting,
										'radius-hotel-booking'
									),
									awaiting
								) }
							</span>
						</button>
					) : null }
					{ late.items.map( ( item ) => {
						const Icon = 'check_out' === item.kind ? LogOut : LogIn;
						return (
							<button
								key={ `${ item.kind }:${ item.line_id }` }
								type="button"
								onClick={ () =>
									go( `/bookings/${ item.booking_id }` )
								}
								className="flex w-full items-start gap-3 border-0 border-b border-border bg-transparent px-4 py-3 text-left last:border-b-0 hover:bg-muted"
							>
								<span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-warning-soft text-warning">
									<Icon
										className="h-4 w-4"
										aria-hidden="true"
									/>
								</span>
								<span className="min-w-0">
									<span className="block text-sm font-semibold text-heading">
										{ lateTitle( item ) }
									</span>
									{ item.guest ? (
										<span className="block truncate text-xs text-muted-foreground">
											{ item.guest }
										</span>
									) : null }
									<span className="block text-xs text-warning">
										{ lateLine( item ) }
									</span>
								</span>
							</button>
						);
					} ) }
					{ late.total > late.items.length ? (
						<p className="m-0 px-4 py-3 text-xs text-muted-foreground">
							{ sprintf(
								/* translators: %d: number of rooms not listed. */
								_n(
									'%d more late room not listed.',
									'%d more late rooms not listed.',
									late.total - late.items.length,
									'radius-hotel-booking'
								),
								late.total - late.items.length
							) }
						</p>
					) : null }
					{ ! count ? (
						<div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
							<CheckCircle2
								className="h-6 w-6 text-success"
								aria-hidden="true"
							/>
							<p className="m-0 text-sm text-muted-foreground">
								{ __(
									'Nothing needs attention.',
									'radius-hotel-booking'
								) }
							</p>
						</div>
					) : null }
				</div>
			</PopoverContent>
		</Popover>
	);
}
