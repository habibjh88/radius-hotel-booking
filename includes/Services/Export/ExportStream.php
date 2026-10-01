<?php
/**
 * A file written row by row (M11).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Export
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Export;

defined( 'ABSPATH' ) || exit;

/**
 * The streamed side of an export format: large exports (every booking of a
 * year) are written row by row to a file, never held in memory. A format of
 * `rtbp_export_formats` offers it as `stream` — a callable returning a new
 * writer (free: `CsvStream`; Pro adds XLSX).
 */
interface ExportStream {

	/**
	 * Start the file.
	 *
	 * @param string   $path    File to write (created or emptied).
	 * @param string[] $columns Header labels.
	 * @return void
	 */
	public function begin( string $path, array $columns ): void;

	/**
	 * Add one row (values in column order; numbers stay numbers).
	 *
	 * @param array $row Values.
	 * @return void
	 */
	public function write( array $row ): void;

	/**
	 * Finish and close the file.
	 *
	 * @return void
	 */
	public function finish(): void;
}
