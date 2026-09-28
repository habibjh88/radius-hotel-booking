<?php
/**
 * Settings access.
 *
 * Every section is declared in Settings\SettingsSchema (types, defaults and
 * limits; core sections in Settings\CoreSettings). This class is the read
 * API: `all()` lists the sections with their defaults, `get_setting()` reads
 * one section from wp_options (key: `rtbp_<section>_settings`) merged over its
 * defaults, so newly added keys are always present.
 *
 * Sections an add-on still declares only through the `rtbp_settings` defaults
 * filter (no schema) keep working: they are read the same way, and saved
 * with their own keys whitelisted (SettingsService).
 *
 * @package RadiusTheme\RadiusHotelBooking\Helpers
 */

namespace RadiusTheme\RadiusHotelBooking\Helpers;

use RadiusTheme\RadiusHotelBooking\Settings\SettingsSchema;

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
		$data = array();
		foreach ( array_keys( SettingsSchema::sections() ) as $section ) {
			$data[ $section ] = SettingsSchema::defaults( $section );
		}

		/**
		 * Filter the full settings map (section => defaults). Prefer
		 * SettingsSchema::register(), which also sanitises; sections added here
		 * without a schema are saved with their keys whitelisted only.
		 *
		 * @param array $data Section key => defaults.
		 */
		return (array) apply_filters( 'rtbp_settings', $data );
	}

	/**
	 * General settings defaults.
	 *
	 * @return array
	 */
	public static function general(): array {
		return SettingsSchema::defaults( 'general' );
	}

	/**
	 * Display settings defaults.
	 *
	 * @return array
	 */
	public static function display(): array {
		return SettingsSchema::defaults( 'display' );
	}

	/**
	 * Email settings defaults.
	 *
	 * @return array
	 */
	public static function email(): array {
		return SettingsSchema::defaults( 'email' );
	}

	/**
	 * Whether a section exists (with a schema or through the defaults filter).
	 *
	 * @param string $key Section key.
	 * @return bool
	 */
	public static function exists( string $key ): bool {
		return SettingsSchema::has( $key ) || array_key_exists( $key, self::all() );
	}

	/**
	 * Retrieve one settings section, saved values merged over the defaults.
	 * Keys no longer declared are left out.
	 *
	 * @param string $key Section key, e.g. 'general'.
	 *
	 * @return mixed
	 */
	public static function get_setting( $key ) {
		$default = SettingsSchema::has( $key ) ? SettingsSchema::defaults( $key ) : ( self::all()[ $key ] ?? array() );

		$saved = get_option( 'rtbp_' . $key . '_settings', $default );

		// Merge saved values over defaults so newly added keys are always available.
		if ( is_array( $default ) && is_array( $saved ) ) {
			return $default ? array_merge( $default, array_intersect_key( $saved, $default ) ) : $saved;
		}

		return $saved;
	}

	/**
	 * Persist one settings section as given. Callers that take user input go
	 * through SettingsService::updateSection(), which validates first.
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
