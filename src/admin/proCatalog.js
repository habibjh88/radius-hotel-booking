/**
 * What Radius Hotel Booking Pro adds, shown locked (a lock and a PRO badge)
 * while Pro is not installed or not active, so the site owner can see it.
 *
 * Only names and descriptions live here: no Pro code ships in the free plugin
 * (ADR-016, amended). PHP decides who sees them (`upgrade.locked`: Pro not
 * active, administrators only). The moment Pro is active its real pages and
 * tabs take these paths and keys, and nothing here is shown.
 */
import { __ } from '@wordpress/i18n';
import {
	ArrowRightLeft,
	Banknote,
	BadgePercent,
	Ban,
	BedDouble,
	Calculator,
	CalendarDays,
	CalendarPlus,
	CalendarSync,
	History,
	Hourglass,
	KeyRound,
	Layers,
	LayoutGrid,
	ShieldHalf,
	UserCog,
	UsersRound,
} from 'lucide-react';

/**
 * Whether locked Pro items are shown to the current user.
 *
 * @return {boolean} Shown.
 */
export function showsProLocked() {
	return window.radius_hotel_booking_param?.upgrade?.locked === true;
}

/**
 * The Pro product page.
 *
 * @return {string} URL.
 */
export function upgradeUrl() {
	return (
		window.radius_hotel_booking_param?.upgrade?.url ||
		'https://radiustheme.com'
	);
}

/**
 * Pro's dashboard pages (same paths and groups as Pro registers).
 *
 * @return {Array<Object>} Pages.
 */
export function proPages() {
	return [
		// `after`: listed in reverse, each goes right below that page.
		{
			path: '/blocks',
			after: '/',
			group: 'overview',
			icon: Ban,
			label: __( 'Blocked dates', 'radius-hotel-booking' ),
			description: __(
				'Close the hotel, a floor, a room type or a room for a while',
				'radius-hotel-booking'
			),
			carryover: 'blocks',
			features: [
				__(
					'Close the whole hotel, a floor, a room type or one room for any dates',
					'radius-hotel-booking'
				),
				__(
					'Imported calendar blocks from Booking.com or Airbnb listed in one place',
					'radius-hotel-booking'
				),
			],
		},
		{
			// Linked from the Rooms sub-menu and screen, not the sidebar.
			path: '/rooms/floors',
			hidden: true,
			group: 'hotel',
			icon: Layers,
			label: __( 'Floors', 'radius-hotel-booking' ),
			description: __(
				'Name the floors of the hotel and group its rooms by floor',
				'radius-hotel-booking'
			),
			features: [
				__(
					'Add, rename and order floors; rooms are listed floor by floor',
					'radius-hotel-booking'
				),
				__(
					'Block a whole floor for works or an event',
					'radius-hotel-booking'
				),
			],
		},
		{
			path: '/calendar',
			after: '/',
			group: 'overview',
			icon: CalendarDays,
			label: __( 'Availability', 'radius-hotel-booking' ),
			description: __(
				'Open, close and price your rooms by date',
				'radius-hotel-booking'
			),
			carryover: 'dates',
			features: [
				__(
					'A month calendar per room type: price, open or close each date',
					'radius-hotel-booking'
				),
				__(
					'Change a whole season at once with the bulk editor',
					'radius-hotel-booking'
				),
			],
		},
		{
			path: '/pro/room-availability',
			after: '/reports',
			group: 'insights',
			icon: LayoutGrid,
			label: __( 'Room availability', 'radius-hotel-booking' ),
			description: __(
				'Which rooms are free for each stay window',
				'radius-hotel-booking'
			),
			features: [
				__(
					'Every room by type and floor: free, booked, held or blocked',
					'radius-hotel-booking'
				),
				__(
					'Pick any window, or see right now, and export it',
					'radius-hotel-booking'
				),
			],
		},
		{
			path: '/pro/rooms-report',
			after: '/reports',
			group: 'insights',
			icon: BedDouble,
			label: __( 'Rooms report', 'radius-hotel-booking' ),
			description: __(
				'Rented and empty rooms in a period, by floor',
				'radius-hotel-booking'
			),
			features: [
				__(
					'Rented, empty, maintenance and out-of-service rooms for any period',
					'radius-hotel-booking'
				),
				__(
					'Booked rooms floor by floor, with every booking listed',
					'radius-hotel-booking'
				),
			],
		},
		{
			path: '/calendar-sync',
			group: 'overview',
			icon: CalendarSync,
			label: __( 'Calendar sync', 'radius-hotel-booking' ),
			description: __(
				'Share and read room calendars with channels',
				'radius-hotel-booking'
			),
			features: [
				__(
					'Export each room type as an iCal feed for Booking.com, Airbnb or Expedia',
					'radius-hotel-booking'
				),
				__(
					'Import their bookings so rooms are never double-booked',
					'radius-hotel-booking'
				),
			],
		},
		{
			path: '/cash-register',
			group: 'frontDesk',
			icon: Calculator,
			label: __( 'Cash register', 'radius-hotel-booking' ),
			description: __(
				'Open and close your register; what came in per payment method',
				'radius-hotel-booking'
			),
			features: [
				__(
					'Open and close the register for each shift',
					'radius-hotel-booking'
				),
				__(
					'Totals per payment method, and the difference at closing',
					'radius-hotel-booking'
				),
			],
		},
		{
			path: '/activity',
			group: 'insights',
			icon: History,
			label: __( 'Activity log', 'radius-hotel-booking' ),
			description: __(
				'Who did what, when and from which device',
				'radius-hotel-booking'
			),
			features: [
				__(
					'Every booking, payment and setting change, with the person and the time',
					'radius-hotel-booking'
				),
				__(
					'Filter by person, action or date, and archive old entries',
					'radius-hotel-booking'
				),
			],
		},
		{
			path: '/pricing',
			group: 'hotel',
			icon: BadgePercent,
			label: __( 'Pricing rules', 'radius-hotel-booking' ),
			description: __(
				'Seasonal, occupancy and booking-window prices',
				'radius-hotel-booking'
			),
			features: [
				__(
					'Higher prices for busy seasons and weekends',
					'radius-hotel-booking'
				),
				__(
					'Early-booking and last-minute prices, and long-stay discounts',
					'radius-hotel-booking'
				),
			],
		},
		{
			path: '/staff',
			group: 'system',
			icon: UsersRound,
			label: __( 'Staff', 'radius-hotel-booking' ),
			description: __(
				'Staff files, contracts and documents',
				'radius-hotel-booking'
			),
			features: [
				__(
					'A file for each employee: contract, documents and shifts',
					'radius-hotel-booking'
				),
				__(
					'Staff see their own profile and payslips',
					'radius-hotel-booking'
				),
			],
		},
		{
			path: '/payroll',
			group: 'system',
			icon: Banknote,
			label: __( 'Payroll', 'radius-hotel-booking' ),
			description: __(
				'Pay runs, payslips, taxes and contributions, and time off',
				'radius-hotel-booking'
			),
			features: [
				__( 'Monthly pay runs with payslips', 'radius-hotel-booking' ),
				__(
					'Deductions, contributions and time off',
					'radius-hotel-booking'
				),
			],
		},
		{
			path: '/access/roles',
			group: 'system',
			icon: ShieldHalf,
			label: __( 'Access roles', 'radius-hotel-booking' ),
			description: __(
				'Named roles such as Receptionist, with their permissions',
				'radius-hotel-booking'
			),
			features: [
				__(
					'Create your own roles and choose what each may do',
					'radius-hotel-booking'
				),
				__(
					'Ask for a PIN before sensitive actions',
					'radius-hotel-booking'
				),
			],
		},
		{
			path: '/access/staff',
			group: 'system',
			icon: UserCog,
			label: __( 'Staff access', 'radius-hotel-booking' ),
			description: __(
				'Each person’s access role, PIN and personal overrides',
				'radius-hotel-booking'
			),
			features: [
				__(
					'Give each person a role and a personal PIN',
					'radius-hotel-booking'
				),
				__(
					'Allow or block single actions for one person',
					'radius-hotel-booking'
				),
			],
		},
	];
}

/**
 * *Add a room* on the booking screen, shown locked on a booking still going
 * ahead.
 *
 * @return {Object} Item.
 */
export function proAddRoom() {
	return {
		label: __( 'Add a room', 'radius-hotel-booking' ),
		icon: BedDouble,
		description: __(
			'Add another room to a booking already made, from its booking screen.',
			'radius-hotel-booking'
		),
		features: [
			__(
				'Only rooms free for the stay are offered',
				'radius-hotel-booking'
			),
			__(
				'The booking total and the invoice are updated',
				'radius-hotel-booking'
			),
		],
	};
}

/**
 * Pro's actions on a checked-in room (booking screen and front desk list
 * menus), shown locked on a checked-in room.
 *
 * @return {Array<Object>} Actions.
 */
export function proLineActions() {
	return [
		{
			action: 'extend_stay',
			label: __( 'Extend stay', 'radius-hotel-booking' ),
			icon: CalendarPlus,
			description: __(
				'Let a guest in the room stay on for another slot, in the same room.',
				'radius-hotel-booking'
			),
			features: [
				__(
					'Only the slots the room is free for are offered',
					'radius-hotel-booking'
				),
				__(
					'The price is added to the booking and the invoice',
					'radius-hotel-booking'
				),
			],
		},
		{
			action: 'move_room',
			label: __( 'Move to another room', 'radius-hotel-booking' ),
			icon: ArrowRightLeft,
			description: __(
				'Move a checked-in guest to another room of the same type during the stay.',
				'radius-hotel-booking'
			),
			features: [
				__(
					'The stay is split at the move; the booking total stays the same',
					'radius-hotel-booking'
				),
				__(
					'Put the room left in maintenance, with the reason',
					'radius-hotel-booking'
				),
				__(
					'Lists the bookings still to come in that room',
					'radius-hotel-booking'
				),
			],
		},
	];
}

/**
 * Pro's Settings tabs (same keys as Pro registers).
 *
 * @return {Array<Object>} Tabs.
 */
export function proSettings() {
	return [
		{
			key: 'pro_staff',
			icon: UsersRound,
			label: __( 'Staff', 'radius-hotel-booking' ),
			description: __(
				'Departments and job titles',
				'radius-hotel-booking'
			),
		},
		{
			key: 'payroll',
			icon: Banknote,
			label: __( 'Payroll', 'radius-hotel-booking' ),
			description: __(
				'Taxes and contributions, and the vacation quota',
				'radius-hotel-booking'
			),
		},
		{
			key: 'pro_access',
			icon: KeyRound,
			label: __( 'Passcodes', 'radius-hotel-booking' ),
			description: __(
				'PIN confirmation, the fallback PIN and the locked message',
				'radius-hotel-booking'
			),
		},
		{
			key: 'pro_cash_register',
			icon: Calculator,
			label: __( 'Cash register', 'radius-hotel-booking' ),
			description: __(
				'Whether payments need an open register',
				'radius-hotel-booking'
			),
		},
		{
			key: 'pro_auto_release',
			icon: Hourglass,
			label: __( 'Automatic release', 'radius-hotel-booking' ),
			description: __(
				'Free the room when a booking stays unpaid past its deadline',
				'radius-hotel-booking'
			),
		},
		{
			key: 'pro_activity',
			icon: History,
			label: __( 'Activity log', 'radius-hotel-booking' ),
			description: __(
				'Archiving, removing archived entries and page views',
				'radius-hotel-booking'
			),
		},
	];
}
