<?php
/**
 * Mechanic dashboard shortcodes.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Assets;
use Super_Mechanic\Helpers\Permission_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Registers mechanic frontend shortcodes.
 */
class Mechanic_Dashboard_Shortcodes {
	/**
	 * Mechanic dashboard controller.
	 *
	 * @var Mechanic_Dashboard_Controller
	 */
	protected $mechanic_dashboard_controller;

	/**
	 * Permission service.
	 *
	 * @var Permission_Service
	 */
	protected $permission_service;

	/**
	 * Constructor.
	 *
	 * @param Mechanic_Dashboard_Controller|null $mechanic_dashboard_controller Mechanic dashboard controller.
	 * @param Permission_Service|null            $permission_service            Permission service.
	 */
	public function __construct( Mechanic_Dashboard_Controller $mechanic_dashboard_controller = null, Permission_Service $permission_service = null ) {
		$this->mechanic_dashboard_controller = $mechanic_dashboard_controller ? $mechanic_dashboard_controller : new Mechanic_Dashboard_Controller();
		$this->permission_service            = $permission_service ? $permission_service : new Permission_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'sm_mechanic_dashboard', array( $this, 'render_mechanic_dashboard' ) );
		add_shortcode( 'sm_mechanic_processes', array( $this, 'render_mechanic_processes' ) );
	}

	/**
	 * Render mechanic dashboard shortcode.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_mechanic_dashboard( $atts = array() ) {
		Assets::enqueue_client_assets();

		$permission = $this->permission_service->user_can_access_mechanic_portal( get_current_user_id() );

		if ( is_wp_error( $permission ) ) {
			return $this->permission_service->get_error_message( $permission );
		}

		return $this->mechanic_dashboard_controller->render_frontend_dashboard();
	}

	/**
	 * Render mechanic processes shortcode.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_mechanic_processes( $atts = array() ) {
		Assets::enqueue_client_assets();

		$permission = $this->permission_service->user_can_access_mechanic_portal( get_current_user_id() );

		if ( is_wp_error( $permission ) ) {
			return $this->permission_service->get_error_message( $permission );
		}

		return $this->mechanic_dashboard_controller->render_frontend_processes();
	}
}
