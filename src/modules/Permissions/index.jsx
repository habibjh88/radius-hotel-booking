/**
 * System → Permissions: the `access` settings section (what each staff role
 * may open and do) as a page of its own, with the Settings screen's save bar.
 */
import Settings from '@/modules/Settings';

/**
 * @return {JSX.Element} Screen.
 */
export default function Permissions() {
	return <Settings only="access" />;
}
