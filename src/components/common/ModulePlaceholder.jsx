/**
 * Placeholder for a screen whose module has not been built yet. It keeps the
 * navigation complete during development and says exactly which module will
 * replace it (docs/modules/Mxx-*.md). Removed route by route as modules land.
 */
import { Link } from 'react-router-dom';
import { __, sprintf } from '@wordpress/i18n';
import { Hammer } from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import { Button } from '@/components/ui/button';

/**
 * @param {Object} props       Props.
 * @param {Object} props.route The route being shown.
 * @return {JSX.Element} Screen.
 */
export default function ModulePlaceholder( { route } ) {
	return (
		<Panel>
			<EmptyState
				icon={ route.icon || Hammer }
				title={ sprintf(
					/* translators: %s: screen name, e.g. "Bookings". */
					__( '%s is on its way', 'radius-hotel-booking' ),
					route.label
				) }
				description={ sprintf(
					/* translators: 1: what the screen does, 2: module id such as M06. */
					__( '%1$s. This screen is built in module %2$s.', 'radius-hotel-booking' ),
					route.description || route.label,
					route.module
				) }
				action={
					<Button asChild variant="outline">
						<Link to="/">
							{ __( 'Back to dashboard', 'radius-hotel-booking' ) }
						</Link>
					</Button>
				}
				className="border-0 py-16"
			/>
		</Panel>
	);
}
