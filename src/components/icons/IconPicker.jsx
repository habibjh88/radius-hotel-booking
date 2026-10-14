/**
 * The icon library: every Lucide icon (lucide-react), laid out like
 * Elementor's — categories down the left, search across the top, the icons
 * in a grid, and the chosen one with Insert at the bottom. "Recently used"
 * remembers the last picks in this browser. Double-click inserts at once.
 *
 * Heavy (the whole icon set): load it lazily — IconField does.
 */
import { createElement, useEffect, useMemo, useRef, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { icons } from 'lucide-react';
import { History, LayoutGrid, Search, Trash2, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import meta from './lucide-meta.json';

// Icons rendered at a time; more as the grid scrolls.
const PAGE = 180;
const RECENT_KEY = 'rtbp-recent-icons';
const RECENT_MAX = 24;

/**
 * `air-vent` → `AirVent`, `arrow-down-0-1` → `ArrowDown01` (lucide-react's export names).
 *
 * @param {string} name Kebab-case name.
 * @return {string} PascalCase name.
 */
const pascal = ( name ) =>
	name
		.split( '-' )
		.map( ( part ) => part.charAt( 0 ).toUpperCase() + part.slice( 1 ) )
		.join( '' );

// Every icon lucide-react has, by its stored (kebab) name.
const ALL = Object.keys( meta.tags ).filter(
	( name ) => icons[ pascal( name ) ]
);

/**
 * "air-vent" → "air vent", for labels and search.
 *
 * @param {string} name Icon name.
 * @return {string} Words.
 */
const words = ( name ) => name.replace( /-/g, ' ' );

/**
 * The recently used icons (this browser).
 *
 * @return {string[]} Names.
 */
function readRecent() {
	try {
		const list = JSON.parse(
			window.localStorage.getItem( RECENT_KEY ) || '[]'
		);
		return Array.isArray( list )
			? list.filter( ( name ) => ALL.includes( name ) )
			: [];
	} catch ( e ) {
		return [];
	}
}

/**
 * Remember a pick.
 *
 * @param {string} name Icon name.
 */
function remember( name ) {
	try {
		const list = [ name, ...readRecent().filter( ( n ) => n !== name ) ];
		window.localStorage.setItem(
			RECENT_KEY,
			JSON.stringify( list.slice( 0, RECENT_MAX ) )
		);
	} catch ( e ) {
		// Private mode or storage blocked: no history, nothing else changes.
	}
}

/**
 * One icon drawn from lucide-react.
 *
 * @param {Object} props           Props.
 * @param {string} props.name      Icon name.
 * @param {string} props.className Classes.
 * @return {JSX.Element|null} Icon.
 */
function Glyph( { name, className } ) {
	const component = icons[ pascal( name ) ];
	return component
		? createElement( component, {
				className,
				strokeWidth: 1.75,
				'aria-hidden': true,
		  } )
		: null;
}

/**
 * @param {Object}   props              Props.
 * @param {boolean}  props.open         Open.
 * @param {Function} props.onOpenChange Open or close.
 * @param {string}   props.value        The current icon ('' = none).
 * @param {Function} props.onSelect     Called with the chosen name ('' = removed).
 * @param {string}   props.title        Dialog title.
 * @return {JSX.Element} Dialog.
 */
export default function IconPicker( {
	open,
	onOpenChange,
	value = '',
	onSelect,
	title,
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ category, setCategory ] = useState( 'all' );
	const [ picked, setPicked ] = useState( value );
	const [ limit, setLimit ] = useState( PAGE );
	const [ recent, setRecent ] = useState( readRecent );
	const gridRef = useRef( null );
	const moreRef = useRef( null );
	const searchRef = useRef( null );

	// Each opening starts from the current icon, all icons, no search.
	useEffect( () => {
		if ( open ) {
			setPicked( value );
			setQuery( '' );
			setCategory( readRecent().length ? 'recent' : 'all' );
			setRecent( readRecent() );
		}
	}, [ open, value ] );

	const categories = useMemo(
		() =>
			[ ...meta.categories ].sort( ( a, b ) =>
				a.title.localeCompare( b.title )
			),
		[]
	);

	const list = useMemo( () => {
		let names = ALL;
		if ( 'recent' === category ) {
			names = recent;
		} else if ( 'all' !== category ) {
			names =
				meta.categories
					.find( ( c ) => c.key === category )
					?.icons.filter( ( name ) => ALL.includes( name ) ) || [];
		}
		const terms = query
			.trim()
			.toLowerCase()
			.split( /\s+/ )
			.filter( Boolean );
		if ( ! terms.length ) {
			return names;
		}
		// Search all icons, whatever the category: names first, then tags.
		const pool = 'recent' === category ? names : ALL;
		const hay = ( name ) =>
			`${ words( name ) } ${ meta.tags[ name ] || '' }`;
		const inName = pool.filter( ( name ) =>
			terms.every( ( t ) => words( name ).includes( t ) )
		);
		const inTags = pool.filter(
			( name ) =>
				! inName.includes( name ) &&
				terms.every( ( t ) => hay( name ).includes( t ) )
		);
		return [ ...inName, ...inTags ];
	}, [ category, query, recent ] );

	// Back to the top, first page, whenever the list changes.
	useEffect( () => {
		setLimit( PAGE );
		if ( gridRef.current ) {
			gridRef.current.scrollTop = 0;
		}
	}, [ category, query ] );

	// Render more as the end of the grid comes into view.
	useEffect( () => {
		const node = moreRef.current;
		if ( ! node || ! open ) {
			return undefined;
		}
		const observer = new window.IntersectionObserver(
			( entries ) => {
				if ( entries[ 0 ]?.isIntersecting ) {
					setLimit( ( l ) => l + PAGE );
				}
			},
			{ root: gridRef.current, rootMargin: '400px' }
		);
		observer.observe( node );
		return () => observer.disconnect();
	}, [ open, list, limit ] );

	const insert = ( name ) => {
		if ( name ) {
			remember( name );
		}
		onSelect( name );
	};

	const searching = query.trim() !== '';
	const sideItem = ( key, label, count, Icon ) => (
		<li key={ key }>
			<button
				type="button"
				onClick={ () => {
					setCategory( key );
					setQuery( '' );
				} }
				aria-current={
					category === key && ! searching ? 'true' : undefined
				}
				className={ cn(
					'flex w-full items-center gap-2 rounded-md border-0 px-3 py-2 text-left text-sm font-normal normal-case tracking-normal shadow-none transition-colors',
					category === key && ! searching
						? 'bg-primary-soft font-semibold text-primary hover:bg-primary-soft hover:text-primary focus:bg-primary-soft focus:text-primary'
						: 'bg-transparent text-foreground hover:bg-muted hover:text-heading focus:bg-muted focus:text-heading'
				) }
			>
				{ Icon ? (
					<Icon className="h-4 w-4 shrink-0" aria-hidden="true" />
				) : null }
				<span className="min-w-0 flex-1 truncate">{ label }</span>
				<span className="text-xs tabular-nums text-muted-foreground">
					{ count }
				</span>
			</button>
		</li>
	);

	return (
		<Dialog open={ open } onOpenChange={ onOpenChange }>
			<DialogContent
				className="flex h-[min(88vh,760px)] w-[calc(100vw-2rem)] max-w-5xl flex-col gap-0 overflow-hidden p-0"
				// Straight into the search box, like Elementor's library.
				onOpenAutoFocus={ ( event ) => {
					event.preventDefault();
					searchRef.current?.focus();
				} }
			>
				{ /* Header */ }
				<div className="flex items-center gap-3 border-b border-border px-5 py-3.5 pr-12">
					<span className="flex h-8 w-8 items-center justify-center rounded-md bg-primary-soft text-primary">
						<LayoutGrid className="h-4 w-4" aria-hidden="true" />
					</span>
					<div className="min-w-0">
						<DialogTitle className="m-0 text-base font-bold text-heading">
							{ __( 'Icon library', 'radius-hotel-booking' ) }
						</DialogTitle>
						<DialogDescription className="m-0 truncate text-xs text-muted-foreground">
							{ title ||
								sprintf(
									/* translators: %d: number of icons. */
									__(
										'%d Lucide icons',
										'radius-hotel-booking'
									),
									ALL.length
								) }
						</DialogDescription>
					</div>
				</div>

				<div className="flex min-h-0 flex-1 flex-col md:flex-row">
					{ /* Categories: a column on wide screens, a row of chips on phones. */ }
					<nav
						className="hidden w-60 shrink-0 overflow-y-auto border-r border-border bg-muted/30 p-2 md:block"
						aria-label={ __(
							'Icon categories',
							'radius-hotel-booking'
						) }
					>
						<ul className="m-0 list-none space-y-0.5 p-0">
							{ recent.length
								? sideItem(
										'recent',
										__(
											'Recently used',
											'radius-hotel-booking'
										),
										recent.length,
										History
								  )
								: null }
							{ sideItem(
								'all',
								__( 'All icons', 'radius-hotel-booking' ),
								ALL.length,
								LayoutGrid
							) }
							<li className="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
								{ __( 'Categories', 'radius-hotel-booking' ) }
							</li>
							{ categories.map( ( c ) =>
								sideItem( c.key, c.title, c.icons.length )
							) }
						</ul>
					</nav>
					<div className="flex gap-1.5 overflow-x-auto border-b border-border px-3 py-2 md:hidden">
						{ [
							...( recent.length
								? [
										{
											key: 'recent',
											title: __(
												'Recent',
												'radius-hotel-booking'
											),
										},
								  ]
								: [] ),
							{
								key: 'all',
								title: __( 'All', 'radius-hotel-booking' ),
							},
							...categories,
						].map( ( c ) => (
							<button
								key={ c.key }
								type="button"
								onClick={ () => {
									setCategory( c.key );
									setQuery( '' );
								} }
								className={ cn(
									'shrink-0 whitespace-nowrap rounded-full border px-3 py-1 text-xs font-medium normal-case shadow-none',
									category === c.key && ! searching
										? 'border-primary bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground'
										: 'border-border bg-white text-foreground hover:bg-muted hover:text-foreground focus:bg-muted focus:text-foreground'
								) }
							>
								{ c.title }
							</button>
						) ) }
					</div>

					{ /* Search + grid */ }
					<div className="flex min-h-0 min-w-0 flex-1 flex-col">
						<div className="flex items-center gap-3 border-b border-border px-4 py-3">
							<div className="relative min-w-0 flex-1">
								<Search
									className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
									aria-hidden="true"
								/>
								<input
									ref={ searchRef }
									type="text"
									enterKeyHint="search"
									value={ query }
									onChange={ ( e ) =>
										setQuery( e.target.value )
									}
									placeholder={ __(
										'Search icons, e.g. wifi, bed, coffee',
										'radius-hotel-booking'
									) }
									aria-label={ __(
										'Search icons',
										'radius-hotel-booking'
									) }
									className="h-10 w-full rounded-md border border-border bg-white pl-9 pr-9 text-sm text-foreground shadow-none outline-none focus:border-primary focus:ring-2 focus:ring-ring"
								/>
								{ query ? (
									<button
										type="button"
										onClick={ () => {
											setQuery( '' );
											searchRef.current?.focus();
										} }
										aria-label={ __(
											'Clear search',
											'radius-hotel-booking'
										) }
										className="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded border-0 bg-transparent p-0 text-muted-foreground hover:bg-muted hover:text-heading focus:bg-muted focus:text-heading"
									>
										<X
											className="h-3.5 w-3.5"
											aria-hidden="true"
										/>
									</button>
								) : null }
							</div>
							<span
								className="hidden shrink-0 text-xs tabular-nums text-muted-foreground sm:block"
								aria-live="polite"
							>
								{ sprintf(
									/* translators: %d: number of icons. */
									_n(
										'%d icon',
										'%d icons',
										list.length,
										'radius-hotel-booking'
									),
									list.length
								) }
							</span>
						</div>

						<div
							ref={ gridRef }
							className="min-h-0 flex-1 overflow-y-auto p-4"
						>
							{ list.length ? (
								<ul
									className="m-0 grid list-none grid-cols-[repeat(auto-fill,minmax(6.25rem,1fr))] gap-2 p-0"
									role="listbox"
									aria-label={ __(
										'Icons',
										'radius-hotel-booking'
									) }
								>
									{ list.slice( 0, limit ).map( ( name ) => {
										const selected = picked === name;
										return (
											<li
												key={ name }
												role="presentation"
											>
												<button
													type="button"
													role="option"
													aria-selected={ selected }
													title={ words( name ) }
													onClick={ () =>
														setPicked( name )
													}
													onDoubleClick={ () =>
														insert( name )
													}
													className={ cn(
														'group flex h-24 w-full flex-col items-center justify-center gap-2 rounded-lg border px-1.5 py-2 text-center font-normal normal-case tracking-normal shadow-none transition-all',
														selected
															? 'border-primary bg-primary-softer text-primary ring-2 ring-primary hover:bg-primary-softer hover:text-primary focus:bg-primary-softer focus:text-primary'
															: 'border-border bg-white text-heading hover:-translate-y-0.5 hover:border-primary hover:bg-white hover:text-primary hover:shadow-md focus:border-primary focus:bg-white focus:text-primary'
													) }
												>
													<Glyph
														name={ name }
														className="h-7 w-7 shrink-0"
													/>
													<span className="line-clamp-2 w-full break-words text-[11px] leading-tight text-muted-foreground group-hover:text-heading">
														{ words( name ) }
													</span>
												</button>
											</li>
										);
									} ) }
								</ul>
							) : (
								<div className="flex h-full flex-col items-center justify-center gap-2 py-12 text-center">
									<Search
										className="h-8 w-8 text-muted-foreground"
										aria-hidden="true"
									/>
									<p className="m-0 font-semibold text-heading">
										{ __(
											'No icon matches',
											'radius-hotel-booking'
										) }
									</p>
									<p className="m-0 text-sm text-muted-foreground">
										{ __(
											'Try another word, such as bed, pool or car.',
											'radius-hotel-booking'
										) }
									</p>
								</div>
							) }
							{ limit < list.length ? (
								<div ref={ moreRef } className="h-8" />
							) : null }
						</div>
					</div>
				</div>

				{ /* Footer */ }
				<div className="flex flex-wrap items-center gap-3 border-t border-border bg-muted/30 px-5 py-3">
					<div className="flex min-w-0 flex-1 items-center gap-3">
						{ picked ? (
							<>
								<span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-border bg-white text-primary">
									<Glyph
										name={ picked }
										className="h-6 w-6"
									/>
								</span>
								<span className="min-w-0">
									<span className="block truncate text-sm font-semibold text-heading">
										{ words( picked ) }
									</span>
									<span className="block text-xs text-muted-foreground">
										{ __(
											'Double-click an icon to insert it at once.',
											'radius-hotel-booking'
										) }
									</span>
								</span>
							</>
						) : (
							<span className="text-sm text-muted-foreground">
								{ __(
									'Pick an icon.',
									'radius-hotel-booking'
								) }
							</span>
						) }
					</div>
					<div className="flex flex-wrap items-center gap-2">
						{ value ? (
							<Button
								type="button"
								variant="ghost"
								className="text-destructive hover:text-destructive"
								onClick={ () => insert( '' ) }
							>
								<Trash2
									className="h-4 w-4"
									aria-hidden="true"
								/>
								{ __( 'Remove icon', 'radius-hotel-booking' ) }
							</Button>
						) : null }
						<Button
							type="button"
							variant="outline"
							onClick={ () => onOpenChange( false ) }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						<Button
							type="button"
							disabled={ ! picked }
							onClick={ () => insert( picked ) }
						>
							{ __( 'Insert', 'radius-hotel-booking' ) }
						</Button>
					</div>
				</div>
			</DialogContent>
		</Dialog>
	);
}
