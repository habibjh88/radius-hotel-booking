/**
 * The lightbox: yet-another-react-lightbox with thumbnails at the bottom,
 * a counter, zoom and full screen; keyboard, swipe and wheel included.
 */
import { __, sprintf } from '@wordpress/i18n';
import Lightbox from 'yet-another-react-lightbox';
import Counter from 'yet-another-react-lightbox/plugins/counter';
import Fullscreen from 'yet-another-react-lightbox/plugins/fullscreen';
import Thumbnails from 'yet-another-react-lightbox/plugins/thumbnails';
import Zoom from 'yet-another-react-lightbox/plugins/zoom';
import 'yet-another-react-lightbox/styles.css';
import 'yet-another-react-lightbox/plugins/counter.css';
import 'yet-another-react-lightbox/plugins/thumbnails.css';
import './gallery.css';

/**
 * @param {Object}   props         Props.
 * @param {Object[]} props.slides  `{ src, width, height, alt, thumb, srcSet }`.
 * @param {number}   props.index   Open at this photo; -1 = closed.
 * @param {string}   props.title   Room type name (for the dialog's label).
 * @param {Function} props.onClose Called when closed.
 * @return {JSX.Element} Lightbox.
 */
export default function Viewer( { slides, index, title, onClose } ) {
	return (
		<Lightbox
			className="rtbp-lightbox"
			open={ index >= 0 }
			index={ Math.max( 0, index ) }
			close={ onClose }
			slides={ slides.map( ( slide ) => ( {
				...slide,
				alt: slide.alt || title,
			} ) ) }
			plugins={ [ Thumbnails, Counter, Zoom, Fullscreen ] }
			carousel={ { finite: slides.length < 3, preload: 2 } }
			controller={ { closeOnBackdropClick: true } }
			animation={ { fade: 250, swipe: 350 } }
			thumbnails={ {
				position: 'bottom',
				width: 104,
				height: 72,
				border: 0,
				borderRadius: 8,
				padding: 0,
				gap: 10,
				imageFit: 'cover',
				vignette: true,
			} }
			counter={ { container: { style: { top: 0, left: 0 } } } }
			zoom={ { maxZoomPixelRatio: 2, scrollToZoom: true } }
			render={ {
				thumbnail: ( { slide } ) => (
					<img
						src={ slide.thumb || slide.src }
						alt=""
						className="yarl__thumbnails_thumbnail_image"
						loading="lazy"
					/>
				),
			} }
			labels={ {
				Previous: __( 'Previous photo', 'radius-hotel-booking' ),
				Next: __( 'Next photo', 'radius-hotel-booking' ),
				Close: __( 'Close', 'radius-hotel-booking' ),
				'Zoom in': __( 'Zoom in', 'radius-hotel-booking' ),
				'Zoom out': __( 'Zoom out', 'radius-hotel-booking' ),
				'Enter Fullscreen': __( 'Full screen', 'radius-hotel-booking' ),
				'Exit Fullscreen': __(
					'Exit full screen',
					'radius-hotel-booking'
				),
				Lightbox: title
					? sprintf(
							/* translators: %s: room type name. */
							__( 'Photos of %s', 'radius-hotel-booking' ),
							title
					  )
					: __( 'Photos', 'radius-hotel-booking' ),
				Carousel: __( 'Photos', 'radius-hotel-booking' ),
				Slide: __( 'Photo', 'radius-hotel-booking' ),
				'Photo gallery': __( 'Photos', 'radius-hotel-booking' ),
				'{index} of {total}': __(
					'{index} of {total}',
					'radius-hotel-booking'
				),
			} }
		/>
	);
}
