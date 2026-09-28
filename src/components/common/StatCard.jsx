/**
 * KPI card: a brand-coloured icon tile, a label, a big number and an optional
 * hint. Clickable when `to` is given (it links to the filtered list).
 */
import { Link } from 'react-router-dom';

import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/**
 * @param {Object}   props         Props.
 * @param {Function} props.icon    lucide-react icon component.
 * @param {string}   props.label   What is counted.
 * @param {*}        props.value   The figure (number or formatted string).
 * @param {string}   props.hint    Small line under the figure.
 * @param {string}   props.to      Optional route to open on click.
 * @param {boolean}  props.loading Show a skeleton instead of the value.
 * @return {JSX.Element} Card.
 */
export default function StatCard( {
	icon: Icon,
	label,
	value,
	hint,
	to,
	loading,
} ) {
	const body = (
		<>
			<span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm">
				{ Icon ? (
					<Icon className="h-6 w-6" aria-hidden="true" />
				) : null }
			</span>
			<div className="min-w-0 flex-1">
				<p className="m-0 truncate text-[13px] font-medium text-muted-foreground">
					{ label }
				</p>
				{ loading ? (
					<Skeleton className="mt-1.5 h-7 w-16" />
				) : (
					<p className="m-0 mt-0.5 text-2xl font-bold leading-8 tabular-nums text-heading">
						{ value }
					</p>
				) }
				{ hint ? (
					<p className="m-0 line-clamp-2 text-xs text-muted-foreground">
						{ hint }
					</p>
				) : null }
			</div>
		</>
	);

	const className =
		'flex min-w-0 items-center gap-4 rounded-xl border border-border bg-card p-5 shadow-sm no-underline transition-all duration-150';

	return to ? (
		<Link
			to={ to }
			className={ cn(
				className,
				'hover:-translate-y-0.5 hover:border-primary hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
			) }
		>
			{ body }
		</Link>
	) : (
		<div className={ className }>{ body }</div>
	);
}
