<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
</td>
</tr>
</table>
</td>
</tr>

<?php
/**
 * Hook: rtbp_email_before_footer
 */
do_action( 'rtbp_email_before_footer' );
?>

<!-- Footer Section -->
<tr>
	<td id="template_footer" style="background-color: #ffffff; border-top: 1px solid #e5e7eb; padding: 24px;">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td id="credit" style="text-align: center; color: #6b7280; font-size: 12px;">
					<?php
					/**
					 * Hook: rtbp_email_footer_content
					 *
					 * @hooked TemplateHooks::email_footer_content - 10
					 */
					do_action( 'rtbp_email_footer_content' );
					?>
				</td>
			</tr>
		</table>
	</td>
</tr>

<?php
/**
 * Hook: rtbp_email_after_footer
 */
do_action( 'rtbp_email_after_footer' );
?>

</table>
<!-- End Container -->

</td>
</tr>
</table>

<?php
/**
 * Hook: rtbp_email_after_container
 */
do_action( 'rtbp_email_after_container' );
?>

</td>
</tr>
</table>

<?php
/**
 * Hook: rtbp_email_after_wrapper
 */
do_action( 'rtbp_email_after_wrapper' );
?>

</body>
</html>