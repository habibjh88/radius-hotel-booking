/**
 * The card every settings tab is built from: a heading, one line of context,
 * then its fields. A tab is a stack of these.
 *
 *   <SettingsSection
 *       title={ __( 'Same-day bookings', … ) }
 *       description={ __( 'Whether guests can book for today.', … ) }
 *   >
 *       <Field label={ … } error={ errors.sameDayEnabled }>…</Field>
 *   </SettingsSection>
 *
 * Also on `window.rtbp.ui`, so add-on tabs look the same.
 */
import { cn } from '@/lib/utils';

/**
 * @param {Object}      props             Props.
 * @param {string}      props.title       Heading.
 * @param {string}      props.description One line under the heading.
 * @param {JSX.Element} props.actions     Controls on the right of the heading.
 * @param {string}      props.className   Extra classes.
 * @param {JSX.Element} props.children    Fields.
 * @return {JSX.Element} Card.
 */
export default function SettingsSection( {
	title,
	description,
	actions,
	className,
	children,
} ) {
	return (
		<section
			className={ cn(
				'min-w-0 rounded-xl border border-border bg-card',
				className
			) }
		>
			{ title || description || actions ? (
				<header className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
					<div className="min-w-0">
						{ title ? (
							<h2 className="m-0 p-0 text-[15px] font-semibold text-heading">
								{ title }
							</h2>
						) : null }
						{ description ? (
							<p className="m-0 mt-1 text-[13px] leading-5 text-muted-foreground">
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
			<div className="space-y-5 px-5 py-5">{ children }</div>
		</section>
	);
}
