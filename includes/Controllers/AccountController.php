<?php
/**
 * The signed-in staff member's own account settings.
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Services\Account\UserLanguage;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `POST me/language` — any dashboard user, on their own account (R1-T6).
 */
class AccountController {

	/**
	 * POST me/language `{ locale }` → `{ locale }`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function language( WP_REST_Request $request ) {
		$auth       = new AuthMiddleware();
		$permission = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );

		return $auth->handle(
			$request,
			fn( $request ) => $permission->handle(
				$request,
				function ( $request ) {
					try {
						$locale = UserLanguage::set( get_current_user_id(), sanitize_text_field( (string) $request->get_param( 'locale' ) ) );
						return ApiResponse::success( array( 'locale' => $locale ), __( 'Language changed.', 'radius-hotel-booking' ) )->send();
					} catch ( \Throwable $e ) {
						return ApiResponse::fromThrowable( $e )->send();
					}
				}
			)
		);
	}
}
