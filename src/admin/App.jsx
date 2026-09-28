import { HashRouter, Link, Route, Routes } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';
import { Suspense } from 'react';
import { __ } from '@wordpress/i18n';
import { SearchX } from 'lucide-react';

import AppShell from '@/components/layout/AppShell';
import EmptyState from '@/components/common/EmptyState';
import ModulePlaceholder from '@/components/common/ModulePlaceholder';
import Panel from '@/components/common/Panel';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { queryClient } from '@/lib/query-client';
import { getRoutes } from './routes';

/**
 * Placeholder shown while a lazily-loaded screen downloads.
 *
 * @return {JSX.Element} Skeleton.
 */
function ScreenSkeleton() {
	return (
		<div className="space-y-6" role="status" aria-live="polite">
			<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
				{ [ 0, 1, 2, 3 ].map( ( key ) => (
					<Skeleton key={ key } className="h-[92px] rounded-xl" />
				) ) }
			</div>
			<Skeleton className="h-72 rounded-xl" />
		</div>
	);
}

/**
 * Unknown route.
 *
 * @return {JSX.Element} Screen.
 */
function NotFound() {
	return (
		<Panel>
			<EmptyState
				icon={ SearchX }
				title={ __(
					'This page does not exist',
					'radius-hotel-booking'
				) }
				description={ __(
					'The link may be old, or the screen may belong to a feature that is switched off.',
					'radius-hotel-booking'
				) }
				action={
					<Button asChild>
						<Link to="/">
							{ __(
								'Go to the dashboard',
								'radius-hotel-booking'
							) }
						</Link>
					</Button>
				}
				className="border-0 py-16"
			/>
		</Panel>
	);
}

/**
 * The admin single-page app.
 *
 * HashRouter is deliberate: wp-admin owns the real URL, so routes live in the
 * fragment (`admin.php?page=radius-hotel-booking#/bookings`).
 *
 * @return {JSX.Element} App.
 */
export default function App() {
	return (
		<QueryClientProvider client={ queryClient }>
			<HashRouter>
				<AppShell>
					<Suspense fallback={ <ScreenSkeleton /> }>
						<Routes>
							{ getRoutes().map( ( route ) => {
								const Screen = route.element;
								return (
									<Route
										key={ route.path }
										path={ route.path }
										element={
											Screen ? (
												<Screen />
											) : (
												<ModulePlaceholder
													route={ route }
												/>
											)
										}
									/>
								);
							} ) }
							<Route path="*" element={ <NotFound /> } />
						</Routes>
					</Suspense>
				</AppShell>
			</HashRouter>
		</QueryClientProvider>
	);
}
