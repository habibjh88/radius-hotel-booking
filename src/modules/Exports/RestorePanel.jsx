/**
 * Exports → Restore bookings (R1-T16): upload a bookings export (CSV) to
 * re-create the bookings it holds that are no longer on the site — after a
 * site was put back to an earlier backup, say. Bookings already on the site
 * are never changed. A preview first, then Restore.
 *
 * A large file (10 000 bookings and more) restores in batches: the server
 * keeps the upload and its place in it, and the panel asks for one batch
 * after another, a few seconds each, with a progress bar. A failed request
 * is tried again (the server carries on where it was); Stop ends the run, and
 * restoring the same file again skips what is already back.
 *
 * The file library's *Restore* action hands a file over in `from`: it is
 * previewed from the library, without downloading and uploading it again.
 */
import { useEffect, useRef, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { RotateCcw, Square, Upload } from 'lucide-react';

import { request } from '@/api/client';
import Panel from '@/components/common/Panel';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';

/**
 * Most failed orders and warnings kept on screen (the totals count them all).
 */
const KEEP = 200;

/**
 * Tries of one batch before the run pauses.
 */
const TRIES = 3;

/**
 * A number in the site's locale.
 *
 * @param {number} value Number.
 * @return {string} Formatted.
 */
const num = ( value ) => Number( value || 0 ).toLocaleString();

/**
 * Wait.
 *
 * @param {number} ms Milliseconds.
 * @return {Promise<void>} Resolves after the wait.
 */
const wait = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );

/**
 * Upload the file and read it: the plan, with its `session`.
 *
 * @param {File} file File.
 * @return {Promise<Object>} Plan.
 */
const upload = ( file ) => {
	const body = new window.FormData();
	body.append( 'file', file );
	body.append( 'mode', 'preview' );
	return request( 'exports/restore', { method: 'POST', body } ).then(
		( { data } ) => data
	);
};

/**
 * Read a file of the library: the plan, with its `session`.
 *
 * @param {number} exportId Library row id.
 * @return {Promise<Object>} Plan.
 */
const fromLibrary = ( exportId ) =>
	request( 'exports/restore', {
		method: 'POST',
		data: { mode: 'preview', export_id: exportId },
	} ).then( ( { data } ) => data );

/**
 * One call on a session: `run` (the next batch) or `cancel`.
 *
 * @param {string} mode    run|cancel.
 * @param {string} session Session id.
 * @return {Promise<Object>} Data.
 */
const call = ( mode, session ) =>
	request( 'exports/restore', {
		method: 'POST',
		data: { mode, session },
	} ).then( ( { data } ) => data );

/**
 * @param {Object} props      Props.
 * @param {Object} props.from A library file to restore: `{ id, name, key }` (`key` changes per click).
 * @return {JSX.Element} Panel.
 */
export default function RestorePanel( { from = null } ) {
	const [ file, setFile ] = useState( null );
	const [ libraryName, setLibraryName ] = useState( '' );
	const panelRef = useRef( null );
	const [ plan, setPlan ] = useState( null );
	// `{ totals, failed, warnings, state: running|paused|stopped|done, error }`.
	const [ run, setRun ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const stopRef = useRef( false );
	const running = 'running' === run?.state;

	// Leaving the page mid-run would stop it: ask first.
	useEffect( () => {
		if ( ! running ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ running ] );

	// A file picked in the library: preview it here.
	useEffect( () => {
		if ( ! from?.id ) {
			return;
		}
		if ( running ) {
			toast.error(
				__(
					'A restore is running: stop it first.',
					'radius-hotel-booking'
				)
			);
			return;
		}
		panelRef.current?.scrollIntoView( {
			behavior: 'smooth',
			block: 'start',
		} );
		setFile( null );
		setLibraryName( from.name || '' );
		setBusy( true );
		setRun( null );
		setPlan( null );
		fromLibrary( from.id )
			.then( setPlan )
			.catch( toastError )
			.finally( () => setBusy( false ) );
		// Only a new click (its key) starts a preview.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ from?.key ] );

	const preview = async () => {
		setBusy( true );
		setRun( null );
		setLibraryName( '' );
		try {
			setPlan( await upload( file ) );
		} catch ( error ) {
			setPlan( null );
			toastError( error );
		} finally {
			setBusy( false );
		}
	};

	const restore = async () => {
		stopRef.current = false;
		setRun( ( current ) => ( {
			totals: current?.totals || {},
			failed: current?.failed || [],
			warnings: current?.warnings || [],
			state: 'running',
			error: '',
		} ) );
		for (;;) {
			if ( stopRef.current ) {
				try {
					await call( 'cancel', plan.session );
				} catch ( error ) {
					// The session may already be gone; nothing to undo.
				}
				setRun( ( current ) => ( { ...current, state: 'stopped' } ) );
				return;
			}
			let batch = null;
			let failure = null;
			for ( let attempt = 1; attempt <= TRIES && ! batch; attempt++ ) {
				try {
					batch = await call( 'run', plan.session );
				} catch ( error ) {
					failure = error;
					// A session that ended cannot be tried again.
					if ( 'restore_gone' === error?.code ) {
						break;
					}
					await wait( 2000 * attempt );
				}
			}
			if ( ! batch ) {
				const gone = 'restore_gone' === failure?.code;
				setRun( ( current ) => ( {
					...current,
					state: gone ? 'stopped' : 'paused',
					error: failure?.message || '',
				} ) );
				return;
			}
			setRun( ( current ) => ( {
				...current,
				totals: batch.totals,
				failed: [ ...current.failed, ...batch.failed ].slice( 0, KEEP ),
				warnings: [ ...current.warnings, ...batch.warnings ].slice(
					0,
					KEEP
				),
			} ) );
			if ( batch.done ) {
				setRun( ( current ) => ( { ...current, state: 'done' } ) );
				toast.success(
					sprintf(
						/* translators: %s: number of bookings. */
						_n(
							'%s booking restored.',
							'%s bookings restored.',
							batch.totals.restored,
							'radius-hotel-booking'
						),
						num( batch.totals.restored )
					)
				);
				return;
			}
		}
	};

	const actionLabel = {
		restore: __( 'Will be restored', 'radius-hotel-booking' ),
		exists: __( 'Already on the site', 'radius-hotel-booking' ),
		invalid: __( 'Cannot be restored', 'radius-hotel-booking' ),
	};
	const total = plan?.total || 0;
	const processed = run?.totals?.processed || 0;
	const percent = total ? Math.min( 100, ( processed / total ) * 100 ) : 0;
	const locked = busy || running;

	return (
		<div ref={ panelRef } className="scroll-mt-20">
			<Panel
				title={ __( 'Restore bookings', 'radius-hotel-booking' ) }
				description={ __(
					'Upload a bookings export (CSV) to re-create the bookings it holds that are no longer on the site. Bookings already here are never changed.',
					'radius-hotel-booking'
				) }
			>
				<div className="space-y-4">
					<div className="flex flex-wrap items-center gap-3">
						<label
							className={ cn(
								'inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm font-medium text-heading',
								locked
									? 'cursor-not-allowed opacity-60'
									: 'cursor-pointer hover:bg-muted'
							) }
						>
							<Upload className="h-4 w-4" aria-hidden="true" />
							{ file?.name ||
								libraryName ||
								__(
									'Choose a CSV file',
									'radius-hotel-booking'
								) }
							<input
								type="file"
								accept=".csv,text/csv"
								className="sr-only"
								disabled={ locked }
								onChange={ ( event ) => {
									setFile(
										event.target.files?.[ 0 ] || null
									);
									setLibraryName( '' );
									setPlan( null );
									setRun( null );
								} }
							/>
						</label>
						<Button
							type="button"
							variant="outline"
							disabled={ ! file || locked }
							onClick={ preview }
						>
							{ busy
								? __( 'Reading…', 'radius-hotel-booking' )
								: __( 'Preview', 'radius-hotel-booking' ) }
						</Button>
					</div>

					{ plan && ! run ? (
						<div className="space-y-3">
							<p className="m-0 text-sm text-heading">
								{ sprintf(
									/* translators: 1: to restore, 2: already on the site, 3: cannot be restored. */
									__(
										'%1$s to restore · %2$s already on the site · %3$s cannot be restored',
										'radius-hotel-booking'
									),
									num( plan.counts.restore ),
									num( plan.counts.exists ),
									num( plan.counts.invalid )
								) }
							</p>
							<ul className="m-0 max-h-72 list-none divide-y divide-border overflow-y-auto rounded-lg border border-border p-0">
								{ plan.orders.map( ( order, index ) => (
									<li
										key={ `${ order.reference }-${ index }` }
										className="m-0 flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
									>
										<span className="min-w-0">
											<span className="font-semibold text-heading">
												{ order.reference }
											</span>
											<span className="text-muted-foreground">
												{ ` · ${ order.guest } · ${ order.arrival }` }
											</span>
											{ order.reason &&
											'exists' !== order.action ? (
												<span className="block text-xs text-destructive">
													{ order.reason }
												</span>
											) : null }
										</span>
										<span
											className={ cn(
												'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
												{
													restore:
														'bg-success-soft text-success',
													exists: 'bg-muted text-muted-foreground',
													invalid:
														'bg-destructive-soft text-destructive',
												}[ order.action ]
											) }
										>
											{ actionLabel[ order.action ] }
										</span>
									</li>
								) ) }
							</ul>
							{ total > plan.orders.length ? (
								<p className="m-0 text-xs text-muted-foreground">
									{ sprintf(
										/* translators: 1: orders listed, 2: orders in the file. */
										__(
											'Listing %1$s of %2$s orders, those that cannot be restored first.',
											'radius-hotel-booking'
										),
										num( plan.orders.length ),
										num( total )
									) }
								</p>
							) : null }
							<Button
								type="button"
								disabled={ ! plan.counts.restore || locked }
								onClick={ restore }
							>
								<RotateCcw
									className="h-4 w-4"
									aria-hidden="true"
								/>
								{ sprintf(
									/* translators: %s: number of bookings. */
									_n(
										'Restore %s booking',
										'Restore %s bookings',
										plan.counts.restore,
										'radius-hotel-booking'
									),
									num( plan.counts.restore )
								) }
							</Button>
						</div>
					) : null }

					{ run ? (
						<div className="space-y-3 rounded-lg border border-border p-3 text-sm">
							<div className="flex flex-wrap items-center justify-between gap-2">
								<p className="m-0 font-semibold text-heading">
									{
										{
											running: __(
												'Restoring…',
												'radius-hotel-booking'
											),
											paused: __(
												'Paused: the server did not answer.',
												'radius-hotel-booking'
											),
											stopped: __(
												'Stopped.',
												'radius-hotel-booking'
											),
											done: __(
												'Restore finished.',
												'radius-hotel-booking'
											),
										}[ run.state ]
									}
								</p>
								<p className="m-0 text-muted-foreground">
									{ sprintf(
										/* translators: 1: orders looked at, 2: orders in the file. */
										__(
											'%1$s of %2$s orders',
											'radius-hotel-booking'
										),
										num( processed ),
										num( total )
									) }
								</p>
							</div>
							<Progress
								value={ percent }
								aria-label={ __(
									'Restore progress',
									'radius-hotel-booking'
								) }
							/>
							<p className="m-0 text-heading">
								{ sprintf(
									/* translators: 1: restored, 2: skipped, 3: failed. */
									__(
										'%1$s restored · %2$s already on the site · %3$s failed',
										'radius-hotel-booking'
									),
									num( run.totals.restored ),
									num( run.totals.skipped ),
									num( run.totals.failed )
								) }
							</p>
							{ run.error ? (
								<p className="m-0 text-destructive">
									{ run.error }
								</p>
							) : null }
							{ 'stopped' === run.state ? (
								<p className="m-0 text-muted-foreground">
									{ __(
										'Bookings restored so far stay. To carry on, preview the same file again and restore: what is already back is skipped.',
										'radius-hotel-booking'
									) }
								</p>
							) : null }
							<div className="flex flex-wrap gap-2">
								{ running ? (
									<Button
										type="button"
										variant="outline"
										onClick={ () => {
											stopRef.current = true;
										} }
									>
										<Square
											className="h-4 w-4"
											aria-hidden="true"
										/>
										{ __(
											'Stop after this batch',
											'radius-hotel-booking'
										) }
									</Button>
								) : null }
								{ 'paused' === run.state ? (
									<Button type="button" onClick={ restore }>
										<RotateCcw
											className="h-4 w-4"
											aria-hidden="true"
										/>
										{ __(
											'Carry on',
											'radius-hotel-booking'
										) }
									</Button>
								) : null }
							</div>
							{ run.totals.failed > run.failed.length ? (
								<p className="m-0 text-xs text-muted-foreground">
									{ sprintf(
										/* translators: 1: failures listed, 2: all failures. */
										__(
											'Listing the first %1$s of %2$s that failed.',
											'radius-hotel-booking'
										),
										num( run.failed.length ),
										num( run.totals.failed )
									) }
								</p>
							) : null }
							{ run.failed.length || run.warnings.length ? (
								<div className="max-h-60 space-y-1 overflow-y-auto">
									{ run.failed.map( ( row, index ) => (
										<p
											key={ `f-${ index }` }
											className="m-0 text-destructive"
										>
											{ `${ row.reference }: ${ row.reason }` }
										</p>
									) ) }
									{ run.warnings.map( ( row, index ) => (
										<p
											key={ `w-${ index }` }
											className="m-0 text-muted-foreground"
										>
											{ `${ row.reference }: ${ row.message }` }
										</p>
									) ) }
								</div>
							) : null }
						</div>
					) : null }
				</div>
			</Panel>
		</div>
	);
}
