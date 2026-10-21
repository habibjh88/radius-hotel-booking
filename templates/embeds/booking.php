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
 * @var string $phone       The hotel's phone (Settings → General), may be empty.
 * @var string $layout      `steps` (one step at a time) or `onepage`.
 * @var string $display     `inline` (on the page) or `modal` (a button opens a popup).
 * @var string $button_text The popup button's text.
 * @var string $page_url     The booking page (Settings → Public booking), may be empty.
 * @var string $room_details What "Room details" opens: `page` or `popup`.
 * @var string $room_card    Room type design in the results: `classic` or `split`.
 * @var int    $rates_shown  Rates per room type before "Show more rates" (0: all).
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

defined( 'ABSPATH' ) || exit;

$layout      = $layout ?? 'steps';
$display     = $display ?? 'inline';
$button_text = $button_text ?? __( 'Book now', 'radius-hotel-booking' );
$page_url    = $page_url ?? '';
$room_details = $room_details ?? 'page';
$room_card    = $room_card ?? 'classic';
$rates_shown  = (int) ( $rates_shown ?? 4 );
?>
<?php if ( 'modal' === $display ) : ?>
	<?php // Before the app starts (or without JavaScript) the button is a link to the booking page. ?>
	<div class="rtbp-root rtbp-site-mount" data-embed="booking" data-display="modal" data-layout="<?php echo esc_attr( $layout ); ?>" data-room-details="<?php echo esc_attr( $room_details ); ?>" data-room-card="<?php echo esc_attr( $room_card ); ?>" data-rates-shown="<?php echo (int) $rates_shown; ?>" data-button-text="<?php echo esc_attr( $button_text ); ?>">
		<a class="rtbp-book-now inline-flex min-h-[44px] items-center justify-center rounded-lg bg-primary px-6 py-2.5 text-base font-semibold text-primary-foreground no-underline shadow-sm hover:bg-primary-hover hover:text-primary-foreground" href="<?php echo esc_url( '' !== $page_url ? $page_url : '#rtbp-booking' ); ?>">
			<?php echo esc_html( $button_text ); ?>
		</a>
	</div>
<?php else : ?>
	<?php // Room reserved for the app, so the page below does not jump when it loads. ?>
	<div class="rtbp-root rtbp-site-mount min-h-[36rem]" data-embed="booking" data-layout="<?php echo esc_attr( $layout ); ?>" data-room-details="<?php echo esc_attr( $room_details ); ?>" data-room-card="<?php echo esc_attr( $room_card ); ?>" data-rates-shown="<?php echo (int) $rates_shown; ?>">
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
<?php endif; ?>
