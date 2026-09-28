<?php
/**
 * Settings API controller.
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseController;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Repositories\SettingsRepository;
use RadiusTheme\RadiusHotelBooking\Services\SettingsService;
use RadiusTheme\RadiusHotelBooking\Settings\SettingsSchema;
use RadiusTheme\RadiusHotelBooking\Settings\SettingsValidator;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings endpoints. Every write goes through SettingsService, which
 * validates against the schema and fires `rtbp_settings_updated`.
 *
 * Access: `rtbp_manage_settings` until M13 replaces it with the
 * `page.settings` / `settings.<section>` access keys.
 */
class SettingsController extends BaseController {

	/**
	 * Settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings;

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( SettingsRepository::class ) );
		$this->settings     = Container::resolve( SettingsService::class );
		$this->middleware[] = new PermissionMiddleware( 'rtbp_manage_settings' );
	}

	/**
	 * Settings are returned as stored; nothing to reshape.
	 *
	 * @param mixed $item Item.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return (array) $item;
	}

	/**
	 * Settings are returned as stored; nothing to reshape.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return $items;
	}

	/**
	 * No request rules: values are validated against Settings\SettingsSchema
	 * by SettingsService, which knows every key's type and limits.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $context Context.
	 * @return array
	 */
	protected function getValidationRules( $request, string $context = 'create' ) {
		unset( $request, $context );
		return array();
	}

	/**
	 * Resource type.
	 *
	 * @return string
	 */
	protected function getResourceType(): string {
		return 'Settings';
	}

	/**
	 * GET /settings: every section, sensitive keys removed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function index( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function () {
				/**
				 * Filters the settings sent to the Settings screen.
				 *
				 * @param array $settings Section => values.
				 */
				$settings = apply_filters( 'rtbp_settings_data', $this->settings->all() );
				return ApiResponse::success( array( 'settings' => $settings ), __( 'Settings retrieved successfully.', 'radius-hotel-booking' ) );
			}
		);
	}

	/**
	 * GET /settings/schema: types, defaults, limits and options per key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function schema( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			static fn() => ApiResponse::success( array( 'schema' => SettingsSchema::for_client() ) )
		);
	}

	/**
	 * GET /settings/{section}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$section = (string) $request->get_param( 'section' );
				$data    = $this->settings->section( $section );
				if ( null === $data ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::notFound( __( 'Settings section not found.', 'radius-hotel-booking' ) );
				}
				return ApiResponse::success(
					array(
						'section' => $section,
						'data'    => $data,
					)
				);
			}
		);
	}

	/**
	 * PUT /settings/{section}: update some keys of one section.
	 *
	 * @param WP_REST_Request $request Request (JSON body: key => value).
	 * @return mixed
	 */
	public function updateSection( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$section = (string) $request->get_param( 'section' );
				$data    = $this->settings->updateSection( $section, (array) $request->get_json_params() );
				return ApiResponse::success(
					array(
						'section' => $section,
						'data'    => $data,
					),
					__( 'Settings saved.', 'radius-hotel-booking' )
				);
			}
		);
	}

	/**
	 * PUT /settings/{section}/reset: restore one section's defaults.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function resetSection( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$section = (string) $request->get_param( 'section' );
				$data    = $this->settings->resetSection( $section );
				return ApiResponse::success(
					array(
						'section' => $section,
						'data'    => $data,
					),
					__( 'Settings section reset to defaults successfully.', 'radius-hotel-booking' )
				);
			}
		);
	}

	/**
	 * PUT /settings: update several sections at once (section => key => value).
	 * All sections are validated before anything is saved; field errors are
	 * keyed `section.key`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$input = (array) $request->get_json_params();

				$errors = array();
				foreach ( $input as $section => $values ) {
					$schema = SettingsSchema::section( (string) $section );
					if ( null === $schema || ! is_array( $values ) ) {
						continue;
					}
					list( , $section_errors ) = SettingsValidator::validate( $schema, $values );
					foreach ( $section_errors as $key => $message ) {
						$errors[ $section . '.' . $key ] = $message;
					}
				}
				if ( $errors ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw new DomainException( 'invalid_settings', __( 'Please correct the highlighted fields.', 'radius-hotel-booking' ), 422, $errors );
				}

				foreach ( $input as $section => $values ) {
					if ( is_array( $values ) ) {
						$this->settings->updateSection( (string) $section, $values );
					}
				}

				return ApiResponse::success( array( 'settings' => $this->settings->all() ), __( 'Settings updated successfully.', 'radius-hotel-booking' ) );
			}
		);
	}

	/**
	 * PUT /settings/reset: restore the defaults of the given sections
	 * (`sections` param), or of every section when none are given.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function reset( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$sections = $request->get_param( 'sections' );
				$sections = is_array( $sections ) && $sections ? $sections : array_keys( $this->settings->all() );
				foreach ( $sections as $section ) {
					$this->settings->resetSection( (string) $section );
				}
				return ApiResponse::success( array( 'settings' => $this->settings->all() ), __( 'Settings reset to defaults successfully.', 'radius-hotel-booking' ) );
			}
		);
	}

	/**
	 * Run a handler behind the middleware and turn exceptions into the
	 * ApiResponse envelope.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param callable        $handler Returns an ApiResponse.
	 * @return mixed
	 */
	private function respond( WP_REST_Request $request, callable $handler ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) use ( $handler ) {
				try {
					return $handler( $request )->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}
}
