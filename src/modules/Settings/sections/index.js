/**
 * The Settings tabs the free plugin owns, in display order.
 *
 * Each tab: `{ key, label, description, icon, Component, onSaved?, accessKey? }`.
 * `key` is the PHP section (Settings\SettingsSchema); `accessKey` (default
 * `settings.<key>`) hides the tab when locked (M13); `Component` receives
 * `{ value, setField, errors, schema, saving }` (see ../index.jsx);
 * `onSaved( values )` runs after a save or reset of the tab.
 *
 * Later modules add their tab here (M05 payments and invoices, M13 access …). Add-ons use
 * the `rtbp.settings.sections` filter instead.
 */
import { lazy } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Bell,
	Building2,
	CalendarCheck,
	FileText,
	Mail,
	Palette,
	ShieldCheck,
	Wallet,
	Globe,
} from 'lucide-react';

import { refreshAccess } from '@/lib/access';
import { setFormatConfig } from '@/lib/format';

/**
 * @return {Array<Object>} Tabs.
 */
export default function coreSections() {
	return [
		{
			key: 'general',
			label: __( 'General', 'radius-hotel-booking' ),
			description: __(
				'Your hotel’s details, formats and currency',
				'radius-hotel-booking'
			),
			icon: Building2,
			Component: lazy( () => import( './General' ) ),
			// Amounts and dates on other screens follow the new formats at once.
			onSaved: ( general ) =>
				setFormatConfig( {
					currency: {
						code: general.currencyCode,
						symbol: general.currencySymbol,
						position: general.currencyPosition,
						thousand: general.thousandSeparator,
						decimal: general.decimalSeparator,
						decimals: general.decimals,
					},
					...( general.dateFormat
						? { dateFormat: general.dateFormat }
						: {} ),
					timeFormat: general.timeSystem === '24h' ? 'H:i' : 'g:i A',
				} ),
		},
		{
			key: 'booking',
			label: __( 'Booking rules', 'radius-hotel-booking' ),
			description: __(
				'Booking window, same-day bookings, approval and turnover',
				'radius-hotel-booking'
			),
			icon: CalendarCheck,
			Component: lazy( () => import( './Booking' ) ),
		},
		{
			key: 'website',
			label: __( 'Public booking', 'radius-hotel-booking' ),
			description: __(
				'The search bar, the booking page and the booking form',
				'radius-hotel-booking'
			),
			icon: Globe,
			Component: lazy( () => import( './Website' ) ),
		},
		{
			key: 'payments',
			label: __( 'Payments', 'radius-hotel-booking' ),
			description: __(
				'How guests can pay, with the instructions they receive',
				'radius-hotel-booking'
			),
			icon: Wallet,
			Component: lazy( () => import( './Payments' ) ),
			// *Paid now* at the desk offers the new list without a reload.
			onSaved: ( payments ) => {
				const params = window.radius_hotel_booking_param;
				if ( params ) {
					params.payment_methods = ( payments.methods || [] )
						.filter( ( method ) => method.enabled )
						.map( ( { key, label } ) => ( { key, label } ) );
				}
			},
		},
		{
			key: 'invoices',
			label: __( 'Invoices', 'radius-hotel-booking' ),
			description: __(
				'Invoice numbers, tax and the footer text',
				'radius-hotel-booking'
			),
			icon: FileText,
			Component: lazy( () => import( './Invoices' ) ),
		},
		{
			key: 'notifications',
			label: __( 'Notifications', 'radius-hotel-booking' ),
			description: __(
				'The new-booking alert and its sound',
				'radius-hotel-booking'
			),
			icon: Bell,
			Component: lazy( () => import( './Notifications' ) ),
			// The alert on other screens uses the new sound without a reload.
			onSaved: ( notifications ) => {
				const params = window.radius_hotel_booking_param;
				if ( ! params?.settings ) {
					return;
				}
				params.settings.notifications = notifications;
				params.notify_sound_url = '';
				const media = window.wp?.media;
				if ( notifications.soundId && media ) {
					const attachment = media.attachment(
						notifications.soundId
					);
					attachment
						.fetch()
						.then( () => {
							params.notify_sound_url =
								attachment.get( 'url' ) || '';
						} )
						.catch( () => {} );
				}
			},
		},
		{
			key: 'email',
			label: __( 'E-mail', 'radius-hotel-booking' ),
			description: __(
				'Sender, reply-to and which e-mails are sent',
				'radius-hotel-booking'
			),
			icon: Mail,
			Component: lazy( () => import( './Email' ) ),
		},
		{
			key: 'display',
			label: __( 'Display', 'radius-hotel-booking' ),
			description: __(
				'Brand colour of the dashboard and booking pages',
				'radius-hotel-booking'
			),
			icon: Palette,
			Component: lazy( () => import( './Display' ) ),
		},
		{
			key: 'access',
			label: __( 'Permissions', 'radius-hotel-booking' ),
			description: __(
				'What each staff role may open and do',
				'radius-hotel-booking'
			),
			icon: ShieldCheck,
			accessKey: 'access.manage',
			Component: lazy( () => import( './Permissions' ) ),
			// Your own menus follow the new map without a reload.
			onSaved: () => refreshAccess(),
		},
	];
}
