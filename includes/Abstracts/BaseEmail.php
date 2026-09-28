<?php
/**
 * Base Email Class
 *
 * @package RadiusTheme\RadiusHotelBooking\Abstracts
 */

namespace RadiusTheme\RadiusHotelBooking\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use RadiusTheme\RadiusHotelBooking\Emails\EmailSender;
use RadiusTheme\RadiusHotelBooking\Emails\MergeTags;
use RadiusTheme\RadiusHotelBooking\Emails\TemplateRenderer;
use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;

/**
 * Base Email Abstract Class
 */
abstract class BaseEmail {

	/**
	 * Email ID.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Email title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Email description.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Email tooltip.
	 *
	 * @var string
	 */
	public $tooltip;

	/**
	 * Recipient type.
	 *
	 * @var string
	 */
	public $recipient_type;

	/**
	 * HTML template.
	 *
	 * @var string
	 */
	public $template_html;

	/**
	 * Email settings.
	 *
	 * @var array
	 */
	public $settings;

	/**
	 * Whether email is enabled.
	 *
	 * @var bool
	 */
	public $enabled = true;

	/**
	 * Email status, set by the concrete email when it needs one.
	 *
	 * @var string|null
	 */
	public $status;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = $this->get_settings();
		$this->register_hooks();
	}

	/**
	 * Get email ID.
	 *
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * Get trigger action.
	 *
	 * @return string
	 */
	abstract public function get_trigger_action();

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	abstract public function get_default_settings();

	/**
	 * Prepare email data.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	abstract protected function prepare_email_data( $args );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		$trigger = $this->get_trigger_action();
		if ( $trigger ) {
			add_action( $trigger, array( $this, 'trigger' ), 10, 10 );
		}
	}

	/**
	 * Get email title.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Get email description.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get email settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$option_key = 'rtbp_email_' . $this->get_id();
		$settings   = get_option( $option_key, $this->get_default_settings() );

		return wp_parse_args( $settings, $this->get_default_settings() );
	}

	/**
	 * Update email settings.
	 *
	 * @param array $new_settings New settings.
	 * @return bool
	 */
	public function update_settings( $new_settings ): bool {
		$option_key = 'rtbp_email_' . $this->get_id();
		$settings   = array_merge( $this->get_settings(), $new_settings );

		update_option( $option_key, $settings, false );
		$this->settings = $settings;

		return true;
	}

	/**
	 * Whether the template's own settings switch it on (the default shown in
	 * Settings → E-mail).
	 *
	 * @return bool
	 */
	public function is_enabled_by_default(): bool {
		$settings = $this->get_settings();
		return isset( $settings['enabled'] ) && $settings['enabled'];
	}

	/**
	 * Check if email is enabled: Settings → E-mail's master switch, then the
	 * template's switch there, then the template's own default.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$email = SettingsHelper::get_setting( 'email' );
		$email = is_array( $email ) ? $email : array();
		if ( array_key_exists( 'enabled', $email ) && ! $email['enabled'] ) {
			return false;
		}

		$templates = is_array( $email['templates'] ?? null ) ? $email['templates'] : array();
		if ( array_key_exists( $this->get_id(), $templates ) ) {
			return (bool) $templates[ $this->get_id() ];
		}

		return $this->is_enabled_by_default();
	}

	/**
	 * Trigger email.
	 *
	 * @param mixed ...$args Arguments.
	 * @return bool
	 */
	public function trigger( ...$args ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$email_data = $this->prepare_email_data( $args );
		if ( empty( $email_data ) || ! isset( $email_data['to'] ) ) {
			return false;
		}

		if ( ! $this->validate_email_data( $email_data ) ) {
			return false;
		}

		$email_data = apply_filters( 'rtbp_be_before_send_email', $email_data, $this );
		$email_data = apply_filters( 'rtbp_be_before_send_email_' . $this->get_id(), $email_data, $this );

		if ( $this->should_queue() ) {
			/**
			 * Defer sending. Nothing listens by default — hook a scheduler
			 * (Action Scheduler, WP-Cron, a queue add-on) here and call
			 * $email->send( $email_data ) from the deferred job.
			 *
			 * @param string $email_id   Email identifier.
			 * @param array  $email_data Prepared email payload.
			 */
			do_action( 'rtbp_queue_email', $this->get_id(), $email_data );

			return true;
		} else {
			return $this->send( $email_data );
		}
	}

	/**
	 * Validate email data.
	 *
	 * @param array $email_data Email data.
	 * @return bool
	 */
	protected function validate_email_data( $email_data ): bool {
		if ( empty( $email_data['to'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether this email should be handed to a queue rather than sent inline.
	 *
	 * Off by default: the hotel booking ships no scheduler. Turn `use_queue` on in
	 * the email's settings and listen to `rtbp_queue_email` to add one.
	 *
	 * @return bool
	 */
	protected function should_queue(): bool {
		$settings = $this->get_settings();
		return ! empty( $settings['use_queue'] ) && has_action( 'rtbp_queue_email' );
	}

	/**
	 * Send email.
	 *
	 * @param array $email_data Email data.
	 * @return bool
	 */
	public function send( $email_data ) {
		$settings = $this->get_settings();

		$subject = MergeTags::replace( $settings['subject'], $email_data['merge_tags'] );

		$html_body = TemplateRenderer::render(
			$this->template_html,
			array(
				'settings'   => $settings,
				'data'       => $email_data['data'],
				'merge_tags' => $email_data['merge_tags'],
				'email'      => $this,
			)
		);

		$html_body = MergeTags::replace( $html_body, $email_data['merge_tags'] );

		$result = EmailSender::send(
			$email_data['to'],
			$subject,
			$html_body,
			$email_data
		);

		do_action( 'rtbp_after_send_email', $result, $email_data, $this );
		do_action( 'rtbp_after_send_email_' . $this->get_id(), $result, $email_data, $this );

		return $result;
	}

	/**
	 * Get preview data for email template.
	 *
	 * @return array
	 */
	public function get_preview_data(): array {
		return array(
			'recipient_name'  => 'John Doe',
			'recipient_email' => 'john@example.com',
			'date'            => 'Monday, October 20, 2025',
			'time'            => '2:00 PM',
			'price'           => '$75.00',
			'status'          => 'Published',
			'notes'           => 'Anything worth saying about this item.',
		);
	}
}
