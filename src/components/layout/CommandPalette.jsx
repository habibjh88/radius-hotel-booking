/**
 * Command palette (⌘K / Ctrl+K): jump to any screen by typing. Lazy-loaded by
 * AppShell on first open; the shortcut itself lives in ./shortcuts.js.
 *
 * Navigation only for now — every route the user can see, from the same
 * route table as the sidebar. Searching records (bookings by reference,
 * guests by name or phone, rooms by number) plugs in here with M01/M09.
 */
import { useNavigate } from 'react-router-dom';
import { __ } from '@wordpress/i18n';

import {
	CommandDialog,
	CommandEmpty,
	CommandGroup,
	CommandInput,
	CommandItem,
	CommandList,
} from '@/components/ui/command';
import { DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { NAV_GROUPS, canSee, getRoutes } from '@/admin/routes';
import { useAccessMap } from '@/lib/access';

/**
 * Predictable matching for a short list of screens: label prefix, word
 * prefix, label substring, then description substring. No loose letter
 * matching — cmdk keeps groups in their order, so a stray match in an early
 * group would sit above the real hit (cmdk's own fuzzy score did exactly that).
 *
 * @param {string}   value    Item value (label + path).
 * @param {string}   search   Query.
 * @param {string[]} keywords Item keywords (the description).
 * @return {number} Score 0–1 (0 hides the item).
 */
function rank( value, search, keywords = [] ) {
	const query = search.trim().toLowerCase();
	if ( ! query ) {
		return 1;
	}
	const label = value.toLowerCase();
	const words = label.split( /\s+/ );
	if ( label.startsWith( query ) ) {
		return 1;
	}
	if ( words.some( ( word ) => word.startsWith( query ) ) ) {
		return 0.9;
	}
	if ( label.includes( query ) ) {
		return 0.8;
	}
	return keywords.join( ' ' ).toLowerCase().includes( query ) ? 0.5 : 0;
}

/**
 * @param {Object}   props              Props.
 * @param {boolean}  props.open         Open state.
 * @param {Function} props.onOpenChange Open-state setter.
 * @return {JSX.Element} Palette.
 */
export default function CommandPalette( { open, onOpenChange } ) {
	const navigate = useNavigate();
	// Re-render when the access map changes (canSee reads it).
	useAccessMap();

	const routes = getRoutes().filter( ( route ) => route.group && ! route.hidden && canSee( route ) );
	const groups = NAV_GROUPS.map( ( group ) => ( {
		...group,
		routes: routes.filter( ( route ) => route.group === group.key ),
	} ) ).filter( ( group ) => group.routes.length );

	const go = ( path ) => {
		onOpenChange( false );
		navigate( path );
	};

	return (
		<CommandDialog open={ open } onOpenChange={ onOpenChange } commandProps={ { filter: rank } }>
			<DialogTitle className="sr-only">
				{ __( 'Go to…', 'radius-hotel-booking' ) }
			</DialogTitle>
			<DialogDescription className="sr-only">
				{ __( 'Type the name of a screen and press Enter.', 'radius-hotel-booking' ) }
			</DialogDescription>
			<CommandInput placeholder={ __( 'Go to a screen…', 'radius-hotel-booking' ) } />
			<CommandList>
				<CommandEmpty>{ __( 'No screen matches.', 'radius-hotel-booking' ) }</CommandEmpty>
				{ groups.map( ( group ) => (
					<CommandGroup key={ group.key } heading={ group.label }>
						{ group.routes.map( ( route ) => {
							const Icon = route.icon;
							return (
								<CommandItem
									key={ route.path }
									// Label first; the description only helps matching.
									value={ `${ route.label } ${ route.path }` }
									keywords={ route.description ? [ route.description ] : [] }
									onSelect={ () => go( route.path ) }
									className="cursor-pointer gap-3"
								>
									{ Icon ? <Icon className="text-muted-foreground" aria-hidden="true" /> : null }
									<span className="flex min-w-0 flex-col">
										<span className="text-sm font-medium text-heading">{ route.label }</span>
										{ route.description ? (
											<span className="truncate text-xs text-muted-foreground">{ route.description }</span>
										) : null }
									</span>
								</CommandItem>
							);
						} ) }
					</CommandGroup>
				) ) }
			</CommandList>
			<p className="m-0 border-t border-border px-4 py-2.5 text-xs text-muted-foreground">
				{ __( 'Searching bookings, guests and rooms by name, phone or number comes with the bookings module.', 'radius-hotel-booking' ) }
			</p>
		</CommandDialog>
	);
}
