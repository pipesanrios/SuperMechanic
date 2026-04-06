<?php
/**
 * Webhook service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Webhooks;

defined( 'ABSPATH' ) || exit;

/**
 * Outbound webhook dispatcher service.
 */
class Webhook_Service {
	/**
	 * Supported webhook events.
	 *
	 * @var array<int,string>
	 */
	protected $supported_events = array(
		'membership_created',
		'membership_updated',
		'user_transferred',
		'overdue_alert_detected',
		'critical_signal_detected',
	);
	/**
	 * Repository dependency.
	 *
	 * @var Webhook_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Webhook_Repository|null $repository Repository.
	 */
	public function __construct( Webhook_Repository $repository = null ) {
		$this->repository = $repository ? $repository : new Webhook_Repository();
	}

	/**
	 * Resolve active webhooks for one event.
	 *
	 * @param string $event_type Event key.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_active_webhooks_by_event( $event_type ) {
		return $this->repository->get_active_webhooks_by_event( $event_type );
	}

	/**
	 * Get supported event list.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_events() {
		return $this->supported_events;
	}

	/**
	 * Get all webhooks for current business.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_webhooks() {
		return $this->repository->get_webhooks();
	}

	/**
	 * Get webhook by id.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return array<string,mixed>|null
	 */
	public function get_webhook_by_id( $webhook_id ) {
		return $this->repository->get_webhook_by_id( $webhook_id );
	}

	/**
	 * Create webhook.
	 *
	 * @param array<string,mixed> $data Payload.
	 * @return array<string,mixed>
	 */
	public function create_webhook( array $data ) {
		$payload = $this->validate_webhook_payload( $data );
		if ( ! $payload['success'] ) {
			return $payload;
		}

		$inserted_id = $this->repository->create_webhook( $payload['data'] );
		if ( $inserted_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create webhook.', 'super-mechanic' ),
			);
		}

		return array(
			'success'    => true,
			'message'    => __( 'Webhook created successfully.', 'super-mechanic' ),
			'webhook_id' => $inserted_id,
		);
	}

	/**
	 * Update webhook.
	 *
	 * @param int                $webhook_id Webhook ID.
	 * @param array<string,mixed> $data Payload.
	 * @return array<string,mixed>
	 */
	public function update_webhook( $webhook_id, array $data ) {
		$webhook_id = absint( $webhook_id );
		if ( $webhook_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid webhook ID.', 'super-mechanic' ),
			);
		}

		$payload = $this->validate_webhook_payload( $data );
		if ( ! $payload['success'] ) {
			return $payload;
		}

		$updated = $this->repository->update_webhook( $webhook_id, $payload['data'] );
		if ( ! $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Could not update webhook.', 'super-mechanic' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Webhook updated successfully.', 'super-mechanic' ),
		);
	}

	/**
	 * Delete webhook.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return array<string,mixed>
	 */
	public function delete_webhook( $webhook_id ) {
		$webhook_id = absint( $webhook_id );
		if ( $webhook_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid webhook ID.', 'super-mechanic' ),
			);
		}

		$deleted = $this->repository->delete_webhook( $webhook_id );
		if ( ! $deleted ) {
			return array(
				'success' => false,
				'message' => __( 'Could not delete webhook.', 'super-mechanic' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Webhook deleted successfully.', 'super-mechanic' ),
		);
	}

	/**
	 * Toggle webhook active status.
	 *
	 * @param int  $webhook_id Webhook ID.
	 * @param bool $is_active Active flag.
	 * @return array<string,mixed>
	 */
	public function set_webhook_active( $webhook_id, $is_active ) {
		$webhook_id = absint( $webhook_id );
		if ( $webhook_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid webhook ID.', 'super-mechanic' ),
			);
		}

		$updated = $this->repository->set_webhook_active( $webhook_id, (bool) $is_active );
		if ( ! $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Could not update webhook status.', 'super-mechanic' ),
			);
		}

		return array(
			'success' => true,
			'message' => $is_active
				? __( 'Webhook activated.', 'super-mechanic' )
				: __( 'Webhook deactivated.', 'super-mechanic' ),
		);
	}

	/**
	 * Send one webhook test payload.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return array<string,mixed>
	 */
	public function send_test_webhook( $webhook_id ) {
		$webhook_id = absint( $webhook_id );
		$webhook    = $this->repository->get_webhook_by_id( $webhook_id );
		if ( ! is_array( $webhook ) ) {
			return array(
				'success' => false,
				'message' => __( 'Webhook not found.', 'super-mechanic' ),
			);
		}

		$event_type = isset( $webhook['event_type'] ) ? sanitize_key( (string) $webhook['event_type'] ) : '';
		if ( ! in_array( $event_type, $this->supported_events, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Webhook event type is not supported.', 'super-mechanic' ),
			);
		}

		$payload = array(
			'event'     => $event_type,
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
			'data'      => array(
				'test'       => true,
				'webhook_id' => $webhook_id,
				'note'       => 'super-mechanic-webhook-test',
			),
		);

		$sent = $this->send_payload_to_webhook( $webhook, $payload, $event_type );
		if ( ! $sent ) {
			return array(
				'success' => false,
				'message' => __( 'Webhook test failed.', 'super-mechanic' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Webhook test sent successfully.', 'super-mechanic' ),
		);
	}

	/**
	 * Dispatch one event from automation engine flow.
	 *
	 * @param string              $event_type Event key.
	 * @param array<string,mixed> $payload Event payload.
	 * @return array<string,mixed>
	 */
	public function dispatch_from_engine( $event_type, array $payload ) {
		return $this->dispatch( $event_type, $payload );
	}
	/**
	 * Dispatch one event payload to subscribed webhooks.
	 *
	 * @param string              $event_type Event key.
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,mixed>
	 */
	public function dispatch( $event_type, array $payload ) {
		$event_type = sanitize_key( (string) $event_type );
		if ( '' === $event_type ) {
			return array(
				'success' => false,
				'sent'    => 0,
				'failed'  => 0,
			);
		}

		$webhooks = $this->get_active_webhooks_by_event( $event_type );
		if ( empty( $webhooks ) ) {
			return array(
				'success' => true,
				'sent'    => 0,
				'failed'  => 0,
			);
		}

		$dispatch_payload = array(
			'event'     => $event_type,
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => isset( $payload['user_id'] ) ? absint( $payload['user_id'] ) : get_current_user_id(),
			'data'      => isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : $payload,
		);

		$sent   = 0;
		$failed = 0;

		foreach ( $webhooks as $webhook ) {
			if ( $this->send_payload_to_webhook( $webhook, $dispatch_payload, $event_type ) ) {
				++$sent;
			} else {
				++$failed;
			}
		}

		return array(
			'success' => true,
			'sent'    => $sent,
			'failed'  => $failed,
		);
	}

	/**
	 * Validate and sanitize webhook payload.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array<string,mixed>
	 */
	protected function validate_webhook_payload( array $data ) {
		$name       = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$url        = isset( $data['url'] ) ? esc_url_raw( (string) $data['url'] ) : '';
		$event_type = isset( $data['event_type'] ) ? sanitize_key( (string) $data['event_type'] ) : '';
		$secret_key = isset( $data['secret_key'] ) ? sanitize_text_field( (string) $data['secret_key'] ) : '';
		$is_active  = ! empty( $data['is_active'] );

		if ( '' === $name ) {
			return array(
				'success' => false,
				'message' => __( 'Webhook name is required.', 'super-mechanic' ),
			);
		}
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return array(
				'success' => false,
				'message' => __( 'A valid webhook URL is required.', 'super-mechanic' ),
			);
		}
		if ( ! in_array( $event_type, $this->supported_events, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid webhook event type.', 'super-mechanic' ),
			);
		}

		return array(
			'success' => true,
			'data'    => array(
				'name'       => $name,
				'url'        => $url,
				'event_type' => $event_type,
				'secret_key' => $secret_key,
				'is_active'  => $is_active ? 1 : 0,
			),
		);
	}

	/**
	 * Send one payload to one webhook endpoint.
	 *
	 * @param array<string,mixed> $webhook Webhook row.
	 * @param array<string,mixed> $payload Payload body.
	 * @param string              $event_type Event type.
	 * @return bool
	 */
	protected function send_payload_to_webhook( array $webhook, array $payload, $event_type ) {
		$url = isset( $webhook['url'] ) && '' !== (string) $webhook['url']
			? esc_url_raw( (string) $webhook['url'] )
			: esc_url_raw( isset( $webhook['endpoint_url'] ) ? (string) $webhook['endpoint_url'] : '' );

		if ( '' === $url ) {
			return false;
		}

		$encoded_payload = wp_json_encode( $payload );
		if ( false === $encoded_payload ) {
			$encoded_payload = '{}';
		}

		$headers = array(
			'Content-Type' => 'application/json; charset=utf-8',
		);
		$secret  = isset( $webhook['secret_key'] ) ? (string) $webhook['secret_key'] : '';
		if ( '' !== $secret ) {
			$headers['X-SM-Signature'] = hash_hmac( 'sha256', $encoded_payload, $secret );
		}

		$response = wp_remote_post(
			$url,
			array(
				'method'      => 'POST',
				'timeout'     => 3,
				'headers'     => $headers,
				'body'        => $encoded_payload,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( sprintf( '[SM_WEBHOOK][ERROR] dispatch failed event=%s url=%s error=%s', sanitize_key( (string) $event_type ), $url, $response->get_error_message() ) );
			return false;
		}

		$code = absint( wp_remote_retrieve_response_code( $response ) );
		if ( $code < 200 || $code >= 300 ) {
			error_log( sprintf( '[SM_WEBHOOK][ERROR] non-2xx event=%s url=%s code=%d', sanitize_key( (string) $event_type ), $url, $code ) );
			return false;
		}

		return true;
	}
}

