<?php
/**
 * Improved BaseController with Enhanced Validation
 *
 * @package RadiusTheme\RadiusHotelBooking\Abstracts
 */

namespace RadiusTheme\RadiusHotelBooking\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use Throwable;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Validation\ApiValidator;
use WP_REST_Controller;
use WP_REST_Request;

/**
 * Enhanced BaseController with nested validation support
 */
abstract class BaseController extends WP_REST_Controller {
	/**
	 * Repository instance for data persistence.
	 *
	 * @var BaseRepository
	 */
	protected BaseRepository $repository;

	/**
	 * Middleware stack for request processing.
	 *
	 * @var array
	 */
	protected array $middleware = array();

	/**
	 *  Allowed Operator
	 *
	 * @var string[]
	 */
	protected $allowedOperators = array( '!=', '>=', '<=', '=', '>', '<', 'LIKE' );


	/**
	 * BaseController constructor.
	 *
	 * @param BaseRepository $repository Repository to handle data persistence.
	 */
	public function __construct( BaseRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Applies middleware stack to a request.
	 *
	 * @param WP_REST_Request $request The current request.
	 * @param callable $handler Final handler after middleware.
	 *
	 * @return mixed
	 */
	protected function applyMiddleware( WP_REST_Request $request, callable $handler ) {
		$middlewareStack = array_reverse( $this->middleware );
		$next            = $handler;

		foreach ( $middlewareStack as $middleware ) {
			$next = function ( $request ) use ( $middleware, $next ) {
				return $middleware->handle( $request, $next );
			};
		}

		return $next( $request );
	}

	/**
	 * Applies a bypass by directly invoking the provided handler with the request.
	 *
	 * @param WP_REST_Request $request The current REST request to be processed.
	 * @param callable $handler The handler function to execute, expecting the request as its argument.
	 *
	 * @return mixed The result of the handler execution.
	 */
	protected function applyBypass( WP_REST_Request $request, callable $handler ) {
		// If handler expects middleware-like chaining, pass request directly
		$middlewareStack = array_reverse( $this->middleware );
		$next            = $handler;

		foreach ( $middlewareStack as $middleware ) {
			$next = function ( $request ) use ( $middleware, $next ) {
				return $middleware->byPassRequest( $request, $next );
			};
		}

		return $next( $request );
	}
	/**
	 * Validates request parameters against defined rules with enhanced error formatting.
	 *
	 * @param WP_REST_Request $request The current REST request.
	 * @param array $rules Validation rules to apply.
	 *
	 * @return WP_REST_Response|null
	 */
	protected function validateRequest( WP_REST_Request $request, array $rules ) {
		$validator = new ApiValidator( $rules );
		$data      = $request->get_params();

		// Merge JSON params if they exist
		if ( 'application/json' === $request->get_content_type()['value'] ) {
			$json_data = $request->get_json_params();
			if ( is_array( $json_data ) ) {
				$data = array_merge( $data, $json_data );
			}
		}

		if ( ! $validator->validate( $data ) ) {
			$errors = $this->formatValidationErrors( $validator->getErrors() );

			return ApiResponse::validationError( $errors )->send();
		}

		return null;
	}

	/**
	 * Format validation errors for better API response.
	 *
	 * @param array $errors Raw validation errors.
	 *
	 * @return array Formatted errors.
	 */
	private function formatValidationErrors( array $errors ): array {
		$formatted = array();

		foreach ( $errors as $field => $messages ) {
			$formatted[ $field ] = array(
				'field'         => $field,
				'messages'      => $messages,
				'first_message' => $messages[0] ?? '',
			);
		}

		return $formatted;
	}

	/**
	 * Get validation rules with context-aware rules.
	 *
	 * @param string $context The validation context.
	 * @param WP_REST_Request $request The current request.
	 *
	 * @return array
	 */
	protected function getValidationRulesWithContext( string $context, WP_REST_Request $request ): array {
		$rules = $this->getValidationRules( $request, $context );

		// Apply conditional rules based on request data
		$data = $request->get_params();
		if ( 'application/json' === $request->get_content_type()['value'] ) {
			$json_data = $request->get_json_params();
			if ( is_array( $json_data ) ) {
				$data = array_merge( $data, $json_data );
			}
		}

		return $this->applyConditionalRules( $rules, $data, $context );
	}

	/**
	 * Apply conditional validation rules based on data.
	 *
	 * @param array $rules Base validation rules.
	 * @param array $data Request data.
	 * @param string $context Validation context.
	 *
	 * @return array
	 */
	protected function applyConditionalRules( array $rules, array $data, string $context ): array {
		// Override this method in child controllers for conditional validation
		return $rules;
	}

	/**
	 * GET /resource — List paginated items.
	 *
	 * @param WP_REST_Request $request The current REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				$page = apply_filters(
					'radius_hotel_booking_index_page_' . $this->getResourceType(),
					$request->get_param( 'page' ) ? $request->get_param( 'page' ) : 1,
					$request,
					$this
				);

				$perPage = apply_filters(
					'radius_hotel_booking_index_per_page_' . $this->getResourceType(),
					$request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 15,
					$request,
					$this
				);

				$orderBy = apply_filters(
					'radius_hotel_booking_index_order_by_' . $this->getResourceType(),
					$request->get_param( 'order_by' ) ? $request->get_param( 'order_by' ) : 'created_at',
					$request,
					$this
				);

				$order        = apply_filters(
					'radius_hotel_booking_index_order_' . $this->getResourceType(),
					$request->get_param( 'order' ) ? $request->get_param( 'order' ) : 'desc',
					$request,
					$this
				);
				$searchColumn = apply_filters( 'rtbp_index_search_column_' . $this->getResourceType(), $request->get_param( 'search_column' ), $request, $this );
				$searchData   = apply_filters( 'rtbp_index_search_data_' . $this->getResourceType(), $request->get_param( 'search_data' ), $request, $this );
				$searchData   = is_array( $searchData ) ? implode( ',', $searchData ) : $searchData;
				$searchData   = $searchData ? $searchData : null;
				$where        = apply_filters( 'rtbp_index_query_condition_' . $this->getResourceType(), $request->get_param( 'condition' ), $request, $this );
				$result       = $this->repository->paginate( $perPage, $page, $order, $orderBy, $searchColumn, $searchData, $where );
				/**
				 * Filter the items before returning in index endpoint.
				 */
				$items = apply_filters( 'rtbp_index_items_' . $this->getResourceType(), $result->items, $this );

				return ApiResponse::success(
					$this->transformCollection( $items ),
					null,
					array(
						'pagination' => array(
							'current_page'   => $result->currentPage,
							'last_page'      => $result->lastPage,
							'per_page'       => $result->perPage,
							'total'          => $result->total,
							'has_more_pages' => $result->hasMorePages,
						),
					)
				)->send();
			}
		);
	}

	/**
	 * GET /resource/{id} — Show a single item.
	 *
	 * @param WP_REST_Request $request The current REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function show( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				$id   = $request->get_param( 'id' );
				$item = $this->repository->find( $id );

				if ( ! $item ) {
					return ApiResponse::notFound()->send();
				}

				/**
				 * Filter the single item before returning it.
				 */
				$item = apply_filters( 'rtbp_show_item_' . $this->getResourceType(), $item, $id, $this );

				return ApiResponse::success( $this->transformItem( $item ) )->send();
			}
		);
	}

	/**
	 * POST /resource — Create a new item.
	 *
	 * @param WP_REST_Request $request The current REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function store( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				$validationData = $this->validateRequest( $request, $this->getValidationRulesWithContext( 'create', $request ) );
				$validation     = apply_filters( 'rtbp_store_validation_rules_' . $this->getResourceType(), $validationData, $request, $this );
				if ( $validation ) {
					return $validation;
				}

				try {
					$data = $this->prepareDataForStorage( $request, 'create' );
					$item = $this->repository->create( $data );

					/**
					 * Fires after a new item is created.
					 */
					do_action( 'rtbp_item_created_' . $this->getResourceType(), $data, $item, $request, $this );

					$item = apply_filters( 'rtbp_created_item_' . $this->getResourceType(), $item, $item->id, $this );

					return ApiResponse::created( $this->transformItem( $item ) )->send();
				} catch ( Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * PUT/PATCH /resource/{id} — Update an existing item.
	 *
	 * @param WP_REST_Request $request The current REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function update( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				$id = $request->get_param( 'id' );

				if ( ! $this->repository->exists( $id ) ) {
					return ApiResponse::notFound()->send();
				}

				$validation = $this->validateRequest( $request, $this->getValidationRulesWithContext( 'update', $request ) );
				if ( $validation ) {
					return $validation;
				}

				try {
					$data = $this->prepareDataForStorage( $request, 'update' );
					$this->repository->update( $id, $data );
					$item = $this->repository->find( $id );

					/**
					 * Fires after an item is updated.
					 */
					do_action( 'rtbp_item_updated_' . $this->getResourceType(), $item, $request, $this );

					do_action( 'rtbp_item_updated_' . $this->getResourceType() . '_with_data', $data, $item, $request, $this );

					$item = apply_filters( 'rtbp_updated_item_' . $this->getResourceType(), $item, $item->id, $this );

					return ApiResponse::success( $this->transformItem( $item ) )->send();
				} catch ( Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * DELETE /resource/{id} — Delete an item.
	 *
	 * @param WP_REST_Request $request The current REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function destroy( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			function ( $request ) {
				$id = $request->get_param( 'id' );

				if ( ! $this->repository->exists( $id ) ) {
					return ApiResponse::notFound()->send();
				}

				try {
					$this->repository->delete( $id );

					/**
					 * Fires after an item is deleted.
					 */
					do_action( 'rtbp_item_deleted_' . $this->getResourceType(), $id, $request, $this );

					return ApiResponse::success( array(), 'Resource deleted successfully' )->send();
				} catch ( Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * Prepare data for storage (override in child classes for custom logic).
	 *
	 * @param WP_REST_Request $request The current request.
	 * @param string $context The operation context ('create' or 'update').
	 *
	 * @return array
	 */
	protected function prepareDataForStorage( WP_REST_Request $request, string $context ): array {
		$data = $request->get_params();

		// Merge JSON params if they exist
		if ( 'application/json' === $request->get_content_type()['value'] ) {
			$json_data = $request->get_json_params();
			if ( is_array( $json_data ) ) {
				$data = array_merge( $data, $json_data );
			}
		}

		return $data;
	}

	/**
	 * Transform a single item to an API-friendly array.
	 *
	 * @param mixed $item The item to transform.
	 *
	 * @return array
	 */
	abstract protected function transformItem( $item ): array;

	/**
	 * Transform a collection of items to an API-friendly array.
	 *
	 * @param array $items The collection of items to transform.
	 *
	 * @return array
	 */
	abstract protected function transformCollection( array $items ): array;

	/**
	 * Get validation rules based on request context (create/update).
	 *
	 * @param object $request The object
	 * @param string $context The context for validation rules, either 'create' or 'update'.
	 *
	 * @return array
	 */
	abstract protected function getValidationRules( $request, string $context = 'create' );

	/**
	 * Get the resource type for hooks and filters.
	 *
	 * @return string
	 */
	abstract protected function getResourceType(): string;
}
