<?php
/**
 * Client REST controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Helpers\Permission_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;
use Super_Mechanic\Vehicles\Vehicle_Service;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes authenticated client-only read REST endpoints.
 */
class Client_REST_Controller {
	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'super-mechanic/v1';

	/**
	 * Dashboard service.
	 *
	 * @var Dashboard_Service
	 */
	protected $dashboard_service;

	/**
	 * Client process view service.
	 *
	 * @var Client_Process_View_Service
	 */
	protected $client_process_view_service;

	/**
	 * Vehicle service.
	 *
	 * @var Vehicle_Service
	 */
	protected $vehicle_service;

	/**
	 * Quote service.
	 *
	 * @var Quote_Service
	 */
	protected $quote_service;

	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * Invoice service.
	 *
	 * @var Invoice_Service
	 */
	protected $invoice_service;

	/**
	 * Permission service.
	 *
	 * @var Permission_Service
	 */
	protected $permission_service;

	/**
	 * Access control service.
	 *
	 * @var Access_Control_Service
	 */
	protected $access_control_service;

	/**
	 * Constructor.
	 *
	 * @param Dashboard_Service|null           $dashboard_service           Dashboard service.
	 * @param Client_Process_View_Service|null $client_process_view_service Client process view service.
	 * @param Vehicle_Service|null             $vehicle_service             Vehicle service.
	 * @param Quote_Service|null               $quote_service               Quote service.
	 * @param Process_Service|null             $process_service             Process service.
	 * @param Invoice_Service|null             $invoice_service             Invoice service.
	 * @param Permission_Service|null          $permission_service          Permission service.
	 * @param Access_Control_Service|null      $access_control_service      Access control service.
	 */
	public function __construct( Dashboard_Service $dashboard_service = null, Client_Process_View_Service $client_process_view_service = null, Vehicle_Service $vehicle_service = null, Quote_Service $quote_service = null, Process_Service $process_service = null, Invoice_Service $invoice_service = null, Permission_Service $permission_service = null, Access_Control_Service $access_control_service = null ) {
		$this->dashboard_service           = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->client_process_view_service = $client_process_view_service ? $client_process_view_service : new Client_Process_View_Service( $this->dashboard_service );
		$this->vehicle_service             = $vehicle_service ? $vehicle_service : new Vehicle_Service();
		$this->quote_service               = $quote_service ? $quote_service : new Quote_Service();
		$this->process_service             = $process_service ? $process_service : new Process_Service();
		$this->invoice_service             = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->permission_service          = $permission_service ? $permission_service : new Permission_Service();
		$this->access_control_service      = $access_control_service ? $access_control_service : new Access_Control_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register client routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/client/processes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_processes' ),
				'permission_callback' => array( $this, 'check_client_portal_permission' ),
				'args'                => $this->get_process_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/client/processes/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_process' ),
				'permission_callback' => array( $this, 'check_client_portal_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/client/vehicles',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_vehicles' ),
				'permission_callback' => array( $this, 'check_client_portal_permission' ),
				'args'                => $this->get_vehicle_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/client/vehicles/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_vehicle' ),
				'permission_callback' => array( $this, 'check_client_portal_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/client/quotes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_quotes' ),
				'permission_callback' => array( $this, 'check_client_portal_permission' ),
				'args'                => $this->get_quote_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/client/quotes/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_quote' ),
				'permission_callback' => array( $this, 'check_client_portal_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/client/invoices',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_invoices' ),
				'permission_callback' => array( $this, 'check_client_portal_permission' ),
				'args'                => $this->get_invoice_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/client/invoices/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_invoice' ),
				'permission_callback' => array( $this, 'check_client_portal_permission' ),
			)
		);
	}

	/**
	 * Check client portal permission.
	 *
	 * @return true|\WP_Error
	 */
	public function check_client_portal_permission() {
		return $this->permission_service->user_can_access_client_portal( get_current_user_id() );
	}

	/**
	 * Get client processes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function get_client_processes( WP_REST_Request $request ) {
		$user_id   = get_current_user_id();
		$client_id = $this->dashboard_service->get_client_id_by_user_id( $user_id );
		$args      = array(
			'client_id'    => absint( $client_id ),
			'per_page'     => $this->normalize_per_page( $request->get_param( 'per_page' ) ),
			'page'         => $this->normalize_page( $request->get_param( 'page' ) ),
			'search'       => $this->normalize_search( $request->get_param( 'search' ) ),
			'status'       => $this->normalize_key_filter( $request->get_param( 'status' ), array( 'draft', 'pending', 'in_progress', 'waiting_approval', 'waiting_parts', 'completed', 'delivered', 'cancelled' ) ),
			'process_type' => $this->normalize_key_filter( $request->get_param( 'type' ), array( 'maintenance', 'pre_delivery', 'paperwork' ) ),
			'date_from'    => $this->normalize_date( $request->get_param( 'date_from' ) ),
			'date_to'      => $this->normalize_date( $request->get_param( 'date_to' ) ),
			'orderby'      => $this->normalize_orderby( $request->get_param( 'orderby' ), array( 'id', 'title', 'process_type', 'status', 'vehicle', 'client', 'opened_at', 'due_date', 'created_at' ), 'created_at' ),
			'order'        => $this->normalize_order( $request->get_param( 'order' ) ),
		);

		if ( empty( $args['client_id'] ) ) {
			return $this->build_collection_response( array(), $args['page'], $args['per_page'], 0 );
		}

		$items = $this->process_service->get_processes( $args );
		$total = $this->process_service->count_processes( $args );

		return $this->build_collection_response(
			array_map( array( $this, 'map_process_payload' ), $items ),
			$args['page'],
			$args['per_page'],
			$total
		);
	}

	/**
	 * Get process detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_client_process( WP_REST_Request $request ) {
		$user_id    = get_current_user_id();
		$process_id = absint( $request->get_param( 'id' ) );

		if ( ! $this->access_control_service->user_can_access_process( $user_id, $process_id ) ) {
			return $this->forbidden_error();
		}

		$process = $this->client_process_view_service->get_client_process_by_id( $user_id, $process_id );

		if ( empty( $process ) ) {
			return $this->not_found_error( __( 'El proceso no existe.', 'super-mechanic' ) );
		}

		return array(
			'item' => $this->map_process_payload( $process ),
		);
	}

	/**
	 * Get client vehicles.
	 *
	 * @return array<string, mixed>
	 */
	public function get_client_vehicles( WP_REST_Request $request ) {
		$user_id   = get_current_user_id();
		$page      = $this->normalize_page( $request->get_param( 'page' ) );
		$per_page  = $this->normalize_per_page( $request->get_param( 'per_page' ) );
		$search    = $this->normalize_search( $request->get_param( 'search' ) );
		$type      = $this->normalize_key_filter( $request->get_param( 'type' ), array( 'vehicle' ) );
		$status    = $this->normalize_key_filter( $request->get_param( 'status' ), array( 'active', 'inactive' ) );
		$date_from = $this->normalize_date( $request->get_param( 'date_from' ) );
		$date_to   = $this->normalize_date( $request->get_param( 'date_to' ) );
		$orderby   = $this->normalize_orderby( $request->get_param( 'orderby' ), array( 'id', 'make', 'model', 'vin', 'plate', 'year', 'created_at' ), 'created_at' );
		$order     = $this->normalize_order( $request->get_param( 'order' ) );
		$relations = $this->dashboard_service->get_client_vehicles( $user_id );
		$vehicles  = array();

		foreach ( $relations as $relation ) {
			$vehicle_id = ! empty( $relation['vehicle_id'] ) ? absint( $relation['vehicle_id'] ) : 0;

			if ( ! $vehicle_id || ! $this->access_control_service->user_can_access_vehicle( $user_id, $vehicle_id ) ) {
				continue;
			}

			$vehicle = $this->vehicle_service->get_vehicle( $vehicle_id );

			if ( ! $vehicle ) {
				continue;
			}

			$vehicles[] = $vehicle;
		}

		$vehicles = $this->apply_vehicle_filters( $vehicles, $search, $type, $status, $date_from, $date_to );
		$vehicles = $this->sort_vehicles( $vehicles, $orderby, $order );
		$total    = count( $vehicles );
		$offset   = ( $page - 1 ) * $per_page;
		$items    = array_slice( $vehicles, $offset, $per_page );

		return $this->build_collection_response(
			array_map( array( $this, 'map_vehicle_payload' ), $items ),
			$page,
			$per_page,
			$total
		);
	}

	/**
	 * Get vehicle detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_client_vehicle( WP_REST_Request $request ) {
		$user_id    = get_current_user_id();
		$vehicle_id = absint( $request->get_param( 'id' ) );

		if ( ! $this->access_control_service->user_can_access_vehicle( $user_id, $vehicle_id ) ) {
			return $this->forbidden_error();
		}

		$vehicle = $this->vehicle_service->get_vehicle( $vehicle_id );

		if ( ! $vehicle ) {
			return $this->not_found_error( __( 'El vehículo no existe.', 'super-mechanic' ) );
		}

		return array(
			'item' => $this->map_vehicle_payload( $vehicle ),
		);
	}

	/**
	 * Get client quotes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function get_client_quotes( WP_REST_Request $request ) {
		$user_id   = get_current_user_id();
		$client_id = $this->dashboard_service->get_client_id_by_user_id( $user_id );
		$args      = array(
			'client_id'    => absint( $client_id ),
			'per_page'     => $this->normalize_per_page( $request->get_param( 'per_page' ) ),
			'page'         => $this->normalize_page( $request->get_param( 'page' ) ),
			'search'       => $this->normalize_search( $request->get_param( 'search' ) ),
			'status'       => $this->normalize_key_filter( $request->get_param( 'status' ), array( 'draft', 'sent', 'approved', 'rejected', 'expired', 'cancelled' ) ),
			'process_type' => $this->normalize_key_filter( $request->get_param( 'type' ), array( 'maintenance', 'pre_delivery', 'paperwork' ) ),
			'date_from'    => $this->normalize_date( $request->get_param( 'date_from' ) ),
			'date_to'      => $this->normalize_date( $request->get_param( 'date_to' ) ),
			'orderby'      => $this->normalize_orderby( $request->get_param( 'orderby' ), array( 'id', 'quote_number', 'status', 'grand_total', 'created_at', 'updated_at' ), 'created_at' ),
			'order'        => $this->normalize_order( $request->get_param( 'order' ) ),
		);
		$quotes    = $this->quote_service->get_quotes_for_user(
			$user_id,
			$args
		);
		$total     = $this->quote_service->count_quotes( $args );

		return $this->build_collection_response(
			array_map( array( $this, 'map_quote_payload' ), $quotes ),
			$args['page'],
			$args['per_page'],
			$total
		);
	}

	/**
	 * Get quote detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_client_quote( WP_REST_Request $request ) {
		$user_id   = get_current_user_id();
		$quote_id  = absint( $request->get_param( 'id' ) );

		if ( ! $this->quote_service->user_can_access_quote( $user_id, $quote_id ) ) {
			return $this->forbidden_error();
		}

		$quote = $this->quote_service->get_quote( $quote_id );

		if ( ! $quote ) {
			return $this->not_found_error( __( 'La cotización no existe.', 'super-mechanic' ) );
		}

		return array(
			'item' => $this->map_quote_payload( $quote ),
		);
	}

	/**
	 * Get client invoices.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function get_client_invoices( WP_REST_Request $request ) {
		$user_id   = get_current_user_id();
		$client_id = $this->dashboard_service->get_client_id_by_user_id( $user_id );
		$args      = array(
			'client_id'    => absint( $client_id ),
			'per_page'     => $this->normalize_per_page( $request->get_param( 'per_page' ) ),
			'page'         => $this->normalize_page( $request->get_param( 'page' ) ),
			'search'       => $this->normalize_search( $request->get_param( 'search' ) ),
			'status'       => $this->normalize_key_filter( $request->get_param( 'status' ), array( 'draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled', 'refunded' ) ),
			'process_type' => $this->normalize_key_filter( $request->get_param( 'type' ), array( 'maintenance', 'pre_delivery', 'paperwork' ) ),
			'date_from'    => $this->normalize_date( $request->get_param( 'date_from' ) ),
			'date_to'      => $this->normalize_date( $request->get_param( 'date_to' ) ),
			'orderby'      => $this->normalize_orderby( $request->get_param( 'orderby' ), array( 'id', 'invoice_number', 'status', 'grand_total', 'balance_due', 'created_at', 'updated_at', 'due_date' ), 'created_at' ),
			'order'        => $this->normalize_order( $request->get_param( 'order' ) ),
		);
		$invoices  = $this->invoice_service->get_invoices_for_user(
			$user_id,
			$args
		);
		$total     = $this->invoice_service->count_invoices( $args );

		$payload = array();
		foreach ( $invoices as $invoice ) {
			$payload[] = $this->map_invoice_payload( $this->invoice_service->append_collection_state( $invoice ) );
		}

		return $this->build_collection_response( $payload, $args['page'], $args['per_page'], $total );
	}

	/**
	 * Get invoice detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_client_invoice( WP_REST_Request $request ) {
		$user_id     = get_current_user_id();
		$invoice_id  = absint( $request->get_param( 'id' ) );

		if ( ! $this->invoice_service->user_can_access_invoice( $user_id, $invoice_id ) ) {
			return $this->forbidden_error();
		}

		$invoice = $this->invoice_service->get_invoice( $invoice_id );

		if ( ! $invoice ) {
			return $this->not_found_error( __( 'La factura no existe.', 'super-mechanic' ) );
		}

		return array(
			'item' => $this->map_invoice_payload( $this->invoice_service->append_collection_state( $invoice ) ),
		);
	}

	/**
	 * Map process payload.
	 *
	 * @param array<string, mixed> $process Process row.
	 * @return array<string, mixed>
	 */
	protected function map_process_payload( array $process ) {
		return array(
			'id'             => absint( $process['id'] ),
			'client_id'      => absint( $process['client_id'] ),
			'vehicle_id'     => absint( $process['vehicle_id'] ),
			'title'          => (string) $process['title'],
			'process_type'   => (string) $process['process_type'],
			'status'         => (string) $process['status'],
			'priority'       => isset( $process['priority'] ) ? (string) $process['priority'] : '',
			'opened_at'      => isset( $process['opened_at'] ) ? (string) $process['opened_at'] : '',
			'due_date'       => isset( $process['due_date'] ) ? (string) $process['due_date'] : '',
			'completed_at'   => isset( $process['completed_at'] ) ? (string) $process['completed_at'] : '',
			'created_at'     => isset( $process['created_at'] ) ? (string) $process['created_at'] : '',
			'updated_at'     => isset( $process['updated_at'] ) ? (string) $process['updated_at'] : '',
			'client_name'    => isset( $process['client_name'] ) ? (string) $process['client_name'] : '',
			'vehicle_make'   => isset( $process['vehicle_make'] ) ? (string) $process['vehicle_make'] : '',
			'vehicle_model'  => isset( $process['vehicle_model'] ) ? (string) $process['vehicle_model'] : '',
			'vehicle_plate'  => isset( $process['vehicle_plate'] ) ? (string) $process['vehicle_plate'] : '',
			'vehicle_vin'    => isset( $process['vehicle_vin'] ) ? (string) $process['vehicle_vin'] : '',
		);
	}

	/**
	 * Map client-vehicle relation payload.
	 *
	 * @param array<string, mixed> $vehicle Vehicle relation row.
	 * @return array<string, mixed>
	 */
	protected function map_client_vehicle_payload( array $vehicle ) {
		return array(
			'vehicle_id'      => absint( $vehicle['vehicle_id'] ),
			'make'            => isset( $vehicle['make'] ) ? (string) $vehicle['make'] : '',
			'model'           => isset( $vehicle['model'] ) ? (string) $vehicle['model'] : '',
			'plate'           => isset( $vehicle['plate'] ) ? (string) $vehicle['plate'] : '',
			'vin'             => isset( $vehicle['vin'] ) ? (string) $vehicle['vin'] : '',
			'ownership_type'  => isset( $vehicle['ownership_type'] ) ? (string) $vehicle['ownership_type'] : '',
			'is_primary'      => isset( $vehicle['is_primary'] ) ? (int) $vehicle['is_primary'] : 0,
			'start_date'      => isset( $vehicle['start_date'] ) ? (string) $vehicle['start_date'] : '',
			'end_date'        => isset( $vehicle['end_date'] ) ? (string) $vehicle['end_date'] : '',
		);
	}

	/**
	 * Map vehicle payload.
	 *
	 * @param array<string, mixed> $vehicle Vehicle row.
	 * @return array<string, mixed>
	 */
	protected function map_vehicle_payload( array $vehicle ) {
		return array(
			'id'         => absint( $vehicle['id'] ),
			'client_id'  => absint( $vehicle['client_id'] ),
			'type'       => isset( $vehicle['type'] ) ? (string) $vehicle['type'] : '',
			'make'       => isset( $vehicle['make'] ) ? (string) $vehicle['make'] : '',
			'brand'      => isset( $vehicle['brand'] ) ? (string) $vehicle['brand'] : ( isset( $vehicle['make'] ) ? (string) $vehicle['make'] : '' ),
			'model'      => isset( $vehicle['model'] ) ? (string) $vehicle['model'] : '',
			'year'       => isset( $vehicle['year'] ) ? absint( $vehicle['year'] ) : 0,
			'vin'        => isset( $vehicle['vin'] ) ? (string) $vehicle['vin'] : '',
			'plate'      => isset( $vehicle['plate'] ) ? (string) $vehicle['plate'] : '',
			'color'      => isset( $vehicle['color'] ) ? (string) $vehicle['color'] : '',
			'mileage'    => isset( $vehicle['mileage'] ) ? absint( $vehicle['mileage'] ) : 0,
			'status'     => isset( $vehicle['status'] ) ? (string) $vehicle['status'] : '',
			'created_at' => isset( $vehicle['created_at'] ) ? (string) $vehicle['created_at'] : '',
			'updated_at' => isset( $vehicle['updated_at'] ) ? (string) $vehicle['updated_at'] : '',
			'client_name'=> isset( $vehicle['client_name'] ) ? (string) $vehicle['client_name'] : '',
		);
	}

	/**
	 * Map quote payload.
	 *
	 * @param array<string, mixed> $quote Quote row.
	 * @return array<string, mixed>
	 */
	protected function map_quote_payload( array $quote ) {
		return array(
			'id'                 => absint( $quote['id'] ),
			'process_id'         => absint( $quote['process_id'] ),
			'client_id'          => absint( $quote['client_id'] ),
			'quote_number'       => (string) $quote['quote_number'],
			'status'             => (string) $quote['status'],
			'currency'           => (string) $quote['currency'],
			'subtotal'           => (float) $quote['subtotal'],
			'tax_total'          => (float) $quote['tax_total'],
			'discount_total'     => (float) $quote['discount_total'],
			'grand_total'        => (float) $quote['grand_total'],
			'approved_by_client' => isset( $quote['approved_by_client'] ) ? (int) $quote['approved_by_client'] : 0,
			'approved_at'        => isset( $quote['approved_at'] ) ? (string) $quote['approved_at'] : '',
			'rejected_at'        => isset( $quote['rejected_at'] ) ? (string) $quote['rejected_at'] : '',
			'created_at'         => isset( $quote['created_at'] ) ? (string) $quote['created_at'] : '',
			'updated_at'         => isset( $quote['updated_at'] ) ? (string) $quote['updated_at'] : '',
			'process_title'      => isset( $quote['process_title'] ) ? (string) $quote['process_title'] : '',
			'client_name'        => isset( $quote['client_name'] ) ? (string) $quote['client_name'] : '',
			'vehicle_make'       => isset( $quote['vehicle_make'] ) ? (string) $quote['vehicle_make'] : '',
			'vehicle_model'      => isset( $quote['vehicle_model'] ) ? (string) $quote['vehicle_model'] : '',
			'vehicle_plate'      => isset( $quote['vehicle_plate'] ) ? (string) $quote['vehicle_plate'] : '',
		);
	}

	/**
	 * Map invoice payload.
	 *
	 * @param array<string, mixed> $invoice Invoice row.
	 * @return array<string, mixed>
	 */
	protected function map_invoice_payload( array $invoice ) {
		return array(
			'id'                 => absint( $invoice['id'] ),
			'process_id'         => absint( $invoice['process_id'] ),
			'quote_id'           => absint( $invoice['quote_id'] ),
			'client_id'          => absint( $invoice['client_id'] ),
			'invoice_number'     => (string) $invoice['invoice_number'],
			'status'             => (string) $invoice['status'],
			'currency'           => (string) $invoice['currency'],
			'subtotal'           => (float) $invoice['subtotal'],
			'tax_total'          => (float) $invoice['tax_total'],
			'discount_total'     => (float) $invoice['discount_total'],
			'grand_total'        => (float) $invoice['grand_total'],
			'amount_paid'        => (float) $invoice['amount_paid'],
			'balance_due'        => (float) $invoice['balance_due'],
			'issued_at'          => isset( $invoice['issued_at'] ) ? (string) $invoice['issued_at'] : '',
			'due_date'           => isset( $invoice['due_date'] ) ? (string) $invoice['due_date'] : '',
			'paid_at'            => isset( $invoice['paid_at'] ) ? (string) $invoice['paid_at'] : '',
			'created_at'         => isset( $invoice['created_at'] ) ? (string) $invoice['created_at'] : '',
			'updated_at'         => isset( $invoice['updated_at'] ) ? (string) $invoice['updated_at'] : '',
			'process_title'      => isset( $invoice['process_title'] ) ? (string) $invoice['process_title'] : '',
			'collection_status'  => isset( $invoice['collection_status'] ) ? (string) $invoice['collection_status'] : '',
			'collection_label'   => isset( $invoice['collection_label'] ) ? (string) $invoice['collection_label'] : '',
			'payment_status'     => isset( $invoice['payment_status'] ) ? (string) $invoice['payment_status'] : '',
			'payment_label'      => isset( $invoice['payment_label'] ) ? (string) $invoice['payment_label'] : '',
			'client_name'        => isset( $invoice['client_name'] ) ? (string) $invoice['client_name'] : '',
			'vehicle_make'       => isset( $invoice['vehicle_make'] ) ? (string) $invoice['vehicle_make'] : '',
			'vehicle_model'      => isset( $invoice['vehicle_model'] ) ? (string) $invoice['vehicle_model'] : '',
			'vehicle_plate'      => isset( $invoice['vehicle_plate'] ) ? (string) $invoice['vehicle_plate'] : '',
		);
	}

	/**
	 * Get common collection args.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_common_collection_args() {
		return array(
			'per_page'  => $this->get_per_page_arg(),
			'page'      => $this->get_page_arg(),
			'search'    => $this->get_search_arg(),
			'orderby'   => $this->get_orderby_arg(),
			'order'     => $this->get_order_arg(),
			'date_from' => $this->get_date_arg(),
			'date_to'   => $this->get_date_arg(),
		);
	}

	/**
	 * Get process collection args.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_process_collection_args() {
		$args           = $this->get_common_collection_args();
		$args['status'] = $this->get_key_arg();
		$args['type']   = $this->get_key_arg();

		return $args;
	}

	/**
	 * Get vehicle collection args.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_vehicle_collection_args() {
		$args           = $this->get_common_collection_args();
		$args['status'] = $this->get_key_arg();
		$args['type']   = $this->get_key_arg();

		return $args;
	}

	/**
	 * Get quote collection args.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_quote_collection_args() {
		$args           = $this->get_common_collection_args();
		$args['status'] = $this->get_key_arg();
		$args['type']   = $this->get_key_arg();

		return $args;
	}

	/**
	 * Get invoice collection args.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_invoice_collection_args() {
		$args           = $this->get_common_collection_args();
		$args['status'] = $this->get_key_arg();
		$args['type']   = $this->get_key_arg();

		return $args;
	}

	/**
	 * Build per_page route arg config.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_per_page_arg() {
		return array(
			'description'       => __( 'Cantidad máxima de resultados por solicitud.', 'super-mechanic' ),
			'type'              => 'integer',
			'default'           => 20,
			'sanitize_callback' => 'absint',
			'validate_callback' => static function ( $value ) {
				$value = absint( $value );
				return $value >= 1 && $value <= 100;
			},
		);
	}

	/**
	 * Build page route arg config.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_page_arg() {
		return array(
			'description'       => __( 'Página de resultados.', 'super-mechanic' ),
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => static function ( $value ) {
				return absint( $value ) >= 1;
			},
		);
	}

	/**
	 * Build search route arg config.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_search_arg() {
		return array(
			'description'       => __( 'Texto de búsqueda.', 'super-mechanic' ),
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * Build orderby route arg config.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_orderby_arg() {
		return array(
			'description'       => __( 'Campo de ordenamiento.', 'super-mechanic' ),
			'type'              => 'string',
			'default'           => 'created_at',
			'sanitize_callback' => 'sanitize_key',
		);
	}

	/**
	 * Build order route arg config.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_order_arg() {
		return array(
			'description'       => __( 'Dirección de ordenamiento.', 'super-mechanic' ),
			'type'              => 'string',
			'default'           => 'DESC',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * Build date route arg config.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_date_arg() {
		return array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * Build key route arg config.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_key_arg() {
		return array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_key',
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
		$value = absint( $value );

		return max( 1, $value );
	}

	/**
	 * Normalize search value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function normalize_search( $value ) {
		$search = sanitize_text_field( (string) $value );

		if ( '' === $search ) {
			return '';
		}

		return mb_substr( $search, 0, 120 );
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
	 * Normalize orderby.
	 *
	 * @param mixed              $value Raw value.
	 * @param array<int, string> $allowed Allowed values.
	 * @param string             $default Default.
	 * @return string
	 */
	protected function normalize_orderby( $value, array $allowed, $default ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Normalize a key filter.
	 *
	 * @param mixed              $value Raw value.
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
	 * Normalize date Y-m-d.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function normalize_date( $value ) {
		$value = sanitize_text_field( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? '' : gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Build a consistent list response.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @param int                              $page Page.
	 * @param int                              $per_page Per page.
	 * @param int                              $total Total.
	 * @return array<string, mixed>
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

	/**
	 * Apply vehicle filters in-memory for owned rows.
	 *
	 * @param array<int, array<string, mixed>> $vehicles Vehicle rows.
	 * @param string                            $search Search.
	 * @param string                            $type Type.
	 * @param string                            $status Status.
	 * @param string                            $date_from Date from.
	 * @param string                            $date_to Date to.
	 * @return array<int, array<string, mixed>>
	 */
	protected function apply_vehicle_filters( array $vehicles, $search, $type, $status, $date_from, $date_to ) {
		$search_lower = '' !== $search ? mb_strtolower( $search ) : '';

		return array_values(
			array_filter(
				$vehicles,
				static function ( $vehicle ) use ( $search_lower, $type, $status, $date_from, $date_to ) {
					if ( '' !== $type && ( ! isset( $vehicle['type'] ) || $type !== sanitize_key( (string) $vehicle['type'] ) ) ) {
						return false;
					}

					if ( '' !== $status && ( ! isset( $vehicle['status'] ) || $status !== sanitize_key( (string) $vehicle['status'] ) ) ) {
						return false;
					}

					if ( '' !== $date_from && ! empty( $vehicle['created_at'] ) && strtotime( (string) $vehicle['created_at'] ) < strtotime( $date_from . ' 00:00:00' ) ) {
						return false;
					}

					if ( '' !== $date_to && ! empty( $vehicle['created_at'] ) && strtotime( (string) $vehicle['created_at'] ) > strtotime( $date_to . ' 23:59:59' ) ) {
						return false;
					}

					if ( '' === $search_lower ) {
						return true;
					}

					$haystack = implode(
						' ',
						array(
							isset( $vehicle['make'] ) ? (string) $vehicle['make'] : '',
							isset( $vehicle['model'] ) ? (string) $vehicle['model'] : '',
							isset( $vehicle['vin'] ) ? (string) $vehicle['vin'] : '',
							isset( $vehicle['plate'] ) ? (string) $vehicle['plate'] : '',
						)
					);

					return false !== mb_strpos( mb_strtolower( $haystack ), $search_lower );
				}
			)
		);
	}

	/**
	 * Sort vehicles.
	 *
	 * @param array<int, array<string, mixed>> $vehicles Vehicles.
	 * @param string                            $orderby Orderby.
	 * @param string                            $order Order.
	 * @return array<int, array<string, mixed>>
	 */
	protected function sort_vehicles( array $vehicles, $orderby, $order ) {
		usort(
			$vehicles,
			static function ( $left, $right ) use ( $orderby, $order ) {
				$left_value  = isset( $left[ $orderby ] ) ? $left[ $orderby ] : '';
				$right_value = isset( $right[ $orderby ] ) ? $right[ $orderby ] : '';

				if ( 'year' === $orderby || 'id' === $orderby ) {
					$compare = absint( $left_value ) <=> absint( $right_value );
				} elseif ( 'created_at' === $orderby ) {
					$compare = strtotime( (string) $left_value ) <=> strtotime( (string) $right_value );
				} else {
					$compare = strnatcasecmp( (string) $left_value, (string) $right_value );
				}

				return 'ASC' === $order ? $compare : -$compare;
			}
		);

		return $vehicles;
	}

	/**
	 * Build a forbidden error.
	 *
	 * @return \WP_Error
	 */
	protected function forbidden_error() {
		return new \WP_Error( 'sm_rest_forbidden', __( 'No tienes acceso a este recurso.', 'super-mechanic' ), array( 'status' => 403 ) );
	}

	/**
	 * Build a not found error.
	 *
	 * @param string $message Error message.
	 * @return \WP_Error
	 */
	protected function not_found_error( $message ) {
		return new \WP_Error( 'sm_rest_not_found', $message, array( 'status' => 404 ) );
	}
}
