<?php
/**
 * Exports and their library (M11).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Export
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Export;

use RadiusTheme\RadiusHotelBooking\Access\Access;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\StoredFile;
use RadiusTheme\RadiusHotelBooking\Services\Reports\ReportRange;
use RadiusTheme\RadiusHotelBooking\Storage\ProtectedFiles;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Generates export files and keeps their library (the legacy "Booking Backup").
 *
 * - **Kinds** come from the `rtbp_export_kinds` registry — `bookings`
 *   (`BookingExport`) and `guests` (`GuestExport`, the whole list) here, Pro
 *   may add its own: `kind => { label, period?, columns(): string[],
 *   rows( ReportRange, input ): iterable }` (`period` false: not by date).
 * - **Formats** are the streamed ones of `rtbp_export_formats` (CSV here, Pro
 *   adds XLSX): rows go to a temporary file one by one — never all in memory,
 *   no row limit — then into protected storage (`ProtectedFiles`, kind
 *   `export`, ADR-009) with its SHA-256.
 * - Each file is a row of the `exports` table; generating is logged
 *   (`exports.generate`).
 * - Downloads go through `GET files/{token}` and need `exports.download`
 *   (the `rtbp_file_access` filter below).
 */
class ExportService {

	/**
	 * Protected file kind of export files.
	 */
	public const FILE_KIND = 'export';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'rtbp_file_kinds', array( self::class, 'file_kinds' ) );
		add_filter( 'rtbp_file_access', array( self::class, 'file_access' ), 10, 3 );
		add_action( 'rtbp_file_download', array( self::class, 'log_download' ) );
	}

	/**
	 * `rtbp_file_download`: log who downloaded an export file (11.6).
	 *
	 * @param StoredFile $file File about to be sent.
	 * @return void
	 */
	public static function log_download( $file ): void {
		if ( ! $file instanceof StoredFile || self::FILE_KIND !== (string) $file->kind ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one row by its file.
		$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE file_token = %s', rtbp_table( 'exports' ), (string) $file->token ) );
		rtbp_activity(
			'exports.download',
			array(
				'type'  => 'export',
				'id'    => $id,
				'label' => (string) $file->original_name,
			),
			array(
				/* translators: %s: file name. */
				'description' => sprintf( __( 'Downloaded %s', 'radius-hotel-booking' ), (string) $file->original_name ),
			)
		);
	}

	/**
	 * `rtbp_file_kinds`: export files.
	 *
	 * @param array $kinds Kind => `{ capability }`.
	 * @return array
	 */
	public static function file_kinds( $kinds ): array {
		$kinds                    = (array) $kinds;
		$kinds[ self::FILE_KIND ] = array( 'capability' => Capabilities::VIEW_DASHBOARD );
		return $kinds;
	}

	/**
	 * `rtbp_file_access`: an export file needs `exports.download` (whatever
	 * the capability says — exports carry every guest's identity document).
	 *
	 * @param bool       $allowed Decision so far.
	 * @param StoredFile $file    File.
	 * @param int        $user_id User.
	 * @return bool
	 */
	public static function file_access( $allowed, $file, $user_id ): bool {
		if ( ! $file instanceof StoredFile || self::FILE_KIND !== (string) $file->kind ) {
			return (bool) $allowed;
		}
		return (int) $user_id > 0 && Access::LOCKED !== Access::level( 'exports.download', (int) $user_id );
	}

	/**
	 * The export kinds.
	 *
	 * @return array<string, array{label: string, columns: callable, rows: callable}>
	 */
	public static function kinds(): array {
		$kinds = array(
			'bookings' => array(
				'label'   => __( 'Bookings', 'radius-hotel-booking' ),
				'columns' => array( BookingExport::class, 'columns' ),
				'rows'    => static fn( ReportRange $range ) => ( new BookingExport() )->rows( $range ),
			),
			// The whole guest list: no period.
			'guests'   => array(
				'label'   => __( 'Guests', 'radius-hotel-booking' ),
				'period'  => false,
				'columns' => array( GuestExport::class, 'columns' ),
				'rows'    => static fn() => ( new GuestExport() )->rows(),
			),
		);

		/**
		 * Filters what can be exported.
		 *
		 * Each entry: `label`, `columns` (callable returning the header labels),
		 * `rows` (callable receiving the `ReportRange` and the input, returning
		 * the rows — a generator, so large exports stay out of memory).
		 *
		 * @param array $kinds Kind => definition.
		 */
		$kinds = (array) apply_filters( 'rtbp_export_kinds', $kinds );

		return array_filter(
			$kinds,
			static fn( $kind, $key ) => is_string( $key ) && preg_match( '/^[a-z0-9_]+$/', $key ) && is_array( $kind )
				&& is_callable( $kind['columns'] ?? null ) && is_callable( $kind['rows'] ?? null ),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Generate an export file.
	 *
	 * @param string $kind   Kind (`bookings` …).
	 * @param array  $input  `from`, `to`, `mode`, `format`.
	 * @param string $source `manual` or `scheduled`.
	 * @return array The library row.
	 * @throws DomainException 422 on a bad kind, format or period; 500 when the file cannot be written.
	 */
	public function generate( string $kind, array $input, string $source = 'manual' ): array {
		$kinds   = self::kinds();
		$formats = ExportWriter::streamed();
		$format  = (string) ( $input['format'] ?? 'csv' );
		$errors  = array();
		if ( ! isset( $kinds[ $kind ] ) ) {
			$errors['kind'] = __( 'Choose what to export.', 'radius-hotel-booking' );
		}
		if ( ! isset( $formats[ $format ] ) ) {
			$errors['format'] = __( 'Choose an export format.', 'radius-hotel-booking' );
		}
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}
		$by_period = false !== ( $kinds[ $kind ]['period'] ?? true );
		$range     = ReportRange::fromInput( $by_period ? $input : array() );

		// A long export must not stop half way.
		wp_raise_memory_limit( 'admin' );
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 600 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- a large export.
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$temp = wp_tempnam( 'rtbp-export' );
		$rows = 0;
		try {
			$writer = call_user_func( $formats[ $format ]['stream'] );
			$writer->begin( $temp, (array) call_user_func( $kinds[ $kind ]['columns'] ) );
			foreach ( call_user_func( $kinds[ $kind ]['rows'], $range, $input ) as $row ) {
				$writer->write( (array) $row );
				++$rows;
			}
			$writer->finish();

			$name_parts = $by_period ? array( $kind, $range->from, $range->to ) : array( $kind, Dates::today() );
			if ( $by_period && 'created' === $range->mode ) {
				$name_parts[] = 'booked';
			}
			$file = ProtectedFiles::put_file( self::FILE_KIND, $temp, implode( '-', $name_parts ) . '.' . $formats[ $format ]['extension'], true, (string) $formats[ $format ]['mime'] );
		} finally {
			if ( file_exists( $temp ) ) {
				wp_delete_file( $temp );
			}
		}

		$params = $by_period ? array(
			'from' => $range->from,
			'to'   => $range->to,
			'mode' => $range->mode,
		) : array();
		if ( 'csv' === $format ) {
			$params['separator'] = (string) rtbp_setting( 'exports', 'csvSeparator', ',' );
		}
		$id = $this->insert(
			array(
				'kind'        => $kind,
				'params'      => wp_json_encode( $params ),
				'format'      => $format,
				'row_count'   => $rows,
				'file_token'  => (string) $file->token,
				'file_sha256' => (string) $file->sha256,
				'status'      => 'done',
				'source'      => in_array( $source, array( 'manual', 'scheduled' ), true ) ? $source : 'manual',
				'created_by'  => get_current_user_id() ? get_current_user_id() : null,
			)
		);

		rtbp_activity(
			'exports.generate',
			array(
				'type'  => 'export',
				'id'    => $id,
				'label' => (string) $file->original_name,
			),
			array(
				'after'       => $params + array(
					'kind'   => $kind,
					'format' => $format,
					'rows'   => $rows,
					'source' => $source,
				),
				'description' => $by_period
					? sprintf(
						/* translators: 1: what was exported, 2: first day, 3: last day, 4: number of rows, 5: file format. */
						__( 'Exported %1$s from %2$s to %3$s: %4$d rows (%5$s)', 'radius-hotel-booking' ),
						strtolower( (string) $kinds[ $kind ]['label'] ),
						$range->from,
						$range->to,
						$rows,
						strtoupper( $format )
					)
					: sprintf(
						/* translators: 1: what was exported, 2: number of rows, 3: file format. */
						__( 'Exported %1$s: %2$d rows (%3$s)', 'radius-hotel-booking' ),
						strtolower( (string) $kinds[ $kind ]['label'] ),
						$rows,
						strtoupper( $format )
					),
			)
		);

		return $this->find( $id );
	}

	/**
	 * Delete an export's file (11.6): the file goes, the library row stays as
	 * `removed` (hidden from the list) so the log keeps its context. An archive
	 * file (`removed_count` > 0) is deleted only by an administrator.
	 *
	 * @param int $id Export id.
	 * @return array The row as it was.
	 * @throws DomainException 404.
	 */
	public function delete( int $id ): array {
		global $wpdb;
		$row = $this->find( $id );
		if ( 'removed' === $row['status'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This export does not exist.', 'radius-hotel-booking' ) );
		}
		// The bookings an archive removed exist only in its file now: only an
		// administrator may delete it (M11 critical review).
		if ( $row['removed_count'] > 0 && ! current_user_can( 'manage_options' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'archive_protected', __( 'This file is the only copy of the bookings it archived. Only an administrator can delete it.', 'radius-hotel-booking' ), 403 );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one row by id.
		$token = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT file_token FROM %i WHERE id = %d', rtbp_table( 'exports' ), $id ) );
		if ( '' !== $token ) {
			ProtectedFiles::delete( $token );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin table.
		$wpdb->update(
			rtbp_table( 'exports' ),
			array(
				'status'     => 'removed',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);
		$name = (string) ( $row['file']['name'] ?? '' );
		rtbp_activity(
			'exports.delete',
			array(
				'type'  => 'export',
				'id'    => $id,
				'label' => $name,
			),
			array(
				'before'      => array(
					'kind'   => $row['kind'],
					'format' => $row['format'],
					'rows'   => $row['row_count'],
				) + $row['params'],
				/* translators: %s: file name. */
				'description' => sprintf( __( 'Deleted the export file %s', 'radius-hotel-booking' ), $name ),
			)
		);
		return $row;
	}

	/**
	 * A page of the library, newest first.
	 *
	 * @param int $page     Page (1-based).
	 * @param int $per_page Rows per page (≤ 100).
	 * @return array `{ items, total }`.
	 */
	public function list( int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$per_page = max( 1, min( 100, $per_page ) );
		$page     = max( 1, $page );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the library, read on demand.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status <> 'removed'", rtbp_table( 'exports' ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE status <> 'removed' ORDER BY id DESC LIMIT %d OFFSET %d",
				rtbp_table( 'exports' ),
				$per_page,
				( $page - 1 ) * $per_page
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return array(
			'items' => array_map( array( $this, 'shape' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * One library row.
	 *
	 * @param int $id Export id.
	 * @return array
	 * @throws DomainException 404.
	 */
	public function find( int $id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one row by id.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', rtbp_table( 'exports' ), $id ), ARRAY_A );
		if ( ! $row ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This export does not exist.', 'radius-hotel-booking' ) );
		}
		return $this->shape( $row );
	}

	/**
	 * A library row as the screen shows it.
	 *
	 * @param array $row Table row.
	 * @return array
	 */
	private function shape( array $row ): array {
		$file   = '' !== (string) $row['file_token'] ? ProtectedFiles::get( (string) $row['file_token'] ) : null;
		$kinds  = self::kinds();
		$params = json_decode( (string) $row['params'], true );
		$user   = (int) $row['created_by'] ? get_userdata( (int) $row['created_by'] ) : null;
		return array(
			'id'            => (int) $row['id'],
			'kind'          => (string) $row['kind'],
			'kind_label'    => (string) ( $kinds[ $row['kind'] ]['label'] ?? $row['kind'] ),
			'params'        => is_array( $params ) ? $params : array(),
			'format'        => (string) $row['format'],
			'row_count'     => (int) $row['row_count'],
			'removed_count' => (int) $row['removed_count'],
			'status'        => (string) $row['status'],
			'source'        => (string) $row['source'],
			'file'          => $file ? array(
				'name'   => (string) $file->original_name,
				'size'   => (int) $file->size,
				'sha256' => (string) $file->sha256,
			) : null,
			'created_by'    => $user ? (string) $user->display_name : '',
			'created_at'    => '' !== (string) $row['created_at'] ? Dates::to_iso( Dates::local( (string) $row['created_at'] ) ) : null,
			// Only for someone allowed to download it (the link carries the REST nonce).
			'download_url'  => $file && ProtectedFiles::can_access( $file ) ? ProtectedFiles::url( (string) $file->token ) : null,
		);
	}

	/**
	 * Store a library row.
	 *
	 * @param array $row Columns.
	 * @return int Id.
	 * @throws \RuntimeException When the row cannot be saved.
	 */
	private function insert( array $row ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin table.
		if ( ! $wpdb->insert(
			rtbp_table( 'exports' ),
			$row + array(
				'created_at' => $now,
				'updated_at' => $now,
			)
		) ) {
			throw new \RuntimeException( 'The export could not be recorded.' );
		}
		return (int) $wpdb->insert_id;
	}
}
