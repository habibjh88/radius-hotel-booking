/**
 * Public app: one root per embed (src/site/main.jsx). `embed` says which:
 * `search` (the search bar, M04 T1b) or `booking` (the guest booking flow,
 * the same `BookingFlow` as the desk with `mode="guest"`, M04 T2b). The
 * booking embed's `data-layout` (steps | onepage) picks the flow's face and
 * `data-display="modal"` puts it in a popup behind a button.
 *
 * Each root has its own query cache and toasts (the flow needs both, as in
 * the admin app); the toaster renders inside the mount node, so it carries
 * the plugin's scoped styles.
 */
import { useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'sonner';
import { __ } from '@wordpress/i18n';

import BookingFlow from '@/components/booking/BookingFlow';
import BookingModal from './BookingModal';
import SearchBar from './SearchBar';

/**
 * The guest booking flow with what it needs around it.
 *
 * @param {Object} props         Props.
 * @param {Object} props.options `layout`, `display`, `buttonText`, `roomDetails`, `roomCard`, `ratesShown` (the mount node's data).
 * @return {JSX.Element} Flow.
 */
function Booking( { options = {} } ) {
	const [ client ] = useState(
		() =>
			new QueryClient( {
				defaultOptions: {
					queries: { retry: 1, refetchOnWindowFocus: false },
				},
			} )
	);
	const layout = 'onepage' === options.layout ? 'onepage' : 'steps';
	const flow = ( visible ) => (
		<QueryClientProvider client={ client }>
			<BookingFlow
				mode="guest"
				layout={ layout }
				visible={ visible }
				roomDetails={
					'popup' === options.roomDetails ? 'popup' : 'page'
				}
				roomCard={ 'split' === options.roomCard ? 'split' : 'classic' }
				ratesShown={
					undefined === options.ratesShown
						? 4
						: Math.max( 0, parseInt( options.ratesShown, 10 ) || 0 )
				}
			/>
			<Toaster
				position="top-center"
				richColors
				closeButton
				theme="light"
			/>
		</QueryClientProvider>
	);
	if ( 'modal' === options.display ) {
		return (
			<BookingModal
				label={
					options.buttonText ||
					__( 'Book now', 'radius-hotel-booking' )
				}
			>
				{ flow }
			</BookingModal>
		);
	}
	return flow( true );
}

/**
 * @param {Object} props         Props.
 * @param {string} props.embed   `search` | `booking`.
 * @param {Object} props.options The mount node's data attributes.
 * @return {JSX.Element|null} The embed.
 */
export default function App( { embed, options } ) {
	const embeds = { search: SearchBar, booking: Booking };
	const Embed = embeds[ embed ];
	return Embed ? <Embed options={ options } /> : null;
}
