/**
 * One guest (`#/guests/:id`, features 9.3, 9.5–9.7): a header card with the
 * name, reference and standing; contact details and the identity document
 * (masked, *Show number* with `guests.view_id`); *Edit* with `guests.edit`.
 * The right column hosts the panels: Stays, Notes, and those add-ons register
 * on `rtbp.guest.panels` (Pro: History).
 */
import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	ArrowLeft,
	Ban,
	Mail,
	Pencil,
	Phone,
	Undo2,
	Users,
} from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import EmptyState from '@/components/common/EmptyState';
import NotesPanel from '@/components/common/NotesPanel';
import Panel from '@/components/common/Panel';
import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAccess } from '@/lib/access';
import { formatDate, formatDateTime } from '@/lib/format';
import { toast } from '@/lib/toast';
import { useGuest, useSetStanding } from './api';
import EditGuestDialog from './components/EditGuestDialog';
import GuestStays from './components/GuestStays';
import IdDocument from './components/IdDocument';

/**
 * @return {JSX.Element} Screen.
 */
export default function GuestDetail() {
	const { id: param } = useParams();
	const id = Number( param ) || 0;
	const { data, isPending, error, refetch } = useGuest( id );
	const canEdit = useAccess( 'guests.edit' ) !== 'locked';
	const canBan = useAccess( 'guests.ban' ) !== 'locked';
	const [ editing, setEditing ] = useState( false );
	// 'ban' | 'unban' while the reason dialog is open.
	const [ standingAction, setStandingAction ] = useState( null );
	const setStanding = useSetStanding( id );
	const [ tab, setTab ] = useState( 'stays' );
	const guestForPanels = data?.guest;

	// The right-hand tabs: Stays here, Notes (T4), and whatever add-ons
	// register on `rtbp.guest.panels` (Pro: History), sorted by `order`.
	const panels = useMemo( () => {
		if ( ! guestForPanels ) {
			return [];
		}
		const base = [
			{
				key: 'stays',
				label: __( 'Stays', 'radius-hotel-booking' ),
				order: 10,
				render: () => <GuestStays guestId={ guestForPanels.id } />,
			},
			{
				key: 'notes',
				label: __( 'Notes', 'radius-hotel-booking' ),
				order: 20,
				render: () => (
					<NotesPanel type="guest" id={ guestForPanels.id } />
				),
			},
		];
		const filtered = applyFilters( 'rtbp.guest.panels', base, {
			guest: guestForPanels,
		} );
		return ( Array.isArray( filtered ) ? filtered : base )
			.filter( ( panel ) => panel && panel.key && panel.render )
			.sort( ( a, b ) => ( a.order ?? 50 ) - ( b.order ?? 50 ) );
	}, [ guestForPanels ] );

	const back = (
		<Link
			to="/guests"
			className="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground no-underline hover:text-heading"
		>
			<ArrowLeft className="h-4 w-4" aria-hidden="true" />
			{ __( 'All guests', 'radius-hotel-booking' ) }
		</Link>
	);

	if ( isPending ) {
		return (
			<div className="mx-auto max-w-6xl space-y-4">
				{ back }
				<Skeleton className="h-28 w-full rounded-xl" />
				<Skeleton className="h-64 w-full rounded-xl" />
			</div>
		);
	}

	if ( error || ! data?.guest ) {
		return (
			<div className="mx-auto max-w-6xl space-y-4">
				{ back }
				<Panel>
					<EmptyState
						icon={ Users }
						title={
							error?.status === 404
								? __(
										'This guest does not exist',
										'radius-hotel-booking'
								  )
								: __(
										'The guest could not be loaded',
										'radius-hotel-booking'
								  )
						}
						description={
							error?.status === 404 ? '' : error?.message
						}
						action={
							error?.status === 404 ? null : (
								<Button
									variant="outline"
									onClick={ () => refetch() }
								>
									{ __(
										'Try again',
										'radius-hotel-booking'
									) }
								</Button>
							)
						}
						className="border-0"
					/>
				</Panel>
			</div>
		);
	}

	const { guest, idTypes } = data;
	const banned = guest.standing === 'banned';

	return (
		<div className="mx-auto max-w-6xl space-y-4">
			{ back }

			<Panel bodyClassName="space-y-3">
				<div className="flex flex-wrap items-start justify-between gap-3">
					<div className="min-w-0 space-y-1">
						<div className="flex flex-wrap items-center gap-2">
							<h2 className="m-0 text-xl font-semibold text-heading">
								{ guest.name }
							</h2>
							<StatusBadge
								domain="standing"
								value={ guest.standing }
							/>
						</div>
						<p className="m-0 text-sm text-muted-foreground">
							{ [
								guest.reference,
								sprintf(
									/* translators: %d: number of stays. */
									_n(
										'%d stay',
										'%d stays',
										guest.stays_count,
										'radius-hotel-booking'
									),
									guest.stays_count
								),
								guest.last_stay_at
									? sprintf(
											/* translators: %s: date. */
											__(
												'last stay %s',
												'radius-hotel-booking'
											),
											formatDate( guest.last_stay_at )
									  )
									: null,
							]
								.filter( Boolean )
								.join( ' · ' ) }
						</p>
					</div>
					<div className="flex flex-wrap gap-2">
						{ canEdit ? (
							<Button
								variant="outline"
								onClick={ () => setEditing( true ) }
							>
								<Pencil
									className="h-4 w-4"
									aria-hidden="true"
								/>
								{ __( 'Edit', 'radius-hotel-booking' ) }
							</Button>
						) : null }
						{ canBan ? (
							<Button
								variant="outline"
								className={ banned ? '' : 'text-destructive' }
								onClick={ () =>
									setStandingAction(
										banned ? 'unban' : 'ban'
									)
								}
							>
								{ banned ? (
									<Undo2
										className="h-4 w-4"
										aria-hidden="true"
									/>
								) : (
									<Ban
										className="h-4 w-4"
										aria-hidden="true"
									/>
								) }
								{ banned
									? __( 'Lift ban', 'radius-hotel-booking' )
									: __( 'Ban', 'radius-hotel-booking' ) }
							</Button>
						) : null }
					</div>
				</div>
				{ banned ? (
					<p
						role="status"
						className="m-0 rounded-lg bg-destructive-soft px-3 py-2 text-sm text-destructive"
					>
						{ sprintf(
							/* translators: 1: reason, 2: who, 3: when. */
							__(
								'Banned: %1$s — by %2$s on %3$s. This guest cannot be booked.',
								'radius-hotel-booking'
							),
							guest.ban_reason,
							guest.banned_by?.name ||
								__( 'someone', 'radius-hotel-booking' ),
							guest.banned_at
								? formatDateTime( guest.banned_at )
								: '—'
						) }
					</p>
				) : null }
			</Panel>

			<div className="grid gap-4 lg:grid-cols-3">
				<div className="space-y-4 lg:col-span-1">
					<Panel title={ __( 'Contact', 'radius-hotel-booking' ) }>
						<dl className="m-0 space-y-3">
							<div className="flex items-start gap-3">
								<Phone
									className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
									aria-hidden="true"
								/>
								<div className="min-w-0">
									<dt className="text-xs text-muted-foreground">
										{ __(
											'Phone',
											'radius-hotel-booking'
										) }
									</dt>
									<dd className="m-0 break-words text-sm text-heading">
										{ guest.phone ? (
											<a
												href={ `tel:${
													guest.phone_e164 ||
													guest.phone
												}` }
												className="text-heading no-underline hover:underline"
											>
												{ guest.phone }
											</a>
										) : (
											'—'
										) }
									</dd>
								</div>
							</div>
							<div className="flex items-start gap-3">
								<Mail
									className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
									aria-hidden="true"
								/>
								<div className="min-w-0">
									<dt className="text-xs text-muted-foreground">
										{ __(
											'E-mail',
											'radius-hotel-booking'
										) }
									</dt>
									<dd className="m-0 break-words text-sm text-heading">
										{ guest.email ? (
											<a
												href={ `mailto:${ guest.email }` }
												className="text-heading no-underline hover:underline"
											>
												{ guest.email }
											</a>
										) : (
											<span className="text-muted-foreground">
												{ __(
													'None given',
													'radius-hotel-booking'
												) }
											</span>
										) }
									</dd>
								</div>
							</div>
						</dl>
					</Panel>

					<Panel
						title={ __(
							'Identity document',
							'radius-hotel-booking'
						) }
					>
						<IdDocument guest={ guest } />
					</Panel>

					<p className="m-0 px-1 text-xs text-muted-foreground">
						{ guest.created_at
							? sprintf(
									/* translators: 1: date, 2: who. */
									__(
										'Added %1$s by %2$s',
										'radius-hotel-booking'
									),
									formatDateTime( guest.created_at ),
									guest.created_by?.name ||
										__(
											'the website',
											'radius-hotel-booking'
										)
							  )
							: null }
					</p>
				</div>

				<div className="lg:col-span-2">
					<Panel>
						{ panels.length > 1 ? (
							<Tabs
								value={
									panels.some( ( p ) => p.key === tab )
										? tab
										: panels[ 0 ].key
								}
								onValueChange={ setTab }
							>
								<TabsList className="h-auto flex-wrap justify-start">
									{ panels.map( ( panel ) => (
										<TabsTrigger
											key={ panel.key }
											value={ panel.key }
										>
											{ panel.label }
										</TabsTrigger>
									) ) }
								</TabsList>
								{ panels.map( ( panel ) => (
									<TabsContent
										key={ panel.key }
										value={ panel.key }
										className="pt-2"
									>
										{ panel.render() }
									</TabsContent>
								) ) }
							</Tabs>
						) : (
							<div className="space-y-2">
								<h3 className="m-0 text-base font-semibold text-heading">
									{ panels[ 0 ]?.label }
								</h3>
								{ panels[ 0 ]?.render() }
							</div>
						) }
					</Panel>
				</div>
			</div>

			<ConfirmDialog
				open={ Boolean( standingAction ) }
				onOpenChange={ ( open ) => ! open && setStandingAction( null ) }
				title={
					'ban' === standingAction
						? sprintf(
								/* translators: %s: guest name. */
								__( 'Ban %s?', 'radius-hotel-booking' ),
								guest.name
						  )
						: sprintf(
								/* translators: %s: guest name. */
								__(
									'Lift the ban on %s?',
									'radius-hotel-booking'
								),
								guest.name
						  )
				}
				description={
					'ban' === standingAction
						? __(
								'They cannot be booked until the ban is lifted. The reason is recorded and shown to staff.',
								'radius-hotel-booking'
						  )
						: __(
								'They can be booked again. The reason is recorded in the activity log.',
								'radius-hotel-booking'
						  )
				}
				confirmLabel={
					'ban' === standingAction
						? __( 'Ban guest', 'radius-hotel-booking' )
						: __( 'Lift ban', 'radius-hotel-booking' )
				}
				destructive={ 'ban' === standingAction }
				requireReason
				reasonLabel={ __( 'Reason', 'radius-hotel-booking' ) }
				onConfirm={ ( reason ) =>
					setStanding
						.mutateAsync( { action: standingAction, reason } )
						.then( () =>
							toast.success(
								'ban' === standingAction
									? __(
											'Guest banned.',
											'radius-hotel-booking'
									  )
									: __(
											'Ban lifted.',
											'radius-hotel-booking'
									  )
							)
						)
				}
			/>

			{ editing ? (
				<EditGuestDialog
					guest={ guest }
					idTypes={ idTypes }
					onClose={ () => setEditing( false ) }
				/>
			) : null }
		</div>
	);
}
