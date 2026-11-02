<?php
/**
 * Export endpoints (M11).
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AccessMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Services\Export\ExportService;
use RadiusTheme\RadiusHotelBooking\Services\Export\ExportWriter;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `GET exports` — the library (`page.exports`), with the kinds and formats
 * the screen offers; `POST exports` — generate a file (`exports.generate`);
 * `DELETE exports/{id}` — delete one (`exports.delete`). Downloads go through
 * `GET files/{token}` (`exports.download`, see `ExportService`).
 */
class ExportController {

	/**
	 * Run a handler behind auth, the dashboard capability and an access key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $key     Access key.
	 * @param callable        $handler Receives the request, returns the response.
	 * @return mixed
	 */
	private function guarded( WP_REST_Request $request, string $key, callable $handler ) {
		$auth       = new AuthMiddleware();
		$permission = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$access     = new AccessMiddleware( $key );
		return $auth->handle(
			$request,
			fn( $request ) => $permission->handle(
				$request,
				fn( $request ) => $access->handle(
					$request,
					function ( $request ) use ( $handler ) {
						try {
							return $handler( $request );
						} catch ( \Throwable $e ) {
							return ApiResponse::fromThrowable( $e )->send();
						}
					}
				)
			)
		);
	}

	/**
	 * GET exports?page=&per_page=.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function index( WP_REST_Request $request ) {
		return $this->guarded(
			$request,
			'page.exports',
			function ( $request ) {
				$list = ( new ExportService() )->list( (int) ( $request->get_param( 'page' ) ?? 1 ), (int) ( $request->get_param( 'per_page' ) ?? 20 ) );
				$list['kinds']   = array_map(
					static fn( $kind, $key ) => array(
						'key'    => $key,
						'label'  => (string) $kind['label'],
						'period' => false !== ( $kind['period'] ?? true ),
					),
					ExportService::kinds(),
					array_keys( ExportService::kinds() )
				);
				$list['formats'] = array_values(
					array_map(
						static fn( $format, $key ) => array(
							'key'   => $key,
							'label' => (string) ( $format['label'] ?? strtoupper( $key ) ),
						),
						ExportWriter::streamed(),
						array_keys( ExportWriter::streamed() )
					)
				);
				return ApiResponse::success( $list )->send();
			}
		);
	}

	/**
	 * POST exports { kind, from, to, mode, format }.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function store( WP_REST_Request $request ) {
		return $this->guarded(
			$request,
			'exports.generate',
			function ( $request ) {
				$input = array_map( 'sanitize_text_field', array_filter( (array) $request->get_params(), 'is_scalar' ) );
				$row   = ( new ExportService() )->generate( (string) ( $input['kind'] ?? 'bookings' ), $input );
				return ApiResponse::created( $row )->send();
			}
		);
	}

	/**
	 * DELETE exports/{id} — delete the file (`exports.delete`).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function destroy( WP_REST_Request $request ) {
		return $this->guarded(
			$request,
			'exports.delete',
			function ( $request ) {
				$row = ( new ExportService() )->delete( (int) $request->get_param( 'id' ) );
				return ApiResponse::success( $row, __( 'The export file was deleted.', 'radius-hotel-booking' ) )->send();
			}
		);
	}

	/**
	 * POST exports/restore (`exports.restore`, R1-T16) — restore the bookings
	 * of an export file that are not on the site, in batches:
	 *
	 * - `mode=preview` + `file` (a bookings export CSV) or `export_id` (one in
	 *   the file library): keeps the file for a session and returns what would
	 *   happen, with `session`;
	 * - `mode=run` + `session`: restores the next orders (one batch, a few
	 *   seconds; the session keeps its place) and returns `done` and `totals`;
	 * - `mode=cancel` + `session`: stops, deletes the kept file.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function restore( WP_REST_Request $request ) {
		return $this->guarded(
			$request,
			'exports.restore',
			function ( $request ) {
				$restore = new \RadiusTheme\RadiusHotelBooking\Services\Export\BookingRestore();
				$mode    = sanitize_key( (string) $request->get_param( 'mode' ) );
				$session = (string) $request->get_param( 'session' );
				if ( 'run' === $mode ) {
					return ApiResponse::success( $restore->runBatch( $session ) )->send();
				}
				if ( 'cancel' === $mode ) {
					$restore->cancel( $session );
					return ApiResponse::success( null, __( 'Restore stopped.', 'radius-hotel-booking' ) )->send();
				}
				// A bookings CSV already in the file library.
				if ( absint( $request->get_param( 'export_id' ) ) ) {
					return ApiResponse::success( $restore->startFromExport( absint( $request->get_param( 'export_id' ) ) ) )->send();
				}
				$files = (array) $request->get_file_params();
				$file  = (array) ( $files['file'] ?? array() );
				if ( in_array( (int) ( $file['error'] ?? 0 ), array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
					/* translators: %s: largest upload the server takes, e.g. 8 MB. */
					return ApiResponse::validationError( array( 'file' => array( sprintf( __( 'The file is larger than this server accepts (%s): export a shorter period, or ask the host to raise the upload limit.', 'radius-hotel-booking' ), size_format( wp_max_upload_size() ) ) ) ) )->send();
				}
				if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
					return ApiResponse::validationError( array( 'file' => array( __( 'Choose a bookings export file (CSV).', 'radius-hotel-booking' ) ) ) )->send();
				}
				if ( (int) ( $file['size'] ?? 0 ) > 50 * MB_IN_BYTES || ! preg_match( '/\.csv$/i', (string) ( $file['name'] ?? '' ) ) ) {
					return ApiResponse::validationError( array( 'file' => array( __( 'Choose a CSV file of at most 50 MB, made by Exports → Bookings.', 'radius-hotel-booking' ) ) ) )->send();
				}
				return ApiResponse::success( $restore->start( (string) $file['tmp_name'], (string) $file['name'] ) )->send();
			}
		);
	}
}
