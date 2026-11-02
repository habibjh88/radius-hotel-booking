<?php
/**
 * Restore bookings from a bookings export file (the hotel's backup).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Export
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Export;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomRepository;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingService;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingStatusService;
use RadiusTheme\RadiusHotelBooking\Services\Guests\GuestService;
use RadiusTheme\RadiusHotelBooking\Services\Reports\ReportService;
use RadiusTheme\RadiusHotelBooking\Storage\ProtectedFiles;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Exports → Restore bookings (R1-T16, client answer Q5): a bookings CSV made
 * by Exports is read back, and every booking whose order ID is **not** on
 * the site is re-created — guest, rooms, stay, original price, amount paid —
 * with its original reference. Bookings already on the site are never
 * touched, so the same file can be restored twice. A preview says what will
 * happen first.
 *
 * Columns are read by position (BookingExport::columns() order), as their
 * headers follow each person's language. Re-created bookings go through the
 * locked booking write path in import mode: a room already taken at that
 * time is refused, never double-booked. Ended stays are re-created checked
 * in, then checked out at their end; cancelled, declined and no-show rooms
 * are re-created and cancelled with their reason.
 *
 * A large file (10 000 bookings and more) is restored in batches: the upload
 * is kept as a protected file for a *session* (`start()`) — a copy with
 * each order's rows put together, as an export lists rooms, not orders —
 * and each
 * `runBatch()` call restores the next orders until a time budget is spent,
 * so no request outlives a host's time limit. Stopping is safe: running the
 * file again skips what is already back.
 */
class BookingRestore {

	/**
	 * Column positions in the export (BookingExport::columns()).
	 */
	private const ORDER_ID   = 0;
	private const CREATED    = 2;
	private const METHOD     = 3;
	private const FIRST_NAME = 9;
	private const LAST_NAME  = 10;
	private const EMAIL      = 11;
	private const PHONE      = 12;
	private const ID_TYPE    = 14;
	private const ID_NUMBER  = 15;
	private const ROOM       = 18;
	private const RATE_PLAN  = 20;
	private const CHECK_IN   = 21;
	private const CHECK_OUT  = 22;
	private const STATUS     = 24;
	private const LINE_TOTAL = 28;
	private const NOTE       = 30;
	private const SOURCE     = 31;
	private const ADULTS     = 32;
	private const CHILDREN   = 33;
	private const PAID       = 34;
	private const REASON     = 38;

	/**
	 * Most data rows read from one file (one row per booked room).
	 */
	public const MAX_ROWS = 200000;

	/**
	 * Seconds of work in one batch request (well under common host limits).
	 */
	public const BATCH_SECONDS = 15;

	/**
	 * Most orders in one batch request.
	 */
	public const BATCH_ORDERS = 250;

	/**
	 * Most orders listed in a preview (the counts cover the whole file).
	 */
	public const PREVIEW_ORDERS = 300;

	/**
	 * Transient prefix of a restore session.
	 */
	private const SESSION = 'rtbp_restore_';

	/**
	 * Order IDs on the site, preloaded for a preview (reference => true), or null.
	 *
	 * @var array<string, true>|null
	 */
	private ?array $existing = null;

	/**
	 * Open a bookings export CSV after its header: `[ handle, delimiter ]`.
	 *
	 * @param string $path File.
	 * @return array{0: resource, 1: string}
	 * @throws DomainException 422 when it is not a bookings export.
	 */
	private static function open( string $path ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading the kept upload.
		$handle = is_readable( $path ) ? fopen( $path, 'r' ) : false;
		if ( ! $handle ) {
			throw DomainException::invalid( array( 'file' => __( 'The file could not be read.', 'radius-hotel-booking' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON.
		}
		$first = preg_replace( '/^\xEF\xBB\xBF/', '', (string) fgets( $handle ) );
		// Comma or semicolon, as Settings → Exports allows.
		$delimiter = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';
		if ( count( str_getcsv( $first, $delimiter, '"', '\\' ) ) < count( BookingExport::columns() ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
			throw DomainException::invalid( array( 'file' => __( 'This is not a bookings export: choose a CSV made by Exports → Bookings.', 'radius-hotel-booking' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON.
		}
		return array( $handle, $delimiter );
	}

	/**
	 * The orders of a file, read row by row (memory stays flat whatever its
	 * size): yields `[ reference, rows, end ]`, `end` being the byte offset
	 * after the order — where the next batch starts. Rows of an order are read
	 * as one order when they follow each other: start() writes the kept copy
	 * that way (groupFile()).
	 *
	 * @param string $path  File.
	 * @param int    $start Byte offset to start at (0: after the header).
	 * @return \Generator
	 * @throws DomainException 422 when it is not a bookings export or is too long.
	 */
	private static function orders( string $path, int $start = 0 ): \Generator {
		list( $handle, $delimiter ) = self::open( $path );
		if ( $start > 0 ) {
			fseek( $handle, $start );
		}
		$expected = count( BookingExport::columns() );
		$current  = '';
		$lines    = array();
		$count    = 0;
		try {
			while ( true ) {
				$position = ftell( $handle );
				$cells    = fgetcsv( $handle, 0, $delimiter, '"', '\\' );
				if ( false === $cells ) {
					break;
				}
				if ( array( null ) === $cells || '' === trim( implode( '', $cells ) ) ) {
					continue;
				}
				if ( ++$count > self::MAX_ROWS ) {
					/* translators: %s: most rows. */
					throw DomainException::invalid( array( 'file' => sprintf( __( 'The file has more than %s rows: split it by period.', 'radius-hotel-booking' ), number_format_i18n( self::MAX_ROWS ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON.
				}
				$row       = array_pad( array_map( 'strval', $cells ), $expected, '' );
				$reference = strtoupper( trim( $row[ self::ORDER_ID ] ) );
				if ( '' === $reference ) {
					continue;
				}
				if ( '' !== $current && $reference !== $current ) {
					yield array( $current, $lines, $position );
					$lines = array();
				}
				$current = $reference;
				$lines[] = $row;
			}
			if ( $lines ) {
				yield array( $current, $lines, ftell( $handle ) );
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
		}
	}

	/**
	 * What a restore of a file would do: `{ orders (the first PREVIEW_ORDERS,
	 * those that cannot be restored first), total, counts }`. The file's rows
	 * of an order follow each other (groupFile()).
	 *
	 * @param string $path File.
	 * @return array
	 * @throws DomainException 422 when it is not a bookings export, has no booking or is too long.
	 */
	public function plan( string $path ): array {
		$listed = array(
			'invalid' => array(),
			'other'   => array(),
		);
		$counts = array(
			'restore' => 0,
			'exists'  => 0,
			'invalid' => 0,
		);
		$buffer = array();
		$total  = 0;
		$flush  = function () use ( &$buffer, &$listed, &$counts ) {
			$this->existing = self::existingReferences( array_column( $buffer, 0 ) );
			foreach ( $buffer as list( $reference, $lines ) ) {
				list( $action, $reason ) = $this->check( $reference, $lines );
				++$counts[ $action ];
				$group = 'invalid' === $action ? 'invalid' : 'other';
				if ( count( $listed[ $group ] ) < self::PREVIEW_ORDERS ) {
					$listed[ $group ][] = array(
						'reference' => $reference,
						'guest'     => trim( $lines[0][ self::FIRST_NAME ] . ' ' . $lines[0][ self::LAST_NAME ] ),
						'rooms'     => count( $lines ),
						'arrival'   => $lines[0][ self::CHECK_IN ],
						'action'    => $action,
						'reason'    => $reason,
					);
				}
			}
			$this->existing = null;
			$buffer         = array();
		};
		foreach ( self::orders( $path ) as list( $reference, $lines ) ) {
			++$total;
			$buffer[] = array( $reference, $lines );
			if ( count( $buffer ) >= 500 ) {
				$flush();
			}
		}
		$flush();
		if ( ! $total ) {
			throw DomainException::invalid( array( 'file' => __( 'The file has no bookings.', 'radius-hotel-booking' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON.
		}
		return array(
			'orders' => array_slice( array_merge( $listed['invalid'], $listed['other'] ), 0, self::PREVIEW_ORDERS ),
			'total'  => $total,
			'counts' => $counts,
		);
	}

	/**
	 * Start a restore session: keep the upload, read it, and say what would happen.
	 *
	 * @param string $path     Uploaded temp file.
	 * @param string $filename Its name.
	 * @return array The plan, plus `session`.
	 * @throws DomainException 422 when it is not a bookings export.
	 */
	public function start( string $path, string $filename ): array {
		wp_raise_memory_limit( 'admin' );
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- reading a large file.
		}
		$grouped = self::groupFile( $path );
		try {
			$plan = $this->plan( $grouped );
			self::endSessionsOf( get_current_user_id() );
			$file = ProtectedFiles::put_file( ExportService::FILE_KIND, $grouped, 'restore-' . sanitize_file_name( $filename ), true, 'text/csv' );
		} finally {
			if ( file_exists( $grouped ) ) {
				wp_delete_file( $grouped );
			}
		}
		$session = wp_generate_password( 32, false );
		set_transient(
			self::SESSION . $session,
			array(
				'token'    => (string) $file->token,
				'user'     => get_current_user_id(),
				'offset'   => 0,
				'restored' => 0,
				'failed'   => 0,
				'skipped'  => 0,
			),
			DAY_IN_SECONDS
		);
		update_user_meta( get_current_user_id(), self::SESSION . 'session', $session );
		return $plan + array( 'session' => $session );
	}

	/**
	 * Start a restore session from a bookings CSV of the file library.
	 *
	 * @param int $export_id Library row.
	 * @return array As start().
	 * @throws DomainException 404 when the file is gone; 422 when it is not a bookings CSV.
	 */
	public function startFromExport( int $export_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one library row, read fresh.
		$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT kind, format, status, file_token FROM %i WHERE id = %d', rtbp_table( 'exports' ), $export_id ), ARRAY_A );
		$file = $row && 'done' === $row['status'] && '' !== (string) $row['file_token'] ? ProtectedFiles::get( (string) $row['file_token'] ) : null;
		if ( ! $file ) {
			throw new DomainException( 'not_found', __( 'This file is no longer in the library.', 'radius-hotel-booking' ), 404 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON.
		}
		if ( 'bookings' !== $row['kind'] || 'csv' !== $row['format'] ) {
			throw DomainException::invalid( array( 'file' => __( 'Only a bookings export in CSV can be restored.', 'radius-hotel-booking' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON.
		}
		return $this->start( ProtectedFiles::path( $file ), (string) $file->original_name );
	}

	/**
	 * Restore the next orders of a session, until BATCH_SECONDS or
	 * BATCH_ORDERS: `{ restored, failed, skipped, warnings, processed, done,
	 * totals }`. The session keeps where it is in the file (a byte offset), so
	 * a batch asked again after a lost response just carries on. The last
	 * batch logs the run and ends the session.
	 *
	 * @param string $session Session id.
	 * @return array
	 * @throws DomainException 404 when the session is gone or not this user's.
	 */
	public function runBatch( string $session ): array {
		$state = self::session( $session );
		$file  = ProtectedFiles::get( $state['token'] );
		$path  = $file ? ProtectedFiles::path( $file ) : '';
		if ( '' === $path || ! is_readable( $path ) ) {
			self::end( $session );
			throw new DomainException( 'restore_gone', __( 'The uploaded file is no longer there: choose it again.', 'radius-hotel-booking' ), 404 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON.
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 120 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- one batch.
		}
		$out   = array(
			'restored'  => array(),
			'failed'    => array(),
			'skipped'   => 0,
			'warnings'  => array(),
			'processed' => 0,
		);
		$until = microtime( true ) + self::BATCH_SECONDS;
		$next  = (int) ( $state['offset'] ?? 0 );
		$done  = true;
		foreach ( self::orders( $path, $next ) as list( $reference, $lines, $end ) ) {
			if ( $out['processed'] >= self::BATCH_ORDERS || ( $out['processed'] > 0 && microtime( true ) >= $until ) ) {
				$done = false;
				break;
			}
			$this->restoreOrder( $reference, $lines, $out );
			++$out['processed'];
			$next = $end;
		}

		$state['offset']     = $next;
		$state['processed']  = (int) ( $state['processed'] ?? 0 ) + $out['processed'];
		$state['restored']  += count( $out['restored'] );
		$state['failed']    += count( $out['failed'] );
		$state['skipped']   += $out['skipped'];
		if ( $done ) {
			$this->logRun( $state['restored'], $state['failed'], $state['skipped'] );
			self::end( $session );
		} else {
			set_transient( self::SESSION . $session, $state, DAY_IN_SECONDS );
		}
		return $out + array(
			'done'   => $done,
			'totals' => array(
				'processed' => $state['processed'],
				'restored'  => $state['restored'],
				'failed'    => $state['failed'],
				'skipped'   => $state['skipped'],
			),
		);
	}

	/**
	 * Stop a session: log what was restored so far, delete the kept file.
	 *
	 * @param string $session Session id.
	 * @return void
	 * @throws DomainException 404 when the session is gone or not this user's.
	 */
	public function cancel( string $session ): void {
		$state = self::session( $session );
		$this->logRun( (int) $state['restored'], (int) $state['failed'], (int) $state['skipped'] );
		self::end( $session );
	}

	/**
	 * A session of the current user.
	 *
	 * @param string $session Session id.
	 * @return array
	 * @throws DomainException 404.
	 */
	private static function session( string $session ): array {
		$state = preg_match( '/^[A-Za-z0-9]{32}$/', $session ) ? get_transient( self::SESSION . $session ) : false;
		if ( ! is_array( $state ) || (int) $state['user'] !== get_current_user_id() ) {
			throw new DomainException( 'restore_gone', __( 'This restore has ended: choose the file again.', 'radius-hotel-booking' ), 404 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON.
		}
		return $state;
	}

	/**
	 * End a session: its kept file and its state go.
	 *
	 * @param string $session Session id.
	 * @return void
	 */
	private static function end( string $session ): void {
		$state = get_transient( self::SESSION . $session );
		if ( is_array( $state ) && ! empty( $state['token'] ) ) {
			ProtectedFiles::delete( (string) $state['token'] );
		}
		delete_transient( self::SESSION . $session );
	}

	/**
	 * End a user's previous session (a new upload replaces it).
	 *
	 * @param int $user_id User.
	 * @return void
	 */
	private static function endSessionsOf( int $user_id ): void {
		$previous = (string) get_user_meta( $user_id, self::SESSION . 'session', true );
		if ( '' !== $previous ) {
			self::end( $previous );
			delete_user_meta( $user_id, self::SESSION . 'session' );
		}
	}

	/**
	 * Restore one order if it is missing, into a batch result.
	 *
	 * @param string  $reference Order ID.
	 * @param array[] $lines     Its rows.
	 * @param array   $out       Batch result (added to).
	 * @return void
	 */
	private function restoreOrder( string $reference, array $lines, array &$out ): void {
		list( $action, $reason ) = $this->check( $reference, $lines );
		if ( 'exists' === $action ) {
			++$out['skipped'];
			return;
		}
		if ( 'invalid' === $action ) {
			$out['failed'][] = array(
				'reference' => $reference,
				'reason'    => $reason,
			);
			return;
		}
		try {
			$warnings          = $this->restoreOne( $reference, $lines );
			$out['restored'][] = $reference;
			foreach ( $warnings as $message ) {
				$out['warnings'][] = array(
					'reference' => $reference,
					'message'   => $message,
				);
			}
		} catch ( \Throwable $e ) {
			$out['failed'][] = array(
				'reference' => $reference,
				'reason'    => $e->getMessage(),
			);
		}
	}

	/**
	 * A copy of a bookings CSV with each order's rows together, orders in the
	 * order they first appear (an export lists booked rooms, so the rooms of
	 * one booking can be far apart). Reads the file twice and keeps only each
	 * row's place in it — 12 bytes a row — so memory stays small.
	 *
	 * @param string $path File.
	 * @return string Temp file (the caller deletes it).
	 * @throws DomainException 422 when it is not a bookings export or is too long; 500 when the copy fails.
	 */
	private static function groupFile( string $path ): string {
		list( $handle, $delimiter ) = self::open( $path );
		$header_end                 = ftell( $handle );
		$places                     = array();
		$count                      = 0;
		while ( true ) {
			$start = ftell( $handle );
			$cells = fgetcsv( $handle, 0, $delimiter, '"', '\\' );
			if ( false === $cells ) {
				break;
			}
			if ( array( null ) === $cells || '' === trim( implode( '', $cells ) ) ) {
				continue;
			}
			if ( ++$count > self::MAX_ROWS ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose( $handle );
				/* translators: %s: most rows. */
				throw DomainException::invalid( array( 'file' => sprintf( __( 'The file has more than %s rows: split it by period.', 'radius-hotel-booking' ), number_format_i18n( self::MAX_ROWS ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON.
			}
			$reference = strtoupper( trim( (string) ( $cells[ self::ORDER_ID ] ?? '' ) ) );
			if ( '' === $reference ) {
				continue;
			}
			$places[ $reference ] = ( $places[ $reference ] ?? '' ) . pack( 'JN', $start, ftell( $handle ) - $start );
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$target = wp_tempnam( 'rtbp-restore' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- our own temp file.
		$out = fopen( $target, 'w' );
		if ( ! $out ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
			throw new DomainException( 'file_write_failed', __( 'The file could not be saved.', 'radius-hotel-booking' ), 500 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON.
		}
		// The header as it was (the BOM included), then each order's rows.
		rewind( $handle );
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fwrite( $out, (string) fread( $handle, max( 1, $header_end ) ) );
		foreach ( $places as $packed ) {
			foreach ( str_split( $packed, 12 ) as $place ) {
				$row = unpack( 'Jstart/Nlength', $place );
				fseek( $handle, $row['start'] );
				$bytes = (string) fread( $handle, $row['length'] );
				fwrite( $out, "\n" === substr( $bytes, -1 ) ? $bytes : $bytes . "\n" );
			}
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fread
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $out );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );
		return $target;
	}

	/**
	 * Log a restore run (nothing when no booking came back).
	 *
	 * @param int $restored Bookings restored.
	 * @param int $failed   Orders that failed.
	 * @param int $skipped  Orders already on the site.
	 * @return void
	 */
	private function logRun( int $restored, int $failed, int $skipped ): void {
		if ( ! $restored ) {
			return;
		}
		rtbp_activity(
			'exports.restore',
			null,
			array(
				'after'       => array(
					'restored' => $restored,
					'failed'   => $failed,
					'skipped'  => $skipped,
				),
				/* translators: %d: number of bookings. */
				'description' => sprintf( _n( 'Restored %d booking from a backup file', 'Restored %d bookings from a backup file', $restored, 'radius-hotel-booking' ), $restored ),
			)
		);
	}

	/**
	 * Whether an order can be restored: `[ action, reason ]`.
	 *
	 * @param string  $reference Order ID.
	 * @param array[] $lines     Its rows.
	 * @return array{0: string, 1: string}
	 */
	private function check( string $reference, array $lines ): array {
		if ( null !== $this->existing ? isset( $this->existing[ $reference ] ) : self::bookingExists( $reference ) ) {
			return array( 'exists', __( 'Already on the site: left as it is.', 'radius-hotel-booking' ) );
		}
		foreach ( $lines as $row ) {
			if ( ! self::roomId( (string) $row[ self::ROOM ] ) ) {
				/* translators: %s: room number. */
				return array( 'invalid', sprintf( __( 'Room %s is not on the site.', 'radius-hotel-booking' ), $row[ self::ROOM ] ) );
			}
			if ( ! self::ratePlanId( (string) $row[ self::RATE_PLAN ] ) ) {
				/* translators: %s: rate plan name. */
				return array( 'invalid', sprintf( __( 'Rate plan "%s" is not on the site.', 'radius-hotel-booking' ), $row[ self::RATE_PLAN ] ) );
			}
			if ( ! self::dateTime( (string) $row[ self::CHECK_IN ] ) || ! self::dateTime( (string) $row[ self::CHECK_OUT ] ) ) {
				return array( 'invalid', __( 'The check-in or check-out date is not readable.', 'radius-hotel-booking' ) );
			}
		}
		if ( '' === trim( $lines[0][ self::PHONE ] ) && '' === trim( $lines[0][ self::EMAIL ] ) ) {
			return array( 'invalid', __( 'The guest has no phone or e-mail.', 'radius-hotel-booking' ) );
		}
		return array( 'restore', '' );
	}

	/**
	 * Re-create one booking with its original reference; returns warnings.
	 *
	 * @param string  $reference Order ID.
	 * @param array[] $lines     Its rows.
	 * @return string[]
	 * @throws DomainException From the booking write path.
	 */
	private function restoreOne( string $reference, array $lines ): array {
		$warnings = array();
		$first    = $lines[0];
		$statuses = array_map( fn( $row ) => self::statusKey( (string) $row[ self::STATUS ] ), $lines );
		$stayed   = (bool) array_intersect( $statuses, array( 'checked_in', 'checked_out' ) );
		$pending  = ! $stayed && in_array( 'pending', $statuses, true ) && ! in_array( 'confirmed', $statuses, true );

		$guest = ( new GuestService() )->findOrCreate(
			array_filter(
				array(
					'first_name' => $first[ self::FIRST_NAME ],
					'last_name'  => $first[ self::LAST_NAME ],
					'phone'      => $first[ self::PHONE ],
					'email'      => $first[ self::EMAIL ],
					'id_type'    => self::idTypeKey( (string) $first[ self::ID_TYPE ] ),
					'id_number'  => $first[ self::ID_NUMBER ],
				),
				static fn( $value ) => '' !== (string) $value
			)
		);

		$requests = array();
		foreach ( $lines as $row ) {
			$start      = self::dateTime( (string) $row[ self::CHECK_IN ] );
			$requests[] = array(
				'room_id'        => self::roomId( (string) $row[ self::ROOM ] ),
				'rate_plan_id'   => self::ratePlanId( (string) $row[ self::RATE_PLAN ] ),
				'arrival'        => $start->format( 'Y-m-d' ),
				'checkin_time'   => $start->format( 'H:i' ),
				'adults'         => max( 1, (int) $row[ self::ADULTS ] ),
				'children'       => max( 0, (int) $row[ self::CHILDREN ] ),
				'price_override' => max( 0, (float) $row[ self::LINE_TOTAL ] ),
			);
		}
		$paid   = max( 0, (float) $first[ self::PAID ] );
		$method = self::methodKey( (string) $first[ self::METHOD ] );
		if ( $paid > 0 && '' === $method ) {
			$warnings[] = __( 'The payment method is not on the site: the amount paid was not restored.', 'radius-hotel-booking' );
			$paid       = 0;
		}
		$created = self::dateTime( (string) $first[ self::CREATED ] );
		$booking = ( new BookingService() )->create(
			array(
				'lines'          => $requests,
				'reference'      => $reference,
				'guest_id'       => (int) $guest->id,
				'payment_state'  => 'unpaid',
				'payment_method' => $method,
				'note'           => (string) $first[ self::NOTE ],
				'status'         => $stayed ? 'checked_in' : ( $pending ? 'pending' : 'confirmed' ),
				'checked_in_at'  => $stayed ? self::dateTime( (string) $first[ self::CHECK_IN ] )->format( 'Y-m-d H:i' ) : '',
				'paid_amount'    => $paid,
				'paid_at'        => $paid > 0 && $created ? $created->format( 'Y-m-d H:i' ) : '',
			),
			array( 'audience' => 'staff' ),
			'import'
		);

		// The original creation date and source (the write path kept the order ID when free).
		if ( (string) $booking->reference !== $reference ) {
			/* translators: 1: original order ID, 2: new order ID. */
			$warnings[] = sprintf( __( 'Order ID %1$s was taken meanwhile: restored as %2$s.', 'radius-hotel-booking' ), $reference, $booking->reference );
		}
		$this->keepOriginal( (int) $booking->id, $created, (string) $first[ self::SOURCE ] );

		// Ended stays and cancelled rooms, line by line (the write path made them open).
		$created_lines = $this->lineIds( (int) $booking->id );
		$status        = new BookingStatusService();
		foreach ( $lines as $index => $row ) {
			$line_id = $created_lines[ $index ] ?? 0;
			$target  = $statuses[ $index ];
			try {
				if ( 'checked_out' === $target && $line_id ) {
					$end = self::dateTime( (string) $row[ self::CHECK_OUT ] );
					$status->checkOut( $line_id, $end ? $end->setTimezone( Dates::timezone() ) : null );
				} elseif ( $stayed && in_array( $target, array( 'pending', 'confirmed' ), true ) ) {
					// One status for the whole booking on the write path: a stayed booking's waiting rooms come back checked in.
					/* translators: 1: room number, 2: status. */
					$warnings[] = sprintf( __( 'Room %1$s was "%2$s" in the file and is restored as checked in.', 'radius-hotel-booking' ), $row[ self::ROOM ], $row[ self::STATUS ] );
				} elseif ( in_array( $target, array( 'cancelled', 'declined', 'no_show' ), true ) && $line_id ) {
					$reason = '' !== trim( $row[ self::REASON ] ) ? (string) $row[ self::REASON ] : __( 'Restored from a backup file as cancelled.', 'radius-hotel-booking' );
					$status->cancel( (int) $booking->id, $reason, count( $lines ) > 1 ? $line_id : 0 );
				}
			} catch ( \Throwable $e ) {
				/* translators: 1: room number, 2: status, 3: reason. */
				$warnings[] = sprintf( __( 'Room %1$s could not be set to "%2$s": %3$s', 'radius-hotel-booking' ), $row[ self::ROOM ], $row[ self::STATUS ], $e->getMessage() );
			}
		}
		return $warnings;
	}

	/**
	 * Put back the original creation date and source.
	 *
	 * @param int                    $booking_id Booking id.
	 * @param DateTimeImmutable|null $created    Original creation (site time).
	 * @param string                 $source     Original source.
	 * @return void
	 */
	private function keepOriginal( int $booking_id, ?DateTimeImmutable $created, string $source ): void {
		global $wpdb;
		$fields = array();
		if ( $created ) {
			$fields['created_at']     = Dates::to_db( $created );
			$fields['created_at_gmt'] = Dates::to_gmt_db( $created );
		}
		if ( in_array( $source, array( 'desk', 'web' ), true ) ) {
			$fields['source'] = $source;
		}
		if ( $fields ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one row the restore just wrote.
			$wpdb->update( rtbp_table( 'bookings' ), $fields, array( 'id' => $booking_id ) );
		}
	}

	/**
	 * A booking's line ids, in creation order.
	 *
	 * @param int $booking_id Booking id.
	 * @return int[]
	 */
	private function lineIds( int $booking_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the lines the restore just wrote.
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE booking_id = %d ORDER BY id', rtbp_table( 'booking_rooms' ), $booking_id ) ) );
	}

	/**
	 * The order IDs among a list that are on the site (reference => true), 500 per query.
	 *
	 * @param array $references Order IDs.
	 * @return array<string, true>
	 */
	private static function existingReferences( array $references ): array {
		global $wpdb;
		$found = array();
		foreach ( array_chunk( array_map( 'strval', $references ), 500 ) as $chunk ) {
			$marks = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $marks are placeholders, read fresh.
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT reference FROM %i WHERE reference IN ( {$marks} )", array_merge( array( rtbp_table( 'bookings' ) ), $chunk ) ) ) as $reference ) {
				$found[ strtoupper( (string) $reference ) ] = true;
			}
		}
		return $found;
	}

	/**
	 * Whether a booking has this reference.
	 *
	 * @param string $reference Order ID.
	 * @return bool
	 */
	private static function bookingExists( string $reference ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one indexed lookup per order.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE reference = %s LIMIT 1', rtbp_table( 'bookings' ), $reference ) );
	}

	/**
	 * A live room's id by its number (0 when none).
	 *
	 * @param string $number Room number.
	 * @return int
	 */
	private static function roomId( string $number ): int {
		static $cache = array();
		$number = trim( $number );
		if ( '' === $number ) {
			return 0;
		}
		if ( ! isset( $cache[ $number ] ) ) {
			$room             = ( new RoomRepository() )->findByNumberAny( $number );
			$cache[ $number ] = $room && empty( $room->deleted_at ) ? (int) $room->id : 0;
		}
		return $cache[ $number ];
	}

	/**
	 * A live rate plan's id by its name (0 when none).
	 *
	 * @param string $name Plan name.
	 * @return int
	 */
	private static function ratePlanId( string $name ): int {
		global $wpdb;
		static $cache = array();
		$name = trim( $name );
		if ( '' === $name ) {
			return 0;
		}
		if ( ! isset( $cache[ $name ] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one lookup per plan name, cached.
			$cache[ $name ] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE name = %s AND deleted_at IS NULL ORDER BY id LIMIT 1', rtbp_table( 'rate_plans' ), $name ) );
		}
		return $cache[ $name ];
	}

	/**
	 * `Y-m-d H:i` (site time) as a date, null when unreadable.
	 *
	 * @param string $value Cell.
	 * @return DateTimeImmutable|null
	 */
	private static function dateTime( string $value ): ?DateTimeImmutable {
		$value = trim( $value );
		foreach ( array( '!Y-m-d H:i', '!Y-m-d H:i:s', '!Y-m-d' ) as $format ) {
			$date = DateTimeImmutable::createFromFormat( $format, $value, Dates::timezone() );
			if ( $date ) {
				return $date;
			}
		}
		return null;
	}

	/**
	 * A line status key from its exported label (this site's language or English).
	 *
	 * @param string $label Label.
	 * @return string
	 */
	private static function statusKey( string $label ): string {
		$label = trim( $label );
		$keys  = array( 'pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'declined', 'no_show' );
		$en    = array(
			'Awaiting approval' => 'pending',
			'Confirmed'         => 'confirmed',
			'Checked in'        => 'checked_in',
			'Checked out'       => 'checked_out',
			'Cancelled'         => 'cancelled',
			'Declined'          => 'declined',
			'No-show'           => 'no_show',
		);
		foreach ( $keys as $key ) {
			if ( 0 === strcasecmp( $label, ReportService::statusLabel( $key ) ) || 0 === strcasecmp( $label, $key ) ) {
				return $key;
			}
		}
		return $en[ $label ] ?? 'confirmed';
	}

	/**
	 * A payment method key from its label or key ('' when not on the site).
	 *
	 * @param string $label Label.
	 * @return string
	 */
	private static function methodKey( string $label ): string {
		$label = trim( $label );
		if ( '' === $label ) {
			return '';
		}
		foreach ( (array) rtbp_setting( 'payments', 'methods', array() ) as $method ) {
			if ( 0 === strcasecmp( $label, (string) ( $method['label'] ?? '' ) ) || 0 === strcasecmp( $label, (string) ( $method['key'] ?? '' ) ) ) {
				return (string) $method['key'];
			}
		}
		return '';
	}

	/**
	 * An ID type key from its exported label ('' when unknown).
	 *
	 * @param string $label Label.
	 * @return string
	 */
	private static function idTypeKey( string $label ): string {
		$label = trim( $label );
		foreach ( GuestService::idTypeLabels() as $key => $text ) {
			if ( 0 === strcasecmp( $label, (string) $text ) || 0 === strcasecmp( $label, (string) $key ) ) {
				return (string) $key;
			}
		}
		return '';
	}
}
