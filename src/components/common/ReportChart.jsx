/**
 * Bar chart for the reports (M10): one bar group per point, one series per
 * coloured segment (stacked by default). Series take the `--chart-1…6`
 * tokens in order, so every report and add-on report looks the same.
 *
 *   <ReportChart
 *       points={ [ { key: '2026-10-01', values: { cash: 12000, wave: 5000 } } ] }
 *       series={ [ { key: 'cash', label: 'Cash' }, { key: 'wave', label: 'Wave' } ] }
 *       formatKey={ ( key ) => formatDateAs( key, 'j M' ) }
 *       formatValue={ formatMoney }
 *   />
 *
 * Loaded lazily (recharts is only in the reports chunk); add-ons get it as
 * `window.rtbp.ui.ReportChart` (wrap in `Suspense`).
 */
import { useLayoutEffect, useRef, useState } from 'react';
import {
	Bar,
	BarChart,
	CartesianGrid,
	Legend,
	ResponsiveContainer,
	Tooltip,
	XAxis,
	YAxis,
} from 'recharts';

const SERIES = [ 1, 2, 3, 4, 5, 6 ].map( ( n ) => `--chart-${ n }` );

// Before the tokens are read (first paint), and if a token is missing.
const FALLBACK = {
	series: [
		'#0040ff',
		'#079455',
		'#dc6803',
		'#7a5af8',
		'#0086c9',
		'#98a2b3',
	],
	border: '#dbe1e6',
	muted: '#f4f6f8',
	text: '#5b6b7f',
};

/**
 * The design tokens as real colours: SVG presentation attributes (recharts'
 * `fill`, `stroke`) do not resolve CSS `var()`, so they are read from the
 * chart's own element — the brand colour from Settings included.
 *
 * @return {Array} `[ ref, colours ]`.
 */
function useTokenColors() {
	const ref = useRef( null );
	const [ colors, setColors ] = useState( FALLBACK );
	useLayoutEffect( () => {
		if ( ! ref.current ) {
			return;
		}
		const style = window.getComputedStyle( ref.current );
		const read = ( name, fallback ) =>
			style.getPropertyValue( name ).trim() || fallback;
		setColors( {
			series: SERIES.map( ( name, index ) =>
				read( name, FALLBACK.series[ index ] )
			),
			border: read( '--border', FALLBACK.border ),
			muted: read( '--muted', FALLBACK.muted ),
			text: read( '--muted-foreground', FALLBACK.text ),
		} );
	}, [] );
	return [ ref, colors ];
}

/**
 * A compact axis figure: 12 500 → 12.5k, 3 200 000 → 3.2M.
 *
 * @param {number} value Number.
 * @return {string} Short figure.
 */
const compact = ( value ) => {
	const abs = Math.abs( value );
	if ( abs >= 1e6 ) {
		return `${ +( value / 1e6 ).toFixed( 1 ) }M`;
	}
	if ( abs >= 1e3 ) {
		return `${ +( value / 1e3 ).toFixed( 1 ) }k`;
	}
	return String( value );
};

/**
 * The tooltip box (rendered inside the chart, so it is styled by the app).
 *
 * @param {Object}   props             Recharts tooltip props.
 * @param {boolean}  props.active      Shown.
 * @param {Array}    props.payload     Series values.
 * @param {string}   props.label       Point key.
 * @param {Function} props.formatKey   Key → label.
 * @param {Function} props.formatValue Value → text.
 * @return {JSX.Element|null} Tooltip.
 */
function ChartTooltip( { active, payload, label, formatKey, formatValue } ) {
	if ( ! active || ! payload?.length ) {
		return null;
	}
	const shown = payload.filter( ( item ) => Number( item.value ) !== 0 );
	return (
		<div className="min-w-[10rem] rounded-lg border border-border bg-card p-3 text-sm shadow-md">
			<p className="m-0 mb-1.5 font-semibold text-heading">
				{ formatKey( label ) }
			</p>
			{ ( shown.length ? shown : payload ).map( ( item ) => (
				<p
					key={ item.dataKey }
					className="m-0 flex items-center justify-between gap-4"
				>
					<span className="flex items-center gap-2 text-muted-foreground">
						<span
							className="h-2.5 w-2.5 rounded-sm"
							style={ { background: item.color } }
							aria-hidden="true"
						/>
						{ item.name }
					</span>
					<span className="font-medium tabular-nums text-foreground">
						{ formatValue( item.value ) }
					</span>
				</p>
			) ) }
		</div>
	);
}

/**
 * @param {Object}   props             Props.
 * @param {Array}    props.points      `[ { key, label?, values: { series: number } } ]`.
 * @param {Array}    props.series      `[ { key, label } ]`, in colour order.
 * @param {Function} props.formatKey   Point key → axis / tooltip label.
 * @param {Function} props.formatValue Value → tooltip text.
 * @param {boolean}  props.stacked     Stack the series (default true).
 * @param {number}   props.height      Height in px (default 280).
 * @param {string}   props.label       Accessible description of the chart.
 * @return {JSX.Element} Chart.
 */
export default function ReportChart( {
	points = [],
	series = [],
	formatKey = ( key ) => key,
	formatValue = ( value ) => String( value ),
	stacked = true,
	height = 280,
	label,
} ) {
	const [ ref, colors ] = useTokenColors();
	const data = points.map( ( point ) => ( {
		key: point.key,
		name: point.label ?? formatKey( point.key ),
		...point.values,
	} ) );

	return (
		<div ref={ ref } role="img" aria-label={ label } style={ { height } }>
			<ResponsiveContainer width="100%" height="100%">
				<BarChart
					data={ data }
					margin={ { top: 8, right: 8, bottom: 0, left: 0 } }
				>
					<CartesianGrid
						vertical={ false }
						stroke={ colors.border }
						strokeDasharray="3 3"
					/>
					<XAxis
						dataKey="name"
						tickLine={ false }
						axisLine={ false }
						tick={ {
							fontSize: 12,
							fill: colors.text,
						} }
						minTickGap={ 8 }
					/>
					<YAxis
						tickLine={ false }
						axisLine={ false }
						width={ 48 }
						tick={ {
							fontSize: 12,
							fill: colors.text,
						} }
						tickFormatter={ compact }
					/>
					<Tooltip
						cursor={ { fill: colors.muted } }
						content={
							<ChartTooltip
								formatKey={ ( name ) => name }
								formatValue={ formatValue }
							/>
						}
					/>
					{ series.length > 1 ? (
						<Legend
							iconType="square"
							iconSize={ 10 }
							wrapperStyle={ { fontSize: 12, paddingTop: 8 } }
						/>
					) : null }
					{ series.map( ( item, index ) => (
						<Bar
							key={ item.key }
							dataKey={ item.key }
							name={ item.label }
							stackId={ stacked ? 'total' : undefined }
							fill={
								colors.series[ index % colors.series.length ]
							}
							maxBarSize={ 48 }
							radius={
								! stacked || index === series.length - 1
									? [ 4, 4, 0, 0 ]
									: 0
							}
						/>
					) ) }
				</BarChart>
			</ResponsiveContainer>
		</div>
	);
}
