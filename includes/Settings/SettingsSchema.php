<?php
/**
 * Settings schema registry.
 *
 * @package RadiusTheme\RadiusHotelBooking\Settings
 */

namespace RadiusTheme\RadiusHotelBooking\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The single source of truth for every settings section: each key's type,
 * default, limits and flags. Defaults, sanitising, validation and the schema
 * the Settings screen reads all come from here.
 *
 *   SettingsSchema::register( 'booking', array(
 *       'bookingWindowDays' => array( 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 3650 ),
 *       'sameDayCutoff'     => array( 'type' => 'time', 'default' => '14:00' ),
 *   ) );
 *
 * Key options:
 *
 * - `type`: string | text | email | url | int | float | bool | enum | color | time |
 *   media | array
 * - `default`: the value a fresh install uses (a callable is resolved lazily)
 * - `min` / `max`: int and float bounds; `maxLength` for strings
 * - `options`: allowed values for `enum`
 * - `mime`: allowed mime prefixes for `media` (e.g. `array( 'audio/' )`)
 * - `sensitive`: never sent to the browser (a PIN hash, an API secret)
 * - `sanitize`: a callable( $value ) returning the clean value or a WP_Error, for `array`
 *   and anything the built-in types cannot express
 *
 * Sections are registered on first use: core sections by CoreSettings, add-on
 * sections through register() (call it from `rtbp_settings_schema_init`) or the
 * `rtbp_settings_schema` filter. Sections that only exist through the older
 * `rtbp_settings` defaults filter keep working without a schema (see
 * SettingsHelper::all()).
 */
final class SettingsSchema {

	/**
	 * Registered sections, before the filter runs.
	 *
	 * @var array<string, array>
	 */
	private static array $registered = array();

	/**
	 * The resolved schema, built once per request.
	 *
	 * @var array<string, array>|null
	 */
	private static ?array $resolved = null;

	/**
	 * Register (or extend) a section.
	 *
	 * @param string $section Section key, snake_case (the option is `rtbp_<section>_settings`).
	 * @param array  $schema  Key => definition.
	 * @return void
	 */
	public static function register( string $section, array $schema ): void {
		self::$registered[ $section ] = array_merge( self::$registered[ $section ] ?? array(), $schema );
		self::$resolved               = null;
	}

	/**
	 * Every section's schema.
	 *
	 * @return array<string, array>
	 */
	public static function sections(): array {
		if ( null !== self::$resolved ) {
			return self::$resolved;
		}

		// Guard against re-entry: a default callable that reads a setting.
		self::$resolved = array();

		CoreSettings::register();

		/**
		 * Fires once, when the schema is first needed. Add-ons call
		 * SettingsSchema::register() here.
		 */
		do_action( 'rtbp_settings_schema_init' );

		/**
		 * Filters the full settings schema.
		 *
		 * @param array $sections Section => key => definition.
		 */
		$sections = (array) apply_filters( 'rtbp_settings_schema', self::$registered );

		foreach ( $sections as $section => $keys ) {
			foreach ( (array) $keys as $key => $definition ) {
				$sections[ $section ][ $key ] = self::normalise( (array) $definition );
			}
		}

		self::$resolved = $sections;
		return self::$resolved;
	}

	/**
	 * One section's schema, or null when the section has none.
	 *
	 * @param string $section Section key.
	 * @return array|null
	 */
	public static function section( string $section ): ?array {
		return self::sections()[ $section ] ?? null;
	}

	/**
	 * Whether a section has a schema.
	 *
	 * @param string $section Section key.
	 * @return bool
	 */
	public static function has( string $section ): bool {
		return null !== self::section( $section );
	}

	/**
	 * A section's defaults.
	 *
	 * @param string $section Section key.
	 * @return array
	 */
	public static function defaults( string $section ): array {
		$defaults = array();
		foreach ( self::section( $section ) ?? array() as $key => $definition ) {
			$defaults[ $key ] = self::default_of( $definition );
		}
		return $defaults;
	}

	/**
	 * The schema as the Settings screen needs it: no callables, no sensitive
	 * defaults.
	 *
	 * @return array
	 */
	public static function for_client(): array {
		$out = array();
		foreach ( self::sections() as $section => $keys ) {
			foreach ( $keys as $key => $definition ) {
				unset( $definition['sanitize'] );
				$definition['default'] = $definition['sensitive'] ? null : self::default_of( $definition );
				if ( isset( $definition['options'] ) ) {
					$definition['options'] = SettingsValidator::options( $definition );
				}
				$out[ $section ][ $key ] = $definition;
			}
		}
		return $out;
	}

	/**
	 * Drop the keys marked sensitive from a section's values.
	 *
	 * @param string $section Section key.
	 * @param array  $values  Values.
	 * @return array
	 */
	public static function strip_sensitive( string $section, array $values ): array {
		foreach ( self::section( $section ) ?? array() as $key => $definition ) {
			if ( $definition['sensitive'] ) {
				unset( $values[ $key ] );
			}
		}
		return $values;
	}

	/**
	 * Forget the resolved schema (tests and runtime registration).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$resolved = null;
	}

	/**
	 * A definition's default, resolving callables.
	 *
	 * @param array $definition Normalised definition.
	 * @return mixed
	 */
	private static function default_of( array $definition ) {
		$default = $definition['default'];
		return is_callable( $default ) && ! is_string( $default ) ? $default() : $default;
	}

	/**
	 * Fill a definition's optional fields.
	 *
	 * @param array $definition Definition.
	 * @return array
	 */
	private static function normalise( array $definition ): array {
		$definition = array_merge(
			array(
				'type'      => 'string',
				'default'   => null,
				'sensitive' => false,
			),
			$definition
		);

		if ( null === $definition['default'] ) {
			$definition['default'] = self::empty_value( $definition['type'] );
		}

		return $definition;
	}

	/**
	 * The empty value of a type, used when no default is given.
	 *
	 * @param string $type Type.
	 * @return mixed
	 */
	private static function empty_value( string $type ) {
		switch ( $type ) {
			case 'int':
			case 'media':
				return 0;
			case 'float':
				return 0.0;
			case 'bool':
				return false;
			case 'array':
				return array();
			default:
				return '';
		}
	}
}
