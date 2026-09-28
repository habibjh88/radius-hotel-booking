<?php
/**
 * Settings service.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services
 */

namespace RadiusTheme\RadiusHotelBooking\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;
use RadiusTheme\RadiusHotelBooking\Settings\SettingsSchema;
use RadiusTheme\RadiusHotelBooking\Settings\SettingsValidator;
use RadiusTheme\RadiusHotelBooking\Support\Money;

/**
 * The only write path for settings: merges a partial update over the saved
 * section, validates it against the schema, saves it, and announces the change
 * with `rtbp_settings_updated` (the activity log, M14, listens there).
 */
class SettingsService {

	/**
	 * All sections the user may see, sensitive keys removed.
	 *
	 * @return array Section => values.
	 */
	public function all(): array {
		$out = array();
		foreach ( array_keys( SettingsHelper::all() ) as $section ) {
			$out[ $section ] = $this->section( $section );
		}
		return $out;
	}

	/**
	 * One section, sensitive keys removed.
	 *
	 * @param string $section Section key.
	 * @return array|null Null when the section does not exist.
	 */
	public function section( string $section ): ?array {
		if ( ! SettingsHelper::exists( $section ) ) {
			return null;
		}
		return SettingsSchema::strip_sensitive( $section, (array) SettingsHelper::get_setting( $section ) );
	}

	/**
	 * Update some keys of a section.
	 *
	 * @param string $section Section key.
	 * @param array  $input   Key => new value (other keys are kept).
	 * @return array The section after saving, sensitive keys removed.
	 * @throws DomainException When the section is unknown (404) or a value is invalid (422, with field errors).
	 */
	public function updateSection( string $section, array $input ): array {
		$before = $this->raw( $section );

		$schema = SettingsSchema::section( $section );
		if ( null !== $schema ) {
			list( $clean, $errors ) = SettingsValidator::validate( $schema, $input );
			if ( $errors ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw new DomainException( 'invalid_settings', __( 'Please correct the highlighted fields.', 'radius-hotel-booking' ), 422, $errors );
			}
		} else {
			// No schema (an add-on's defaults-only section): keep only known keys.
			// The add-on sanitises its own option (e.g. pre_update_option_*).
			$clean = array_intersect_key( $input, $before );
		}

		return $this->save( $section, $before, array_merge( $before, $clean ) );
	}

	/**
	 * Restore a section's defaults.
	 *
	 * @param string $section Section key.
	 * @return array The section after the reset, sensitive keys removed.
	 * @throws DomainException When the section is unknown.
	 */
	public function resetSection( string $section ): array {
		$before   = $this->raw( $section );
		$defaults = SettingsSchema::has( $section ) ? SettingsSchema::defaults( $section ) : ( SettingsHelper::all()[ $section ] ?? array() );
		return $this->save( $section, $before, (array) $defaults );
	}

	/**
	 * The section's current values including sensitive keys.
	 *
	 * @param string $section Section key.
	 * @return array
	 * @throws DomainException When the section is unknown.
	 */
	private function raw( string $section ): array {
		if ( ! SettingsHelper::exists( $section ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'Settings section not found.', 'radius-hotel-booking' ) );
		}
		return (array) SettingsHelper::get_setting( $section );
	}

	/**
	 * Store the section and announce the change.
	 *
	 * @param string $section Section key.
	 * @param array  $before  Values before.
	 * @param array  $after   Values to store.
	 * @return array The stored section, sensitive keys removed.
	 */
	private function save( string $section, array $before, array $after ): array {
		// update_option() returns false for an unchanged value, which is fine.
		SettingsHelper::update_setting( $section, $after );

		$stored = (array) SettingsHelper::get_setting( $section );

		if ( $stored !== $before ) {
			// Money caches the currency for the request.
			if ( 'general' === $section ) {
				Money::flush();
			}

			/**
			 * Fires after a settings section changed.
			 *
			 * Values include sensitive keys; mask them with
			 * SettingsSchema::strip_sensitive() before logging or showing them.
			 *
			 * @param string $section Section key.
			 * @param array  $before  Values before.
			 * @param array  $after   Values after.
			 */
			do_action( 'rtbp_settings_updated', $section, $before, $stored );
		}

		return SettingsSchema::strip_sensitive( $section, $stored );
	}
}
