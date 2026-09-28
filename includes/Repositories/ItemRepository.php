<?php
/**
 * Item repository.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\Item;

/**
 * Class ItemRepository
 *
 * Data access for the Item model. BaseRepository already provides
 * all(), find(), findOrFail(), create(), update(), delete(), paginate(),
 * where(), count() and exists() — add only query methods specific to Items here.
 */
class ItemRepository extends BaseRepository {

	/**
	 * The model class managed by this repository.
	 *
	 * @var string
	 */
	protected string $model = Item::class;

	/**
	 * Fetch every published item, ordered by position.
	 *
	 * @param int $limit Maximum number of rows.
	 *
	 * @return array
	 */
	public function published( int $limit = 20 ): array {
		return $this->model::query()
			->where( 'status', 'published' )
			->orderBy( 'position', 'ASC' )
			->limit( $limit )
			->get();
	}
}
