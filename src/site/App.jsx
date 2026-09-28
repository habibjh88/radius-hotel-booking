import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';

import { get } from '@/api/client';
import { cn } from '@/lib/utils';

/**
 * Public item list.
 *
 * Reads the same REST endpoint as the admin screen, but renders the published
 * items only and takes its layout from the shortcode/block attributes.
 *
 * @param {Object} props         Component props.
 * @param {string} props.layout  'grid' or 'list'.
 * @param {number} props.columns Column count for the grid layout.
 * @param {number} props.perPage Items to request.
 * @return {JSX.Element} App.
 */
export default function App( { layout = 'grid', columns = 3, perPage = 9 } ) {
	const [ items, setItems ] = useState( [] );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		get( 'items', { per_page: perPage, condition: 'status=published' } )
			.then( ( { data } ) =>
				setItems( Array.isArray( data ) ? data : [] )
			)
			.catch( () => setItems( [] ) )
			.finally( () => setLoading( false ) );
	}, [ perPage ] );

	if ( loading ) {
		return (
			<p className="rtbp-root text-sm text-muted-foreground">
				{ __( 'Loading…', 'radius-hotel-booking' ) }
			</p>
		);
	}

	if ( ! items.length ) {
		return (
			<p className="rtbp-root text-sm text-muted-foreground">
				{ __( 'Nothing to show yet.', 'radius-hotel-booking' ) }
			</p>
		);
	}

	return (
		<div
			className={ cn(
				'rtbp-root',
				layout === 'grid' ? 'grid gap-4' : 'flex flex-col gap-3'
			) }
			style={
				layout === 'grid'
					? {
							gridTemplateColumns: `repeat(${ columns }, minmax(0, 1fr))`,
					  }
					: undefined
			}
		>
			{ items.map( ( item ) => (
				<article
					key={ item.id }
					className="rounded-lg border border-border bg-card p-4 text-card-foreground"
				>
					<h3 className="m-0 text-base font-semibold">
						{ item.title }
					</h3>
					{ item.description ? (
						<p className="mt-2 text-sm text-muted-foreground">
							{ item.description }
						</p>
					) : null }
					{ Number( item.price ) > 0 ? (
						<p className="mt-2 font-medium">
							{ Number( item.price ).toFixed( 2 ) }
						</p>
					) : null }
				</article>
			) ) }
		</div>
	);
}
