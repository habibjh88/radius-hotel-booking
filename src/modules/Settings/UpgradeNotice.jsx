/**
 * The one upsell the free plugin shows: a dismissible notice at the top of
 * Settings, only while Pro is not active (ADR-016). PHP decides whether it
 * shows (Admin\UpgradeNotice, localised as `upgrade`); dismissing stores user
 * meta through the core `wp/v2/users/me` endpoint, so it stays dismissed on
 * every device.
 */
import { useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { ExternalLink, Sparkles, X } from 'lucide-react';

import { Button } from '@/components/ui/button';

/**
 * @return {JSX.Element|null} Notice.
 */
export default function UpgradeNotice() {
	const params = window.radius_hotel_booking_param || {};
	const upgrade = params.upgrade || {};
	const [ visible, setVisible ] = useState( !! upgrade.show );

	if ( ! visible ) {
		return null;
	}

	const dismiss = () => {
		setVisible( false );
		// The core REST root, from our namespace's URL.
		const root = String( params.rest_url || '' ).replace(
			/radius-hotel-booking\/v1\/?$/,
			''
		);
		apiFetch( {
			url: `${ root }wp/v2/users/me`,
			method: 'POST',
			headers: params.nonce ? { 'X-WP-Nonce': params.nonce } : {},
			data: { meta: { rtbp_upgrade_notice_dismissed: true } },
		} ).catch( () => {} );
	};

	return (
		<div
			role="region"
			aria-label={ __( 'Upgrade', 'radius-hotel-booking' ) }
			className="mb-6 flex items-start gap-3 rounded-xl border border-border bg-primary-soft p-4"
		>
			<Sparkles
				className="mt-0.5 h-5 w-5 shrink-0 text-primary"
				aria-hidden="true"
			/>
			<div className="min-w-0 flex-1">
				<p className="m-0 text-sm font-semibold text-heading">
					{ __( 'Radius Hotel Booking Pro', 'radius-hotel-booking' ) }
				</p>
				<p className="m-0 mt-1 text-[13px] leading-5 text-muted-foreground">
					{ __(
						'Adds an activity log, staff passcodes, advanced pricing rules, PDF invoices, iCal sync, and advanced reports and exports.',
						'radius-hotel-booking'
					) }
				</p>
				<a
					href={ upgrade.url }
					target="_blank"
					rel="noopener noreferrer"
					className="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-primary"
				>
					{ __( 'Upgrade', 'radius-hotel-booking' ) }
					<ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
				</a>
			</div>
			<Button
				variant="ghost"
				size="icon"
				onClick={ dismiss }
				aria-label={ __( 'Dismiss', 'radius-hotel-booking' ) }
				className="-mr-1 -mt-1 h-8 w-8 shrink-0"
			>
				<X aria-hidden="true" />
			</Button>
		</div>
	);
}
