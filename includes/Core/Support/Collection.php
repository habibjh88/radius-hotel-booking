<?php
/**
 * Collection class
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Support
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Simple Collection class to handle arrays of model instances
 */
class Collection implements \ArrayAccess, \Iterator, \Countable {


	/**
	 * Array of items
	 *
	 * @var array
	 */
	private array $items  = array();
	/**
	 * Current position for iterator
	 *
	 * @var int
	 */
	private int $position = 0;

	/**
	 * Constructor method to initialize the object with items.
	 *
	 * @param array $items An optional array of items to initialize with.
	 *
	 * @return void
	 */
	public function __construct( array $items = array() ) {
		$this->items = $items;
	}

	/**
	 * Extract values from a specific key/property from all items
	 *
	 * @param string $key The key/property to extract
	 * @return array
	 */
	public function pluck( string $key ): array {
		return array_map(
			function ( $item ) use ( $key ) {
				if ( is_object( $item ) ) {
					return $item->$key ?? null;
				}
				return $item[ $key ] ?? null;
			},
			$this->items
		);
	}

	/**
	 * Extract values corresponding to a given key or nested key from the collection items.
	 *
	 * @param string $key The key or nested key (dot notation) to extract the values for.
	 *
	 * @return static A new collection instance containing the extracted values.
	 */
	public function pluckWith( string $key ) {
		$plucked = array_map(
			function ( $item ) use ( $key ) {
				// Handle nested keys like 'customer.customer_id'
				$keys  = explode( '.', $key );
				$value = $item;

				foreach ( $keys as $nestedKey ) {
					if ( is_object( $value ) ) {
						$value = $value->$nestedKey ?? null;
					} elseif ( is_array( $value ) ) {
						$value = $value[ $nestedKey ] ?? null;
					} else {
						$value = null;
						break;
					}
				}

				return $value;
			},
			$this->items
		);

		return new static( $plucked );
	}

	/**
	 * Get unique values from the collection
	 *
	 * @return static
	 */
	public function unique(): static {
		return new static( array_values( array_unique( $this->items, SORT_REGULAR ) ) );
	}

	/**
	 * Convert collection to array
	 *
	 * @return array
	 */
	public function toArray(): array {
		return array_map(
			function ( $item ) {
				if ( is_object( $item ) && method_exists( $item, 'toArray' ) ) {
					return $item->toArray();
				}
				return (array) $item;
			},
			$this->items
		);
	}

	/**
	 * Get all items as array
	 *
	 * @return array
	 */
	public function all(): array {
		return $this->items;
	}

	/**
	 * Get first item
	 *
	 * @return mixed|null
	 */
	public function first() {
		return $this->items[0] ?? null;
	}

	/**
	 * Get last item
	 *
	 * @return mixed|null
	 */
	public function last() {
		return end( $this->items ) ?? null;
	}

	/**
	 * Filters the collection using the given callback function.
	 *
	 * @param callable $callback A callback function to determine if an item should be included.
	 *
	 * @return static A new collection instance containing the filtered items.
	 */
	public function filter( callable $callback ): static {
		return new static( array_filter( $this->items, $callback ) );
	}

	/**
	 * Apply the given callback to each item in the collection and return a new instance with the transformed items.
	 *
	 * @param callable $callback A callback function to apply to each item in the collection.
	 *
	 * @return static A new instance of the collection with the transformed items.
	 */
	public function map( callable $callback ): static {
		return new static( array_map( $callback, $this->items ) );
	}

	/**
	 * Check if collection is empty
	 *
	 * @return bool
	 */
	public function isEmpty(): bool {
		return empty( $this->items );
	}

	/**
	 * Checks if the current instance is not empty.
	 *
	 * @return bool Returns true if the instance is not empty, false otherwise.
	 */
	public function isNotEmpty(): bool {
		return ! $this->isEmpty();
	}

	// ArrayAccess implementation

	/**
	 * Checks if the specified offset exists in the collection.
	 *
	 * @param mixed $offset The offset to check for existence.
	 *
	 * @return bool Returns true if the offset exists, false otherwise.
	 */
	public function offsetExists( $offset ): bool {
		return isset( $this->items[ $offset ] );
	}

	/**
	 * Retrieves the value at the specified offset.
	 *
	 * @param mixed $offset The offset to retrieve the value for.
	 *
	 * @return mixed The value at the given offset, or null if the offset does not exist.
	 */
	public function offsetGet( $offset ): mixed {
		return $this->items[ $offset ] ?? null;
	}

	/**
	 * Sets the value at the specified offset within the collection.
	 *
	 * @param mixed $offset The offset at which the value will be set. If null, the value will be appended to the collection.
	 * @param mixed $value The value to be set at the specified offset.
	 *
	 * @return void
	 */
	public function offsetSet( $offset, $value ): void {
		if ( is_null( $offset ) ) {
			$this->items[] = $value;
		} else {
			$this->items[ $offset ] = $value;
		}
	}

	/**
	 * Removes the specified offset from the collection.
	 *
	 * @param mixed $offset The offset to be unset.
	 *
	 * @return void
	 */
	public function offsetUnset( $offset ): void {
		unset( $this->items[ $offset ] );
	}

	// Iterator implementation

	/**
	 * Retrieves the current element from the collection based on the internal position.
	 *
	 * @return mixed Returns the current element at the internal position.
	 */
	public function current(): mixed {
		return $this->items[ $this->position ];
	}

	/**
	 * Retrieves the current key at the iterator's position.
	 *
	 * @return mixed Returns the key of the current element or null if the position is invalid.
	 */
	public function key(): mixed {
		return $this->position;
	}

	/**
	 * Advances the current position to the next element.
	 *
	 * @return void
	 */
	public function next(): void {
		++$this->position;
	}

	/**
	 * Resets the position to the starting point.
	 *
	 * @return void
	 */
	public function rewind(): void {
		$this->position = 0;
	}

	/**
	 * Determines if the current position is valid.
	 *
	 * @return bool Returns true if the current position is valid, false otherwise.
	 */
	public function valid(): bool {
		return isset( $this->items[ $this->position ] );
	}

	// Countable implementation

	/**
	 * Retrieves the total number of items in the current instance.
	 *
	 * @return int Returns the count of items.
	 */
	public function count(): int {
		return count( $this->items );
	}

	/**
	 * Calculates the sum of the items in the collection. The sum can be calculated directly,
	 * using a callback, or by a specific key/property (supports nested dot-notation).
	 *
	 * @param string|callable|null $key Optional. The key/property to sum,
	 *                                   a callback function to determine the value,
	 *                                   or null to sum raw items.
	 *
	 * @return float|int The total sum of the items based on the provided key or callback.
	 */
	public function sum( string|callable|null $key = null ): float|int {
		$total = 0;

		foreach ( $this->items as $item ) {
			$value = null;

			if ( is_null( $key ) ) {
				// Sum raw items
				$value = $item;
			} elseif ( is_callable( $key ) ) {
				// Sum by callback
				$value = $key( $item );
			} else {
				// Sum by key / property (supports nested dot-notation)
				$keys  = explode( '.', $key );
				$value = $item;
				foreach ( $keys as $nestedKey ) {
					if ( is_object( $value ) ) {
						$value = $value->$nestedKey ?? null;
					} elseif ( is_array( $value ) ) {
						$value = $value[ $nestedKey ] ?? null;
					} else {
						$value = null;
						break;
					}
				}
			}

			if ( is_numeric( $value ) ) {
				$total += $value;
			}
		}

		return $total;
	}
}
