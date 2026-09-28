/**
 * Whether a CSS media query matches, kept in sync with the viewport.
 */
import { useEffect, useState } from 'react';

/**
 * @param {string} query Media query, e.g. '(min-width: 768px)'.
 * @return {boolean} Matches.
 */
export default function useMediaQuery( query ) {
	const [ matches, setMatches ] = useState(
		() => window.matchMedia?.( query ).matches ?? false
	);

	useEffect( () => {
		const list = window.matchMedia?.( query );
		if ( ! list ) {
			return undefined;
		}
		const onChange = ( event ) => setMatches( event.matches );
		setMatches( list.matches );
		list.addEventListener( 'change', onChange );
		return () => list.removeEventListener( 'change', onChange );
	}, [ query ] );

	return matches;
}
