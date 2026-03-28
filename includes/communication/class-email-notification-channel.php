<?php
/**
 * Email notification channel.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Communication;

use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Helpers\Settings_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Sends notifications through WordPress wp_mail.
 */
class Email_Notification_Channel implements Notification_Channel_Interface {
	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Client service.
	 *
	 * @var Client_Service
	 */
	protected $client_service;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null $settings_service Settings service.
	 * @param Client_Service|null   $client_service   Client service.
	 */
	public function __construct( Settings_Service $settings_service = null, Client_Service $client_service = null ) {
		$this->settings_service = $settings_service ? $settings_service : new Settings_Service();
		$this->client_service   = $client_service ? $client_service : new Client_Service();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_channel_key() {
		return 'email';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled() {
		return ! empty( $this->settings_service->get_setting( 'notifications', 'enable_email_notifications', false ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function send( array $notification, array $event_definition = array() ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$to = $this->resolve_recipient_email( $notification );
		if ( '' === $to ) {
			return false;
		}

		$subject = ! empty( $notification['title'] ) ? sanitize_text_field( (string) $notification['title'] ) : '';
		if ( '' === $subject && ! empty( $event_definition['default_title'] ) ) {
			$subject = sanitize_text_field( (string) $event_definition['default_title'] );
		}

		if ( '' === $subject ) {
			return false;
		}

		$message = ! empty( $notification['message'] ) ? sanitize_textarea_field( (string) $notification['message'] ) : '';
		if ( '' === $message && ! empty( $event_definition['default_message'] ) ) {
			$message = sanitize_textarea_field( (string) $event_definition['default_message'] );
		}

		$body = $subject;
		if ( '' !== $message ) {
			$body .= "\n\n" . $message;
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return (bool) wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Resolve recipient email from notification payload.
	 *
	 * @param array<string, mixed> $notification Notification payload.
	 * @return string
	 */
	protected function resolve_recipient_email( array $notification ) {
		$recipient_type = ! empty( $notification['recipient_type'] ) ? sanitize_key( (string) $notification['recipient_type'] ) : '';
		$recipient_id   = ! empty( $notification['recipient_id'] ) ? absint( $notification['recipient_id'] ) : 0;

		if ( ! $recipient_id ) {
			return '';
		}

		if ( 'user' === $recipient_type ) {
			$user = get_userdata( $recipient_id );
			return ( $user && ! empty( $user->user_email ) && is_email( $user->user_email ) ) ? (string) $user->user_email : '';
		}

		if ( 'client' !== $recipient_type ) {
			return '';
		}

		$client = $this->client_service->get_client( $recipient_id );
		if ( ! is_array( $client ) || empty( $client['email'] ) || ! is_email( $client['email'] ) ) {
			return '';
		}

		return (string) $client['email'];
	}
}
