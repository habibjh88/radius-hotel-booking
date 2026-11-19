<?php
/**
 * Settings export and import.
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

/**
 * Moves settings between sites (or back onto the same one) as a JSON file.
 *
 * Export: the chosen sections as stored, sensitive keys removed (a PIN hash
 * or an API secret never leaves the site).
 *
 * Import: each value is checked on its own against this site's schema. A
 * value that does not fit here (a logo or page that is not on this site, an
 * option an older version did not have) is skipped and reported, so one bad
 * key never blocks the rest; sensitive and unknown keys are ignored. The
 * accepted values are saved through SettingsService, so they are validated
 * again, stored and logged (`rtbp_settings_updated`) like any edit.
 */
class SettingsTransferService {

	/**
	 * The file's marker, so any other JSON file is refused.
	 */
	public const FORMAT = 'radius-hotel-booking-settings';

	/**
	 * File format version; bump on a breaking change of the layout.
	 */
	public const VERSION = 1;

	/**
	 * Settings service (the only write path).
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings;

	/**
	 * @param SettingsService $settings Settings service.
	 */
	public function __construct( SettingsService $settings ) {
		$this->settings = $settings;
	}

	/**
	 * The export file's content.
	 *
	 * @param string[] $sections Section keys ([] = every section).
	 * @return array
	 */
	public function export( array $sections = array() ): array {
		$known    = array_keys( SettingsHelper::all() );
		$sections = $sections ? array_values( array_intersect( $known, $sections ) ) : $known;

		$data = array();
		foreach ( $sections as $section ) {
			$data[ $section ] = (array) $this->settings->section( $section );
		}

		rtbp_activity(
			'settings.exported',
			null,
			array( 'sections' => implode( ', ', $sections ) )
		);

		return array(
			'format'         => self::FORMAT,
			'version'        => self::VERSION,
			'plugin_version' => defined( 'RADIUS_HOTEL_BOOKING_VERSION' ) ? RADIUS_HOTEL_BOOKING_VERSION : '',
			'exported_at'    => gmdate( 'c' ),
			'site'           => home_url(),
			'settings'       => $data,
		);
	}

	/**
	 * What importing the file would do, section by section, without saving.
	 *
	 * @param mixed $file The parsed export file.
	 * @return array `{ exported_at, site, plugin_version, sections: [ { key, known, changes, skipped: [ { key, message } ] } ] }`.
	 * @throws DomainException When the file is not a settings export.
	 */
	public function preview( $file ): array {
		$file     = $this->checkFile( $file );
		$sections = array();
		foreach ( $file['settings'] as $section => $values ) {
			$plan       = $this->plan( (string) $section, is_array( $values ) ? $values : array() );
			$sections[] = array(
				'key'     => (string) $section,
				'known'   => $plan['known'],
				'changes' => count( $plan['changes'] ),
				'skipped' => $plan['skipped'],
			);
		}

		return array(
			'exported_at'    => (string) ( $file['exported_at'] ?? '' ),
			'site'           => (string) ( $file['site'] ?? '' ),
			'plugin_version' => (string) ( $file['plugin_version'] ?? '' ),
			'sections'       => $sections,
		);
	}

	/**
	 * Import the chosen sections of the file.
	 *
	 * @param mixed    $file     The parsed export file.
	 * @param string[] $sections Sections to import ([] = every section in the file).
	 * @return array `{ imported: [ key => changes ], skipped: [ key => [ { key, message } ] ] }`.
	 * @throws DomainException When the file is not a settings export, or a value is refused on save.
	 */
	public function import( $file, array $sections = array() ): array {
		$file   = $this->checkFile( $file );
		$chosen = $sections ? array_flip( $sections ) : null;

		// Plan everything first, so nothing is saved when the file is unusable.
		$plans = array();
		foreach ( $file['settings'] as $section => $values ) {
			$section = (string) $section;
			if ( null !== $chosen && ! isset( $chosen[ $section ] ) ) {
				continue;
			}
			$plan = $this->plan( $section, is_array( $values ) ? $values : array() );
			if ( $plan['known'] ) {
				$plans[ $section ] = $plan;
			}
		}

		$imported = array();
		$skipped  = array();
		foreach ( $plans as $section => $plan ) {
			if ( $plan['changes'] ) {
				$this->settings->updateSection( $section, $plan['changes'] );
			}
			$imported[ $section ] = count( $plan['changes'] );
			if ( $plan['skipped'] ) {
				$skipped[ $section ] = $plan['skipped'];
			}
		}

		rtbp_activity(
			'settings.imported',
			null,
			array(
				'sections' => implode( ', ', array_keys( $imported ) ),
				'from'     => (string) ( $file['site'] ?? '' ),
			)
		);

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}

	/**
	 * The section keys an import of this file would write (for the access check).
	 *
	 * @param mixed    $file     The parsed export file.
	 * @param string[] $sections Sections asked for ([] = all in the file).
	 * @return string[]
	 */
	public static function sectionsIn( $file, array $sections = array() ): array {
		$in   = is_array( $file ) && isset( $file['settings'] ) && is_array( $file['settings'] ) ? array_map( 'strval', array_keys( $file['settings'] ) ) : array();
		$keys = $sections ? array_values( array_intersect( $in, $sections ) ) : $in;
		return array_values( array_filter( $keys, array( SettingsHelper::class, 'exists' ) ) );
	}

	/**
	 * Which values of one section would be written, and which are skipped.
	 *
	 * @param string $section Section key.
	 * @param array  $values  Values from the file.
	 * @return array `{ known: bool, changes: key => value, skipped: [ { key, message } ] }`.
	 */
	private function plan( string $section, array $values ): array {
		if ( ! SettingsHelper::exists( $section ) ) {
			return array(
				'known'   => false,
				'changes' => array(),
				'skipped' => array(),
			);
		}

		$current = (array) SettingsHelper::get_setting( $section );
		$schema  = SettingsSchema::section( $section );
		// Never written from a file: a secret stays the one set on this site.
		$public  = null !== $schema ? SettingsSchema::strip_sensitive( $section, $values ) : $values;
		$changes = array();
		$skipped = array();

		foreach ( $public as $key => $value ) {
			$key = (string) $key;
			if ( null !== $schema ) {
				if ( ! isset( $schema[ $key ] ) ) {
					continue; // An option this version does not have.
				}
				list( $clean, $errors ) = SettingsValidator::validate( array( $key => $schema[ $key ] ), array( $key => $value ) );
				if ( $errors ) {
					$skipped[] = array(
						'key'     => $key,
						'message' => (string) reset( $errors ),
					);
					continue;
				}
				$value = $clean[ $key ];
			} elseif ( ! array_key_exists( $key, $current ) ) {
				continue; // An add-on section without a schema: only its known keys.
			}

			if ( ! array_key_exists( $key, $current ) || ! self::same( $this->cleanCurrent( $schema, $key, $current[ $key ] ), $value ) ) {
				$changes[ $key ] = $value;
			}
		}

		return array(
			'known'   => true,
			'changes' => $changes,
			'skipped' => $skipped,
		);
	}

	/**
	 * The stored value as the validator would clean it, so a value read
	 * back from a file compares equal (a stored 40 is cleaned to 40.0, a
	 * payment method gains its missing keys).
	 *
	 * @param array|null $schema  Section schema.
	 * @param string     $key     Key.
	 * @param mixed      $value   Stored value.
	 * @return mixed
	 */
	private function cleanCurrent( ?array $schema, string $key, $value ) {
		if ( null === $schema || ! isset( $schema[ $key ] ) ) {
			return $value;
		}
		list( $clean ) = SettingsValidator::validate( array( $key => $schema[ $key ] ), array( $key => $value ) );
		return array_key_exists( $key, $clean ) ? $clean[ $key ] : $value;
	}

	/**
	 * Equal once number types (40 and 40.0) and key order are set aside.
	 *
	 * @param mixed $a Value.
	 * @param mixed $b Value.
	 * @return bool
	 */
	private static function same( $a, $b ): bool {
		return self::canonical( $a ) === self::canonical( $b );
	}

	/**
	 * Numbers as floats, keyed arrays sorted by key, recursively.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function canonical( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$value = array_map( array( self::class, 'canonical' ), $value );
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}
		return $value;
	}

	/**
	 * Refuse anything but a settings export of a format this version reads.
	 *
	 * @param mixed $file Parsed file.
	 * @return array
	 * @throws DomainException When it is not one.
	 */
	private function checkFile( $file ): array {
		if ( ! is_array( $file ) || self::FORMAT !== ( $file['format'] ?? '' ) || ! isset( $file['settings'] ) || ! is_array( $file['settings'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'invalid_settings_file', __( 'This is not a settings file exported from Radius Hotel Booking.', 'radius-hotel-booking' ), 422 );
		}
		if ( (int) ( $file['version'] ?? 0 ) > self::VERSION ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'settings_file_too_new', __( 'This settings file was made by a newer version of the plugin. Update the plugin, then import it again.', 'radius-hotel-booking' ), 422 );
		}
		return $file;
	}
}
