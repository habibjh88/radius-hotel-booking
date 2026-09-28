<?php
namespace RadiusTheme\RadiusHotelBooking\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;
use RadiusTheme\RadiusHotelBooking\Models\Settings;

/**
 * Class SettingsRepository
 *
 * Handles data operations for the Settings model.
 */
class SettingsRepository extends BaseRepository {

	/**
	 * The model class managed by this repository.
	 *
	 * @var string
	 */
	protected string $model = Settings::class;


	/**
	 * Retrieves the complete settings by merging default settings with saved settings.
	 *
	 * @return array An array containing the merged settings.
	 */
	public function getSettings(): array {
		$defaults = SettingsHelper::all();
		$setting  = new Settings();
		$saved    = $setting->allData( array_keys( $defaults ), $defaults );
		return $this->deepMerge( $defaults, $saved );
	}

	/**
	 * Retrieves a specific section from the settings.
	 *
	 * @param string $section The name of the section to retrieve.
	 *
	 * @return array|null The section data if it exists, or null if the section is not found.
	 */
	public function getSection( string $section ): ?array {
		$settings = $this->getSettings();
		return $settings[ $section ] ?? null;
	}

	/**
	 * Saves the provided settings data.
	 *
	 * @param array $data An associative array of settings where the key represents the setting name and the value represents the setting value.
	 *
	 * @return bool Returns true if all settings were successfully saved, otherwise false.
	 */
	public function saveSettings( array $data ): bool {
		$success = true;
		$setting = new Settings();
		foreach ( $data as $key => $value ) {
			if ( ! $setting->updateSettings( $key, $value ) ) {
				$success = false;
			}
		}
		return $success;
	}

	/**
	 * Saves the provided data to a specific section of settings.
	 *
	 * @param string $section The name of the section to save the data to.
	 * @param array $data The data to be saved to the specified section.
	 *
	 * @return bool Returns true on successful save, or false on failure.
	 */
	public function saveSection( string $section, array $data ): bool {
		$setting = new $this->model();
		return $setting->updateSettings( $section, $data );
	}

	/**
	 * Resets all settings to their default values.
	 *
	 * @return array The array of default settings after being reset.
	 */
	public function resetSettings(): array {
		$defaults = SettingsHelper::all();
		$setting  = new Settings();
		foreach ( $defaults as $key => $value ) {
			$setting->updateSettings( $key, $value );
		}
		return $defaults;
	}

	/**
	 *
	 * Resets the specified section of settings to its default values.
	 *
	 * @param string $section The name of the section to reset.
	 *
	 * @return array|null The default values of the section if it exists, or null if the section is not found.
	 */
	public function resetSection( string $section ): ?array {
		$defaults = SettingsHelper::all();
		$setting  = new Settings();
		if ( isset( $defaults[ $section ] ) ) {
			$setting->updateSettings( $section, $defaults[ $section ] );
			return $defaults[ $section ];
		}
		return null;
	}

	/**
	 * Resets only the given settings sections to their defaults, leaving every
	 * other section untouched. Used to reset a single settings tab without
	 * wiping unrelated tabs. Unknown section keys are ignored.
	 *
	 * @param string[] $sections Section keys to reset (e.g. 'general', 'display').
	 *
	 * @return array The full settings map after the reset (all sections merged).
	 */
	public function resetSections( array $sections ): array {
		$defaults = SettingsHelper::all();
		$setting  = new Settings();
		foreach ( $sections as $section ) {
			if ( isset( $defaults[ $section ] ) ) {
				$setting->updateSettings( $section, $defaults[ $section ] );
			}
		}
		return $this->getSettings();
	}

	/**
	 * Merges two arrays deeply, combining nested arrays recursively.
	 *
	 * @param array $defaults The default array to be merged into.
	 * @param array $saved The array containing overrides to merge with defaults.
	 *
	 * @return array The resulting array after performing a deep merge.
	 */
	protected function deepMerge( array $defaults, array $saved ): array {
		foreach ( $saved as $key => $value ) {
			if ( is_array( $value ) && isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) ) {
				$defaults[ $key ] = $this->deepMerge( $defaults[ $key ], $value );
			} else {
				$defaults[ $key ] = $value;
			}
		}
		return $defaults;
	}
}
