<?php
/**
 * Spreadsheet files from tables (M10, 10.14; M11 generalises it).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Export
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Export;

use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;

defined( 'ABSPATH' ) || exit;

/**
 * Writes **tables** — `[ { title, columns: [ label, … ], rows: [ [ value, … ] ] } ]`
 * — in one of the formats of the `rtbp_export_formats` registry:
 *
 *     'csv' => array(
 *         'label'     => 'CSV',
 *         'extension' => 'csv',
 *         'mime'      => 'text/csv',
 *         'write'     => callable( array $tables ): string,   // the file's bytes
 *         'stream'    => callable(): ExportStream,             // optional: row by row (M11)
 *     )
 *
 * The free plugin ships CSV; Pro adds XLSX (one sheet per table) to the same
 * registry. Values are written as given: numbers stay numbers, text is text.
 *
 * CSV: UTF-8 with a byte-order mark (so Excel reads accents), comma separated,
 * the tables one after the other — each under its title when there are
 * several — with a blank line between. Text starting — after any leading
 * spaces — with `=`, `+`, `-`, `@` or their fullwidth forms, or with a tab or
 * a carriage return, is prefixed with `'`, so a spreadsheet never runs a
 * guest's name as a formula (CSV injection).
 */
class ExportWriter {

	/**
	 * The registered formats.
	 *
	 * @return array<string, array{label: string, extension: string, mime: string, write: callable}>
	 */
	public static function formats(): array {
		$formats = array(
			'csv' => array(
				'label'     => __( 'CSV (spreadsheet)', 'radius-hotel-booking' ),
				'extension' => 'csv',
				'mime'      => 'text/csv',
				'write'     => array( self::class, 'csv' ),
				// Large exports (M11): written row by row.
				'stream'    => static fn() => new CsvStream(),
			),
		);

		/**
		 * Filters the export formats.
		 *
		 * Each entry: `label`, `extension`, `mime`, `write` (callable receiving the
		 * tables and returning the file's bytes).
		 *
		 * @param array $formats Key => format.
		 */
		$formats = (array) apply_filters( 'rtbp_export_formats', $formats );

		return array_filter(
			$formats,
			static fn( $format, $key ) => is_string( $key ) && preg_match( '/^[a-z0-9]+$/', $key ) && is_array( $format )
				&& ! empty( $format['extension'] ) && ! empty( $format['mime'] ) && is_callable( $format['write'] ?? null ),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * The formats that can write row by row (the booking and guest exports).
	 *
	 * @return array<string, array> Key => format.
	 */
	public static function streamed(): array {
		return array_filter( self::formats(), static fn( $format ) => is_callable( $format['stream'] ?? null ) );
	}

	/**
	 * The formats as the screen lists them.
	 *
	 * @return array[] `[ { key, label } ]`.
	 */
	public static function choices(): array {
		$out = array();
		foreach ( self::formats() as $key => $format ) {
			$out[] = array(
				'key'   => $key,
				'label' => (string) ( $format['label'] ?? strtoupper( $key ) ),
			);
		}
		return $out;
	}

	/**
	 * Write tables as a file.
	 *
	 * @param string $format   Format key.
	 * @param array  $tables   Tables.
	 * @param string $basename File name without extension.
	 * @return array `{ filename, mime, content }` (content: the raw bytes).
	 * @throws DomainException 422 for an unknown format.
	 */
	public static function write( string $format, array $tables, string $basename ): array {
		$formats = self::formats();
		if ( ! isset( $formats[ $format ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'format' => __( 'Choose an export format.', 'radius-hotel-booking' ) ) );
		}
		$definition = $formats[ $format ];
		return array(
			'filename' => sanitize_file_name( $basename ) . '.' . $definition['extension'],
			'mime'     => (string) $definition['mime'],
			'content'  => (string) call_user_func( $definition['write'], $tables ),
		);
	}

	/**
	 * CSV writer.
	 *
	 * @param array $tables Tables.
	 * @return string File bytes.
	 */
	public static function csv( array $tables ): string {
		$handle = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- an in-memory buffer, not a file.
		$titled = count( $tables ) > 1;
		foreach ( array_values( $tables ) as $index => $table ) {
			if ( $index > 0 ) {
				fputcsv( $handle, array(), ',', '"', '' );
			}
			if ( $titled && '' !== (string) ( $table['title'] ?? '' ) ) {
				fputcsv( $handle, array( self::cell( (string) $table['title'] ) ), ',', '"', '' );
			}
			fputcsv( $handle, array_map( array( self::class, 'cell' ), (array) ( $table['columns'] ?? array() ) ), ',', '"', '' );
			foreach ( (array) ( $table['rows'] ?? array() ) as $row ) {
				fputcsv( $handle, array_map( array( self::class, 'cell' ), (array) $row ), ',', '"', '' );
			}
		}
		rewind( $handle );
		$body = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- the buffer above.
		return "\xEF\xBB\xBF" . $body;
	}

	/**
	 * One cell: numbers as they are, text guarded against formulas.
	 *
	 * @param mixed $value Value.
	 * @return string|int|float
	 */
	public static function cell( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}
		$text = (string) $value;
		// Judged after leading whitespace (some programs trim it on import), and
		// the fullwidth signs some Excel versions also treat as a formula start.
		$lead = (string) preg_replace( '/^[\s\x{00A0}\x{3000}]+/u', '', $text );
		if ( '' === $lead ) {
			return $text;
		}
		$first = function_exists( 'mb_substr' ) ? mb_substr( $lead, 0, 1 ) : $lead[0];
		return in_array( $first, array( '=', '+', '-', '@', "\u{FF1D}", "\u{FF0B}", "\u{FF0D}", "\u{FF20}" ), true ) || ( '' !== $text && false !== strpos( "\t\r", $text[0] ) ) ? "'" . $text : $text;
	}
}
