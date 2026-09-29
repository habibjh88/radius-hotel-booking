<?php
/**
 * Rate plans API controller.
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseController;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AccessMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Models\RatePlan;
use RadiusTheme\RadiusHotelBooking\Repositories\RatePlanRepository;
use RadiusTheme\RadiusHotelBooking\Services\Pricing\RatePlanService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `rate-plans` CRUD (M07). Reading needs `page.rates`, changing needs
 * `rates.manage`. Validation lives in RatePlanService.
 */
class RatePlanController extends BaseController {

	/**
	 * Rate plan service.
	 *
	 * @var RatePlanService
	 */
	private RatePlanService $service;

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( RatePlanRepository::class ) );
		$this->service      = Container::resolve( RatePlanService::class );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				'index'   => 'page.rates',
				'show'    => 'page.rates',
				'store'   => 'rates.manage',
				'update'  => 'rates.manage',
				'destroy' => 'rates.manage',
			)
		);
	}

	/**
	 * GET /rate-plans: the library, with where each plan is used.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function index( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn() => ApiResponse::success( array( 'rate_plans' => $this->transformCollection( $this->service->all() ) ) )
		);
	}

	/**
	 * GET /rate-plans/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success( array( 'rate_plan' => $this->transformItem( $this->service->get( (int) $request->get_param( 'id' ) ) ) ) )
		);
	}

	/**
	 * POST /rate-plans.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function store( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::created(
				array( 'rate_plan' => $this->transformItem( $this->service->create( (array) $request->get_json_params() ) ) ),
				__( 'Rate plan added.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * PUT/PATCH /rate-plans/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success(
				array( 'rate_plan' => $this->transformItem( $this->service->update( (int) $request->get_param( 'id' ), (array) $request->get_json_params() ) ) ),
				__( 'Rate plan saved.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * DELETE /rate-plans/{id}: refused while in use.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function destroy( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$this->service->delete( (int) $request->get_param( 'id' ) );
				return ApiResponse::success( array(), __( 'Rate plan deleted.', 'radius-hotel-booking' ) );
			}
		);
	}

	/**
	 * One plan for the API.
	 *
	 * @param mixed $item RatePlan.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return array(
			'id'               => (int) $item->id,
			'name'             => (string) $item->name,
			'code'             => (string) $item->code,
			'type'             => (string) $item->type,
			'start_time'       => $item->start_time,
			'end_time'         => $item->end_time,
			'duration_minutes' => (int) $item->duration_minutes,
			'checkin_from'     => $item->checkin_from,
			'checkin_until'    => $item->checkin_until,
			'multi_unit'       => (bool) $item->multi_unit,
			'features'         => array_values( (array) $item->features ),
			'policy'           => (string) $item->policy,
			'is_active'        => (bool) $item->is_active,
			'sort_order'       => (int) $item->sort_order,
			'usage'            => $this->service->usage( (int) $item->id ),
		);
	}

	/**
	 * Plans for the API.
	 *
	 * @param array $items Plans.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return array_map( fn( RatePlan $plan ) => $this->transformItem( $plan ), $items );
	}

	/**
	 * No request rules: RatePlanService validates.
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
		return 'RatePlan';
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
