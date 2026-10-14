/**
 * Any Lucide icon by its name (`wifi`, `air-vent`), the way settings and
 * records store it. Drawn from the plugin's icon files (`build/icons/`)
 * through a CSS mask, so it takes the text colour and costs no JavaScript:
 * a screen that shows icons never loads the whole set (only the picker does).
 *
 *   <LucideIcon name={ amenity.icon } className="h-4 w-4" />
 */
import { cn } from '@/lib/utils';

/**
 * The icon files' folder URL.
 *
 * @return {string} URL ending in `/`.
 */
export function iconsUrl() {
	const params =
		window.radius_hotel_booking_param ||
		window.radius_hotel_booking_site_param ||
		{};
	return params.icons_url || '';
}

/**
 * @param {Object} props           Props.
 * @param {string} props.name      Icon name ('' renders nothing).
 * @param {string} props.className Classes (size it: `h-4 w-4`; default 1em).
 * @param {string} props.label     Accessible name; without it the icon is decorative.
 * @return {JSX.Element|null} Icon.
 */
export default function LucideIcon( { name, className, label } ) {
	const base = iconsUrl();
	if ( ! name || ! base || ! /^[a-z0-9]+(-[a-z0-9]+)*$/.test( name ) ) {
		return null;
	}
	const url = `url("${ base }${ name }.svg")`;
	return (
		<span
			role={ label ? 'img' : undefined }
			aria-label={ label || undefined }
			aria-hidden={ label ? undefined : true }
			className={ cn(
				'inline-block h-[1em] w-[1em] shrink-0 bg-current',
				className
			) }
			style={ {
				WebkitMaskImage: url,
				maskImage: url,
				WebkitMaskRepeat: 'no-repeat',
				maskRepeat: 'no-repeat',
				WebkitMaskSize: 'contain',
				maskSize: 'contain',
				WebkitMaskPosition: 'center',
				maskPosition: 'center',
			} }
		/>
	);
}
