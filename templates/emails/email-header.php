<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta content="width=device-width, initial-scale=1.0" name="viewport">
	<title><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></title>
	<?php
	/**
	 * Hook: rtbp_email_header
	 *
	 * @hooked TemplateHooks::email_styles - 10
	 */
	do_action( 'rtbp_email_header' );
	?>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;">

<?php
/**
 * Hook: rtbp_email_before_wrapper
 */
do_action( 'rtbp_email_before_wrapper' );
?>

<!-- Main Container Table -->
<table id="wrapper" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6;">
	<tr>
		<td align="center" style="padding: 20px 0;">

			<?php
			/**
			 * Hook: rtbp_email_before_container
			 */
			do_action( 'rtbp_email_before_container' );
			?>

			<!-- Content Wrapper -->
			<table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6;">
				<tr>
					<td style="padding: 24px;">

						<!-- White Card Container -->
						<table id="template_container" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">

							<?php
							/**
							 * Hook: rtbp_email_before_header
							 */
							do_action( 'rtbp_email_before_header' );
							?>

							<!-- Header Section -->
							<tr>
								<td id="template_header" style="background: linear-gradient(to right, #2563eb, #1d4ed8); background-color: #2563eb; padding: 24px; text-align: center; border-radius: 8px 8px 0 0;">
									<?php
									/**
									 * Hook: rtbp_email_header_content
									 *
									 * @hooked TemplateHooks::email_header_content - 10
									 */
									do_action( 'rtbp_email_header_content' );
									?>
								</td>
							</tr>

							<?php
							/**
							 * Hook: rtbp_email_after_header
							 */
							do_action( 'rtbp_email_after_header' );
							?>

							<!-- Main Content Section -->
							<tr>
								<td id="body_content" style="background-color: #ffffff;">
									<table width="100%" border="0" cellspacing="0" cellpadding="0">
										<tr>
											<td id="body_content_inner" style="padding: 24px;">