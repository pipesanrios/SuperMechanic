<?php
/**
 * Connector service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Connectors;

use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Webhooks\Webhook_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles connector CRUD and outbound dispatch.
 */
class Connector_Service {
	/**
	 * Supported connector types.
	 *
	 * @var array<int,string>
	 */
	protected $supported_types = array(
		'webhook',
		'google_sheets',
		'email_trigger',
	);

	/**
	 * Supported canonical event names.
	 *
	 * @var array<int,string>
	 */
	protected $supported_events = array(
		'process.created',
		'process.updated',
		'quote.approved',
		'invoice.paid',
		'payment.created',
	);

	/**
	 * Repository dependency.
	 *
	 * @var Connector_Repository
	 */
	protected $repository;

	/**
	 * Business context dependency.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Webhook service dependency.
	 *
	 * @var Webhook_Service
	 */
	protected $webhook_service;

	/**
	 * Constructor.
	 *
	 * @param Connector_Repository|null    $repository               Repository dependency.
	 * @param Business_Context_Service|null $business_context_service Business context dependency.
	 * @param Webhook_Service|null         $webhook_service          Webhook service dependency.
	 */
	public function __construct( Connector_Repository $repository = null, Business_Context_Service $business_context_service = null, Webhook_Service $webhook_service = null ) {
		$this->repository               = $repository ? $repository : new Connector_Repository();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->webhook_service          = $webhook_service ? $webhook_service : new Webhook_Service();
	}

	/**
	 * Register event hooks for 55C formalized events.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'sm_event_process_created', array( $this, 'handle_process_created' ), 20, 1 );
		add_action( 'sm_event_process_updated', array( $this, 'handle_process_updated' ), 20, 1 );
		add_action( 'sm_event_quote_approved', array( $this, 'handle_quote_approved' ), 20, 1 );
		add_action( 'sm_event_invoice_paid', array( $this, 'handle_invoice_paid' ), 20, 1 );
		add_action( 'sm_event_payment_registered', array( $this, 'handle_payment_registered' ), 20, 1 );
	}

	/**
	 * Get supported connector types.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_connector_types() {
		return $this->supported_types;
	}

	/**
	 * Get supported event names.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_events() {
		return $this->supported_events;
	}

	/**
	 * Get all connectors.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_connectors() {
		return $this->repository->get_all();
	}

	/**
	 * Get connector by id.
	 *
	 * @param int $connector_id Connector id.
	 * @return array<string,mixed>|null
	 */
	public function get_connector( $connector_id ) {
		return $this->repository->get_by_id( $connector_id );
	}

	/**
	 * Create one connector.
	 *
	 * @param array<string,mixed> $data Raw input data.
	 * @return array<string,mixed>
	 */
	public function create_connector( $data ) {
		$normalized = $this->normalize_connector_data( $data );
		if ( is_wp_error( $normalized ) ) {
			return array(
				'success' => false,
				'message' => $normalized->get_error_message(),
			);
		}

		$inserted_id = $this->repository->create( $normalized );
		if ( $inserted_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create connector.', 'super-mechanic' ),
			);
		}

		return array(
			'success'      => true,
			'message'      => __( 'Connector created successfully.', 'super-mechanic' ),
			'connector_id' => $inserted_id,
		);
	}

	/**
	 * Update one connector.
	 *
	 * @param int                $connector_id Connector id.
	 * @param array<string,mixed> $data        Raw input data.
	 * @return array<string,mixed>
	 */
	public function update_connector( $connector_id, $data ) {
		$connector_id = absint( $connector_id );
		if ( $connector_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid connector ID.', 'super-mechanic' ),
			);
		}

		$existing = $this->repository->get_by_id( $connector_id );
		if ( ! is_array( $existing ) ) {
			return array(
				'success' => false,
				'message' => __( 'Connector not found.', 'super-mechanic' ),
			);
		}

		$normalized = $this->normalize_connector_data( $data, $existing );
		if ( is_wp_error( $normalized ) ) {
			return array(
				'success' => false,
				'message' => $normalized->get_error_message(),
			);
		}

		$updated = $this->repository->update( $connector_id, $normalized );
		if ( ! $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Could not update connector.', 'super-mechanic' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Connector updated successfully.', 'super-mechanic' ),
		);
	}

	/**
	 * Delete one connector.
	 *
	 * @param int $connector_id Connector id.
	 * @return array<string,mixed>
	 */
	public function delete_connector( $connector_id ) {
		$deleted = $this->repository->delete( $connector_id );
		if ( ! $deleted ) {
			return array(
				'success' => false,
				'message' => __( 'Could not delete connector.', 'super-mechanic' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Connector deleted successfully.', 'super-mechanic' ),
		);
	}

	/**
	 * Set connector status.
	 *
	 * @param int    $connector_id Connector id.
	 * @param string $status       Status.
	 * @return array<string,mixed>
	 */
	public function set_connector_status( $connector_id, $status ) {
		$status = $this->normalize_status( $status );
		if ( '' === $status ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid connector status.', 'super-mechanic' ),
			);
		}

		$updated = $this->repository->set_status( $connector_id, $status );
		if ( ! $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Could not update connector status.', 'super-mechanic' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Connector status updated successfully.', 'super-mechanic' ),
		);
	}

	/**
	 * Dispatch one payload to one connector.
	 *
	 * @param int                 $connector_id Connector id.
	 * @param array<string,mixed> $payload      Payload.
	 * @return array<string,mixed>
	 */
	public function dispatch_to_connector( $connector_id, $payload ) {
		$connector = $this->repository->get_by_id( $connector_id );
		if ( ! is_array( $connector ) ) {
			return array(
				'success' => false,
				'message' => __( 'Connector not found.', 'super-mechanic' ),
			);
		}

		$status = isset( $connector['status'] ) ? $this->normalize_status( $connector['status'] ) : '';
		if ( 'active' !== $status ) {
			return array(
				'success' => false,
				'message' => __( 'Connector is inactive.', 'super-mechanic' ),
			);
		}

		$body = $this->build_connector_payload( $connector, is_array( $payload ) ? $payload : array() );
		$url  = isset( $connector['endpoint_url'] ) ? esc_url_raw( (string) $connector['endpoint_url'] ) : '';

		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return array(
				'success' => false,
				'message' => __( 'Connector endpoint URL is invalid.', 'super-mechanic' ),
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'method'      => 'POST',
				'timeout'     => 5,
				'headers'     => array(
					'Content-Type' => 'application/json; charset=utf-8',
				),
				'body'        => wp_json_encode( $body ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = absint( wp_remote_retrieve_response_code( $response ) );
		if ( $code < 200 || $code >= 300 ) {
			return array(
				'success' => false,
				'message' => sprintf( 'HTTP %d', $code ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Connector dispatch sent successfully.', 'super-mechanic' ),
			'http'    => $code,
		);
	}

	/**
	 * Dispatch event payload to all active connectors subscribed to event.
	 *
	 * @param string              $event_name Canonical event name.
	 * @param array<string,mixed> $payload    Event payload.
	 * @return array<string,mixed>
	 */
	public function dispatch_event_to_connectors( $event_name, $payload ) {
		$event_name = $this->normalize_event_name( $event_name );
		if ( '' === $event_name || ! in_array( $event_name, $this->supported_events, true ) ) {
			return array(
				'success' => false,
				'sent'    => 0,
				'failed'  => 0,
				'message' => __( 'Unsupported connector event.', 'super-mechanic' ),
			);
		}

		$payload    = is_array( $payload ) ? $payload : array();
		$connectors = $this->repository->get_by_event( $event_name );

		$sent   = 0;
		$failed = 0;
		foreach ( $connectors as $connector ) {
			$connector_id = isset( $connector['id'] ) ? absint( $connector['id'] ) : 0;
			if ( $connector_id <= 0 ) {
				continue;
			}

			$result = $this->dispatch_to_connector( $connector_id, $payload );
			if ( ! empty( $result['success'] ) ) {
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
	 * Test dispatch for one connector.
	 *
	 * @param int $connector_id Connector id.
	 * @return array<string,mixed>
	 */
	public function test_dispatch_connector( $connector_id ) {
		$connector = $this->repository->get_by_id( $connector_id );
		if ( ! is_array( $connector ) ) {
			return array(
				'success' => false,
				'message' => __( 'Connector not found.', 'super-mechanic' ),
			);
		}

		$event_name = isset( $connector['event_name'] ) ? $this->normalize_event_name( $connector['event_name'] ) : '';
		if ( '' === $event_name ) {
			$event_name = 'process.updated';
		}

		$payload = array(
			'event'       => $event_name,
			'business_id' => $this->resolve_business_id(),
			'entity_type' => 'test',
			'entity_id'   => 0,
			'data'        => array(
				'test'         => true,
				'connector_id' => absint( $connector_id ),
			),
		);

		return $this->dispatch_to_connector( $connector_id, $payload );
	}

	/**
	 * Handle process.created internal event.
	 *
	 * @param array<string,mixed> $payload Event payload.
	 * @return void
	 */
	public function handle_process_created( $payload ) {
		$this->dispatch_event_to_connectors( 'process.created', is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Handle process.updated internal event.
	 *
	 * @param array<string,mixed> $payload Event payload.
	 * @return void
	 */
	public function handle_process_updated( $payload ) {
		$this->dispatch_event_to_connectors( 'process.updated', is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Handle quote.approved internal event.
	 *
	 * @param array<string,mixed> $payload Event payload.
	 * @return void
	 */
	public function handle_quote_approved( $payload ) {
		$this->dispatch_event_to_connectors( 'quote.approved', is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Handle invoice.paid internal event.
	 *
	 * @param array<string,mixed> $payload Event payload.
	 * @return void
	 */
	public function handle_invoice_paid( $payload ) {
		$this->dispatch_event_to_connectors( 'invoice.paid', is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Handle payment.created internal event.
	 *
	 * @param array<string,mixed> $payload Event payload.
	 * @return void
	 */
	public function handle_payment_registered( $payload ) {
		$this->dispatch_event_to_connectors( 'payment.created', is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Normalize connector input.
	 *
	 * @param array<string,mixed>      $data     Raw data.
	 * @param array<string,mixed>|null $existing Existing row for update.
	 * @return array<string,mixed>|\WP_Error
	 */
	protected function normalize_connector_data( $data, $existing = null ) {
		$data = is_array( $data ) ? $data : array();

		$name           = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$connector_type = isset( $data['connector_type'] ) ? sanitize_key( (string) $data['connector_type'] ) : '';
		$endpoint_url   = isset( $data['endpoint_url'] ) ? esc_url_raw( (string) $data['endpoint_url'] ) : '';
		$status         = isset( $data['status'] ) ? $this->normalize_status( $data['status'] ) : '';
		$event_name     = isset( $data['event_name'] ) ? $this->normalize_event_name( $data['event_name'] ) : '';
		$config_json    = isset( $data['config_json'] ) ? $this->normalize_config_json( $data['config_json'] ) : '{}';

		if ( '' === $status && is_array( $existing ) && isset( $existing['status'] ) ) {
			$status = $this->normalize_status( $existing['status'] );
		}
		if ( '' === $status ) {
			$status = 'active';
		}

		if ( '' === $name ) {
			return new \WP_Error( 'sm_connector_name_required', __( 'Connector name is required.', 'super-mechanic' ) );
		}

		if ( ! in_array( $connector_type, $this->supported_types, true ) ) {
			return new \WP_Error( 'sm_connector_type_invalid', __( 'Connector type is invalid.', 'super-mechanic' ) );
		}

		if ( '' === $endpoint_url || ! wp_http_validate_url( $endpoint_url ) ) {
			return new \WP_Error( 'sm_connector_endpoint_invalid', __( 'A valid connector endpoint URL is required.', 'super-mechanic' ) );
		}

		if ( ! in_array( $event_name, $this->supported_events, true ) ) {
			return new \WP_Error( 'sm_connector_event_invalid', __( 'Connector event name is invalid.', 'super-mechanic' ) );
		}

		if ( '' === $status ) {
			return new \WP_Error( 'sm_connector_status_invalid', __( 'Connector status is invalid.', 'super-mechanic' ) );
		}

		return array(
			'name'           => $name,
			'connector_type' => $connector_type,
			'endpoint_url'   => $endpoint_url,
			'status'         => $status,
			'event_name'     => $event_name,
			'config_json'    => $config_json,
		);
	}

	/**
	 * Build connector-specific outbound payload.
	 *
	 * @param array<string,mixed> $connector Connector row.
	 * @param array<string,mixed> $payload   Raw payload.
	 * @return array<string,mixed>
	 */
	protected function build_connector_payload( array $connector, array $payload ) {
		$event_name = isset( $connector['event_name'] ) ? $this->normalize_event_name( $connector['event_name'] ) : '';
		$event_name = '' !== $event_name ? $event_name : ( isset( $payload['event'] ) ? $this->normalize_event_name( $payload['event'] ) : '' );
		$event_name = '' !== $event_name ? $event_name : 'process.updated';

		$normalized = $this->webhook_service->build_standard_event_payload( $event_name, $payload );
		$type       = isset( $connector['connector_type'] ) ? sanitize_key( (string) $connector['connector_type'] ) : 'webhook';

		if ( 'google_sheets' === $type ) {
			return array(
				'event'       => isset( $normalized['event'] ) ? $normalized['event'] : $event_name,
				'timestamp'   => isset( $normalized['timestamp'] ) ? $normalized['timestamp'] : gmdate( 'c' ),
				'business_id' => isset( $normalized['business_id'] ) ? absint( $normalized['business_id'] ) : 0,
				'entity_type' => isset( $normalized['entity_type'] ) ? (string) $normalized['entity_type'] : '',
				'entity_id'   => isset( $normalized['entity_id'] ) ? absint( $normalized['entity_id'] ) : 0,
				'row'         => isset( $normalized['data'] ) && is_array( $normalized['data'] ) ? $normalized['data'] : array(),
			);
		}

		if ( 'email_trigger' === $type ) {
			$config = $this->decode_config_json( isset( $connector['config_json'] ) ? (string) $connector['config_json'] : '{}' );

			return array(
				'event'       => isset( $normalized['event'] ) ? $normalized['event'] : $event_name,
				'timestamp'   => isset( $normalized['timestamp'] ) ? $normalized['timestamp'] : gmdate( 'c' ),
				'business_id' => isset( $normalized['business_id'] ) ? absint( $normalized['business_id'] ) : 0,
				'entity_type' => isset( $normalized['entity_type'] ) ? (string) $normalized['entity_type'] : '',
				'entity_id'   => isset( $normalized['entity_id'] ) ? absint( $normalized['entity_id'] ) : 0,
				'trigger'     => array(
					'recipient' => isset( $config['recipient'] ) ? sanitize_email( (string) $config['recipient'] ) : '',
					'subject'   => isset( $config['subject'] ) ? sanitize_text_field( (string) $config['subject'] ) : '',
				),
				'data'        => isset( $normalized['data'] ) && is_array( $normalized['data'] ) ? $normalized['data'] : array(),
			);
		}

		return $normalized;
	}

	/**
	 * Normalize event name.
	 *
	 * @param string $event_name Event name.
	 * @return string
	 */
	protected function normalize_event_name( $event_name ) {
		$event_name = strtolower( trim( (string) $event_name ) );
		$event_name = preg_replace( '/[^a-z0-9._-]/', '', $event_name );

		return is_string( $event_name ) ? $event_name : '';
	}

	/**
	 * Normalize status value.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	protected function normalize_status( $status ) {
		$status = sanitize_key( (string) $status );
		if ( in_array( $status, array( 'active', 'inactive' ), true ) ) {
			return $status;
		}

		return '';
	}

	/**
	 * Normalize config JSON.
	 *
	 * @param mixed $config_json Raw config json.
	 * @return string
	 */
	protected function normalize_config_json( $config_json ) {
		if ( is_array( $config_json ) ) {
			$encoded = wp_json_encode( $config_json );
			return is_string( $encoded ) ? $encoded : '{}';
		}

		$config_json = trim( (string) $config_json );
		if ( '' === $config_json ) {
			return '{}';
		}

		$decoded = json_decode( $config_json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return '{}';
		}

		$encoded = wp_json_encode( $decoded );
		return is_string( $encoded ) ? $encoded : '{}';
	}

	/**
	 * Decode config json.
	 *
	 * @param string $config_json Config JSON.
	 * @return array<string,mixed>
	 */
	protected function decode_config_json( $config_json ) {
		$decoded = json_decode( (string) $config_json, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Resolve current business id.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		return absint( $this->business_context_service->resolve_business_id_for_user( get_current_user_id() ) );
	}
}

