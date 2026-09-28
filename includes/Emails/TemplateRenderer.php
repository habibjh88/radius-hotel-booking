<?php

/**
 * TemplateRenderer class file.
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails
 */

namespace RadiusTheme\RadiusHotelBooking\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class TemplateRenderer
 *
 * Responsible for rendering email templates with header, footer, and inlined CSS.
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails
 */
class TemplateRenderer {

	/**
	 * Renders the complete email template with header, footer, and inlined CSS.
	 *
	 * @param string $template_file The main template file to render.
	 * @param array  $args          Associative array of arguments to pass to the template.
	 *
	 * @return string The rendered HTML email content.
	 */
	public static function render( $template_file, $args = array() ) {
		extract( $args ); //phpcs:ignore

		ob_start();
		self::include_template( 'email-header.php', $args );
		self::include_template( $template_file, $args );
		self::include_template( 'email-footer.php', $args );

		$html = ob_get_clean();
		return self::inline_css( $html );
	}

	/**
	 * Includes a template file and passes arguments to it.
	 *
	 * @param string $template The template file to include.
	 * @param array  $args     Associative array of arguments to pass to the template.
	 */
	private static function include_template( $template, $args = array() ) {
		$template_path = RADIUS_HOTEL_BOOKING_TEMPLATES_DIR . $template;
		if ( ! file_exists( $template_path ) ) {
			return;
		}

		extract( $args ); //phpcs:ignore
		include $template_path;
	}

	/**
	 * Inlines CSS styles into the HTML email content.
	 *
	 * @param string $html The HTML content of the email.
	 *
	 * @return string The HTML content with inlined CSS.
	 */
	private static function inline_css( $html ) {
		ob_start();
		self::include_template( 'styles.php', array() );
		$css = ob_get_clean();

		if ( ! empty( $css ) ) {
			$html = str_replace( '</head>', '<style>' . $css . '</style></head>', $html );
		}
		return $html;
	}
}
