<?php
/**
 * Client portal controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Portal;

use Super_Mechanic\Assets;
use Super_Mechanic\Helpers\Permission_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Rendering layer for enhanced client portal sections.
 */
class Client_Portal_Controller {
	/**
	 * Portal service dependency.
	 *
	 * @var Client_Portal_Service
	 */
	protected $portal_service;

	/**
	 * Permission service.
	 *
	 * @var Permission_Service
	 */
	protected $permission_service;

	/**
	 * Constructor.
	 *
	 * @param Client_Portal_Service|null $portal_service Portal service.
	 * @param Permission_Service|null    $permission_service Permission service.
	 */
	public function __construct( Client_Portal_Service $portal_service = null, Permission_Service $permission_service = null ) {
		$this->portal_service    = $portal_service ? $portal_service : new Client_Portal_Service();
		$this->permission_service = $permission_service ? $permission_service : new Permission_Service();
	}

	/**
	 * Render full client portal.
	 *
	 * @param int|null $user_id User ID.
	 * @return string
	 */
	public function render_portal( $user_id = null ) {
		$user_id    = $user_id ? absint( $user_id ) : get_current_user_id();
		$permission = $this->permission_service->user_can_access_client_portal( $user_id );
		if ( is_wp_error( $permission ) ) {
			return $this->permission_service->get_error_message( $permission );
		}

		$this->enqueue_portal_assets();

		$kpis      = $this->portal_service->get_process_kpis( $user_id );
		$processes = $this->portal_service->get_processes( $user_id, 20 );
		$history   = $this->portal_service->get_recent_history( $user_id, 12 );
		$quick_summary = $this->portal_service->get_client_quick_summary( $user_id );
		$selected_process_id = isset( $_GET['process_id'] ) ? absint( wp_unslash( $_GET['process_id'] ) ) : 0;
		$process_summary = $this->portal_service->get_process_summary_card( $user_id, $selected_process_id, $processes, $history );
		$documents = $selected_process_id > 0 ? $this->portal_service->get_process_documents( $user_id, $selected_process_id ) : array();
		$timeline  = $selected_process_id > 0 ? $this->portal_service->get_process_timeline( $user_id, $selected_process_id, 10 ) : array();

		ob_start();
		echo '<div class="sm-client-ui sm-client-portal-enhanced">';
		echo '<div class="sm-client-header">';
		echo '<div><h2 class="sm-client-title">' . esc_html__( 'Client Portal', 'super-mechanic' ) . '</h2>';
		echo '<p class="sm-client-subtitle">' . esc_html__( 'Track process status, recent history, and related documents in one place.', 'super-mechanic' ) . '</p></div>';
		echo '<span class="sm-client-badge sm-client-badge-primary">' . esc_html__( 'Enhanced', 'super-mechanic' ) . '</span>';
		echo '</div>';

		echo '<div class="sm-grid sm-grid-cards sm-portal-kpis">';
		echo $this->render_kpi_card( __( 'Total Processes', 'super-mechanic' ), isset( $kpis['total'] ) ? absint( $kpis['total'] ) : 0 );
		echo $this->render_kpi_card( __( 'Active', 'super-mechanic' ), isset( $kpis['active'] ) ? absint( $kpis['active'] ) : 0 );
		echo $this->render_kpi_card( __( 'Pending', 'super-mechanic' ), isset( $kpis['pending'] ) ? absint( $kpis['pending'] ) : 0 );
		echo $this->render_kpi_card( __( 'Completed', 'super-mechanic' ), isset( $kpis['completed'] ) ? absint( $kpis['completed'] ) : 0 );
		echo '</div>';
		echo '<div class="sm-grid sm-grid-two sm-portal-summary-grid">';
		echo $this->render_client_quick_summary_widget( $quick_summary );
		echo $this->render_process_summary_widget( $process_summary );
		echo '</div>';

		echo '<section class="sm-card sm-portal-section">';
		echo '<h3>' . esc_html__( 'Process Status', 'super-mechanic' ) . '</h3>';
		echo '<div class="sm-client-table-wrap sm-portal-table-wrap">';
		echo '<table class="sm-client-table sm-portal-process-table">';
		echo '<thead><tr><th>ID</th><th>' . esc_html__( 'Title', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Actions', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $processes ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No processes available.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $processes as $process ) {
				$process_id = absint( isset( $process['id'] ) ? $process['id'] : 0 );
				$vehicle    = trim( (string) ( isset( $process['vehicle_make'] ) ? $process['vehicle_make'] : '' ) . ' ' . (string) ( isset( $process['vehicle_model'] ) ? $process['vehicle_model'] : '' ) );
				if ( ! empty( $process['vehicle_plate'] ) ) {
					$vehicle .= ' - ' . sanitize_text_field( (string) $process['vehicle_plate'] );
				}

				echo '<tr>';
				echo '<td data-label="' . esc_attr__( 'ID', 'super-mechanic' ) . '">' . esc_html( (string) $process_id ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Title', 'super-mechanic' ) . '">' . esc_html( isset( $process['title'] ) ? (string) $process['title'] : '' ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Type', 'super-mechanic' ) . '">' . esc_html( ucwords( str_replace( '_', ' ', (string) ( isset( $process['process_type'] ) ? $process['process_type'] : '' ) ) ) ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Status', 'super-mechanic' ) . '"><span class="' . esc_attr( isset( $process['badge_class'] ) ? (string) $process['badge_class'] : 'sm-portal-badge sm-portal-badge-info' ) . '">' . esc_html( isset( $process['status_label'] ) ? (string) $process['status_label'] : '' ) . '</span></td>';
				echo '<td data-label="' . esc_attr__( 'Vehicle', 'super-mechanic' ) . '">' . esc_html( $vehicle ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Actions', 'super-mechanic' ) . '"><a class="button button-small sm-portal-action-link" href="' . esc_url( add_query_arg( 'process_id', $process_id ) ) . '">' . esc_html__( 'View details', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';

		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card sm-portal-section">';
		echo '<h3>' . esc_html__( 'Recent History', 'super-mechanic' ) . '</h3>';
		echo '<ul class="sm-portal-timeline">';
		if ( empty( $history ) ) {
			echo '<li>' . esc_html__( 'No recent events available.', 'super-mechanic' ) . '</li>';
		} else {
			foreach ( $history as $event ) {
				echo '<li><strong>' . esc_html( isset( $event['created_at'] ) ? (string) $event['created_at'] : '' ) . '</strong> · ';
				echo esc_html( ucwords( str_replace( '_', ' ', (string) ( isset( $event['action_type'] ) ? $event['action_type'] : '' ) ) ) );
				echo '<br /><span>' . esc_html( isset( $event['message'] ) ? (string) $event['message'] : '' ) . '</span></li>';
			}
		}
		echo '</ul>';
		echo '</section>';

		echo '<section class="sm-card sm-portal-section" id="sm-portal-documents">';
		echo '<h3>' . esc_html__( 'Documents', 'super-mechanic' ) . '</h3>';
		if ( $selected_process_id <= 0 ) {
			echo '<p>' . esc_html__( 'Select a process to view related documents and timeline.', 'super-mechanic' ) . '</p>';
		} else {
			echo '<p><strong>' . esc_html__( 'Process', 'super-mechanic' ) . ' #' . esc_html( (string) $selected_process_id ) . '</strong></p>';
			echo '<ul class="sm-portal-documents">';
			if ( empty( $documents ) ) {
				echo '<li>' . esc_html__( 'No visible documents for this process.', 'super-mechanic' ) . '</li>';
			} else {
				foreach ( $documents as $document ) {
					echo '<li>';
					echo '<span class="sm-portal-doc-title">' . esc_html( isset( $document['title'] ) ? (string) $document['title'] : '' ) . '</span>';
					echo '<span class="sm-portal-doc-meta">' . esc_html( isset( $document['attachment_type'] ) ? (string) $document['attachment_type'] : '' ) . ' · ' . esc_html( isset( $document['created_at'] ) ? (string) $document['created_at'] : '' ) . '</span>';
					if ( ! empty( $document['download_url'] ) ) {
						echo '<a class="button button-small" href="' . esc_url( (string) $document['download_url'] ) . '">' . esc_html__( 'Download', 'super-mechanic' ) . '</a>';
					}
					echo '</li>';
				}
			}
			echo '</ul>';

			echo '<h4>' . esc_html__( 'Timeline', 'super-mechanic' ) . '</h4>';
			echo '<ul class="sm-portal-timeline">';
			if ( empty( $timeline ) ) {
				echo '<li>' . esc_html__( 'No timeline events available for this process.', 'super-mechanic' ) . '</li>';
			} else {
				foreach ( $timeline as $event ) {
					echo '<li><strong>' . esc_html( isset( $event['event_date'] ) ? (string) $event['event_date'] : '' ) . '</strong> · ' . esc_html( isset( $event['event_label'] ) ? (string) $event['event_label'] : '' ) . '</li>';
				}
			}
			echo '</ul>';
		}
		echo '</section>';
		echo '</div>';

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Render one KPI card.
	 *
	 * @param string $label Label.
	 * @param int    $value Value.
	 * @return string
	 */
	protected function render_kpi_card( $label, $value ) {
		ob_start();
		echo '<article class="sm-card sm-kpi-card">';
		echo '<span class="sm-kpi-label">' . esc_html( (string) $label ) . '</span>';
		echo '<strong class="sm-kpi-value">' . esc_html( (string) absint( $value ) ) . '</strong>';
		echo '</article>';

		return (string) ob_get_clean();
	}

	/**
	 * Render compact client summary widget.
	 *
	 * @param array<string,int> $summary Summary payload.
	 * @return string
	 */
	protected function render_client_quick_summary_widget( array $summary ) {
		ob_start();
		echo '<section class="sm-card sm-portal-section sm-summary-widget">';
		echo '<div class="sm-section-heading"><h3>' . esc_html__( 'Client quick summary', 'super-mechanic' ) . '</h3><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Operational snapshot', 'super-mechanic' ) . '</span></div>';
		echo '<div class="sm-widget-stat-grid">';
		echo $this->render_widget_stat( __( 'Vehicles', 'super-mechanic' ), isset( $summary['vehicles_total'] ) ? absint( $summary['vehicles_total'] ) : 0 );
		echo $this->render_widget_stat( __( 'Active processes', 'super-mechanic' ), isset( $summary['active_processes'] ) ? absint( $summary['active_processes'] ) : 0 );
		echo $this->render_widget_stat( __( 'Open quotes', 'super-mechanic' ), isset( $summary['open_quotes'] ) ? absint( $summary['open_quotes'] ) : 0 );
		echo $this->render_widget_stat( __( 'Open invoices', 'super-mechanic' ), isset( $summary['open_invoices'] ) ? absint( $summary['open_invoices'] ) : 0 );
		echo '</div>';
		echo '</section>';

		return (string) ob_get_clean();
	}

	/**
	 * Render compact process summary widget.
	 *
	 * @param array<string,mixed> $summary Summary payload.
	 * @return string
	 */
	protected function render_process_summary_widget( array $summary ) {
		ob_start();
		echo '<section class="sm-card sm-portal-section sm-process-summary-widget">';
		echo '<div class="sm-section-heading"><h3>' . esc_html__( 'Process summary', 'super-mechanic' ) . '</h3><span class="sm-badge sm-badge-primary">' . esc_html__( 'Priority card', 'super-mechanic' ) . '</span></div>';

		if ( empty( $summary ) ) {
			echo '<p>' . esc_html__( 'No process summary available yet.', 'super-mechanic' ) . '</p>';
			echo '</section>';
			return (string) ob_get_clean();
		}

		echo '<p class="sm-widget-title"><strong>#' . esc_html( isset( $summary['process_id'] ) ? (string) absint( $summary['process_id'] ) : '' ) . '</strong> · ' . esc_html( isset( $summary['title'] ) ? (string) $summary['title'] : '' ) . '</p>';
		echo '<div class="sm-summary-badges">';
		echo '<span class="' . esc_attr( isset( $summary['status_badge_class'] ) ? (string) $summary['status_badge_class'] : 'sm-portal-badge sm-portal-badge-info' ) . '">' . esc_html( isset( $summary['status_label'] ) ? (string) $summary['status_label'] : '' ) . '</span>';
		echo '<span class="' . esc_attr( isset( $summary['priority_badge'] ) ? (string) $summary['priority_badge'] : 'sm-badge sm-badge-neutral' ) . '">' . esc_html( isset( $summary['priority_label'] ) ? (string) $summary['priority_label'] : '' ) . '</span>';
		echo '</div>';

		echo '<div class="sm-widget-stat-grid sm-widget-stat-grid-compact">';
		echo $this->render_widget_stat( __( 'Last change', 'super-mechanic' ), isset( $summary['last_change'] ) ? (string) $summary['last_change'] : '-' );
		echo $this->render_widget_stat( __( 'Last activity', 'super-mechanic' ), isset( $summary['last_activity'] ) && '' !== (string) $summary['last_activity'] ? (string) $summary['last_activity'] : __( 'No recent activity', 'super-mechanic' ) );
		echo '</div>';
		echo '<p><a class="button button-primary sm-portal-action-link" href="' . esc_url( isset( $summary['cta_url'] ) ? (string) $summary['cta_url'] : '#' ) . '">' . esc_html__( 'Open process details', 'super-mechanic' ) . '</a></p>';
		echo '</section>';

		return (string) ob_get_clean();
	}

	/**
	 * Render one reusable summary stat cell.
	 *
	 * @param string     $label Stat label.
	 * @param int|string $value Stat value.
	 * @return string
	 */
	protected function render_widget_stat( $label, $value ) {
		ob_start();
		echo '<article class="sm-widget-stat">';
		echo '<span class="sm-widget-stat-label">' . esc_html( (string) $label ) . '</span>';
		echo '<strong class="sm-widget-stat-value">' . esc_html( (string) $value ) . '</strong>';
		echo '</article>';

		return (string) ob_get_clean();
	}

	/**
	 * Enqueue portal assets.
	 *
	 * @return void
	 */
	protected function enqueue_portal_assets() {
		Assets::enqueue_client_assets();

		if ( ! wp_style_is( 'sm-portal-ui', 'registered' ) ) {
			wp_register_style(
				'sm-portal-ui',
				SM_PLUGIN_URL . 'assets/css/portal.css',
				array( Assets::CLIENT_STYLE ),
				SM_PLUGIN_VERSION
			);
		}
		wp_enqueue_style( 'sm-portal-ui' );

		if ( ! wp_script_is( 'sm-portal-ui', 'registered' ) ) {
			wp_register_script(
				'sm-portal-ui',
				SM_PLUGIN_URL . 'assets/js/portal.js',
				array( Assets::CLIENT_SCRIPT ),
				SM_PLUGIN_VERSION,
				true
			);
		}
		wp_enqueue_script( 'sm-portal-ui' );
	}
}
