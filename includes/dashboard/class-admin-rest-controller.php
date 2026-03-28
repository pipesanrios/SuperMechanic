<?php
/**
 * Admin REST controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Communication\Comment_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;
use Super_Mechanic\Vehicles\Vehicle_Service;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes authenticated admin-only read REST endpoints.
 */
class Admin_REST_Controller {
	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'super-mechanic/v1';

	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * Vehicle service.
	 *
	 * @var Vehicle_Service
	 */
	protected $vehicle_service;

	/**
	 * Client service.
	 *
	 * @var Client_Service
	 */
	protected $client_service;

	/**
	 * Quote service.
	 *
	 * @var Quote_Service
	 */
	protected $quote_service;

	/**
	 * Invoice service.
	 *
	 * @var Invoice_Service
	 */
	protected $invoice_service;

	/**
	 * Comment service.
	 *
	 * @var Comment_Service
	 */
	protected $comment_service;

	/**
	 * Constructor.
	 *
	 * @param Process_Service|null $process_service Process service.
	 * @param Vehicle_Service|null $vehicle_service Vehicle service.
	 * @param Client_Service|null  $client_service  Client service.
	 * @param Quote_Service|null   $quote_service   Quote service.
	 * @param Invoice_Service|null $invoice_service Invoice service.
	 * @param Comment_Service|null $comment_service Comment service.
	 */
	public function __construct( Process_Service $process_service = null, Vehicle_Service $vehicle_service = null, Client_Service $client_service = null, Quote_Service $quote_service = null, Invoice_Service $invoice_service = null, Comment_Service $comment_service = null ) {
		$this->process_service = $process_service ? $process_service : new Process_Service();
		$this->vehicle_service = $vehicle_service ? $vehicle_service : new Vehicle_Service();
		$this->client_service  = $client_service ? $client_service : new Client_Service();
		$this->quote_service   = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->comment_service = $comment_service ? $comment_service : new Comment_Service();
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
	 * Register admin routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/admin/processes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_processes' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_process_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/processes/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_process' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/processes/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_admin_process_status' ),
				'permission_callback' => array( $this, 'check_admin_process_write_permission' ),
				'args'                => $this->get_process_status_write_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/processes/(?P<id>\d+)/internal-comment',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_admin_process_internal_comment' ),
				'permission_callback' => array( $this, 'check_admin_process_write_permission' ),
				'args'                => $this->get_process_internal_comment_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/vehicles',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_vehicles' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_vehicle_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/vehicles/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_vehicle' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/clients',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_clients' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_client_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/clients/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_client' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/quotes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_quotes' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_quote_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/quotes/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_quote' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/invoices',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_invoices' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_invoice_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/invoices/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_admin_invoice' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Check admin permission.
	 *
	 * @return true|\WP_Error
	 */
	public function check_admin_permission() {
		$user_id = get_current_user_id();

		if ( ! $user_id || ! is_user_logged_in() ) {
			return new \WP_Error( 'sm_rest_login_required', __( 'Debe iniciar sesión para acceder a este recurso.', 'super-mechanic' ), array( 'status' => 401 ) );
		}

		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			return new \WP_Error( 'sm_rest_forbidden', __( 'No tienes permisos para acceder a este recurso.', 'super-mechanic' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check admin permission for process write actions.
	 *
	 * @return true|\WP_Error
	 */
	public function check_admin_process_write_permission() {
		$base_permission = $this->check_admin_permission();

		if ( is_wp_error( $base_permission ) ) {
			return $base_permission;
		}

		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			return new \WP_Error( 'sm_rest_process_write_forbidden', __( 'No tienes permisos para ejecutar acciones de procesos.', 'super-mechanic' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Get admin processes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function get_admin_processes( WP_REST_Request $request ) {
		$args = array(
			'per_page'     => $this->normalize_per_page( $request->get_param( 'per_page' ) ),
			'page'         => $this->normalize_page( $request->get_param( 'page' ) ),
			'search'       => $this->normalize_search( $request->get_param( 'search' ) ),
			'vehicle_id'   => absint( $request->get_param( 'vehicle_id' ) ),
			'client_id'    => absint( $request->get_param( 'client_id' ) ),
			'process_type' => $this->normalize_key_filter( $request->get_param( 'process_type' ), array( 'maintenance', 'pre_delivery', 'paperwork' ) ),
			'status'       => $this->normalize_key_filter( $request->get_param( 'status' ), array( 'draft', 'pending', 'in_progress', 'waiting_approval', 'waiting_parts', 'completed', 'delivered', 'cancelled' ) ),
			'date_from'    => $this->normalize_date( $request->get_param( 'date_from' ) ),
			'date_to'      => $this->normalize_date( $request->get_param( 'date_to' ) ),
			'orderby'      => $this->normalize_orderby( $request->get_param( 'orderby' ), array( 'id', 'title', 'process_type', 'status', 'vehicle', 'client', 'opened_at', 'due_date', 'created_at' ), 'created_at' ),
			'order'        => $this->normalize_order( $request->get_param( 'order' ) ),
		);

		$items = $this->process_service->get_processes( $args );
		$total = $this->process_service->count_processes( $args );

		return array(
			'items'      => array_map( array( $this, 'map_process_payload' ), $items ),
			'count'      => count( $items ),
			'total'      => absint( $total ),
			'page'       => $args['page'],
			'per_page'   => $args['per_page'],
			'total_pages'=> $this->calculate_total_pages( $total, $args['per_page'] ),
		);
	}

	/**
	 * Get admin process detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_admin_process( WP_REST_Request $request ) {
		$process = $this->process_service->get_process( absint( $request->get_param( 'id' ) ) );

		if ( empty( $process ) ) {
			return $this->not_found_error( __( 'El proceso no existe.', 'super-mechanic' ) );
		}

		return array(
			'item' => $this->map_process_payload( $process ),
		);
	}

	/**
	 * Update process status with a minimal write payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_admin_process_status( WP_REST_Request $request ) {
		$process_id = absint( $request->get_param( 'id' ) );
		$process    = $this->process_service->get_process( $process_id );

		if ( empty( $process ) ) {
			return $this->not_found_error( __( 'El proceso no existe.', 'super-mechanic' ) );
		}

		$status = $this->normalize_key_filter( $request->get_param( 'status' ), $this->get_process_status_values() );

		if ( '' === $status ) {
			return new \WP_Error( 'sm_rest_invalid_status', __( 'El estado del proceso no es válido.', 'super-mechanic' ), array( 'status' => 400 ) );
		}

		$result = $this->process_service->update_process(
			$process_id,
			array(
				'status' => $status,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $this->as_rest_error( $result, 400 );
		}

		$updated = $this->process_service->get_process( $process_id );

		return array(
			'item' => $this->map_process_payload( $updated ),
		);
	}

	/**
	 * Create an internal process comment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_admin_process_internal_comment( WP_REST_Request $request ) {
		$process_id = absint( $request->get_param( 'id' ) );
		$process    = $this->process_service->get_process( $process_id );

		if ( empty( $process ) ) {
			return $this->not_found_error( __( 'El proceso no existe.', 'super-mechanic' ) );
		}

		$content = sanitize_textarea_field( (string) $request->get_param( 'content' ) );

		if ( '' === $content ) {
			return new \WP_Error( 'sm_rest_comment_content_required', __( 'El contenido del comentario es obligatorio.', 'super-mechanic' ), array( 'status' => 400 ) );
		}

		$comment_type = $this->normalize_key_filter( $request->get_param( 'comment_type' ), $this->get_internal_comment_type_values() );

		if ( '' === $comment_type ) {
			$comment_type = 'internal_note';
		}

		$comment_id = $this->comment_service->create_comment(
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'client_id'         => absint( $process['client_id'] ),
				'vehicle_id'        => absint( $process['vehicle_id'] ),
				'comment_type'      => $comment_type,
				'content'           => $content,
				'is_internal'       => 1,
				'is_client_visible' => 0,
				'author_user_id'    => get_current_user_id(),
				'status'            => 'published',
			)
		);

		if ( is_wp_error( $comment_id ) ) {
			return $this->as_rest_error( $comment_id, 400 );
		}

		$comment = $this->comment_service->get_comment( $comment_id );

		return array(
			'item' => $this->map_internal_comment_payload( $comment ),
		);
	}

	/**
	 * Get admin vehicles.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function get_admin_vehicles( WP_REST_Request $request ) {
		$args = array(
			'per_page'  => $this->normalize_per_page( $request->get_param( 'per_page' ) ),
			'page'      => $this->normalize_page( $request->get_param( 'page' ) ),
			'search'    => $this->normalize_search( $request->get_param( 'search' ) ),
			'status'    => $this->normalize_key_filter( $request->get_param( 'status' ), array( 'active', 'inactive' ) ),
			'type'      => $this->normalize_key_filter( $request->get_param( 'type' ), array( 'vehicle' ) ),
			'date_from' => $this->normalize_date( $request->get_param( 'date_from' ) ),
			'date_to'   => $this->normalize_date( $request->get_param( 'date_to' ) ),
			'client_id' => $this->normalize_nullable_id( $request->get_param( 'client_id' ) ),
			'orderby'   => $this->normalize_orderby( $request->get_param( 'orderby' ), array( 'id', 'client', 'vin', 'plate', 'brand', 'model', 'year', 'color', 'created_at' ), 'created_at' ),
			'order'     => $this->normalize_order( $request->get_param( 'order' ) ),
		);

		$items = $this->vehicle_service->get_vehicles( $args );
		$total = $this->vehicle_service->count_vehicles( $args );

		return array(
			'items'      => array_map( array( $this, 'map_vehicle_payload' ), $items ),
			'count'      => count( $items ),
			'total'      => absint( $total ),
			'page'       => $args['page'],
			'per_page'   => $args['per_page'],
			'total_pages'=> $this->calculate_total_pages( $total, $args['per_page'] ),
		);
	}

	/**
	 * Get admin vehicle detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_admin_vehicle( WP_REST_Request $request ) {
		$vehicle = $this->vehicle_service->get_vehicle( absint( $request->get_param( 'id' ) ) );

		if ( empty( $vehicle ) ) {
			return $this->not_found_error( __( 'El vehículo no existe.', 'super-mechanic' ) );
		}

		return array(
			'item' => $this->map_vehicle_payload( $vehicle ),
		);
	}

	/**
	 * Get admin clients.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function get_admin_clients( WP_REST_Request $request ) {
		$args = array(
			'per_page' => $this->normalize_per_page( $request->get_param( 'per_page' ) ),
			'page'     => $this->normalize_page( $request->get_param( 'page' ) ),
			'search'   => $this->normalize_search( $request->get_param( 'search' ) ),
			'status'   => $this->normalize_key_filter( $request->get_param( 'status' ), array( 'active', 'inactive' ) ),
			'date_from'=> $this->normalize_date( $request->get_param( 'date_from' ) ),
			'date_to'  => $this->normalize_date( $request->get_param( 'date_to' ) ),
			'orderby'  => $this->normalize_orderby( $request->get_param( 'orderby' ), array( 'id', 'first_name', 'last_name', 'email', 'phone', 'document_id', 'created_at' ), 'created_at' ),
			'order'    => $this->normalize_order( $request->get_param( 'order' ) ),
		);

		$items = $this->client_service->get_clients( $args );
		$total = $this->client_service->count_clients( $args );

		return array(
			'items'      => array_map( array( $this, 'map_client_payload' ), $items ),
			'count'      => count( $items ),
			'total'      => absint( $total ),
			'page'       => $args['page'],
			'per_page'   => $args['per_page'],
			'total_pages'=> $this->calculate_total_pages( $total, $args['per_page'] ),
		);
	}

	/**
	 * Get admin client detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_admin_client( WP_REST_Request $request ) {
		$client = $this->client_service->get_client( absint( $request->get_param( 'id' ) ) );

		if ( empty( $client ) ) {
			return $this->not_found_error( __( 'El cliente no existe.', 'super-mechanic' ) );
		}

		return array(
			'item' => $this->map_client_payload( $client ),
		);
	}

	/**
	 * Get admin quotes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function get_admin_quotes( WP_REST_Request $request ) {
		$args = array(
			'per_page'   => $this->normalize_per_page( $request->get_param( 'per_page' ) ),
			'page'       => $this->normalize_page( $request->get_param( 'page' ) ),
			'search'     => $this->normalize_search( $request->get_param( 'search' ) ),
			'process_id' => absint( $request->get_param( 'process_id' ) ),
			'client_id'  => absint( $request->get_param( 'client_id' ) ),
			'process_type' => $this->normalize_key_filter( $request->get_param( 'type' ), array( 'maintenance', 'pre_delivery', 'paperwork' ) ),
			'status'     => $this->normalize_key_filter( $request->get_param( 'status' ), array( 'draft', 'sent', 'approved', 'rejected', 'expired', 'cancelled' ) ),
			'date_from'  => $this->normalize_date( $request->get_param( 'date_from' ) ),
			'date_to'    => $this->normalize_date( $request->get_param( 'date_to' ) ),
			'orderby'    => $this->normalize_orderby( $request->get_param( 'orderby' ), array( 'id', 'quote_number', 'status', 'grand_total', 'created_at', 'updated_at' ), 'created_at' ),
			'order'      => $this->normalize_order( $request->get_param( 'order' ) ),
		);

		$items = $this->quote_service->get_quotes( $args );
		$total = $this->quote_service->count_quotes( $args );

		return array(
			'items'      => array_map( array( $this, 'map_quote_payload' ), $items ),
			'count'      => count( $items ),
			'total'      => absint( $total ),
			'page'       => $args['page'],
			'per_page'   => $args['per_page'],
			'total_pages'=> $this->calculate_total_pages( $total, $args['per_page'] ),
		);
	}

	/**
	 * Get admin quote detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_admin_quote( WP_REST_Request $request ) {
		$quote = $this->quote_service->get_quote( absint( $request->get_param( 'id' ) ) );

		if ( empty( $quote ) ) {
			return $this->not_found_error( __( 'La cotización no existe.', 'super-mechanic' ) );
		}

		return array(
			'item' => $this->map_quote_payload( $quote ),
		);
	}

	/**
	 * Get admin invoices.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function get_admin_invoices( WP_REST_Request $request ) {
		$args = array(
			'per_page'   => $this->normalize_per_page( $request->get_param( 'per_page' ) ),
			'page'       => $this->normalize_page( $request->get_param( 'page' ) ),
			'search'     => $this->normalize_search( $request->get_param( 'search' ) ),
			'process_id' => absint( $request->get_param( 'process_id' ) ),
			'client_id'  => absint( $request->get_param( 'client_id' ) ),
			'process_type' => $this->normalize_key_filter( $request->get_param( 'type' ), array( 'maintenance', 'pre_delivery', 'paperwork' ) ),
			'status'     => $this->normalize_key_filter( $request->get_param( 'status' ), array( 'draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled', 'refunded' ) ),
			'date_from'  => $this->normalize_date( $request->get_param( 'date_from' ) ),
			'date_to'    => $this->normalize_date( $request->get_param( 'date_to' ) ),
			'orderby'    => $this->normalize_orderby( $request->get_param( 'orderby' ), array( 'id', 'invoice_number', 'status', 'grand_total', 'balance_due', 'created_at', 'updated_at', 'due_date' ), 'created_at' ),
			'order'      => $this->normalize_order( $request->get_param( 'order' ) ),
		);

		$items = $this->invoice_service->get_invoices( $args );
		$total = $this->invoice_service->count_invoices( $args );

		$payload = array();
		foreach ( $items as $invoice ) {
			$payload[] = $this->map_invoice_payload( $this->invoice_service->append_collection_state( $invoice ) );
		}

		return array(
			'items'      => $payload,
			'count'      => count( $payload ),
			'total'      => absint( $total ),
			'page'       => $args['page'],
			'per_page'   => $args['per_page'],
			'total_pages'=> $this->calculate_total_pages( $total, $args['per_page'] ),
		);
	}

	/**
	 * Get admin invoice detail.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_admin_invoice( WP_REST_Request $request ) {
		$invoice = $this->invoice_service->get_invoice( absint( $request->get_param( 'id' ) ) );

		if ( empty( $invoice ) ) {
			return $this->not_found_error( __( 'La factura no existe.', 'super-mechanic' ) );
		}

		return array(
			'item' => $this->map_invoice_payload( $this->invoice_service->append_collection_state( $invoice ) ),
		);
	}

	/**
	 * Get common collection route args.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_common_collection_args() {
		return array(
			'per_page' => $this->get_per_page_arg(),
			'page'     => $this->get_page_arg(),
			'search'   => $this->get_search_arg(),
			'date_from'=> $this->get_date_arg(),
			'date_to'  => $this->get_date_arg(),
			'orderby'  => $this->get_orderby_arg(),
			'order'    => $this->get_order_arg(),
		);
	}

	/**
	 * Get process collection route args.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_process_collection_args() {
		$args = $this->get_common_collection_args();
		$args['vehicle_id']   = $this->get_id_arg();
		$args['client_id']    = $this->get_id_arg();
		$args['process_type'] = $this->get_key_arg();
		$args['status']       = $this->get_key_arg();

		return $args;
	}

	/**
	 * Get args for process status write action.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_process_status_write_args() {
		return array(
			'status' => array(
				'description'       => __( 'Nuevo estado del proceso.', 'super-mechanic' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => function ( $value ) {
					return in_array( sanitize_key( (string) $value ), $this->get_process_status_values(), true );
				},
			),
		);
	}

	/**
	 * Get args for internal process comment write action.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_process_internal_comment_args() {
		return array(
			'content'      => array(
				'description'       => __( 'Contenido del comentario interno.', 'super-mechanic' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => static function ( $value ) {
					return '' !== trim( (string) $value );
				},
			),
			'comment_type' => array(
				'description'       => __( 'Tipo de comentario interno.', 'super-mechanic' ),
				'type'              => 'string',
				'default'           => 'internal_note',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => function ( $value ) {
					return in_array( sanitize_key( (string) $value ), $this->get_internal_comment_type_values(), true );
				},
			),
		);
	}

	/**
	 * Get vehicle collection route args.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_vehicle_collection_args() {
		$args = $this->get_common_collection_args();
		$args['client_id'] = $this->get_id_arg();
		$args['status']    = $this->get_key_arg();
		$args['type']      = $this->get_key_arg();

		return $args;
	}

	/**
	 * Get client collection route args.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_client_collection_args() {
		$args = $this->get_common_collection_args();
		$args['status'] = $this->get_key_arg();

		return $args;
	}

	/**
	 * Get quote collection route args.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_quote_collection_args() {
		$args = $this->get_common_collection_args();
		$args['process_id'] = $this->get_id_arg();
		$args['client_id']  = $this->get_id_arg();
		$args['status']     = $this->get_key_arg();
		$args['type']       = $this->get_key_arg();

		return $args;
	}

	/**
	 * Get invoice collection route args.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_invoice_collection_args() {
		$args = $this->get_common_collection_args();
		$args['process_id'] = $this->get_id_arg();
		$args['client_id']  = $this->get_id_arg();
		$args['status']     = $this->get_key_arg();
		$args['type']       = $this->get_key_arg();

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
	 * Build numeric ID route arg config.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_id_arg() {
		return array(
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * Build key-like route arg config.
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
	 * Map process payload.
	 *
	 * @param array<string, mixed> $process Process row.
	 * @return array<string, mixed>
	 */
	protected function map_process_payload( array $process ) {
		return array(
			'id'            => absint( $process['id'] ),
			'client_id'     => absint( $process['client_id'] ),
			'vehicle_id'    => absint( $process['vehicle_id'] ),
			'title'         => (string) $process['title'],
			'process_type'  => (string) $process['process_type'],
			'status'        => (string) $process['status'],
			'priority'      => isset( $process['priority'] ) ? (string) $process['priority'] : '',
			'opened_at'     => isset( $process['opened_at'] ) ? (string) $process['opened_at'] : '',
			'due_date'      => isset( $process['due_date'] ) ? (string) $process['due_date'] : '',
			'completed_at'  => isset( $process['completed_at'] ) ? (string) $process['completed_at'] : '',
			'created_at'    => isset( $process['created_at'] ) ? (string) $process['created_at'] : '',
			'updated_at'    => isset( $process['updated_at'] ) ? (string) $process['updated_at'] : '',
			'client_name'   => isset( $process['client_name'] ) ? (string) $process['client_name'] : '',
			'vehicle_make'  => isset( $process['vehicle_make'] ) ? (string) $process['vehicle_make'] : '',
			'vehicle_model' => isset( $process['vehicle_model'] ) ? (string) $process['vehicle_model'] : '',
			'vehicle_plate' => isset( $process['vehicle_plate'] ) ? (string) $process['vehicle_plate'] : '',
			'vehicle_vin'   => isset( $process['vehicle_vin'] ) ? (string) $process['vehicle_vin'] : '',
		);
	}

	/**
	 * Map internal comment payload.
	 *
	 * @param array<string, mixed>|null $comment Comment row.
	 * @return array<string, mixed>
	 */
	protected function map_internal_comment_payload( $comment ) {
		if ( empty( $comment ) || ! is_array( $comment ) ) {
			return array();
		}

		return array(
			'id'                => absint( $comment['id'] ),
			'process_id'        => absint( $comment['process_id'] ),
			'object_id'         => absint( $comment['object_id'] ),
			'object_type'       => isset( $comment['object_type'] ) ? (string) $comment['object_type'] : 'process',
			'comment_type'      => isset( $comment['comment_type'] ) ? (string) $comment['comment_type'] : 'internal_note',
			'content'           => isset( $comment['content'] ) ? (string) $comment['content'] : '',
			'is_internal'       => 1,
			'is_client_visible' => 0,
			'status'            => isset( $comment['status'] ) ? (string) $comment['status'] : 'published',
			'author_user_id'    => isset( $comment['author_user_id'] ) ? absint( $comment['author_user_id'] ) : 0,
			'created_at'        => isset( $comment['created_at'] ) ? (string) $comment['created_at'] : '',
			'updated_at'        => isset( $comment['updated_at'] ) ? (string) $comment['updated_at'] : '',
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
			'id'          => absint( $vehicle['id'] ),
			'client_id'   => absint( $vehicle['client_id'] ),
			'type'        => isset( $vehicle['type'] ) ? (string) $vehicle['type'] : '',
			'make'        => isset( $vehicle['make'] ) ? (string) $vehicle['make'] : '',
			'brand'       => isset( $vehicle['brand'] ) ? (string) $vehicle['brand'] : ( isset( $vehicle['make'] ) ? (string) $vehicle['make'] : '' ),
			'model'       => isset( $vehicle['model'] ) ? (string) $vehicle['model'] : '',
			'year'        => isset( $vehicle['year'] ) ? absint( $vehicle['year'] ) : 0,
			'vin'         => isset( $vehicle['vin'] ) ? (string) $vehicle['vin'] : '',
			'plate'       => isset( $vehicle['plate'] ) ? (string) $vehicle['plate'] : '',
			'color'       => isset( $vehicle['color'] ) ? (string) $vehicle['color'] : '',
			'mileage'     => isset( $vehicle['mileage'] ) ? absint( $vehicle['mileage'] ) : 0,
			'status'      => isset( $vehicle['status'] ) ? (string) $vehicle['status'] : '',
			'created_at'  => isset( $vehicle['created_at'] ) ? (string) $vehicle['created_at'] : '',
			'updated_at'  => isset( $vehicle['updated_at'] ) ? (string) $vehicle['updated_at'] : '',
			'client_name' => isset( $vehicle['client_name'] ) ? (string) $vehicle['client_name'] : '',
		);
	}

	/**
	 * Map client payload.
	 *
	 * @param array<string, mixed> $client Client row.
	 * @return array<string, mixed>
	 */
	protected function map_client_payload( array $client ) {
		return array(
			'id'          => absint( $client['id'] ),
			'first_name'  => isset( $client['first_name'] ) ? (string) $client['first_name'] : '',
			'last_name'   => isset( $client['last_name'] ) ? (string) $client['last_name'] : '',
			'email'       => isset( $client['email'] ) ? (string) $client['email'] : '',
			'phone'       => isset( $client['phone'] ) ? (string) $client['phone'] : '',
			'document_id' => isset( $client['document_id'] ) ? (string) $client['document_id'] : '',
			'status'      => isset( $client['status'] ) ? (string) $client['status'] : '',
			'created_at'  => isset( $client['created_at'] ) ? (string) $client['created_at'] : '',
			'updated_at'  => isset( $client['updated_at'] ) ? (string) $client['updated_at'] : '',
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
			'id'                => absint( $invoice['id'] ),
			'process_id'        => absint( $invoice['process_id'] ),
			'quote_id'          => absint( $invoice['quote_id'] ),
			'client_id'         => absint( $invoice['client_id'] ),
			'invoice_number'    => (string) $invoice['invoice_number'],
			'status'            => (string) $invoice['status'],
			'currency'          => (string) $invoice['currency'],
			'subtotal'          => (float) $invoice['subtotal'],
			'tax_total'         => (float) $invoice['tax_total'],
			'discount_total'    => (float) $invoice['discount_total'],
			'grand_total'       => (float) $invoice['grand_total'],
			'amount_paid'       => (float) $invoice['amount_paid'],
			'balance_due'       => (float) $invoice['balance_due'],
			'issued_at'         => isset( $invoice['issued_at'] ) ? (string) $invoice['issued_at'] : '',
			'due_date'          => isset( $invoice['due_date'] ) ? (string) $invoice['due_date'] : '',
			'paid_at'           => isset( $invoice['paid_at'] ) ? (string) $invoice['paid_at'] : '',
			'created_at'        => isset( $invoice['created_at'] ) ? (string) $invoice['created_at'] : '',
			'updated_at'        => isset( $invoice['updated_at'] ) ? (string) $invoice['updated_at'] : '',
			'process_title'     => isset( $invoice['process_title'] ) ? (string) $invoice['process_title'] : '',
			'collection_status' => isset( $invoice['collection_status'] ) ? (string) $invoice['collection_status'] : '',
			'collection_label'  => isset( $invoice['collection_label'] ) ? (string) $invoice['collection_label'] : '',
			'payment_status'    => isset( $invoice['payment_status'] ) ? (string) $invoice['payment_status'] : '',
			'payment_label'     => isset( $invoice['payment_label'] ) ? (string) $invoice['payment_label'] : '',
			'client_name'       => isset( $invoice['client_name'] ) ? (string) $invoice['client_name'] : '',
			'vehicle_make'      => isset( $invoice['vehicle_make'] ) ? (string) $invoice['vehicle_make'] : '',
			'vehicle_model'     => isset( $invoice['vehicle_model'] ) ? (string) $invoice['vehicle_model'] : '',
			'vehicle_plate'     => isset( $invoice['vehicle_plate'] ) ? (string) $invoice['vehicle_plate'] : '',
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
	 * Normalize search text.
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
	 * @param mixed          $value   Raw value.
	 * @param array<int, string> $allowed Allowed values.
	 * @param string         $default Default value.
	 * @return string
	 */
	protected function normalize_orderby( $value, array $allowed, $default ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Normalize key filter against an allowed list.
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
	 * Get allowed process status values.
	 *
	 * @return array<int, string>
	 */
	protected function get_process_status_values() {
		return array( 'draft', 'pending', 'in_progress', 'waiting_approval', 'waiting_parts', 'completed', 'delivered', 'cancelled' );
	}

	/**
	 * Get allowed internal comment type values.
	 *
	 * @return array<int, string>
	 */
	protected function get_internal_comment_type_values() {
		return array( 'internal_note', 'staff_reply', 'system_note' );
	}

	/**
	 * Normalize a nullable ID.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	protected function normalize_nullable_id( $value ) {
		$id = absint( $value );

		return $id > 0 ? $id : null;
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
	 * Build a not-found error.
	 *
	 * @param string $message Error message.
	 * @return \WP_Error
	 */
	protected function not_found_error( $message ) {
		return new \WP_Error( 'sm_rest_not_found', $message, array( 'status' => 404 ) );
	}

	/**
	 * Convert service layer errors into REST errors.
	 *
	 * @param \WP_Error $error Service error.
	 * @param int       $default_status Fallback status.
	 * @return \WP_Error
	 */
	protected function as_rest_error( \WP_Error $error, $default_status = 400 ) {
		$status = absint( $default_status );
		$data   = $error->get_error_data();

		if ( is_array( $data ) && isset( $data['status'] ) ) {
			$status = absint( $data['status'] );
		}

		if ( $status < 100 ) {
			$status = 400;
		}

		return new \WP_Error(
			$error->get_error_code(),
			$error->get_error_message(),
			array( 'status' => $status )
		);
	}

	/**
	 * Calculate total pages.
	 *
	 * @param int $total Total rows.
	 * @param int $per_page Page size.
	 * @return int
	 */
	protected function calculate_total_pages( $total, $per_page ) {
		$total    = max( 0, absint( $total ) );
		$per_page = max( 1, absint( $per_page ) );

		return (int) ceil( $total / $per_page );
	}
}
