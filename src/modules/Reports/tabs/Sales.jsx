/**
 * Sales report (M10, 10.1–10.6): the money of the period, tax apart, how much
 * was collected, how many booked rooms were paid or did not happen, a chart
 * split by payment method, and the methods table. Figures come from the
 * stored booking lines and payments (`GET reports/sales`); nothing is
 * re-priced.
 */
import { lazy, Suspense } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	AlertCircle,
	Banknote,
	BarChart3,
	CircleCheck,
	Percent,
	Wallet,
	XCircle,
} from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import Money from '@/components/common/Money';
import Panel from '@/components/common/Panel';
import StatCard from '@/components/common/StatCard';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateAs, formatMoney } from '@/lib/format';
import { useReport } from '../api';

const ReportChart = lazy( () => import( '@/components/common/ReportChart' ) );

/**
 * A chart point's label for its grouping.
 *
 * @param {string} mode `day`, `month` or `method`.
 * @return {Function} Key → label.
 */
const keyLabel = ( mode ) => ( key ) => {
	if ( 'day' === mode ) {
		return formatDateAs( key, 'j M' );
	}
	if ( 'month' === mode ) {
		return formatDateAs( `${ key }-01`, 'M Y' );
	}
	return key;
};

/**
 * @param {Object} props       Props.
 * @param {Object} props.range `{ from, to, mode }`.
 * @return {JSX.Element} Tab.
 */
export default function Sales( { range } ) {
	const report = useReport( 'sales', range );
	const data = report.data;
	const totals = data?.totals || {};
	const loading = report.isPending;

	if ( report.isError && ! data ) {
		return (
			<Panel>
				<div className="flex flex-col items-center gap-3 py-10 text-center">
					<AlertCircle
						className="h-8 w-8 text-destructive"
						aria-hidden="true"
					/>
					<p className="m-0 text-sm text-muted-foreground">
						{ report.error?.message ||
							__(
								'The report could not be loaded.',
								'radius-hotel-booking'
							) }
					</p>
					<Button
						variant="outline"
						onClick={ () => report.refetch() }
					>
						{ __( 'Try again', 'radius-hotel-booking' ) }
					</Button>
				</div>
			</Panel>
		);
	}

	const taxName = totals.tax_label || __( 'Tax', 'radius-hotel-booking' );
	const chart = data?.chart;
	const hasSales = ( totals.sold || 0 ) > 0;

	return (
		<div className="space-y-5">
			<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
				<StatCard
					icon={ Banknote }
					label={ __( 'Net sales', 'radius-hotel-booking' ) }
					value={ <Money value={ totals.net_sales } /> }
					hint={ sprintf(
						/* translators: %s: amount including tax. */
						__( '%s with tax included', 'radius-hotel-booking' ),
						formatMoney( totals.revenue || 0 )
					) }
					loading={ loading }
				/>
				<StatCard
					icon={ Percent }
					label={ taxName }
					value={ <Money value={ totals.tax } /> }
					hint={
						totals.tax_rate
							? sprintf(
									/* translators: %s: tax rate, e.g. 18. */
									__(
										'%s%% included in the prices',
										'radius-hotel-booking'
									),
									totals.tax_rate
							  )
							: __(
									'No tax rate is set (Settings → Invoices)',
									'radius-hotel-booking'
							  )
					}
					loading={ loading }
				/>
				<StatCard
					icon={ Wallet }
					label={ __( 'Collected', 'radius-hotel-booking' ) }
					value={ <Money value={ totals.collected } /> }
					hint={
						'created' === range.mode
							? __(
									'Payments received in the period, refunds taken off',
									'radius-hotel-booking'
							  )
							: __(
									'Paid on these stays so far, refunds taken off',
									'radius-hotel-booking'
							  )
					}
					loading={ loading }
				/>
				<StatCard
					icon={ BarChart3 }
					label={ __( 'Rooms sold', 'radius-hotel-booking' ) }
					value={ totals.sold ?? 0 }
					hint={ __(
						'Booked rooms counted in the sales',
						'radius-hotel-booking'
					) }
					loading={ loading }
				/>
				<StatCard
					icon={ CircleCheck }
					label={ __( 'Completed', 'radius-hotel-booking' ) }
					value={ totals.completed ?? 0 }
					hint={ __(
						'Booked rooms on fully paid bookings',
						'radius-hotel-booking'
					) }
					loading={ loading }
				/>
				<StatCard
					icon={ XCircle }
					label={ __( 'Unsuccessful', 'radius-hotel-booking' ) }
					value={ totals.unsuccessful ?? 0 }
					hint={ sprintf(
						/* translators: %d: number of no-shows. */
						__(
							'Cancelled, declined or refunded · %d no-shows apart',
							'radius-hotel-booking'
						),
						totals.no_show || 0
					) }
					loading={ loading }
				/>
			</div>

			<Panel
				title={ __(
					'Sales by payment method',
					'radius-hotel-booking'
				) }
				description={
					'method' === chart?.mode
						? __( 'For the day', 'radius-hotel-booking' )
						: 'month' === chart?.mode
						? __( 'Per month', 'radius-hotel-booking' )
						: __( 'Per day', 'radius-hotel-booking' )
				}
			>
				{ loading ? (
					<Skeleton className="h-[280px] w-full" />
				) : hasSales ? (
					<Suspense
						fallback={ <Skeleton className="h-[280px] w-full" /> }
					>
						<ReportChart
							points={ chart.points }
							series={ chart.series }
							formatKey={ keyLabel( chart.mode ) }
							formatValue={ ( value ) => formatMoney( value ) }
							label={ __(
								'Sales by payment method',
								'radius-hotel-booking'
							) }
						/>
					</Suspense>
				) : (
					<EmptyState
						icon={ BarChart3 }
						title={ __(
							'No sales in this period',
							'radius-hotel-booking'
						) }
						description={
							'created' === range.mode
								? __(
										'No booking taken in these dates was sold. Try other dates, or switch to arrival date.',
										'radius-hotel-booking'
								  )
								: __(
										'No stay starting in these dates was sold. Try other dates, or switch to booking date.',
										'radius-hotel-booking'
								  )
						}
					/>
				) }
			</Panel>

			<Panel
				title={ __( 'Payment methods', 'radius-hotel-booking' ) }
				description={ __(
					'A booking counts under its latest payment.',
					'radius-hotel-booking'
				) }
			>
				{ loading ? (
					<Skeleton className="h-32 w-full" />
				) : (
					<MethodsTable rows={ data?.methods || [] } />
				) }
			</Panel>

			<Definitions />
		</div>
	);
}

/**
 * The methods table: bookings, rooms, sales and money collected per method.
 *
 * @param {Object} props      Props.
 * @param {Array}  props.rows `[ { key, label, bookings, lines, revenue, collected } ]`.
 * @return {JSX.Element} Table.
 */
function MethodsTable( { rows } ) {
	if ( ! rows.length ) {
		return (
			<p className="m-0 text-sm text-muted-foreground">
				{ __(
					'Nothing to show for this period.',
					'radius-hotel-booking'
				) }
			</p>
		);
	}
	const head =
		'px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground';
	const cell = 'px-3 py-2.5 tabular-nums';
	return (
		<div className="-mx-1 overflow-x-auto">
			<table className="w-full min-w-[32rem] border-collapse text-sm">
				<thead>
					<tr className="border-b border-border">
						<th scope="col" className={ `${ head } text-left` }>
							{ __( 'Method', 'radius-hotel-booking' ) }
						</th>
						<th scope="col" className={ `${ head } text-right` }>
							{ __( 'Bookings', 'radius-hotel-booking' ) }
						</th>
						<th scope="col" className={ `${ head } text-right` }>
							{ __( 'Rooms', 'radius-hotel-booking' ) }
						</th>
						<th scope="col" className={ `${ head } text-right` }>
							{ __( 'Sales', 'radius-hotel-booking' ) }
						</th>
						<th scope="col" className={ `${ head } text-right` }>
							{ __( 'Collected', 'radius-hotel-booking' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row ) => (
						<tr
							key={ row.key || 'unpaid' }
							className="border-b border-border last:border-b-0"
						>
							<th
								scope="row"
								className="px-3 py-2.5 text-left font-medium text-heading"
							>
								{ row.label }
							</th>
							<td className={ `${ cell } text-right` }>
								{ row.bookings }
							</td>
							<td className={ `${ cell } text-right` }>
								{ row.lines }
							</td>
							<td className={ `${ cell } text-right` }>
								<Money value={ row.revenue } />
							</td>
							<td className={ `${ cell } text-right` }>
								<Money value={ row.collected } />
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

/**
 * How the figures are counted (the module's definitions, on screen).
 *
 * @return {JSX.Element} Help.
 */
function Definitions() {
	const items = [
		__(
			'Sales are the prices frozen on the booked rooms, never re-priced. Cancelled, declined and no-show rooms, and refunded bookings, are left out.',
			'radius-hotel-booking'
		),
		__(
			'Prices include the tax; net sales are the sales without it, at the tax rate set now in Settings → Invoices.',
			'radius-hotel-booking'
		),
		__(
			'By arrival date: stays starting in the period. By booking date: bookings taken in the period.',
			'radius-hotel-booking'
		),
		__(
			'Collected is the money in the payments ledger, refunds and voided payments taken off.',
			'radius-hotel-booking'
		),
	];
	return (
		<details className="rounded-xl border border-border bg-card px-4 py-3 text-sm">
			<summary className="cursor-pointer font-medium text-heading">
				{ __(
					'How these figures are counted',
					'radius-hotel-booking'
				) }
			</summary>
			<ul className="m-0 mt-3 list-disc space-y-1.5 pl-5 text-muted-foreground">
				{ items.map( ( item ) => (
					<li key={ item }>{ item }</li>
				) ) }
			</ul>
		</details>
	);
}
