<?php
/**
 * Pricing API controller.
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
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRateRepository;
use RadiusTheme\RadiusHotelBooking\Services\Pricing\PriceResolver;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `POST pricing/quote` (M07): the price simulator. Staff with `page.rates`
 * can see what a stay costs and why. Writes nothing.
 */
class PricingController extends BaseController {

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( RoomTypeRateRepository::class ) );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware( array( 'quote' => 'page.rates' ) );
	}

	/**
	 * POST pricing/quote: `{ room_type_id, rate_plan_id, arrival, units?,
	 * checkin_time?, booked_at? }` → the price, per unit, with every step.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function quote( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				try {
					$input   = (array) $request->get_json_params();
					$arrival = (string) ( $input['arrival'] ?? '' );
					if ( ! Dates::is_date( $arrival ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
						throw DomainException::invalid( array( 'arrival' => __( 'Choose the arrival date.', 'radius-hotel-booking' ) ) );
					}
					$booked = (string) ( $input['booked_at'] ?? '' );
					if ( '' !== $booked && ! Dates::is_date( $booked ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
						throw DomainException::invalid( array( 'booked_at' => __( 'Choose a valid booking date.', 'radius-hotel-booking' ) ) );
					}
					$units = max( 1, absint( $input['units'] ?? 1 ) );

					$quote = Container::resolve( PriceResolver::class )->quote(
						absint( $input['room_type_id'] ?? 0 ),
						absint( $input['rate_plan_id'] ?? 0 ),
						$arrival,
						$units,
						isset( $input['checkin_time'] ) && '' !== $input['checkin_time'] ? (string) $input['checkin_time'] : null,
						'' !== $booked ? $booked : null
					);
					return ApiResponse::success( array( 'quote' => $quote ) )->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * Unused (no collection).
	 *
	 * @param mixed $item Item.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return (array) $item;
	}

	/**
	 * Unused (no collection).
	 *
	 * @param array $items Items.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return $items;
	}

	/**
	 * No request rules: the resolver validates.
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
		return 'PriceQuote';
	}
}
