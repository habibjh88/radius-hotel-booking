/**
 * "Notes and history" for any record (R1-T14): a button that opens a side
 * drawer with the record's notes (when its type takes notes) and the panels
 * add-ons register on `rtbp.record.panels` — Pro adds History, who changed
 * what and when, from the activity log.
 *
 *   <RecordHistory type="room_type" id={ type.id } title={ type.name } notes />
 *
 * Panels are `{ key, label, order, render() }`, like `rtbp.booking.panels`.
 * Nothing to show (no notes, no add-on panel) → no button.
 */
import { Suspense, lazy, useMemo, useState } from 'react';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { History } from 'lucide-react';

import FilterTabs from '@/components/common/FilterTabs';
import { Button } from '@/components/ui/button';
import {
	Sheet,
	SheetContent,
	SheetDescription,
	SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useAccess } from '@/lib/access';

const NotesPanel = lazy( () => import( '@/components/common/NotesPanel' ) );

/**
 * @param {Object}  props       Props.
 * @param {string}  props.type  Record type (`room_type`, `room`, `floor`, `rate_plan`, …).
 * @param {number}  props.id    Record id.
 * @param {string}  props.title Drawer title (the record's name).
 * @param {boolean} props.notes The type takes notes (registered on `rtbp_note_types`).
 * @param {string}  props.size  Button size.
 * @return {JSX.Element|null} Button and drawer.
 */
export default function RecordHistory( {
	type,
	id,
	title,
	notes = false,
	size = 'sm',
} ) {
	const [ open, setOpen ] = useState( false );
	const [ tab, setTab ] = useState( '' );
	// Notes on rooms are read with the rooms page key.
	const canReadNotes = useAccess( 'page.rooms' ) !== 'locked';

	const panels = useMemo( () => {
		const base =
			notes && canReadNotes
				? [
						{
							key: 'notes',
							label: __( 'Notes', 'radius-hotel-booking' ),
							order: 10,
							render: () => (
								<NotesPanel type={ type } id={ id } />
							),
						},
				  ]
				: [];
		const filtered = applyFilters( 'rtbp.record.panels', base, {
			type,
			id,
		} );
		return ( Array.isArray( filtered ) ? filtered : base )
			.filter( ( panel ) => panel?.key && panel.render )
			.sort( ( a, b ) => ( a.order || 50 ) - ( b.order || 50 ) );
	}, [ type, id, notes, canReadNotes ] );

	if ( ! id || ! panels.length ) {
		return null;
	}
	const active = panels.find( ( panel ) => panel.key === tab ) || panels[ 0 ];

	return (
		<>
			<Button
				type="button"
				variant="outline"
				size={ size }
				onClick={ ( event ) => {
					event.stopPropagation();
					setOpen( true );
				} }
			>
				<History aria-hidden="true" />
				{ notes
					? __( 'Notes and history', 'radius-hotel-booking' )
					: __( 'History', 'radius-hotel-booking' ) }
			</Button>
			<Sheet open={ open } onOpenChange={ setOpen }>
				<SheetContent
					side="right"
					overlayClassName="bg-black/30"
					className="rtbp-root flex w-full flex-col gap-0 p-0 sm:max-w-xl"
				>
					<div className="space-y-1 border-b border-border px-5 py-4 pr-14">
						<SheetTitle className="m-0 text-lg font-bold text-heading">
							{ title || __( 'History', 'radius-hotel-booking' ) }
						</SheetTitle>
						<SheetDescription className="m-0 text-sm text-muted-foreground">
							{ __(
								'Who changed what, and when.',
								'radius-hotel-booking'
							) }
						</SheetDescription>
					</div>
					{ panels.length > 1 ? (
						<div className="px-5 pt-3">
							<FilterTabs
								tabs={ panels.map( ( panel ) => ( {
									value: panel.key,
									label: panel.label,
								} ) ) }
								value={ active.key }
								onChange={ setTab }
								label={ __(
									'Notes and history',
									'radius-hotel-booking'
								) }
							/>
						</div>
					) : null }
					<div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">
						<Suspense
							fallback={ <Skeleton className="h-40 w-full" /> }
						>
							{ active.render() }
						</Suspense>
					</div>
				</SheetContent>
			</Sheet>
		</>
	);
}
