<?php
/**
 * Login screen for the front-end staff dashboard.
 *
 * Override by copying to `yourtheme/radius-hotel-booking/dashboard/login.php`.
 *
 * @package RadiusTheme\RadiusHotelBooking
 *
 * @var string $page_url The dashboard page URL (the login returns here).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$rtbp_site = get_bloginfo( 'name' );
?>
<div class="rtbp-gate">
	<div class="rtbp-gate__card">
		<div class="rtbp-gate__brand">
			<span class="rtbp-gate__logo" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $rtbp_site ? $rtbp_site : 'H', 0, 1 ) ) ); ?></span>
			<div>
				<p class="rtbp-gate__site"><?php echo esc_html( $rtbp_site ); ?></p>
				<p class="rtbp-gate__tag"><?php esc_html_e( 'Hotel dashboard', 'radius-hotel-booking' ); ?></p>
			</div>
		</div>

		<h1><?php esc_html_e( 'Sign in', 'radius-hotel-booking' ); ?></h1>
		<p class="rtbp-gate__lead"><?php esc_html_e( 'Sign in with your staff account to open the front desk.', 'radius-hotel-booking' ); ?></p>

		<?php
		wp_login_form(
			array(
				'redirect'       => esc_url_raw( $page_url ),
				'label_username' => __( 'Username or e-mail', 'radius-hotel-booking' ),
				'label_password' => __( 'Password', 'radius-hotel-booking' ),
				'label_remember' => __( 'Keep me signed in', 'radius-hotel-booking' ),
				'label_log_in'   => __( 'Sign in', 'radius-hotel-booking' ),
				'remember'       => true,
			)
		);
		?>

		<p class="rtbp-gate__links">
			<a href="<?php echo esc_url( wp_lostpassword_url( $page_url ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'radius-hotel-booking' ); ?></a>
		</p>
	</div>
</div>
