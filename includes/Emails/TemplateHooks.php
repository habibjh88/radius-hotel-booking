<?php
/**
 * Email template hooks.
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails
 */

namespace RadiusTheme\RadiusHotelBooking\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;

/**
 * Class TemplateHooks
 *
 * The email header/footer templates are pure hook scaffolding; everything they
 * render is attached here. Override any piece by unhooking the method and
 * hooking your own — no template needs editing.
 */
class TemplateHooks {

	/**
	 * Register the hooks.
	 *
	 * @return self
	 */
	public static function init() {
		add_action( 'rtbp_email_header', array( __CLASS__, 'email_styles' ), 10 );
		add_action( 'rtbp_email_header_content', array( __CLASS__, 'email_header_content' ), 10 );
		add_action( 'rtbp_email_before_content', array( __CLASS__, 'email_heading' ), 10, 2 );
		add_action( 'rtbp_email_button', array( __CLASS__, 'email_button' ), 10, 3 );
		add_action( 'rtbp_email_footer_content', array( __CLASS__, 'email_footer_content' ), 10 );

		return new self();
	}

	/**
	 * Inline the email stylesheet.
	 *
	 * @return void
	 */
	public static function email_styles() {
		$display   = SettingsHelper::get_setting( 'display' );
		$accent    = $display['primaryColor'] ?? '#0040ff';
		$body_bg   = '#f3f4f6';
		$body_text = '#374151';
		?>
		<style type="text/css">
			body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: <?php echo esc_attr( $body_bg ); ?>; color: <?php echo esc_attr( $body_text ); ?>; }
			table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
			img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
			.header-logo-box { background-color: <?php echo esc_attr( $accent ); ?>; border-radius: 8px; width: 56px; height: 56px; text-align: center; vertical-align: middle; }
			.header-logo-text { color: #ffffff; font-size: 24px; font-weight: 700; line-height: 56px; }
			.header-business-name { margin: 0; font-size: 18px; color: #111827; }
			.email-heading { margin: 0; font-size: 22px; color: #111827; }
			.email-table td { padding: 8px 0; font-size: 14px; border-bottom: 1px solid #f3f4f6; }
			.email-table td.label { color: #6b7280; }
			.email-table td.value { color: #111827; text-align: right; font-weight: 600; }
		</style>
		<?php
	}

	/**
	 * Render the header: logo (or initial) plus the site/company name.
	 *
	 * @return void
	 */
	public static function email_header_content() {
		$general   = SettingsHelper::get_setting( 'general' );
		$site_name = ! empty( $general['companyName'] ) ? $general['companyName'] : get_bloginfo( 'name' );
		$logo_id   = (int) ( $general['logo'] ?? 0 );
		$logo_url  = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		?>
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td align="center">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="64" height="64" style="display: block; margin: 0 auto 12px; border-radius: 8px;" />
					<?php else : ?>
						<table border="0" cellspacing="0" cellpadding="0" style="margin: 0 auto 12px;">
							<tr>
								<td class="header-logo-box">
									<span class="header-logo-text"><?php echo esc_html( substr( $site_name, 0, 1 ) ); ?></span>
								</td>
							</tr>
						</table>
					<?php endif; ?>
					<h2 class="header-business-name"><?php echo esc_html( $site_name ); ?></h2>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the email's heading, as configured in its settings.
	 *
	 * @param \RadiusTheme\RadiusHotelBooking\Abstracts\BaseEmail $email The email object.
	 * @param mixed                                              $data  The email payload.
	 *
	 * @return void
	 */
	public static function email_heading( $email, $data ) { // phpcs:ignore
		$settings = $email->get_settings();
		$heading  = $settings['heading'] ?? '';

		if ( ! $heading ) {
			return;
		}
		?>
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td align="center" style="padding-bottom: 16px;">
					<h1 class="email-heading"><?php echo esc_html( $heading ); ?></h1>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the call-to-action button when the email defines one.
	 *
	 * @param \RadiusTheme\RadiusHotelBooking\Abstracts\BaseEmail $email    The email object.
	 * @param mixed                                              $data     The email payload.
	 * @param array                                              $settings The email settings.
	 *
	 * @return void
	 */
	public static function email_button( $email, $data, $settings ) { // phpcs:ignore
		$button_text = $settings['button_text'] ?? '';
		$button_url  = $settings['button_url'] ?? '';

		if ( ! $button_text || ! $button_url ) {
			return;
		}

		$display = SettingsHelper::get_setting( 'display' );
		$accent  = $display['primaryColor'] ?? '#0040ff';
		?>
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td align="center" style="padding-bottom: 24px;">
					<table border="0" cellspacing="0" cellpadding="0">
						<tr>
							<td style="background-color: <?php echo esc_attr( $accent ); ?>; border-radius: 8px;">
								<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; color: #ffffff; padding: 12px 32px; font-weight: 500; text-decoration: none; font-size: 14px;">
									<?php echo esc_html( $button_text ); ?>
								</a>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the footer credit line.
	 *
	 * @return void
	 */
	public static function email_footer_content() {
		$general   = SettingsHelper::get_setting( 'general' );
		$site_name = ! empty( $general['companyName'] ) ? $general['companyName'] : get_bloginfo( 'name' );
		?>
		<p style="margin: 0;">
			<?php
			printf(
				/* translators: 1: year, 2: site or company name. */
				esc_html__( '&copy; %1$s %2$s. All rights reserved.', 'radius-hotel-booking' ),
				esc_html( gmdate( 'Y' ) ),
				esc_html( $site_name )
			);
			?>
		</p>
		<?php
	}
}
