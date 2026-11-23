/**
 * A locked Pro page (route `pro` data from `@/admin/proCatalog`).
 */
import { useLocation } from 'react-router-dom';

import { matchRoute } from '@/admin/routes';
import ProLockedPanel from './ProLockedPanel';

export default function ProLockedScreen() {
	const { pathname } = useLocation();
	return <ProLockedPanel item={ matchRoute( pathname ) } />;
}
