<?php
/**
 * Item CRUD tests.
 *
 * BOILERPLATE: the reference unit test. It exercises the whole stack —
 * migration, model, repository and events — against the real database the
 * WordPress test suite provides.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use RadiusTheme\RadiusHotelBooking\Models\Item;
use RadiusTheme\RadiusHotelBooking\Repositories\ItemRepository;
use RadiusTheme\RadiusHotelBooking\Services\ItemService;

/**
 * Class TestItemCrud
 */
class TestItemCrud extends WP_UnitTestCase {

	/**
	 * Repository under test.
	 *
	 * @var ItemRepository
	 */
	private ItemRepository $repository;

	/**
	 * Set up: make sure the schema exists and start from an empty table.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		( new RadiusTheme\RadiusHotelBooking\Setup\Installer() )->create_tables();

		global $wpdb;
		$table = rtbp_table_prefix() . 'items';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE `{$table}`" );

		$this->repository = new ItemRepository();
	}

	/**
	 * An item can be created, read back, updated and deleted.
	 *
	 * @return void
	 */
	public function test_item_crud_round_trip() {
		$item = $this->repository->create(
			array(
				'title'       => 'First item',
				'description' => 'Created in a test.',
				'status'      => 'draft',
				'price'       => 12.5,
			)
		);

		$this->assertInstanceOf( Item::class, $item );
		$this->assertSame( 'First item', $item->title );

		$found = $this->repository->find( (int) $item->id );
		$this->assertNotNull( $found );
		$this->assertSame( 'draft', $found->status );

		$this->assertTrue( $this->repository->update( (int) $item->id, array( 'title' => 'Renamed' ) ) );
		$this->assertSame( 'Renamed', $this->repository->find( (int) $item->id )->title );

		$this->assertTrue( $this->repository->delete( (int) $item->id ) );
		$this->assertNull( $this->repository->find( (int) $item->id ) );
	}

	/**
	 * The service publishes an item through the container-resolved repository.
	 *
	 * @return void
	 */
	public function test_service_publishes_item() {
		$item = $this->repository->create(
			array(
				'title'  => 'Draft item',
				'status' => 'draft',
			)
		);

		$service = new ItemService( $this->repository );
		$published = $service->publish( (int) $item->id );

		$this->assertNotNull( $published );
		$this->assertSame( 'published', $published->status );
	}

	/**
	 * The container resolves the bound service with its dependency injected.
	 *
	 * @return void
	 */
	public function test_container_resolves_item_service() {
		$service = Container::resolve( ItemService::class );

		$this->assertInstanceOf( ItemService::class, $service );
	}
}
