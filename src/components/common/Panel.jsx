/**
 * Panel: the white card every dashboard and detail section sits in — a header
 * (title, optional description, optional actions) and a body.
 */
import { cn } from '@/lib/utils';

/**
 * @param {Object}      props             Props.
 * @param {string}      props.title       Section title.
 * @param {string}      props.description One line under the title.
 * @param {JSX.Element} props.actions     Controls on the right of the header.
 * @param {string}      props.className   Extra classes for the card.
 * @param {string}      props.bodyClassName Extra classes for the body.
 * @param {JSX.Element} props.children    Body.
 * @return {JSX.Element} Panel.
 */
export default function Panel( {
	title,
	description,
	actions,
	className,
	bodyClassName,
	children,
} ) {
	return (
		<section
			className={ cn(
				'flex flex-col rounded-xl border border-border bg-card shadow-sm',
				className
			) }
		>
			{ title || actions ? (
				<header className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
					<div className="min-w-0">
						{ title ? (
							<h2 className="m-0 p-0 text-base font-semibold leading-6 text-heading">
								{ title }
							</h2>
						) : null }
						{ description ? (
							<p className="m-0 mt-0.5 text-[13px] text-muted-foreground">
								{ description }
							</p>
						) : null }
					</div>
					{ actions ? (
						<div className="flex shrink-0 items-center gap-2">
							{ actions }
						</div>
					) : null }
				</header>
			) : null }
			<div className={ cn( 'flex-1 p-5', bodyClassName ) }>
				{ children }
			</div>
		</section>
	);
}
