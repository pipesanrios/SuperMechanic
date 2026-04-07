<?php
/**
 * Webhook service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Webhooks;

use Super_Mechanic\Audit\Audit_Service;
use Super_Mechanic\Logs\Log_Service;
use Super_Mechanic\Queue\Queue_Service;

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
	 * Queue service dependency.
	 *
	 * @var Queue_Service|null
	 */
	protected $queue_service;

	/**
	 * Log service dependency.
	 *
	 * @var Log_Service|null
	 */
	protected $log_service;

	/**
	 * Audit service dependency.
	 *
	 * @var Audit_Service|null
	 */
	protected $audit_service;

	/**
	 * Constructor.
	 *
	 * @param Webhook_Repository|null $repository Repository.
	 * @param Queue_Service|null      $queue_service Queue service.
	 * @param Log_Service|null        $log_service Log service.
	 * @param Audit_Service|null      $audit_service Audit service.
	 */
	public function __construct( Webhook_Repository $repository = null, Queue_Service $queue_service = null, Log_Service $log_service = null, Audit_Service $audit_service = null ) {
		$this->repository    = $repository ? $repository : new Webhook_Repository();
		$this->queue_service = $queue_service;
		$this->log_service   = $log_service;
		$this->audit_service = $audit_service;
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

		$after = $this->get_webhook_audit_snapshot( $this->repository->get_webhook_by_id( $inserted_id ) );
		$this->audit_webhook_change(
			'create',
			$inserted_id,
			array(),
			$after,
			array(
				'operation' => 'create_webhook',
			)
		);

		return array(
			'success'    => true,
			'message'    => __( 'Webhook created successfully.', 'super-mechanic' ),
			'webhook_id' => $inserted_id,
		);
	}

	/**
	 * Update webhook.
	 *
	 * @param int                 $webhook_id Webhook ID.
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

		$before  = $this->get_webhook_audit_snapshot( $this->repository->get_webhook_by_id( $webhook_id ) );
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

		$after = $this->get_webhook_audit_snapshot( $this->repository->get_webhook_by_id( $webhook_id ) );
		$this->audit_webhook_change(
			'update',
			$webhook_id,
			$before,
			$after,
			array(
				'operation' => 'update_webhook',
			)
		);

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

		$before  = $this->get_webhook_audit_snapshot( $this->repository->get_webhook_by_id( $webhook_id ) );
		$deleted = $this->repository->delete_webhook( $webhook_id );
		if ( ! $deleted ) {
			return array(
				'success' => false,
				'message' => __( 'Could not delete webhook.', 'super-mechanic' ),
			);
		}

		$this->audit_webhook_change(
			'delete',
			$webhook_id,
			$before,
			array(),
			array(
				'operation' => 'delete_webhook',
			)
		);

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

		$before  = $this->get_webhook_audit_snapshot( $this->repository->get_webhook_by_id( $webhook_id ) );
		$updated = $this->repository->set_webhook_active( $webhook_id, (bool) $is_active );
		if ( ! $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Could not update webhook status.', 'super-mechanic' ),
			);
		}

		$after = $this->get_webhook_audit_snapshot( $this->repository->get_webhook_by_id( $webhook_id ) );
		$this->audit_webhook_change(
			$is_active ? 'activate' : 'deactivate',
			$webhook_id,
			$before,
			$after,
			array(
				'operation' => 'set_webhook_active',
			)
		);

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
	 * Execute webhook dispatch directly (used by queue worker).
	 *
	 * @param string              $event_type Event key.
	 * @param array<string,mixed> $payload Event payload.
	 * @return array<string,mixed>
	 */
	public function dispatch_from_queue( $event_type, array $payload ) {
		return $this->dispatch_immediate( $event_type, $payload );
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
			$this->log_webhook( 'dispatch', 'error', 'Webhook dispatch received invalid event.', array() );
			return array(
				'success' => false,
				'sent'    => 0,
				'failed'  => 0,
			);
		}

		$queued = $this->enqueue_webhook_job( $event_type, $payload );
		if ( $queued > 0 ) {
			$this->log_webhook( 'dispatch', 'info', 'Webhook dispatch job queued.', array( 'event_type' => $event_type, 'job_id' => $queued ), $queued );
			return array(
				'success' => true,
				'sent'    => 0,
				'failed'  => 0,
				'queued'  => true,
				'job_id'  => $queued,
			);
		}

		error_log( sprintf( '[SM_WEBHOOK][ERROR] queue enqueue failed fallback immediate event=%s', $event_type ) );
		$this->log_webhook( 'dispatch', 'warning', 'Webhook queue failed, fallback immediate dispatch.', array( 'event_type' => $event_type ) );
		return $this->dispatch_immediate( $event_type, $payload );
	}

	/**
	 * Dispatch one event payload directly.
	 *
	 * @param string              $event_type Event key.
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,mixed>
	 */
	protected function dispatch_immediate( $event_type, array $payload ) {
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
			$this->log_webhook( 'dispatch', 'info', 'No active webhooks for event.', array( 'event_type' => $event_type ) );
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

		$this->log_webhook( 'dispatch', $failed > 0 ? 'warning' : 'success', $failed > 0 ? 'Webhook dispatch completed with partial failures.' : 'Webhook dispatch completed successfully.', array(
			'event_type' => $event_type,
			'sent'       => $sent,
			'failed'     => $failed,
		) );
		return array(
			'success' => true,
			'sent'    => $sent,
			'failed'  => $failed,
		);
	}

	/**
	 * Enqueue webhook dispatch job.
	 *
	 * @param string              $event_type Event key.
	 * @param array<string,mixed> $payload Raw payload.
	 * @return int
	 */
	protected function enqueue_webhook_job( $event_type, array $payload ) {
		$queue = $this->get_queue_service();
		if ( ! $queue instanceof Queue_Service ) {
			return 0;
		}

		return absint(
			$queue->enqueue(
				'webhook',
				array(
					'event_type' => sanitize_key( (string) $event_type ),
					'payload'    => $payload,
				)
			)
		);
	}

	/**
	 * Resolve queue service lazily.
	 *
	 * @return Queue_Service|null
	 */
	protected function get_queue_service() {
		if ( $this->queue_service instanceof Queue_Service ) {
			return $this->queue_service;
		}

		try {
			$this->queue_service = new Queue_Service();
			return $this->queue_service;
		} catch ( \Throwable $throwable ) {
			error_log( sprintf( '[SM_WEBHOOK][ERROR] queue service unavailable error=%s', $throwable->getMessage() ) );
		}

		return null;
	}

	/**
	 * Resolve log service lazily.
	 *
	 * @return Log_Service|null
	 */
	protected function get_log_service() {
		if ( $this->log_service instanceof Log_Service ) {
			return $this->log_service;
		}

		try {
			$this->log_service = new Log_Service();
			return $this->log_service;
		} catch ( \Throwable $throwable ) {
			return null;
		}
	}

	/**
	 * Resolve audit service lazily.
	 *
	 * @return Audit_Service|null
	 */
	protected function get_audit_service() {
		if ( $this->audit_service instanceof Audit_Service ) {
			return $this->audit_service;
		}

		try {
			$this->audit_service = new Audit_Service();
			return $this->audit_service;
		} catch ( \Throwable $throwable ) {
			return null;
		}
	}

	/**
	 * Write webhook log.
	 *
	 * @param string              $source Source.
	 * @param string              $status Status.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @param int                 $reference_id Reference ID.
	 * @return void
	 */
	protected function log_webhook( $source, $status, $message, array $context = array(), $reference_id = 0 ) {
		$logger = $this->get_log_service();
		if ( ! $logger instanceof Log_Service ) {
			return;
		}

		$logger->log_webhook_event( $source, $status, $message, $context, $reference_id );
	}

	/**
	 * Write webhook audit row.
	 *
	 * @param string              $action Action.
	 * @param int                 $webhook_id Webhook ID.
	 * @param array<string,mixed> $before Before payload.
	 * @param array<string,mixed> $after After payload.
	 * @param array<string,mixed> $context Context payload.
	 * @return void
	 */
	protected function audit_webhook_change( $action, $webhook_id, array $before, array $after, array $context = array() ) {
		$audit = $this->get_audit_service();
		if ( ! $audit instanceof Audit_Service ) {
			return;
		}

		$business_id = 0;
		if ( isset( $after['business_id'] ) ) {
			$business_id = absint( $after['business_id'] );
		}
		if ( $business_id <= 0 && isset( $before['business_id'] ) ) {
			$business_id = absint( $before['business_id'] );
		}

		$audit->audit_webhook_change(
			sanitize_key( (string) $action ),
			absint( $webhook_id ),
			$before,
			$after,
			$context,
			get_current_user_id(),
			$business_id
		);
	}

	/**
	 * Compact webhook payload for audit without exposing secrets.
	 *
	 * @param array<string,mixed>|null $webhook Webhook row.
	 * @return array<string,mixed>
	 */
	protected function get_webhook_audit_snapshot( $webhook ) {
		if ( ! is_array( $webhook ) ) {
			return array();
		}

		$url = isset( $webhook['url'] ) ? (string) $webhook['url'] : '';
		if ( '' === $url && isset( $webhook['endpoint_url'] ) ) {
			$url = (string) $webhook['endpoint_url'];
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return array(
			'id'            => isset( $webhook['id'] ) ? absint( $webhook['id'] ) : 0,
			'business_id'   => isset( $webhook['business_id'] ) ? absint( $webhook['business_id'] ) : 0,
			'name'          => isset( $webhook['name'] ) ? sanitize_text_field( (string) $webhook['name'] ) : '',
			'event_type'    => isset( $webhook['event_type'] ) ? sanitize_key( (string) $webhook['event_type'] ) : '',
			'is_active'     => ! empty( $webhook['is_active'] ) || ( isset( $webhook['status'] ) && 'active' === sanitize_key( (string) $webhook['status'] ) ),
			'endpoint_host' => is_string( $host ) ? sanitize_text_field( $host ) : '',
			'has_secret'    => ( isset( $webhook['secret_key'] ) && '' !== (string) $webhook['secret_key'] ),
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
			$this->log_webhook( 'dispatch', 'error', 'Webhook endpoint URL missing.', array( 'event_type' => sanitize_key( (string) $event_type ) ) );
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
			$this->log_webhook( 'fail', 'error', 'Webhook endpoint request failed.', array( 'event_type' => sanitize_key( (string) $event_type ) ) );
			return false;
		}

		$code = absint( wp_remote_retrieve_response_code( $response ) );
		if ( $code < 200 || $code >= 300 ) {
			error_log( sprintf( '[SM_WEBHOOK][ERROR] non-2xx event=%s url=%s code=%d', sanitize_key( (string) $event_type ), $url, $code ) );
			$this->log_webhook( 'fail', 'error', 'Webhook endpoint returned non-2xx status.', array( 'event_type' => sanitize_key( (string) $event_type ), 'http_code' => $code ) );
			return false;
		}

		$this->log_webhook( 'success', 'success', 'Webhook delivered to endpoint.', array( 'event_type' => sanitize_key( (string) $event_type ), 'http_code' => $code ) );
		return true;
	}
}
