<?php
/**
 * Item API controller.
 *
 * BOILERPLATE: the example controller. BaseController already implements
 * index/show/store/update/destroy against the injected repository — a controller
 * only declares its middleware, its transformers, its validation rules and its
 * resource type, plus any extra endpoints.
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseController;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules\InRule;
use RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules\MaxRule;
use RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules\NumericRule;
use RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules\RequiredRule;
use RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules\StringRule;
use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Repositories\ItemRepository;
use RadiusTheme\RadiusHotelBooking\Resources\ItemResource;
use RadiusTheme\RadiusHotelBooking\Services\ItemService;
use WP_REST_Request;

/**
 * Class ItemController
 */
class ItemController extends BaseController {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( ItemRepository::class ) );

		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::MANAGE_ITEMS );
	}

	/**
	 * Transform a single item.
	 *
	 * @param mixed $item Model instance.
	 *
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return ( new ItemResource() )->transform( $item );
	}

	/**
	 * Transform a collection of items.
	 *
	 * @param array $items Model instances.
	 *
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return ItemResource::collection( $items );
	}

	/**
	 * Validation rules per context.
	 *
	 * @param mixed  $request The incoming request.
	 * @param string $context 'create' or 'update'.
	 *
	 * @return array
	 */
	protected function getValidationRules( $request, string $context = 'create' ): array {
		$rules = array(
			'title'       => array( new RequiredRule(), new StringRule(), new MaxRule( 255 ) ),
			'description' => array( new StringRule() ),
			'status'      => array( new InRule( array( 'draft', 'published', 'archived' ) ) ),
			'price'       => array( new NumericRule() ),
		);

		if ( 'update' === $context ) {
			// Title stays optional on a partial update.
			$rules['title'] = array( new StringRule(), new MaxRule( 255 ) );
		}

		return $rules;
	}

	/**
	 * Resource type slug, used in the filter names BaseController fires.
	 *
	 * @return string
	 */
	protected function getResourceType(): string {
		return 'item';
	}

	/**
	 * PUT /items/{id}/publish — extra endpoint delegating to the service layer.
	 *
	 * @param WP_REST_Request $request Current request.
	 *
	 * @return mixed
	 */
	public function publish( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				$service = Container::resolve( ItemService::class );
				$item    = $service->publish( (int) $request->get_param( 'id' ) );

				if ( ! $item ) {
					return ApiResponse::notFound()->send();
				}

				return ApiResponse::success(
					$this->transformItem( $item ),
					__( 'Item published.', 'radius-hotel-booking' )
				)->send();
			}
		);
	}
}
