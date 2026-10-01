<?php
/**
 * The guest booking flow (M04, 4.3–4.10) — rendered the same way by
 * `[rtbp_booking]`, the *Hotel booking* block and the Elementor widget.
 *
 * The outer element is the mount node the public app takes over
 * (`data-embed` = booking); it reads the search from the URL the search bar
 * sent. The message inside shows only until the app starts — or for a
 * visitor without JavaScript, who is asked to call.
 *
 * Override from a theme at `radius-hotel-booking/embeds/booking.php`; keep the
 * outer element's class and `data-embed`.
 *
 * @var string $phone The hotel's phone (Settings → General), may be empty.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

defined( 'ABSPATH' ) || exit;
?>
<?php // Room reserved for the app, so the page below does not jump when it loads. ?>
<div class="rtbp-root rtbp-site-mount min-h-[36rem]" data-embed="booking">
	<p class="rtbp-booking-fallback text-sm text-muted-foreground">
		<?php
		if ( '' !== $phone ) {
			printf(
				/* translators: %s: the hotel's phone number. */
				esc_html__( 'Loading the booking form… If it does not appear, please call us on %s to book.', 'radius-hotel-booking' ),
				esc_html( $phone )
			);
		} else {
			esc_html_e( 'Loading the booking form…', 'radius-hotel-booking' );
		}
		?>
	</p>
</div>
