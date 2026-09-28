<?php
/**
 * File: Controllers/SettingsController.php
 * Settings API controller
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use WP_REST_Request;
use RadiusTheme\RadiusHotelBooking\Abstracts\BaseController;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Repositories\SettingsRepository;
use RadiusTheme\RadiusHotelBooking\Resources\SettingsResource;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Api/Controllers/SettingsController.php
 */
class SettingsController extends BaseController {

	/**
	 * Initialize the class by resolving dependencies and calling the parent's constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( Container::resolve( SettingsRepository::class ) );
		$this->middleware[] = new PermissionMiddleware( 'rtbp_manage_settings' );
	}

	/**
	 * Transforms a single item into an array format.
	 *
	 * @param mixed $item The item to be transformed.
	 *
	 * @return array The transformed item as an array.
	 */
	protected function transformItem( $item ): array {
		return ( new SettingsResource() )->transform( $item );
	}

	/**
	 * Transforms a collection of items into a specific format.
	 *
	 * @param array $items The collection of items to be transformed.
	 *
	 * @return array The transformed collection.
	 */
	protected function transformCollection( array $items ): array {
		return SettingsResource::collection( $items );
	}

	/**
	 * Retrieves the type of the resource.
	 *
	 * @return string The resource type.
	 */
	protected function getResourceType(): string {
		return 'Settings';
	}

	/**
	 * Retrieve settings
	 * GET /settings
	 *
	 * @param WP_REST_Request $request The REST request instance.
	 *
	 * @return ApiResponse The success or error response of the settings retrieval operation.
	 */
	public function index( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function () {
				try {
					$settings = $this->repository->getSettings();
					$settings = apply_filters( 'rtbp_settings_data', $settings );
					return ApiResponse::success(
						array( 'settings' => $settings ),
						__( 'Settings retrieved successfully.', 'radius-hotel-booking' )
					)->send();
				} catch ( \Exception $e ) {
					return ApiResponse::error(
						$e->getMessage(),
						500
					)->send();
				}
			}
		);
	}

	/**
	 * Show specific settings section
	 * GET /settings/show
	 *
	 * @param WP_REST_Request $request The request object containing parameters for retrieving the settings section.
	 *
	 * @return mixed A response indicating success or failure of the operation. On success, returns the settings section data; on failure, returns an error response.
	 */
	public function show( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				try {
					$section = $request->get_param( 'section' );
					$data    = $this->repository->getSection( $section );

					if ( null === $data ) {
						return ApiResponse::notFound(
							__( 'Settings section not found.', 'radius-hotel-booking' )
						)->send();
					}

					return ApiResponse::success(
						array(
							'section' => $section,
							'data'    => $data,
						)
					)->send();
				} catch ( \Exception $e ) {
					return ApiResponse::error(
						$e->getMessage(),
						500
					)->send();
				}
			}
		);
	}

	/**
	 * Update settings
	 * POST /settings/update
	 *
	 * @param WP_REST_Request $request The request object containing settings to be updated.
	 *
	 * @return ApiResponse Returns success response with updated settings or error response on failure.
	 */
	public function update( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				try {
					$data = $request->get_json_params();

					// Save settings
					$is_save = $this->repository->saveSettings( $data );
					if ( is_wp_error( $is_save ) ) {
						return ApiResponse::validationError(
							$is_save->get_error_data(),
							$is_save->get_error_message()
						)->send();
					}
					return ApiResponse::success(
						array(
							'settings' => $this->repository->getSettings(),
						),
						__( 'Settings updated successfully.', 'radius-hotel-booking' )
					)->send();
				} catch ( \Exception $e ) {
					return ApiResponse::error(
						$e->getMessage(),
						500
					)->send();
				}
			}
		);
	}

	/**
	 * Update a specific settings section.
	 *
	 * @param WP_REST_Request $request The REST request containing the section identifier and updated data.
	 */
	public function updateSection( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				try {
					$section = $request->get_param( 'section' );
					$data    = $request->get_json_params();

					// Get current section data
					$currentData = $this->repository->getSection( $section );
					if ( null === $currentData ) {
						return ApiResponse::notFound(
							__( 'Settings section not found.', 'radius-hotel-booking' )
						)->send();
					}

					// Merge with new data
					$mergedData = array_replace_recursive( $currentData, $data );

					// Save section
					$this->repository->saveSection( $section, $mergedData );

					return ApiResponse::success(
						array(
							'section' => $section,
							'data'    => $this->transformItem( $mergedData ),
						),
						__( 'Settings section updated successfully.', 'radius-hotel-booking' )
					)->send();
				} catch ( \Exception $e ) {
					return ApiResponse::error(
						$e->getMessage(),
						500
					)->send();
				}
			}
		);
	}

	/**
	 * Resets the settings to their default values and generates a response.
	 *
	 * @param WP_REST_Request $request The REST request instance containing request details.
	 */
	public function reset( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				try {
					$sections = $request->get_param( 'sections' );

					if ( is_array( $sections ) && ! empty( $sections ) ) {
						// Reset only the requested sections (i.e. one settings
						// tab), leaving every other tab untouched. Preserve the
						// stored keys' camelCase (do NOT sanitize_key — it would
						// lowercase a camelCase section key.); resetSections() only
						// touches keys that exist in the defaults, so anything
						// unrecognised is harmlessly ignored.
						$sections = array_values(
							array_filter(
								array_map(
									static function ( $section ) {
										return preg_replace( '/[^A-Za-z0-9_]/', '', (string) $section );
									},
									$sections
								)
							)
						);

						$settings = $this->repository->resetSections( $sections );
						$message  = __( 'Settings reset to defaults successfully.', 'radius-hotel-booking' );
					} else {
						// No sections given → full reset (backward compatible).
						$settings = $this->repository->resetSettings();
						$message  = __( 'All settings reset to defaults successfully.', 'radius-hotel-booking' );
					}

					return ApiResponse::success(
						array( 'settings' => $settings ),
						$message
					)->send();
				} catch ( \Exception $e ) {
					return ApiResponse::error(
						$e->getMessage(),
						500
					)->send();
				}
			}
		);
	}

	/**
	 * Resets a specific settings section to its default values.
	 *
	 * @param WP_REST_Request $request The REST request object containing request parameters, including the section to reset.
	 */
	public function resetSection( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				try {
					$section = $request->get_param( 'section' );
					$data    = $this->repository->resetSection( $section );

					if ( null === $data ) {
						return ApiResponse::notFound(
							__( 'Settings section not found.', 'radius-hotel-booking' )
						)->send();
					}

					return ApiResponse::success(
						array(
							'section' => $section,
							'data'    => $this->transformItem( $data ),
						),
						__( 'Settings section reset to defaults successfully.', 'radius-hotel-booking' )
					)->send();
				} catch ( \Exception $e ) {
					return ApiResponse::error(
						$e->getMessage(),
						500
					)->send();
				}
			}
		);
	}

	/**
	 * Exports the settings data collected from the repository and generates a response.
	 *
	 * @param WP_REST_Request $request The REST request instance containing request details.
	 */
	public function export( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function () {
				try {
					$settings = $this->repository->getSettings();

					return ApiResponse::success(
						array(
							'settings'    => $this->transformCollection( $settings ),
							'exported_at' => current_time( 'mysql' ),
							'version'     => defined( 'RADIUS_HOTEL_BOOKING_VERSION' ) ? RADIUS_HOTEL_BOOKING_VERSION : '1.0.0',
						),
						__( 'Settings exported successfully.', 'radius-hotel-booking' )
					)->send();
				} catch ( \Exception $e ) {
					return ApiResponse::error(
						$e->getMessage(),
						500
					)->send();
				}
			}
		);
	}

	/**
	 * Imports settings data from the provided request, validates the data, and saves it to the repository.
	 *
	 * @param WP_REST_Request $request The REST request instance containing the import data and details.
	 */
	public function import( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				try {
					$data = $request->get_json_params();

					if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
						return ApiResponse::validationError(
							array( 'settings' => __( 'Settings data is required and must be an array.', 'radius-hotel-booking' ) ),
							__( 'Invalid import data format.', 'radius-hotel-booking' )
						)->send();
					}

					// Save imported settings
					$this->repository->saveSettings( $data['settings'] );

					return ApiResponse::success(
						array( 'settings' => $this->transformCollection( $this->repository->getSettings() ) ),
						__( 'Settings imported successfully.', 'radius-hotel-booking' )
					)->send();
				} catch ( \Exception $e ) {
					return ApiResponse::error(
						$e->getMessage(),
						500
					)->send();
				}
			}
		);
	}

	/**
	 * Generates validation rules based on the provided request and context.
	 *
	 * @param mixed $request The request data to validate against.
	 * @param string $context The context for the validation rules. Defaults to 'create'. Possible values include 'create' and 'update'.
	 */
	protected function getValidationRules( $request, string $context = 'create' ) {
		$rules = array(
			// Add your fields below
		);

		return $rules;
	}
}
