<?php
/**
 * "Item created" admin email body.
 *
 * Rendered between templates/emails/email-header.php and email-footer.php by
 * Emails\TemplateRenderer. Override in a theme at
 * `radius-hotel-booking/emails/admin/item-created.php`.
 *
 * @var array  $settings   The email's settings.
 * @var object $data       The Item model that triggered the email.
 * @var array  $merge_tags Resolved merge-tag values.
 * @var object $email      The BaseEmail instance.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

do_action( 'rtbp_email_before_content', $email, $data );
?>

<table width="100%" border="0" cellspacing="0" cellpadding="0" class="email-table">
	<tr>
		<td class="label"><?php esc_html_e( 'Title', 'radius-hotel-booking' ); ?></td>
		<td class="value"><?php echo esc_html( $merge_tags['item_title'] ?? '' ); ?></td>
	</tr>
	<tr>
		<td class="label"><?php esc_html_e( 'Item ID', 'radius-hotel-booking' ); ?></td>
		<td class="value"><?php echo esc_html( $merge_tags['item_id'] ?? '' ); ?></td>
	</tr>
	<tr>
		<td class="label"><?php esc_html_e( 'Status', 'radius-hotel-booking' ); ?></td>
		<td class="value"><?php echo esc_html( $merge_tags['status'] ?? '' ); ?></td>
	</tr>
	<tr>
		<td class="label"><?php esc_html_e( 'Created', 'radius-hotel-booking' ); ?></td>
		<td class="value"><?php echo esc_html( $merge_tags['date'] ?? '' ); ?></td>
	</tr>
</table>

<?php
do_action( 'rtbp_email_button', $email, $data, $settings );
do_action( 'rtbp_email_after_content', $email, $data );
