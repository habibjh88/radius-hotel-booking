<?php
/**
 * Core functions.
 *
 * Global procedural helpers, loaded on every request. Everything here is
 * namespaced by the `rtbp_` prefix and guarded so an add-on plugin can never
 * fatal on a redeclare.
 *
 * @package RadiusTheme\RadiusHotelBooking
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;

/**
 * Load a specified template file with optional arguments.
 *
 * This function locates a template file, applies filters for customization,
 * validates its existence, and allows passing arguments to the template scope.
 * It also triggers actions before and after the template file inclusion.
 *
 * @param string $template_name The name of the template to be loaded, with or without `.php` extension.
 * @param array $args Optional. An associative array of arguments to pass to the template. Default empty array.
 * @param string $template_path Optional. custom template path. Default ''.
 * @param string $default_path Optional. Default template path to use if template not found. Default ''.
 *
 * @return void
 */
function rtbp_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {

	if ( false === strpos( $template_name, '.php' ) ) {
		$template_name .= '.php';
	}

	$template = rtbp_locate_template( $template_name, $template_path, $default_path );

	// Allow 3rd party plugin filter template file from their plugin.
	$filter_template = apply_filters( 'rtbp_get_template', $template, $template_name, $args, $template_path, $default_path );

	if ( $filter_template !== $template ) {
		if ( ! file_exists( $filter_template ) ) {
			/* translators: %s template */
			_doing_it_wrong(
				__FUNCTION__,
				sprintf(
				/* translators: %s template */
					esc_html__( '%s does not exist.', 'radius-hotel-booking' ),
					'<code>' . esc_html( $filter_template ) . '</code>'
				),
				'1.0'
			);
			return;
		}
		$template = $filter_template;
	}

	$action_args = array(
		'template_name' => $template_name,
		'template_path' => $template_path,
		'located'       => $template,
		'args'          => $args,
	);

	if ( ! empty( $args ) && is_array( $args ) ) {
		if ( isset( $args['action_args'] ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				esc_html__( 'action_args should not be overwritten when calling rtbp_get_template.', 'radius-hotel-booking' ),
				'1.0.0'
			);
			unset( $args['action_args'] );
		}
		extract( $args ); // @codingStandardsIgnoreLine
	}

	do_action( 'rtbp_before_template_part', $action_args['template_name'], $action_args['template_path'], $action_args['located'], $action_args['args'] );

	include $action_args['located'];

	do_action( 'rtbp_after_template_part', $action_args['template_name'], $action_args['template_path'], $action_args['located'], $action_args['args'] );
}


/**
 * Locate a template file.
 *
 * @param string $template_name The name of the template file to locate.
 * @param string $template_path Optional. The path to search within. Default is determined by the 'rtbp_template_path' filter.
 * @param string $default_path Optional. The default path to search if the template is not found in the given path. Default is determined by the 'rtbp_template_path' filter and 'RADIUS_HOTEL_BOOKING_PATH'.
 *
 * @return string The full path to the located template file.
 */
function rtbp_locate_template( $template_name, $template_path = '', $default_path = '' ) {
	if ( ! $template_path ) {
		$template_path = apply_filters( 'rtbp_template_path', 'radius-hotel-booking/' );
	}

	if ( ! $default_path ) {
		$default_path = apply_filters( 'rtbp_template_path', RADIUS_HOTEL_BOOKING_PATH ) . '/templates/';
	}

	if ( empty( $template ) ) {
		$template = locate_template(
			array(
				trailingslashit( $template_path ) . $template_name,
				$template_name,
			)
		);
	}

	if ( ! isset( $template ) || ! $template ) {
		$template = trailingslashit( $default_path ) . $template_name;
	}

	// Return what we found.
	return apply_filters( 'rtbp_locate_template', $template, $template_name, $template_path );
}


/**
 * Locate and load a template part.
 *
 * This function attempts to locate a template part by its slug and optionally by name.
 * It searches in the child theme, parent theme, and a plugin-defined path as a fallback.
 * If a template is found, it is loaded.
 *
 * @param string $slug The slug name for the generic template.
 * @param string $name Optional. The name of the specialized template. Default is an empty string.
 *
 * @return void
 */
function rtbp_locate_template_part( $slug, $name = '' ) {
	if ( $name ) {
		$template = locate_template(
			array(
				"{$slug}-{$name}.php",
				rtbp_template_path() . "{$slug}-{$name}.php",
			)
		);
		if ( ! $template ) {
			$fallback = RADIUS_HOTEL_BOOKING_PATH . "/templates/{$slug}-{$name}.php";
			$template = file_exists( $fallback ) ? $fallback : '';
		}
	}
	$template = apply_filters( 'rtbp_get_template_part', $template, $slug, $name );

	if ( $template ) {
		load_template( $template, false );
	}
}


/**
 * Set a cookie with the given parameters if headers have not been sent.
 *
 * @param string $name The name of the cookie.
 * @param string $value The value of the cookie.
 * @param int $expire The time the cookie expires. Defaults to 0 (session cookie).
 * @param bool $secure Whether the cookie should only be transmitted over a secure HTTPS connection. Defaults to false.
 * @param bool $httponly Whether the cookie is accessible only through the HTTP protocol. Defaults to false.
 *
 * @return void
 */
function rtbp_setcookie( $name, $value, $expire = 0, $secure = false, $httponly = false ) {
	if ( ! headers_sent() ) {
		$options = array(
			'expires'  => $expire,
			'secure'   => $secure,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN,
			'httponly' => $httponly,
		);
		setcookie( $name, $value, $options );
	}
}


/**
 * Check if the site URL is using HTTPS.
 *
 * @return bool
 */
function rtbp_site_is_https() {
	return false !== strstr( get_option( 'home' ), 'https:' );
}


/**
 * Generate a help tip element with provided text.
 *
 * @param string $tip The help tip text to be displayed.
 *
 * @return string The generated HTML for the help tip element.
 */
function rtbp_help_tip( $tip ) {
	$sanitized_tip = esc_attr( $tip );
	return apply_filters( 'rtbp_help_tip', '<span class="rtbp-help-tip" tabindex="0" aria-label="' . $sanitized_tip . '" data-tip="' . $sanitized_tip . '"></span>', $sanitized_tip, $tip );
}


/**
 * Retrieve the template path for the Radius Hotel Booking plugin.
 *
 * This function applies a filter to allow customization of the template path.
 *
 * @return string The template path for the Radius Hotel Booking plugin.
 */
function rtbp_template_path() {
	return apply_filters( 'rtbp_template_path', 'radius-hotel-booking/' );
}

/**
 * Whether the paid add-on plugin is loaded.
 *
 * Mirrors the free/pro split: the add-on defines its version constant, this
 * plugin only ever checks for it. No licence logic lives here.
 *
 * BOILERPLATE: rename the constant to your add-on's.
 *
 * @return bool
 */
function rtbp_addon_active(): bool {
	return defined( 'RADIUS_HOTEL_BOOKING_PRO_VERSION' );
}

/**
 * Read one settings section.
 *
 * Shorthand for SettingsHelper::get_setting() so templates and hooks don't
 * need the class import.
 *
 * @param string $key Section key, e.g. 'general'.
 *
 * @return mixed
 */
function rtbp_get_setting( string $key ) {
	return SettingsHelper::get_setting( $key );
}

/**
 * Read one setting, with its default applied.
 *
 * Use this rather than get_option(), so a fresh install that never opened
 * Settings still gets the schema's defaults. Example:
 * `rtbp_setting( 'booking', 'sameDayCutoff' )` returns '14:00' until changed.
 *
 * @param string $section Section key, e.g. 'general'.
 * @param string $key     Key within the section, e.g. 'currencyCode'.
 * @param mixed  $default Returned when the section or key does not exist.
 *
 * @return mixed
 */
function rtbp_setting( string $section, string $key, $default = null ) {
	$values = SettingsHelper::get_setting( $section );
	return is_array( $values ) && array_key_exists( $key, $values ) ? $values[ $key ] : $default;
}

/**
 * Get the URL of the current request.
 *
 * @return string
 */
function rtbp_get_current_url(): string {
	global $wp;

	// The referer is more reliable during AJAX requests.
	if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		return esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
	}

	return home_url( add_query_arg( array(), $wp->request ) );
}

/**
 * Get the class basename of an object or fully qualified class name.
 *
 * @param object|string $class_name Object instance or FQCN.
 *
 * @return string
 */
function rtbp_class_basename( $class_name ): string {
	if ( is_object( $class_name ) ) {
		$class_name = get_class( $class_name );
	}

	return basename( str_replace( '\\', '/', $class_name ) );
}

/**
 * Record an activity event (M14).
 *
 * Builds a normalised event (actor, IP, user agent, subject, only the changed
 * before/after values with secrets masked) and fires `rtbp_activity`. The free
 * plugin stores nothing; the Pro activity log stores it. Example:
 *
 *     rtbp_activity( 'bookings.approve', $booking, array(
 *         'before'      => array( 'status' => 'pending' ),
 *         'after'       => array( 'status' => 'confirmed' ),
 *         'description' => sprintf( __( 'Approved booking %s', 'radius-hotel-booking' ), $booking->reference ),
 *     ) );
 *
 * Call it inside the service method, after the write succeeded.
 *
 * @param string                                          $action  Action key (in ActionCatalog).
 * @param \RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel|array|null $subject The record, `[ type, id, label ]`, or null.
 * @param array                                           $context `before`, `after`, `description`, `actor`, extras.
 * @return array The event as fired.
 */
function rtbp_activity( string $action, $subject = null, array $context = array() ): array {
	return \RadiusTheme\RadiusHotelBooking\ActivityLog\Activity::emit( $action, $subject, $context );
}

if ( ! function_exists( 'rtbp_table_prefix' ) ) {
	/**
	 * Table prefix for the plugin's own tables.
	 *
	 * `$wpdb->prefix . 'radius_hotel_booking_'`, unless the site defines
	 * RADIUS_HOTEL_BOOKING_TABLE_PREFIX to override it wholesale.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return string
	 */
	function rtbp_table_prefix(): string {
		global $wpdb;

		$prefix = $wpdb->prefix . 'radius_hotel_booking_';

		if ( defined( 'RADIUS_HOTEL_BOOKING_TABLE_PREFIX' ) && RADIUS_HOTEL_BOOKING_TABLE_PREFIX ) {
			$prefix = RADIUS_HOTEL_BOOKING_TABLE_PREFIX;
		}

		return $prefix;
	}
}

/**
 * Prefixed table name for one of the plugin's tables.
 *
 * @param string $table Table name without prefix, e.g. 'items'.
 *
 * @return string
 */
function rtbp_table( string $table ): string {
	return rtbp_table_prefix() . $table;
}
