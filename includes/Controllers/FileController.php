<?php
/**
 * Protected file download.
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Storage\ProtectedFiles;
use WP_REST_Request;

/**
 * GET /files/{token} — stream a protected file to a user allowed to see it.
 *
 * This endpoint is reached by plain links (`ProtectedFiles::url()`), which
 * cannot send the `X-WP-Nonce` header PermissionMiddleware requires, so it
 * authenticates with the `_wpnonce` query parameter WordPress's REST cookie
 * check reads, then checks the file kind's capability itself. A GET that only
 * reads needs no Referer check.
 *
 * Missing, unknown and forbidden files all answer 404, so a token cannot be
 * probed to learn whether a file exists.
 */
class FileController {

	/**
	 * Stream the file.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed A REST response on failure; exits after streaming on success.
	 */
	public function download( WP_REST_Request $request ) {
		return ( new AuthMiddleware() )->handle(
			$request,
			function ( $request ) {
				$file = ProtectedFiles::get( (string) $request->get_param( 'token' ) );

				if ( ! $file || ! ProtectedFiles::can_access( $file ) ) {
					return ApiResponse::notFound( __( 'File not found.', 'radius-hotel-booking' ) )->withCode( 'file_not_found' )->send();
				}

				$path = ProtectedFiles::path( $file );
				if ( ! is_readable( $path ) ) {
					return ApiResponse::notFound( __( 'File not found.', 'radius-hotel-booking' ) )->withCode( 'file_not_found' )->send();
				}

				/**
				 * Fires before a protected file is sent (M14 records downloads).
				 *
				 * @param \RadiusTheme\RadiusHotelBooking\Models\StoredFile $file File.
				 */
				do_action( 'rtbp_file_download', $file );

				$this->stream( $path, (string) $file->original_name, (string) $file->mime, (bool) $request->get_param( 'inline' ) );
				exit;
			}
		);
	}

	/**
	 * Send headers and the file body.
	 *
	 * @param string $path   Absolute path.
	 * @param string $name   Download name.
	 * @param string $mime   MIME type.
	 * @param bool   $inline Show instead of download.
	 * @return void
	 */
	private function stream( string $path, string $name, string $mime, bool $inline ): void {
		// Discard anything buffered so far so it can't corrupt the file.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		$disposition = $inline ? 'inline' : 'attachment';
		$ascii_name  = preg_replace( '/[^A-Za-z0-9._-]/', '_', $name );

		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( sprintf( 'Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s', $disposition, $ascii_name, rawurlencode( $name ) ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox" );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a protected file.
		readfile( $path );
	}
}
