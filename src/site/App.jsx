/**
 * Public app: one root per embed (src/site/main.jsx). `embed` says which:
 * `search` (the search bar, M04 T1b) or `booking` (the guest booking flow,
 * the same `BookingFlow` as the desk with `mode="guest"`, M04 T2b).
 *
 * Each root has its own query cache and toasts (the flow needs both, as in
 * the admin app); the toaster renders inside the mount node, so it carries
 * the plugin's scoped styles.
 */
import { useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'sonner';

import BookingFlow from '@/components/booking/BookingFlow';
import SearchBar from './SearchBar';

/**
 * The guest booking flow with what it needs around it.
 *
 * @return {JSX.Element} Flow.
 */
function Booking() {
	const [ client ] = useState(
		() =>
			new QueryClient( {
				defaultOptions: {
					queries: { retry: 1, refetchOnWindowFocus: false },
				},
			} )
	);
	return (
		<QueryClientProvider client={ client }>
			<BookingFlow mode="guest" />
			<Toaster
				position="top-center"
				richColors
				closeButton
				theme="light"
			/>
		</QueryClientProvider>
	);
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
