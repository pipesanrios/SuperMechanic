<?php
/**
 * Public webhook delivery service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Public_API;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Executes signed outbound webhook deliveries.
 */
class Public_Webhook_Delivery_Service {
	/**
	 * Cron hook for delivery processing.
	 */
	const PROCESS_HOOK = 'sm_public_webhook_process_delivery';

	/**
	 * Max retry attempts after initial send.
	 */
	const MAX_RETRIES = 3;

	/**
	 * Retry schedule in seconds.
	 *
	 * @var array<int,int>
	 */
	protected $retry_backoff = array( 60, 300, 900 );

	/**
	 * Repository.
	 *
	 * @var Public_Webhook_Delivery_Repository
	 */
	protected $delivery_repository;

	/**
	 * Constructor.
	 *
	 * @param Public_Webhook_Delivery_Repository|null $delivery_repository Repository.
	 */
	public function __construct( Public_Webhook_Delivery_Repository $delivery_repository = null ) {
		$this->delivery_repository = $delivery_repository ? $delivery_repository : new Public_Webhook_Delivery_Repository();
	}

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( self::PROCESS_HOOK, array( $this, 'process_delivery_job' ), 10, 1 );
	}

	/**
	 * Queue one outbound delivery and schedule async processing.
	 *
	 * @param array<string,mixed> $webhook Webhook row.
	 * @param array<string,mixed> $event   Event payload.
	 * @return int|false
	 */
	public function queue_delivery( array $webhook, array $event ) {
		$payload_json = wp_json_encode( $event['payload'] );
		if ( false === $payload_json ) {
			return false;
		}

		$delivery_id = $this->delivery_repository->queue_delivery(
			array(
				'business_id'  => absint( $event['business_id'] ),
				'webhook_id'   => absint( $webhook['id'] ),
				'event_key'    => sanitize_text_field( (string) $event['event_key'] ),
				'event_id'     => sanitize_text_field( (string) $event['event_id'] ),
				'payload_json' => $payload_json,
			)
		);

		if ( ! $delivery_id ) {
			return false;
		}

		wp_schedule_single_event(
			time(),
			self::PROCESS_HOOK,
			array( absint( $delivery_id ) )
		);

		return $delivery_id;
	}

	/**
	 * Process one queued delivery.
	 *
	 * @param int $delivery_id Delivery ID.
	 * @return void
	 */
	public function process_delivery_job( $delivery_id ) {
		$delivery_id = absint( $delivery_id );
		if ( $delivery_id <= 0 ) {
			return;
		}

		if ( ! $this->delivery_repository->lock_delivery_for_processing( $delivery_id ) ) {
			return;
		}

		$delivery = $this->delivery_repository->get_delivery_with_webhook( $delivery_id );
		if ( ! is_array( $delivery ) ) {
			return;
		}

		if ( 'active' !== sanitize_key( isset( $delivery['webhook_status'] ) ? (string) $delivery['webhook_status'] : '' ) ) {
			$this->delivery_repository->mark_failed( $delivery_id, 0, 'webhook_inactive' );
			return;
		}

		$attempts = absint( isset( $delivery['attempts'] ) ? $delivery['attempts'] : 0 ) + 1;
		$this->delivery_repository->record_attempt( $delivery_id, $attempts );

		$payload_json = isset( $delivery['payload_json'] ) ? (string) $delivery['payload_json'] : '';
		$secret       = $this->resolve_webhook_secret( $delivery );
		if ( '' === $secret ) {
			$this->delivery_repository->mark_failed( $delivery_id, 0, 'invalid_webhook_secret' );
			return;
		}
		$timestamp    = (string) time();
		$signature    = $this->build_signature(
			$secret,
			$timestamp,
			(string) $delivery_id,
			$payload_json
		);

		$response = wp_remote_post(
			esc_url_raw( (string) $delivery['endpoint_url'] ),
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type'      => 'application/json',
					'X-SM-Signature'    => $signature,
					'X-SM-Timestamp'    => $timestamp,
					'X-SM-Delivery-Id'  => (string) $delivery_id,
					'X-SM-Event'        => sanitize_text_field( (string) $delivery['event_key'] ),
				),
				'body'        => $payload_json,
			)
		);

		$result = $this->normalize_delivery_result( $response );

		if ( ! empty( $result['success'] ) ) {
			$this->delivery_repository->mark_delivered( $delivery_id, absint( $result['http_code'] ) );
			$this->delivery_repository->touch_webhook_usage( absint( $delivery['webhook_id'] ) );
			return;
		}

		$retry_count = max( 0, $attempts - 1 );
		$should_retry = $this->should_retry_result( $result, $retry_count );

		if ( $should_retry ) {
			$next_retry_at = gmdate( 'Y-m-d H:i:s', time() + $this->retry_backoff[ $retry_count ] );
			$this->delivery_repository->mark_retrying(
				$delivery_id,
				absint( $result['http_code'] ),
				(string) $result['error'],
				$next_retry_at
			);

			wp_schedule_single_event(
				time() + $this->retry_backoff[ $retry_count ],
				self::PROCESS_HOOK,
				array( $delivery_id )
			);
			return;
		}

		$this->delivery_repository->mark_failed( $delivery_id, absint( $result['http_code'] ), (string) $result['error'] );
	}

	/**
	 * Normalize HTTP result.
	 *
	 * @param array<string,mixed>|WP_Error $response Response.
	 * @return array<string,mixed>
	 */
	protected function normalize_delivery_result( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'success'   => false,
				'http_code' => 0,
				'error'     => $response->get_error_code() . ':' . $response->get_error_message(),
				'type'      => 'network',
			);
		}

		$http_code = absint( wp_remote_retrieve_response_code( $response ) );
		if ( $http_code >= 200 && $http_code < 300 ) {
			return array(
				'success'   => true,
				'http_code' => $http_code,
				'error'     => '',
				'type'      => 'ok',
			);
		}

		$type = ( 429 === $http_code || $http_code >= 500 ) ? 'retryable_http' : 'functional_http';
		return array(
			'success'   => false,
			'http_code' => $http_code,
			'error'     => 'http_' . $http_code,
			'type'      => $type,
		);
	}

	/**
	 * Check if failed response should be retried.
	 *
	 * @param array<string,mixed> $result      Result.
	 * @param int                 $retry_count Retry count.
	 * @return bool
	 */
	protected function should_retry_result( array $result, $retry_count ) {
		if ( $retry_count >= self::MAX_RETRIES ) {
			return false;
		}

		if ( ! isset( $this->retry_backoff[ $retry_count ] ) ) {
			return false;
		}

		$type = isset( $result['type'] ) ? (string) $result['type'] : '';

		return in_array( $type, array( 'network', 'retryable_http' ), true );
	}

	/**
	 * Build HMAC signature header.
	 *
	 * @param string $secret      Secret.
	 * @param string $timestamp   Timestamp.
	 * @param string $delivery_id Delivery ID.
	 * @param string $raw_body    JSON payload.
	 * @return string
	 */
	protected function build_signature( $secret, $timestamp, $delivery_id, $raw_body ) {
		$payload = $timestamp . '.' . $delivery_id . '.' . $raw_body;

		return 'v1=' . hash_hmac( 'sha256', $payload, (string) $secret );
	}

	/**
	 * Resolve webhook secret from encrypted storage.
	 *
	 * @param array<string,mixed> $webhook Webhook row.
	 * @return string
	 */
	protected function resolve_webhook_secret( array $webhook ) {
		$encrypted = isset( $webhook['secret_encrypted'] ) ? (string) $webhook['secret_encrypted'] : '';
		if ( '' === $encrypted ) {
			return '';
		}

		if ( 0 !== strpos( $encrypted, 'smenc:' ) ) {
			return $encrypted;
		}

		$encoded = substr( $encrypted, 6 );
		$decoded = base64_decode( $encoded, true );
		if ( false === $decoded || '' === $decoded ) {
			return '';
		}

		$parts = explode( ':', $decoded, 2 );
		if ( 2 !== count( $parts ) ) {
			return '';
		}

		$iv        = base64_decode( $parts[0], true );
		$cipher    = base64_decode( $parts[1], true );
		$key       = hash( 'sha256', wp_salt( 'sm_public_webhooks' ), true );
		$plaintext = false;

		if ( false !== $iv && false !== $cipher && function_exists( 'openssl_decrypt' ) ) {
			$plaintext = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		}

		return false === $plaintext ? '' : (string) $plaintext;
	}
}
