<?php
/**
 * Stored file repository.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\StoredFile;

/**
 * Data access for the `files` index.
 */
class StoredFileRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = StoredFile::class;

	/**
	 * A file by its download token.
	 *
	 * @param string $token 32 hex characters.
	 * @return StoredFile|null
	 */
	public function findByToken( string $token ): ?StoredFile {
		// query() returns raw rows; hydrate the match into a model.
		$row = $this->model::query()->where( 'token', $token )->first();
		return $row ? StoredFile::hydrate( $row ) : null;
	}
}
