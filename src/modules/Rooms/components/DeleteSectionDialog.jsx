/**
 * Delete an amenity section. When it holds amenities, they move to the
 * section chosen here (nothing is lost, room types keep them).
 */
import { useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { AlertTriangle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { toast, toastError } from '@/lib/toast';
import { useDeleteAmenitySection } from '../api';

/**
 * @param {Object}      props          Props.
 * @param {Object|null} props.section  The section to delete (`items`), or null (closed).
 * @param {Object[]}    props.sections Every section.
 * @param {Function}    props.onClose  Close.
 * @return {JSX.Element} Dialog.
 */
export default function DeleteSectionDialog( { section, sections, onClose } ) {
	const remove = useDeleteAmenitySection();
	const others = sections.filter( ( s ) => s.id !== section?.id );
	const [ target, setTarget ] = useState( '' );
	const count = section?.items?.length ?? 0;

	useEffect( () => {
		setTarget( others[ 0 ] ? String( others[ 0 ].id ) : '' );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ section?.id ] );

	const confirm = () =>
		remove.mutate(
			{ id: section.id, moveTo: count ? Number( target ) : 0 },
			{
				onSuccess: ( data ) => {
					toast.success( data.message );
					onClose();
				},
				onError: ( e ) => toastError( e ),
			}
		);

	const blocked = count > 0 && ! others.length;

	return (
		<Dialog
			open={ Boolean( section ) }
			onOpenChange={ ( o ) => ! o && onClose() }
		>
			<DialogContent className="max-w-md">
				<DialogHeader>
					<DialogTitle>
						{ sprintf(
							/* translators: %s: section name. */
							__(
								'Delete the section %s?',
								'radius-hotel-booking'
							),
							section?.name ?? ''
						) }
					</DialogTitle>
					<DialogDescription>
						{ count
							? sprintf(
									/* translators: %d: number of amenities. */
									_n(
										'Its %d amenity moves to the section you choose. Room types keep it.',
										'Its %d amenities move to the section you choose. Room types keep them.',
										count,
										'radius-hotel-booking'
									),
									count
							  )
							: __(
									'It has no amenities.',
									'radius-hotel-booking'
							  ) }
					</DialogDescription>
				</DialogHeader>
				{ count && others.length ? (
					<div className="space-y-1.5">
						<span className="block text-sm font-semibold text-heading">
							{ __( 'Move them to', 'radius-hotel-booking' ) }
						</span>
						<Select value={ target } onValueChange={ setTarget }>
							<SelectTrigger className="h-11 bg-white">
								<SelectValue />
							</SelectTrigger>
							<SelectContent className="rtbp-root">
								{ others.map( ( s ) => (
									<SelectItem
										key={ s.id }
										value={ String( s.id ) }
									>
										{ s.name }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>
					</div>
				) : null }
				{ blocked ? (
					<p className="m-0 flex items-start gap-2 text-sm text-destructive">
						<AlertTriangle
							className="mt-0.5 h-4 w-4 shrink-0"
							aria-hidden="true"
						/>
						{ __(
							'This is the only section. Add another section first, so its amenities have somewhere to go.',
							'radius-hotel-booking'
						) }
					</p>
				) : null }
				<DialogFooter>
					<Button type="button" variant="outline" onClick={ onClose }>
						{ __( 'Go back', 'radius-hotel-booking' ) }
					</Button>
					<Button
						type="button"
						variant="destructive"
						disabled={ blocked || remove.isPending }
						onClick={ confirm }
					>
						{ __( 'Delete section', 'radius-hotel-booking' ) }
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	);
}
