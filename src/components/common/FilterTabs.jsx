/**
 * Quick-filter tabs above a list: All · Awaiting approval · Arriving today…
 * with a count on each. Scrolls sideways on phones instead of wrapping.
 *
 *   <FilterTabs
 *       value={ tab } onChange={ setTab }
 *       tabs={ [ { value: 'all', label: __( 'All', … ), count: 134 }, … ] }
 *   />
 */
import { cn } from '@/lib/utils';

/**
 * @param {Object}   props           Props.
 * @param {Array<{value: string, label: string, count?: number}>} props.tabs Tabs.
 * @param {string}   props.value     Selected tab.
 * @param {Function} props.onChange  Called with the tab value.
 * @param {string}   props.label     Accessible name.
 * @param {string}   props.className Extra classes.
 * @return {JSX.Element} Tabs.
 */
export default function FilterTabs( {
	tabs,
	value,
	onChange,
	label,
	className,
} ) {
	return (
		<div className={ cn( 'rtbp-no-scrollbar -mb-px overflow-x-auto', className ) }>
			<div
				role="tablist"
				aria-label={ label }
				className="flex min-w-max gap-1 border-b border-border"
			>
				{ tabs.map( ( tab ) => {
					const selected = tab.value === value;
					return (
						<button
							key={ tab.value }
							type="button"
							role="tab"
							aria-selected={ selected }
							onClick={ () => onChange( tab.value ) }
							className={ cn(
								'-mb-px inline-flex h-10 items-center gap-2 whitespace-nowrap border-0 border-b-2 bg-transparent px-3 text-sm transition-colors',
								selected
									? 'border-primary font-semibold text-primary'
									: 'border-transparent font-medium text-muted-foreground hover:text-heading'
							) }
						>
							{ tab.label }
							{ typeof tab.count === 'number' ? (
								<span
									className={ cn(
										'min-w-[20px] rounded-full px-1.5 text-center text-[11px] font-semibold tabular-nums',
										selected
											? 'bg-primary text-primary-foreground'
											: 'bg-muted text-muted-foreground'
									) }
								>
									{ tab.count }
								</span>
							) : null }
						</button>
					);
				} ) }
			</div>
		</div>
	);
}
