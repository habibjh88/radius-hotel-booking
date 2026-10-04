/**
 * Warnings about the site's own configuration (`Admin\SiteChecks`, localised
 * as `site_checks`): administrators only, e.g. a time zone set as a raw UTC
 * offset. Add-ons add checks on the server (`rtbp_site_checks`). Renders
 * nothing when every check passes.
 */
import { __ } from '@wordpress/i18n';
import { AlertTriangle } from 'lucide-react';

import { cn } from '@/lib/utils';

/**
 * The failing checks.
 *
 * @param {Object} props           Props.
 * @param {string} props.className Extra classes for the wrapper.
 * @return {JSX.Element|null} Warnings.
 */
export default function SiteChecks( { className } ) {
	const params = window.radius_hotel_booking_param || {};
	const checks = Array.isArray( params.site_checks )
		? params.site_checks
		: [];
	if ( ! checks.length ) {
		return null;
	}

	return (
		<div
			role="region"
			aria-label={ __(
				'Site settings to check',
				'radius-hotel-booking'
			) }
			className={ cn( 'space-y-2', className ) }
		>
			{ checks.map( ( check ) => (
				<div
					key={ check.id }
					className="flex items-start gap-3 rounded-xl border border-warning bg-warning-soft p-4"
				>
					<AlertTriangle
						className="mt-0.5 h-5 w-5 shrink-0 text-warning"
						aria-hidden="true"
					/>
					<div className="min-w-0 flex-1">
						<p className="m-0 text-sm text-heading">
							{ check.message }
						</p>
						{ check.action_url && check.action_label ? (
							<a
								href={ check.action_url }
								className="mt-2 inline-flex text-sm font-semibold text-primary"
							>
								{ check.action_label }
							</a>
						) : null }
					</div>
				</div>
			) ) }
		</div>
	);
}
