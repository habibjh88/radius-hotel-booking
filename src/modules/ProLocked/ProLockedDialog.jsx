/**
 * A locked Pro action, opened from a menu: what it does and the Get Pro link.
 */
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import ProLockedPanel from './ProLockedPanel';

/**
 * @param {Object}      props         Props.
 * @param {Object|null} props.item    `{ label, description, icon?, features? }` (null = closed).
 * @param {Function}    props.onClose Close.
 * @return {JSX.Element} Dialog.
 */
export default function ProLockedDialog( { item, onClose } ) {
	return (
		<Dialog
			open={ Boolean( item ) }
			onOpenChange={ ( open ) => ! open && onClose() }
		>
			<DialogContent className="rtbp-root max-w-md p-0">
				<DialogTitle className="sr-only">{ item?.label }</DialogTitle>
				{ item ? <ProLockedPanel item={ item } /> : null }
			</DialogContent>
		</Dialog>
	);
}
