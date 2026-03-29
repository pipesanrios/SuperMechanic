<?php
/**
 * Public API service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Public_API;

use Super_Mechanic\Appointments\Appointment_Service;
use Super_Mechanic\Businesses\Business_Service;
use Super_Mechanic\Processes\Process_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates read-only public payloads with explicit field selection.
 */
class Public_API_Service {
	/**
	 * Business service.
	 *
	 * @var Business_Service
	 */
	protected $business_service;

	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * Appointment service.
	 *
	 * @var Appointment_Service
	 */
	protected $appointment_service;

	/**
	 * Idempotency service.
	 *
	 * @var Public_API_Idempotency_Service
	 */
	protected $idempotency_service;

	/**
	 * Constructor.
	 *
	 * @param Business_Service|null    $business_service    Business service.
	 * @param Process_Service|null     $process_service     Process service.
	 * @param Appointment_Service|null $appointment_service Appointment service.
	 */
	public function __construct( Business_Service $business_service = null, Process_Service $process_service = null, Appointment_Service $appointment_service = null, Public_API_Idempotency_Service $idempotency_service = null ) {
		$this->business_service    = $business_service ? $business_service : new Business_Service();
		$this->process_service     = $process_service ? $process_service : new Process_Service();
		$this->appointment_service = $appointment_service ? $appointment_service : new Appointment_Service();
		$this->idempotency_service = $idempotency_service ? $idempotency_service : new Public_API_Idempotency_Service();
	}

	/**
	 * Get a minimal business summary.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_business_summary( $business_id ) {
		$business_id = absint( $business_id );
		$business    = $this->business_service->get_business( $business_id );

		if ( ! is_array( $business ) ) {
			return new WP_Error( 'sm_public_api_business_not_found', __( 'No se encontró el negocio solicitado.', 'super-mechanic' ), array( 'status' => 404 ) );
		}

		return array(
			'id'       => absint( $business['id'] ),
			'slug'     => isset( $business['slug'] ) ? (string) $business['slug'] : '',
			'name'     => isset( $business['name'] ) ? (string) $business['name'] : '',
			'status'   => isset( $business['status'] ) ? (string) $business['status'] : '',
			'timezone' => isset( $business['timezone'] ) ? (string) $business['timezone'] : '',
			'currency' => isset( $business['currency'] ) ? (string) $business['currency'] : '',
		);
	}

	/**
	 * List public processes with explicit safe payload.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $args        Query args.
	 * @return array<string,mixed>
	 */
	public function list_processes( $business_id, array $args = array() ) {
		$query = $this->normalize_process_query_args( $business_id, $args );
		$rows  = $this->process_service->get_processes( $query );
		$total = $this->process_service->count_processes( $query );

		return $this->build_collection_response(
			array_map( array( $this, 'map_public_process_payload' ), $rows ),
			$query['page'],
			$query['per_page'],
			$total
		);
	}

	/**
	 * List public appointments with explicit safe payload.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $args        Query args.
	 * @return array<string,mixed>
	 */
	public function list_appointments( $business_id, array $args = array() ) {
		$query = $this->normalize_appointment_query_args( $business_id, $args );
		$rows  = $this->appointment_service->get_appointments( $query );
		$total = $this->appointment_service->count_appointments( $query );

		return $this->build_collection_response(
			array_map( array( $this, 'map_public_appointment_payload' ), $rows ),
			$query['page'],
			$query['per_page'],
			$total
		);
	}

	/**
	 * Cancel one appointment in a public and tenant-safe way.
	 *
	 * @param int                 $business_id Business ID.
	 * @param int                 $appointment_id Appointment ID.
	 * @param array<string,mixed> $args Optional payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function cancel_public_appointment( $business_id, $appointment_id, array $args = array() ) {
		$business_id      = max( 1, absint( $business_id ) );
		$appointment_id   = absint( $appointment_id );
		$reason           = isset( $args['reason'] ) ? $this->normalize_reason( $args['reason'] ) : '';
		$idempotency_key  = isset( $args['idempotency_key'] ) ? $this->normalize_idempotency_key( $args['idempotency_key'] ) : '';
		$fingerprint      = '';

		if ( '' !== $idempotency_key ) {
			$fingerprint = $this->idempotency_service->build_fingerprint( $business_id, $appointment_id, 'cancel', $idempotency_key );
			$cached      = $this->idempotency_service->get_cached_response( $fingerprint );

			if ( is_array( $cached ) ) {
				$cached['idempotent_replay'] = true;
				return $cached;
			}
		}

		$result = $this->appointment_service->cancel_appointment_for_business( $appointment_id, $business_id, $reason );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$appointment = isset( $result['appointment'] ) && is_array( $result['appointment'] ) ? $result['appointment'] : array();
		$response    = array(
			'item'               => $this->map_public_cancel_appointment_payload( $appointment ),
			'already_cancelled'  => ! empty( $result['already_cancelled'] ),
			'idempotent_replay'  => false,
		);

		if ( '' !== $fingerprint ) {
			$this->idempotency_service->remember_response( $fingerprint, $response );
		}

		return $response;
	}

	/**
	 * Confirm one appointment in a public and tenant-safe way.
	 *
	 * @param int                 $business_id    Business ID.
	 * @param int                 $appointment_id Appointment ID.
	 * @param array<string,mixed> $args           Optional payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function confirm_public_appointment( $business_id, $appointment_id, array $args = array() ) {
		$business_id      = max( 1, absint( $business_id ) );
		$appointment_id   = absint( $appointment_id );
		$reason           = isset( $args['reason'] ) ? $this->normalize_reason( $args['reason'] ) : '';
		$idempotency_key  = isset( $args['idempotency_key'] ) ? $this->normalize_idempotency_key( $args['idempotency_key'] ) : '';
		$fingerprint      = '';

		if ( '' !== $idempotency_key ) {
			$fingerprint = $this->idempotency_service->build_fingerprint( $business_id, $appointment_id, 'confirm', $idempotency_key );
			$cached      = $this->idempotency_service->get_cached_response( $fingerprint );

			if ( is_array( $cached ) ) {
				$cached['idempotent_replay'] = true;
				return $cached;
			}
		}

		$result = $this->appointment_service->confirm_appointment_for_business( $appointment_id, $business_id, $reason );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$appointment = isset( $result['appointment'] ) && is_array( $result['appointment'] ) ? $result['appointment'] : array();
		$response    = array(
			'item'               => $this->map_public_confirm_appointment_payload( $appointment ),
			'already_confirmed'  => ! empty( $result['already_confirmed'] ),
			'idempotent_replay'  => false,
		);

		if ( '' !== $fingerprint ) {
			$this->idempotency_service->remember_response( $fingerprint, $response );
		}

		return $response;
	}

	/**
	 * Normalize process query args.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $args        Raw args.
	 * @return array<string,mixed>
	 */
	protected function normalize_process_query_args( $business_id, array $args ) {
		$allowed_status = array( 'draft', 'pending', 'in_progress', 'waiting_approval', 'waiting_parts', 'completed', 'delivered', 'cancelled' );
		$allowed_type   = array( 'maintenance', 'pre_delivery', 'paperwork' );
		$allowed_orderby = array( 'id', 'title', 'process_type', 'status', 'priority', 'opened_at', 'due_date', 'completed_at', 'created_at', 'updated_at' );

		return array(
			'business_id'   => max( 1, absint( $business_id ) ),
			'per_page'      => $this->normalize_per_page( isset( $args['per_page'] ) ? $args['per_page'] : 20 ),
			'page'          => $this->normalize_page( isset( $args['page'] ) ? $args['page'] : 1 ),
			'search'        => $this->normalize_search( isset( $args['search'] ) ? $args['search'] : '' ),
			'status'        => $this->normalize_key_filter( isset( $args['status'] ) ? $args['status'] : '', $allowed_status ),
			'process_type'  => $this->normalize_key_filter( isset( $args['type'] ) ? $args['type'] : '', $allowed_type ),
			'date_from'     => $this->normalize_date( isset( $args['date_from'] ) ? $args['date_from'] : '' ),
			'date_to'       => $this->normalize_date( isset( $args['date_to'] ) ? $args['date_to'] : '' ),
			'orderby'       => $this->normalize_orderby( isset( $args['orderby'] ) ? $args['orderby'] : 'updated_at', $allowed_orderby, 'updated_at' ),
			'order'         => $this->normalize_order( isset( $args['order'] ) ? $args['order'] : 'DESC' ),
		);
	}

	/**
	 * Normalize appointment query args.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $args        Raw args.
	 * @return array<string,mixed>
	 */
	protected function normalize_appointment_query_args( $business_id, array $args ) {
		$allowed_status  = array( 'scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled' );
		$allowed_orderby = array( 'id', 'appointment_date', 'start_at', 'appointment_status', 'assigned_to', 'created_at' );

		return array(
			'business_id'        => max( 1, absint( $business_id ) ),
			'per_page'           => $this->normalize_per_page( isset( $args['per_page'] ) ? $args['per_page'] : 20 ),
			'page'               => $this->normalize_page( isset( $args['page'] ) ? $args['page'] : 1 ),
			'search'             => $this->normalize_search( isset( $args['search'] ) ? $args['search'] : '' ),
			'appointment_status' => $this->normalize_key_filter( isset( $args['status'] ) ? $args['status'] : '', $allowed_status ),
			'assigned_to'        => isset( $args['assigned_to'] ) ? absint( $args['assigned_to'] ) : 0,
			'date_from'          => $this->normalize_date( isset( $args['date_from'] ) ? $args['date_from'] : '' ),
			'date_to'            => $this->normalize_date( isset( $args['date_to'] ) ? $args['date_to'] : '' ),
			'orderby'            => $this->normalize_orderby( isset( $args['orderby'] ) ? $args['orderby'] : 'start_at', $allowed_orderby, 'start_at' ),
			'order'              => $this->normalize_order( isset( $args['order'] ) ? $args['order'] : 'DESC' ),
		);
	}

	/**
	 * Map process row to public payload.
	 *
	 * @param array<string,mixed> $row Process row.
	 * @return array<string,mixed>
	 */
	protected function map_public_process_payload( array $row ) {
		return array(
			'id'           => absint( $row['id'] ),
			'client_id'    => absint( $row['client_id'] ),
			'vehicle_id'   => absint( $row['vehicle_id'] ),
			'title'        => isset( $row['title'] ) ? (string) $row['title'] : '',
			'process_type' => isset( $row['process_type'] ) ? (string) $row['process_type'] : '',
			'status'       => isset( $row['status'] ) ? (string) $row['status'] : '',
			'priority'     => isset( $row['priority'] ) ? (string) $row['priority'] : '',
			'opened_at'    => isset( $row['opened_at'] ) ? (string) $row['opened_at'] : '',
			'due_date'     => isset( $row['due_date'] ) ? (string) $row['due_date'] : '',
			'completed_at' => isset( $row['completed_at'] ) ? (string) $row['completed_at'] : '',
			'created_at'   => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
			'updated_at'   => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
		);
	}

	/**
	 * Map appointment row to public payload.
	 *
	 * @param array<string,mixed> $row Appointment row.
	 * @return array<string,mixed>
	 */
	protected function map_public_appointment_payload( array $row ) {
		return array(
			'id'                 => absint( $row['id'] ),
			'process_id'         => absint( $row['process_id'] ),
			'client_id'          => absint( $row['client_id'] ),
			'vehicle_id'         => absint( $row['vehicle_id'] ),
			'assigned_to'        => absint( $row['assigned_to'] ),
			'appointment_status' => isset( $row['appointment_status'] ) ? (string) $row['appointment_status'] : '',
			'appointment_date'   => isset( $row['appointment_date'] ) ? (string) $row['appointment_date'] : '',
			'start_at'           => isset( $row['start_at'] ) ? (string) $row['start_at'] : '',
			'created_at'         => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
			'updated_at'         => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
		);
	}

	/**
	 * Map appointment row to cancel response payload.
	 *
	 * @param array<string,mixed> $row Appointment row.
	 * @return array<string,mixed>
	 */
	protected function map_public_cancel_appointment_payload( array $row ) {
		return array(
			'id'                 => absint( isset( $row['id'] ) ? $row['id'] : 0 ),
			'appointment_status' => isset( $row['appointment_status'] ) ? (string) $row['appointment_status'] : '',
			'appointment_date'   => isset( $row['appointment_date'] ) ? (string) $row['appointment_date'] : '',
			'start_at'           => isset( $row['start_at'] ) ? (string) $row['start_at'] : '',
			'updated_at'         => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
		);
	}

	/**
	 * Map appointment row to confirm response payload.
	 *
	 * @param array<string,mixed> $row Appointment row.
	 * @return array<string,mixed>
	 */
	protected function map_public_confirm_appointment_payload( array $row ) {
		return array(
			'id'                 => absint( isset( $row['id'] ) ? $row['id'] : 0 ),
			'appointment_status' => isset( $row['appointment_status'] ) ? (string) $row['appointment_status'] : '',
			'appointment_date'   => isset( $row['appointment_date'] ) ? (string) $row['appointment_date'] : '',
			'start_at'           => isset( $row['start_at'] ) ? (string) $row['start_at'] : '',
			'updated_at'         => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
		);
	}

	/**
	 * Normalize per_page.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	protected function normalize_per_page( $value ) {
		$value = absint( $value );

		if ( $value < 1 ) {
			return 20;
		}

		return min( 100, $value );
	}

	/**
	 * Normalize page.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	protected function normalize_page( $value ) {
		return max( 1, absint( $value ) );
	}

	/**
	 * Normalize search text.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function normalize_search( $value ) {
		$search = sanitize_text_field( (string) $value );

		return '' === $search ? '' : mb_substr( $search, 0, 120 );
	}

	/**
	 * Normalize order.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function normalize_order( $value ) {
		return 'ASC' === strtoupper( (string) $value ) ? 'ASC' : 'DESC';
	}

	/**
	 * Normalize key filter.
	 *
	 * @param mixed              $value   Raw value.
	 * @param array<int, string> $allowed Allowed values.
	 * @return string
	 */
	protected function normalize_key_filter( $value, array $allowed ) {
		$key = sanitize_key( (string) $value );

		if ( '' === $key ) {
			return '';
		}

		return in_array( $key, $allowed, true ) ? $key : '';
	}

	/**
	 * Normalize orderby.
	 *
	 * @param mixed              $value   Raw value.
	 * @param array<int, string> $allowed Allowed values.
	 * @param string             $default Default value.
	 * @return string
	 */
	protected function normalize_orderby( $value, array $allowed, $default ) {
		$orderby = sanitize_key( (string) $value );

		return in_array( $orderby, $allowed, true ) ? $orderby : $default;
	}

	/**
	 * Normalize date.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function normalize_date( $value ) {
		$raw = sanitize_text_field( (string) $value );
		if ( '' === $raw ) {
			return '';
		}

		$timestamp = strtotime( $raw );
		return false === $timestamp ? '' : gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Normalize cancel reason.
	 *
	 * @param mixed $value Raw reason.
	 * @return string
	 */
	protected function normalize_reason( $value ) {
		return mb_substr( sanitize_text_field( (string) $value ), 0, 280 );
	}

	/**
	 * Normalize idempotency key.
	 *
	 * @param mixed $value Raw key.
	 * @return string
	 */
	protected function normalize_idempotency_key( $value ) {
		return mb_substr( sanitize_text_field( (string) $value ), 0, 120 );
	}

	/**
	 * Build paginated response.
	 *
	 * @param array<int,array<string,mixed>> $items    Items.
	 * @param int                            $page     Page.
	 * @param int                            $per_page Per page.
	 * @param int                            $total    Total.
	 * @return array<string,mixed>
	 */
	protected function build_collection_response( array $items, $page, $per_page, $total ) {
		$page       = max( 1, absint( $page ) );
		$per_page   = max( 1, absint( $per_page ) );
		$total      = max( 0, absint( $total ) );
		$total_page = (int) ceil( $total / $per_page );

		return array(
			'items'       => $items,
			'count'       => count( $items ),
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total_page,
		);
	}
}
