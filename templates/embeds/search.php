<?php
/**
 * The search bar (M04, 4.1, 4.2) — rendered the same way by `[rtbp_search]`,
 * the *Hotel search bar* block and the Elementor widget.
 *
 * The outer element is the mount node the public app takes over (`data-embed`
 * = search). Until it does — or without JavaScript — the plain form below
 * still works: it sends `arrival`, `departure`, `adults`, `children` (and
 * `rooms`) to the booking page.
 *
 * Override from a theme at `radius-hotel-booking/embeds/search.php`; keep the
 * outer element's class and `data-embed`.
 *
 * @var string $action Booking page URL ('' = this page).
 * @var array  $rules  Public booking rules (Frontend\Embeds::rules()).
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

// The first and last arrival a guest may pick — the server's own rules.
$rtbp_now   = Dates::now()->setTimezone( Dates::timezone() );
$rtbp_today = $rtbp_now->format( 'Y-m-d' );
$rtbp_min   = $rules['sameDayEnabled'] && $rtbp_now->format( 'H:i' ) < $rules['sameDayCutoff'] ? $rtbp_today : $rtbp_now->modify( '+1 day' )->format( 'Y-m-d' );
$rtbp_max   = $rules['bookingWindowDays'] ? $rtbp_now->modify( '+' . (int) $rules['bookingWindowDays'] . ' days' )->format( 'Y-m-d' ) : '';
?>
<div class="rtbp-root rtbp-site-mount" data-embed="search">
	<form class="rtbp-search-fallback flex flex-wrap items-end gap-3" method="get" action="<?php echo esc_url( $action ); ?>">
		<label class="flex flex-col gap-1 text-sm">
			<?php esc_html_e( 'Arrival', 'radius-hotel-booking' ); ?>
			<input class="rounded-md border border-input px-3 py-2" type="date" name="arrival" required value="<?php echo esc_attr( $rtbp_min ); ?>" min="<?php echo esc_attr( $rtbp_min ); ?>" <?php echo $rtbp_max ? 'max="' . esc_attr( $rtbp_max ) . '"' : ''; ?> />
		</label>
		<label class="flex flex-col gap-1 text-sm">
			<?php esc_html_e( 'Departure', 'radius-hotel-booking' ); ?>
			<input class="rounded-md border border-input px-3 py-2" type="date" name="departure" required value="<?php echo esc_attr( $rtbp_min ); ?>" min="<?php echo esc_attr( $rtbp_min ); ?>" />
		</label>
		<label class="flex flex-col gap-1 text-sm">
			<?php esc_html_e( 'Adults', 'radius-hotel-booking' ); ?>
			<input class="w-20 rounded-md border border-input px-3 py-2" type="number" name="adults" min="1" max="20" value="<?php echo esc_attr( (string) $rules['defaultAdults'] ); ?>" />
		</label>
		<label class="flex flex-col gap-1 text-sm">
			<?php esc_html_e( 'Children', 'radius-hotel-booking' ); ?>
			<input class="w-20 rounded-md border border-input px-3 py-2" type="number" name="children" min="0" max="10" value="0" />
		</label>
		<?php if ( $rules['showRoomsField'] ) : ?>
			<label class="flex flex-col gap-1 text-sm">
				<?php esc_html_e( 'Rooms', 'radius-hotel-booking' ); ?>
				<input class="w-20 rounded-md border border-input px-3 py-2" type="number" name="rooms" min="1" max="10" value="1" />
			</label>
		<?php endif; ?>
		<button class="rounded-md bg-primary px-5 py-2 font-semibold text-primary-foreground" type="submit">
			<?php esc_html_e( 'Find a room', 'radius-hotel-booking' ); ?>
		</button>
	</form>
</div>
