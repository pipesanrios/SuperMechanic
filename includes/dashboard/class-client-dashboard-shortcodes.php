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
