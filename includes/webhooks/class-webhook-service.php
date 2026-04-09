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
		'process_created',
		'process_updated',
		'quote_approved',
		'invoice_paid',
		'payment_registered',
		'process.created',
		'process.updated',
		'quote.approved',
		'invoice.paid',
		'payment.created',
	);

	/**
	 * Canonical-to-legacy event aliases.
	 *
	 * @var array<string,string>
	 */
	protected $event_aliases = array(
		'process.created' => 'process_created',
		'process.updated' => 'process_updated',
		'quote.approved'  => 'quote_approved',
		'invoice.paid'    => 'invoice_paid',
		'payment.created' => 'payment_registered',
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
	 * Get normalized event options for admin forms (canonical-first, unique).
	 *
	 * @return array<int,string>
	 */
	public function get_supported_events_for_admin() {
		$options = array();

		foreach ( $this->supported_events as $event_name ) {
			$canonical = $this->to_canonical_event_name( $event_name );
			if ( '' === $canonical ) {
				continue;
			}

			if ( ! in_array( $canonical, $options, true ) ) {
				$options[] = $canonical;
			}
		}

		return $options;
	}

	/**
	 * Resolve canonical event name for UI/display.
	 *
	 * @param string $event_name Raw event key.
	 * @return string
	 */
	public function get_canonical_event_name( $event_name ) {
		return $this->to_canonical_event_name( $event_name );
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

		$event_type = isset( $webhook['event_type'] ) ? $this->sanitize_event_name( (string) $webhook['event_type'] ) : '';
		if ( ! $this->is_supported_event( $event_type ) ) {
			return array(
				'success' => false,
				'message' => __( 'Webhook event type is not supported.', 'super-mechanic' ),
			);
		}

		$event_context = $this->resolve_event_context( $event_type );
		if ( empty( $event_context ) ) {
			return array(
				'success' => false,
				'message' => __( 'Webhook event type is invalid.', 'super-mechanic' ),
			);
		}

		$payload = $this->normalize_event_payload(
			$event_context['canonical_event'],
			array(
				'business_id' => isset( $webhook['business_id'] ) ? absint( $webhook['business_id'] ) : 0,
				'data'        => array(
					'test'       => true,
					'webhook_id' => $webhook_id,
					'note'       => 'super-mechanic-webhook-test',
				),
			),
			$event_context['lookup_event']
		);

		$sent = $this->send_payload_to_webhook( $webhook, $payload, $event_context['canonical_event'] );
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
		return $this->dispatch_event( $event_type, $payload );
	}

	/**
	 * Execute webhook dispatch directly (used by queue worker).
	 *
	 * @param string              $event_type Event key.
	 * @param array<string,mixed> $payload Event payload.
	 * @return array<string,mixed>
	 */
	public function dispatch_from_queue( $event_type, array $payload ) {
		return $this->dispatch_event( $event_type, $payload, true );
	}

	/**
	 * Dispatch one event payload using normalized context.
	 *
	 * @param string              $event_name      Event key.
	 * @param array<string,mixed> $payload         Raw payload.
	 * @param bool                $force_immediate Whether queue must be bypassed.
	 * @return array<string,mixed>
	 */
	public function dispatch_event( $event_name, array $payload = array(), $force_immediate = false ) {
		$event_context = $this->resolve_event_context( $event_name );
		if ( empty( $event_context ) ) {
			$this->log_webhook( 'dispatch', 'error', 'Webhook dispatch received invalid event.', array() );
			return array(
				'success' => false,
				'sent'    => 0,
				'failed'  => 0,
			);
		}

		if ( ! $force_immediate ) {
			$queued = $this->enqueue_webhook_job( $event_context['lookup_event'], $payload );
			if ( $queued > 0 ) {
				$this->log_webhook(
					'dispatch',
					'info',
					'Webhook dispatch job queued.',
					array(
						'event_type'      => $event_context['canonical_event'],
						'lookup_event'    => $event_context['lookup_event'],
						'lookup_events'   => $event_context['lookup_events'],
						'job_id'          => $queued,
					),
					$queued
				);

				return array(
					'success'   => true,
					'sent'      => 0,
					'failed'    => 0,
					'queued'    => true,
					'job_id'    => $queued,
					'event'     => $event_context['canonical_event'],
				);
			}

			error_log( sprintf( '[SM_WEBHOOK][ERROR] queue enqueue failed fallback immediate event=%s', $event_context['canonical_event'] ) );
			$this->log_webhook(
				'dispatch',
				'warning',
				'Webhook queue failed, fallback immediate dispatch.',
				array(
					'event_type'    => $event_context['canonical_event'],
					'lookup_events' => $event_context['lookup_events'],
				)
			);
		}

		return $this->dispatch_immediate( $event_context, $payload );
	}

	/**
	 * Dispatch one event payload to subscribed webhooks.
	 *
	 * @param string              $event_type Event key.
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,mixed>
	 */
	public function dispatch( $event_type, array $payload ) {
		return $this->dispatch_event( $event_type, $payload );
	}

	/**
	 * Build standardized webhook-compatible payload for external integrations.
	 *
	 * @param string              $event_name Event key (canonical or legacy).
	 * @param array<string,mixed> $payload    Raw payload.
	 * @return array<string,mixed>
	 */
	public function build_standard_event_payload( $event_name, array $payload = array() ) {
		$event_context = $this->resolve_event_context( $event_name );
		if ( empty( $event_context ) ) {
			$event_name = $this->sanitize_event_name( $event_name );
			if ( '' === $event_name ) {
				$event_name = 'process.updated';
			}

			return $this->normalize_event_payload( $event_name, $payload, '' );
		}

		return $this->normalize_event_payload(
			$event_context['canonical_event'],
			$payload,
			isset( $event_context['lookup_event'] ) ? (string) $event_context['lookup_event'] : ''
		);
	}

	/**
	 * Dispatch one event payload directly.
	 *
	 * @param string              $event_type Event key.
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,mixed>
	 */
	protected function dispatch_immediate( array $event_context, array $payload ) {
		$canonical_event = isset( $event_context['canonical_event'] ) ? $this->sanitize_event_name( (string) $event_context['canonical_event'] ) : '';
		$lookup_events   = isset( $event_context['lookup_events'] ) && is_array( $event_context['lookup_events'] ) ? $event_context['lookup_events'] : array();

		if ( '' === $canonical_event ) {
			return array(
				'success' => false,
				'sent'    => 0,
				'failed'  => 0,
			);
		}

		if ( empty( $lookup_events ) ) {
			$lookup_events = array( $canonical_event );
		}

		$webhooks_by_id = array();
		foreach ( $lookup_events as $lookup_event ) {
			$lookup_event = $this->sanitize_event_name( (string) $lookup_event );
			if ( '' === $lookup_event ) {
				continue;
			}

			$rows = $this->get_active_webhooks_by_event( $lookup_event );
			foreach ( $rows as $row ) {
				$webhook_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
				if ( $webhook_id > 0 ) {
					$webhooks_by_id[ $webhook_id ] = $row;
				}
			}
		}

		if ( empty( $webhooks_by_id ) ) {
			$this->log_webhook(
				'dispatch',
				'info',
				'No active webhooks for event.',
				array(
					'event_type'    => $canonical_event,
					'lookup_events' => $lookup_events,
				)
			);
			return array(
				'success' => true,
				'sent'    => 0,
				'failed'  => 0,
			);
		}

		$dispatch_payload = $this->normalize_event_payload( $canonical_event, $payload, isset( $event_context['lookup_event'] ) ? (string) $event_context['lookup_event'] : '' );

		$sent   = 0;
		$failed = 0;

		foreach ( $webhooks_by_id as $webhook ) {
			if ( $this->send_payload_to_webhook( $webhook, $dispatch_payload, $canonical_event ) ) {
				++$sent;
			} else {
				++$failed;
			}
		}

		$this->log_webhook( 'dispatch', $failed > 0 ? 'warning' : 'success', $failed > 0 ? 'Webhook dispatch completed with partial failures.' : 'Webhook dispatch completed successfully.', array(
			'event_type'    => $canonical_event,
			'lookup_events' => $lookup_events,
			'sent'          => $sent,
			'failed'        => $failed,
		) );
		return array(
			'success' => true,
			'event'   => $canonical_event,
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
					'event_type' => $this->sanitize_event_name( (string) $event_type ),
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
			'event_type'    => isset( $webhook['event_type'] ) ? $this->sanitize_event_name( (string) $webhook['event_type'] ) : '',
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
		$event_type = isset( $data['event_type'] ) ? $this->sanitize_event_name( (string) $data['event_type'] ) : '';
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
		if ( ! $this->is_supported_event( $event_type ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid webhook event type.', 'super-mechanic' ),
			);
		}

		$event_context = $this->resolve_event_context( $event_type );
		if ( empty( $event_context ) ) {
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
				'event_type' => $event_context['lookup_event'],
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
			$this->log_webhook( 'dispatch', 'error', 'Webhook endpoint URL missing.', array( 'event_type' => $this->sanitize_event_name( (string) $event_type ) ) );
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
			error_log( sprintf( '[SM_WEBHOOK][ERROR] dispatch failed event=%s url=%s error=%s', $this->sanitize_event_name( (string) $event_type ), $url, $response->get_error_message() ) );
			$this->log_webhook( 'fail', 'error', 'Webhook endpoint request failed.', array( 'event_type' => $this->sanitize_event_name( (string) $event_type ) ) );
			return false;
		}

		$code = absint( wp_remote_retrieve_response_code( $response ) );
		if ( $code < 200 || $code >= 300 ) {
			error_log( sprintf( '[SM_WEBHOOK][ERROR] non-2xx event=%s url=%s code=%d', $this->sanitize_event_name( (string) $event_type ), $url, $code ) );
			$this->log_webhook( 'fail', 'error', 'Webhook endpoint returned non-2xx status.', array( 'event_type' => $this->sanitize_event_name( (string) $event_type ), 'http_code' => $code ) );
			return false;
		}

		$this->log_webhook( 'success', 'success', 'Webhook delivered to endpoint.', array( 'event_type' => $this->sanitize_event_name( (string) $event_type ), 'http_code' => $code ) );
		return true;
	}

	/**
	 * Check whether one event key is supported (canonical or legacy).
	 *
	 * @param string $event_name Event name.
	 * @return bool
	 */
	protected function is_supported_event( $event_name ) {
		$event_name = $this->sanitize_event_name( $event_name );
		if ( '' === $event_name ) {
			return false;
		}

		if ( in_array( $event_name, $this->supported_events, true ) ) {
			return true;
		}

		$context = $this->resolve_event_context( $event_name );
		if ( empty( $context ) ) {
			return false;
		}

		return in_array( $context['canonical_event'], $this->supported_events, true )
			|| in_array( $context['lookup_event'], $this->supported_events, true );
	}

	/**
	 * Resolve canonical + lookup event context.
	 *
	 * @param string $event_name Event name.
	 * @return array<string,mixed>
	 */
	protected function resolve_event_context( $event_name ) {
		$event_name = $this->sanitize_event_name( $event_name );
		if ( '' === $event_name ) {
			return array();
		}

		$canonical_event = $this->to_canonical_event_name( $event_name );
		$lookup_event    = $this->to_lookup_event_name( $canonical_event );
		$lookup_events   = array_values(
			array_unique(
				array_filter(
					array(
						$lookup_event,
						$this->to_lookup_event_name( $event_name ),
					)
				)
			)
		);

		return array(
			'input_event'     => $event_name,
			'canonical_event' => $canonical_event,
			'lookup_event'    => $lookup_event,
			'lookup_events'   => $lookup_events,
		);
	}

	/**
	 * Convert any supported key to canonical dotted event name.
	 *
	 * @param string $event_name Event name.
	 * @return string
	 */
	protected function to_canonical_event_name( $event_name ) {
		$event_name = $this->sanitize_event_name( $event_name );
		if ( '' === $event_name ) {
			return '';
		}

		if ( isset( $this->event_aliases[ $event_name ] ) ) {
			return $event_name;
		}

		foreach ( $this->event_aliases as $canonical => $legacy ) {
			if ( $legacy === $event_name ) {
				return $canonical;
			}
		}

		return $event_name;
	}

	/**
	 * Resolve lookup/storage event key for repository matching.
	 *
	 * @param string $event_name Event name.
	 * @return string
	 */
	protected function to_lookup_event_name( $event_name ) {
		$event_name = $this->sanitize_event_name( $event_name );
		if ( '' === $event_name ) {
			return '';
		}

		if ( isset( $this->event_aliases[ $event_name ] ) ) {
			return $this->event_aliases[ $event_name ];
		}

		return sanitize_key( $event_name );
	}

	/**
	 * Build standardized outbound event payload.
	 *
	 * @param string              $event_name    Canonical event name.
	 * @param array<string,mixed> $payload       Raw payload.
	 * @param string              $lookup_event  Legacy/lookup event key.
	 * @return array<string,mixed>
	 */
	protected function normalize_event_payload( $event_name, array $payload = array(), $lookup_event = '' ) {
		$event_name = $this->sanitize_event_name( $event_name );
		$data       = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : $payload;

		$business_id = 0;
		if ( isset( $payload['business_id'] ) ) {
			$business_id = absint( $payload['business_id'] );
		}
		if ( $business_id <= 0 && isset( $data['business_id'] ) ) {
			$business_id = absint( $data['business_id'] );
		}

		$entity_context = $this->resolve_entity_context( $event_name, $payload, $data );

		$normalized = array(
			'event'       => $event_name,
			'timestamp'   => gmdate( 'c' ),
			'business_id' => $business_id,
			'entity_type' => $entity_context['entity_type'],
			'entity_id'   => $entity_context['entity_id'],
			'data'        => $data,
		);

		$lookup_event = $this->sanitize_event_name( $lookup_event );
		if ( '' !== $lookup_event && $lookup_event !== $event_name ) {
			$normalized['legacy_event'] = $lookup_event;
		}

		return $normalized;
	}

	/**
	 * Resolve entity type/id from event + payload.
	 *
	 * @param string              $event_name Event key.
	 * @param array<string,mixed> $payload    Raw payload.
	 * @param array<string,mixed> $data       Data payload.
	 * @return array<string,mixed>
	 */
	protected function resolve_entity_context( $event_name, array $payload, array $data ) {
		$entity_type = 'event';
		$entity_id   = 0;

		if ( 0 === strpos( $event_name, 'process.' ) || false !== strpos( $event_name, 'process' ) ) {
			$entity_type = 'process';
			$entity_id   = isset( $data['process_id'] ) ? absint( $data['process_id'] ) : 0;
		} elseif ( 0 === strpos( $event_name, 'quote.' ) || false !== strpos( $event_name, 'quote' ) ) {
			$entity_type = 'quote';
			$entity_id   = isset( $data['quote_id'] ) ? absint( $data['quote_id'] ) : 0;
		} elseif ( 0 === strpos( $event_name, 'invoice.' ) || false !== strpos( $event_name, 'invoice' ) ) {
			$entity_type = 'invoice';
			$entity_id   = isset( $data['invoice_id'] ) ? absint( $data['invoice_id'] ) : 0;
		} elseif ( 0 === strpos( $event_name, 'payment.' ) || false !== strpos( $event_name, 'payment' ) ) {
			$entity_type = 'payment';
			$entity_id   = isset( $data['payment_id'] ) ? absint( $data['payment_id'] ) : 0;
		}

		if ( $entity_id <= 0 && isset( $payload[ $entity_type . '_id' ] ) ) {
			$entity_id = absint( $payload[ $entity_type . '_id' ] );
		}

		return array(
			'entity_type' => $entity_type,
			'entity_id'   => $entity_id,
		);
	}

	/**
	 * Sanitize event name while preserving dots for canonical events.
	 *
	 * @param string $event_name Raw event name.
	 * @return string
	 */
	protected function sanitize_event_name( $event_name ) {
		$event_name = strtolower( trim( (string) $event_name ) );
		$event_name = preg_replace( '/[^a-z0-9._-]/', '', $event_name );

		return is_string( $event_name ) ? $event_name : '';
	}
}
