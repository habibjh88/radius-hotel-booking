/**
 * Display formatting: money and dates, the JS twins of Support\Money and
 * Support\Dates. Configuration comes from the localized `format` block
 * (LoadAssets::format_params), so the browser formats exactly like PHP.
 *
 * Dates are shown in the site time zone, never the browser's: a receptionist
 * in another country still sees the hotel's clock.
 *
 * Accepted date values:
 * - an ISO 8601 string with offset (API responses) or a Date → converted to
 *   the site zone;
 * - a local `Y-m-d H:i:s` / `Y-m-d H:i` / `Y-m-d` string → already site-local
 *   wall-clock time, shown as is.
 */

/**
 * @return {Object} The localized format config.
 */
function config() {
	const params =
		( typeof window !== 'undefined' &&
			( window.radius_hotel_booking_param ||
				window.radius_hotel_booking_site_param ) ) ||
		{};
	return params.format || {};
}

/**
 * Update the format config in place, so amounts and dates follow Settings →
 * General straight after a save, without a reload.
 *
 * @param {Object} changes Keys of the `format` block (currency, dateFormat, timeFormat).
 */
export function setFormatConfig( changes ) {
	const params =
		typeof window !== 'undefined' &&
		( window.radius_hotel_booking_param ||
			window.radius_hotel_booking_site_param );
	if ( params ) {
		params.format = { ...( params.format || {} ), ...changes };
	}
}

/* ------------------------------------------------------------------ Money */

/**
 * Format an amount in the site currency: `$1,250.50`, `15 000 CFA`.
 *
 * @param {number|string} amount             Amount.
 * @param {Object}        options            Options.
 * @param {boolean}       options.symbol     Include the currency symbol.
 * @param {Object}        options.currency   Currency config instead of the site's
 *                                           (a Settings preview).
 * @return {string} Formatted amount.
 */
export function formatMoney(
	amount,
	{ symbol = true, currency: custom } = {}
) {
	const currency = custom || config().currency || {};
	const decimals = Number.isInteger( currency.decimals )
		? currency.decimals
		: 2;
	const factor = 10 ** decimals;
	const value = Number( amount ) || 0;

	// Half away from zero, like PHP round(); toFixed(8) absorbs float noise
	// (1.005 * 100 = 100.49999…).
	const minor =
		Math.sign( value ) *
		Math.round( Number( ( Math.abs( value ) * factor ).toFixed( 8 ) ) );

	const [ whole, fraction = '' ] = ( Math.abs( minor ) / factor )
		.toFixed( decimals )
		.split( '.' );
	const grouped = whole.replace(
		/\B(?=(\d{3})+(?!\d))/g,
		currency.thousand ?? ','
	);
	const number = decimals
		? `${ grouped }${ currency.decimal ?? '.' }${ fraction }`
		: grouped;
	const sign = minor < 0 ? '-' : '';
	const mark = currency.symbol ?? '$';

	if ( ! symbol || ! mark ) {
		return sign + number;
	}

	switch ( currency.position ) {
		case 'right':
			return `${ sign }${ number }${ mark }`;
		case 'right_space':
			return `${ sign }${ number } ${ mark }`;
		case 'left_space':
			return `${ sign }${ mark } ${ number }`;
		default:
			return `${ sign }${ mark }${ number }`;
	}
}

/* ------------------------------------------------------------------ Dates */

const LOCAL_PATTERN =
	/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/;

/**
 * Break a date value into site-local parts.
 *
 * @param {Date|string} value Date value.
 * @return {Object|null} `{ year, month, day, hour, minute, second, weekday }`.
 */
function toParts( value ) {
	if ( value === null || value === undefined || value === '' ) {
		return null;
	}

	// Site-local wall-clock string: take it as is.
	if ( typeof value === 'string' ) {
		const match = value.match( LOCAL_PATTERN );
		if ( match ) {
			const [ , y, m, d, h = '0', i = '0', s = '0' ] = match;
			const year = Number( y );
			const month = Number( m );
			const day = Number( d );
			return {
				year,
				month,
				day,
				hour: Number( h ),
				minute: Number( i ),
				second: Number( s ),
				weekday: new Date(
					Date.UTC( year, month - 1, day )
				).getUTCDay(),
			};
		}
	}

	// A moment in time: convert to the site zone.
	const date = value instanceof Date ? value : new Date( value );
	if ( Number.isNaN( date.getTime() ) ) {
		return null;
	}

	let parts;
	try {
		parts = new Intl.DateTimeFormat( 'en-US', {
			timeZone: config().timezone || undefined,
			year: 'numeric',
			month: 'numeric',
			day: 'numeric',
			hour: 'numeric',
			minute: 'numeric',
			second: 'numeric',
			hourCycle: 'h23',
		} ).formatToParts( date );
	} catch ( e ) {
		// Offsets such as "+00:00" are not IANA zones; fall back to the browser.
		parts = new Intl.DateTimeFormat( 'en-US', {
			year: 'numeric',
			month: 'numeric',
			day: 'numeric',
			hour: 'numeric',
			minute: 'numeric',
			second: 'numeric',
			hourCycle: 'h23',
		} ).formatToParts( date );
	}

	const get = ( type ) =>
		Number( parts.find( ( part ) => part.type === type )?.value || 0 );
	const year = get( 'year' );
	const month = get( 'month' );
	const day = get( 'day' );

	return {
		year,
		month,
		day,
		hour: get( 'hour' ) % 24,
		minute: get( 'minute' ),
		second: get( 'second' ),
		weekday: new Date( Date.UTC( year, month - 1, day ) ).getUTCDay(),
	};
}

/**
 * Localized month or weekday name.
 *
 * @param {'month'|'weekday'} kind  What to name.
 * @param {number}            index Month 1–12 or weekday 0–6.
 * @param {'long'|'short'}    width Name width.
 * @return {string} Name.
 */
function nameOf( kind, index, width ) {
	const locale = config().locale || undefined;
	// 2023-01-01 was a Sunday, so day (1 + index) of January is weekday `index`.
	const date =
		kind === 'month'
			? new Date( Date.UTC( 2023, index - 1, 1 ) )
			: new Date( Date.UTC( 2023, 0, 1 + index ) );
	return new Intl.DateTimeFormat( locale, {
		[ kind ]: width,
		timeZone: 'UTC',
	} ).format( date );
}

const pad = ( number ) => String( number ).padStart( 2, '0' );

/**
 * Render parts with a PHP date() format string (the format WordPress and our
 * settings use). Supports d D j l N w m M n F Y y a A g G h H i s; a
 * backslash escapes the next character.
 *
 * @param {Object} p      Parts from toParts().
 * @param {string} format PHP date format.
 * @return {string} Formatted value.
 */
function render( p, format ) {
	const hour12 = p.hour % 12 || 12;
	const tokens = {
		d: () => pad( p.day ),
		D: () => nameOf( 'weekday', p.weekday, 'short' ),
		j: () => String( p.day ),
		l: () => nameOf( 'weekday', p.weekday, 'long' ),
		N: () => String( p.weekday || 7 ),
		w: () => String( p.weekday ),
		m: () => pad( p.month ),
		M: () => nameOf( 'month', p.month, 'short' ),
		n: () => String( p.month ),
		F: () => nameOf( 'month', p.month, 'long' ),
		Y: () => String( p.year ),
		y: () => String( p.year ).slice( -2 ),
		a: () => ( p.hour < 12 ? 'am' : 'pm' ),
		A: () => ( p.hour < 12 ? 'AM' : 'PM' ),
		g: () => String( hour12 ),
		G: () => String( p.hour ),
		h: () => pad( hour12 ),
		H: () => pad( p.hour ),
		i: () => pad( p.minute ),
		s: () => pad( p.second ),
	};

	let out = '';
	for ( let index = 0; index < format.length; index++ ) {
		const char = format[ index ];
		if ( char === '\\' && index + 1 < format.length ) {
			out += format[ ++index ];
		} else {
			out += tokens[ char ] ? tokens[ char ]() : char;
		}
	}
	return out;
}

/**
 * The site currency's symbol and decimals, for price inputs.
 *
 * @return {{symbol: string, decimals: number}} Currency.
 */
export function currencyInfo() {
	const currency = config().currency || {};
	return {
		symbol: currency.symbol ?? '',
		decimals: Number.isInteger( currency.decimals ) ? currency.decimals : 2,
	};
}

/**
 * Date with the site's date format.
 *
 * @param {Date|string} value Date value.
 * @return {string} Formatted date, or '' for an empty/invalid value.
 */
export function formatDate( value ) {
	const parts = toParts( value );
	return parts ? render( parts, config().dateFormat || 'Y-m-d' ) : '';
}

/**
 * Date with a given PHP date format (a Settings preview).
 *
 * @param {Date|string} value  Date value.
 * @param {string}      format PHP date format.
 * @return {string} Formatted date.
 */
export function formatDateAs( value, format ) {
	const parts = toParts( value );
	return parts ? render( parts, format || 'Y-m-d' ) : '';
}

/**
 * Time with the site's time format (12 h or 24 h).
 *
 * @param {Date|string} value Date value.
 * @return {string} Formatted time.
 */
export function formatTime( value ) {
	const parts = toParts( value );
	return parts ? render( parts, config().timeFormat || 'H:i' ) : '';
}

/**
 * Date and time.
 *
 * @param {Date|string} value Date value.
 * @return {string} Formatted date and time.
 */
export function formatDateTime( value ) {
	const date = formatDate( value );
	return date ? `${ date } ${ formatTime( value ) }` : '';
}

/**
 * A stay window: `02/10/2026 08:30 – 17:00` on one day, or
 * `02/10/2026 20:00 → 03/10/2026 08:00` across days.
 *
 * @param {Date|string} start Start.
 * @param {Date|string} end   End.
 * @return {string} Formatted range.
 */
export function formatDateRange( start, end ) {
	const a = toParts( start );
	const b = toParts( end );
	if ( ! a || ! b ) {
		return '';
	}
	const sameDay = a.year === b.year && a.month === b.month && a.day === b.day;
	return sameDay
		? `${ formatDate( start ) } ${ formatTime( start ) } – ${ formatTime(
				end
		  ) }`
		: `${ formatDateTime( start ) } → ${ formatDateTime( end ) }`;
}

/* --------------------------------------------------------- Calendar days */

/**
 * Today's date in the site time zone, `Y-m-d` — the hotel's "today", which
 * can differ from the browser's near midnight.
 *
 * @return {string} Y-m-d.
 */
export function siteToday() {
	const parts = toParts( new Date() );
	return `${ parts.year }-${ pad( parts.month ) }-${ pad( parts.day ) }`;
}

/**
 * The time now in the site time zone, `HH:MM`.
 *
 * @return {string} Time.
 */
export function siteNowTime() {
	const parts = toParts( new Date() );
	return `${ pad( parts.hour ) }:${ pad( parts.minute ) }`;
}

/**
 * Add calendar days to a `Y-m-d` date (pure date arithmetic, no time zone).
 *
 * @param {string} ymd  Date.
 * @param {number} days Days, may be negative.
 * @return {string} Y-m-d.
 */
export function addDaysYmd( ymd, days ) {
	const [ y, m, d ] = ymd.split( '-' ).map( Number );
	const date = new Date( Date.UTC( y, m - 1, d + days ) );
	return date.toISOString().slice( 0, 10 );
}

/**
 * `Y-m-d` → a Date at local midnight, for calendar widgets that work in
 * plain calendar days (never use it as a moment in time).
 *
 * @param {string} ymd Date.
 * @return {Date|undefined} Date.
 */
export function ymdToDate( ymd ) {
	if ( ! ymd || ! /^\d{4}-\d{2}-\d{2}$/.test( ymd ) ) {
		return undefined;
	}
	const [ y, m, d ] = ymd.split( '-' ).map( Number );
	return new Date( y, m - 1, d );
}

/**
 * A calendar widget's Date → `Y-m-d`.
 *
 * @param {Date} date Date at local midnight.
 * @return {string} Y-m-d, or '' for no date.
 */
export function dateToYmd( date ) {
	if ( ! ( date instanceof Date ) || Number.isNaN( date.getTime() ) ) {
		return '';
	}
	return `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad(
		date.getDate()
	) }`;
}
