<?php
/**
 * Mechanic Panel controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Attachments\Attachment_Service;
use Super_Mechanic\Attachments\Process_Timeline_Service;
use Super_Mechanic\Communication\Comment_Service;
use Super_Mechanic\Flows\Flow_Step_Service;
use Super_Mechanic\Helpers\Download_Service;
use Super_Mechanic\Helpers\Permission_Service;
use Super_Mechanic\Maintenance\Maintenance_Service;
use Super_Mechanic\Processes\Process_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the mechanic operational portal.
 */
class Mechanic_Dashboard_Controller {
	const PAGE_SLUG = 'super-mechanic-mechanic-dashboard';

	protected $dashboard_service;
	protected $process_service;
	protected $timeline_service;
	protected $comment_service;
	protected $attachment_service;
	protected $maintenance_service;
	protected $flow_step_service;
	protected $download_service;
	protected $permission_service;
	protected $frontend_render = false;

	public function __construct( Dashboard_Service $dashboard_service = null, Process_Service $process_service = null, Process_Timeline_Service $timeline_service = null, Comment_Service $comment_service = null, Attachment_Service $attachment_service = null, Maintenance_Service $maintenance_service = null, Flow_Step_Service $flow_step_service = null, Download_Service $download_service = null, Permission_Service $permission_service = null ) {
		$this->dashboard_service   = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->process_service     = $process_service ? $process_service : new Process_Service();
		$this->timeline_service    = $timeline_service ? $timeline_service : new Process_Timeline_Service();
		$this->comment_service     = $comment_service ? $comment_service : new Comment_Service();
		$this->attachment_service  = $attachment_service ? $attachment_service : new Attachment_Service();
		$this->maintenance_service = $maintenance_service ? $maintenance_service : new Maintenance_Service();
		$this->flow_step_service   = $flow_step_service ? $flow_step_service : new Flow_Step_Service();
		$this->download_service    = $download_service ? $download_service : new Download_Service();
		$this->permission_service  = $permission_service ? $permission_service : new Permission_Service();
	}

	public function register_hooks() {
		add_action( 'init', array( $this, 'maybe_handle_actions' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_actions' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	public function maybe_handle_actions() {
		if ( ! $this->is_mechanic_action_request() ) {
			return;
		}

		if ( is_wp_error( $this->permission_service->user_can_access_mechanic_portal( get_current_user_id() ) ) ) {
			return;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';

		if ( 'POST' !== $request_method ) {
			return;
		}

		$operation = isset( $_POST['sm_mechanic_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_mechanic_operation'] ) ) : '';

		if ( 'update_step' === $operation ) {
			$this->handle_step_update_action();
		}

		if ( 'update_status' === $operation ) {
			$this->handle_status_update_action();
		}

		if ( 'create_comment' === $operation ) {
			$this->handle_comment_create_action();
		}
	}

	public function render_frontend_dashboard() {
		$permission = $this->permission_service->user_can_access_mechanic_portal( get_current_user_id() );

		if ( is_wp_error( $permission ) ) {
			return $this->permission_service->get_error_message( $permission );
		}

		$process_id            = isset( $_GET['process_id'] ) ? absint( wp_unslash( $_GET['process_id'] ) ) : 0;
		$this->frontend_render = true;

		ob_start();
		echo '<div class="sm-client-ui sm-mechanic-portal">';
		echo '<div class="sm-client-header"><div><h2 class="sm-client-title">' . esc_html__( 'Mechanic portal', 'super-mechanic' ) . '</h2><p class="sm-client-subtitle">' . esc_html__( 'Frontend operational view for assigned processes and allowed actions.', 'super-mechanic' ) . '</p></div><span class="sm-client-badge sm-client-badge-primary">' . esc_html__( 'Mechanic', 'super-mechanic' ) . '</span></div>';
		$this->render_frontend_notices();
		if ( $process_id > 0 ) {
			$this->render_process_detail_page( $process_id );
		} else {
			$this->render_dashboard_overview();
		}
		echo '</div>';

		$this->frontend_render = false;

		return (string) ob_get_clean();
	}

	public function render_frontend_processes() {
		$permission = $this->permission_service->user_can_access_mechanic_portal( get_current_user_id() );

		if ( is_wp_error( $permission ) ) {
			return $this->permission_service->get_error_message( $permission );
		}

		$this->frontend_render = true;

		ob_start();
		echo '<div class="sm-client-ui sm-mechanic-processes">';
		echo '<div class="sm-client-header"><div><h2 class="sm-client-title">' . esc_html__( 'bssigned processes', 'super-mechanic' ) . '</h2><p class="sm-client-subtitle">' . esc_html__( 'Secure list of processes visible to the authenticated mechanic.', 'super-mechanic' ) . '</p></div><span class="sm-client-badge sm-client-badge-primary">' . esc_html__( 'Mechanic', 'super-mechanic' ) . '</span></div>';
		$this->render_frontend_notices();
		$this->render_dashboard_overview();
		echo '</div>';

		$this->frontend_render = false;

		return (string) ob_get_clean();
	}

	public function render_page() {
		$this->ensure_permissions();

		$process_id = isset( $_GET['process_id'] ) ? absint( wp_unslash( $_GET['process_id'] ) ) : 0;

		echo '<div class="wrap sm-admin-shell sm-mechanic-shell sm-mechanic-dashboard">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Mechanic panel', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Work only on assigned processes or those allowed by the current system policy.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<span class="sm-badge sm-badge-primary">' . esc_html__( 'Mechanic operations', 'super-mechanic' ) . '</span>';
		echo '</div>';

		if ( $process_id > 0 ) {
			$this->render_process_detail_page( $process_id );
		} else {
			$this->render_dashboard_overview();
		}

		echo '</div>';
	}

	public function render_admin_notices() {
		if ( ! $this->is_mechanic_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';

		if ( 'status_updated' === $notice ) {
			$this->render_notice( __( 'Process status updated successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'step_updated' === $notice ) {
			$this->render_notice( __( 'Process step updated successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'comment_created' === $notice ) {
			$this->render_notice( __( 'Technical note saved successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'error' === $notice ) {
			$messages = get_transient( $this->get_error_transient_key() );
			delete_transient( $this->get_error_transient_key() );

			if ( is_array( $messages ) ) {
				foreach ( $messages as $message ) {
					$this->render_notice( $message, 'error' );
				}
			}
		}
	}

	protected function render_dashboard_overview() {
		$user_id           = get_current_user_id();
		$kpis              = $this->dashboard_service->get_mechanic_kpis( $user_id );
		$processes         = $this->get_filtered_processes( $user_id );
		$appointments      = $this->dashboard_service->get_mechanic_upcoming_appointments( $user_id, 12 );
		$quick_summary     = $this->build_mechanic_quick_summary( $kpis, $processes, $appointments );
		$process_highlight = $this->build_mechanic_process_highlight( $processes );
		$current_status    = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : '';
		$current_type      = isset( $_GET['filter_process_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_process_type'] ) ) : '';
		$status_options    = $this->process_service->get_status_options();
		$process_type_opts = $this->process_service->get_process_type_options();

		echo '<div class="sm-grid sm-grid-cards" style="margin-bottom:24px;">';
		$this->render_kpi_card( __( 'bctive processes', 'super-mechanic' ), $kpis['active_processes'] );
		$this->render_kpi_card( __( 'Pending approval', 'super-mechanic' ), $kpis['pending_approvals'] );
		$this->render_kpi_card( __( 'Maintenance processes', 'super-mechanic' ), $kpis['maintenance_processes'] );
		echo '</div>';
		$this->render_mechanic_quick_summary_widget( $quick_summary );
		$this->render_mechanic_process_summary_widget( $process_highlight );

		echo '<form method="get" class="sm-card sm-filter-card" style="margin-bottom:16px;">';
		if ( ! $this->frontend_render ) {
			echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
		}
		echo '<select name="filter_process_type">';
		echo '<option value="">' . esc_html__( 'All types', 'super-mechanic' ) . '</option>';
		foreach ( $process_type_opts as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current_type, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo '<select name="filter_status">';
		echo '<option value="">' . esc_html__( 'All statuses', 'super-mechanic' ) . '</option>';
		foreach ( $status_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current_status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo $this->render_submit_button( __( 'Filter', 'super-mechanic' ), '', 'filter_action' );
		echo '</form>';

		echo '<div class="sm-table-wrap"><table class="widefat striped">';
		echo '<thead><tr><th>ID</th><th>' . esc_html__( 'Title', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Current step', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Updated', 'super-mechanic' ) . '</th><th>' . esc_html__( 'bction', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $processes ) ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No processes available for this mechanic.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $processes as $process ) {
				$detail_url = $this->get_page_url(
					array(
						'process_id' => absint( $process['id'] ),
					)
				);

				echo '<tr>';
				echo '<td>' . esc_html( absint( $process['id'] ) ) . '</td>';
				echo '<td>' . esc_html( $process['title'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $process['process_type'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->get_process_status_display( $process ) ) . '</td>';
				echo '<td>' . esc_html( $this->get_process_step_label( $process ) ) . '</td>';
				echo '<td>' . esc_html( $this->get_process_client_label( $process ) ) . '</td>';
				echo '<td>' . esc_html( $this->get_process_vehicle_label( $process ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $process['updated_at'] ) ? $process['updated_at'] : $process['created_at'] ) . '</td>';
				echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html__( 'Ver detalle', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';

		$this->render_mechanic_appointments_block( $appointments );
	}

	protected function render_process_detail_page( $process_id ) {
		$process = $this->get_accessible_process_row( $process_id );

		if ( empty( $process ) ) {
			$this->render_notice( __( 'You do not have access to this process, or the process does not exist.', 'super-mechanic' ), 'error' );
			echo '<p><a href="' . esc_url( $this->get_page_url() ) . '">' . esc_html__( 'Back to mechanic panel', 'super-mechanic' ) . '</a></p>';
			return;
		}

		$timeline    = $this->timeline_service->get_process_timeline( $process_id, false );
		$comments    = $this->comment_service->get_process_comments( $process_id, array( 'per_page' => 100, 'orderby' => 'created_at', 'order' => 'DESC' ) );
		$attachments = $this->attachment_service->get_process_attachments( $process_id, array( 'per_page' => 100, 'orderby' => 'created_at', 'order' => 'DESC' ) );
		$steps       = $this->get_available_steps( $process );
		$status_opts = $this->process_service->get_status_options();
		$maintenance = 'maintenance' === $process['process_type'] ? $this->maintenance_service->get_maintenance_by_process( $process_id ) : array();

		echo '<p><a href="' . esc_url( $this->get_page_url() ) . '">' . esc_html__( '← Back to list', 'super-mechanic' ) . '</a></p>';
		echo '<h2>' . esc_html( $process['title'] ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:900px;margin-bottom:20px;"><tbody>';
		$this->render_summary_row( __( 'Type', 'super-mechanic' ), $this->humanize_key( $process['process_type'] ) );
		$this->render_summary_row( __( 'Status', 'super-mechanic' ), $this->get_process_status_display( $process ) );
		$this->render_summary_row( __( 'Current step', 'super-mechanic' ), $this->get_process_step_label( $process ) );
		$this->render_summary_row( __( 'Client', 'super-mechanic' ), $this->get_process_client_label( $process ) );
		$this->render_summary_row( __( 'Vehicle', 'super-mechanic' ), $this->get_process_vehicle_label( $process ) );
		$this->render_summary_row( __( 'ppen date', 'super-mechanic' ), ! empty( $process['opened_at'] ) ? $process['opened_at'] : '-' );
		$this->render_summary_row( __( 'Target date', 'super-mechanic' ), ! empty( $process['due_date'] ) ? $process['due_date'] : '-' );
		$this->render_summary_row( __( 'Notas internas', 'super-mechanic' ), ! empty( $process['internal_notes'] ) ? $process['internal_notes'] : '-' );
		echo '</tbody></table>';

		echo '<div class="sm-ops-grid" style="margin-bottom:20px;">';
		echo '<div>';
		$this->render_status_form( $process, $status_opts );
		$this->render_step_form( $process, $steps );
		$this->render_comment_form( $process );
		echo '</div>';
		echo '<div>';
		$this->render_maintenance_panel( $process, $maintenance );
		echo '</div>';
		echo '</div>';

		$this->render_attachments_table( $attachments );
		$this->render_comments_table( $comments );
		$this->render_timeline_table( $timeline );
	}

	protected function handle_step_update_action() {
		check_admin_referer( 'sm_mechanic_update_step', 'sm_mechanic_step_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$step_id    = isset( $_POST['current_step_id'] ) ? absint( wp_unslash( $_POST['current_step_id'] ) ) : 0;

		if ( ! $this->current_user_can_access_process( $process_id ) ) {
			$this->store_errors( new WP_Error( 'sm_mechanic_process_forbidden', __( 'You cannot update the step of a process you do not own.', 'super-mechanic' ) ) );
			$this->redirect_to_process( $process_id, 'error' );
		}

		$result = $this->process_service->update_current_step( $process_id, $step_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'error' );
		}

		$this->redirect_to_process( $process_id, 'step_updated' );
	}

	protected function handle_status_update_action() {
		check_admin_referer( 'sm_mechanic_update_status', 'sm_mechanic_status_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$status     = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $this->current_user_can_access_process( $process_id ) ) {
			$this->store_errors( new WP_Error( 'sm_mechanic_process_forbidden', __( 'You cannot update the status of a process you do not own.', 'super-mechanic' ) ) );
			$this->redirect_to_process( $process_id, 'error' );
		}

		$result = $this->process_service->update_process(
			$process_id,
			array(
				'status' => $status,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'error' );
		}

		$this->redirect_to_process( $process_id, 'status_updated' );
	}

	protected function handle_comment_create_action() {
		check_admin_referer( 'sm_mechanic_create_comment', 'sm_mechanic_comment_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$process    = $this->process_service->get_process( $process_id );

		if ( ! $process || ! $this->current_user_can_access_process( $process_id ) ) {
			$this->store_errors( new WP_Error( 'sm_mechanic_process_forbidden', __( 'You cannot add notes to a process you do not own.', 'super-mechanic' ) ) );
			$this->redirect_to_process( $process_id, 'error' );
		}

		$result = $this->comment_service->create_comment(
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'client_id'         => ! empty( $process['client_id'] ) ? absint( $process['client_id'] ) : 0,
				'vehicle_id'        => ! empty( $process['vehicle_id'] ) ? absint( $process['vehicle_id'] ) : 0,
				'comment_type'      => isset( $_POST['comment_type'] ) ? wp_unslash( $_POST['comment_type'] ) : 'internal_note',
				'content'           => isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '',
				'is_internal'       => 1,
				'is_client_visible' => 0,
				'author_user_id'    => get_current_user_id(),
				'status'            => 'published',
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'error' );
		}

		$this->redirect_to_process( $process_id, 'comment_created' );
	}

	protected function get_filtered_processes( $user_id ) {
		$args = array();

		if ( isset( $_GET['filter_status'] ) && '' !== wp_unslash( $_GET['filter_status'] ) ) {
			$args['status'] = sanitize_key( wp_unslash( $_GET['filter_status'] ) );
		}

		if ( isset( $_GET['filter_process_type'] ) && '' !== wp_unslash( $_GET['filter_process_type'] ) ) {
			$args['process_type'] = sanitize_key( wp_unslash( $_GET['filter_process_type'] ) );
		}

		return $this->dashboard_service->append_derived_state_to_processes(
			$this->process_service->get_mechanic_processes( $user_id, $args, 200 )
		);
	}

	protected function get_accessible_process_row( $process_id ) {
		$process_id = absint( $process_id );

		if ( ! $process_id || ! $this->current_user_can_access_process( $process_id ) ) {
			return array();
		}

		$processes = $this->process_service->get_mechanic_processes( get_current_user_id(), array(), 500 );
		foreach ( $processes as $process ) {
			if ( absint( $process['id'] ) === $process_id ) {
				return $this->dashboard_service->append_derived_state_to_process( $process );
			}
		}

		$process = $this->process_service->get_process( $process_id );

		return is_array( $process ) ? $this->dashboard_service->append_derived_state_to_process( $process ) : array();
	}

	protected function get_available_steps( array $process ) {
		if ( empty( $process['flow_id'] ) ) {
			return array();
		}

		return $this->flow_step_service->get_steps_by_flow( absint( $process['flow_id'] ), true );
	}

	protected function render_kpi_card( $label, $value ) {
		echo '<article class="sm-card sm-kpi-card">';
		echo '<span class="sm-kpi-label">' . esc_html( $label ) . '</span>';
		echo '<strong class="sm-kpi-value">' . esc_html( absint( $value ) ) . '</strong>';
		echo '</article>';
	}

	/**
	 * Build compact quick summary for mechanic dashboard.
	 *
	 * @param array<string,mixed>              $kpis KPI payload.
	 * @param array<int,array<string,mixed>>   $processes Process rows.
	 * @param array<int,array<string,mixed>>   $appointments Appointment rows.
	 * @return array<string,mixed>
	 */
	protected function build_mechanic_quick_summary( array $kpis, array $processes, array $appointments ) {
		$critical_count = 0;
		foreach ( $processes as $process ) {
			if ( ! is_array( $process ) ) {
				continue;
			}
			if ( 'critical' === $this->resolve_process_priority_level( $process ) ) {
				++$critical_count;
			}
		}

		$next_appointment = __( 'No appointment scheduled', 'super-mechanic' );
		if ( ! empty( $appointments ) && is_array( $appointments[0] ) ) {
			$next_appointment = $this->format_datetime_label( isset( $appointments[0]['start_at'] ) ? (string) $appointments[0]['start_at'] : '' );
		}

		return array(
			'assigned_processes' => count( $processes ),
			'pending_approvals'  => isset( $kpis['pending_approvals'] ) ? absint( $kpis['pending_approvals'] ) : 0,
			'critical_processes' => $critical_count,
			'next_appointment'   => $next_appointment,
		);
	}

	/**
	 * Build highlighted process summary payload.
	 *
	 * @param array<int,array<string,mixed>> $processes Process rows.
	 * @return array<string,mixed>
	 */
	protected function build_mechanic_process_highlight( array $processes ) {
		if ( empty( $processes ) ) {
			return array();
		}

		$selected = $processes[0];
		foreach ( $processes as $process ) {
			if ( ! is_array( $process ) ) {
				continue;
			}
			if ( 'critical' === $this->resolve_process_priority_level( $process ) ) {
				$selected = $process;
				break;
			}
		}

		$priority_level = $this->resolve_process_priority_level( $selected );
		$priority_label = __( 'Normal', 'super-mechanic' );
		$priority_badge = 'sm-badge sm-badge-success';
		if ( 'critical' === $priority_level ) {
			$priority_label = __( 'Critical', 'super-mechanic' );
			$priority_badge = 'sm-badge sm-badge-danger';
		} elseif ( 'warning' === $priority_level ) {
			$priority_label = __( 'Warning', 'super-mechanic' );
			$priority_badge = 'sm-badge sm-badge-warning';
		}

		return array(
			'process_id'      => absint( isset( $selected['id'] ) ? $selected['id'] : 0 ),
			'title'           => sanitize_text_field( isset( $selected['title'] ) ? (string) $selected['title'] : '' ),
			'status'          => $this->get_process_status_display( $selected ),
			'priority_label'  => $priority_label,
			'priority_badge'  => $priority_badge,
			'last_change'     => sanitize_text_field( ! empty( $selected['updated_at'] ) ? (string) $selected['updated_at'] : (string) ( isset( $selected['created_at'] ) ? $selected['created_at'] : '' ) ),
			'cta_url'         => $this->get_page_url( array( 'process_id' => absint( isset( $selected['id'] ) ? $selected['id'] : 0 ) ) ),
		);
	}

	/**
	 * Render mechanic quick summary widget.
	 *
	 * @param array<string,mixed> $summary Summary payload.
	 * @return void
	 */
	protected function render_mechanic_quick_summary_widget( array $summary ) {
		echo '<section class="sm-card sm-section sm-summary-widget" style="margin-top:0;margin-bottom:14px;">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Mechanic quick summary', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'At a glance', 'super-mechanic' ) . '</span></div>';
		echo '<div class="sm-widget-stat-grid">';
		echo '<article class="sm-widget-stat"><span class="sm-widget-stat-label">' . esc_html__( 'Assigned', 'super-mechanic' ) . '</span><strong class="sm-widget-stat-value">' . esc_html( (string) absint( isset( $summary['assigned_processes'] ) ? $summary['assigned_processes'] : 0 ) ) . '</strong></article>';
		echo '<article class="sm-widget-stat"><span class="sm-widget-stat-label">' . esc_html__( 'Pending', 'super-mechanic' ) . '</span><strong class="sm-widget-stat-value">' . esc_html( (string) absint( isset( $summary['pending_approvals'] ) ? $summary['pending_approvals'] : 0 ) ) . '</strong></article>';
		echo '<article class="sm-widget-stat"><span class="sm-widget-stat-label">' . esc_html__( 'Critical', 'super-mechanic' ) . '</span><strong class="sm-widget-stat-value">' . esc_html( (string) absint( isset( $summary['critical_processes'] ) ? $summary['critical_processes'] : 0 ) ) . '</strong></article>';
		echo '<article class="sm-widget-stat"><span class="sm-widget-stat-label">' . esc_html__( 'Next appointment', 'super-mechanic' ) . '</span><strong class="sm-widget-stat-value">' . esc_html( isset( $summary['next_appointment'] ) ? (string) $summary['next_appointment'] : '-' ) . '</strong></article>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render process highlight widget.
	 *
	 * @param array<string,mixed> $summary Process summary payload.
	 * @return void
	 */
	protected function render_mechanic_process_summary_widget( array $summary ) {
		echo '<section class="sm-card sm-section sm-process-summary-widget" style="margin-top:0;margin-bottom:14px;">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Process summary', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html__( 'Priority card', 'super-mechanic' ) . '</span></div>';
		if ( empty( $summary ) ) {
			echo '<p>' . esc_html__( 'No process available for summary.', 'super-mechanic' ) . '</p>';
			echo '</section>';
			return;
		}
		echo '<p class="sm-widget-title"><strong>#' . esc_html( isset( $summary['process_id'] ) ? (string) absint( $summary['process_id'] ) : '' ) . '</strong> · ' . esc_html( isset( $summary['title'] ) ? (string) $summary['title'] : '' ) . '</p>';
		echo '<div class="sm-summary-badges">';
		echo '<span class="sm-badge sm-badge-neutral">' . esc_html( isset( $summary['status'] ) ? (string) $summary['status'] : '' ) . '</span>';
		echo '<span class="' . esc_attr( isset( $summary['priority_badge'] ) ? (string) $summary['priority_badge'] : 'sm-badge sm-badge-neutral' ) . '">' . esc_html( isset( $summary['priority_label'] ) ? (string) $summary['priority_label'] : '' ) . '</span>';
		echo '</div>';
		echo '<div class="sm-widget-stat-grid sm-widget-stat-grid-compact">';
		echo '<article class="sm-widget-stat"><span class="sm-widget-stat-label">' . esc_html__( 'Last change', 'super-mechanic' ) . '</span><strong class="sm-widget-stat-value">' . esc_html( isset( $summary['last_change'] ) ? (string) $summary['last_change'] : '-' ) . '</strong></article>';
		echo '</div>';
		echo '<p><a class="button button-primary" href="' . esc_url( isset( $summary['cta_url'] ) ? (string) $summary['cta_url'] : '#' ) . '">' . esc_html__( 'Open process details', 'super-mechanic' ) . '</a></p>';
		echo '</section>';
	}

	/**
	 * Resolve lightweight priority level from existing process metadata.
	 *
	 * @param array<string,mixed> $process Process row.
	 * @return string
	 */
	protected function resolve_process_priority_level( array $process ) {
		$priority = isset( $process['priority'] ) ? sanitize_key( (string) $process['priority'] ) : '';
		$status   = isset( $process['status'] ) ? sanitize_key( (string) $process['status'] ) : '';
		$derived  = isset( $process['derived_status'] ) ? sanitize_key( (string) $process['derived_status'] ) : '';

		if ( 'critical' === $priority || in_array( $derived, array( 'waiting_payment', 'waiting_approval' ), true ) || 'waiting_approval' === $status ) {
			return 'critical';
		}

		if ( 'warning' === $priority || in_array( $status, array( 'pending', 'in_progress' ), true ) ) {
			return 'warning';
		}

		return 'normal';
	}

	protected function render_summary_row( $label, $value ) {
		echo '<tr><th style="width:220px;">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}

	protected function render_status_form( array $process, array $status_options ) {
		echo '<h3>' . esc_html__( 'Update status', 'super-mechanic' ) . '</h3>';
		echo '<form method="post" class="sm-card sm-panel-form" style="margin-bottom:16px;">';
		wp_nonce_field( 'sm_mechanic_update_status', 'sm_mechanic_status_nonce' );
		echo '<input type="hidden" name="sm_mechanic_operation" value="update_status" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		if ( $this->frontend_render ) {
			echo '<input type="hidden" name="sm_mechanic_frontend" value="1" />';
		}
		echo '<select name="status">';
		foreach ( $status_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $process['status'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo $this->render_submit_button( __( 'Save status', 'super-mechanic' ), 'secondary', 'submit' );
		echo '</form>';
	}

	protected function render_step_form( array $process, array $steps ) {
		echo '<h3>' . esc_html__( 'Update step', 'super-mechanic' ) . '</h3>';

		if ( empty( $steps ) ) {
			echo '<p>' . esc_html__( 'This process has no active steps available.', 'super-mechanic' ) . '</p>';
			return;
		}

		echo '<form method="post" class="sm-card sm-panel-form" style="margin-bottom:16px;">';
		wp_nonce_field( 'sm_mechanic_update_step', 'sm_mechanic_step_nonce' );
		echo '<input type="hidden" name="sm_mechanic_operation" value="update_step" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		if ( $this->frontend_render ) {
			echo '<input type="hidden" name="sm_mechanic_frontend" value="1" />';
		}
		echo '<select name="current_step_id">';
		foreach ( $steps as $step ) {
			echo '<option value="' . esc_attr( absint( $step['id'] ) ) . '" ' . selected( absint( $process['current_step_id'] ), absint( $step['id'] ), false ) . '>' . esc_html( $step['step_label'] ) . '</option>';
		}
		echo '</select> ';
		echo $this->render_submit_button( __( 'Save step', 'super-mechanic' ), 'secondary', 'submit' );
		echo '</form>';
	}

	protected function render_comment_form( array $process ) {
		echo '<h3>' . esc_html__( 'bdd technical note', 'super-mechanic' ) . '</h3>';
		echo '<form method="post" class="sm-card sm-panel-form" style="margin-bottom:16px;">';
		wp_nonce_field( 'sm_mechanic_create_comment', 'sm_mechanic_comment_nonce' );
		echo '<input type="hidden" name="sm_mechanic_operation" value="create_comment" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		if ( $this->frontend_render ) {
			echo '<input type="hidden" name="sm_mechanic_frontend" value="1" />';
		}
		echo '<p><label for="sm_mechanic_comment_type">' . esc_html__( 'Type', 'super-mechanic' ) . '</label><br />';
		echo '<select id="sm_mechanic_comment_type" name="comment_type">';
		echo '<option value="internal_note">' . esc_html__( 'Nota interna', 'super-mechanic' ) . '</option>';
		echo '<option value="staff_reply">' . esc_html__( 'bvance operativo', 'super-mechanic' ) . '</option>';
		echo '<option value="system_note">' . esc_html__( 'System note', 'super-mechanic' ) . '</option>';
		echo '</select></p>';
		echo '<p><label for="sm_mechanic_comment_content">' . esc_html__( 'Content', 'super-mechanic' ) . '</label><br />';
		echo '<textarea id="sm_mechanic_comment_content" name="content" rows="5" class="large-text" required></textarea></p>';
		echo $this->render_submit_button( __( 'Save note', 'super-mechanic' ), 'primary', 'submit' );
		echo '</form>';
	}

	protected function render_maintenance_panel( array $process, $maintenance ) {
		echo '<h3>' . esc_html__( 'Mantenimiento', 'super-mechanic' ) . '</h3>';

		if ( 'maintenance' !== $process['process_type'] ) {
			echo '<p>' . esc_html__( 'This process does not belong to the maintenance module.', 'super-mechanic' ) . '</p>';
			return;
		}

		if ( empty( $maintenance ) ) {
			echo '<p>' . esc_html__( 'No maintenance record has been created for this process yet.', 'super-mechanic' ) . '</p>';
			return;
		}

		$maintenance_id = absint( $maintenance['id'] );
		$parts          = $this->maintenance_service->get_parts( $maintenance_id );
		$labor          = $this->maintenance_service->get_labor( $maintenance_id );

		echo '<table class="widefat striped" style="margin-bottom:16px;"><tbody>';
		$this->render_summary_row( __( 'Diagnosis', 'super-mechanic' ), ! empty( $maintenance['diagnosis'] ) ? $maintenance['diagnosis'] : '-' );
		$this->render_summary_row( __( 'bssigned mechanic', 'super-mechanic' ), ! empty( $maintenance['mechanic_id'] ) ? '#' . absint( $maintenance['mechanic_id'] ) : '-' );
		$this->render_summary_row( __( 'Times estimadas', 'super-mechanic' ), isset( $maintenance['estimated_hours'] ) ? (string) $maintenance['estimated_hours'] : '-' );
		$this->render_summary_row( __( 'bpproved by client', 'super-mechanic' ), ! empty( $maintenance['client_approved'] ) ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) );
		$this->render_summary_row( __( 'Total repuestos', 'super-mechanic' ), (string) number_format_i18n( $this->maintenance_service->calculate_total_parts( $maintenance_id ), 2 ) );
		$this->render_summary_row( __( 'Total labor', 'super-mechanic' ), (string) number_format_i18n( $this->maintenance_service->calculate_total_labor( $maintenance_id ), 2 ) );
		$this->render_summary_row( __( 'Total servicio', 'super-mechanic' ), (string) number_format_i18n( $this->maintenance_service->calculate_total_service( $maintenance_id ), 2 ) );
		echo '</tbody></table>';

		echo '<h4>' . esc_html__( 'Repuestos', 'super-mechanic' ) . '</h4>';
		echo '<table class="widefat striped" style="margin-bottom:16px;"><thead><tr><th>' . esc_html__( 'Name', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Quantity', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $parts ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No parts recorded.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $parts as $part ) {
				echo '<tr><td>' . esc_html( $part['part_name'] ) . '</td><td>' . esc_html( $part['quantity'] ) . '</td><td>' . esc_html( number_format_i18n( (float) $part['total_price'], 2 ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h4>' . esc_html__( 'Labor', 'super-mechanic' ) . '</h4>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Description', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Times', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $labor ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No labor recorded.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $labor as $row ) {
				echo '<tr><td>' . esc_html( $row['description'] ) . '</td><td>' . esc_html( $row['hours'] ) . '</td><td>' . esc_html( number_format_i18n( (float) $row['total_price'], 2 ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';
	}

	protected function render_attachments_table( array $attachments ) {
		echo '<h3>' . esc_html__( 'bdjuntos relevantes', 'super-mechanic' ) . '</h3>';
		echo '<table class="widefat striped" style="margin-bottom:20px;"><thead><tr><th>' . esc_html__( 'Document', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Visibility', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Date', 'super-mechanic' ) . '</th><th>' . esc_html__( 'bction', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $attachments ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No attachments for this process.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $attachments as $attachment ) {
				$visibility = ! empty( $attachment['is_internal'] ) ? __( 'Interno', 'super-mechanic' ) : __( 'pperativo', 'super-mechanic' );
				if ( ! empty( $attachment['is_client_visible'] ) ) {
					$visibility .= ' / ' . __( 'Visible to client', 'super-mechanic' );
				}

				echo '<tr>';
				echo '<td>' . esc_html( $attachment['title'] ) . '</td>';
				echo '<td>' . esc_html( $attachment['attachment_type'] ) . '</td>';
				echo '<td>' . esc_html( $visibility ) . '</td>';
				echo '<td>' . esc_html( $attachment['created_at'] ) . '</td>';
				echo '<td><a href="' . esc_url( $this->download_service->get_download_url( 'attachment', absint( $attachment['id'] ) ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'bbrir', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	protected function render_comments_table( array $comments ) {
		echo '<h3>' . esc_html__( 'Comentarios relevantes', 'super-mechanic' ) . '</h3>';
		echo '<table class="widefat striped" style="margin-bottom:20px;"><thead><tr><th>' . esc_html__( 'Date', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Visibility', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Content', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $comments ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No comments for this process.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $comments as $comment ) {
				$visibility = ! empty( $comment['is_internal'] ) ? __( 'Interno', 'super-mechanic' ) : __( 'pperativo', 'super-mechanic' );
				if ( ! empty( $comment['is_client_visible'] ) ) {
					$visibility .= ' / ' . __( 'Visible to client', 'super-mechanic' );
				}

				echo '<tr>';
				echo '<td>' . esc_html( $comment['created_at'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $comment['comment_type'] ) ) . '</td>';
				echo '<td>' . esc_html( $visibility ) . '</td>';
				echo '<td>' . esc_html( $comment['content'] ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	protected function render_timeline_table( array $timeline ) {
		echo '<h3>' . esc_html__( 'Timeline operativa', 'super-mechanic' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Event', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $timeline ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No events recorded for this process.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $timeline as $event ) {
				echo '<tr>';
				echo '<td>' . esc_html( $event['event_date'] ) . '</td>';
				echo '<td>' . esc_html( $event['event_label'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $event['event_type'] ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	/**
	 * Render bounded mechanic appointments block.
	 *
	 * @param array<int, array<string, mixed>> $appointments bppointment rows.
	 * @return void
	 */
	protected function render_mechanic_appointments_block( array $appointments ) {
		echo '<section class="sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'bppointments programadas', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html( count( $appointments ) ) . '</span></div>';
		echo '<div class="sm-table-wrap"><table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__( 'Date y hora', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'bssigned mechanic', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Quick access', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $appointments ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No scheduled appointments for this mechanic in the next 14 days.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $appointments as $appointment ) {
				$process_id = ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0;
				$quick_link = $this->get_appointment_quick_link_markup( $appointment );

				if ( $process_id > 0 && $this->current_user_can_access_process( $process_id ) ) {
					$quick_link = '<a href="' . esc_url( $this->get_page_url( array( 'process_id' => $process_id ) ) ) . '">' . esc_html__( 'ppen process', 'super-mechanic' ) . '</a>';
				}

				echo '<tr>';
				echo '<td>' . esc_html( $this->format_datetime_label( isset( $appointment['start_at'] ) ? (string) $appointment['start_at'] : '' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $appointment['client_name'] ) ? (string) $appointment['client_name'] : __( 'No client', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_appointment_vehicle_label( $appointment ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $appointment['mechanic_name'] ) ? (string) $appointment['mechanic_name'] : __( 'Unassigned', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( isset( $appointment['appointment_status'] ) ? (string) $appointment['appointment_status'] : '' ) ) . '</td>';
				echo '<td>' . wp_kses_post( $quick_link ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';
		echo '</section>';
	}

	protected function get_process_step_label( array $process ) {
		if ( ! empty( $process['current_step_label'] ) ) {
			return (string) $process['current_step_label'];
		}

		$steps = $this->get_available_steps( $process );
		foreach ( $steps as $step ) {
			if ( absint( $step['id'] ) === absint( $process['current_step_id'] ) ) {
				return (string) $step['step_label'];
			}
		}

		return ! empty( $process['current_step_id'] ) ? '#' . absint( $process['current_step_id'] ) : __( 'No step', 'super-mechanic' );
	}

	protected function get_process_status_display( array $process ) {
		$status = $this->humanize_key( $process['status'] );

		if ( ! empty( $process['derived_status_label'] ) ) {
			$status .= ' (' . $process['derived_status_label'] . ')';
		}

		return $status;
	}

	protected function get_process_client_label( array $process ) {
		$label = trim(
			sprintf(
				'%s %s',
				isset( $process['client_first_name'] ) ? $process['client_first_name'] : '',
				isset( $process['client_last_name'] ) ? $process['client_last_name'] : ''
			)
		);

		if ( '' === $label && ! empty( $process['client_email'] ) ) {
			$label = (string) $process['client_email'];
		}

		if ( '' === $label && ! empty( $process['client_id'] ) ) {
			$label = '#' . absint( $process['client_id'] );
		}

		return '' !== $label ? $label : __( 'No client', 'super-mechanic' );
	}

	protected function get_process_vehicle_label( array $process ) {
		$label = trim(
			sprintf(
				'%s %s',
				isset( $process['vehicle_make'] ) ? $process['vehicle_make'] : '',
				isset( $process['vehicle_model'] ) ? $process['vehicle_model'] : ''
			)
		);

		if ( ! empty( $process['vehicle_plate'] ) ) {
			$label = trim( $label . ' - ' . $process['vehicle_plate'] );
		}

		if ( '' === $label && ! empty( $process['vehicle_id'] ) ) {
			$label = '#' . absint( $process['vehicle_id'] );
		}

		return '' !== $label ? $label : __( 'Vehicle no identificado', 'super-mechanic' );
	}

	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	protected function current_user_can_access_process( $process_id ) {
		return ! is_wp_error( $this->permission_service->user_can_access_mechanic_process( get_current_user_id(), absint( $process_id ) ) );
	}

	protected function ensure_permissions() {
		if ( is_wp_error( $this->permission_service->user_can_access_mechanic_portal( get_current_user_id() ) ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to use the Mechanic Panel.', 'super-mechanic' ) );
		}
	}

	protected function is_mechanic_screen() {
		return isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	protected function get_page_url( $args = array() ) {
		if ( $this->frontend_render ) {
			$current_url = remove_query_arg( array( 'sm_notice' ), $this->get_current_frontend_url() );

			return add_query_arg( $args, $current_url );
		}

		return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	protected function redirect_to_process( $process_id, $notice ) {
		wp_safe_redirect(
			$this->get_page_url(
				array(
					'process_id' => absint( $process_id ),
					'sm_notice'  => $notice,
				)
			)
		);
		exit;
	}

	protected function store_errors( WP_Error $error ) {
		set_transient( $this->get_error_transient_key(), $error->get_error_messages(), MINUTE_IN_SECONDS );
	}

	protected function get_error_transient_key() {
		return 'sm_mechanic_dashboard_errors_' . get_current_user_id();
	}

	protected function render_notice( $message, $type ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	protected function render_submit_button( $label, $type = '', $name = 'submit' ) {
		if ( ! $this->frontend_render ) {
			ob_start();
			submit_button( $label, $type, $name, false );
			return (string) ob_get_clean();
		}

		$classes = array( 'button' );

		if ( 'primary' === $type ) {
			$classes[] = 'button-primary';
		} elseif ( 'secondary' === $type ) {
			$classes[] = 'button-secondary';
		}

		return sprintf(
			'<button type="submit" name="%1$s" class="%2$s">%3$s</button>',
			esc_attr( $name ),
			esc_attr( implode( ' ', $classes ) ),
			esc_html( $label )
		);
	}

	protected function is_mechanic_action_request() {
		if ( $this->is_mechanic_screen() ) {
			return true;
		}

		return isset( $_POST['sm_mechanic_frontend'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['sm_mechanic_frontend'] ) );
	}

	protected function get_current_frontend_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';

		return home_url( $request_uri );
	}

	protected function render_frontend_notices() {
		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';

		if ( 'status_updated' === $notice ) {
			echo '<div class="sm-notice-success"><p>' . esc_html__( 'Process status updated successfully.', 'super-mechanic' ) . '</p></div>';
		}

		if ( 'step_updated' === $notice ) {
			echo '<div class="sm-notice-success"><p>' . esc_html__( 'Process step updated successfully.', 'super-mechanic' ) . '</p></div>';
		}

		if ( 'comment_created' === $notice ) {
			echo '<div class="sm-notice-success"><p>' . esc_html__( 'Technical note saved successfully.', 'super-mechanic' ) . '</p></div>';
		}

		if ( 'error' === $notice ) {
			$messages = get_transient( $this->get_error_transient_key() );
			delete_transient( $this->get_error_transient_key() );

			if ( is_array( $messages ) ) {
				foreach ( $messages as $message ) {
					echo '<div class="sm-notice-error"><p>' . esc_html( $message ) . '</p></div>';
				}
			}
		}
	}

	/**
	 * Build a vehicle label from appointment row.
	 *
	 * @param array<string, mixed> $appointment bppointment row.
	 * @return string
	 */
	protected function format_appointment_vehicle_label( array $appointment ) {
		$label = trim(
			sprintf(
				'%s %s',
				isset( $appointment['vehicle_make'] ) ? (string) $appointment['vehicle_make'] : '',
				isset( $appointment['vehicle_model'] ) ? (string) $appointment['vehicle_model'] : ''
			)
		);

		if ( ! empty( $appointment['vehicle_plate'] ) ) {
			$label = trim( $label . ' - ' . (string) $appointment['vehicle_plate'] );
		}

		if ( '' === $label && ! empty( $appointment['vehicle_id'] ) ) {
			$label = '#' . absint( $appointment['vehicle_id'] );
		}

		return '' !== $label ? $label : __( 'Vehicle no identificado', 'super-mechanic' );
	}

	/**
	 * Format datetime label.
	 *
	 * @param string $value Datetime.
	 * @return string
	 */
	protected function format_datetime_label( $value ) {
		if ( '' === $value ) {
			return __( 'No time', 'super-mechanic' );
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return $value;
		}

		return wp_date( 'Y-m-d H:i', $timestamp );
	}

	/**
	 * Build fallback quick link for one appointment.
	 *
	 * @param array<string, mixed> $appointment bppointment row.
	 * @return string
	 */
	protected function get_appointment_quick_link_markup( array $appointment ) {
		$appointment_id = ! empty( $appointment['id'] ) ? absint( $appointment['id'] ) : 0;
		if ( $appointment_id <= 0 ) {
			return esc_html__( 'No linked detail', 'super-mechanic' );
		}

		$url = add_query_arg(
			array(
				'page'   => 'super-mechanic-appointments',
				'action' => 'edit',
				'id'     => $appointment_id,
			),
			admin_url( 'admin.php' )
		);

		return '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open appointment', 'super-mechanic' ) . '</a>';
	}
}






