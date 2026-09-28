import { HashRouter, Route, Routes } from 'react-router-dom';
import { Suspense } from 'react';

import Layout from '@/components/Layout';
import routes from './routes';

/**
 * Full-page spinner shown while a lazily-loaded route resolves.
 *
 * @return {JSX.Element} Spinner.
 */
function LoadingSpinner() {
	return (
		<div className="flex h-64 items-center justify-center" role="status">
			<div className="h-8 w-8 animate-spin rounded-full border-2 border-muted border-t-primary" />
		</div>
	);
}

/**
 * The admin single-page app.
 *
 * HashRouter is deliberate: wp-admin owns the real URL, so routes live in the
 * fragment (`admin.php?page=radius-hotel-booking#/items`). The submenu items
 * registered in Admin\Menu link straight to these hashes.
 *
 * @return {JSX.Element} App.
 */
export default function App() {
	return (
		<HashRouter>
			<Layout>
				<Routes>
					{ routes.map( ( { path, element: Element } ) => (
						<Route
							key={ path }
							path={ path }
							element={
								<Suspense fallback={ <LoadingSpinner /> }>
									<Element />
								</Suspense>
							}
						/>
					) ) }
				</Routes>
			</Layout>
		</HashRouter>
	);
}
