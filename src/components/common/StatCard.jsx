/**
 * KPI card: a brand-coloured icon tile, a label, a big number and an optional
 * hint. Clickable when `to` is given (it links to the filtered list).
 *
 * With `tone` it takes the hotel dashboard's look (R1, as on the old
 * dashboard): label and a coloured outline icon on top, the figure under
 * them, a soft tint of the tone fading to white and a matching border.
 */
import { Link } from 'react-router-dom';

import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/**
 * Tones: icon colour, tint (top of the card) and border.
 */
const TONES = {
	red: {
		icon: '#dc2626',
		tint: '#fdf2f2',
		border: 'rgba(183, 52, 54, 0.22)',
	},
	green: {
		icon: '#039855',
		tint: '#f0f9f5',
		border: 'rgba(23, 135, 89, 0.22)',
	},
	orange: {
		icon: '#dc6803',
		tint: '#fef6ee',
		border: 'rgba(196, 98, 18, 0.22)',
	},
	blue: {
		icon: '#2563eb',
		tint: '#eff4ff',
		border: 'rgba(37, 99, 235, 0.2)',
	},
	purple: {
		icon: '#7c3aed',
		tint: '#f5f3ff',
		border: 'rgba(124, 58, 237, 0.2)',
	},
	amber: {
		icon: '#d97706',
		tint: '#fffbeb',
		border: 'rgba(217, 119, 6, 0.22)',
	},
};

/**
 * @param {Object}   props         Props.
 * @param {Function} props.icon    lucide-react icon component.
 * @param {string}   props.label   What is counted.
 * @param {*}        props.value   The figure (number or formatted string).
 * @param {string}   props.hint    Small line under the figure.
 * @param {string}   props.to      Optional route to open on click.
 * @param {boolean}  props.loading Show a skeleton instead of the value.
 * @param {string}   props.tone    `red` | `green` | `orange` | `blue` | `purple` | `amber`: the tinted look.
 * @return {JSX.Element} Card.
 */
export default function StatCard( {
	icon: Icon,
	label,
	value,
	hint,
	to,
	loading,
	tone,
} ) {
	const colors = tone ? TONES[ tone ] || TONES.blue : null;
	if ( colors ) {
		const tinted = (
			<>
				<div className="flex items-start justify-between gap-3">
					<p className="m-0 min-w-0 truncate text-[15px] font-medium text-muted-foreground">
						{ label }
					</p>
					{ Icon ? (
						<Icon
							className="h-6 w-6 shrink-0"
							style={ { color: colors.icon } }
							strokeWidth={ 1.75 }
							aria-hidden="true"
						/>
					) : null }
				</div>
				{ loading ? (
					<Skeleton className="mt-2 h-8 w-16" />
				) : (
					<p className="m-0 mt-1 text-[32px] font-semibold leading-10 tabular-nums text-heading">
						{ value }
					</p>
				) }
				{ hint ? (
					<p className="m-0 truncate text-xs text-muted-foreground">
						{ hint }
					</p>
				) : null }
			</>
		);
		const tintedClass =
			'block min-w-0 rounded-lg border p-5 no-underline transition-all duration-150';
		const style = {
			backgroundImage: `linear-gradient(${ colors.tint }, #ffffff)`,
			borderColor: colors.border,
		};
		return to ? (
			<Link
				to={ to }
				style={ style }
				className={ cn(
					tintedClass,
					'hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
				) }
			>
				{ tinted }
			</Link>
		) : (
			<div style={ style } className={ tintedClass }>
				{ tinted }
			</div>
		);
	}

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
