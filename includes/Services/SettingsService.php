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

use RadiusTheme\RadiusHotelBooking\Repositories\SettingsRepository;

/**
 * Class SettingsService
 *
 * Thin business-logic layer over the settings repository. Anything that needs
 * to read settings outside a REST request can resolve this from the container.
 */
class SettingsService {

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $repository Injected repository.
	 */
	public function __construct( SettingsRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * All settings, defaults merged with saved values.
	 *
	 * @return array
	 */
	public function all(): array {
		return $this->repository->getSettings();
	}

	/**
	 * A single settings section.
	 *
	 * @param string $section Section key.
	 *
	 * @return array|null
	 */
	public function section( string $section ): ?array {
		return $this->repository->getSection( $section );
	}

	/**
	 * Save one section.
	 *
	 * @param string $section Section key.
	 * @param array  $data    Section data.
	 *
	 * @return bool
	 */
	public function saveSection( string $section, array $data ): bool {
		return $this->repository->saveSection( $section, $data );
	}
}
