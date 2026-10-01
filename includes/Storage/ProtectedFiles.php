<?php
/**
 * Protected file storage.
 *
 * @package RadiusTheme\RadiusHotelBooking\Storage
 */

namespace RadiusTheme\RadiusHotelBooking\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\StoredFile;
use RadiusTheme\RadiusHotelBooking\Repositories\StoredFileRepository;
use RuntimeException;

/**
 * Files that must never be publicly reachable (ADR-009): invoices, receipts,
 * exports and archives, employee documents.
 *
 * - Stored under `uploads/radius-hotel-booking/<kind>/` with a random
 *   32-hex name — independent of the download token — and the original
 *   extension; the folder carries `.htaccess`,
 *   `web.config` and `index.php` denying direct access. nginx ignores those:
 *   there the 128-bit random names are the protection, unless the host adds
 *   a deny rule or sets RTBP_PROTECTED_DIR outside the web root (base_dir()).
 * - Indexed in the `files` table. Other records store the file's `token`.
 * - Downloaded only through `GET /files/{token}` (FileController), which
 *   checks the capability the file's kind requires.
 *
 * Kinds are registered with the `rtbp_file_kinds` filter:
 *     $kinds['invoice'] = array( 'capability' => Capabilities::VIEW_DASHBOARD );
 * An unregistered kind cannot be stored. M13 replaces capabilities with
 * access keys (`rtbp_file_access`).
 */
class ProtectedFiles {

	/**
	 * Folder under uploads.
	 */
	const DIR = 'radius-hotel-booking';

	/**
	 * Registered kinds: kind => { capability }.
	 *
	 * @return array<string,array{capability:string}>
	 */
	public static function kinds(): array {
		$kinds = (array) apply_filters( 'rtbp_file_kinds', array() );

		$valid = array();
		foreach ( $kinds as $kind => $config ) {
			if ( is_string( $kind ) && preg_match( '/^[a-z0-9_]{1,40}$/', $kind ) ) {
				$valid[ $kind ] = array(
					'capability' => (string) ( ( (array) $config )['capability'] ?? Capabilities::MANAGE_SETTINGS ),
				);
			}
		}

		return $valid;
	}

	/**
	 * Store raw contents as a new protected file.
	 *
	 * @param string $kind     Registered kind.
	 * @param string $contents File contents.
	 * @param string $filename Original name, shown when downloading.
	 * @param string $mime     MIME type; guessed from the name when empty.
	 * @return StoredFile
	 * @throws DomainException When the kind is unknown or the file cannot be written.
	 */
	public static function put( string $kind, string $contents, string $filename, string $mime = '' ): StoredFile {
		$target = self::new_target( $kind, $filename );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing into our own protected folder.
		if ( false === file_put_contents( $target['absolute'], $contents, LOCK_EX ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'file_write_failed', __( 'The file could not be saved.', 'radius-hotel-booking' ), 500 );
		}

		return self::index( $kind, $target, $filename, $mime );
	}

	/**
	 * Store an existing file (an upload, or an export written to a temp path).
	 *
	 * @param string $kind     Registered kind.
	 * @param string $source   Absolute path of the file to take in.
	 * @param string $filename Original name, shown when downloading.
	 * @param bool   $move     Move instead of copy (the source disappears).
	 * @param string $mime     MIME type; guessed from the name when empty.
	 * @return StoredFile
	 * @throws DomainException When the kind is unknown or the file cannot be stored.
	 */
	public static function put_file( string $kind, string $source, string $filename, bool $move = false, string $mime = '' ): StoredFile {
		if ( ! is_readable( $source ) || ! is_file( $source ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'file_missing', __( 'The file to store was not found.', 'radius-hotel-booking' ), 500 );
		}

		$target = self::new_target( $kind, $filename );
		$done   = $move
			? rename( $source, $target['absolute'] ) // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			: copy( $source, $target['absolute'] );

		if ( ! $done ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'file_write_failed', __( 'The file could not be saved.', 'radius-hotel-booking' ), 500 );
		}

		return self::index( $kind, $target, $filename, $mime );
	}

	/**
	 * A file by token, or null.
	 *
	 * @param string $token Token.
	 * @return StoredFile|null
	 */
	public static function get( string $token ): ?StoredFile {
		if ( ! self::is_token( $token ) ) {
			return null;
		}
		return self::repository()->findByToken( $token );
	}

	/**
	 * Absolute path of a stored file.
	 *
	 * @param StoredFile $file File.
	 * @return string
	 */
	public static function path( StoredFile $file ): string {
		return trailingslashit( self::base_dir() ) . ltrim( (string) $file->path, '/' );
	}

	/**
	 * Delete a file from disk and from the index.
	 *
	 * @param string $token Token.
	 * @return bool Whether a file was deleted.
	 */
	public static function delete( string $token ): bool {
		$file = self::get( $token );
		if ( ! $file ) {
			return false;
		}

		$path = self::path( $file );
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}

		return (bool) self::repository()->delete( (int) $file->id );
	}

	/**
	 * Whether a user may download a file: logged in and holding the
	 * capability its kind requires (administrators always may).
	 *
	 * @param StoredFile $file    File.
	 * @param int|null   $user_id User; the current user when null.
	 * @return bool
	 */
	public static function can_access( StoredFile $file, ?int $user_id = null ): bool {
		$user_id = null === $user_id ? get_current_user_id() : $user_id;
		$kinds   = self::kinds();
		$kind    = (string) $file->kind;

		$allowed = $user_id > 0
			&& isset( $kinds[ $kind ] )
			&& ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, $kinds[ $kind ]['capability'] ) );

		/**
		 * Filters whether a user may download a protected file. M13 hooks in
		 * here to apply access keys; a module may narrow access further (e.g.
		 * staff may only download their own documents).
		 *
		 * @param bool       $allowed Decision so far.
		 * @param StoredFile $file    File.
		 * @param int        $user_id User.
		 */
		return (bool) apply_filters( 'rtbp_file_access', $allowed, $file, $user_id );
	}

	/**
	 * Download URL for the current user (carries the REST nonce so a plain
	 * link works). Pass `$inline` to display PDFs in the browser.
	 *
	 * @param string $token  Token.
	 * @param bool   $inline Show instead of download.
	 * @return string
	 */
	public static function url( string $token, bool $inline = false ): string {
		$args = array( '_wpnonce' => wp_create_nonce( 'wp_rest' ) );
		if ( $inline ) {
			$args['inline'] = 1;
		}
		return add_query_arg( $args, rest_url( 'radius-hotel-booking/v1/files/' . rawurlencode( $token ) ) );
	}

	/**
	 * Absolute base folder (created and protected on first use).
	 *
	 * @return string
	 */
	public static function base_dir(): string {
		/*
		 * Hosts can keep the files outside the public web folder entirely
		 * (the safest setup, and the only full protection on nginx, which
		 * ignores .htaccess): define( 'RTBP_PROTECTED_DIR', '/srv/private/rtbp' )
		 * in wp-config.php. Downloads work the same either way.
		 */
		if ( defined( 'RTBP_PROTECTED_DIR' ) && is_string( RTBP_PROTECTED_DIR ) && '' !== RTBP_PROTECTED_DIR ) {
			return untrailingslashit( RTBP_PROTECTED_DIR );
		}

		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . self::DIR;
	}

	/**
	 * Create a folder with the deny-all guards.
	 *
	 * @param string $dir Absolute folder.
	 * @return void
	 * @throws RuntimeException When the folder cannot be created.
	 */
	public static function ensure_protected_dir( string $dir ): void {
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new RuntimeException( 'Could not create the protected storage folder.' );
		}

		$guards = array(
			'.htaccess'  => "# Radius Hotel Booking: files are served only through the REST API.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\nOptions -Indexes\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
		);

		foreach ( $guards as $name => $content ) {
			$path = trailingslashit( $dir ) . $name;
			if ( ! file_exists( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guard files in our own folder.
				file_put_contents( $path, $content );
			}
		}
	}

	/**
	 * Pick the folder and random name for a new file.
	 *
	 * @param string $kind     Kind.
	 * @param string $filename Original name (for the extension).
	 * @return array{token:string,relative:string,absolute:string}
	 * @throws DomainException When the kind is not registered.
	 */
	private static function new_target( string $kind, string $filename ): array {
		if ( ! isset( self::kinds()[ $kind ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'unknown_file_kind', __( 'This type of file cannot be stored.', 'radius-hotel-booking' ), 500 );
		}

		self::ensure_protected_dir( self::base_dir() );
		self::ensure_protected_dir( trailingslashit( self::base_dir() ) . $kind );

		$token     = bin2hex( random_bytes( 16 ) );
		$extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		$extension = preg_match( '/^[a-z0-9]{1,8}$/', $extension ) ? '.' . $extension : '';
		// The name on disk is its own secret, never the token: the token travels
		// in download links (history, logs, referrers), and where the server
		// ignores .htaccess (nginx) a name derived from it would make the file
		// reachable without signing in (M11 review of ADR-009).
		$relative = $kind . '/' . bin2hex( random_bytes( 16 ) ) . $extension;

		return array(
			'token'    => $token,
			'relative' => $relative,
			'absolute' => trailingslashit( self::base_dir() ) . $relative,
		);
	}

	/**
	 * Give every file still named after its download token on disk a random
	 * name of its own (files stored before M11; see new_target()). Run once,
	 * by the installer.
	 *
	 * @return int Files renamed.
	 */
	public static function rename_token_named(): int {
		global $wpdb;
		$renamed = 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-off upgrade over our own index.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, token, path FROM %i', rtbp_table( 'files' ) ), ARRAY_A );
		foreach ( $rows as $row ) {
			$path = (string) $row['path'];
			if ( pathinfo( $path, PATHINFO_FILENAME ) !== (string) $row['token'] ) {
				continue;
			}
			$extension = pathinfo( $path, PATHINFO_EXTENSION );
			$relative  = trailingslashit( dirname( $path ) ) . bin2hex( random_bytes( 16 ) ) . ( '' !== $extension ? '.' . $extension : '' );
			$from      = trailingslashit( self::base_dir() ) . $path;
			$to        = trailingslashit( self::base_dir() ) . $relative;
			if ( is_file( $from ) && rename( $from, $to ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- inside our own protected folder.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- our own index.
				$wpdb->update( rtbp_table( 'files' ), array( 'path' => $relative ), array( 'id' => (int) $row['id'] ) );
				++$renamed;
			}
		}
		return $renamed;
	}

	/**
	 * Record a written file in the index.
	 *
	 * @param string $kind     Kind.
	 * @param array  $target   From new_target().
	 * @param string $filename Original name.
	 * @param string $mime     MIME type or ''.
	 * @return StoredFile
	 */
	private static function index( string $kind, array $target, string $filename, string $mime ): StoredFile {
		if ( '' === $mime ) {
			$type = wp_check_filetype( $filename );
			$mime = $type['type'] ? (string) $type['type'] : 'application/octet-stream';
		}

		$file = self::repository()->create(
			array(
				'token'         => $target['token'],
				'kind'          => $kind,
				'path'          => $target['relative'],
				'original_name' => self::display_name( $filename ),
				'mime'          => $mime,
				'size'          => (int) filesize( $target['absolute'] ),
				'sha256'        => (string) hash_file( 'sha256', $target['absolute'] ),
				'created_by'    => get_current_user_id() ? get_current_user_id() : null,
			)
		);

		return $file instanceof StoredFile ? $file : StoredFile::hydrate( (array) $file );
	}

	/**
	 * The name shown when downloading. Keeps accents and spaces (French
	 * invoice names), strips only path separators, control and reserved
	 * characters; the name is never used as a path.
	 *
	 * @param string $filename Original name.
	 * @return string
	 */
	private static function display_name( string $filename ): string {
		$name = (string) preg_replace( '/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '-', wp_basename( $filename ) );
		$name = trim( $name, ' .-' );
		return '' !== $name ? mb_substr( $name, 0, 200 ) : 'file';
	}

	/**
	 * Whether a string looks like a token.
	 *
	 * @param string $token Candidate.
	 * @return bool
	 */
	private static function is_token( string $token ): bool {
		return 1 === preg_match( '/^[a-f0-9]{32}$/', $token );
	}

	/**
	 * Repository.
	 *
	 * @return StoredFileRepository
	 */
	private static function repository(): StoredFileRepository {
		return new StoredFileRepository();
	}
}
