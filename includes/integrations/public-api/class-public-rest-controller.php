<?php
/**
 * Public REST controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Public_API;

use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes a minimal read-only public API surface.
 */
class Public_REST_Controller {
	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'super-mechanic-public/v1';

	/**
	 * Auth service.
	 *
	 * @var Public_API_Auth_Service
	 */
	protected $auth_service;

	/**
	 * Public API service.
	 *
	 * @var Public_API_Service
	 */
	protected $public_api_service;

	/**
	 * Constructor.
	 *
	 * @param Public_API_Auth_Service|null $auth_service       Auth service.
	 * @param Public_API_Service|null      $public_api_service Public API service.
	 */
	public function __construct( Public_API_Auth_Service $auth_service = null, Public_API_Service $public_api_service = null ) {
		$this->auth_service       = $auth_service ? $auth_service : new Public_API_Auth_Service();
		$this->public_api_service = $public_api_service ? $public_api_service : new Public_API_Service();
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
	 * Register public routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/business',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_business_summary' ),
				'permission_callback' => array( $this, 'check_business_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/processes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_processes' ),
				'permission_callback' => array( $this, 'check_processes_permission' ),
				'args'                => $this->get_process_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/appointments',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_appointments' ),
				'permission_callback' => array( $this, 'check_appointments_permission' ),
				'args'                => $this->get_appointment_collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/appointments/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_appointment' ),
				'permission_callback' => array( $this, 'check_appointments_cancel_permission' ),
				'args'                => $this->get_appointment_cancel_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/appointments/(?P<id>\d+)/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'confirm_appointment' ),
				'permission_callback' => array( $this, 'check_appointments_confirm_permission' ),
				'args'                => $this->get_appointment_confirm_args(),
			)
		);
	}

	/**
	 * Permission callback for business summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function check_business_permission( WP_REST_Request $request ) {
		return $this->authorize_request( $request, 'business:read' );
	}

	/**
	 * Permission callback for processes list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function check_processes_permission( WP_REST_Request $request ) {
		return $this->authorize_request( $request, 'processes:read' );
	}

	/**
	 * Permission callback for appointments list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function check_appointments_permission( WP_REST_Request $request ) {
		return $this->authorize_request( $request, 'appointments:read' );
	}

	/**
	 * Permission callback for appointment cancellation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function check_appointments_cancel_permission( WP_REST_Request $request ) {
		return $this->authorize_request( $request, 'appointments:cancel' );
	}

	/**
	 * Permission callback for appointment confirmation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function check_appointments_confirm_permission( WP_REST_Request $request ) {
		return $this->authorize_request( $request, 'appointments:confirm' );
	}

	/**
	 * Get business summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_business_summary( WP_REST_Request $request ) {
		$credential = $this->resolve_credential( $request, 'business:read' );
		if ( is_wp_error( $credential ) ) {
			return $credential;
		}

		$item = $this->public_api_service->get_business_summary( absint( $credential['business_id'] ) );
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		return array(
			'item' => $item,
		);
	}

	/**
	 * Get public processes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_processes( WP_REST_Request $request ) {
		$credential = $this->resolve_credential( $request, 'processes:read' );
		if ( is_wp_error( $credential ) ) {
			return $credential;
		}

		$args = array(
			'per_page'  => $request->get_param( 'per_page' ),
			'page'      => $request->get_param( 'page' ),
			'search'    => $request->get_param( 'search' ),
			'status'    => $request->get_param( 'status' ),
			'type'      => $request->get_param( 'type' ),
			'date_from' => $request->get_param( 'date_from' ),
			'date_to'   => $request->get_param( 'date_to' ),
			'orderby'   => $request->get_param( 'orderby' ),
			'order'     => $request->get_param( 'order' ),
		);

		return $this->public_api_service->list_processes( absint( $credential['business_id'] ), $args );
	}

	/**
	 * Get public appointments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_appointments( WP_REST_Request $request ) {
		$credential = $this->resolve_credential( $request, 'appointments:read' );
		if ( is_wp_error( $credential ) ) {
			return $credential;
		}

		$args = array(
			'per_page'    => $request->get_param( 'per_page' ),
			'page'        => $request->get_param( 'page' ),
			'search'      => $request->get_param( 'search' ),
			'status'      => $request->get_param( 'status' ),
			'assigned_to' => $request->get_param( 'assigned_to' ),
			'date_from'   => $request->get_param( 'date_from' ),
			'date_to'     => $request->get_param( 'date_to' ),
			'orderby'     => $request->get_param( 'orderby' ),
			'order'       => $request->get_param( 'order' ),
		);

		return $this->public_api_service->list_appointments( absint( $credential['business_id'] ), $args );
	}

	/**
	 * Cancel one public appointment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function cancel_appointment( WP_REST_Request $request ) {
		$credential = $this->resolve_credential( $request, 'appointments:cancel' );
		if ( is_wp_error( $credential ) ) {
			return $credential;
		}

		$idempotency_key = $request->get_param( 'idempotency_key' );
		if ( '' === (string) $idempotency_key ) {
			$idempotency_key = $request->get_header( 'x-idempotency-key' );
		}

		$args = array(
			'reason'          => $request->get_param( 'reason' ),
			'idempotency_key' => $idempotency_key,
		);

		return $this->public_api_service->cancel_public_appointment(
			absint( $credential['business_id'] ),
			absint( $request->get_param( 'id' ) ),
			$args
		);
	}

	/**
	 * Confirm one public appointment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function confirm_appointment( WP_REST_Request $request ) {
		$credential = $this->resolve_credential( $request, 'appointments:confirm' );
		if ( is_wp_error( $credential ) ) {
			return $credential;
		}

		$idempotency_key = $request->get_param( 'idempotency_key' );
		if ( '' === (string) $idempotency_key ) {
			$idempotency_key = $request->get_header( 'x-idempotency-key' );
		}

		$args = array(
			'reason'          => $request->get_param( 'reason' ),
			'idempotency_key' => $idempotency_key,
		);

		return $this->public_api_service->confirm_public_appointment(
			absint( $credential['business_id'] ),
			absint( $request->get_param( 'id' ) ),
			$args
		);
	}

	/**
	 * Authorize request and cache credential.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $scope   Scope.
	 * @return true|\WP_Error
	 */
	protected function authorize_request( WP_REST_Request $request, $scope ) {
		$credential = $this->auth_service->authenticate_request( $request, $scope, true );

		if ( is_wp_error( $credential ) ) {
			return $credential;
		}

		$request->set_param( '_sm_public_api_credential', $credential );

		return true;
	}

	/**
	 * Resolve credential from request context.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $scope   Scope.
	 * @return array<string,mixed>|\WP_Error
	 */
	protected function resolve_credential( WP_REST_Request $request, $scope ) {
		$cached = $request->get_param( '_sm_public_api_credential' );
		if ( is_array( $cached ) && ! empty( $cached['business_id'] ) ) {
			return $cached;
		}

		return $this->auth_service->authenticate_request( $request, $scope, false );
	}

	/**
	 * Get process list route args.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_process_collection_args() {
		$args           = $this->get_common_collection_args();
		$args['status'] = $this->get_key_arg();
		$args['type']   = $this->get_key_arg();

		return $args;
	}

	/**
	 * Get appointment list route args.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_appointment_collection_args() {
		$args                = $this->get_common_collection_args();
		$args['status']      = $this->get_key_arg();
		$args['assigned_to'] = $this->get_id_arg();

		return $args;
	}

	/**
	 * Get appointment cancel route args.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_appointment_cancel_args() {
		return array(
			'id'              => $this->get_id_arg(),
			'reason'          => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'idempotency_key' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get appointment confirm route args.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_appointment_confirm_args() {
		return array(
			'id'              => $this->get_id_arg(),
			'reason'          => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'idempotency_key' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get common list route args.
	 *
	 * @return array<string,mixed>
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
	 * Per-page arg definition.
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
	 * Page arg definition.
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
	 * Search arg definition.
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
	 * Orderby arg definition.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_orderby_arg() {
		return array(
			'type'              => 'string',
			'default'           => 'updated_at',
			'sanitize_callback' => 'sanitize_key',
		);
	}

	/**
	 * Order arg definition.
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
	 * Date arg definition.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_date_arg() {
		return array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * Numeric id arg definition.
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
	 * Key-like arg definition.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_key_arg() {
		return array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_key',
		);
	}
}
