/**
 * The status palette (design-system §4): one map for every badge, calendar
 * cell and chart legend, so a status always has the same colour and words.
 *
 * Tones map to design tokens: success (green), info (blue), warning (amber),
 * caution (orange), danger (red), neutral (slate). `outline` renders the
 * tone as an outlined badge (ended states: cancelled, declined, no-show).
 * `pattern` marks availability cells that also need a stripe/hatch so the
 * state is never shown by colour alone.
 */
import { __ } from '@wordpress/i18n';

export const STATUS = {
	stay: {
		pending: { label: __( 'Awaiting approval', 'radius-hotel-booking' ), tone: 'warning' },
		confirmed: { label: __( 'Confirmed', 'radius-hotel-booking' ), tone: 'info' },
		checked_in: { label: __( 'Checked in', 'radius-hotel-booking' ), tone: 'success' },
		checked_out: { label: __( 'Checked out', 'radius-hotel-booking' ), tone: 'neutral' },
		cancelled: { label: __( 'Cancelled', 'radius-hotel-booking' ), tone: 'danger', outline: true },
		declined: { label: __( 'Declined', 'radius-hotel-booking' ), tone: 'danger', outline: true },
		no_show: { label: __( 'No-show', 'radius-hotel-booking' ), tone: 'danger', outline: true },
	},
	payment: {
		unpaid: { label: __( 'Unpaid', 'radius-hotel-booking' ), tone: 'warning' },
		on_hold: { label: __( 'On hold', 'radius-hotel-booking' ), tone: 'neutral' },
		partially_paid: { label: __( 'Partially paid', 'radius-hotel-booking' ), tone: 'caution' },
		paid: { label: __( 'Paid', 'radius-hotel-booking' ), tone: 'success' },
		refunded: { label: __( 'Refunded', 'radius-hotel-booking' ), tone: 'neutral', outline: true },
		overdue: { label: __( 'Payment overdue', 'radius-hotel-booking' ), tone: 'danger' },
	},
	room: {
		available: { label: __( 'Available', 'radius-hotel-booking' ), tone: 'success' },
		maintenance: { label: __( 'Maintenance', 'radius-hotel-booking' ), tone: 'warning' },
		out_of_service: { label: __( 'Out of service', 'radius-hotel-booking' ), tone: 'neutral' },
	},
	// A room type's sellability (M06): rooms available to sell, or none.
	readiness: {
		ready: { label: __( 'Ready to sell', 'radius-hotel-booking' ), tone: 'success' },
		no_rooms: { label: __( 'No rooms to sell', 'radius-hotel-booking' ), tone: 'warning' },
		hidden: { label: __( 'Hidden from sale', 'radius-hotel-booking' ), tone: 'neutral', outline: true },
	},
	availability: {
		free: { label: __( 'Free', 'radius-hotel-booking' ), tone: 'success' },
		booked: { label: __( 'Booked', 'radius-hotel-booking' ), tone: 'danger' },
		held: { label: __( 'Held', 'radius-hotel-booking' ), tone: 'warning', pattern: 'stripes' },
		blocked: { label: __( 'Blocked', 'radius-hotel-booking' ), tone: 'neutral', pattern: 'hatch' },
		closed: { label: __( 'Closed', 'radius-hotel-booking' ), tone: 'neutral' },
	},
	// Whether a record is in use: an employee file (M12), and the like.
	active: {
		active: { label: __( 'Active', 'radius-hotel-booking' ), tone: 'success' },
		inactive: { label: __( 'Inactive', 'radius-hotel-booking' ), tone: 'neutral', outline: true },
		// Something that ran its course: an ended contract (M12).
		ended: { label: __( 'Ended', 'radius-hotel-booking' ), tone: 'neutral', outline: true },
	},
	// A guest's standing (M09, 9.11).
	standing: {
		normal: { label: __( 'Normal', 'radius-hotel-booking' ), tone: 'neutral', outline: true },
		banned: { label: __( 'Banned', 'radius-hotel-booking' ), tone: 'danger' },
	},
	// The kind of a note on a guest, booking or employee (M09, 9.8).
	note: {
		general: { label: __( 'Note', 'radius-hotel-booking' ), tone: 'neutral' },
		caution: { label: __( 'Caution', 'radius-hotel-booking' ), tone: 'warning' },
		warning: { label: __( 'Warning', 'radius-hotel-booking' ), tone: 'danger' },
	},
	// The last read of an external calendar or feed (M08: iCal import).
	sync: {
		ok: { label: __( 'Up to date', 'radius-hotel-booking' ), tone: 'success' },
		warning: { label: __( 'Check bookings', 'radius-hotel-booking' ), tone: 'warning' },
		error: { label: __( 'Could not read', 'radius-hotel-booking' ), tone: 'danger' },
		never: { label: __( 'Not read yet', 'radius-hotel-booking' ), tone: 'neutral', outline: true },
	},
};

/**
 * Tailwind classes per tone: [solid, outline]. Literal strings so Tailwind
 * compiles them.
 */
export const TONE_CLASSES = {
	success: [ 'bg-success-soft text-success border-transparent', 'bg-card text-success border-success' ],
	info: [ 'bg-info-soft text-info border-transparent', 'bg-card text-info border-info' ],
	warning: [ 'bg-warning-soft text-warning border-transparent', 'bg-card text-warning border-warning' ],
	caution: [ 'bg-caution-soft text-caution border-transparent', 'bg-card text-caution border-caution' ],
	danger: [ 'bg-destructive-soft text-destructive border-transparent', 'bg-card text-destructive border-destructive' ],
	neutral: [ 'bg-muted text-muted-foreground border-transparent', 'bg-card text-muted-foreground border-border' ],
};

/**
 * Dot colour per tone (CSS variable), for legends and calendar cells.
 */
export const TONE_COLOR = {
	success: 'var(--success)',
	info: 'var(--info)',
	warning: 'var(--warning)',
	caution: 'var(--caution)',
	danger: 'var(--destructive)',
	neutral: 'var(--muted-foreground)',
};

/**
 * Look up a status. Unknown values fall back to a neutral badge showing the
 * raw value, so a new server status never breaks a screen.
 *
 * @param {string} domain 'stay' | 'payment' | 'room' | 'readiness' | 'availability' | 'active' | 'standing' | 'note' | 'sync'.
 * @param {string} value  Status value.
 * @return {{label: string, tone: string, outline?: boolean, pattern?: string}} Status.
 */
export function getStatus( domain, value ) {
	return (
		STATUS[ domain ]?.[ value ] || {
			label: String( value ?? '' ).replace( /_/g, ' ' ),
			tone: 'neutral',
		}
	);
}
