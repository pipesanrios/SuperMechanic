<?php
/**
 * Public webhook service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Public_API;

use Super_Mechanic\Helpers\Settings_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates outbound public webhook routing by business.
 */
class Public_Webhook_Service {
	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Webhook repository.
	 *
	 * @var Public_Webhook_Repository
	 */
	protected $webhook_repository;

	/**
	 * Delivery service.
	 *
	 * @var Public_Webhook_Delivery_Service
	 */
	protected $delivery_service;

	/**
	 * Event catalog.
	 *
	 * @var Public_Webhook_Event_Catalog
	 */
	protected $event_catalog;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null               $settings_service   Settings.
	 * @param Public_Webhook_Repository|null      $webhook_repository Repository.
	 * @param Public_Webhook_Delivery_Service|null $delivery_service  Delivery service.
	 * @param Public_Webhook_Event_Catalog|null   $event_catalog      Event catalog.
	 */
	public function __construct( Settings_Service $settings_service = null, Public_Webhook_Repository $webhook_repository = null, Public_Webhook_Delivery_Service $delivery_service = null, Public_Webhook_Event_Catalog $event_catalog = null ) {
		$this->settings_service   = $settings_service ? $settings_service : new Settings_Service();
		$this->webhook_repository = $webhook_repository ? $webhook_repository : new Public_Webhook_Repository();
		$this->delivery_service   = $delivery_service ? $delivery_service : new Public_Webhook_Delivery_Service();
		$this->event_catalog      = $event_catalog ? $event_catalog : new Public_Webhook_Event_Catalog();
	}

	/**
	 * Register async runtime hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$this->delivery_service->register_hooks();
	}

	/**
	 * Queue deliveries for one mapped internal event.
	 *
	 * @param string               $internal_event Internal event key.
	 * @param array<string, mixed> $payload        Internal payload.
	 * @return void
	 */
	public function queue_from_internal_event( $internal_event, array $payload ) {
		if ( ! $this->is_webhooks_runtime_enabled() ) {
			return;
		}

		$public_event_key = $this->event_catalog->map_internal_event( $internal_event );
		if ( '' === $public_event_key ) {
			return;
		}

		$event = $this->build_public_event_payload( $public_event_key, $payload );
		if ( ! is_array( $event ) || empty( $event['business_id'] ) || empty( $event['event_key'] ) ) {
			return;
		}

		$webhooks = $this->webhook_repository->get_active_webhooks_by_business( absint( $event['business_id'] ) );
		foreach ( $webhooks as $webhook ) {
			if ( ! $this->webhook_accepts_event( $webhook, (string) $event['event_key'] ) ) {
				continue;
			}

			$this->delivery_service->queue_delivery( $webhook, $event );
		}
	}

	/**
	 * Register one webhook endpoint for a business.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $data        Registration payload.
	 * @return int|false
	 */
	public function register_webhook( $business_id, array $data ) {
		$business_id = max( 1, absint( $business_id ) );
		$url         = esc_url_raw( isset( $data['endpoint_url'] ) ? (string) $data['endpoint_url'] : '' );
		$secret      = sanitize_text_field( isset( $data['secret'] ) ? (string) $data['secret'] : '' );

		if ( '' === $url || '' === $secret ) {
			return false;
		}

		$events = $this->event_catalog->normalize_subscribed_events( isset( $data['events'] ) ? $data['events'] : array() );
		if ( empty( $events ) ) {
			$events = $this->event_catalog->get_supported_events();
		}

		$events_json = wp_json_encode( $events );
		if ( false === $events_json ) {
			return false;
		}

		return $this->webhook_repository->insert_webhook(
			array(
				'business_id'      => $business_id,
				'name'             => isset( $data['name'] ) ? (string) $data['name'] : '',
				'endpoint_url'     => $url,
				'secret_encrypted' => $this->encrypt_webhook_secret( $secret ),
				'secret_hash'      => hash_hmac( 'sha256', $secret, wp_salt( 'sm_public_webhooks' ) ),
				'events_json'      => $events_json,
				'status'           => ! empty( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'active',
			)
		);
	}

	/**
	 * Build public event payload.
	 *
	 * @param string               $event_key Public event key.
	 * @param array<string, mixed> $payload   Internal payload.
	 * @return array<string,mixed>|null
	 */
	protected function build_public_event_payload( $event_key, array $payload ) {
		if ( 'process.created' === $event_key || 'process.status_changed' === $event_key ) {
			$process_id = absint( isset( $payload['process_id'] ) ? $payload['process_id'] : 0 );
			if ( $process_id <= 0 ) {
				return null;
			}

			$process = $this->webhook_repository->get_process_snapshot( $process_id );
			if ( ! is_array( $process ) ) {
				return null;
			}

			$public_payload = array(
				'id'           => absint( $process['id'] ),
				'client_id'    => absint( $process['client_id'] ),
				'vehicle_id'   => absint( $process['vehicle_id'] ),
				'title'        => (string) $process['title'],
				'process_type' => (string) $process['process_type'],
				'status'       => (string) $process['status'],
				'priority'     => (string) $process['priority'],
				'opened_at'    => (string) $process['opened_at'],
				'due_date'     => (string) $process['due_date'],
				'completed_at' => (string) $process['completed_at'],
				'created_at'   => (string) $process['created_at'],
				'updated_at'   => (string) $process['updated_at'],
			);

			if ( 'process.status_changed' === $event_key ) {
				$public_payload['old_status'] = isset( $payload['old_status'] ) ? sanitize_key( (string) $payload['old_status'] ) : '';
				$public_payload['new_status'] = isset( $payload['new_status'] ) ? sanitize_key( (string) $payload['new_status'] ) : (string) $process['status'];
			}

			return $this->finalize_event_payload( $event_key, absint( $process['business_id'] ), $public_payload );
		}

		if ( 'appointment.created' === $event_key || 'appointment.status_changed' === $event_key ) {
			$appointment_id = absint( isset( $payload['appointment_id'] ) ? $payload['appointment_id'] : 0 );
			if ( $appointment_id <= 0 ) {
				return null;
			}

			$appointment = $this->webhook_repository->get_appointment_snapshot( $appointment_id );
			if ( ! is_array( $appointment ) ) {
				return null;
			}

			$public_payload = array(
				'id'                 => absint( $appointment['id'] ),
				'process_id'         => absint( $appointment['process_id'] ),
				'client_id'          => absint( $appointment['client_id'] ),
				'vehicle_id'         => absint( $appointment['vehicle_id'] ),
				'assigned_to'        => absint( $appointment['assigned_to'] ),
				'appointment_status' => (string) $appointment['appointment_status'],
				'appointment_date'   => (string) $appointment['appointment_date'],
				'start_at'           => (string) $appointment['start_at'],
				'created_at'         => (string) $appointment['created_at'],
				'updated_at'         => (string) $appointment['updated_at'],
			);

			if ( 'appointment.status_changed' === $event_key ) {
				$public_payload['old_status'] = isset( $payload['old_status'] ) ? sanitize_key( (string) $payload['old_status'] ) : '';
				$public_payload['new_status'] = isset( $payload['new_status'] ) ? sanitize_key( (string) $payload['new_status'] ) : (string) $appointment['appointment_status'];
			}

			return $this->finalize_event_payload( $event_key, absint( $appointment['business_id'] ), $public_payload );
		}

		return null;
	}

	/**
	 * Build final envelope with stable event id for idempotency.
	 *
	 * @param string               $event_key   Event key.
	 * @param int                  $business_id Business ID.
	 * @param array<string, mixed> $resource    Public resource payload.
	 * @return array<string,mixed>
	 */
	protected function finalize_event_payload( $event_key, $business_id, array $resource ) {
		$body = array(
			'event'       => $event_key,
			'business_id' => max( 1, absint( $business_id ) ),
			'occurred_at' => gmdate( 'c' ),
			'data'        => $resource,
		);

		$event_id_source = $event_key . '|' . $body['business_id'] . '|' . wp_json_encode( $resource );
		$event_id        = hash( 'sha256', $event_id_source );

		return array(
			'business_id' => $body['business_id'],
			'event_key'   => $event_key,
			'event_id'    => $event_id,
			'payload'     => $body,
		);
	}

	/**
	 * Check if one webhook is subscribed to an event.
	 *
	 * @param array<string,mixed> $webhook   Webhook row.
	 * @param string              $event_key Event key.
	 * @return bool
	 */
	protected function webhook_accepts_event( array $webhook, $event_key ) {
		$status = isset( $webhook['status'] ) ? sanitize_key( (string) $webhook['status'] ) : 'inactive';
		if ( 'active' !== $status ) {
			return false;
		}

		$events = $this->event_catalog->normalize_subscribed_events( isset( $webhook['events_json'] ) ? $webhook['events_json'] : array() );
		if ( empty( $events ) ) {
			return false;
		}

		return in_array( '*', $events, true ) || in_array( $event_key, $events, true );
	}

	/**
	 * Check global runtime flag for outbound webhooks.
	 *
	 * @return bool
	 */
	protected function is_webhooks_runtime_enabled() {
		$enabled = $this->settings_service->get_setting( 'public_api', 'webhooks_enabled', false );

		return ! empty( $enabled );
	}

	/**
	 * Encrypt webhook secret with plugin salt.
	 *
	 * @param string $secret Secret.
	 * @return string
	 */
	protected function encrypt_webhook_secret( $secret ) {
		$secret = (string) $secret;
		if ( '' === $secret || ! function_exists( 'openssl_encrypt' ) ) {
			return $secret;
		}

		$key     = hash( 'sha256', wp_salt( 'sm_public_webhooks' ), true );
		$iv      = '';
		if ( function_exists( 'random_bytes' ) ) {
			try {
				$iv = random_bytes( 16 );
			} catch ( \Exception $exception ) {
				$iv = '';
			}
		}
		if ( '' === $iv ) {
			return '';
		}
		$cipher  = openssl_encrypt( $secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return '';
		}

		return 'smenc:' . base64_encode( base64_encode( $iv ) . ':' . base64_encode( $cipher ) );
	}
}
