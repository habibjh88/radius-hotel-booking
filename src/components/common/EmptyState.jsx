/**
 * Empty state: an icon, one line of title, one line of help, one action.
 */
import { cn } from '@/lib/utils';

/**
 * @param {Object}      props             Props.
 * @param {Function}    props.icon        lucide-react icon component.
 * @param {string}      props.title       What is empty.
 * @param {string}      props.description What to do about it.
 * @param {JSX.Element} props.action      Optional button.
 * @param {string}      props.className   Extra classes.
 * @return {JSX.Element} Empty state.
 */
export default function EmptyState( {
	icon: Icon,
	title,
	description,
	action,
	className,
} ) {
	return (
		<div
			className={ cn(
				'flex flex-col items-center justify-center rounded-lg border border-dashed border-border px-6 py-10 text-center',
				className
			) }
		>
			{ Icon ? (
				<span className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary-soft text-primary">
					<Icon className="h-6 w-6" aria-hidden="true" />
				</span>
			) : null }
			<p className="m-0 text-sm font-semibold text-heading">{ title }</p>
			{ description ? (
				<p className="m-0 mt-1 max-w-sm text-[13px] leading-5 text-muted-foreground">
					{ description }
				</p>
			) : null }
			{ action ? <div className="mt-5">{ action }</div> : null }
		</div>
	);
}
