/**
 * Guest list (`#/guests`, features 9.1, 9.2, 9.11): every guest with
 * reference, name, phone, e-mail, standing, stays and last stay. Search by
 * name (accents and case ignored), phone (any form: `07 07 12 34 56`,
 * `+225…`, the old 8 digits), e-mail or reference; filter the banned ones.
 * The search and tab live in the URL, so a reload keeps them.
 */
import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Plus, Users } from 'lucide-react';

import DataTable from '@/components/common/DataTable';
import EmptyState from '@/components/common/EmptyState';
import FilterTabs from '@/components/common/FilterTabs';
import Panel from '@/components/common/Panel';
import StatusBadge from '@/components/common/StatusBadge';
import { usePageActions } from '@/components/layout/PageActions';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/lib/access';
import { formatDate } from '@/lib/format';
import { useGuests } from './api';
import AddGuestDialog from './components/AddGuestDialog';

const PER_PAGE = 20;

export default function Guests() {
	const [ params, setParams ] = useSearchParams();
	const canCreate = useAccess( 'guests.create' ) !== 'locked';
	const [ adding, setAdding ] = useState( false );
	const navigate = useNavigate();

	const q = params.get( 'q' ) || '';
	const standing = params.get( 'standing' ) === 'banned' ? 'banned' : '';
	const page = Math.max( 1, Number( params.get( 'page' ) ) || 1 );
	const list = useGuests( { q, standing, page, per_page: PER_PAGE } );

	const setView = ( changes ) =>
		setParams(
			( prev ) => {
				const next = new URLSearchParams( prev );
				Object.entries( changes ).forEach( ( [ key, value ] ) =>
					value
						? next.set( key, String( value ) )
						: next.delete( key )
				);
				return next;
			},
			{ replace: true }
		);

	usePageActions(
		canCreate ? (
			<Button onClick={ () => setAdding( true ) }>
				<Plus className="h-4 w-4" aria-hidden="true" />
				<span className="sr-only sm:not-sr-only">
					{ __( 'Add guest', 'radius-hotel-booking' ) }
				</span>
			</Button>
		) : null,
		[ canCreate ]
	);

	const columns = [
		{
			id: 'guest',
			header: __( 'Guest', 'radius-hotel-booking' ),
			mobile: 'title',
			cell: ( guest ) => (
				<span className="inline-flex flex-wrap items-baseline gap-x-2">
					<span className="font-semibold text-heading">
						{ guest.name }
					</span>
					<span className="text-xs text-muted-foreground">
						{ guest.reference }
					</span>
				</span>
			),
		},
		{
			id: 'phone',
			header: __( 'Phone', 'radius-hotel-booking' ),
			mobile: 'subtitle',
			cell: ( guest ) => guest.phone || '—',
		},
		{
			id: 'email',
			header: __( 'E-mail', 'radius-hotel-booking' ),
			cell: ( guest ) =>
				guest.email || (
					<span className="text-muted-foreground">
						{ __( 'No e-mail', 'radius-hotel-booking' ) }
					</span>
				),
		},
		{
			id: 'standing',
			header: __( 'Standing', 'radius-hotel-booking' ),
			cell: ( guest ) => (
				<StatusBadge domain="standing" value={ guest.standing } />
			),
		},
		{
			id: 'stays',
			header: __( 'Stays', 'radius-hotel-booking' ),
			align: 'right',
			cell: ( guest ) => guest.stays_count,
		},
		{
			id: 'last_stay',
			header: __( 'Last stay', 'radius-hotel-booking' ),
			cell: ( guest ) =>
				guest.last_stay_at ? formatDate( guest.last_stay_at ) : '—',
		},
	];

	const empty = q ? (
		<EmptyState
			icon={ Users }
			title={ __( 'No guest matches', 'radius-hotel-booking' ) }
			description={ sprintf(
				/* translators: %s: what was searched. */
				__(
					'Nothing found for “%s”. Try part of the name, the phone number or the e-mail.',
					'radius-hotel-booking'
				),
				q
			) }
			className="border-0 py-12"
		/>
	) : (
		<EmptyState
			icon={ Users }
			title={
				standing
					? __( 'No banned guests', 'radius-hotel-booking' )
					: __( 'No guests yet', 'radius-hotel-booking' )
			}
			description={ __(
				'Guests are added when they book, or here by hand.',
				'radius-hotel-booking'
			) }
			action={
				canCreate && ! standing ? (
					<Button onClick={ () => setAdding( true ) }>
						<Plus className="h-4 w-4" aria-hidden="true" />
						{ __( 'Add guest', 'radius-hotel-booking' ) }
					</Button>
				) : null
			}
			className="border-0 py-12"
		/>
	);

	return (
		<div className="mx-auto max-w-6xl space-y-4">
			<Panel
				title={ __( 'Guests', 'radius-hotel-booking' ) }
				description={
					list.data
						? sprintf(
								/* translators: %d: number of guests. */
								_n(
									'%d guest',
									'%d guests',
									list.data.total,
									'radius-hotel-booking'
								),
								list.data.total
						  )
						: null
				}
			>
				<div className="space-y-3">
					<FilterTabs
						label={ __( 'Show', 'radius-hotel-booking' ) }
						value={ standing || 'all' }
						onChange={ ( next ) =>
							setView( {
								standing: next === 'banned' ? 'banned' : '',
								page: '',
							} )
						}
						tabs={ [
							{
								value: 'all',
								label: __(
									'All guests',
									'radius-hotel-booking'
								),
							},
							{
								value: 'banned',
								label: __( 'Banned', 'radius-hotel-booking' ),
							},
						] }
					/>
					<DataTable
						columns={ columns }
						rows={ list.data?.guests || [] }
						total={ list.data?.total || 0 }
						page={ page }
						perPage={ PER_PAGE }
						onPageChange={ ( next ) => setView( { page: next } ) }
						loading={ list.isPending }
						error={ list.error }
						onRetry={ () => list.refetch() }
						search={ {
							value: q,
							onChange: ( value ) =>
								setView( { q: value, page: '' } ),
							placeholder: __(
								'Name, phone or e-mail',
								'radius-hotel-booking'
							),
						} }
						empty={ empty }
						onRowClick={ ( guest ) =>
							navigate( `/guests/${ guest.id }` )
						}
						caption={ __( 'Guests', 'radius-hotel-booking' ) }
					/>
				</div>
			</Panel>

			{ adding ? (
				<AddGuestDialog
					idTypes={ list.data?.idTypes || {} }
					onClose={ () => setAdding( false ) }
					onExisting={ ( guest ) =>
						navigate( `/guests/${ guest.id }` )
					}
					onCreated={ ( guest ) =>
						navigate( `/guests/${ guest.id }` )
					}
				/>
			) : null }
		</div>
	);
}
