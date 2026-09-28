<?php
/**
 * Email Sender
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails
 * @since 1.0.0
 * @author
 * @version 1.0.0
 */
namespace RadiusTheme\RadiusHotelBooking\Emails;

use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class EmailSender
 *
 * Handles sending of emails with customizable headers, attachments, and filtering options.
 */
class EmailSender {

	/**
	 * Sends an email with the specified parameters and email data.
	 *
	 * @param string $to The recipient(s) of the email. This can be a single email address or multiple addresses separated by commas.
	 * @param string $subject The subject of the email.
	 * @param string $message The body of the email.
	 * @param array $email_data Optional. An associative array of additional email data, including headers and attachments.
	 *     - 'attachments' (array): An array of file paths for attachments.
	 *
	 * @return bool True if the email was sent successfully, false otherwise.
	 */
	public static function send( $to, $subject, $message, $email_data = array() ) {
		$headers     = self::prepare_headers( $email_data );
		$attachments = isset( $email_data['attachments'] ) ? $email_data['attachments'] : array();

		$to      = apply_filters( 'rtbp_email_recipient', $to, $email_data );
		$subject = apply_filters( 'rtbp_email_subject', $subject, $email_data );
		$message = apply_filters( 'rtbp_email_message', $message, $email_data );
		$headers = apply_filters( 'rtbp_email_headers', $headers, $email_data );

		$result = wp_mail( $to, $subject, $message, $headers, $attachments );

		self::cleanup_attachments( $attachments );

		return $result;
	}

	/**
	 * Prepares email headers based on the provided email data and default settings.
	 *
	 * @param array $email_data An associative array of email data that can include:
	 *     - 'cc' (array): An array of email addresses to be added as CC recipients.
	 *     - 'bcc' (array): An array of email addresses to be added as BCC recipients.
	 *
	 * @return array An array of headers for the email, including content type, sender, reply-to, and optional CC/BCC addresses.
	 */
	private static function prepare_headers( $email_data ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$email_setting    = SettingsHelper::get_setting( 'email' );
		$general_setting = SettingsHelper::get_setting( 'general' );

		$from_name = ! empty( $email_setting['senderName'] )
			? $email_setting['senderName']
			: ( ! empty( $general_setting['companyName'] ) ? $general_setting['companyName'] : get_bloginfo( 'name' ) );

		$from_email = ! empty( $email_setting['senderEmail'] )
			? $email_setting['senderEmail']
			: get_option( 'admin_email' );

		if ( $from_email && $from_name ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
		}

		$reply_to = ! empty( $email_setting['replyToEmail'] ) ? $email_setting['replyToEmail'] : $from_email;
		if ( $reply_to ) {
			$headers[] = sprintf( 'Reply-To: %s', $reply_to );
		}

		if ( isset( $email_data['cc'] ) && is_array( $email_data['cc'] ) ) {
			foreach ( $email_data['cc'] as $cc_email ) {
				$headers[] = sprintf( 'Cc: %s', $cc_email );
			}
		}

		if ( isset( $email_data['bcc'] ) && is_array( $email_data['bcc'] ) ) {
			foreach ( $email_data['bcc'] as $bcc_email ) {
				$headers[] = sprintf( 'Bcc: %s', $bcc_email );
			}
		}

		return $headers;
	}

	/**
	 * Deletes temporary email attachment files from the filesystem.
	 *
	 * @param array $attachments An array of file paths for attachments to be cleaned up. Each file path should refer to a file in the rtbp-emails directory within the WordPress uploads folder.
	 *
	 * @return void
	 */
	private static function cleanup_attachments( $attachments ) {
		if ( ! is_array( $attachments ) ) {
			return;
		}

		foreach ( $attachments as $file ) {
			if ( file_exists( $file ) && strpos( $file, wp_upload_dir()['basedir'] . '/rtbp-emails/' ) === 0 ) {
				wp_delete_file( $file );
			}
		}
	}
}
