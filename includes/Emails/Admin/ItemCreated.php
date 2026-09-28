<?php
/**
 * "Item created" admin notification.
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails\Admin
 */

namespace RadiusTheme\RadiusHotelBooking\Emails\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseEmail;

/**
 * Class ItemCreated
 *
 * BOILERPLATE: the example email. It listens on the `rtbp_item_created` action
 * fired by the Item model's `created` event (Core/config/events.php), builds
 * the merge-tag payload, and renders templates/emails/admin/item-created.php.
 */
class ItemCreated extends BaseEmail {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'item_created_admin';
		$this->title          = __( 'New item (admin)', 'radius-hotel-booking' );
		$this->description    = __( 'Sent to the site administrator when a new item is created.', 'radius-hotel-booking' );
		$this->recipient_type = 'admin';
		$this->template_html  = 'admin/item-created.php';

		parent::__construct();
	}

	/**
	 * Email id.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * The action that triggers this email.
	 *
	 * @return string
	 */
	public function get_trigger_action() {
		return 'rtbp_item_created';
	}

	/**
	 * Default settings, editable from the admin UI.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'enabled'   => true,
			'recipient' => get_option( 'admin_email' ),
			'subject'   => __( 'New item created: {item_title}', 'radius-hotel-booking' ),
			'heading'   => __( 'A new item was created', 'radius-hotel-booking' ),
			'use_queue' => false,
		);
	}

	/**
	 * Build the payload passed to the template and merge tags.
	 *
	 * @param array $args Arguments from the trigger action; the first is the model.
	 *
	 * @return array
	 */
	protected function prepare_email_data( $args ) {
		$item     = $args[0] ?? null;
		$settings = $this->get_settings();

		if ( ! $item ) {
			return array();
		}

		return array(
			'to'         => $settings['recipient'],
			'data'       => $item,
			'merge_tags' => array(
				'item_title' => $item->title ?? '',
				'item_id'    => $item->id ?? '',
				'status'     => $item->status ?? '',
				'price'      => $item->price ?? '',
				'date'       => date_i18n( get_option( 'date_format' ) ),
			),
		);
	}
}
