<?php
/**
 * Report endpoints (M10).
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AccessMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Services\Reports\ReportService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `GET reports/{name}?from&to&mode` — any report of the
 * `rtbp_report_definitions` registry, behind the access key its definition
 * names (`page.reports_sales`, `page.reports_rooms`, or an add-on's).
 */
class ReportController {

	/**
	 * GET reports/{name}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function show( WP_REST_Request $request ) {
		$auth       = new AuthMiddleware();
		$permission = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );

		return $auth->handle(
			$request,
			fn( $request ) => $permission->handle(
				$request,
				function ( $request ) {
					$name = (string) $request->get_param( 'name' );
					try {
						$definition = ReportService::definition( $name );
					} catch ( \Throwable $e ) {
						return ApiResponse::fromThrowable( $e )->send();
					}
					return ( new AccessMiddleware( (string) $definition['access'] ) )->handle(
						$request,
						function ( $request ) use ( $name ) {
							try {
								$input = array_map( 'sanitize_text_field', array_filter( (array) $request->get_query_params(), 'is_scalar' ) );
								return ApiResponse::success( ( new ReportService() )->run( $name, $input ) )->send();
							} catch ( \Throwable $e ) {
								return ApiResponse::fromThrowable( $e )->send();
							}
						}
					);
				}
			)
		);
	}

	/**
	 * GET reports/{name}/export?format=&from&to&mode — the report as a file,
	 * behind the report's own key **and** `reports.export`. The file comes back
	 * in the JSON envelope (`{ filename, mime, content: base64 }`) so the admin
	 * app's client (nonce, passcode retry) handles it like any other call.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function export( WP_REST_Request $request ) {
		$auth       = new AuthMiddleware();
		$permission = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );

		return $auth->handle(
			$request,
			fn( $request ) => $permission->handle(
				$request,
				function ( $request ) {
					$name = (string) $request->get_param( 'name' );
					try {
						$definition = ReportService::definition( $name );
					} catch ( \Throwable $e ) {
						return ApiResponse::fromThrowable( $e )->send();
					}
					return ( new AccessMiddleware( (string) $definition['access'] ) )->handle(
						$request,
						fn( $request ) => ( new AccessMiddleware( 'reports.export' ) )->handle(
							$request,
							function ( $request ) use ( $name ) {
								try {
									$input = array_map( 'sanitize_text_field', array_filter( (array) $request->get_query_params(), 'is_scalar' ) );
									$file  = ( new ReportService() )->export( $name, (string) ( $input['format'] ?? 'csv' ), $input );
									return ApiResponse::success(
										array(
											'filename' => $file['filename'],
											'mime'     => $file['mime'],
											'content'  => base64_encode( $file['content'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a file sent inside JSON, not obfuscation.
										)
									)->send();
								} catch ( \Throwable $e ) {
									return ApiResponse::fromThrowable( $e )->send();
								}
							}
						)
					);
				}
			)
		);
	}
}
