/**
 * What a locked Pro page or Settings tab shows: its name, what it does and
 * an Upgrade button to the Pro product page. No Pro code runs here.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { AlertTriangle, Check, ExternalLink, Lock } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { upgradeUrl } from '@/admin/proCatalog';
import ProBadge from './ProBadge';

/**
 * Blocks or date settings made while Pro was active still apply (the free
 * engine honours them); they can be changed again once Pro is back.
 *
 * @param {Object} props      Props.
 * @param {string} props.kind `blocks` | `dates`.
 * @return {JSX.Element|null} Notice.
 */
function CarryOver( { kind } ) {
	const counts = window.radius_hotel_booking_param?.upgrade?.carryover || {};
	const count = kind ? Number( counts[ kind ] ) || 0 : 0;
	if ( ! count ) {
		return null;
	}
	const text =
		'blocks' === kind
			? sprintf(
					/* translators: %d: number of blocks. */
					_n(
						'%d block made earlier still closes its rooms. It can be changed or removed once Pro is active again.',
						'%d blocks made earlier still close their rooms. They can be changed or removed once Pro is active again.',
						count,
						'radius-hotel-booking'
					),
					count
			  )
			: sprintf(
					/* translators: %d: number of dates. */
					_n(
						'%d date with its own price or closure still applies to bookings. It can be changed once Pro is active again.',
						'%d dates with their own price or closure still apply to bookings. They can be changed once Pro is active again.',
						count,
						'radius-hotel-booking'
					),
					count
			  );
	return (
		<p className="m-0 mx-auto mt-5 flex max-w-md items-start gap-2 rounded-lg bg-warning-soft px-3 py-2 text-left text-sm text-heading">
			<AlertTriangle
				className="mt-0.5 h-4 w-4 shrink-0 text-warning"
				aria-hidden="true"
			/>
			<span>{ text }</span>
		</p>
	);
}

/**
 * @param {Object} props      Props.
 * @param {Object} props.item `{ label, description, icon?, features? }`.
 * @return {JSX.Element} Panel.
 */
export default function ProLockedPanel( { item } ) {
	const Icon = item?.icon || Lock;
	const features = Array.isArray( item?.features ) ? item.features : [];
	return (
		<div className="rounded-xl border border-border bg-card px-6 py-12 text-center">
			<div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary-soft text-primary">
				<Icon className="h-7 w-7" aria-hidden="true" />
			</div>
			<div className="mb-2 flex items-center justify-center gap-2">
				<h2 className="m-0 p-0 text-lg font-semibold text-heading">
					{ item?.label }
				</h2>
				<ProBadge />
			</div>
			{ item?.description ? (
				<p className="m-0 mx-auto max-w-md text-sm text-muted-foreground">
					{ item.description }
				</p>
			) : null }
			{ features.length ? (
				<ul className="m-0 mx-auto mt-5 max-w-md list-none space-y-2 p-0 text-left">
					{ features.map( ( feature ) => (
						<li
							key={ feature }
							className="m-0 flex items-start gap-2 text-sm text-heading"
						>
							<Check
								className="mt-0.5 h-4 w-4 shrink-0 text-success"
								aria-hidden="true"
							/>
							<span>{ feature }</span>
						</li>
					) ) }
				</ul>
			) : null }
			<CarryOver kind={ item?.carryover } />
			<p className="m-0 mx-auto mt-5 max-w-md text-sm text-muted-foreground">
				{ __(
					'This is part of Radius Hotel Booking Pro. Install and activate Pro to use it; your free features and data stay as they are.',
					'radius-hotel-booking'
				) }
			</p>
			<Button asChild className="mt-6">
				<a
					href={ upgradeUrl() }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Get Pro', 'radius-hotel-booking' ) }
					<ExternalLink aria-hidden="true" />
				</a>
			</Button>
		</div>
	);
}
