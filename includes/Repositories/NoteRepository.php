<?php
/**
 * Note data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\Note;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `notes`.
 */
class NoteRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = Note::class;

	/**
	 * The notes of one record, newest first.
	 *
	 * @param string $notable_type Type, e.g. `guest`.
	 * @param int    $notable_id   Record id.
	 * @return Note[]
	 */
	public function forNotable( string $notable_type, int $notable_id ): array {
		$rows = Note::query()->where( 'notable_type', '=', $notable_type )->where( 'notable_id', '=', $notable_id )->orderBy( 'id', 'DESC' )->get();
		return array_map( static fn( $row ) => Note::hydrate( $row ), $rows );
	}

	/**
	 * The notable type of a note ('' when it does not exist): what the
	 * access check of an edit or a removal needs.
	 *
	 * @param int $id Note id.
	 * @return string
	 */
	public function notableTypeOf( int $id ): string {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one small read for the access check.
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT notable_type FROM %i WHERE id = %d', rtbp_table( 'notes' ), $id ) );
	}
}
