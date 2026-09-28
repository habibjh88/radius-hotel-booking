<?php
/**
 * Full-screen page for the front-end staff dashboard.
 *
 * Used instead of the theme's template for any page containing
 * [rtbp_dashboard] (Frontend\DashboardPage::template()). Override it by
 * copying it to `yourtheme/radius-hotel-booking/dashboard/app-page.php`.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
wp_body_open();

// The shortcode decides: login form, no-access message, or the app mount node.
echo RadiusTheme\RadiusHotelBooking\Frontend\DashboardPage::shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the templates / static markup.

// Not wp_footer(): other plugins must not inject markup into the app.
RadiusTheme\RadiusHotelBooking\Frontend\DashboardPage::footer();
?>
</body>
</html>
