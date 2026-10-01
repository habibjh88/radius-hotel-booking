/**
 * Reports (M10): one screen, a tab per report. The period and the date mode
 * (stays *arriving* in it, or bookings *taken* in it — 10.7) are shared by
 * every tab and live in the URL (`?tab=&from=&to=&mode=`), so a reload or a
 * shared link opens the same figures.
 *
 * Tabs come from `./tabs` plus the `rtbp.reports.tabs` filter (Pro adds
 * occupancy and revenue breakdowns); a tab whose access key is locked is not
 * shown.
 */
import { Suspense, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { BarChart3 } from 'lucide-react';

import DateRangePicker from '@/components/common/DateRangePicker';
import EmptyState from '@/components/common/EmptyState';
import FilterTabs from '@/components/common/FilterTabs';
import { Skeleton } from '@/components/ui/skeleton';
import { canAccess, useAccessMap } from '@/lib/access';
import { siteToday } from '@/lib/format';
import ExportButton from './ExportButton';
import coreTabs from './tabs';

/**
 * Core tabs plus add-on tabs the user may open, de-duplicated by key.
 *
 * @param {Object} levels The access map (re-evaluates when it changes).
 * @return {Array<Object>} Tabs.
 */
function useTabs( levels ) {
	return useMemo( () => {
		const core = coreTabs();
		const extra = ( applyFilters( 'rtbp.reports.tabs', [] ) || [] ).filter(
			( tab ) =>
				tab?.key &&
				tab.Component &&
				! core.some( ( { key } ) => key === tab.key )
		);
		return [ ...core, ...extra ].filter( ( tab ) =>
			canAccess( tab.accessKey )
		);
		// eslint-disable-next-line react-hooks/exhaustive-deps -- recomputed when the access map changes.
	}, [ levels ] );
}

/**
 * @return {JSX.Element} Screen.
 */
export default function Reports() {
	const [ params, setParams ] = useSearchParams();
	const { data: levels } = useAccessMap();
	const tabs = useTabs( levels );

	const today = siteToday();
	const active =
		tabs.find( ( tab ) => tab.key === params.get( 'tab' ) ) || tabs[ 0 ];
	const range = {
		from: params.get( 'from' ) || `${ today.slice( 0, 8 ) }01`,
		to: params.get( 'to' ) || today,
		mode: params.get( 'mode' ) === 'created' ? 'created' : 'arrival',
	};

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

	if ( ! active ) {
		return (
			<EmptyState
				icon={ BarChart3 }
				title={ __( 'No reports to show', 'radius-hotel-booking' ) }
				description={ __(
					'Your role cannot open any report. Ask a manager for access.',
					'radius-hotel-booking'
				) }
			/>
		);
	}

	const Component = active.Component;
	return (
		<div className="space-y-5">
			<div className="flex flex-col gap-3 2xl:flex-row 2xl:items-center 2xl:justify-between">
				{ tabs.length > 1 ? (
					<FilterTabs
						tabs={ tabs.map( ( tab ) => ( {
							value: tab.key,
							label: tab.label,
						} ) ) }
						value={ active.key }
						onChange={ ( key ) => setView( { tab: key } ) }
						label={ __( 'Reports', 'radius-hotel-booking' ) }
					/>
				) : (
					<h2 className="m-0 text-lg font-bold text-heading">
						{ active.label }
					</h2>
				) }
				<div className="flex flex-wrap items-center gap-2">
					{ false !== active.range ? (
						<DateRangePicker
							value={ { from: range.from, to: range.to } }
							onChange={ ( next ) =>
								setView( {
									from: next?.from || '',
									to: next?.to || '',
								} )
							}
							mode={
								false !== active.mode ? range.mode : undefined
							}
							onModeChange={
								false !== active.mode
									? ( mode ) =>
											setView( {
												mode:
													'created' === mode
														? mode
														: '',
											} )
									: undefined
							}
							align="end"
						/>
					) : null }
					{ false !== active.export ? (
						<ExportButton
							report={ active.report || active.key }
							params={ {
								...range,
								// A tab without the date mode exports without it.
								...( false === active.mode
									? { mode: undefined }
									: {} ),
								// The availability grid's window times, when set.
								...Object.fromEntries(
									[ 'from_time', 'to_time' ]
										.filter( ( key ) => params.get( key ) )
										.map( ( key ) => [
											key,
											params.get( key ),
										] )
								),
							} }
						/>
					) : null }
				</div>
			</div>
			<Suspense fallback={ <Skeleton className="h-96 w-full" /> }>
				<Component range={ range } />
			</Suspense>
		</div>
	);
}
