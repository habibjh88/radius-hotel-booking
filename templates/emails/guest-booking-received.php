<?php
/**
 * Guest e-mail: booking received, with how to pay (M05, 5.2, 5.7).
 *
 * @var \RadiusTheme\RadiusHotelBooking\Abstracts\BaseEmail $email      The e-mail.
 * @var array $data `{ intro, rooms, total, paid, due, deadline, instructions, reference, page_url, invoice }`.
 * @var array $settings   The e-mail's settings.
 * @var array $merge_tags Merge tags.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

defined( 'ABSPATH' ) || exit;

$rtbp_color = sanitize_hex_color( (string) rtbp_setting( 'display', 'primaryColor', '#1d4ed8' ) );
$rtbp_color = $rtbp_color ? $rtbp_color : '#1d4ed8';

do_action( 'rtbp_email_before_content', $email, $data );
?>
<p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #111827;"><?php echo esc_html( $data['intro'] ); ?></p>

<?php if ( ! empty( $data['rooms'] ) ) : ?>
<table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse: collapse; margin: 0 0 16px;">
	<?php foreach ( $data['rooms'] as $rtbp_room ) : ?>
	<tr>
		<td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 14px; color: #111827;">
			<?php
			/* translators: 1: room number, 2: rate name. */
			echo esc_html( sprintf( __( 'Room %1$s · %2$s', 'radius-hotel-booking' ), $rtbp_room['room'], $rtbp_room['rate'] ) );
			?>
			<br /><span style="font-size: 13px; color: #6b7280;"><?php echo esc_html( $rtbp_room['start'] . ' → ' . $rtbp_room['end'] ); ?></span>
		</td>
		<td align="right" style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 14px; color: #111827;"><?php echo esc_html( $rtbp_room['total'] ); ?></td>
	</tr>
	<?php endforeach; ?>
	<tr>
		<td style="padding: 10px 0 4px; font-size: 14px; font-weight: bold; color: #111827;"><?php esc_html_e( 'Total', 'radius-hotel-booking' ); ?></td>
		<td align="right" style="padding: 10px 0 4px; font-size: 14px; font-weight: bold; color: #111827;"><?php echo esc_html( $data['total'] ); ?></td>
	</tr>
	<?php if ( ! empty( $data['paid'] ) ) : ?>
	<tr>
		<td style="padding: 4px 0; font-size: 14px; color: #374151;"><?php esc_html_e( 'Paid', 'radius-hotel-booking' ); ?></td>
		<td align="right" style="padding: 4px 0; font-size: 14px; color: #374151;"><?php echo esc_html( $data['paid'] ); ?></td>
	</tr>
	<?php endif; ?>
	<?php if ( ! empty( $data['due'] ) ) : ?>
	<tr>
		<td style="padding: 4px 0; font-size: 15px; font-weight: bold; color: #b45309;"><?php esc_html_e( 'To pay', 'radius-hotel-booking' ); ?></td>
		<td align="right" style="padding: 4px 0; font-size: 15px; font-weight: bold; color: #b45309;"><?php echo esc_html( $data['due'] ); ?></td>
	</tr>
	<?php endif; ?>
</table>
<?php endif; ?>

<?php if ( ! empty( $data['instructions'] ) ) : ?>
	<h2 style="margin: 24px 0 8px; font-size: 16px; color: #111827;"><?php esc_html_e( 'How to pay', 'radius-hotel-booking' ); ?></h2>
	<?php if ( ! empty( $data['deadline'] ) ) : ?>
	<p style="margin: 0 0 12px; padding: 10px 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; font-size: 14px; color: #92400e;">
		<?php
		/* translators: %s: date and time. */
		echo esc_html( sprintf( __( 'Please pay before %s.', 'radius-hotel-booking' ), $data['deadline'] ) );
		?>
	</p>
	<?php endif; ?>
	<p style="margin: 0 0 12px; font-size: 14px; color: #111827;">
		<?php
		/* translators: %s: booking reference. */
		echo wp_kses( sprintf( __( 'Quote the reference %s with your payment.', 'radius-hotel-booking' ), '<strong>' . esc_html( $data['reference'] ) . '</strong>' ), array( 'strong' => array() ) );
		?>
	</p>
	<?php foreach ( $data['instructions'] as $rtbp_method ) : ?>
	<table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse: collapse; margin: 0 0 10px; border: 1px solid #e5e7eb;">
		<tr><td style="padding: 10px 12px; font-size: 14px; color: #111827;">
			<strong><?php echo esc_html( $rtbp_method['label'] ); ?></strong>
			<?php if ( '' !== $rtbp_method['instructions'] ) : ?>
				<br /><span style="white-space: pre-line;"><?php echo esc_html( $rtbp_method['instructions'] ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $rtbp_method['account'] ) : ?>
				<br /><span style="font-family: monospace; white-space: pre-line; color: #374151;"><?php echo esc_html( $rtbp_method['account'] ); ?></span>
			<?php endif; ?>
		</td></tr>
	</table>
	<?php endforeach; ?>
<?php endif; ?>

<p style="margin: 24px 0 8px;">
	<a href="<?php echo esc_url( $data['page_url'] ); ?>" style="display: inline-block; padding: 10px 18px; background: <?php echo esc_attr( $rtbp_color ); ?>; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: bold;"><?php esc_html_e( 'See your booking', 'radius-hotel-booking' ); ?></a>
</p>
<?php if ( ! empty( $data['invoice'] ) ) : ?>
<p style="margin: 0 0 16px; font-size: 14px;">
	<a href="<?php echo esc_url( $data['invoice']['url'] ); ?>" style="color: <?php echo esc_attr( $rtbp_color ); ?>;">
	<?php
	/* translators: %s: invoice number. */
	echo esc_html( sprintf( __( 'Your invoice %s', 'radius-hotel-booking' ), $data['invoice']['number'] ) );
	?>
	</a>
</p>
<?php endif; ?>
<?php
do_action( 'rtbp_email_after_content', $email, $data );
