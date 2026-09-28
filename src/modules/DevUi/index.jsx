/**
 * Developer UI kit: every shared component, in every state, on one screen
 * (`#/dev/ui`). Visible only with WP_DEBUG on to administrators (LoadAssets
 * `dev_ui` flag); lazy-loaded, so it costs nothing otherwise.
 *
 * Use it to check a change to a shared component in all its states, in both
 * languages and at phone width, before a module relies on it. Not translated
 * on purpose: sample content only.
 */
import { useState } from 'react';
import { z } from 'zod';
import {
	BedDouble,
	CheckCircle2,
	Hourglass,
	Inbox,
	LogIn,
	Plus,
} from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import DataTable from '@/components/common/DataTable';
import DateRangePicker from '@/components/common/DateRangePicker';
import DateTime from '@/components/common/DateTime';
import EmptyState from '@/components/common/EmptyState';
import FilterTabs from '@/components/common/FilterTabs';
import { Field, FormSection } from '@/components/common/Form';
import MediaField from '@/components/common/MediaField';
import Money from '@/components/common/Money';
import Panel from '@/components/common/Panel';
import SegmentedControl from '@/components/common/SegmentedControl';
import StatCard from '@/components/common/StatCard';
import StatusBadge from '@/components/common/StatusBadge';
import { usePageActions } from '@/components/layout/PageActions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { applyServerErrors, useZodForm } from '@/lib/forms';
import { STATUS } from '@/lib/status';
import { toast, toastError } from '@/lib/toast';

const SAMPLE_ROWS = Array.from( { length: 23 }, ( _, i ) => ( {
	id: i + 1,
	reference: `RT-2026-${ String( 841 + i ).padStart( 6, '0' ) }`,
	guest: [ 'Aya Koné', 'Moussa Traoré', 'Fatou Diallo', 'Jean-Marc Kouassi' ][
		i % 4
	],
	room: `A${ ( i % 12 ) + 1 }`,
	start: `2026-10-${ String( ( i % 27 ) + 1 ).padStart( 2, '0' ) } 20:00:00`,
	end: `2026-10-${ String( ( i % 27 ) + 2 ).padStart( 2, '0' ) } 08:00:00`,
	status: Object.keys( STATUS.stay )[ i % 7 ],
	payment: Object.keys( STATUS.payment )[ i % 6 ],
	total: 10000 + i * 2500,
} ) );

const SCHEMA = z.object( {
	name: z.string().trim().min( 2, 'Enter the guest’s full name.' ),
	phone: z
		.string()
		.trim()
		.regex(
			/^\+?[0-9 ]{8,}$/,
			'Enter a phone number with at least 8 digits.'
		),
	email: z
		.string()
		.trim()
		.email( 'Enter a valid e-mail address.' )
		.or( z.literal( '' ) ),
} );

/**
 * A titled block of the kit.
 *
 * @param {Object}      props          Props.
 * @param {string}      props.title    Title.
 * @param {JSX.Element} props.children Content.
 * @return {JSX.Element} Block.
 */
function Block( { title, children } ) {
	return (
		<Panel title={ title }>
			<div className="space-y-4">{ children }</div>
		</Panel>
	);
}

/**
 * @return {JSX.Element} Screen.
 */
export default function DevUi() {
	const [ state, setState ] = useState( 'populated' );
	const [ tab, setTab ] = useState( 'all' );
	const [ page, setPage ] = useState( 1 );
	const [ sort, setSort ] = useState( { by: 'reference', dir: 'asc' } );
	const [ search, setSearch ] = useState( '' );
	const [ range, setRange ] = useState( { from: '', to: '' } );
	const [ mode, setMode ] = useState( 'arrival' );
	const [ confirm, setConfirm ] = useState( null );
	const [ image, setImage ] = useState( 0 );
	const [ sound, setSound ] = useState( 0 );
	const form = useZodForm( SCHEMA, {
		defaultValues: { name: '', phone: '', email: '' },
	} );

	usePageActions(
		<Button onClick={ () => toast.success( 'Page action clicked.' ) }>
			<Plus aria-hidden="true" />
			Page action
		</Button>
	);

	const loading = state === 'loading';
	const error =
		state === 'error'
			? new Error( 'The server could not be reached (sample error).' )
			: null;
	let rows = SAMPLE_ROWS.filter(
		( r ) => tab === 'all' || r.status === tab
	).filter(
		( r ) =>
			! search || r.guest.toLowerCase().includes( search.toLowerCase() )
	);
	rows = [ ...rows ].sort(
		( a, b ) =>
			( sort.dir === 'asc' ? 1 : -1 ) *
			( a[ sort.by ] > b[ sort.by ] ? 1 : -1 )
	);
	const pageRows =
		state === 'empty' ? [] : rows.slice( ( page - 1 ) * 10, page * 10 );

	const submit = form.handleSubmit( ( values ) => {
		// Simulate the server refusing a duplicate phone number.
		if ( values.phone.replace( /\s/g, '' ).endsWith( '0000' ) ) {
			const serverError = Object.assign(
				new Error( 'Validation failed' ),
				{
					status: 422,
					errors: {
						phone: {
							first_message:
								'A guest with this phone number already exists.',
						},
					},
				}
			);
			if ( ! applyServerErrors( form.setError, serverError ) ) {
				toastError( serverError );
			}
			return;
		}
		toast.success( `Saved ${ values.name }.` );
	} );

	return (
		<div className="space-y-6">
			<div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-primary bg-primary-softer px-4 py-3">
				<p className="m-0 text-sm text-heading">
					<strong>UI kit</strong> — shown only with WP_DEBUG on.
					Switch the state of every data component:
				</p>
				<SegmentedControl
					label="Data state"
					value={ state }
					onChange={ setState }
					options={ [
						{ value: 'populated', label: 'Populated' },
						{ value: 'loading', label: 'Loading' },
						{ value: 'empty', label: 'Empty' },
						{ value: 'error', label: 'Error' },
					] }
				/>
			</div>

			<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
				<StatCard
					icon={ Hourglass }
					label="Awaiting approval"
					value={ state === 'empty' ? 0 : 3 }
					hint="Bookings to accept or decline"
					loading={ loading }
				/>
				<StatCard
					icon={ LogIn }
					label="Arriving today"
					value={ state === 'empty' ? 0 : 7 }
					hint="Guests due to check in"
					loading={ loading }
				/>
				<StatCard
					icon={ BedDouble }
					label="Rooms free now"
					value={ state === 'empty' ? 0 : 12 }
					hint="of 30 rooms"
					loading={ loading }
				/>
				<StatCard
					icon={ CheckCircle2 }
					label="Collected today"
					value={ <Money value={ state === 'empty' ? 0 : 245000 } /> }
					loading={ loading }
				/>
			</div>

			<Block title="List: FilterTabs + DataTable + DateRangePicker">
				<FilterTabs
					label="Filter bookings"
					value={ tab }
					onChange={ ( v ) => {
						setTab( v );
						setPage( 1 );
					} }
					tabs={ [
						{
							value: 'all',
							label: 'All',
							count: SAMPLE_ROWS.length,
						},
						...[
							'pending',
							'confirmed',
							'checked_in',
							'checked_out',
						].map( ( key ) => ( {
							value: key,
							label: STATUS.stay[ key ].label,
							count: SAMPLE_ROWS.filter(
								( r ) => r.status === key
							).length,
						} ) ),
					] }
				/>
				<DataTable
					caption="Sample bookings"
					columns={ [
						{
							id: 'reference',
							header: 'Reference',
							cell: ( r ) => (
								<span className="font-semibold text-primary">
									{ r.reference }
								</span>
							),
							sortable: true,
							mobile: 'title',
						},
						{
							id: 'guest',
							header: 'Guest',
							cell: ( r ) => r.guest,
							sortable: true,
							mobile: 'subtitle',
						},
						{ id: 'room', header: 'Room', cell: ( r ) => r.room },
						{
							id: 'stay',
							header: 'Stay',
							wrap: true,
							cell: ( r ) => (
								<DateTime value={ r.start } end={ r.end } />
							),
						},
						{
							id: 'status',
							header: 'Status',
							cell: ( r ) => (
								<StatusBadge domain="stay" value={ r.status } />
							),
						},
						{
							id: 'payment',
							header: 'Payment',
							cell: ( r ) => (
								<StatusBadge
									domain="payment"
									value={ r.payment }
								/>
							),
						},
						{
							id: 'total',
							header: 'Total',
							cell: ( r ) => <Money value={ r.total } />,
							align: 'right',
							sortable: true,
						},
					] }
					rows={ pageRows }
					total={ state === 'empty' ? 0 : rows.length }
					page={ page }
					perPage={ 10 }
					onPageChange={ setPage }
					sort={ sort }
					onSortChange={ setSort }
					loading={ loading }
					error={ error }
					onRetry={ () => setState( 'populated' ) }
					search={ {
						value: search,
						onChange: setSearch,
						placeholder: 'Search guest',
					} }
					toolbar={
						<DateRangePicker
							value={ range }
							onChange={ setRange }
							mode={ mode }
							onModeChange={ setMode }
						/>
					}
					rowActions={ ( r ) => [
						{
							label: 'Approve',
							primary: true,
							onSelect: () =>
								toast.success( `${ r.reference } approved.` ),
						},
						{
							label: 'Open',
							onSelect: () => toast( `Open ${ r.reference }` ),
						},
						{
							label: 'Cancel booking',
							destructive: true,
							onSelect: () => setConfirm( r ),
						},
					] }
					onRowClick={ ( r ) => toast( `Row ${ r.reference }` ) }
					empty={
						<EmptyState
							icon={ Inbox }
							title="No bookings match"
							description="Try another tab, date range or search."
							action={
								<Button
									variant="outline"
									onClick={ () => setState( 'populated' ) }
								>
									Show sample data
								</Button>
							}
						/>
					}
				/>
			</Block>

			<div className="grid gap-6 lg:grid-cols-2">
				<Block title="StatusBadge: every status">
					{ Object.entries( STATUS ).map( ( [ domain, values ] ) => (
						<div key={ domain } className="space-y-1.5">
							<p className="m-0 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
								{ domain }
							</p>
							<div className="flex flex-wrap gap-2">
								{ Object.keys( values ).map( ( value ) => (
									<StatusBadge
										key={ value }
										domain={ domain }
										value={ value }
									/>
								) ) }
								<StatusBadge
									domain={ domain }
									value="unknown_value"
								/>
							</div>
						</div>
					) ) }
				</Block>

				<Block title="Money and DateTime">
					<dl className="m-0 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
						<dt className="text-muted-foreground">Money 1250000</dt>
						<dd className="m-0">
							<Money value={ 1250000 } />
						</dd>
						<dt className="text-muted-foreground">
							Money −3.005 signed
						</dt>
						<dd className="m-0">
							<Money value={ -3.005 } signed />
						</dd>
						<dt className="text-muted-foreground">
							Money 15000 signed
						</dt>
						<dd className="m-0">
							<Money value={ 15000 } signed />
						</dd>
						<dt className="text-muted-foreground">
							Date (ISO, UTC)
						</dt>
						<dd className="m-0">
							<DateTime value="2026-10-02T18:00:00+00:00" />
						</dd>
						<dt className="text-muted-foreground">Time</dt>
						<dd className="m-0">
							<DateTime value="2026-10-02 20:00:00" show="time" />
						</dd>
						<dt className="text-muted-foreground">
							Same-day window
						</dt>
						<dd className="m-0">
							<DateTime
								value="2026-10-02 08:30:00"
								end="2026-10-02 17:00:00"
							/>
						</dd>
						<dt className="text-muted-foreground">
							Overnight window
						</dt>
						<dd className="m-0">
							<DateTime
								value="2026-10-02 20:00:00"
								end="2026-10-03 08:00:00"
							/>
						</dd>
					</dl>
				</Block>

				<Block title="Form: FormSection + Field + zod + server errors">
					<form
						onSubmit={ submit }
						noValidate
						className="divide-y divide-border"
					>
						<FormSection
							title="Guest"
							description="Blur a field to validate. A phone ending in 0000 simulates a server error."
						>
							<Field
								label="Full name"
								name="name"
								form={ form }
								required
							>
								<Input { ...form.register( 'name' ) } />
							</Field>
							<Field
								label="Phone"
								name="phone"
								form={ form }
								required
								description="With country code, e.g. +225 07 07 12 34 56."
							>
								<Input
									{ ...form.register( 'phone' ) }
									inputMode="tel"
								/>
							</Field>
							<Field
								label="E-mail"
								name="email"
								form={ form }
								description="Optional."
							>
								<Input
									{ ...form.register( 'email' ) }
									type="email"
								/>
							</Field>
						</FormSection>
						<div className="flex justify-end gap-2 pt-4">
							<Button
								type="button"
								variant="outline"
								onClick={ () => form.reset() }
							>
								Reset
							</Button>
							<Button type="submit">Save guest</Button>
						</div>
					</form>
				</Block>

				<Block title="MediaField: WordPress media library">
					<div className="space-y-4">
						<Field
							label="Logo (images only)"
							description="Stores the attachment id."
						>
							<MediaField
								value={ image }
								onChange={ setImage }
								accept={ [ 'image/' ] }
								title="Choose a logo"
							/>
						</Field>
						<Field label="Sound (audio only)">
							<MediaField
								value={ sound }
								onChange={ setSound }
								accept={ [ 'audio/' ] }
								title="Choose a sound"
							/>
						</Field>
						<Field
							label="Missing file + server error"
							error="That file is no longer in the media library."
						>
							<MediaField
								value={ 999999 }
								onChange={ () => {} }
							/>
						</Field>
					</div>
					<p className="m-0 text-xs text-muted-foreground">
						Values: logo { image }, sound { sound }
					</p>
				</Block>

				<Block title="Feedback: toasts, dialogs, skeletons, empty">
					<div className="flex flex-wrap gap-2">
						<Button
							variant="outline"
							onClick={ () =>
								toast.success(
									'Booking RT-2026-000841 approved.'
								)
							}
						>
							Success toast
						</Button>
						<Button
							variant="outline"
							onClick={ () =>
								toastError(
									new Error( 'Room A2 was just taken.' )
								)
							}
						>
							Error toast
						</Button>
						<Button
							variant="outline"
							onClick={ () =>
								toast( 'New booking from the website.', {
									action: {
										label: 'View',
										onClick: () => {},
									},
								} )
							}
						>
							Toast with action
						</Button>
						<Button
							variant="destructive"
							onClick={ () => setConfirm( SAMPLE_ROWS[ 0 ] ) }
						>
							Confirm with reason
						</Button>
					</div>
					<div className="space-y-2">
						<Skeleton className="h-4 w-2/3" />
						<Skeleton className="h-4 w-1/2" />
						<Skeleton className="h-10 w-full" />
					</div>
					<EmptyState
						icon={ Inbox }
						title="Nothing here yet"
						description="One sentence explaining what will appear here."
						action={ <Button size="sm">Primary action</Button> }
					/>
				</Block>
			</div>

			<ConfirmDialog
				open={ !! confirm }
				onOpenChange={ ( open ) => ! open && setConfirm( null ) }
				title={ confirm ? `Cancel ${ confirm.reference }?` : '' }
				description="The room is released immediately and the guest is e-mailed. Typing “fail” simulates a server error."
				confirmLabel="Cancel booking"
				destructive
				requireReason
				onConfirm={ ( reason ) =>
					reason.toLowerCase().includes( 'fail' )
						? Promise.reject(
								new Error( 'The guest has already checked in.' )
						  )
						: new Promise( ( resolve ) =>
								setTimeout( resolve, 600 )
						  ).then( () =>
								toast.success(
									`${ confirm.reference } cancelled: ${ reason }`
								)
						  )
				}
			/>
		</div>
	);
}
