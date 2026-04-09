<?php
/**
 * Public API v1 controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\API\Controllers;

use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;
use Super_Mechanic\Reporting\Reporting_Service;
use Super_Mechanic\Vehicles\Vehicle_Service;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the formal versioned API namespace.
 */
class Public_API_Controller {
	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'sm/v1';

	/**
	 * Client service.
	 *
	 * @var Client_Service
	 */
	protected $client_service;

	/**
	 * Vehicle service.
	 *
	 * @var Vehicle_Service
	 */
	protected $vehicle_service;

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
	 * Reporting service.
	 *
	 * @var Reporting_Service
	 */
	protected $reporting_service;

	/**
	 * Quote service.
	 *
	 * @var Quote_Service
	 */
	protected $quote_service;

	/**
	 * Access control service.
	 *
	 * @var Access_Control_Service
	 */
	protected $access_control_service;

	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Client_Service|null           $client_service           Client service.
	 * @param Vehicle_Service|null          $vehicle_service          Vehicle service.
	 * @param Process_Service|null          $process_service          Process service.
	 * @param Invoice_Service|null          $invoice_service          Invoice service.
	 * @param Reporting_Service|null        $reporting_service        Reporting service.
	 * @param Quote_Service|null            $quote_service            Quote service.
	 * @param Access_Control_Service|null   $access_control_service   Access service.
	 * @param Business_Context_Service|null $business_context_service Business context service.
	 */
	public function __construct(
		Client_Service $client_service = null,
		Vehicle_Service $vehicle_service = null,
		Process_Service $process_service = null,
		Invoice_Service $invoice_service = null,
		Reporting_Service $reporting_service = null,
		Quote_Service $quote_service = null,
		Access_Control_Service $access_control_service = null,
		Business_Context_Service $business_context_service = null
	) {
		$this->client_service           = $client_service ? $client_service : new Client_Service();
		$this->vehicle_service          = $vehicle_service ? $vehicle_service : new Vehicle_Service();
		$this->process_service          = $process_service ? $process_service : new Process_Service();
		$this->invoice_service          = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->reporting_service        = $reporting_service ? $reporting_service : new Reporting_Service();
		$this->quote_service            = $quote_service ? $quote_service : new Quote_Service();
		$this->access_control_service   = $access_control_service ? $access_control_service : new Access_Control_Service();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
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
	 * Register API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/clients',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_clients' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/vehicles',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_vehicles' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_args( true ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/processes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_processes' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_args( false, true ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/processes/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_process' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'          => $this->get_id_arg(),
					'business_id' => $this->get_business_id_arg(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/invoices',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_invoices' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_args( false, true ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/reporting/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_reporting_summary' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'business_id' => $this->get_business_id_arg(),
					'range'       => $this->get_range_arg(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/quotes/(?P<id>\d+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve_quote' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'          => $this->get_id_arg(),
					'business_id' => $this->get_business_id_arg(),
				),
			)
		);
	}

	/**
	 * List clients.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_clients( WP_REST_Request $request ) {
		$user_id = $this->get_authenticated_user_id();
		if ( $user_id <= 0 ) {
			return $this->error_response( __( 'Authentication required.', 'super-mechanic' ), 401 );
		}

		$business_id = $this->resolve_business_scope( $request, $user_id );
		if ( is_wp_error( $business_id ) ) {
			return $this->error_response( $business_id->get_error_message(), 403 );
		}

		$items = array();
		if ( $this->access_control_service->user_has_full_access( $user_id ) ) {
			$args  = $this->build_collection_query_args( $request, $business_id );
			$items = $this->client_service->get_clients( $args );
		} else {
			$client_id = absint( get_user_meta( $user_id, 'sm_client_id', true ) );
			if ( $client_id > 0 ) {
				$client = $this->client_service->get_client( $client_id );
				if ( is_array( $client ) && absint( $client['business_id'] ) === $business_id ) {
					$items[] = $client;
				}
			}
		}

		return $this->success_response(
			$items,
			$this->build_collection_meta( $request, $business_id, count( $items ) )
		);
	}

	/**
	 * List vehicles.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_vehicles( WP_REST_Request $request ) {
		$user_id = $this->get_authenticated_user_id();
		if ( $user_id <= 0 ) {
			return $this->error_response( __( 'Authentication required.', 'super-mechanic' ), 401 );
		}

		$business_id = $this->resolve_business_scope( $request, $user_id );
		if ( is_wp_error( $business_id ) ) {
			return $this->error_response( $business_id->get_error_message(), 403 );
		}

		$args  = $this->build_collection_query_args( $request, $business_id, true );
		$items = $this->vehicle_service->get_vehicles( $args );

		if ( ! $this->access_control_service->user_has_full_access( $user_id ) ) {
			$items = array_values(
				array_filter(
					$items,
					function ( $item ) use ( $user_id ) {
						return ! empty( $item['id'] ) && $this->access_control_service->user_can_access_vehicle( $user_id, absint( $item['id'] ) );
					}
				)
			);
		}

		return $this->success_response(
			$items,
			$this->build_collection_meta( $request, $business_id, count( $items ) )
		);
	}

	/**
	 * List processes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_processes( WP_REST_Request $request ) {
		$user_id = $this->get_authenticated_user_id();
		if ( $user_id <= 0 ) {
			return $this->error_response( __( 'Authentication required.', 'super-mechanic' ), 401 );
		}

		$business_id = $this->resolve_business_scope( $request, $user_id );
		if ( is_wp_error( $business_id ) ) {
			return $this->error_response( $business_id->get_error_message(), 403 );
		}

		$args  = $this->build_collection_query_args( $request, $business_id, false, true );
		$items = $this->process_service->get_processes( $args );

		if ( ! $this->access_control_service->user_has_full_access( $user_id ) ) {
			$items = $this->access_control_service->filter_processes_for_user( $user_id, $items );
		}

		return $this->success_response(
			$items,
			$this->build_collection_meta( $request, $business_id, count( $items ) )
		);
	}

	/**
	 * Get one process.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_process( WP_REST_Request $request ) {
		$user_id = $this->get_authenticated_user_id();
		if ( $user_id <= 0 ) {
			return $this->error_response( __( 'Authentication required.', 'super-mechanic' ), 401 );
		}

		$business_id = $this->resolve_business_scope( $request, $user_id );
		if ( is_wp_error( $business_id ) ) {
			return $this->error_response( $business_id->get_error_message(), 403 );
		}

		$process_id = absint( $request->get_param( 'id' ) );
		$process    = $this->process_service->get_process( $process_id );

		if ( ! is_array( $process ) ) {
			return $this->error_response( __( 'Process not found.', 'super-mechanic' ), 404 );
		}

		if ( ! empty( $process['business_id'] ) && absint( $process['business_id'] ) !== $business_id ) {
			return $this->error_response( __( 'Process not found in current business scope.', 'super-mechanic' ), 404 );
		}

		if ( ! $this->process_service->user_can_access_process( $user_id, $process_id ) ) {
			return $this->error_response( __( 'You do not have access to this process.', 'super-mechanic' ), 403 );
		}

		return $this->success_response(
			$process,
			array(
				'business_id' => $business_id,
			)
		);
	}

	/**
	 * List invoices.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_invoices( WP_REST_Request $request ) {
		$user_id = $this->get_authenticated_user_id();
		if ( $user_id <= 0 ) {
			return $this->error_response( __( 'Authentication required.', 'super-mechanic' ), 401 );
		}

		$business_id = $this->resolve_business_scope( $request, $user_id );
		if ( is_wp_error( $business_id ) ) {
			return $this->error_response( $business_id->get_error_message(), 403 );
		}

		$args = $this->build_collection_query_args( $request, $business_id, false, true );

		if ( $this->access_control_service->user_has_full_access( $user_id ) ) {
			$items = $this->invoice_service->get_invoices( $args );
		} else {
			$items = $this->invoice_service->get_invoices_for_user( $user_id, $args );
		}

		return $this->success_response(
			$items,
			$this->build_collection_meta( $request, $business_id, count( $items ) )
		);
	}

	/**
	 * Get reporting summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_reporting_summary( WP_REST_Request $request ) {
		$user_id = $this->get_authenticated_user_id();
		if ( $user_id <= 0 ) {
			return $this->error_response( __( 'Authentication required.', 'super-mechanic' ), 401 );
		}

		$business_id = $this->resolve_business_scope( $request, $user_id );
		if ( is_wp_error( $business_id ) ) {
			return $this->error_response( $business_id->get_error_message(), 403 );
		}

		$range = sanitize_key( (string) $request->get_param( 'range' ) );
		if ( '' === $range ) {
			$range = '30d';
		}

		$summary = $this->reporting_service->get_reporting_summary( $business_id, $range );

		return $this->success_response(
			$summary,
			array(
				'business_id' => $business_id,
				'range'       => $range,
			)
		);
	}

	/**
	 * Approve one quote (optional endpoint).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function approve_quote( WP_REST_Request $request ) {
		$user_id = $this->get_authenticated_user_id();
		if ( $user_id <= 0 ) {
			return $this->error_response( __( 'Authentication required.', 'super-mechanic' ), 401 );
		}

		$business_id = $this->resolve_business_scope( $request, $user_id );
		if ( is_wp_error( $business_id ) ) {
			return $this->error_response( $business_id->get_error_message(), 403 );
		}

		$quote_id = absint( $request->get_param( 'id' ) );
		if ( ! $this->quote_service->user_can_access_quote( $user_id, $quote_id ) ) {
			return $this->error_response( __( 'You do not have access to this quote.', 'super-mechanic' ), 403 );
		}

		$result = $this->quote_service->approve_quote( $quote_id, $user_id );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result->get_error_message(), 400 );
		}

		return $this->success_response(
			array(
				'quote_id' => $quote_id,
				'status'   => 'approved',
			),
			array(
				'business_id' => $business_id,
			)
		);
	}

	/**
	 * Build collection route args.
	 *
	 * @param bool $include_vehicle_type Include vehicle type filter.
	 * @param bool $include_process_type Include process type filter.
	 * @return array<string,mixed>
	 */
	protected function get_collection_args( $include_vehicle_type = false, $include_process_type = false ) {
		$args = array(
			'business_id' => $this->get_business_id_arg(),
			'page'        => $this->get_page_arg(),
			'per_page'    => $this->get_per_page_arg(),
			'search'      => $this->get_search_arg(),
			'status'      => $this->get_status_arg(),
			'orderby'     => $this->get_orderby_arg(),
			'order'       => $this->get_order_arg(),
		);

		if ( $include_vehicle_type ) {
			$args['type'] = $this->get_type_arg();
		}

		if ( $include_process_type ) {
			$args['process_type'] = $this->get_process_type_arg();
		}

		return $args;
	}

	/**
	 * Build collection query args.
	 *
	 * @param WP_REST_Request $request              Request.
	 * @param int             $business_id          Business scope.
	 * @param bool            $include_vehicle_type Include type.
	 * @param bool            $include_process_type Include process type.
	 * @return array<string,mixed>
	 */
	protected function build_collection_query_args( WP_REST_Request $request, $business_id, $include_vehicle_type = false, $include_process_type = false ) {
		$args = array(
			'business_id' => $business_id,
			'page'        => absint( $request->get_param( 'page' ) ),
			'per_page'    => absint( $request->get_param( 'per_page' ) ),
			'search'      => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'status'      => sanitize_key( (string) $request->get_param( 'status' ) ),
			'orderby'     => sanitize_key( (string) $request->get_param( 'orderby' ) ),
			'order'       => 'ASC' === strtoupper( (string) $request->get_param( 'order' ) ) ? 'ASC' : 'DESC',
		);

		if ( $args['page'] <= 0 ) {
			$args['page'] = 1;
		}

		if ( $args['per_page'] <= 0 ) {
			$args['per_page'] = 20;
		}

		if ( $include_vehicle_type ) {
			$args['type'] = sanitize_key( (string) $request->get_param( 'type' ) );
		}

		if ( $include_process_type ) {
			$args['process_type'] = sanitize_key( (string) $request->get_param( 'process_type' ) );
		}

		return $args;
	}

	/**
	 * Resolve business scope and reject invalid tenant requests.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $user_id User ID.
	 * @return int|\WP_Error
	 */
	protected function resolve_business_scope( WP_REST_Request $request, $user_id ) {
		$user_id              = absint( $user_id );
		$requested_business_id = absint( $request->get_param( 'business_id' ) );
		$resolved_business_id  = absint( $this->business_context_service->resolve_business_id_for_user( $user_id, $requested_business_id ) );

		if ( $requested_business_id > 0 && $requested_business_id !== $resolved_business_id ) {
			return new \WP_Error( 'sm_api_business_scope_denied', __( 'Invalid business scope for current user.', 'super-mechanic' ) );
		}

		return $resolved_business_id;
	}

	/**
	 * Resolve authenticated user id.
	 *
	 * @return int
	 */
	protected function get_authenticated_user_id() {
		return absint( get_current_user_id() );
	}

	/**
	 * Build standard success response.
	 *
	 * @param mixed                $data   Payload.
	 * @param array<string,mixed>  $meta   Metadata.
	 * @param int                  $status HTTP status.
	 * @return WP_REST_Response
	 */
	protected function success_response( $data, array $meta = array(), $status = 200 ) {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
				'meta'    => $meta,
			),
			absint( $status )
		);
	}

	/**
	 * Build standard error response.
	 *
	 * @param string $message Error message.
	 * @param int    $status  HTTP status.
	 * @return WP_REST_Response
	 */
	protected function error_response( $message, $status = 400 ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => (string) $message,
			),
			absint( $status )
		);
	}

	/**
	 * Build collection metadata.
	 *
	 * @param WP_REST_Request $request     Request.
	 * @param int             $business_id Business ID.
	 * @param int             $count       Number of returned rows.
	 * @return array<string,mixed>
	 */
	protected function build_collection_meta( WP_REST_Request $request, $business_id, $count ) {
		return array(
			'business_id' => absint( $business_id ),
			'page'        => max( 1, absint( $request->get_param( 'page' ) ) ),
			'per_page'    => max( 1, absint( $request->get_param( 'per_page' ) ) ),
			'count'       => absint( $count ),
		);
	}

	/**
	 * Arg definition: business ID.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_business_id_arg() {
		return array(
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * Arg definition: identifier.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_id_arg() {
		return array(
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * Arg definition: page.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_page_arg() {
		return array(
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => static function ( $value ) {
				return absint( $value ) >= 1;
			},
		);
	}

	/**
	 * Arg definition: page size.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_per_page_arg() {
		return array(
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
	 * Arg definition: search text.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_search_arg() {
		return array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * Arg definition: status.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_status_arg() {
		return array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_key',
		);
	}

	/**
	 * Arg definition: order by.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_orderby_arg() {
		return array(
			'type'              => 'string',
			'default'           => 'created_at',
			'sanitize_callback' => 'sanitize_key',
		);
	}

	/**
	 * Arg definition: order direction.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_order_arg() {
		return array(
			'type'              => 'string',
			'default'           => 'DESC',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * Arg definition: vehicle type.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_type_arg() {
		return array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_key',
		);
	}

	/**
	 * Arg definition: process type.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_process_type_arg() {
		return array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_key',
		);
	}

	/**
	 * Arg definition: reporting range.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_range_arg() {
		return array(
			'type'              => 'string',
			'default'           => '30d',
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => static function ( $value ) {
				return in_array( sanitize_key( (string) $value ), array( '7d', '30d', '90d', 'all' ), true );
			},
		);
	}
}

