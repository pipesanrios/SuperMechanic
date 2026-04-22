<?php
/**
 * Client dashboard shortcodes.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Helpers\Permission_Service;
use Super_Mechanic\Portal\Client_Portal_Controller;

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
	protected $permission_service;
	protected $client_portal_controller;

	/**
	 * Constructor.
	 *
	 * @param Client_Dashboard_Controller|null $client_dashboard_controller Client dashboard controller.
	 * @param Dashboard_Service|null           $service                     Dashboard service.
	 * @param Permission_Service|null          $permission_service          Permission service.
	 * @param Client_Portal_Controller|null    $client_portal_controller    Enhanced portal controller.
	 */
	public function __construct( Client_Dashboard_Controller $client_dashboard_controller = null, Dashboard_Service $service = null, Permission_Service $permission_service = null, Client_Portal_Controller $client_portal_controller = null ) {
		$this->client_dashboard_controller = $client_dashboard_controller ? $client_dashboard_controller : new Client_Dashboard_Controller();
		$this->service                     = $service ? $service : new Dashboard_Service();
		$this->permission_service          = $permission_service ? $permission_service : new Permission_Service();
		$this->client_portal_controller    = $client_portal_controller ? $client_portal_controller : new Client_Portal_Controller();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'sm_client_dashboard', array( $this, 'render_client_dashboard' ) );
		add_shortcode( 'sm_client_panel', array( $this, 'render_client_panel' ) );
		add_shortcode( 'mekvort_client_panel', array( $this, 'render_mekvort_client_panel' ) );
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
		$permission = $this->permission_service->user_can_access_client_portal( get_current_user_id() );

		if ( is_wp_error( $permission ) ) {
			return $this->permission_service->get_error_message( $permission );
		}

		return $this->client_portal_controller->render_portal( get_current_user_id() );
	}

	/**
	 * Render unified client panel shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_client_panel( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'show_overview'  => 'yes',
				'show_documents' => 'yes',
			),
			$atts,
			'sm_client_panel'
		);

		$permission = $this->permission_service->user_can_access_client_portal( get_current_user_id() );
		if ( is_wp_error( $permission ) ) {
			return $this->permission_service->get_error_message( $permission );
		}

		$user_id        = get_current_user_id();
		$show_overview  = 'no' !== sanitize_key( (string) $atts['show_overview'] );
		$show_documents = 'no' !== sanitize_key( (string) $atts['show_documents'] );

		ob_start();
		echo '<div class="sm-client-panel-base">';
		echo '<div class="sm-client-panel-header">';
		echo '<h2>' . esc_html__( 'My client panel', 'super-mechanic' ) . '</h2>';
		echo '<p>' . esc_html__( 'Access your process status, commercial documents and communication from one private panel.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<nav class="sm-client-panel-nav" aria-label="' . esc_attr__( 'Client panel sections', 'super-mechanic' ) . '">';
		echo '<a href="#sm-client-panel-dashboard">' . esc_html__( 'Dashboard', 'super-mechanic' ) . '</a>';
		echo '<a href="#sm-client-panel-vehicles">' . esc_html__( 'Vehicles', 'super-mechanic' ) . '</a>';
		echo '<a href="#sm-client-panel-processes">' . esc_html__( 'Processes', 'super-mechanic' ) . '</a>';
		echo '<a href="#sm-client-panel-quotes">' . esc_html__( 'Quotes', 'super-mechanic' ) . '</a>';
		echo '<a href="#sm-client-panel-invoices">' . esc_html__( 'Invoices', 'super-mechanic' ) . '</a>';
		if ( $show_documents ) {
			echo '<a href="#sm-client-panel-documents">' . esc_html__( 'Documents', 'super-mechanic' ) . '</a>';
		}
		echo '</nav>';

		echo '<section id="sm-client-panel-dashboard" class="sm-client-panel-section">';
		echo '<h3>' . esc_html__( 'Dashboard', 'super-mechanic' ) . '</h3>';
		echo $show_overview ? $this->client_portal_controller->render_portal( $user_id ) : $this->client_dashboard_controller->render_dashboard( $user_id );
		echo '</section>';

		echo '<section id="sm-client-panel-vehicles" class="sm-client-panel-section">';
		echo '<h3>' . esc_html__( 'Vehicles', 'super-mechanic' ) . '</h3>';
		echo $this->client_dashboard_controller->render_vehicles( $user_id );
		echo '</section>';

		echo '<section id="sm-client-panel-processes" class="sm-client-panel-section">';
		echo '<h3>' . esc_html__( 'Processes', 'super-mechanic' ) . '</h3>';
		echo $this->client_dashboard_controller->render_processes( $user_id );
		echo '</section>';

		echo '<section id="sm-client-panel-quotes" class="sm-client-panel-section">';
		echo '<h3>' . esc_html__( 'Quotes', 'super-mechanic' ) . '</h3>';
		echo $this->client_dashboard_controller->render_quotes( $user_id );
		echo '</section>';

		echo '<section id="sm-client-panel-invoices" class="sm-client-panel-section">';
		echo '<h3>' . esc_html__( 'Invoices', 'super-mechanic' ) . '</h3>';
		echo $this->client_dashboard_controller->render_invoices( $user_id );
		echo '</section>';

		if ( $show_documents ) {
			echo '<section id="sm-client-panel-documents" class="sm-client-panel-section">';
			echo '<h3>' . esc_html__( 'Documents', 'super-mechanic' ) . '</h3>';
			echo '<p class="sm-client-panel-note">' . esc_html__( 'Use process links from Dashboard or Processes to set process context and see related documents/timeline below.', 'super-mechanic' ) . '</p>';
			echo do_shortcode( '[sm_client_process_documents]' );
			echo do_shortcode( '[sm_client_process_timeline]' );
			echo '</section>';
		}

		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Render Mekvort client panel shortcode with auth redirect.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_mekvort_client_panel( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			$redirect_url = home_url( '/my-account/' );
			if ( ! headers_sent() ) {
				wp_safe_redirect( $redirect_url );
				exit;
			}

			return '<script>window.location.href="' . esc_js( $redirect_url ) . '";</script>';
		}

		return $this->render_client_panel( $atts );
	}

	/**
	 * Render client vehicles shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_client_vehicles( $atts = array() ) {
		$atts = shortcode_atts( array(), $atts, 'sm_client_vehicles' );
		$permission = $this->permission_service->user_can_access_client_portal( get_current_user_id() );

		if ( is_wp_error( $permission ) ) {
			return $this->permission_service->get_error_message( $permission );
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
		$permission = $this->permission_service->user_can_access_client_portal( get_current_user_id() );

		if ( is_wp_error( $permission ) ) {
			return $this->permission_service->get_error_message( $permission );
		}

		return $this->client_dashboard_controller->render_processes( get_current_user_id() );
	}
}
