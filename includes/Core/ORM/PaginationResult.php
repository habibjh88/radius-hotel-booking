<?php
/**
 * Value object holding a page of query results and its metadata.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\ORM
 */

namespace RadiusTheme\RadiusHotelBooking\Core\ORM;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/ORM/PaginationResult.php
 * Handles pagination results with metadata
 */
class PaginationResult {

	/**
	 * Represents a collection of items in a paginated response.
	 * This is typically an array of objects or data entries that are part of the current page of results.
	 * For example, it could be a list of posts, users, or any other entities that are being paginated.
	 *
	 * @var array<mixed>
	 */
	public array $items;
	/**
	 * Represents the total number of items across all pages.
	 * This is useful for understanding the overall size of the dataset being paginated.
	 * For instance, if there are 100 total posts and each page shows 10 posts, this value would be 100.
	 *
	 * @var int
	 */
	public int $total;
	/**
	 * Represents the number of items per page in a paginated response.
	 * This value determines how many items will be displayed on each page of results.
	 * For example, if this value is set to 10, each page will show 10 items.
	 *
	 * @var int
	 */
	public int $perPage;
	/**
	 * Represents the current page in a pagination or navigation context.
	 * This is the page number that the user is currently viewing or interacting with.
	 * For example, if a user is on the second page of a paginated list, this value would be 2.
	 *
	 * @var int
	 * This value is crucial for understanding the context of the results being displayed, especially in a paginated API response.
	 */
	public int $currentPage;
	/**
	 * Represents the last page in a paginated set of results.
	 * This is calculated based on the total number of items and the number of items per page.
	 * For example, if there are 100 total items and each page shows 10 items, the last page would be 10.
	 *
	 * @var int
	 * This value is important for navigation purposes, allowing users to know how many pages of results are available.
	 */
	public int $lastPage;
	/**
	 * Indicates whether there are more pages available in a paginated response.
	 * This boolean value is true if there are additional pages beyond the current one, and false if the current page is the last one.
	 * For example, if a user is on page 1 of a 5-page result set, this value would be true, indicating that pages 2 through 5 are available.
	 *
	 * @var bool
	 */
	public bool $hasMorePages;

	/**
	 * Initializes a paginated collection instance.
	 *
	 * @param array $items The list of items for the current page.
	 * @param int $total The total number of items across all pages.
	 * @param int $perPage The number of items per page.
	 * @param int $currentPage The current page number.
	 *
	 * @return void
	 */
	public function __construct( array $items, int $total, int $perPage, int $currentPage ) {
		$this->items        = $items;
		$this->total        = $total;
		$this->perPage      = $perPage;
		$this->currentPage  = $currentPage;
		$this->lastPage     = (int) ceil( $total / $perPage );
		$this->hasMorePages = $currentPage < $this->lastPage;
	}

	/**
	 * Converts the current paginated data into an array representation.
	 *
	 * @return array The array containing paginated data including items, current page, last page, items per page, total items, and whether there are more pages.
	 */
	public function toArray(): array {
		return array(
			'data'           => $this->items,
			'current_page'   => $this->currentPage,
			'last_page'      => $this->lastPage,
			'per_page'       => $this->perPage,
			'total'          => $this->total,
			'has_more_pages' => $this->hasMorePages,
		);
	}
}
