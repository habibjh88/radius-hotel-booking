/**
 * Page actions: lets a screen put its buttons (e.g. "New booking") in the
 * top bar, next to the page title, instead of rendering its own header.
 */
import { createContext, useContext, useEffect, useState } from 'react';

const PageActionsContext = createContext( {
	actions: null,
	setActions: () => {},
} );

/**
 * Provider, mounted once by the app shell.
 *
 * @param {Object}      props          Props.
 * @param {JSX.Element} props.children Shell.
 * @return {JSX.Element} Provider.
 */
export function PageActionsProvider( { children } ) {
	const [ actions, setActions ] = useState( null );

	return (
		<PageActionsContext.Provider value={ { actions, setActions } }>
			{ children }
		</PageActionsContext.Provider>
	);
}

/**
 * The actions of the current page (read by the top bar).
 *
 * @return {JSX.Element|null} Actions.
 */
export function useCurrentPageActions() {
	return useContext( PageActionsContext ).actions;
}

/**
 * Register the current screen's top-bar actions. Cleared on unmount.
 *
 * @param {JSX.Element|null} node Buttons to show.
 * @param {Array}            deps Re-render dependencies.
 * @return {void}
 */
export function usePageActions( node, deps = [] ) {
	const { setActions } = useContext( PageActionsContext );

	useEffect( () => {
		setActions( node );
		return () => setActions( null );
	}, deps );
}
