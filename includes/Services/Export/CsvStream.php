<?php
/**
 * Streamed CSV (M11, 11.2).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Export
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Export;

defined( 'ABSPATH' ) || exit;

/**
 * CSV row by row: UTF-8 with a byte-order mark (Excel reads accents), the
 * separator from Settings → Exports (`,` like the legacy file, or `;`), every
 * text cell through `ExportWriter::cell()` (the formula-injection guard).
 */
class CsvStream implements ExportStream {

	/**
	 * Open file.
	 *
	 * @var resource|null
	 */
	private $handle = null;

	/**
	 * Separator.
	 *
	 * @var string
	 */
	private string $separator;

	/**
	 * Constructor.
	 *
	 * @param string $separator `,` or `;` (default: the setting).
	 */
	public function __construct( string $separator = '' ) {
		$separator       = '' !== $separator ? $separator : (string) rtbp_setting( 'exports', 'csvSeparator', ',' );
		$this->separator = in_array( $separator, array( ',', ';' ), true ) ? $separator : ',';
	}

	/**
	 * Start the file.
	 *
	 * @param string   $path    File.
	 * @param string[] $columns Header labels.
	 * @return void
	 * @throws \RuntimeException When the file cannot be opened.
	 */
	public function begin( string $path, array $columns ): void {
		$handle = fopen( $path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- a temporary file we stream into.
		if ( false === $handle ) {
			throw new \RuntimeException( 'The export file could not be opened.' );
		}
		$this->handle = $handle;
		if ( false === fwrite( $this->handle, "\xEF\xBB\xBF" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see above.
			throw new \RuntimeException( 'The export file could not be written.' );
		}
		$this->write( $columns );
	}

	/**
	 * Add one row.
	 *
	 * @param array $row Values.
	 * @return void
	 * @throws \RuntimeException When the row cannot be written (a full disk): the
	 *                           file must never end early unnoticed.
	 */
	public function write( array $row ): void {
		if ( false === fputcsv( $this->handle, array_map( array( ExportWriter::class, 'cell' ), array_values( $row ) ), $this->separator, '"', '' ) ) {
			throw new \RuntimeException( 'The export file could not be written.' );
		}
	}

	/**
	 * Close the file.
	 *
	 * @return void
	 * @throws \RuntimeException When the last bytes cannot be flushed.
	 */
	public function finish(): void {
		if ( $this->handle ) {
			$flushed = fflush( $this->handle );
			$closed  = fclose( $this->handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see above.
			$this->handle = null;
			if ( ! $flushed || ! $closed ) {
				throw new \RuntimeException( 'The export file could not be finished.' );
			}
		}
	}
}
