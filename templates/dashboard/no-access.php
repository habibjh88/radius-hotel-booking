<?php
/**
 * Shown to a signed-in user who may not open the hotel dashboard.
 *
 * Override by copying to `yourtheme/radius-hotel-booking/dashboard/no-access.php`.
 *
 * @package RadiusTheme\RadiusHotelBooking
 *
 * @var string $page_url The dashboard page URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$rtbp_user = wp_get_current_user();
?>
<div class="rtbp-gate">
	<div class="rtbp-gate__card">
		<h1><?php esc_html_e( 'No access to the hotel dashboard', 'radius-hotel-booking' ); ?></h1>
		<p class="rtbp-gate__lead">
			<?php
			printf(
				/* translators: %s: the signed-in user's display name. */
				esc_html__( 'You are signed in as %s, but this account cannot open the hotel dashboard. Ask a manager to give you access.', 'radius-hotel-booking' ),
				'<strong>' . esc_html( $rtbp_user->display_name ) . '</strong>'
			);
			?>
		</p>
		<a class="rtbp-gate__button" href="<?php echo esc_url( wp_logout_url( $page_url ) ); ?>"><?php esc_html_e( 'Sign in with another account', 'radius-hotel-booking' ); ?></a>
	</div>
</div>
