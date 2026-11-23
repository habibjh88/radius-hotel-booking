/**
 * Search box for the Settings screen: finds a setting by its label, its help
 * text or its card's heading, on any tab, and jumps to it.
 *
 * Nothing keeps a list of settings by hand. While the box is in use, every
 * tab is rendered once in a hidden, read-only container (changes are thrown
 * away and nothing can be saved from it) and its card headings and field
 * labels are read from there, so a new setting, or an add-on's tab, is found
 * without extra work. The active tab is read from the screen itself.
 */
import {
	Component as ReactComponent,
	Suspense,
	useEffect,
	useId,
	useMemo,
	useRef,
	useState,
} from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Search, X } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** Labels longer than this are sentences (radio help, notes), not names. */
const MAX_LABEL = 90;

/** Results shown at most. */
const MAX_RESULTS = 12;

/**
 * Lower case, without accents, so "reservation" finds "Réservation".
 *
 * @param {string} text Text.
 * @return {string} Folded text.
 */
export function fold( text ) {
	return String( text || '' )
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.toLowerCase()
		.replace( /\s+/g, ' ' )
		.trim();
}

/**
 * Visible text of an element, without a required-field asterisk.
 *
 * @param {Element} el Element.
 * @return {string} Text.
 */
function textOf( el ) {
	return ( el?.textContent || '' )
		.replace( /\s*\*\s*$/, '' )
		.replace( /\s+/g, ' ' )
		.trim();
}

/**
 * The searchable items of one rendered tab: each card heading and each field
 * label, with the card it sits in and its help line.
 *
 * @param {Element} root  The tab's content.
 * @param {Object}  tab   The tab (`key`, `label`).
 * @return {Array<Object>} Items `{ tab, tabLabel, section, label, help }`.
 */
export function harvest( root, tab ) {
	if ( ! root ) {
		return [];
	}
	const items = [];
	const seen = new Set();
	const add = ( item ) => {
		const id = `${ item.section }|${ item.label }`;
		if ( ! item.label || item.label.length > MAX_LABEL || seen.has( id ) ) {
			return;
		}
		seen.add( id );
		items.push( { tab: tab.key, tabLabel: tab.label, ...item } );
	};
	root.querySelectorAll( 'section' ).forEach( ( card ) => {
		const heading = card.querySelector( 'header h2' );
		const section = textOf( heading );
		if ( section ) {
			add( {
				section: '',
				label: section,
				help: textOf( heading.nextElementSibling ),
			} );
		}
		card.querySelectorAll( 'label, h4' ).forEach( ( el ) => {
			// A choice inside a group (radio, checkbox list) is not a setting.
			const target = el.htmlFor
				? card.querySelector( `[id="${ CSS.escape( el.htmlFor ) }"]` )
				: null;
			if (
				target &&
				[ 'radio', 'checkbox' ].includes(
					target.getAttribute( 'role' ) || target.type
				) &&
				target.closest( '[role="radiogroup"], fieldset' )
			) {
				return;
			}
			const next = el.nextElementSibling;
			add( {
				section,
				label: textOf( el ),
				help: next && 'P' === next.tagName ? textOf( next ) : '',
			} );
		} );
	} );
	return items;
}

/**
 * Items that match every word of the query, best first: a match in the
 * label, then in the card heading, then in the help or tab name.
 *
 * @param {Array<Object>} items Items.
 * @param {string}        query What was typed.
 * @return {Array<Object>} Matches.
 */
export function rank( items, query ) {
	const words = fold( query ).split( ' ' ).filter( Boolean );
	if ( ! words.length ) {
		return [];
	}
	const whole = words.join( ' ' );
	return items
		.map( ( item ) => {
			const label = fold( item.label );
			const section = fold( item.section );
			const all = `${ label } ${ section } ${ fold( item.help ) } ${ fold(
				item.tabLabel
			) }`;
			if ( ! words.every( ( word ) => all.includes( word ) ) ) {
				return null;
			}
			let score = 0;
			if ( label.startsWith( whole ) ) {
				score += 6;
			} else if ( label.includes( whole ) ) {
				score += 4;
			} else if ( words.every( ( word ) => label.includes( word ) ) ) {
				score += 3;
			}
			if ( section.includes( whole ) ) {
				score += 2;
			}
			// A card heading on its own ranks with its fields, not above them.
			if ( ! item.section ) {
				score -= 0.5;
			}
			return { ...item, score };
		} )
		.filter( Boolean )
		.sort( ( a, b ) => b.score - a.score )
		.slice( 0, MAX_RESULTS );
}

/**
 * A tab that fails to render while hidden is left out of the search.
 */
class Quiet extends ReactComponent {
	constructor( props ) {
		super( props );
		this.state = { failed: false };
	}

	static getDerivedStateFromError() {
		return { failed: true };
	}

	render() {
		return this.state.failed ? null : this.props.children;
	}
}

/**
 * Renders the given tabs hidden and read-only, and reports their items each
 * time their content settles (lazy tabs arrive late).
 *
 * @param {Object}        props          Props.
 * @param {Array<Object>} props.tabs     Tabs to read.
 * @param {Function}      props.propsFor Props for a tab (read-only).
 * @param {Function}      props.onItems  Called with all items found.
 * @return {JSX.Element} Hidden container.
 */
function Harvester( { tabs, propsFor, onItems } ) {
	const ref = useRef( null );
	useEffect( () => {
		const root = ref.current;
		if ( ! root ) {
			return undefined;
		}
		let timer = 0;
		const read = () => {
			const items = [];
			root.querySelectorAll( '[data-settings-tab]' ).forEach( ( el ) => {
				const tab = tabs.find(
					( item ) => item.key === el.dataset.settingsTab
				);
				if ( tab ) {
					items.push( ...harvest( el, tab ) );
				}
			} );
			onItems( items );
		};
		const observer = new window.MutationObserver( () => {
			window.clearTimeout( timer );
			timer = window.setTimeout( read, 150 );
		} );
		observer.observe( root, {
			childList: true,
			subtree: true,
			characterData: true,
		} );
		read();
		return () => {
			observer.disconnect();
			window.clearTimeout( timer );
		};
	}, [ tabs, onItems ] );

	return (
		<div ref={ ref } hidden aria-hidden="true" inert="">
			{ tabs.map( ( tab ) => {
				const TabComponent = tab.Component;
				const props = propsFor( tab.key );
				return (
					<div key={ tab.key } data-settings-tab={ tab.key }>
						<Quiet>
							<Suspense fallback={ null }>
								{ TabComponent ? (
									<TabComponent { ...props } />
								) : (
									tab.render( props )
								) }
							</Suspense>
						</Quiet>
					</div>
				);
			} ) }
		</div>
	);
}

/**
 * Part of a text with the query's words marked.
 *
 * @param {Object} props       Props.
 * @param {string} props.text  Text.
 * @param {string} props.query Query.
 * @return {JSX.Element} Text.
 */
function Marked( { text, query } ) {
	const words = fold( query ).split( ' ' ).filter( Boolean );
	if ( ! words.length || ! text ) {
		return text || null;
	}
	// Folding keeps one character per character for Latin text, so positions
	// in the folded text are positions in the original.
	const folded = fold( text );
	if ( folded.length !== text.replace( /\s+/g, ' ' ).trim().length ) {
		return text;
	}
	const plain = text.replace( /\s+/g, ' ' ).trim();
	const marks = new Array( plain.length ).fill( false );
	words.forEach( ( word ) => {
		let at = folded.indexOf( word );
		while ( at !== -1 ) {
			for ( let i = at; i < at + word.length; i++ ) {
				marks[ i ] = true;
			}
			at = folded.indexOf( word, at + word.length );
		}
	} );
	const parts = [];
	let start = 0;
	for ( let i = 1; i <= plain.length; i++ ) {
		if ( i === plain.length || marks[ i ] !== marks[ start ] ) {
			const part = plain.slice( start, i );
			parts.push(
				marks[ start ] ? (
					<mark
						key={ start }
						className="rounded-sm bg-primary-soft px-0 text-primary"
					>
						{ part }
					</mark>
				) : (
					<span key={ start }>{ part }</span>
				)
			);
			start = i;
		}
	}
	return parts;
}

/**
 * @param {Object}        props            Props.
 * @param {Array<Object>} props.tabs       Tabs the user can open.
 * @param {string}        props.active     Active tab key.
 * @param {Function}      props.activeRoot Returns the active tab's content.
 * @param {Function}      props.propsFor   Read-only props for a tab.
 * @param {Function}      props.onPick     Called with the chosen item.
 * @return {JSX.Element} Search box and results.
 */
export default function SettingsSearch( {
	tabs,
	active,
	activeRoot,
	propsFor,
	onPick,
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ open, setOpen ] = useState( false );
	const [ cursor, setCursor ] = useState( 0 );
	const [ hiddenItems, setHiddenItems ] = useState( [] );
	// Rendering every tab costs a little: only once the box is used.
	const [ indexing, setIndexing ] = useState( false );
	const listId = useId();
	const boxRef = useRef( null );

	// Tabs read in the hidden container: settings tabs other than the one on
	// screen (its copy would repeat its field ids).
	const hiddenTabs = useMemo(
		() =>
			tabs.filter(
				( tab ) => ! tab.tool && ! tab.locked && tab.key !== active
			),
		[ tabs, active ]
	);

	const items = useMemo( () => {
		const current = tabs.find( ( tab ) => tab.key === active );
		const onScreen =
			current && ! current.tool && ! current.locked
				? harvest( activeRoot(), current )
				: [];
		// Every tab can be found by its own name too (Pro and tool tabs
		// included, which have no settings of their own).
		const names = tabs.map( ( tab ) => ( {
			tab: tab.key,
			tabLabel: tab.label,
			section: '',
			label: tab.label,
			help: tab.description || '',
			isTab: true,
			locked: !! tab.locked,
		} ) );
		return [ ...names, ...onScreen, ...hiddenItems ];
		// query: the active tab is read again as the user types (it may have
		// opened a card since).
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ tabs, active, hiddenItems, query, open ] );

	const results = useMemo( () => rank( items, query ), [ items, query ] );

	useEffect( () => setCursor( 0 ), [ query ] );

	// Close on a click outside.
	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const away = ( event ) => {
			if ( ! boxRef.current?.contains( event.target ) ) {
				setOpen( false );
			}
		};
		document.addEventListener( 'mousedown', away );
		return () => document.removeEventListener( 'mousedown', away );
	}, [ open ] );

	const pick = ( item ) => {
		setOpen( false );
		setQuery( '' );
		onPick( item );
	};

	const onKeyDown = ( event ) => {
		if ( 'ArrowDown' === event.key ) {
			event.preventDefault();
			setOpen( true );
			setCursor( ( at ) => Math.min( at + 1, results.length - 1 ) );
		} else if ( 'ArrowUp' === event.key ) {
			event.preventDefault();
			setCursor( ( at ) => Math.max( at - 1, 0 ) );
		} else if ( 'Enter' === event.key ) {
			event.preventDefault();
			if ( results[ cursor ] ) {
				pick( results[ cursor ] );
			}
		} else if ( 'Escape' === event.key ) {
			if ( query ) {
				event.stopPropagation();
				setQuery( '' );
			}
			setOpen( false );
		}
	};

	const showList = open && '' !== query.trim();

	return (
		<div ref={ boxRef } className="relative">
			<Search
				className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
				aria-hidden="true"
			/>
			<Input
				type="search"
				value={ query }
				onChange={ ( event ) => {
					setQuery( event.target.value );
					setOpen( true );
				} }
				onFocus={ () => {
					setIndexing( true );
					setOpen( true );
				} }
				onKeyDown={ onKeyDown }
				placeholder={ __( 'Search settings…', 'radius-hotel-booking' ) }
				aria-label={ __( 'Search settings', 'radius-hotel-booking' ) }
				role="combobox"
				aria-expanded={ showList }
				aria-controls={ listId }
				aria-autocomplete="list"
				aria-activedescendant={
					showList && results[ cursor ]
						? `${ listId }-${ cursor }`
						: undefined
				}
				autoComplete="off"
				className="h-9 min-h-0 rounded-lg border-transparent bg-muted pl-9 pr-8 text-sm shadow-none focus:border-primary focus:bg-card [&::-webkit-search-cancel-button]:hidden"
			/>
			{ query ? (
				<button
					type="button"
					onClick={ () => setQuery( '' ) }
					className="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 cursor-pointer items-center justify-center rounded border-0 bg-transparent p-0 text-muted-foreground hover:text-heading"
					aria-label={ __( 'Clear search', 'radius-hotel-booking' ) }
				>
					<X className="h-4 w-4" aria-hidden="true" />
				</button>
			) : null }

			{ showList ? (
				<ul
					id={ listId }
					role="listbox"
					aria-label={ __(
						'Matching settings',
						'radius-hotel-booking'
					) }
					className="absolute left-0 right-0 top-full z-30 m-0 mt-1 max-h-[50vh] list-none overflow-y-auto rounded-lg border border-border bg-card p-1 shadow-lg md:w-[26rem]"
				>
					{ results.length ? (
						results.map( ( item, index ) => (
							<li
								key={ `${ item.tab }|${ item.section }|${ item.label }|${ index }` }
								id={ `${ listId }-${ index }` }
								role="option"
								aria-selected={ index === cursor }
								onMouseDown={ ( event ) =>
									event.preventDefault()
								}
								onMouseEnter={ () => setCursor( index ) }
								onClick={ () => pick( item ) }
								className={ cn(
									'm-0 cursor-pointer rounded-md px-3 py-2',
									index === cursor
										? 'bg-muted'
										: 'bg-transparent'
								) }
							>
								<span className="block text-sm font-medium text-heading">
									<Marked
										text={ item.label }
										query={ query }
									/>
									{ item.locked
										? ` (${ __(
												'Pro',
												'radius-hotel-booking'
										  ) })`
										: '' }
								</span>
								<span className="block truncate text-xs text-muted-foreground">
									{ item.isTab
										? __(
												'Settings tab',
												'radius-hotel-booking'
										  )
										: item.section
										? sprintf(
												/* translators: 1: settings tab, 2: card heading on that tab. */
												__(
													'%1$s › %2$s',
													'radius-hotel-booking'
												),
												item.tabLabel,
												item.section
										  )
										: item.tabLabel }
								</span>
							</li>
						) )
					) : (
						<li className="m-0 px-3 py-2 text-sm text-muted-foreground">
							{ __(
								'No setting matches.',
								'radius-hotel-booking'
							) }
						</li>
					) }
				</ul>
			) : null }

			{ indexing ? (
				<Harvester
					tabs={ hiddenTabs }
					propsFor={ propsFor }
					onItems={ setHiddenItems }
				/>
			) : null }
		</div>
	);
}
