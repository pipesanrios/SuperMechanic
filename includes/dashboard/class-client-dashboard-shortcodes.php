<?php
/**
 * Client dashboard shortcodes.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Registers client dashboard shortcodes.
 */
class Client_Dashboard_Shortcodes {
	/**
	 * Client dashboard controller.
	 *
	 * @var Client_Dashboard_Controller
	 */
	protected $client_dashboard_controller;

	/**
	 * Dashboard service.
	 *
	 * @var Dashboard_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Client_Dashboard_Controller|null $client_dashboard_controller Client dashboard controller.
	 * @param Dashboard_Service|null           $service                     Dashboard service.
	 */
	public function __construct( Client_Dashboard_Controller $client_dashboard_controller = null, Dashboard_Service $service = null ) {
		$this->client_dashboard_controller = $client_dashboard_controller ? $client_dashboard_controller : new Client_Dashboard_Controller();
		$this->service                     = $service ? $service : new Dashboard_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'sm_client_dashboard', array( $this, 'render_client_dashboard' ) );
		add_shortcode( 'sm_client_vehicles', array( $this, 'render_client_vehicles' ) );
		add_shortcode( 'sm_client_processes', array( $this, 'render_client_processes' ) );
	}

	/**
	 * Render client dashboard shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_client_dashboard( $atts = array() ) {
		$atts = shortcode_atts( array(), $atts, 'sm_client_dashboard' );

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Debe iniciar sesión para ver su panel.', 'super-mechanic' ) . '</p>';
		}

		if ( ! current_user_can( 'sm_view_own_vehicles' ) && ! current_user_can( 'sm_view_own_processes' ) ) {
			return '<p>' . esc_html__( 'No tiene permisos para ver este panel.', 'super-mechanic' ) . '</p>';
		}

		if ( ! $this->service->get_client_id_by_user_id( get_current_user_id() ) ) {
			return '<p>' . esc_html__( 'No hay un cliente vinculado a su usuario.', 'super-mechanic' ) . '</p>';
		}

		return $this->client_dashboard_controller->render_dashboard( get_current_user_id() );
	}

	/**
	 * Render client vehicles shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_client_vehicles( $atts = array() ) {
		$atts = shortcode_atts( array(), $atts, 'sm_client_vehicles' );

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Debe iniciar sesión para ver sus vehículos.', 'super-mechanic' ) . '</p>';
		}

		if ( ! current_user_can( 'sm_view_own_vehicles' ) ) {
			return '<p>' . esc_html__( 'No tiene permisos para ver sus vehículos.', 'super-mechanic' ) . '</p>';
		}

		if ( ! $this->service->get_client_id_by_user_id( get_current_user_id() ) ) {
			return '<p>' . esc_html__( 'No hay un cliente vinculado a su usuario.', 'super-mechanic' ) . '</p>';
		}

		return $this->client_dashboard_controller->render_vehicles( get_current_user_id() );
	}

	/**
	 * Render client processes shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_client_processes( $atts = array() ) {
		$atts = shortcode_atts( array(), $atts, 'sm_client_processes' );

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Debe iniciar sesión para ver sus procesos.', 'super-mechanic' ) . '</p>';
		}

		if ( ! current_user_can( 'sm_view_own_processes' ) ) {
			return '<p>' . esc_html__( 'No tiene permisos para ver sus procesos.', 'super-mechanic' ) . '</p>';
		}

		if ( ! $this->service->get_client_id_by_user_id( get_current_user_id() ) ) {
			return '<p>' . esc_html__( 'No hay un cliente vinculado a su usuario.', 'super-mechanic' ) . '</p>';
		}

		return $this->client_dashboard_controller->render_processes( get_current_user_id() );
	}
}
