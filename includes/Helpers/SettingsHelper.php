<?php
/**
 * Default settings configuration.
 *
 * Every settings section the plugin knows about is declared here as a method
 * returning its defaults. `all()` assembles them; `get_setting()` reads one
 * section from wp_options (key: `rtbp_<section>_settings`) merged over its
 * defaults, so newly added keys are always present.
 *
 * BOILERPLATE: add a method per section, register it in all(), and the REST
 * settings endpoints + React settings page pick it up with no further wiring.
 *
 * @package RadiusTheme\RadiusHotelBooking\Helpers
 */

namespace RadiusTheme\RadiusHotelBooking\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class SettingsHelper
 */
class SettingsHelper {

	/**
	 * Every settings section with its defaults.
	 *
	 * @return array
	 */
	public static function all(): array {
		$data = array(
			'general' => self::general(),
			'display' => self::display(),
			'email'   => self::email(),
		);

		/**
		 * Filter the full settings map. Add-ons register their own sections here.
		 *
		 * @param array $data Section key => defaults.
		 */
		return apply_filters( 'rtbp_settings', $data );
	}

	/**
	 * General settings defaults.
	 *
	 * @return array
	 */
	public static function general(): array {
		return array(
			'companyName'  => get_bloginfo( 'name' ),
			'contactEmail' => get_option( 'admin_email' ),
			'dateFormat'   => 'm/d/Y',
			'timeSystem'   => '12h',
			'perPage'      => 15,
			'enableDebug'  => false,
		);
	}

	/**
	 * Display settings defaults.
	 *
	 * @return array
	 */
	public static function display(): array {
		return array(
			'primaryColor' => '#0040ff',
			'cardRadius'   => '0.75rem',
			'layout'       => 'grid',
			'columns'      => 3,
		);
	}

	/**
	 * Email settings defaults.
	 *
	 * @return array
	 */
	public static function email(): array {
		return array(
			'enabled'      => true,
			'senderName'   => get_bloginfo( 'name' ),
			'senderEmail'  => get_option( 'admin_email' ),
			'replyToEmail' => '',
			'use_queue'    => false,
		);
	}

	/**
	 * Retrieve one settings section, saved values merged over the defaults.
	 *
	 * @param string $key Section key, e.g. 'general'.
	 *
	 * @return mixed
	 */
	public static function get_setting( $key ) {
		if ( method_exists( static::class, $key ) ) {
			$default = self::$key();
		} else {
			// Fallback to the filtered defaults, so add-on sections resolve too.
			$all_defaults = self::all();
			$default      = $all_defaults[ $key ] ?? array();
		}

		$option_key = 'rtbp_' . $key . '_settings';
		$saved      = get_option( $option_key, $default );

		// Merge saved values over defaults so newly added keys are always available.
		if ( is_array( $default ) && is_array( $saved ) ) {
			return wp_parse_args( $saved, $default );
		}

		return $saved;
	}

	/**
	 * Persist one settings section.
	 *
	 * @param string $key   Section key.
	 * @param mixed  $value Section value.
	 *
	 * @return bool
	 */
	public static function update_setting( string $key, $value ): bool {
		return (bool) update_option( 'rtbp_' . $key . '_settings', $value );
	}
}
