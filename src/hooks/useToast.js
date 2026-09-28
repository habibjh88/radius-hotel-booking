import { useCallback } from 'react';

export default function useToast() {
	return useCallback( ( { message, type = 'success', duration = 3000 } ) => {
		if (
			typeof window !== 'undefined' &&
			typeof window.showToast === 'function'
		) {
			window.showToast( { message, type, duration } );
		}
		// No toast host on the page — callers treat a toast as best-effort.
	}, [] );
}
