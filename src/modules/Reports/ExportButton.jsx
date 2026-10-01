/**
 * Export the open report (M10, 10.14): one button with one format, a menu
 * with several (Pro adds XLSX). The server builds the file
 * (`GET reports/{name}/export`), the browser saves it. Hidden when
 * `reports.export` is locked; a passcode level is asked by the API client.
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ChevronDown, Download } from 'lucide-react';

import { get } from '@/api/client';
import { Button } from '@/components/ui/button';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAccess } from '@/lib/access';
import { toast, toastError } from '@/lib/toast';

/**
 * Save a base64 file the server sent.
 *
 * @param {Object} file `{ filename, mime, content }`.
 */
function save( file ) {
	const bytes = Uint8Array.from( window.atob( file.content ), ( c ) =>
		c.charCodeAt( 0 )
	);
	const url = URL.createObjectURL(
		new window.Blob( [ bytes ], { type: file.mime } )
	);
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = file.filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.setTimeout( () => URL.revokeObjectURL( url ), 1000 );
}

/**
 * @param {Object} props        Props.
 * @param {string} props.report Report name on the server.
 * @param {Object} props.params The report's parameters (period, mode, times …).
 * @return {JSX.Element|null} Button.
 */
export default function ExportButton( { report, params } ) {
	const level = useAccess( 'reports.export' );
	const [ busy, setBusy ] = useState( false );
	const formats = window.radius_hotel_booking_param?.export_formats || [];

	if ( 'locked' === level || ! formats.length || ! report ) {
		return null;
	}

	const run = ( format ) => {
		setBusy( true );
		get( `reports/${ report }/export`, { ...params, format } )
			.then( ( { data } ) => {
				save( data );
				toast.success(
					sprintf(
						/* translators: %s: file name. */
						__( 'Downloaded %s', 'radius-hotel-booking' ),
						data.filename
					)
				);
			} )
			.catch( toastError )
			.finally( () => setBusy( false ) );
	};

	if ( 1 === formats.length ) {
		return (
			<Button
				type="button"
				variant="outline"
				disabled={ busy }
				onClick={ () => run( formats[ 0 ].key ) }
			>
				<Download aria-hidden="true" />
				{ __( 'Export', 'radius-hotel-booking' ) }
			</Button>
		);
	}

	return (
		<DropdownMenu>
			<DropdownMenuTrigger asChild>
				<Button type="button" variant="outline" disabled={ busy }>
					<Download aria-hidden="true" />
					{ __( 'Export', 'radius-hotel-booking' ) }
					<ChevronDown aria-hidden="true" />
				</Button>
			</DropdownMenuTrigger>
			<DropdownMenuContent className="rtbp-root" align="end">
				{ formats.map( ( format ) => (
					<DropdownMenuItem
						key={ format.key }
						onSelect={ () => run( format.key ) }
					>
						{ format.label }
					</DropdownMenuItem>
				) ) }
			</DropdownMenuContent>
		</DropdownMenu>
	);
}
