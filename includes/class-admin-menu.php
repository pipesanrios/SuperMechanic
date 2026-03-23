<?php
/**
 * Admin menu manager.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

use Super_Mechanic\Clients\Client_Admin_Controller;
use Super_Mechanic\Dashboard\Admin_Dashboard_Controller;
use Super_Mechanic\Dashboard\Mechanic_Dashboard_Controller;
use Super_Mechanic\Flows\Flow_Admin_Controller;
use Super_Mechanic\Processes\Process_Admin_Controller;
use Super_Mechanic\Reports\Report_Admin_Controller;
use Super_Mechanic\Vehicles\Vehicle_Admin_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin menu pages for the plugin.
 */
class Admin_Menu {
	protected $settings;
	protected $client_admin_controller;
	protected $vehicle_admin_controller;
	protected $process_admin_controller;
	protected $flow_admin_controller;
	protected $admin_dashboard_controller;
	protected $mechanic_dashboard_controller;
	protected $report_admin_controller;
	protected $shortcode_admin_controller;

	/**
	 * Constructor.
	 *
	 * @param Settings                     $settings                     Settings handler.
	 * @param Client_Admin_Controller      $client_admin_controller      Clients controller.
	 * @param Vehicle_Admin_Controller     $vehicle_admin_controller     Vehicles controller.
	 * @param Process_Admin_Controller     $process_admin_controller     Processes controller.
	 * @param Flow_Admin_Controller        $flow_admin_controller        Flows controller.
	 * @param Admin_Dashboard_Controller   $admin_dashboard_controller   Admin dashboard controller.
	 * @param Mechanic_Dashboard_Controller $mechanic_dashboard_controller Mechanic dashboard controller.
	 * @param Report_Admin_Controller      $report_admin_controller      Reports controller.
	 * @param Shortcode_Admin_Controller   $shortcode_admin_controller   Shortcodes controller.
	 */
	public function __construct( Settings $settings, Client_Admin_Controller $client_admin_controller, Vehicle_Admin_Controller $vehicle_admin_controller, Process_Admin_Controller $process_admin_controller, Flow_Admin_Controller $flow_admin_controller, Admin_Dashboard_Controller $admin_dashboard_controller, Mechanic_Dashboard_Controller $mechanic_dashboard_controller, Report_Admin_Controller $report_admin_controller, Shortcode_Admin_Controller $shortcode_admin_controller ) {
		$this->settings                    = $settings;
		$this->client_admin_controller     = $client_admin_controller;
		$this->vehicle_admin_controller    = $vehicle_admin_controller;
		$this->process_admin_controller    = $process_admin_controller;
		$this->flow_admin_controller       = $flow_admin_controller;
		$this->admin_dashboard_controller  = $admin_dashboard_controller;
		$this->mechanic_dashboard_controller = $mechanic_dashboard_controller;
		$this->report_admin_controller     = $report_admin_controller;
		$this->shortcode_admin_controller  = $shortcode_admin_controller;
	}

	/**
	 * Register plugin admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Super Mechanic', 'super-mechanic' ),
			__( 'Super Mechanic', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic',
			array( $this->admin_dashboard_controller, 'render_page' ),
			'dashicons-admin-tools',
			56
		);

		add_submenu_page(
			'super-mechanic',
			__( 'Dashboard', 'super-mechanic' ),
			__( 'Dashboard', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic',
			array( $this->admin_dashboard_controller, 'render_page' )
		);

		if ( current_user_can( 'sm_manage_processes' ) ) {
			add_submenu_page(
				'super-mechanic',
				__( 'Panel mecánico', 'super-mechanic' ),
				__( 'Panel mecánico', 'super-mechanic' ),
				'sm_manage_processes',
				'super-mechanic-mechanic-dashboard',
				array( $this->mechanic_dashboard_controller, 'render_page' )
			);
		}

		add_submenu_page(
			'super-mechanic',
			__( 'Clientes', 'super-mechanic' ),
			__( 'Clientes', 'super-mechanic' ),
			'sm_manage_clients',
			'super-mechanic-clients',
			array( $this->client_admin_controller, 'render_page' )
		);

		add_submenu_page(
			'super-mechanic',
			__( 'Vehículos', 'super-mechanic' ),
			__( 'Vehículos', 'super-mechanic' ),
			'sm_manage_vehicles',
			'super-mechanic-vehicles',
			array( $this->vehicle_admin_controller, 'render_page' )
		);

		add_submenu_page(
			'super-mechanic',
			__( 'Procesos', 'super-mechanic' ),
			__( 'Procesos', 'super-mechanic' ),
			'sm_manage_processes',
			'super-mechanic-processes',
			array( $this->process_admin_controller, 'render_page' )
		);

		add_submenu_page(
			'super-mechanic',
			__( 'Flujos', 'super-mechanic' ),
			__( 'Flujos', 'super-mechanic' ),
			'sm_manage_flows',
			'super-mechanic-flows',
			array( $this->flow_admin_controller, 'render_page' )
		);

		add_submenu_page(
			'super-mechanic',
			__( 'Reportes', 'super-mechanic' ),
			__( 'Reportes', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-reports',
			array( $this->report_admin_controller, 'render_page' )
		);

		add_submenu_page(
			'super-mechanic',
			__( 'Shortcodes', 'super-mechanic' ),
			__( 'Shortcodes', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-shortcodes',
			array( $this->shortcode_admin_controller, 'render_page' )
		);

		add_submenu_page(
			'super-mechanic',
			__( 'Ajustes', 'super-mechanic' ),
			__( 'Ajustes', 'super-mechanic' ),
			'sm_manage_settings',
			'super-mechanic-settings',
			array( $this->settings, 'render_settings_page' )
		);
	}
}
