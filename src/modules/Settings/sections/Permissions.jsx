/**
 * Settings → Permissions (M13): open or locked, per built-in role, for every
 * page and action. Stored as `access.roleLevels` (role => key => level); a
 * key a role does not set follows the role's default, then the key's.
 *
 * The keys, groups and roles come from `GET access/registry`, so add-on keys
 * appear here without changes.
 */
import { useQuery } from '@tanstack/react-query';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';

import SegmentedControl from '@/components/common/SegmentedControl';
import SettingsSection from '@/components/common/SettingsSection';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { get } from '@/api/client';

const fetchRegistry = () =>
	get( 'access/registry' ).then( ( { data } ) => data );

/**
 * The levels offered in the matrix: open and locked, or more from an add-on
 * (`rtbp.access.levels`; Pro inserts Passcode, and allows it server-side with
 * the `rtbp_access_role_levels` filter).
 *
 * @return {Array<{value: string, label: string}>} Options.
 */
const levelOptions = () => {
	const free = [
		{ value: 'open', label: __( 'Open', 'radius-hotel-booking' ) },
		{ value: 'locked', label: __( 'Locked', 'radius-hotel-booking' ) },
	];
	const filtered = applyFilters( 'rtbp.access.levels', free );
	return Array.isArray( filtered ) && filtered.length ? filtered : free;
};

/**
 * @return {JSX.Element} Placeholder.
 */
function MatrixSkeleton() {
	return (
		<div className="space-y-4" role="status" aria-live="polite">
			{ [ 0, 1, 2 ].map( ( i ) => (
				<Skeleton key={ i } className="h-48 w-full rounded-xl" />
			) ) }
		</div>
	);
}

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values (`roleLevels`).
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @return {JSX.Element} Tab.
 */
export default function Permissions( { value, setField, errors } ) {
	const registry = useQuery( {
		queryKey: [ 'access', 'registry' ],
		queryFn: fetchRegistry,
		staleTime: 5 * 60 * 1000,
	} );

	if ( registry.isPending ) {
		return <MatrixSkeleton />;
	}

	if ( registry.error ) {
		return (
			<div
				role="alert"
				className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-destructive-soft p-4 text-sm text-destructive"
			>
				<span>{ registry.error.message }</span>
				<Button
					variant="outline"
					size="sm"
					onClick={ () => registry.refetch() }
				>
					{ __( 'Try again', 'radius-hotel-booking' ) }
				</Button>
			</div>
		);
	}

	const { groups, keys, roles } = registry.data;
	const stored = value.roleLevels || {};
	const options = levelOptions();

	// What applies when the role does not set the key itself.
	const fallback = ( role, key ) => role.defaults?.[ key.key ] ?? key.default;
	const levelOf = ( role, key ) =>
		stored[ role.slug ]?.[ key.key ] ?? fallback( role, key );

	// Store only what differs from the fallback, so defaults stay defaults.
	const change = ( role, key, level ) => {
		const next = { ...stored, [ role.slug ]: { ...stored[ role.slug ] } };
		if ( level === fallback( role, key ) ) {
			delete next[ role.slug ][ key.key ];
		} else {
			next[ role.slug ][ key.key ] = level;
		}
		setField( 'roleLevels' )( next );
	};

	return (
		<div className="space-y-6">
			<SettingsSection
				title={ __( 'How permissions work', 'radius-hotel-booking' ) }
				description={ __(
					'Open: staff with this role may use it. Locked: it is hidden and the server refuses it. Administrators are never locked out.',
					'radius-hotel-booking'
				) }
			>
				{ errors.roleLevels ? (
					<p role="alert" className="m-0 text-sm text-destructive">
						{ errors.roleLevels }
					</p>
				) : (
					<p className="m-0 text-sm text-muted-foreground">
						{ __(
							'Changes apply to everyone with the role after you save.',
							'radius-hotel-booking'
						) }
					</p>
				) }
			</SettingsSection>

			{ groups.map( ( group ) => {
				const rows = keys.filter( ( key ) => key.group === group.key );
				if ( ! rows.length ) {
					return null;
				}
				return (
					<SettingsSection key={ group.key } title={ group.label }>
						<div>
							<div className="hidden items-center gap-4 border-b border-border pb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground xl:flex">
								<span className="min-w-0 flex-1">
									{ __(
										'Page or action',
										'radius-hotel-booking'
									) }
								</span>
								{ roles.map( ( role ) => (
									<span
										key={ role.slug }
										className="w-[232px] shrink-0"
									>
										{ role.name }
									</span>
								) ) }
							</div>
							<ul className="m-0 list-none divide-y divide-border p-0">
								{ rows.map( ( key ) => (
									<li
										key={ key.key }
										className="flex flex-col gap-2 py-3 xl:flex-row xl:items-center xl:gap-4"
									>
										<span className="min-w-0 flex-1 text-sm text-heading">
											{ key.label }
										</span>
										{ roles.map( ( role ) => (
											<div
												key={ role.slug }
												className="flex items-center justify-between gap-3 xl:w-[232px] xl:shrink-0"
											>
												<span className="text-xs text-muted-foreground xl:hidden">
													{ role.name }
												</span>
												<SegmentedControl
													options={ options }
													value={ levelOf( role, key ) }
													onChange={ ( level ) =>
														change( role, key, level )
													}
													label={ sprintf(
														/* translators: 1: a page or action, 2: a staff role. */
														__(
															'%1$s for %2$s',
															'radius-hotel-booking'
														),
														key.label,
														role.name
													) }
												/>
											</div>
										) ) }
									</li>
								) ) }
							</ul>
						</div>
					</SettingsSection>
				);
			} ) }
		</div>
	);
}
