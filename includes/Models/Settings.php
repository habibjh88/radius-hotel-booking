<?php
/**
 * File: Models/Settings.php
 * Settings model for database operations
 */
namespace RadiusTheme\RadiusHotelBooking\Models;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class Settings
 *
 * A class to manage application settings stored in the WordPress options table.
 * Includes functionality for caching, nested keys, bulk operations, and migration.
 */
class Settings extends BaseModel {
	/**
	 * Option prefix for WordPress options table
	 *
	 * @var string Prefix for settings
	 */
	protected string $prefix = 'rtbp_';

	/**
	 * Option postfix for WordPress options table
	 *
	 * @var string Postfix for settings
	 */
	protected string $postfix = '_settings';

	/**
	 * Cache for settings (optional performance optimization)
	 *
	 * @var array
	 */
	protected array $cache = array();

	/**
	 * Whether to use caching
	 *
	 * @var bool
	 */
	protected bool $useCache = true;

	/**
	 * Get a single setting by key
	 *
	 * @param string $key Setting key
	 * @param mixed $default Default value if setting doesn't exist
	 * @return array|mixed
	 */
	public function getSettings( string $key, $default = array() ) {
		// Check cache first
		if ( $this->useCache && isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$optionKey = $this->prefix . $key . $this->postfix;
		$value     = get_option( $optionKey, $default );

		// Cache the value
		if ( $this->useCache ) {
			$this->cache[ $key ] = $value;
		}
		return $value;
	}

	/**
	 * Update a setting
	 *
	 * @param string $key Setting key
	 * @param array|mixed $value Setting value
	 * @return bool Success status
	 */
	public function updateSettings( string $key, $value ): bool {
		$optionKey = $this->prefix . $key . $this->postfix;

		// Update cache
		if ( $this->useCache ) {
			$this->cache[ $key ] = $value;
		}

		// Use update_option which will add if doesn't exist
		return update_option( $optionKey, $value );
	}

	/**
	 * Delete a setting
	 *
	 * @param string $key Setting key
	 * @return bool Success status
	 */
	public function deleteSettings( string $key ): bool {
		$optionKey = $this->prefix . $key . $this->postfix;

		// Remove from cache
		if ( $this->useCache && isset( $this->cache[ $key ] ) ) {
			unset( $this->cache[ $key ] );
		}

		return delete_option( $optionKey );
	}

	/**
	 * Get multiple settings at once
	 *
	 * @param array $keys Array of setting keys
	 * @param array $defaults Associative array of default values
	 * @return array Associative array of settings
	 */
	public function allData( array $keys, array $defaults = array() ): array {
		$settings = array();

		foreach ( $keys as $key ) {
			$default          = $defaults[ $key ] ?? array();
			$settings[ $key ] = $this->getSettings( $key, $default );
		}

		return $settings;
	}

	/**
	 * Bulk update multiple settings
	 *
	 * @param array $settings Associative array of settings
	 * @return bool Overall success status
	 */
	public function bulkUpdate( array $settings ): bool {
		$success = true;

		foreach ( $settings as $key => $value ) {
			if ( ! $this->updateSettings( $key, $value ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Check if a setting exists
	 *
	 * @param string $key Setting key
	 * @return bool
	 */
	public function exists( string $key ): bool {
		$optionKey = $this->prefix . $key . $this->postfix;
		return get_option( $optionKey ) !== false;
	}

	/**
	 * Get all settings keys from database
	 * (Useful for migration or cleanup)
	 *
	 * @return array Array of setting keys (without prefix)
	 */
	public function getAllKeys(): array {
		global $wpdb;

		$pattern = $wpdb->esc_like( $this->prefix ) . '%';

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		// Remove prefix from keys
		return array_map(
			function ( $key ) {
				return str_replace( $this->prefix, '', $key );
			},
			$results
		);
	}

	/**
	 * Delete all settings (use with caution!)
	 *
	 * @return bool Success status
	 */
	public function deleteAllSettings(): bool {
		$keys    = $this->getAllKeys();
		$success = true;

		foreach ( $keys as $key ) {
			if ( ! $this->delete( $key ) ) {
				$success = false;
			}
		}

		// Clear cache
		$this->clearCache();

		return $success;
	}

	/**
	 * Get setting with nested key support
	 * Example: get('integrations.zoom.apiKey', 'default')
	 *
	 * @param string $key Dot-notation key
	 * @param mixed $default Default value
	 * @return mixed
	 */
	public function getNestedKey( string $key, $default = null ) {
		$keys     = explode( '.', $key );
		$firstKey = array_shift( $keys );

		$data = $this->getSettings( $firstKey, array() );

		// Navigate through nested keys
		foreach ( $keys as $nestedKey ) {
			if ( ! isset( $data[ $nestedKey ] ) ) {
				return $default;
			}
			$data = $data[ $nestedKey ];
		}

		return $data;
	}

	/**
	 * Update setting with nested key support
	 * Example: updateNestedKey('integrations.zoom.apiKey', 'new-key')
	 *
	 * @param string $key Dot-notation key
	 * @param mixed $value New value
	 * @return bool Success status
	 */
	public function updateNestedKey( string $key, $value ): bool {
		$keys     = explode( '.', $key );
		$firstKey = array_shift( $keys );

		$data = $this->getSettings( $firstKey, array() );

		// Navigate to the nested key and update
		$current = &$data;
		foreach ( $keys as $nestedKey ) {
			if ( ! isset( $current[ $nestedKey ] ) ) {
				$current[ $nestedKey ] = array();
			}
			$current = &$current[ $nestedKey ];
		}
		$current = $value;

		return $this->updateSettings( $firstKey, $data );
	}

	/**
	 * Clear the settings cache
	 *
	 * @return void
	 */
	public function clearCache(): void {
		$this->cache = array();
	}

	/**
	 * Enable or disable caching
	 *
	 * @param bool $enabled Whether to enable caching
	 * @return void
	 */
	public function setCaching( bool $enabled ): void {
		$this->useCache = $enabled;
		if ( ! $enabled ) {
			$this->clearCache();
		}
	}

	/**
	 * Get the option prefix
	 *
	 * @return string
	 */
	public function getPrefix(): string {
		return $this->prefix;
	}

	/**
	 * Export all settings (for backup/migration)
	 *
	 * @return array
	 */
	public function export(): array {
		$keys   = $this->getAllKeys();
		$export = array(
			'exported_at'    => current_time( 'mysql' ),
			'plugin_version' => defined( 'RADIUS_HOTEL_BOOKING_VERSION' ) ? RADIUS_HOTEL_BOOKING_VERSION : '1.0.0',
			'settings'       => array(),
		);

		foreach ( $keys as $key ) {
			$export['settings'][ $key ] = $this->getSettings( $key );
		}

		return $export;
	}

	/**
	 * Import settings (for backup/migration)
	 *
	 * @param array $data Export data
	 * @param bool $overwrite Whether to overwrite existing settings
	 * @return bool Success status
	 */
	public function import( array $data, bool $overwrite = true ): bool {
		if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return false;
		}

		$success = true;

		foreach ( $data['settings'] as $key => $value ) {
			// Skip if not overwriting and key exists
			if ( ! $overwrite && $this->exists( $key ) ) {
				continue;
			}

			if ( ! $this->updateSettings( $key, $value ) ) {
				$success = false;
			}
		}

		return $success;
	}
}
