<?php
/**
 * Dashboard admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Dashboard\Dashboard_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Operational dashboard page for admin.
 */
class Dashboard_Admin_Controller {
	/**
	 * Dashboard service.
	 *
	 * @var Dashboard_Service
	 */
	protected $dashboard_service;

	/**
	 * Constructor.
	 *
	 * @param Dashboard_Service|null $dashboard_service Dashboard service dependency.
	 */
	public function __construct( Dashboard_Service $dashboard_service = null ) {
		$this->dashboard_service = $dashboard_service ? $dashboard_service : new Dashboard_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 90 );
	}

	/**
	 * Register dashboard submenu.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Operational Dashboard', 'super-mechanic' ),
			__( 'Operational Dashboard', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-dashboard',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		$metrics  = $this->dashboard_service->get_dashboard_metrics();
		$activity = $this->dashboard_service->get_recent_activity( 10 );

		echo '<div class="wrap sm-admin-shell">';
		echo '<h1>' . esc_html__( 'Operational Dashboard', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Real-time operational metrics and recent platform activity.', 'super-mechanic' ) . '</p>';

		$this->render_metric_cards( $metrics );
		$this->render_recent_activity( $activity );

		echo '</div>';
	}

	/**
	 * Render metric cards.
	 *
	 * @param array<string,int> $metrics Metric map.
	 * @return void
	 */
	protected function render_metric_cards( array $metrics ) {
		$operational_cards = array(
			'total_clients'       => __( 'Total Clients', 'super-mechanic' ),
			'total_vehicles'      => __( 'Total Vehicles', 'super-mechanic' ),
			'active_processes'    => __( 'Active Processes', 'super-mechanic' ),
			'completed_processes' => __( 'Completed Processes', 'super-mechanic' ),
			'pending_processes'   => __( 'Pending Processes', 'super-mechanic' ),
		);
		$platform_cards = array(
			'active_webhooks'     => __( 'Active Webhooks', 'super-mechanic' ),
			'notifications_today' => __( 'Notifications Today', 'super-mechanic' ),
		);

		echo '<section class="sm-card sm-section sm-dashboard-metrics-shell">';
		echo '<div class="sm-dashboard-metrics-grid">';
		echo '<div class="sm-dashboard-metric-group">';
		echo '<h2 class="sm-dashboard-group-title">' . esc_html__( 'Operations', 'super-mechanic' ) . '</h2>';
		echo '<div class="sm-grid-cards sm-grid-cards-compact sm-dashboard-card-grid">';
		foreach ( $operational_cards as $key => $label ) {
			$value = isset( $metrics[ $key ] ) ? absint( $metrics[ $key ] ) : 0;
			echo '<article class="sm-kpi-card sm-dashboard-kpi-card">';
			echo '<div class="sm-kpi-label">' . esc_html( $label ) . '</div>';
			echo '<div class="sm-kpi-value">' . esc_html( (string) $value ) . '</div>';
			echo '</article>';
		}
		echo '</div>';
		echo '</div>';

		echo '<div class="sm-dashboard-metric-group">';
		echo '<h2 class="sm-dashboard-group-title">' . esc_html__( 'Platform Signals', 'super-mechanic' ) . '</h2>';
		echo '<div class="sm-grid-cards sm-grid-cards-compact sm-dashboard-card-grid">';
		foreach ( $platform_cards as $key => $label ) {
			$value = isset( $metrics[ $key ] ) ? absint( $metrics[ $key ] ) : 0;
			echo '<article class="sm-kpi-card sm-dashboard-kpi-card">';
			echo '<div class="sm-kpi-label">' . esc_html( $label ) . '</div>';
			echo '<div class="sm-kpi-value">' . esc_html( (string) $value ) . '</div>';
			echo '</article>';
		}

		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render recent activity block.
	 *
	 * @param array<int,array<string,mixed>> $rows Activity rows.
	 * @return void
	 */
	protected function render_recent_activity( array $rows ) {
		echo '<section class="sm-card sm-section sm-dashboard-activity-card">';
		echo '<h2>' . esc_html__( 'Recent Activity', 'super-mechanic' ) . '</h2>';
		echo '<div class="sm-table-wrap">';
		echo '<table class="sm-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Source', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Message', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No recent activity available.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( isset( $row['created_at'] ) ? (string) $row['created_at'] : '' ) . '</td>';
				echo '<td>' . esc_html( isset( $row['log_type'] ) ? (string) $row['log_type'] : '' ) . '</td>';
				echo '<td>' . esc_html( isset( $row['source'] ) ? (string) $row['source'] : '' ) . '</td>';
				echo '<td>' . esc_html( isset( $row['status'] ) ? (string) $row['status'] : '' ) . '</td>';
				echo '<td>' . esc_html( isset( $row['message'] ) ? (string) $row['message'] : '' ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';
	}
}
