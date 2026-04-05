<?php
/**
 * Admin dashboard controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Automation\Execution_Log_Service;
use Super_Mechanic\Automation\Operational_Rules_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Users\Admin_Roles_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the admin dashboard.
 */
class Admin_Dashboard_Controller {
	/**
	 * Dashboard service.
	 *
	 * @var Dashboard_Service
	 */
	protected $service;
	/**
	 * Workload service.
	 *
	 * @var Workload_Service
	 */
	protected $workload_service;
	/**
	 * Execution log service.
	 *
	 * @var Execution_Log_Service
	 */
	protected $execution_log_service;
	/**
	 * Operational rules service.
	 *
	 * @var Operational_Rules_Service
	 */
	protected $operational_rules_service;
	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;
	/**
	 * Dashboard request-level cache.
	 *
	 * @var array<string,mixed>
	 */
	protected $dashboard_request_cache = array();
	/**
	 * Profiling enabled for current request.
	 *
	 * @var bool
	 */
	protected $profiling_enabled = false;
	/**
	 * Profiling context label.
	 *
	 * @var string
	 */
	protected $profiling_context = '';
	/**
	 * Profiling request start time.
	 *
	 * @var float
	 */
	protected $profiling_request_started_at = 0.0;
	/**
	 * Profiling marks.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	protected $profiling_marks = array();
	/**
	 * Reassignment feedback notice.
	 *
	 * @var array<string,string>|null
	 */
	protected $reassignment_notice;
	/**
	 * Bulk action feedback notice.
	 *
	 * @var array<string,string>|null
	 */
	protected $bulk_action_notice;
	/**
	 * Controlled auto execution feedback notice.
	 *
	 * @var array<string,string>|null
	 */
	protected $auto_execution_notice;
	/**
	 * Controlled auto execution payload.
	 *
	 * @var array<string,mixed>|null
	 */
	protected $auto_execution_payload;
	/**
	 * Rollback feedback notice.
	 *
	 * @var array<string,string>|null
	 */
	protected $rollback_notice;
	/**
	 * Rules update feedback notice.
	 *
	 * @var array<string,string>|null
	 */
	protected $rules_notice;
	/**
	 * Roles and access admin controller.
	 *
	 * @var Admin_Roles_Controller
	 */
	protected $roles_controller;

	/**
	 * Constructor.
	 *
	 * @param Dashboard_Service|null $service Dashboard service.
	 * @param Workload_Service|null  $workload_service Workload service.
	 */
	public function __construct( Dashboard_Service $service = null, Workload_Service $workload_service = null ) {
		$this->service               = $service ? $service : new Dashboard_Service();
		$this->workload_service      = $workload_service ? $workload_service : new Workload_Service();
		$this->execution_log_service = new Execution_Log_Service();
		$this->operational_rules_service = new Operational_Rules_Service( $this->workload_service );
		$this->business_context_service  = new Business_Context_Service();
		$this->roles_controller          = new Admin_Roles_Controller();
		$this->roles_controller->register_hooks();

		add_action( 'admin_menu', array( $this, 'register_automation_submenu' ), 98 );
		add_action( 'admin_menu', array( $this, 'register_logs_submenu' ), 99 );
		add_action( 'wp_ajax_sm_dashboard_lazy_sections', array( $this, 'ajax_load_dashboard_heavy_sections' ) );
		add_action( 'wp_ajax_sm_dashboard_save_preferences', array( $this, 'ajax_save_dashboard_preferences' ) );
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}
		$this->start_request_profiling( 'dashboard' );

		$this->maybe_handle_operational_reassignment_request();
		$this->maybe_handle_operational_bulk_action_request();
		$this->maybe_handle_controlled_auto_execution_request();
		$this->maybe_handle_controlled_execution_rollback_request();
		$this->maybe_handle_operational_rule_update_request();

		$selected_workload_user_id = isset( $_GET['workload_user_id'] ) ? absint( wp_unslash( $_GET['workload_user_id'] ) ) : get_current_user_id();
		if ( $selected_workload_user_id <= 0 ) {
			$selected_workload_user_id = get_current_user_id();
		}
		$role_context          = $this->get_operational_dashboard_role_context();
		$is_admin_view         = ! empty( $role_context['is_admin'] );
		$is_mechanic_view      = ! empty( $role_context['is_mechanic'] );
		$ui_preferences        = $this->get_dashboard_ui_preferences( get_current_user_id() );
		$this->enqueue_dashboard_lazy_assets( $selected_workload_user_id );
		$business_id = $this->resolve_business_id_for_user( $selected_workload_user_id );
		$timer    = $this->profile_mark_start();
		$workload = $this->workload_service->get_user_workload(
			$selected_workload_user_id,
			array(
				'upcoming_days'    => 7,
				'max_scan'         => 250,
				'limit_per_bucket' => 12,
			)
		);
		$this->profile_mark_end( 'data.workload', $timer );
		$timer          = $this->profile_mark_start();
		$global_summary = $this->get_cached_dashboard_payload(
			'global_operational_summary',
			$business_id,
			30,
			function () use ( $business_id ) {
				return $this->workload_service->get_global_operational_summary( $business_id );
			}
		);
		$this->profile_mark_end( 'data.global_summary', $timer );
		$timer            = $this->profile_mark_start();
		$automation_flags = $this->workload_service->get_operational_automation_flags(
			$business_id,
			$selected_workload_user_id
		);
		$this->profile_mark_end( 'data.automation_flags', $timer );
		$timer            = $this->profile_mark_start();
		$escalation_state = $this->workload_service->get_operational_escalation_state(
			$business_id,
			$selected_workload_user_id
		);
		$this->profile_mark_end( 'data.escalation', $timer );
		$timer                       = $this->profile_mark_start();
		$operational_recommendations = $this->workload_service->get_operational_recommendations(
			$business_id,
			$selected_workload_user_id
		);
		$this->profile_mark_end( 'data.recommendations', $timer );
		$timer                   = $this->profile_mark_start();
		$operational_assignments = $this->workload_service->get_operational_assignments(
			$business_id
		);
		$this->profile_mark_end( 'data.assignments', $timer );
		$timer              = $this->profile_mark_start();
		$automation_console = $this->workload_service->get_operational_automation_console(
			$business_id,
			$selected_workload_user_id
		);
		$this->profile_mark_end( 'data.automation_console', $timer );
		$timer            = $this->profile_mark_start();
		$assisted_actions = $this->workload_service->get_operational_assisted_actions(
			$business_id,
			$selected_workload_user_id
		);
		$this->profile_mark_end( 'data.assisted_actions', $timer );
		$timer                    = $this->profile_mark_start();
		$operational_bulk_actions = $this->workload_service->get_operational_bulk_actions(
			$business_id,
			$selected_workload_user_id
		);
		$this->profile_mark_end( 'data.bulk_actions', $timer );
		$action_center_section = isset( $_GET['section'] ) ? sanitize_key( (string) wp_unslash( $_GET['section'] ) ) : '';
		$action_center_filter  = isset( $_GET['filter'] ) ? sanitize_key( (string) wp_unslash( $_GET['filter'] ) ) : '';
		$has_operational_data  = $this->has_operational_data( $global_summary, $workload );
		$noise_coverage        = $this->build_operational_noise_coverage(
			$assisted_actions,
			$operational_assignments,
			$operational_bulk_actions,
			$escalation_state
		);

		$dashboard_shell_classes = 'wrap sm-admin-shell';
		if ( ! empty( $ui_preferences['compact_mode'] ) ) {
			$dashboard_shell_classes .= ' is-compact-mode';
		}

		echo '<div class="' . esc_attr( $dashboard_shell_classes ) . '">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Dashboard', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Operational overview for priorities, actions, and current workload.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		$this->render_reassignment_notice();
		$this->render_bulk_action_notice();
		echo '<span class="sm-badge sm-badge-primary">' . esc_html__( 'Operations hub', 'super-mechanic' ) . '</span>';
		echo '</div>';

		echo '<div class="sm-notice-card"><strong>' . esc_html__( 'Live summary', 'super-mechanic' ) . '</strong><p class="sm-card-copy">' . esc_html__( 'Real-time operational metrics with no flow changes.', 'super-mechanic' ) . '</p></div>';
		$this->render_dashboard_preferences_panel( $ui_preferences );
		if ( ! $has_operational_data ) {
			echo '<div class="sm-notice-card">';
			echo '<strong>' . esc_html__( 'This business has no operational data yet.', 'super-mechanic' ) . '</strong>';
			echo '<p class="sm-card-copy">' . esc_html__( 'Create CRM tasks, active processes, appointments, or operational signals to activate workload, automation, and action features.', 'super-mechanic' ) . '</p>';
			echo '</div>';
		}
		// 1) Quick actions first.
		echo '<section class="sm-card sm-section sm-quick-actions-card">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Quick actions', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Operational shortcuts', 'super-mechanic' ) . '</span></div>';
		echo '<div class="sm-page-actions">';
		echo '<a class="button button-primary" href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-processes', array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Create process', 'super-mechanic' ) . '</a>';
		echo '<a class="button button-secondary" href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-processes', array( 'filter_process_type' => 'maintenance' ) ) ) . '">' . esc_html__( 'Open maintenance', 'super-mechanic' ) . '</a>';
		if ( $is_admin_view ) {
			echo '<a class="button button-secondary" href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-processes', array( 'action' => 'new', 'process_type' => 'maintenance' ) ) ) . '">' . esc_html__( 'Create quote', 'super-mechanic' ) . '</a>';
			echo '<a class="button button-secondary" href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-financial-invoices' ) ) . '">' . esc_html__( 'Create invoice', 'super-mechanic' ) . '</a>';
			echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=super-mechanic-automation' ) ) . '">' . esc_html__( 'Open Automation Center', 'super-mechanic' ) . '</a>';
		}
		echo '</div>';
		echo '</section>';

		if ( $is_admin_view ) {
			$timer = $this->profile_mark_start();
			// 2) KPI header.
			$this->render_operational_kpi_header( $global_summary );
			$this->profile_mark_end( 'render.kpi_header', $timer );
		}
		$timer = $this->profile_mark_start();
		// 3) Critical strip.
		$this->render_operational_critical_strip( $escalation_state );
		$this->profile_mark_end( 'render.critical_strip', $timer );
		$timer = $this->profile_mark_start();
		// 4) Action center.
		$this->render_operational_action_center(
			$assisted_actions,
			$operational_assignments,
			$operational_bulk_actions,
			$automation_console,
			$action_center_section,
			$action_center_filter,
			$noise_coverage
		);
		$this->profile_mark_end( 'render.action_center', $timer );
		$this->render_preference_managed_block_start( 'recommendations', __( 'Secondary block', 'super-mechanic' ), $ui_preferences );
		$timer = $this->profile_mark_start();
		// 5) Smart suggestions (max 3).
		$this->render_operational_recommendations( $operational_recommendations, 3, $noise_coverage );
		$this->profile_mark_end( 'render.recommendations', $timer );
		$this->render_preference_managed_block_end();
		$timer = $this->profile_mark_start();
		// 6) My workload.
		$this->render_workload_section( $workload );
		$this->profile_mark_end( 'render.workload', $timer );
		if ( $is_mechanic_view ) {
			echo '<div class="sm-notice-card sm-notice-card-inline">';
			echo '<strong>' . esc_html__( 'Focused mechanic view enabled.', 'super-mechanic' ) . '</strong>';
			echo '<p class="sm-card-copy">' . esc_html__( 'Detailed business and automation sections are hidden here to keep the dashboard focused on immediate operational work.', 'super-mechanic' ) . '</p>';
			echo '</div>';
		}

		if ( $is_admin_view ) {
			$this->render_preference_managed_block_start( 'automation_summary', __( 'Secondary block', 'super-mechanic' ), $ui_preferences );
			$timer = $this->profile_mark_start();
			// 7) Automation summary compact.
			$this->render_automation_summary_compact( $automation_flags );
			$this->profile_mark_end( 'render.automation_summary', $timer );
			$this->render_preference_managed_block_end();
		}
		if ( $is_admin_view && ! $is_mechanic_view ) {
			$this->render_preference_managed_block_start( 'secondary_data', __( 'Secondary block', 'super-mechanic' ), $ui_preferences );
			echo '<section class="sm-card sm-section sm-lazy-shell" id="sm-dashboard-lazy-shell" data-sm-lazy="heavy-sections">';
			echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Secondary operational data', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Deferred by profiling', 'super-mechanic' ) . '</span></div>';
			echo '<p class="sm-card-copy">' . esc_html__( 'Detailed sections load after core operational blocks to preserve focus and perceived speed.', 'super-mechanic' ) . '</p>';
			echo '<div id="sm-dashboard-lazy-placeholder" class="sm-lazy-placeholder">' . esc_html__( 'Loading section...', 'super-mechanic' ) . '</div>';
			echo '<div id="sm-dashboard-lazy-content" class="sm-lazy-content" aria-live="polite"></div>';
			echo '<noscript><p class="description">' . esc_html__( 'Enable JavaScript to load extended operational sections.', 'super-mechanic' ) . '</p></noscript>';
			echo '</section>';
			$this->render_preference_managed_block_end();
		}

		$this->render_profiling_panel();
		echo '</div>';
	}

	/**
	 * Handle lazy dashboard heavy sections request.
	 *
	 * @return void
	 */
	public function ajax_load_dashboard_heavy_sections() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to load this dashboard section.', 'super-mechanic' ),
				),
				403
			);
		}
		$role_context = $this->get_operational_dashboard_role_context();
		if ( empty( $role_context['is_admin'] ) || ! empty( $role_context['is_mechanic'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'This dashboard section is only available for administrative operational views.', 'super-mechanic' ),
				),
				403
			);
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'sm_dashboard_lazy_sections' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security validation failed while loading dashboard section.', 'super-mechanic' ),
				),
				403
			);
		}

		$selected_workload_user_id = isset( $_POST['workload_user_id'] ) ? absint( wp_unslash( $_POST['workload_user_id'] ) ) : get_current_user_id();
		if ( $selected_workload_user_id <= 0 ) {
			$selected_workload_user_id = get_current_user_id();
		}

		$this->start_request_profiling( 'dashboard_lazy_ajax' );
		$timer = $this->profile_mark_start();
		$html = $this->get_dashboard_heavy_sections_html( $selected_workload_user_id );
		$this->profile_mark_end( 'lazy.heavy_sections', $timer );
		wp_send_json_success(
			array(
				'html'    => $html,
				'profile' => $this->get_profile_export_payload(),
			)
		);
	}

	/**
	 * Persist lightweight dashboard UI preferences for current user.
	 *
	 * @return void
	 */
	public function ajax_save_dashboard_preferences() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to update dashboard preferences.', 'super-mechanic' ),
				),
				403
			);
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'sm_dashboard_ui_preferences' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security validation failed while saving preferences.', 'super-mechanic' ),
				),
				403
			);
		}

		$raw_collapsed = isset( $_POST['collapsed_blocks'] ) ? wp_unslash( $_POST['collapsed_blocks'] ) : '[]';
		$raw_hidden    = isset( $_POST['hidden_secondary_blocks'] ) ? wp_unslash( $_POST['hidden_secondary_blocks'] ) : '[]';
		$raw_compact   = isset( $_POST['compact_mode'] ) ? wp_unslash( $_POST['compact_mode'] ) : '0';

		$decoded_collapsed = json_decode( is_string( $raw_collapsed ) ? $raw_collapsed : '[]', true );
		$decoded_hidden    = json_decode( is_string( $raw_hidden ) ? $raw_hidden : '[]', true );

		$preferences = $this->sanitize_dashboard_ui_preferences(
			array(
				'collapsed_blocks'        => is_array( $decoded_collapsed ) ? $decoded_collapsed : array(),
				'hidden_secondary_blocks' => is_array( $decoded_hidden ) ? $decoded_hidden : array(),
				'compact_mode'            => absint( $raw_compact ),
			)
		);

		update_user_meta( get_current_user_id(), 'sm_dashboard_ui_preferences', $preferences );

		wp_send_json_success(
			array(
				'preferences' => $preferences,
			)
		);
	}

	/**
	 * Handle manual reassignment request from operational assignment block.
	 *
	 * @return void
	 */
	protected function maybe_handle_operational_reassignment_request() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			return;
		}

		$action = isset( $_POST['sm_operational_reassign_action'] ) ? sanitize_key( (string) wp_unslash( $_POST['sm_operational_reassign_action'] ) ) : '';
		if ( 'execute' !== $action ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			$this->reassignment_notice = array(
				'type'    => 'error',
				'message' => __( 'You are not allowed to execute reassignment.', 'super-mechanic' ),
			);
			return;
		}

		$nonce = isset( $_POST['sm_operational_reassign_nonce'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['sm_operational_reassign_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'sm_operational_reassign' ) ) {
			$this->reassignment_notice = array(
				'type'    => 'error',
				'message' => __( 'Security validation failed for reassignment action.', 'super-mechanic' ),
			);
			return;
		}

		$business_id = isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0;
		$from_user   = isset( $_POST['from_user'] ) ? absint( wp_unslash( $_POST['from_user'] ) ) : 0;
		$to_user     = isset( $_POST['to_user'] ) ? absint( wp_unslash( $_POST['to_user'] ) ) : 0;
		$entity_type = isset( $_POST['entity_type'] ) ? sanitize_key( (string) wp_unslash( $_POST['entity_type'] ) ) : '';
		$entity_id   = isset( $_POST['entity_id'] ) ? absint( wp_unslash( $_POST['entity_id'] ) ) : 0;
		$result      = $this->workload_service->execute_operational_reassignment( $business_id, $from_user, $to_user, $entity_type, $entity_id );

		if ( is_wp_error( $result ) ) {
			$this->reassignment_notice = array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			);
			return;
		}

		$this->reassignment_notice = array(
			'type'    => 'success',
			'message' => __( 'Operational reassignment executed successfully.', 'super-mechanic' ),
		);
	}

	/**
	 * Render reassignment feedback notice.
	 *
	 * @return void
	 */
	protected function render_reassignment_notice() {
		if ( empty( $this->reassignment_notice ) || ! is_array( $this->reassignment_notice ) ) {
			return;
		}

		$type    = isset( $this->reassignment_notice['type'] ) ? sanitize_key( (string) $this->reassignment_notice['type'] ) : 'success';
		$message = isset( $this->reassignment_notice['message'] ) ? sanitize_text_field( (string) $this->reassignment_notice['message'] ) : '';
		if ( '' === $message ) {
			return;
		}

		$class = 'notice notice-success is-dismissible';
		if ( 'error' === $type ) {
			$class = 'notice notice-error is-dismissible';
		}

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Handle manual operational bulk action request.
	 *
	 * @return void
	 */
	protected function maybe_handle_operational_bulk_action_request() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			return;
		}

		$action_key = isset( $_POST['sm_operational_bulk_action'] ) ? sanitize_key( (string) wp_unslash( $_POST['sm_operational_bulk_action'] ) ) : '';
		if ( '' === $action_key ) {
			return;
		}

		$business_id    = isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0;
		$entity_type    = isset( $_POST['entity_type'] ) ? sanitize_key( (string) wp_unslash( $_POST['entity_type'] ) ) : '';
		$ids_raw        = isset( $_POST['ids'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['ids'] ) ) : '';
		$ids            = '' !== $ids_raw ? array_filter( array_map( 'absint', explode( ',', $ids_raw ) ) ) : array();
		$target_user_id = isset( $_POST['target_user_id'] ) ? absint( wp_unslash( $_POST['target_user_id'] ) ) : 0;
		$result         = $this->workload_service->execute_operational_bulk_action( $business_id, $action_key, $entity_type, $ids, $target_user_id );

		if ( is_wp_error( $result ) ) {
			$this->bulk_action_notice = array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			);
			return;
		}

		$status       = isset( $result['status'] ) ? sanitize_key( (string) $result['status'] ) : 'success';
		$success_count = isset( $result['success_count'] ) ? absint( $result['success_count'] ) : 0;
		$failed_count  = isset( $result['failed_count'] ) ? absint( $result['failed_count'] ) : 0;
		if ( 'failed' === $status ) {
			$this->bulk_action_notice = array(
				'type'    => 'error',
				'message' => __( 'Bulk action failed for all selected items.', 'super-mechanic' ),
			);
			return;
		}

		if ( 'partial' === $status ) {
			$this->bulk_action_notice = array(
				'type'    => 'error',
				'message' => sprintf(
					/* translators: 1: success count, 2: failed count. */
					__( 'Bulk action executed partially: %1$d succeeded, %2$d failed.', 'super-mechanic' ),
					$success_count,
					$failed_count
				),
			);
			return;
		}

		$this->bulk_action_notice = array(
			'type'    => 'success',
			'message' => sprintf(
				/* translators: %d success count. */
				__( 'Bulk action executed successfully for %d items.', 'super-mechanic' ),
				$success_count
			),
		);
	}

	/**
	 * Render bulk action feedback notice.
	 *
	 * @return void
	 */
	protected function render_bulk_action_notice() {
		if ( empty( $this->bulk_action_notice ) || ! is_array( $this->bulk_action_notice ) ) {
			return;
		}

		$type    = isset( $this->bulk_action_notice['type'] ) ? sanitize_key( (string) $this->bulk_action_notice['type'] ) : 'success';
		$message = isset( $this->bulk_action_notice['message'] ) ? sanitize_text_field( (string) $this->bulk_action_notice['message'] ) : '';
		if ( '' === $message ) {
			return;
		}

		$class = 'notice notice-success is-dismissible';
		if ( 'error' === $type ) {
			$class = 'notice notice-error is-dismissible';
		}

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Handle controlled auto execution request (explicit run only).
	 *
	 * @return void
	 */
	protected function maybe_handle_controlled_auto_execution_request() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			return;
		}

		$action = isset( $_POST['sm_controlled_auto_execution_action'] ) ? sanitize_key( (string) wp_unslash( $_POST['sm_controlled_auto_execution_action'] ) ) : '';
		if ( 'run' !== $action ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			$this->auto_execution_notice = array(
				'type'    => 'error',
				'message' => __( 'You are not allowed to run controlled auto execution.', 'super-mechanic' ),
			);
			return;
		}

		$nonce = isset( $_POST['sm_controlled_auto_execution_nonce'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['sm_controlled_auto_execution_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'sm_controlled_auto_execution' ) ) {
			$this->auto_execution_notice = array(
				'type'    => 'error',
				'message' => __( 'Security validation failed for controlled auto execution.', 'super-mechanic' ),
			);
			return;
		}

		$business_id = isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0;
		$user_id     = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : get_current_user_id();
		$result      = $this->workload_service->run_controlled_auto_execution( $business_id, $user_id );

		if ( is_wp_error( $result ) ) {
			$this->auto_execution_notice = array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			);
			return;
		}

		$this->auto_execution_payload = is_array( $result ) ? $result : null;
		$executed_rules = isset( $result['summary']['executed_rules'] ) ? absint( $result['summary']['executed_rules'] ) : 0;
		$blocked_rules  = isset( $result['summary']['blocked_rules'] ) ? absint( $result['summary']['blocked_rules'] ) : 0;

		if ( $executed_rules > 0 ) {
			$this->auto_execution_notice = array(
				'type'    => 'success',
				'message' => sprintf(
					/* translators: %1$d executed rules, %2$d blocked rules. */
					__( 'Controlled auto execution finished: %1$d rules executed, %2$d blocked.', 'super-mechanic' ),
					$executed_rules,
					$blocked_rules
				),
			);
			return;
		}

		$this->auto_execution_notice = array(
			'type'    => 'error',
			'message' => __( 'Controlled auto execution did not run any rule. Review blocking reasons below.', 'super-mechanic' ),
		);
	}

	/**
	 * Render controlled auto execution feedback notice.
	 *
	 * @return void
	 */
	protected function render_controlled_auto_execution_notice() {
		if ( empty( $this->auto_execution_notice ) || ! is_array( $this->auto_execution_notice ) ) {
			return;
		}

		$type    = isset( $this->auto_execution_notice['type'] ) ? sanitize_key( (string) $this->auto_execution_notice['type'] ) : 'success';
		$message = isset( $this->auto_execution_notice['message'] ) ? sanitize_text_field( (string) $this->auto_execution_notice['message'] ) : '';
		if ( '' === $message ) {
			return;
		}

		$class = 'notice notice-success is-dismissible';
		if ( 'error' === $type ) {
			$class = 'notice notice-error is-dismissible';
		}

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Handle controlled rollback request.
	 *
	 * @return void
	 */
	protected function maybe_handle_controlled_execution_rollback_request() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			return;
		}

		$action = isset( $_POST['sm_controlled_execution_rollback_action'] ) ? sanitize_key( (string) wp_unslash( $_POST['sm_controlled_execution_rollback_action'] ) ) : '';
		if ( 'run' !== $action ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			$this->rollback_notice = array(
				'type'    => 'error',
				'message' => __( 'You are not allowed to rollback controlled execution.', 'super-mechanic' ),
			);
			return;
		}

		$nonce = isset( $_POST['sm_controlled_execution_rollback_nonce'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['sm_controlled_execution_rollback_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'sm_controlled_execution_rollback' ) ) {
			$this->rollback_notice = array(
				'type'    => 'error',
				'message' => __( 'Security validation failed for rollback action.', 'super-mechanic' ),
			);
			return;
		}

		$business_id  = isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0;
		$action_type  = isset( $_POST['action_type'] ) ? sanitize_key( (string) wp_unslash( $_POST['action_type'] ) ) : '';
		$snapshot_key = isset( $_POST['snapshot_key'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['snapshot_key'] ) ) : '';
		$result       = $this->workload_service->rollback_controlled_execution(
			$business_id,
			$action_type,
			array(
				'snapshot_key' => $snapshot_key,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->rollback_notice = array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			);
			return;
		}

		$status        = isset( $result['status'] ) ? sanitize_key( (string) $result['status'] ) : 'failed';
		$success_count = isset( $result['success_count'] ) ? absint( $result['success_count'] ) : 0;
		$failed_count  = isset( $result['failed_count'] ) ? absint( $result['failed_count'] ) : 0;

		if ( 'success' === $status ) {
			$this->rollback_notice = array(
				'type'    => 'success',
				'message' => sprintf(
					/* translators: %d success count. */
					__( 'Rollback completed successfully for %d items.', 'super-mechanic' ),
					$success_count
				),
			);
			return;
		}

		if ( 'partial' === $status ) {
			$this->rollback_notice = array(
				'type'    => 'error',
				'message' => sprintf(
					/* translators: 1: success count, 2: failed count. */
					__( 'Rollback executed partially: %1$d reverted, %2$d failed.', 'super-mechanic' ),
					$success_count,
					$failed_count
				),
			);
			return;
		}

		$this->rollback_notice = array(
			'type'    => 'error',
			'message' => __( 'Rollback could not revert any item.', 'super-mechanic' ),
		);
	}

	/**
	 * Render rollback feedback notice.
	 *
	 * @return void
	 */
	protected function render_controlled_execution_rollback_notice() {
		if ( empty( $this->rollback_notice ) || ! is_array( $this->rollback_notice ) ) {
			return;
		}

		$type    = isset( $this->rollback_notice['type'] ) ? sanitize_key( (string) $this->rollback_notice['type'] ) : 'success';
		$message = isset( $this->rollback_notice['message'] ) ? sanitize_text_field( (string) $this->rollback_notice['message'] ) : '';
		if ( '' === $message ) {
			return;
		}

		$class = 'notice notice-success is-dismissible';
		if ( 'error' === $type ) {
			$class = 'notice notice-error is-dismissible';
		}

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Handle basic operational rule update request.
	 *
	 * @return void
	 */
	protected function maybe_handle_operational_rule_update_request() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			return;
		}

		$action = isset( $_POST['sm_operational_rule_action'] ) ? sanitize_key( (string) wp_unslash( $_POST['sm_operational_rule_action'] ) ) : '';
		if ( 'save_basic' !== $action ) {
			return;
		}

			if ( ! current_user_can( 'sm_manage_plugin' ) ) {
				$this->rules_notice = array(
					'type'    => 'error',
					'message' => __( 'Invalid rule configuration. You are not allowed to update operational rules.', 'super-mechanic' ),
				);
				return;
			}

		$nonce = isset( $_POST['sm_operational_rule_nonce'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['sm_operational_rule_nonce'] ) ) : '';
			if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'sm_operational_rule_save' ) ) {
				$this->rules_notice = array(
					'type'    => 'error',
					'message' => __( 'Invalid rule configuration. Security validation failed.', 'super-mechanic' ),
				);
				return;
			}

		$business_id    = isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0;
		$rule_key       = isset( $_POST['rule_key'] ) ? sanitize_key( (string) wp_unslash( $_POST['rule_key'] ) ) : '';
		$enabled_raw    = isset( $_POST['enabled'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['enabled'] ) ) : null;
		$execution_mode = isset( $_POST['execution_mode'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['execution_mode'] ) ) : null;
		$max_items_auto = isset( $_POST['max_items_auto'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['max_items_auto'] ) ) : null;

			$result  = $this->operational_rules_service->save_basic_rule_config( $business_id, $rule_key, $enabled_raw, $execution_mode, $max_items_auto );
			if ( is_wp_error( $result ) ) {
				$error_code = $result->get_error_code();
				if ( in_array( $error_code, array( 'invalid_rule_key', 'invalid_execution_mode', 'invalid_max_items_auto', 'invalid_enabled', 'invalid_business_id' ), true ) ) {
					$this->rules_notice = array(
						'type'    => 'error',
						'message' => __( 'Invalid rule configuration. Please review the selected mode and limits.', 'super-mechanic' ),
					);
					return;
				}

				$this->rules_notice = array(
					'type'    => 'error',
					'message' => $result->get_error_message(),
			);
			return;
		}

			$this->rules_notice = array(
				'type'    => 'success',
				'message' => __( 'Rule updated successfully.', 'super-mechanic' ),
			);
		}

	/**
	 * Render operational rules update notice.
	 *
	 * @return void
	 */
	protected function render_operational_rules_update_notice() {
		if ( empty( $this->rules_notice ) || ! is_array( $this->rules_notice ) ) {
			return;
		}

		$type    = isset( $this->rules_notice['type'] ) ? sanitize_key( (string) $this->rules_notice['type'] ) : 'success';
		$message = isset( $this->rules_notice['message'] ) ? sanitize_text_field( (string) $this->rules_notice['message'] ) : '';
		if ( '' === $message ) {
			return;
		}

		$class = 'notice notice-success is-dismissible';
		if ( 'error' === $type ) {
			$class = 'notice notice-error is-dismissible';
		}

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Render a KrI card.
	 *
	 * @param string     $label    Card label.
	 * @param string|int $value    Card value.
	 * @param string     $footnote Optional footnote.
	 * @return void
	 */
	protected function render_kpi_card( $label, $value, $footnote = '', $url = '' ) {
		$tag = '' !== $url ? 'a' : 'article';
		echo '<' . $tag . ' class="sm-card sm-kpi-card"' . ( '' !== $url ? ' href="' . esc_url( $url ) . '" style="text-decoration:none;color:inherit;"' : '' ) . '>';
		echo '<span class="sm-kpi-label">' . esc_html( $label ) . '</span>';
		echo '<strong class="sm-kpi-value">' . esc_html( $value ) . '</strong>';
		if ( '' !== $footnote ) {
			echo '<p class="sm-kpi-footnote">' . esc_html( $footnote ) . '</p>';
		}
		echo '</' . $tag . '>';
	}

	/**
	 * Render a compact status badge.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	protected function render_status_badge( $status ) {
		$class = 'sm-badge sm-badge-neutral';

		if ( in_array( $status, array( 'open', 'in_progress', 'active' ), true ) ) {
			$class = 'sm-badge sm-badge-primary';
		} elseif ( in_array( $status, array( 'completed', 'paid', 'ready_for_delivery' ), true ) ) {
			$class = 'sm-badge sm-badge-success';
		} elseif ( in_array( $status, array( 'pending', 'draft', 'sent' ), true ) ) {
			$class = 'sm-badge sm-badge-warning';
		} elseif ( in_array( $status, array( 'cancelled', 'rejected', 'overdue' ), true ) ) {
			$class = 'sm-badge sm-badge-danger';
		}

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $this->humanize_key( $status ) ) . '</span>';
	}

	/**
	 * Render execution mode badge.
	 *
	 * @param string $mode Execution mode.
	 * @return string
	 */
	protected function render_execution_mode_badge( $mode ) {
		$mode  = sanitize_key( (string) $mode );
		$class = 'sm-badge sm-badge-neutral';
		$label = __( 'Manual', 'super-mechanic' );

		if ( 'confirmable' === $mode ) {
			$class = 'sm-badge sm-badge-warning';
			$label = __( 'Confirmable', 'super-mechanic' );
		} elseif ( 'auto' === $mode ) {
			$class = 'sm-badge sm-badge-danger';
			$label = __( 'Auto', 'super-mechanic' );
		} elseif ( 'manual' === $mode ) {
			$class = 'sm-badge sm-badge-neutral';
			$label = __( 'Manual', 'super-mechanic' );
		}

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Render a compact summary table.
	 *
	 * @param array  $rows         Summary rows.
	 * @param string $label_header Column header.
	 * @return void
	 */
	protected function render_simple_summary_table( $rows, $label_header, $filter_key = '' ) {
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html( $label_header ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="2">' . esc_html__( 'No data available for this section yet.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$label_markup = wp_kses_post( $this->render_status_badge( $row['label'] ) );
				if ( '' !== $filter_key ) {
					$label_markup = '<a href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-processes', array( 'filter_' . $filter_key => $row['label'] ) ) ) . '">' . $label_markup . '</a>';
				}
				echo '<tr><td>' . $label_markup . '</td><td>' . esc_html( $row['total'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render compact appointments table.
	 *
	 * @param array<int, array<string, mixed>> $rows Appointment rows.
	 * @param string                            $empty_message Empty state.
	 * @return void
	 */
	protected function render_appointments_table( $rows, $empty_message ) {
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Time', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Mechanic', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Action', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="6">' . esc_html( $empty_message ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$start_label = $this->format_datetime_label( isset( $row['start_at'] ) ? (string) $row['start_at'] : '' );
				$client      = ! empty( $row['client_name'] ) ? (string) $row['client_name'] : __( 'No client', 'super-mechanic' );
				$mechanic    = ! empty( $row['mechanic_name'] ) ? (string) $row['mechanic_name'] : __( 'Unassigned mechanic', 'super-mechanic' );
				$status      = isset( $row['appointment_status'] ) ? (string) $row['appointment_status'] : '';
				$detail_url  = $this->get_admin_page_url(
					'super-mechanic-appointments',
					array(
						'action' => 'edit',
						'id'     => absint( isset( $row['id'] ) ? $row['id'] : 0 ),
					)
				);

				echo '<tr>';
				echo '<td>' . esc_html( $start_label ) . '</td>';
				echo '<td>' . esc_html( $client ) . '</td>';
				echo '<td>' . esc_html( $this->format_vehicle_label( $row ) ) . '</td>';
				echo '<td>' . esc_html( $mechanic ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_status_badge( $status ) ) . '</td>';
				echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html__( 'View appointment', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render quick links for a process row.
	 *
	 * @param int    $process_id Process ID.
	 * @param string $process_type Process type.
	 * @return string
	 */
	protected function render_process_quick_links( $process_id, $process_type ) {
		$links = array();

		if ( 'maintenance' === $process_type ) {
			$links[] = '<a href="' . esc_url( $this->get_process_tab_url( $process_id, 'maintenance' ) ) . '">' . esc_html__( 'Maintenance', 'super-mechanic' ) . '</a>';
			$links[] = '<a href="' . esc_url( $this->get_process_tab_url( $process_id, 'quote' ) ) . '">' . esc_html__( 'Quote', 'super-mechanic' ) . '</a>';
		}

		$links[] = '<a href="' . esc_url( $this->get_process_tab_url( $process_id, 'invoice' ) ) . '">' . esc_html__( 'Invoice', 'super-mechanic' ) . '</a>';

		return implode( ' | ', $links );
	}

	/**
	 * Build process edit URL for a specific tab.
	 *
	 * @param int    $process_id Process ID.
	 * @param string $tab Process tab.
	 * @return string
	 */
	protected function get_process_tab_url( $process_id, $tab ) {
		return $this->get_admin_page_url(
			'super-mechanic-processes',
			array(
				'action' => 'edit',
				'id'     => absint( $process_id ),
				'tab'    => sanitize_key( $tab ),
			)
		);
	}

	/**
	 * Build admin page URLs.
	 *
	 * @param string               $page_slug rage slug.
	 * @param array<string, mixed> $args      Extra args.
	 * @return string
	 */
	protected function get_admin_page_url( $page_slug, $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => $page_slug,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Humanize an internal key.
	 *
	 * @param string $value Raw key.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/**
	 * Format rule metadata for readable admin display.
	 *
	 * @param mixed $data Rule metadata payload.
	 * @return string
	 */
	protected function format_rule_meta_display( $data ) {
		if ( ! is_array( $data ) || empty( $data ) ) {
			return '&mdash;';
		}

		$label_map = array(
			'overdue_tasks'    => __( 'Overdue tasks', 'super-mechanic' ),
			'overloaded_users' => __( 'Overloaded users', 'super-mechanic' ),
			'critical_flags'   => __( 'Critical flags', 'super-mechanic' ),
			'max_items_auto'   => __( 'Max auto items', 'super-mechanic' ),
		);

		$lines = array();
		foreach ( $data as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}

			$label = isset( $label_map[ $key ] ) ? $label_map[ $key ] : $this->humanize_key( $key );
			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} elseif ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			} else {
				$value = (string) $value;
			}

			$lines[] = esc_html( $label . ': ' . $value );
		}

		if ( empty( $lines ) ) {
			return '&mdash;';
		}

		return implode( '<br>', $lines );
	}

	/**
	 * Format a vehicle label.
	 *
	 * @param array<string, mixed> $vehicle Vehicle-like row.
	 * @return string
	 */
	protected function format_vehicle_label( $vehicle ) {
		$make  = ! empty( $vehicle['brand'] ) ? $vehicle['brand'] : ( ! empty( $vehicle['make'] ) ? $vehicle['make'] : ( ! empty( $vehicle['vehicle_make'] ) ? $vehicle['vehicle_make'] : '' ) );
		$model = ! empty( $vehicle['model'] ) ? $vehicle['model'] : ( ! empty( $vehicle['vehicle_model'] ) ? $vehicle['vehicle_model'] : '' );
		$plate = ! empty( $vehicle['plate'] ) ? $vehicle['plate'] : ( ! empty( $vehicle['vehicle_plate'] ) ? $vehicle['vehicle_plate'] : '' );
		$label = trim( $make . ' ' . $model );
		if ( $plate ) {
			$label .= ' - ' . $plate;
		}

		return $label ? $label : __( 'Unidentified vehicle', 'super-mechanic' );
	}

	/**
	 * Format datetime into operational label.
	 *
	 * @param string $value Datetime value.
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
	 * Render global operational summary section.
	 *
	 * @param array<string,int> $summary Global summary payload.
	 * @return void
	 */
	protected function render_global_operational_summary( array $summary ) {
		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Resumen Operativo Global', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Global', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Business-level aggregated metrics for operational load and critical points.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards sm-grid-cards-compact sm-secondary-metrics">';
		$this->render_kpi_card(
			__( 'Pending CRM tasks', 'super-mechanic' ),
			isset( $summary['tasks_pending_total'] ) ? absint( $summary['tasks_pending_total'] ) : 0,
			__( 'Open workload in CRM', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Overdue CRM tasks', 'super-mechanic' ),
			isset( $summary['tasks_overdue_total'] ) ? absint( $summary['tasks_overdue_total'] ) : 0,
			__( 'Immediate attention required', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Active operational signals', 'super-mechanic' ),
			isset( $summary['alerts_active_total'] ) ? absint( $summary['alerts_active_total'] ) : 0,
			__( 'Pipeline-equivalent critical/attention signals', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Active processes', 'super-mechanic' ),
			isset( $summary['processes_active_total'] ) ? absint( $summary['processes_active_total'] ) : 0,
			__( 'Operational pipeline currently open', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Upcoming appointments', 'super-mechanic' ),
			isset( $summary['appointments_upcoming_total'] ) ? absint( $summary['appointments_upcoming_total'] ) : 0,
			__( 'Near-term scheduled work', 'super-mechanic' )
		);
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render compact KPI header for daily operations.
	 *
	 * @param array<string,int> $summary Global summary payload.
	 * @return void
	 */
	protected function render_operational_kpi_header( array $summary ) {
		echo '<section class="sm-card sm-section sm-kpi-header-compact">';
		echo '<div class="sm-grid sm-grid-cards sm-grid-cards-compact">';
		$this->render_kpi_card(
			__( 'Pending CRM tasks', 'super-mechanic' ),
			isset( $summary['tasks_pending_total'] ) ? absint( $summary['tasks_pending_total'] ) : 0,
			'',
			$this->get_admin_page_url( 'super-mechanic-crm-pipeline' )
		);
		$this->render_kpi_card(
			__( 'Overdue CRM tasks', 'super-mechanic' ),
			isset( $summary['tasks_overdue_total'] ) ? absint( $summary['tasks_overdue_total'] ) : 0,
			'',
			$this->get_admin_page_url( 'super-mechanic-crm-pipeline' )
		);
		$this->render_kpi_card(
			__( 'Active processes', 'super-mechanic' ),
			isset( $summary['processes_active_total'] ) ? absint( $summary['processes_active_total'] ) : 0,
			'',
			$this->get_admin_page_url( 'super-mechanic-processes', array( 'filter_status' => 'open' ) )
		);
		$this->render_kpi_card(
			__( 'Upcoming appointments', 'super-mechanic' ),
			isset( $summary['appointments_upcoming_total'] ) ? absint( $summary['appointments_upcoming_total'] ) : 0,
			'',
			$this->get_admin_page_url( 'super-mechanic-appointments' )
		);
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render compact critical strip replacing heavy escalation table.
	 *
	 * @param array<string,mixed> $escalation_state Escalation payload.
	 * @return void
	 */
	protected function render_operational_critical_strip( array $escalation_state ) {
		$blocking  = isset( $escalation_state['blocking_flags'] ) && is_array( $escalation_state['blocking_flags'] ) ? $escalation_state['blocking_flags'] : array();
		$criticals = array();
		$warnings  = array();

		foreach ( $blocking as $flag ) {
			if ( ! is_array( $flag ) ) {
				continue;
			}
			$message = isset( $flag['message'] ) ? sanitize_text_field( (string) $flag['message'] ) : __( 'Operational blocker detected', 'super-mechanic' );
			$value   = isset( $flag['value'] ) ? absint( $flag['value'] ) : 0;
			$level   = isset( $flag['level'] ) ? sanitize_key( (string) $flag['level'] ) : 'warning';
			$label   = sprintf( '%d %s', $value, strtolower( $message ) );

			if ( 'critical' === $level ) {
				$criticals[] = $label;
			} else {
				$warnings[] = $label;
			}
		}

		$warning_workload = isset( $escalation_state['warning_workload_count'] ) ? absint( $escalation_state['warning_workload_count'] ) : 0;
		if ( $warning_workload > 0 ) {
			$warnings[] = sprintf(
				/* translators: %d warning items. */
				__( 'workload warning detected (%d)', 'super-mechanic' ),
				$warning_workload
			);
		}

		echo '<section class="sm-card sm-section sm-critical-strip">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Critical operational strip', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-critical-strip-items">';
		if ( empty( $criticals ) && empty( $warnings ) ) {
			echo '<span class="sm-badge sm-badge-success">' . esc_html__( 'No critical or warning operational signals right now.', 'super-mechanic' ) . '</span>';
		} else {
			foreach ( $criticals as $item ) {
				echo '<span class="sm-badge sm-badge-danger">' . esc_html( $item ) . '</span>';
			}
			foreach ( $warnings as $item ) {
				echo '<span class="sm-badge sm-badge-warning">' . esc_html( $item ) . '</span>';
			}
		}
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render workload section.
	 *
	 * @param array<string,mixed> $workload Workload payload.
	 * @return void
	 */
	protected function render_workload_section( array $workload ) {
		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Mi trabajo (workload operativo)', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html__( 'Por usuario', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Vista consolidada de tareas, alertas persistidas, procesos activos y citas próximas.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards sm-grid-cards-compact sm-secondary-metrics">';
		$this->render_workload_bucket_table(
			__( 'Critical', 'super-mechanic' ),
			isset( $workload['critical'] ) && is_array( $workload['critical'] ) ? $workload['critical'] : array(),
			__( 'No critical items.', 'super-mechanic' )
		);
		$this->render_workload_bucket_table(
			__( 'Warning', 'super-mechanic' ),
			isset( $workload['warning'] ) && is_array( $workload['warning'] ) ? $workload['warning'] : array(),
			__( 'No warning items.', 'super-mechanic' )
		);
		$this->render_workload_bucket_table(
			__( 'Normal', 'super-mechanic' ),
			isset( $workload['normal'] ) && is_array( $workload['normal'] ) ? $workload['normal'] : array(),
			__( 'No normal items.', 'super-mechanic' )
		);
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render automation flags section.
	 *
	 * @param array<string,mixed> $automation_flags Automation flags payload.
	 * @return void
	 */
	protected function render_operational_automation_flags( array $automation_flags ) {
		$flags        = isset( $automation_flags['flags'] ) && is_array( $automation_flags['flags'] ) ? $automation_flags['flags'] : array();
		$summary      = isset( $automation_flags['summary'] ) && is_array( $automation_flags['summary'] ) ? $automation_flags['summary'] : array();
		$active_count = isset( $summary['active_flags'] ) ? absint( $summary['active_flags'] ) : 0;
		$global_state = isset( $summary['global_state'] ) ? sanitize_key( (string) $summary['global_state'] ) : 'stable';
		$state_label  = 'elevated' === $global_state ? __( 'Elevated', 'super-mechanic' ) : ( 'attention' === $global_state ? __( 'Attention', 'super-mechanic' ) : __( 'Stable', 'super-mechanic' ) );
		$state_badge  = 'elevated' === $global_state ? 'sm-badge sm-badge-danger' : ( 'attention' === $global_state ? 'sm-badge sm-badge-warning' : 'sm-badge sm-badge-success' );

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Automatización operativa interna', 'super-mechanic' ) . '</h2><span class="' . esc_attr( $state_badge ) . '">' . esc_html( $state_label ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Rule-based internal flags generated from existing operational signals (no external automation).', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Active internal flags', 'super-mechanic' ),
			$active_count,
			__( 'Automatic operational suggestions', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Critical flags', 'super-mechanic' ),
			isset( $summary['critical_flags'] ) ? absint( $summary['critical_flags'] ) : 0,
			__( 'Need immediate attention', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Warning flags', 'super-mechanic' ),
			isset( $summary['warning_flags'] ) ? absint( $summary['warning_flags'] ) : 0,
			__( 'Monitor and rebalance load', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Rule', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Current value', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Threshold', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $flags ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No internal rules available.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $flags as $flag ) {
				$message   = isset( $flag['message'] ) ? sanitize_text_field( (string) $flag['message'] ) : __( 'Operational rule', 'super-mechanic' );
				$is_active = ! empty( $flag['active'] );
				$value     = isset( $flag['value'] ) ? absint( $flag['value'] ) : 0;
				$threshold = isset( $flag['threshold'] ) ? absint( $flag['threshold'] ) : 0;
				$level     = isset( $flag['level'] ) ? sanitize_key( (string) $flag['level'] ) : 'normal';
				$badge     = $is_active ? $this->render_workload_priority_badge( $level ) : '<span class="sm-badge sm-badge-success">' . esc_html__( 'OK', 'super-mechanic' ) . '</span>';
				echo '<tr>';
				echo '<td>' . esc_html( $message ) . '</td>';
				echo '<td>' . wp_kses_post( $badge ) . '</td>';
				echo '<td>' . esc_html( $value ) . '</td>';
				echo '<td>' . esc_html( $threshold ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render escalation state section.
	 *
	 * @param array<string,mixed> $escalation_state Escalation payload.
	 * @return void
	 */
	protected function render_operational_escalation_state( array $escalation_state ) {
		$global_level = isset( $escalation_state['global_level'] ) ? sanitize_key( (string) $escalation_state['global_level'] ) : 'normal';
		$blocking     = isset( $escalation_state['blocking_flags'] ) && is_array( $escalation_state['blocking_flags'] ) ? $escalation_state['blocking_flags'] : array();
		$user_sat     = isset( $escalation_state['user_saturation'] ) && is_array( $escalation_state['user_saturation'] ) ? $escalation_state['user_saturation'] : array();
		$badge_class  = 'sm-badge sm-badge-success';
		$badge_label  = __( 'Normal', 'super-mechanic' );

		if ( 'critical' === $global_level ) {
			$badge_class = 'sm-badge sm-badge-danger';
			$badge_label = __( 'Critical', 'super-mechanic' );
		} elseif ( 'warning' === $global_level ) {
			$badge_class = 'sm-badge sm-badge-warning';
			$badge_label = __( 'Warning', 'super-mechanic' );
		}

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Escalamiento Operativo', 'super-mechanic' ) . '</h2><span class="' . esc_attr( $badge_class ) . '">' . esc_html( $badge_label ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Consolidated escalation layer for critical blockers and saturation conditions.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Blocking flags', 'super-mechanic' ),
			count( $blocking ),
			__( 'Active operational blockers', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Critical workload', 'super-mechanic' ),
			isset( $escalation_state['critical_workload_count'] ) ? absint( $escalation_state['critical_workload_count'] ) : 0,
			__( 'Prioritized workload items', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Warning workload', 'super-mechanic' ),
			isset( $escalation_state['warning_workload_count'] ) ? absint( $escalation_state['warning_workload_count'] ) : 0,
			__( 'Follow-up workload items', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'User saturation', 'super-mechanic' ),
			! empty( $user_sat['is_saturated'] ) ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ),
			__( 'Critical load pressure per user', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Blocking condition', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Value', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Threshold', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $blocking ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No active operational blockers.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $blocking as $flag ) {
				$message   = isset( $flag['message'] ) ? sanitize_text_field( (string) $flag['message'] ) : __( 'Operational blocker', 'super-mechanic' );
				$level     = isset( $flag['level'] ) ? sanitize_key( (string) $flag['level'] ) : 'warning';
				$value     = isset( $flag['value'] ) ? absint( $flag['value'] ) : 0;
				$threshold = isset( $flag['threshold'] ) ? absint( $flag['threshold'] ) : 0;
				echo '<tr>';
				echo '<td>' . esc_html( $message ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $level ) ) . '</td>';
				echo '<td>' . esc_html( $value ) . '</td>';
				echo '<td>' . esc_html( $threshold ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render intelligent recommendations section.
	 *
	 * @param array<string,mixed> $payload Recommendations payload.
	 * @param int                 $max_items Optional max items to show.
	 * @param array<string,bool>  $suppressed_problem_keys Problem keys already covered by higher-priority actionable blocks.
	 * @return void
	 */
	protected function render_operational_recommendations( array $payload, $max_items = 0, array $suppressed_problem_keys = array() ) {
		$recommendations = isset( $payload['recommendations'] ) && is_array( $payload['recommendations'] ) ? $payload['recommendations'] : array();
		$max_items       = absint( $max_items );
		$recommendations = $this->filter_recommendations_by_noise_coverage( $recommendations, $suppressed_problem_keys, false );

		if ( $max_items > 0 && ! empty( $recommendations ) ) {
			$recommendations = array_slice( $recommendations, 0, $max_items );
		}
		$display_total    = count( $recommendations );
		$display_critical = 0;
		$display_warning  = 0;
		foreach ( $recommendations as $recommendation ) {
			$level = isset( $recommendation['level'] ) ? sanitize_key( (string) $recommendation['level'] ) : 'warning';
			if ( 'critical' === $level ) {
				++$display_critical;
			} else {
				++$display_warning;
			}
		}

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Sugerencias Inteligentes', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html__( 'Recommendations', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Suggested next actions based on workload, escalation, and SLA signals.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Total recommendations', 'super-mechanic' ),
			$display_total,
			__( 'Suggested operational actions', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Critical recommendations', 'super-mechanic' ),
			$display_critical,
			__( 'Immediate interventions', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Warning recommendations', 'super-mechanic' ),
			$display_warning,
			__( 'Priority follow-up actions', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Recommendation', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Message', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Action hint', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recommendations ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No recommendations at this time.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recommendations as $recommendation ) {
				$title       = isset( $recommendation['title'] ) ? sanitize_text_field( (string) $recommendation['title'] ) : __( 'Operational recommendation', 'super-mechanic' );
				$level       = isset( $recommendation['level'] ) ? sanitize_key( (string) $recommendation['level'] ) : 'warning';
				$message     = isset( $recommendation['message'] ) ? sanitize_text_field( (string) $recommendation['message'] ) : '';
				$action_hint = isset( $recommendation['action_hint'] ) ? sanitize_text_field( (string) $recommendation['action_hint'] ) : '';
				echo '<tr>';
				echo '<td>' . esc_html( $title ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $level ) ) . '</td>';
				echo '<td>' . esc_html( $message ) . '</td>';
				echo '<td>' . esc_html( $action_hint ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render compact automation summary on dashboard.
	 *
	 * @param array<string,mixed> $automation_flags Flags payload.
	 * @return void
	 */
	protected function render_automation_summary_compact( array $automation_flags ) {
		$summary = isset( $automation_flags['summary'] ) && is_array( $automation_flags['summary'] ) ? $automation_flags['summary'] : array();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Automation Summary', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Compact', 'super-mechanic' ) . '</span></div>';
		echo '<div class="sm-grid sm-grid-cards sm-grid-cards-compact">';
		$this->render_kpi_card(
			__( 'Active internal flags', 'super-mechanic' ),
			isset( $summary['active_flags'] ) ? absint( $summary['active_flags'] ) : 0,
			''
		);
		$this->render_kpi_card(
			__( 'Critical flags', 'super-mechanic' ),
			isset( $summary['critical_flags'] ) ? absint( $summary['critical_flags'] ) : 0,
			''
		);
		$this->render_kpi_card(
			__( 'Warning flags', 'super-mechanic' ),
			isset( $summary['warning_flags'] ) ? absint( $summary['warning_flags'] ) : 0,
			''
		);
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=super-mechanic-automation' ) ) . '">' . esc_html__( 'Open Automation Center', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render centralized operational action center.
	 *
	 * @param array<string,mixed> $assisted_payload Assisted actions payload.
	 * @param array<string,mixed> $assignments_payload Assignment payload.
	 * @param array<string,mixed> $bulk_payload Bulk actions payload.
	 * @param array<string,mixed> $console_payload Automation console payload.
	 * @param string              $section Optional dashboard section filter.
	 * @param string              $filter Optional severity filter.
	 * @param array<string,bool>  $suppressed_problem_keys Problem keys already covered in higher-priority surfaces.
	 * @return void
	 */
	protected function render_operational_action_center( array $assisted_payload, array $assignments_payload, array $bulk_payload, array $console_payload, $section = '', $filter = '', array $suppressed_problem_keys = array() ) {
		$is_critical_focus = 'action_center' === sanitize_key( (string) $section ) && 'critical' === sanitize_key( (string) $filter );
		$assisted_actions = isset( $assisted_payload['actions'] ) && is_array( $assisted_payload['actions'] ) ? $assisted_payload['actions'] : array();
		$critical_actions = array_values(
			array_filter(
				$assisted_actions,
				static function ( $action ) {
					return is_array( $action ) && 'critical' === sanitize_key( (string) ( $action['level'] ?? '' ) );
				}
			)
		);
		$assignments       = isset( $assignments_payload['assignments'] ) && is_array( $assignments_payload['assignments'] ) ? $assignments_payload['assignments'] : array();
		$business_id       = isset( $assignments_payload['meta']['business_id'] ) ? absint( $assignments_payload['meta']['business_id'] ) : 0;
		$executable_assignments = array_values(
			array_filter(
				$assignments,
				static function ( $proposal ) use ( $business_id ) {
					if ( ! is_array( $proposal ) ) {
						return false;
					}
					$entity_type = sanitize_key( (string) ( $proposal['entity_type'] ?? '' ) );
					$entity_id   = absint( $proposal['entity_id'] ?? 0 );
					$from_user   = absint( $proposal['from_user'] ?? 0 );
					$to_user     = absint( $proposal['to_user'] ?? 0 );

					return ! empty( $proposal['executable'] ) && 'crm_task' === $entity_type && $entity_id > 0 && $from_user > 0 && $to_user > 0 && $business_id > 0;
				}
			)
		);
		$critical_assignments   = array_values(
			array_filter(
				$executable_assignments,
				static function ( $proposal ) {
					return is_array( $proposal ) && 'critical' === sanitize_key( (string) ( $proposal['level'] ?? '' ) );
				}
			)
		);
		$groups            = isset( $bulk_payload['groups'] ) && is_array( $bulk_payload['groups'] ) ? $bulk_payload['groups'] : array();
		$bulk_business_id  = isset( $bulk_payload['meta']['business_id'] ) ? absint( $bulk_payload['meta']['business_id'] ) : 0;
		$executable_groups = array_values(
			array_filter(
				$groups,
				static function ( $group ) use ( $bulk_business_id ) {
					if ( ! is_array( $group ) ) {
						return false;
					}
					$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
					return ! empty( $group['executable'] ) && $bulk_business_id > 0 && ! empty( $items );
				}
			)
		);
		$critical_executable_groups = array_values(
			array_filter(
				$executable_groups,
				static function ( $group ) {
					return is_array( $group ) && 'critical' === sanitize_key( (string) ( $group['level'] ?? '' ) );
				}
			)
		);
		$flags             = isset( $console_payload['flags']['flags'] ) && is_array( $console_payload['flags']['flags'] ) ? $console_payload['flags']['flags'] : array();
		$critical_flags    = array_values(
			array_filter(
				$flags,
				static function ( $flag ) {
					return is_array( $flag ) && ! empty( $flag['active'] ) && 'critical' === sanitize_key( (string) ( $flag['level'] ?? '' ) );
				}
			)
		);
		$recommendations   = isset( $console_payload['recommendations']['recommendations'] ) && is_array( $console_payload['recommendations']['recommendations'] ) ? $console_payload['recommendations']['recommendations'] : array();
		$critical_recommendations = array_values(
			array_filter(
				$recommendations,
				static function ( $recommendation ) {
					return is_array( $recommendation ) && 'critical' === sanitize_key( (string) ( $recommendation['level'] ?? '' ) );
				}
			)
		);
		$render_assignments    = $is_critical_focus ? $critical_assignments : $executable_assignments;
		$render_groups         = $is_critical_focus ? $critical_executable_groups : $executable_groups;
		$render_recommendations = $is_critical_focus ? $critical_recommendations : $recommendations;
		$render_recommendations = $this->filter_recommendations_by_noise_coverage( $render_recommendations, $suppressed_problem_keys, true );
		$recommendation_total  = count( $render_recommendations );
		$has_critical_items    = ! empty( $critical_actions ) || ! empty( $critical_flags ) || ! empty( $critical_assignments ) || ! empty( $critical_executable_groups ) || ! empty( $critical_recommendations );

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Centro de Acción Operativa', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html( $is_critical_focus ? __( 'Critical focus', 'super-mechanic' ) : __( 'Unified', 'super-mechanic' ) ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Single operational layer to execute critical actions, controlled reassignments, and bulk actions from one place.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card( __( 'Critical priority actions', 'super-mechanic' ), count( $critical_actions ), __( 'Assisted critical actions', 'super-mechanic' ) );
		$this->render_kpi_card( __( 'Critical flags', 'super-mechanic' ), count( $critical_flags ), __( 'Active critical signals', 'super-mechanic' ) );
		$this->render_kpi_card( __( 'Executable reassignments', 'super-mechanic' ), count( $render_assignments ), __( 'Reassignment proposals ready to run', 'super-mechanic' ) );
		$this->render_kpi_card( __( 'Executable bulk groups', 'super-mechanic' ), count( $render_groups ), __( 'Bulk actions ready to run', 'super-mechanic' ) );
		$this->render_kpi_card( __( 'Recommendations', 'super-mechanic' ), $recommendation_total, __( 'Operational suggestions', 'super-mechanic' ) );
		echo '</div>';
		if ( $is_critical_focus && ! $has_critical_items ) {
			echo '<div class="sm-notice-card">';
			echo '<strong>' . esc_html__( 'Critical Action Center', 'super-mechanic' ) . '</strong>';
			echo '<p class="sm-card-copy">' . esc_html__( 'No critical operational items found for this business right now.', 'super-mechanic' ) . '</p>';
			echo '<p class="sm-card-copy">' . esc_html__( 'Critical actions will appear here when overdue workload, escalated rules, or critical signals exist.', 'super-mechanic' ) . '</p>';
			echo '</div>';
		}

		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card sm-card-muted sm-secondary-compact">';
		echo '<div class="sm-section-heading"><h3>' . esc_html__( 'Prioridad crítica', 'super-mechanic' ) . '</h3></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Item', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Action', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $critical_actions ) && empty( $critical_flags ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No critical operational actions right now. This section activates when critical signals or critical assisted actions are detected.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $critical_actions as $action ) {
				$label = isset( $action['label'] ) ? sanitize_text_field( (string) $action['label'] ) : __( 'Critical action', 'super-mechanic' );
				$url   = isset( $action['url'] ) ? esc_url_raw( (string) $action['url'] ) : '';
				echo '<tr>';
				echo '<td>' . esc_html( $label ) . '</td>';
				echo '<td>' . esc_html__( 'Assisted action', 'super-mechanic' ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( 'critical' ) ) . '</td>';
				if ( '' !== $url ) {
					echo '<td><a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'super-mechanic' ) . '</a></td>';
				} else {
					echo '<td>' . esc_html__( 'No direct action available yet. Review the related recommendation context.', 'super-mechanic' ) . '</td>';
				}
				echo '</tr>';
			}
			foreach ( $critical_flags as $flag ) {
				$message = isset( $flag['message'] ) ? sanitize_text_field( (string) $flag['message'] ) : __( 'Critical operational flag', 'super-mechanic' );
				echo '<tr>';
				echo '<td>' . esc_html( $message ) . '</td>';
				echo '<td>' . esc_html__( 'Automation flag', 'super-mechanic' ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( 'critical' ) ) . '</td>';
				echo '<td>' . esc_html__( 'Monitor now', 'super-mechanic' ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-card sm-card-muted sm-secondary-compact">';
		echo '<div class="sm-section-heading"><h3>' . esc_html__( 'Reasignación', 'super-mechanic' ) . '</h3></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'From', 'super-mechanic' ) . '</th><th>' . esc_html__( 'To', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Entity', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Execute', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $render_assignments ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No executable reassignment proposals. Proposals appear when overloaded and available users have a valid reassignable task candidate.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $render_assignments as $proposal ) {
				$from_name = isset( $proposal['from_name'] ) ? sanitize_text_field( (string) $proposal['from_name'] ) : '';
				$to_name   = isset( $proposal['to_name'] ) ? sanitize_text_field( (string) $proposal['to_name'] ) : '';
				$entity    = isset( $proposal['entity_type'] ) ? sanitize_key( (string) $proposal['entity_type'] ) : '';
				$level     = isset( $proposal['level'] ) ? sanitize_key( (string) $proposal['level'] ) : 'warning';
				$from_user = absint( $proposal['from_user'] );
				$to_user   = absint( $proposal['to_user'] );
				$entity_id = absint( $proposal['entity_id'] );
				echo '<tr>';
				echo '<td>' . esc_html( $from_name ) . '</td>';
				echo '<td>' . esc_html( $to_name ) . '</td>';
				echo '<td>' . esc_html( strtoupper( str_replace( '_', ' ', $entity ) ) ) . ' #' . esc_html( $entity_id ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $level ) ) . '</td>';
				echo '<td><form method="post" style="margin:0;">';
				echo '<input type="hidden" name="sm_operational_reassign_action" value="execute" />';
				echo '<input type="hidden" name="business_id" value="' . esc_attr( $business_id ) . '" />';
				echo '<input type="hidden" name="from_user" value="' . esc_attr( $from_user ) . '" />';
				echo '<input type="hidden" name="to_user" value="' . esc_attr( $to_user ) . '" />';
				echo '<input type="hidden" name="entity_type" value="' . esc_attr( $entity ) . '" />';
				echo '<input type="hidden" name="entity_id" value="' . esc_attr( $entity_id ) . '" />';
				wp_nonce_field( 'sm_operational_reassign', 'sm_operational_reassign_nonce', false, true );
				echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Reassign', 'super-mechanic' ) . '</button>';
				echo '</form></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-card sm-card-muted sm-secondary-compact">';
		echo '<div class="sm-section-heading"><h3>' . esc_html__( 'Acciones masivas', 'super-mechanic' ) . '</h3></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Group', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Entity', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Count', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Execute', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $render_groups ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No executable bulk groups. Bulk actions appear when a safe grouped candidate set is available.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $render_groups as $group ) {
				$group_key      = isset( $group['group_key'] ) ? sanitize_key( (string) $group['group_key'] ) : 'group';
				$entity_type    = isset( $group['entity_type'] ) ? sanitize_key( (string) $group['entity_type'] ) : '';
				$count          = isset( $group['count'] ) ? absint( $group['count'] ) : 0;
				$level          = isset( $group['level'] ) ? sanitize_key( (string) $group['level'] ) : 'warning';
				$action         = isset( $group['action'] ) ? sanitize_key( (string) $group['action'] ) : '';
				$target_user_id = isset( $group['target_user_id'] ) ? absint( $group['target_user_id'] ) : 0;
				$ids            = isset( $group['items'] ) && is_array( $group['items'] ) ? implode( ',', array_map( 'absint', $group['items'] ) ) : '';
				$button_label   = 'bulk_resolve' === $action ? __( 'Resolve all', 'super-mechanic' ) : __( 'Reassign all', 'super-mechanic' );
				echo '<tr>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $group_key ) ) ) . '</td>';
				echo '<td>' . esc_html( strtoupper( str_replace( '_', ' ', $entity_type ) ) ) . '</td>';
				echo '<td>' . esc_html( $count ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $level ) ) . '</td>';
				echo '<td><form method="post" style="margin:0;">';
				echo '<input type="hidden" name="sm_operational_bulk_action" value="' . esc_attr( $action ) . '" />';
				echo '<input type="hidden" name="sm_execution_source" value="controlled" />';
				echo '<input type="hidden" name="business_id" value="' . esc_attr( $bulk_business_id ) . '" />';
				echo '<input type="hidden" name="entity_type" value="' . esc_attr( $entity_type ) . '" />';
				echo '<input type="hidden" name="ids" value="' . esc_attr( $ids ) . '" />';
				if ( $target_user_id > 0 ) {
					echo '<input type="hidden" name="target_user_id" value="' . esc_attr( $target_user_id ) . '" />';
				}
				wp_nonce_field( 'sm_operational_bulk_action', 'sm_operational_bulk_action_nonce', false, true );
				echo '<button type="submit" class="button button-secondary">' . esc_html( $button_label ) . '</button>';
				echo '</form></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-card sm-card-muted sm-secondary-compact">';
		echo '<div class="sm-section-heading"><h3>' . esc_html__( 'Sugerencias', 'super-mechanic' ) . '</h3></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Recommendation', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Action hint', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $render_recommendations ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No additional recommendations. Priority outputs are already listed above.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $render_recommendations as $recommendation ) {
				$title       = isset( $recommendation['title'] ) ? sanitize_text_field( (string) $recommendation['title'] ) : __( 'Operational recommendation', 'super-mechanic' );
				$level       = isset( $recommendation['level'] ) ? sanitize_key( (string) $recommendation['level'] ) : 'warning';
				$action_hint = isset( $recommendation['action_hint'] ) ? sanitize_text_field( (string) $recommendation['action_hint'] ) : '';
				echo '<tr>';
				echo '<td>' . esc_html( $title ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $level ) ) . '</td>';
				echo '<td>' . esc_html( $action_hint ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Build per-problem coverage map to reduce duplicated noise across blocks.
	 *
	 * @param array<string,mixed> $assisted_payload Assisted actions payload.
	 * @param array<string,mixed> $assignments_payload Assignments payload.
	 * @param array<string,mixed> $bulk_payload Bulk payload.
	 * @param array<string,mixed> $escalation_state Escalation payload.
	 * @return array<string,bool>
	 */
	protected function build_operational_noise_coverage( array $assisted_payload, array $assignments_payload, array $bulk_payload, array $escalation_state ) {
		$coverage = array();

		$actions = isset( $assisted_payload['actions'] ) && is_array( $assisted_payload['actions'] ) ? $assisted_payload['actions'] : array();
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$key      = isset( $action['key'] ) ? sanitize_key( (string) $action['key'] ) : '';
			$label    = isset( $action['label'] ) ? sanitize_text_field( (string) $action['label'] ) : '';
			$context  = isset( $action['context'] ) ? sanitize_text_field( (string) $action['context'] ) : '';
			$combined = trim( $key . ' ' . $label . ' ' . $context );
			$issue    = $this->infer_operational_problem_from_text( $combined );
			if ( '' !== $issue ) {
				$coverage[ $issue ] = true;
			}
		}

		$assignments = isset( $assignments_payload['assignments'] ) && is_array( $assignments_payload['assignments'] ) ? $assignments_payload['assignments'] : array();
		foreach ( $assignments as $assignment ) {
			if ( is_array( $assignment ) && ! empty( $assignment['executable'] ) ) {
				$coverage['saturation'] = true;
				break;
			}
		}

		$groups = isset( $bulk_payload['groups'] ) && is_array( $bulk_payload['groups'] ) ? $bulk_payload['groups'] : array();
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || empty( $group['executable'] ) ) {
				continue;
			}
			$group_key = isset( $group['group_key'] ) ? sanitize_key( (string) $group['group_key'] ) : '';
			$issue     = $this->infer_operational_problem_from_text( $group_key );
			if ( '' !== $issue ) {
				$coverage[ $issue ] = true;
			}
		}

		$blocking_flags = isset( $escalation_state['blocking_flags'] ) && is_array( $escalation_state['blocking_flags'] ) ? $escalation_state['blocking_flags'] : array();
		foreach ( $blocking_flags as $flag ) {
			if ( ! is_array( $flag ) ) {
				continue;
			}
			$message = isset( $flag['message'] ) ? sanitize_text_field( (string) $flag['message'] ) : '';
			$issue   = $this->infer_operational_problem_from_text( $message );
			if ( '' !== $issue ) {
				$coverage[ $issue ] = true;
			}
		}

		return $coverage;
	}

	/**
	 * Filter recommendations when the same problem is already surfaced in higher-priority outputs.
	 *
	 * @param array<int,array<string,mixed>> $recommendations Recommendations to filter.
	 * @param array<string,bool>             $suppressed_problem_keys Covered problems.
	 * @param bool                           $keep_critical Whether critical recommendations should still pass through.
	 * @return array<int,array<string,mixed>>
	 */
	protected function filter_recommendations_by_noise_coverage( array $recommendations, array $suppressed_problem_keys, $keep_critical = false ) {
		if ( empty( $suppressed_problem_keys ) ) {
			return $recommendations;
		}

		$filtered = array();
		foreach ( $recommendations as $recommendation ) {
			if ( ! is_array( $recommendation ) ) {
				continue;
			}
			$problem_key = $this->resolve_recommendation_problem_key( $recommendation );
			$level       = isset( $recommendation['level'] ) ? sanitize_key( (string) $recommendation['level'] ) : 'warning';

			if ( '' !== $problem_key && isset( $suppressed_problem_keys[ $problem_key ] ) ) {
				if ( $keep_critical && 'critical' === $level ) {
					$filtered[] = $recommendation;
				}
				continue;
			}

			$filtered[] = $recommendation;
		}

		return $filtered;
	}

	/**
	 * Resolve recommendation to a normalized operational problem key.
	 *
	 * @param array<string,mixed> $recommendation Recommendation row.
	 * @return string
	 */
	protected function resolve_recommendation_problem_key( array $recommendation ) {
		$key = isset( $recommendation['key'] ) ? sanitize_key( (string) $recommendation['key'] ) : '';
		if ( '' !== $key ) {
			if ( false !== strpos( $key, 'overdue' ) || false !== strpos( $key, 'backlog' ) ) {
				return 'overdue_tasks';
			}
			if ( false !== strpos( $key, 'delayed' ) || false !== strpos( $key, 'process' ) ) {
				return 'delayed_processes';
			}
			if ( false !== strpos( $key, 'critical' ) || false !== strpos( $key, 'intervention' ) ) {
				return 'critical_signals';
			}
			if ( false !== strpos( $key, 'saturation' ) || false !== strpos( $key, 'redistribute' ) || false !== strpos( $key, 'load' ) ) {
				return 'saturation';
			}
			if ( false !== strpos( $key, 'appointment' ) ) {
				return 'upcoming_appointments';
			}
		}

		$title   = isset( $recommendation['title'] ) ? sanitize_text_field( (string) $recommendation['title'] ) : '';
		$message = isset( $recommendation['message'] ) ? sanitize_text_field( (string) $recommendation['message'] ) : '';
		$hint    = isset( $recommendation['action_hint'] ) ? sanitize_text_field( (string) $recommendation['action_hint'] ) : '';

		return $this->infer_operational_problem_from_text( trim( $title . ' ' . $message . ' ' . $hint ) );
	}

	/**
	 * Infer normalized operational problem key from free text.
	 *
	 * @param string $text Source text.
	 * @return string
	 */
	protected function infer_operational_problem_from_text( $text ) {
		$value = strtolower( sanitize_text_field( (string) $text ) );
		if ( '' === $value ) {
			return '';
		}

		if ( false !== strpos( $value, 'overdue' ) || false !== strpos( $value, 'backlog' ) ) {
			return 'overdue_tasks';
		}
		if ( false !== strpos( $value, 'delayed' ) || false !== strpos( $value, 'process' ) ) {
			return 'delayed_processes';
		}
		if ( false !== strpos( $value, 'critical' ) || false !== strpos( $value, 'blocker' ) || false !== strpos( $value, 'flag' ) || false !== strpos( $value, 'intervention' ) ) {
			return 'critical_signals';
		}
		if ( false !== strpos( $value, 'saturation' ) || false !== strpos( $value, 'workload' ) || false !== strpos( $value, 'reassign' ) || false !== strpos( $value, 'rebalance' ) ) {
			return 'saturation';
		}
		if ( false !== strpos( $value, 'appointment' ) || false !== strpos( $value, 'scheduled work' ) ) {
			return 'upcoming_appointments';
		}

		return '';
	}

	/**
	 * Determine if tenant has operational data to activate dashboard intelligence.
	 *
	 * @param array<string,mixed> $global_summary Global summary payload.
	 * @param array<string,mixed> $workload Workload payload.
	 * @return bool
	 */
	protected function has_operational_data( array $global_summary, array $workload ) {
		$summary_total =
			absint( isset( $global_summary['tasks_pending_total'] ) ? $global_summary['tasks_pending_total'] : 0 ) +
			absint( isset( $global_summary['tasks_overdue_total'] ) ? $global_summary['tasks_overdue_total'] : 0 ) +
			absint( isset( $global_summary['alerts_active_total'] ) ? $global_summary['alerts_active_total'] : 0 ) +
			absint( isset( $global_summary['processes_active_total'] ) ? $global_summary['processes_active_total'] : 0 ) +
			absint( isset( $global_summary['appointments_upcoming_total'] ) ? $global_summary['appointments_upcoming_total'] : 0 );
		if ( $summary_total > 0 ) {
			return true;
		}

		$workload_total =
			( isset( $workload['critical'] ) && is_array( $workload['critical'] ) ? count( $workload['critical'] ) : 0 ) +
			( isset( $workload['warning'] ) && is_array( $workload['warning'] ) ? count( $workload['warning'] ) : 0 ) +
			( isset( $workload['normal'] ) && is_array( $workload['normal'] ) ? count( $workload['normal'] ) : 0 );

		return $workload_total > 0;
	}

	/**
	 * Render operational rules overview block.
	 *
	 * @param array<string,mixed> $payload Rules overview payload.
	 * @return void
	 */
	protected function render_operational_rules( array $payload ) {
		$rules       = isset( $payload['rules'] ) && is_array( $payload['rules'] ) ? $payload['rules'] : array();
		$evaluations = isset( $payload['evaluations'] ) && is_array( $payload['evaluations'] ) ? $payload['evaluations'] : array();

		$evaluation_by_rule = array();
		$triggered_count    = 0;
		foreach ( $evaluations as $evaluation ) {
			if ( ! is_array( $evaluation ) ) {
				continue;
			}
			$rule_key = isset( $evaluation['rule_key'] ) ? sanitize_key( (string) $evaluation['rule_key'] ) : '';
			if ( '' === $rule_key ) {
				continue;
			}
			$evaluation_by_rule[ $rule_key ] = $evaluation;
			if ( ! empty( $evaluation['triggered'] ) ) {
				++$triggered_count;
			}
		}

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Reglas Operativas', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Preview only', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Configurable operational rules evaluated against current system state. No automatic execution is performed.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Total rules', 'super-mechanic' ),
			count( $rules ),
			__( 'Configured operational rules', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Triggered rules', 'super-mechanic' ),
			$triggered_count,
			__( 'Rules currently matching conditions', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Execution mode', 'super-mechanic' ),
			__( 'Manual only', 'super-mechanic' ),
			__( 'No cron or automatic action execution', 'super-mechanic' )
		);
		echo '</div>';

		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Rule', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Enabled', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Execution mode', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Triggered', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Impact', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Action preview', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Debug reason', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $rules ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No operational rules configured.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rules as $rule ) {
				$rule_key   = isset( $rule['rule_key'] ) ? sanitize_key( (string) $rule['rule_key'] ) : '';
				$name       = isset( $rule['name'] ) ? sanitize_text_field( (string) $rule['name'] ) : __( 'Operational rule', 'super-mechanic' );
				$enabled    = ! empty( $rule['enabled'] );
				$evaluation = isset( $evaluation_by_rule[ $rule_key ] ) && is_array( $evaluation_by_rule[ $rule_key ] ) ? $evaluation_by_rule[ $rule_key ] : array();
				$triggered  = ! empty( $evaluation['triggered'] );
				$impact     = isset( $evaluation['impact_level'] ) ? sanitize_key( (string) $evaluation['impact_level'] ) : 'info';
				$preview    = isset( $evaluation['action_preview'] ) && is_array( $evaluation['action_preview'] ) ? $evaluation['action_preview'] : array();
				$action_type = isset( $preview['action_type'] ) ? sanitize_key( (string) $preview['action_type'] ) : ( isset( $rule['action_type'] ) ? sanitize_key( (string) $rule['action_type'] ) : 'flag' );
				$execution_mode = isset( $rule['execution_mode'] ) ? sanitize_key( (string) $rule['execution_mode'] ) : 'manual';
				$candidate_count = isset( $preview['candidate_count'] ) ? absint( $preview['candidate_count'] ) : 0;
				$proposal_count  = isset( $preview['proposal_count'] ) ? absint( $preview['proposal_count'] ) : 0;
				$executable      = isset( $preview['executable'] ) ? (bool) $preview['executable'] : false;
				$trigger_reason  = isset( $evaluation['trigger_reason'] ) ? sanitize_text_field( (string) $evaluation['trigger_reason'] ) : '';
				$execution_reason = isset( $evaluation['execution_reason'] ) ? sanitize_text_field( (string) $evaluation['execution_reason'] ) : '';
				$execution_state  = isset( $evaluation['execution_state'] ) ? sanitize_key( (string) $evaluation['execution_state'] ) : '';

				$preview_parts = array(
					ucwords( str_replace( '_', ' ', $action_type ) ),
				);
				if ( $candidate_count > 0 ) {
					$preview_parts[] = sprintf(
						/* translators: %d candidate count. */
						__( '%d candidates', 'super-mechanic' ),
						$candidate_count
					);
				}
				if ( $proposal_count > 0 ) {
					$preview_parts[] = sprintf(
						/* translators: %d proposal count. */
						__( '%d proposals', 'super-mechanic' ),
						$proposal_count
					);
				}
				$preview_parts[] = $executable ? __( 'Executable in manual flow', 'super-mechanic' ) : __( 'Informative only', 'super-mechanic' );
				$preview_label   = implode( ' · ', $preview_parts );
				$debug_parts     = array();
				if ( '' !== $trigger_reason ) {
					$debug_parts[] = $trigger_reason;
				}
				if ( '' !== $execution_reason ) {
					$debug_parts[] = $execution_reason;
				}
				if ( '' !== $execution_state ) {
					$debug_parts[] = sprintf(
						/* translators: %s execution state. */
						__( 'State: %s.', 'super-mechanic' ),
						$execution_state
					);
				}
				$debug_label = empty( $debug_parts ) ? __( 'No debug detail available.', 'super-mechanic' ) : implode( ' ', $debug_parts );

				echo '<tr>';
				echo '<td>' . esc_html( $name ) . '</td>';
				echo '<td>' . ( $enabled ? '<span class="sm-badge sm-badge-success">' . esc_html__( 'Enabled', 'super-mechanic' ) . '</span>' : '<span class="sm-badge sm-badge-neutral">' . esc_html__( 'Disabled', 'super-mechanic' ) . '</span>' ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_execution_mode_badge( $execution_mode ) ) . '</td>';
				echo '<td>' . ( $triggered ? '<span class="sm-badge sm-badge-warning">' . esc_html__( 'Triggered', 'super-mechanic' ) . '</span>' : '<span class="sm-badge sm-badge-neutral">' . esc_html__( 'Not triggered', 'super-mechanic' ) . '</span>' ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( in_array( $impact, array( 'critical', 'warning' ), true ) ? $impact : 'normal' ) ) . '</td>';
				echo '<td>' . esc_html( $preview_label ) . '</td>';
				echo '<td>' . esc_html( $debug_label ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render persisted/default rule config listing for current business.
	 *
	 * @param array<string,mixed> $payload Listing payload.
	 * @return void
	 */
	protected function render_operational_rules_admin_listing( array $payload ) {
		$rules      = isset( $payload['rules'] ) && is_array( $payload['rules'] ) ? $payload['rules'] : array();
		$summary    = isset( $payload['summary'] ) && is_array( $payload['summary'] ) ? $payload['summary'] : array();
		$business_id = isset( $payload['business_id'] ) ? absint( $payload['business_id'] ) : 0;
		$has_persisted = ! empty( $summary['has_persisted'] );

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Rules by Business', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html__( 'Basic edit', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Edit basic operational rule settings for the current tenant: enabled, execution_mode and max_items_auto.', 'super-mechanic' ) . '</p>';
		if ( $business_id > 0 ) {
			echo '<p class="sm-card-copy">' . esc_html( sprintf( __( 'Business ID: %d', 'super-mechanic' ), $business_id ) ) . '</p>';
		}
		if ( ! $has_persisted ) {
			echo '<div class="sm-notice-card">';
			echo '<strong>' . esc_html__( 'No custom rules configured for this business yet.', 'super-mechanic' ) . '</strong>';
			echo '<p class="sm-card-copy">' . esc_html__( 'Default rule settings are active. Save any row below to create the first custom configuration.', 'super-mechanic' ) . '</p>';
			echo '</div>';
		}

		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Total rules', 'super-mechanic' ),
			isset( $summary['total'] ) ? absint( $summary['total'] ) : count( $rules ),
			__( 'Supported rule keys for this business', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Persisted', 'super-mechanic' ),
			isset( $summary['persisted'] ) ? absint( $summary['persisted'] ) : 0,
			__( 'Rules loaded from database', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Defaults', 'super-mechanic' ),
			isset( $summary['defaults'] ) ? absint( $summary['defaults'] ) : 0,
			__( 'Rules using fallback defaults', 'super-mechanic' )
		);
		echo '</div>';

		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'rule_key', 'super-mechanic' ) . '</th><th>' . esc_html__( 'enabled', 'super-mechanic' ) . '</th><th>' . esc_html__( 'execution_mode', 'super-mechanic' ) . '</th><th>' . esc_html__( 'thresholds', 'super-mechanic' ) . '</th><th>' . esc_html__( 'limits', 'super-mechanic' ) . '</th><th>' . esc_html__( 'source', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Edit basic config', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $rules ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No rules available for this business.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rules as $rule ) {
				$rule_key       = isset( $rule['rule_key'] ) ? sanitize_key( (string) $rule['rule_key'] ) : '';
				$enabled        = ! empty( $rule['enabled'] );
				$execution_mode = isset( $rule['execution_mode'] ) ? sanitize_key( (string) $rule['execution_mode'] ) : 'manual';
				$thresholds     = isset( $rule['thresholds'] ) && is_array( $rule['thresholds'] ) ? $rule['thresholds'] : array();
				$limits         = isset( $rule['limits'] ) && is_array( $rule['limits'] ) ? $rule['limits'] : array();
				$source         = isset( $rule['source'] ) ? sanitize_key( (string) $rule['source'] ) : 'default';
				$max_items_auto = isset( $limits['max_items_auto'] ) ? absint( $limits['max_items_auto'] ) : '';

				echo '<tr>';
				echo '<td>' . esc_html( $rule_key ) . '</td>';
				echo '<td>' . ( $enabled ? '<span class="sm-badge sm-badge-success">' . esc_html__( 'true', 'super-mechanic' ) . '</span>' : '<span class="sm-badge sm-badge-neutral">' . esc_html__( 'false', 'super-mechanic' ) . '</span>' ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_execution_mode_badge( $execution_mode ) ) . '</td>';
					echo '<td>' . wp_kses_post( $this->format_rule_meta_display( $thresholds ) ) . '</td>';
					echo '<td>' . wp_kses_post( $this->format_rule_meta_display( $limits ) ) . '</td>';
				echo '<td>' . ( 'db' === $source ? '<span class="sm-badge sm-badge-primary">' . esc_html__( 'db', 'super-mechanic' ) . '</span>' : '<span class="sm-badge sm-badge-neutral">' . esc_html__( 'default', 'super-mechanic' ) . '</span>' ) . '</td>';
				echo '<td>';
				echo '<form method="post" style="display:flex;flex-direction:column;gap:8px;min-width:220px;">';
				echo '<input type="hidden" name="sm_operational_rule_action" value="save_basic" />';
				echo '<input type="hidden" name="business_id" value="' . esc_attr( $business_id ) . '" />';
				echo '<input type="hidden" name="rule_key" value="' . esc_attr( $rule_key ) . '" />';
				echo '<input type="hidden" name="enabled" value="0" />';
				echo '<label><input type="checkbox" name="enabled" value="1" ' . checked( $enabled, true, false ) . ' /> ' . esc_html__( 'Enabled', 'super-mechanic' ) . '</label>';
				echo '<label>' . esc_html__( 'Execution mode', 'super-mechanic' ) . ' ';
				echo '<select name="execution_mode">';
				echo '<option value="manual"' . selected( $execution_mode, 'manual', false ) . '>' . esc_html__( 'manual', 'super-mechanic' ) . '</option>';
				echo '<option value="confirmable"' . selected( $execution_mode, 'confirmable', false ) . '>' . esc_html__( 'confirmable', 'super-mechanic' ) . '</option>';
				echo '<option value="auto"' . selected( $execution_mode, 'auto', false ) . '>' . esc_html__( 'auto', 'super-mechanic' ) . '</option>';
				echo '</select></label>';
				echo '<label>' . esc_html__( 'max_items_auto', 'super-mechanic' ) . ' <input type="number" min="1" step="1" name="max_items_auto" value="' . esc_attr( (string) $max_items_auto ) . '" /></label>';
				wp_nonce_field( 'sm_operational_rule_save', 'sm_operational_rule_nonce', false, true );
				echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Save', 'super-mechanic' ) . '</button>';
				echo '</form>';
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render guided manual actions from triggered rules.
	 *
	 * @param array<string,mixed> $payload Guided actions payload.
	 * @return void
	 */
	protected function render_guided_rule_actions( array $payload ) {
		$guided_actions = isset( $payload['guided_actions'] ) && is_array( $payload['guided_actions'] ) ? $payload['guided_actions'] : array();
		$summary        = isset( $payload['summary'] ) && is_array( $payload['summary'] ) ? $payload['summary'] : array();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Acciones Guiadas por Reglas', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html__( 'Manual execution', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Triggered rules mapped to safe manual actions using existing execution handlers.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Total guided actions', 'super-mechanic' ),
			isset( $summary['total'] ) ? absint( $summary['total'] ) : 0,
			__( 'Rules with mapped actions', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Executable now', 'super-mechanic' ),
			isset( $summary['executable'] ) ? absint( $summary['executable'] ) : 0,
			__( 'Safe actions ready to run', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Not executable', 'super-mechanic' ),
			isset( $summary['non_executable'] ) ? absint( $summary['non_executable'] ) : 0,
			__( 'Rules needing additional runtime conditions', 'super-mechanic' )
		);
		echo '</div>';

		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Rule', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Impact', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Suggested action', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Reason', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Execute', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $guided_actions ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No guided actions available.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $guided_actions as $guided_action ) {
				$rule_key   = isset( $guided_action['rule_key'] ) ? sanitize_key( (string) $guided_action['rule_key'] ) : 'rule';
				$triggered  = ! empty( $guided_action['triggered'] );
				$impact     = isset( $guided_action['impact_level'] ) ? sanitize_key( (string) $guided_action['impact_level'] ) : 'info';
				$label      = isset( $guided_action['label'] ) ? sanitize_text_field( (string) $guided_action['label'] ) : __( 'Manual action', 'super-mechanic' );
				$reason     = isset( $guided_action['reason'] ) ? sanitize_text_field( (string) $guided_action['reason'] ) : '';
				$action_type = isset( $guided_action['action_type'] ) ? sanitize_key( (string) $guided_action['action_type'] ) : '';
				$executable = ! empty( $guided_action['executable'] );
				$exec_payload = isset( $guided_action['execution_payload'] ) && is_array( $guided_action['execution_payload'] ) ? $guided_action['execution_payload'] : array();

				echo '<tr>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $rule_key ) ) ) . ( $triggered ? ' <span class="sm-badge sm-badge-warning">' . esc_html__( 'Triggered', 'super-mechanic' ) . '</span>' : ' <span class="sm-badge sm-badge-neutral">' . esc_html__( 'Not triggered', 'super-mechanic' ) . '</span>' ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( in_array( $impact, array( 'critical', 'warning' ), true ) ? $impact : 'normal' ) ) . '</td>';
				echo '<td>' . esc_html( $label ) . '</td>';
				echo '<td>' . esc_html( $reason ) . '</td>';
				echo '<td>';
				if ( $triggered && $executable && in_array( $action_type, array( 'bulk_resolve', 'bulk_reassign' ), true ) ) {
					$action_key     = isset( $exec_payload['action_key'] ) ? sanitize_key( (string) $exec_payload['action_key'] ) : $action_type;
					$business_id    = isset( $exec_payload['business_id'] ) ? absint( $exec_payload['business_id'] ) : 0;
					$entity_type    = isset( $exec_payload['entity_type'] ) ? sanitize_key( (string) $exec_payload['entity_type'] ) : 'crm_task';
					$ids            = isset( $exec_payload['ids'] ) ? sanitize_text_field( (string) $exec_payload['ids'] ) : '';
					$target_user_id = isset( $exec_payload['target_user_id'] ) ? absint( $exec_payload['target_user_id'] ) : 0;
					$button_label   = 'bulk_resolve' === $action_key ? __( 'Resolve now', 'super-mechanic' ) : __( 'Reassign now', 'super-mechanic' );

					if ( $business_id > 0 && '' !== $ids ) {
						echo '<form method="post" style="margin:0;">';
						echo '<input type="hidden" name="sm_operational_bulk_action" value="' . esc_attr( $action_key ) . '" />';
						echo '<input type="hidden" name="sm_execution_source" value="controlled" />';
						echo '<input type="hidden" name="sm_execution_mode" value="manual" />';
						echo '<input type="hidden" name="sm_rule_key" value="' . esc_attr( $rule_key ) . '" />';
						echo '<input type="hidden" name="business_id" value="' . esc_attr( $business_id ) . '" />';
						echo '<input type="hidden" name="entity_type" value="' . esc_attr( $entity_type ) . '" />';
						echo '<input type="hidden" name="ids" value="' . esc_attr( $ids ) . '" />';
						if ( $target_user_id > 0 ) {
							echo '<input type="hidden" name="target_user_id" value="' . esc_attr( $target_user_id ) . '" />';
						}
						wp_nonce_field( 'sm_operational_bulk_action', 'sm_operational_bulk_action_nonce', false, true );
						echo '<button type="submit" class="button button-secondary">' . esc_html( $button_label ) . '</button>';
						echo '</form>';
					} else {
						echo esc_html__( 'No direct action available yet. Review the related recommendation context.', 'super-mechanic' );
					}
				} elseif ( $triggered && $executable && 'open_center' === $action_type ) {
					$url = isset( $exec_payload['url'] ) ? esc_url_raw( (string) $exec_payload['url'] ) : '';
					if ( '' !== $url ) {
						echo '<a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html__( 'Open center', 'super-mechanic' ) . '</a>';
					} else {
						echo esc_html__( 'No direct action available yet. Review the related recommendation context.', 'super-mechanic' );
					}
				} elseif ( ! $triggered ) {
					echo esc_html__( 'Rule not triggered', 'super-mechanic' );
				} else {
					echo esc_html__( 'No direct action available yet. Review the related recommendation context.', 'super-mechanic' );
				}
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render semi-automatic confirmable actions from triggered rules.
	 *
	 * @param array<string,mixed> $payload Confirmable actions payload.
	 * @return void
	 */
	protected function render_confirmable_rule_actions( array $payload ) {
		$confirmable_actions = isset( $payload['confirmable_actions'] ) && is_array( $payload['confirmable_actions'] ) ? $payload['confirmable_actions'] : array();
		$summary             = isset( $payload['summary'] ) && is_array( $payload['summary'] ) ? $payload['summary'] : array();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Acciones Confirmables por Reglas', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-warning">' . esc_html__( 'Confirmation required', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Triggered rules prepare executable actions, but data mutation only runs after explicit confirmation.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Total confirmable actions', 'super-mechanic' ),
			isset( $summary['total'] ) ? absint( $summary['total'] ) : 0,
			__( 'Rules mapped to confirmable flow', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Ready to confirm', 'super-mechanic' ),
			isset( $summary['confirmable'] ) ? absint( $summary['confirmable'] ) : 0,
			__( 'Executable with manual confirmation', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Not executable', 'super-mechanic' ),
			isset( $summary['non_executable'] ) ? absint( $summary['non_executable'] ) : 0,
			__( 'Rules requiring additional runtime conditions', 'super-mechanic' )
		);
		echo '</div>';

		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Rule', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Impact', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Affected', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Prepared action', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Reason', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Execute', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $confirmable_actions ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No confirmable actions available.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $confirmable_actions as $action ) {
				$rule_key         = isset( $action['rule_key'] ) ? sanitize_key( (string) $action['rule_key'] ) : 'rule';
				$triggered        = ! empty( $action['triggered'] );
				$impact           = isset( $action['impact_level'] ) ? sanitize_key( (string) $action['impact_level'] ) : 'info';
				$label            = isset( $action['label'] ) ? sanitize_text_field( (string) $action['label'] ) : __( 'Prepared action', 'super-mechanic' );
				$reason           = isset( $action['reason'] ) ? sanitize_text_field( (string) $action['reason'] ) : '';
				$action_type      = isset( $action['action_type'] ) ? sanitize_key( (string) $action['action_type'] ) : '';
				$executable       = ! empty( $action['executable'] );
				$confirm_required = ! empty( $action['confirm_required'] );
				$affected_count   = isset( $action['affected_count'] ) ? absint( $action['affected_count'] ) : 0;
				$exec_payload     = isset( $action['execution_payload'] ) && is_array( $action['execution_payload'] ) ? $action['execution_payload'] : array();

				echo '<tr>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $rule_key ) ) ) . ( $triggered ? ' <span class="sm-badge sm-badge-warning">' . esc_html__( 'Triggered', 'super-mechanic' ) . '</span>' : ' <span class="sm-badge sm-badge-neutral">' . esc_html__( 'Not triggered', 'super-mechanic' ) . '</span>' ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( in_array( $impact, array( 'critical', 'warning' ), true ) ? $impact : 'normal' ) ) . '</td>';
				echo '<td>' . esc_html( $affected_count ) . '</td>';
				echo '<td>' . esc_html( $label ) . '</td>';
				echo '<td>' . esc_html( $reason ) . '</td>';
				echo '<td>';
				if ( $triggered && $executable && $confirm_required && in_array( $action_type, array( 'bulk_resolve', 'bulk_reassign' ), true ) ) {
					$action_key     = isset( $exec_payload['action_key'] ) ? sanitize_key( (string) $exec_payload['action_key'] ) : $action_type;
					$business_id    = isset( $exec_payload['business_id'] ) ? absint( $exec_payload['business_id'] ) : 0;
					$entity_type    = isset( $exec_payload['entity_type'] ) ? sanitize_key( (string) $exec_payload['entity_type'] ) : 'crm_task';
					$ids            = isset( $exec_payload['ids'] ) ? sanitize_text_field( (string) $exec_payload['ids'] ) : '';
					$target_user_id = isset( $exec_payload['target_user_id'] ) ? absint( $exec_payload['target_user_id'] ) : 0;

					if ( $business_id > 0 && '' !== $ids ) {
						echo '<form method="post" style="margin:0;">';
						echo '<input type="hidden" name="sm_operational_bulk_action" value="' . esc_attr( $action_key ) . '" />';
						echo '<input type="hidden" name="sm_execution_source" value="controlled" />';
						echo '<input type="hidden" name="sm_execution_mode" value="confirmable" />';
						echo '<input type="hidden" name="sm_rule_key" value="' . esc_attr( $rule_key ) . '" />';
						echo '<input type="hidden" name="business_id" value="' . esc_attr( $business_id ) . '" />';
						echo '<input type="hidden" name="entity_type" value="' . esc_attr( $entity_type ) . '" />';
						echo '<input type="hidden" name="ids" value="' . esc_attr( $ids ) . '" />';
						if ( $target_user_id > 0 ) {
							echo '<input type="hidden" name="target_user_id" value="' . esc_attr( $target_user_id ) . '" />';
						}
						wp_nonce_field( 'sm_operational_bulk_action', 'sm_operational_bulk_action_nonce', false, true );
						echo '<button type="submit" class="button button-primary">' . esc_html__( 'Confirm and Run', 'super-mechanic' ) . '</button>';
						echo '</form>';
					} else {
						echo esc_html__( 'No direct action available yet. Review the related recommendation context.', 'super-mechanic' );
					}
				} elseif ( $triggered && $executable && 'open_center' === $action_type ) {
					$url = isset( $exec_payload['url'] ) ? esc_url_raw( (string) $exec_payload['url'] ) : '';
					if ( '' !== $url ) {
						echo '<a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html__( 'Open center', 'super-mechanic' ) . '</a>';
					} else {
						echo esc_html__( 'No direct action available yet. Review the related recommendation context.', 'super-mechanic' );
					}
				} elseif ( ! $triggered ) {
					echo esc_html__( 'Rule not triggered', 'super-mechanic' );
				} else {
					echo esc_html__( 'No direct action available yet. Review the related recommendation context.', 'super-mechanic' );
				}
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render controlled automatic execution block (explicit run only).
	 *
	 * @param array<string,mixed> $payload Auto execution payload.
	 * @return void
	 */
	protected function render_controlled_auto_execution( array $payload ) {
		$rows       = isset( $payload['auto_execution'] ) && is_array( $payload['auto_execution'] ) ? $payload['auto_execution'] : array();
		$summary    = isset( $payload['summary'] ) && is_array( $payload['summary'] ) ? $payload['summary'] : array();
		$meta       = isset( $payload['meta'] ) && is_array( $payload['meta'] ) ? $payload['meta'] : array();
		$business_id = isset( $meta['business_id'] ) ? absint( $meta['business_id'] ) : 0;
		$user_id     = isset( $meta['user_id'] ) ? absint( $meta['user_id'] ) : get_current_user_id();
		$mode        = isset( $meta['mode'] ) ? sanitize_key( (string) $meta['mode'] ) : 'preview';

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Ejecución Automática Controlada', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-warning">' . esc_html__( 'Controlled run', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Low-risk rules can run automatically only after explicit dashboard request. No cron and no hidden execution.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Eligible rules', 'super-mechanic' ),
			isset( $summary['eligible_rules'] ) ? absint( $summary['eligible_rules'] ) : 0,
			__( 'Ready for controlled run', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Executed rules', 'super-mechanic' ),
			isset( $summary['executed_rules'] ) ? absint( $summary['executed_rules'] ) : 0,
			__( 'Executed in this request', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Blocked rules', 'super-mechanic' ),
			isset( $summary['blocked_rules'] ) ? absint( $summary['blocked_rules'] ) : 0,
			__( 'Blocked by policy or safety', 'super-mechanic' )
		);
		echo '</div>';

		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Rule', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Impact', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Auto executable', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Result', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Affected', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Reason', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No controlled auto execution rules available.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$rule_key        = isset( $row['rule_key'] ) ? sanitize_key( (string) $row['rule_key'] ) : 'rule';
				$impact_level    = isset( $row['impact_level'] ) ? sanitize_key( (string) $row['impact_level'] ) : 'info';
				$auto_executable = ! empty( $row['auto_executable'] );
				$result          = isset( $row['result'] ) ? sanitize_key( (string) $row['result'] ) : 'skipped';
				$affected_count  = isset( $row['affected_count'] ) ? absint( $row['affected_count'] ) : 0;
				$reason          = isset( $row['reason'] ) ? sanitize_text_field( (string) $row['reason'] ) : '';

				echo '<tr>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $rule_key ) ) ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( in_array( $impact_level, array( 'critical', 'warning' ), true ) ? $impact_level : 'normal' ) ) . '</td>';
				echo '<td>' . ( $auto_executable ? '<span class="sm-badge sm-badge-success">' . esc_html__( 'Yes', 'super-mechanic' ) . '</span>' : '<span class="sm-badge sm-badge-neutral">' . esc_html__( 'No', 'super-mechanic' ) . '</span>' ) . '</td>';
				echo '<td>' . esc_html( ucfirst( $result ) ) . '</td>';
				echo '<td>' . esc_html( $affected_count ) . '</td>';
				echo '<td>' . esc_html( $reason ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';

		echo '<div class="sm-page-actions">';
		echo '<form method="post" style="margin:0;">';
		echo '<input type="hidden" name="sm_controlled_auto_execution_action" value="run" />';
		echo '<input type="hidden" name="sm_execution_source" value="controlled" />';
		echo '<input type="hidden" name="business_id" value="' . esc_attr( $business_id ) . '" />';
		echo '<input type="hidden" name="user_id" value="' . esc_attr( $user_id ) . '" />';
		wp_nonce_field( 'sm_controlled_auto_execution', 'sm_controlled_auto_execution_nonce', false, true );
		wp_nonce_field( 'sm_operational_bulk_action', 'sm_operational_bulk_action_nonce', false, true );
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Run controlled auto execution', 'super-mechanic' ) . '</button>';
		echo '</form>';
		echo '<span class="sm-badge sm-badge-neutral">' . esc_html( 'run' === $mode ? __( 'Last mode: run', 'super-mechanic' ) : __( 'Last mode: preview', 'super-mechanic' ) ) . '</span>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render execution safety and rollback availability block.
	 *
	 * @param array<string,mixed> $payload Safety payload.
	 * @param int                 $business_id Business ID.
	 * @return void
	 */
	protected function render_execution_safety_section( array $payload, $business_id ) {
		$execution_guard = isset( $payload['execution_guard'] ) && is_array( $payload['execution_guard'] ) ? $payload['execution_guard'] : array();
		$rollback        = isset( $payload['rollback'] ) && is_array( $payload['rollback'] ) ? $payload['rollback'] : array();
		$allowed         = ! empty( $execution_guard['allowed'] );
		$risk_level      = isset( $execution_guard['risk_level'] ) ? sanitize_key( (string) $execution_guard['risk_level'] ) : 'medium';
		$reason          = isset( $execution_guard['reason'] ) ? sanitize_text_field( (string) $execution_guard['reason'] ) : '';

		$rollback_available = ! empty( $rollback['available'] );
		$rollback_supported = ! empty( $rollback['supported'] );
		$action_type        = isset( $rollback['action_type'] ) ? sanitize_key( (string) $rollback['action_type'] ) : '';
		$snapshot_key       = isset( $rollback['snapshot_key'] ) ? sanitize_text_field( (string) $rollback['snapshot_key'] ) : '';
		$item_count         = isset( $rollback['items'] ) && is_array( $rollback['items'] ) ? count( $rollback['items'] ) : 0;

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Guardrails / Execution Safety', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Safety layer', 'super-mechanic' ) . '</span></div>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Execution allowed', 'super-mechanic' ),
			$allowed ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ),
			__( 'Pre-execution guardrail result', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Risk level', 'super-mechanic' ),
			ucfirst( $risk_level ),
			__( 'Evaluated before mutation', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Rollback', 'super-mechanic' ),
			$rollback_available ? __( 'Available', 'super-mechanic' ) : __( 'Not available', 'super-mechanic' ),
			__( 'Only for supported controlled actions', 'super-mechanic' )
		);
		echo '</div>';

		echo '<p class="sm-card-copy">' . esc_html( $reason ) . '</p>';

		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Rollback action', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Supported', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Available', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Items', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Execute', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		echo '<tr>';
		echo '<td>' . esc_html( '' !== $action_type ? strtoupper( str_replace( '_', ' ', $action_type ) ) : __( 'N/A', 'super-mechanic' ) ) . '</td>';
		echo '<td>' . esc_html( $rollback_supported ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</td>';
		echo '<td>' . esc_html( $rollback_available ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</td>';
		echo '<td>' . esc_html( $item_count ) . '</td>';
		echo '<td>';
		if ( $rollback_supported && $rollback_available && '' !== $action_type && '' !== $snapshot_key ) {
			echo '<form method="post" style="margin:0;">';
			echo '<input type="hidden" name="sm_controlled_execution_rollback_action" value="run" />';
			echo '<input type="hidden" name="business_id" value="' . esc_attr( absint( $business_id ) ) . '" />';
			echo '<input type="hidden" name="action_type" value="' . esc_attr( $action_type ) . '" />';
			echo '<input type="hidden" name="snapshot_key" value="' . esc_attr( $snapshot_key ) . '" />';
			wp_nonce_field( 'sm_controlled_execution_rollback', 'sm_controlled_execution_rollback_nonce', false, true );
			echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Rollback', 'super-mechanic' ) . '</button>';
			echo '</form>';
		} else {
			echo esc_html__( 'Rollback not available', 'super-mechanic' );
		}
		echo '</td>';
		echo '</tr>';
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render operational assignment suggestions.
	 *
	 * @param array<string,mixed> $payload Assignment payload.
	 * @return void
	 */
	protected function render_operational_assignments( array $payload ) {
		$overloaded  = isset( $payload['overloaded_users'] ) && is_array( $payload['overloaded_users'] ) ? $payload['overloaded_users'] : array();
		$available   = isset( $payload['available_users'] ) && is_array( $payload['available_users'] ) ? $payload['available_users'] : array();
		$assignments = isset( $payload['assignments'] ) && is_array( $payload['assignments'] ) ? $payload['assignments'] : array();
		$summary     = isset( $payload['summary'] ) && is_array( $payload['summary'] ) ? $payload['summary'] : array();
		$business_id = isset( $payload['meta']['business_id'] ) ? absint( $payload['meta']['business_id'] ) : 0;

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Asignación Operativa', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Suggested only', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Load balancing proposals without applying any real assignment changes.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Overloaded users', 'super-mechanic' ),
			isset( $summary['overloaded_users'] ) ? absint( $summary['overloaded_users'] ) : 0,
			__( 'Users with high critical load', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Available users', 'super-mechanic' ),
			isset( $summary['available_users'] ) ? absint( $summary['available_users'] ) : 0,
			__( 'Users with low operational load', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Assignment proposals', 'super-mechanic' ),
			isset( $summary['proposals'] ) ? absint( $summary['proposals'] ) : 0,
			__( 'Suggested workload redistribution', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h3>' . esc_html__( 'Saturated users', 'super-mechanic' ) . '</h3></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'User', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Critical', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Warning', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $overloaded ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No saturated users detected.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $overloaded as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( isset( $row['display_name'] ) ? (string) $row['display_name'] : '' ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['critical'] ) ? $row['critical'] : 0 ) ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['warning'] ) ? $row['warning'] : 0 ) ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['total'] ) ? $row['total'] : 0 ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h3>' . esc_html__( 'Available users', 'super-mechanic' ) . '</h3></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'User', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Critical', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Warning', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $available ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No available users detected.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $available as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( isset( $row['display_name'] ) ? (string) $row['display_name'] : '' ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['critical'] ) ? $row['critical'] : 0 ) ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['warning'] ) ? $row['warning'] : 0 ) ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['total'] ) ? $row['total'] : 0 ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
		echo '</div>';

		$proposals_count                  = isset( $summary['proposals'] ) ? absint( $summary['proposals'] ) : 0;
		$overloaded_users_count           = isset( $summary['overloaded_users'] ) ? absint( $summary['overloaded_users'] ) : 0;
		$available_users_count            = isset( $summary['available_users'] ) ? absint( $summary['available_users'] ) : 0;
		$executable_task_candidates_count = isset( $summary['executable_task_candidates'] ) ? absint( $summary['executable_task_candidates'] ) : 0;
		if ( 0 === $proposals_count ) {
			echo '<div class="sm-notice-card">';
			echo '<strong>' . esc_html__( 'No executable assignment proposals yet.', 'super-mechanic' ) . '</strong>';
			echo '<p class="sm-card-copy">' . esc_html__( 'Proposals require at least one overloaded user, one available user, and a reassignable CRM task candidate.', 'super-mechanic' ) . '</p>';
			echo '<ul class="sm-inline-list">';
			echo '<li>' . esc_html( sprintf( __( 'Overloaded users detected: %d', 'super-mechanic' ), $overloaded_users_count ) ) . '</li>';
			echo '<li>' . esc_html( sprintf( __( 'Available users detected: %d', 'super-mechanic' ), $available_users_count ) ) . '</li>';
			echo '<li>' . esc_html( sprintf( __( 'Reassignable CRM task candidates: %d', 'super-mechanic' ), $executable_task_candidates_count ) ) . '</li>';
			echo '</ul>';
			echo '</div>';
		}
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'From', 'super-mechanic' ) . '</th><th>' . esc_html__( 'To', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Reason', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Delta', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Action', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $assignments ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No redistribution proposals right now.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $assignments as $proposal ) {
				$from_name = isset( $proposal['from_name'] ) ? sanitize_text_field( (string) $proposal['from_name'] ) : '';
				$to_name   = isset( $proposal['to_name'] ) ? sanitize_text_field( (string) $proposal['to_name'] ) : '';
				$reason    = isset( $proposal['reason'] ) ? sanitize_key( (string) $proposal['reason'] ) : 'saturation_balance';
				$delta     = isset( $proposal['workload_delta'] ) ? absint( $proposal['workload_delta'] ) : 0;
				$level     = isset( $proposal['level'] ) ? sanitize_key( (string) $proposal['level'] ) : 'warning';
				$from_user = isset( $proposal['from_user'] ) ? absint( $proposal['from_user'] ) : 0;
				$to_user   = isset( $proposal['to_user'] ) ? absint( $proposal['to_user'] ) : 0;
				$entity_id = isset( $proposal['entity_id'] ) ? absint( $proposal['entity_id'] ) : 0;
				$entity    = isset( $proposal['entity_type'] ) ? sanitize_key( (string) $proposal['entity_type'] ) : '';
				$executable = ! empty( $proposal['executable'] ) && 'crm_task' === $entity && $entity_id > 0 && $from_user > 0 && $to_user > 0 && $business_id > 0;
				echo '<tr>';
				echo '<td>' . esc_html( $from_name ) . '</td>';
				echo '<td>' . esc_html( $to_name ) . '</td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $reason ) ) ) . '</td>';
				echo '<td>' . esc_html( $delta ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $level ) ) . '</td>';
				if ( $executable ) {
					echo '<td><form method="post" style="margin:0;">';
					echo '<input type="hidden" name="sm_operational_reassign_action" value="execute" />';
					echo '<input type="hidden" name="business_id" value="' . esc_attr( $business_id ) . '" />';
					echo '<input type="hidden" name="from_user" value="' . esc_attr( $from_user ) . '" />';
					echo '<input type="hidden" name="to_user" value="' . esc_attr( $to_user ) . '" />';
					echo '<input type="hidden" name="entity_type" value="' . esc_attr( $entity ) . '" />';
					echo '<input type="hidden" name="entity_id" value="' . esc_attr( $entity_id ) . '" />';
					wp_nonce_field( 'sm_operational_reassign', 'sm_operational_reassign_nonce', false, true );
					echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Reassign', 'super-mechanic' ) . '</button>';
					echo '</form></td>';
				} else {
					echo '<td>' . esc_html__( 'Not executable', 'super-mechanic' ) . '</td>';
				}
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render centralized automation console.
	 *
	 * @param array<string,mixed> $payload Automation console payload.
	 * @return void
	 */
	protected function render_operational_automation_console( array $payload ) {
		$status          = isset( $payload['system_status'] ) && is_array( $payload['system_status'] ) ? $payload['system_status'] : array();
		$flags           = isset( $payload['flags'] ) && is_array( $payload['flags'] ) ? $payload['flags'] : array();
		$escalation      = isset( $payload['escalation'] ) && is_array( $payload['escalation'] ) ? $payload['escalation'] : array();
		$recommendations = isset( $payload['recommendations'] ) && is_array( $payload['recommendations'] ) ? $payload['recommendations'] : array();
		$assignments     = isset( $payload['assignments'] ) && is_array( $payload['assignments'] ) ? $payload['assignments'] : array();

		$global_level = isset( $status['global_level'] ) ? sanitize_key( (string) $status['global_level'] ) : 'normal';
		$badge_class  = 'sm-badge sm-badge-success';
		$badge_label  = __( 'Normal', 'super-mechanic' );
		if ( 'critical' === $global_level ) {
			$badge_class = 'sm-badge sm-badge-danger';
			$badge_label = __( 'Critical', 'super-mechanic' );
		} elseif ( 'warning' === $global_level ) {
			$badge_class = 'sm-badge sm-badge-warning';
			$badge_label = __( 'Warning', 'super-mechanic' );
		}

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Consola de Automatización', 'super-mechanic' ) . '</h2><span class="' . esc_attr( $badge_class ) . '">' . esc_html( $badge_label ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Centralized read-only overview of automatic operational layers.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Active flags', 'super-mechanic' ),
			isset( $status['active_flags'] ) ? absint( $status['active_flags'] ) : 0,
			__( 'From automation flags layer', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Blocking flags', 'super-mechanic' ),
			isset( $status['blocking_flags'] ) ? absint( $status['blocking_flags'] ) : 0,
			__( 'From escalation layer', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Recommendations', 'super-mechanic' ),
			isset( $recommendations['summary']['total'] ) ? absint( $recommendations['summary']['total'] ) : 0,
			__( 'Suggested next actions', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Assignment proposals', 'super-mechanic' ),
			isset( $assignments['summary']['proposals'] ) ? absint( $assignments['summary']['proposals'] ) : 0,
			__( 'Suggested redistribution only', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Layer', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Main count', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		echo '<tr><td>' . esc_html__( 'Flags', 'super-mechanic' ) . '</td><td>' . esc_html__( 'Active', 'super-mechanic' ) . '</td><td>' . esc_html( isset( $flags['summary']['active_flags'] ) ? absint( $flags['summary']['active_flags'] ) : 0 ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Escalation', 'super-mechanic' ) . '</td><td>' . esc_html( ucfirst( $global_level ) ) . '</td><td>' . esc_html( isset( $escalation['blocking_flags'] ) && is_array( $escalation['blocking_flags'] ) ? count( $escalation['blocking_flags'] ) : 0 ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Recommendations', 'super-mechanic' ) . '</td><td>' . esc_html__( 'Generated', 'super-mechanic' ) . '</td><td>' . esc_html( isset( $recommendations['summary']['total'] ) ? absint( $recommendations['summary']['total'] ) : 0 ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Assignments', 'super-mechanic' ) . '</td><td>' . esc_html__( 'Suggested', 'super-mechanic' ) . '</td><td>' . esc_html( isset( $assignments['summary']['proposals'] ) ? absint( $assignments['summary']['proposals'] ) : 0 ) . '</td></tr>';
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render assisted operational actions block.
	 *
	 * @param array<string,mixed> $payload Assisted actions payload.
	 * @return void
	 */
	protected function render_operational_assisted_actions( array $payload ) {
		$actions = isset( $payload['actions'] ) && is_array( $payload['actions'] ) ? $payload['actions'] : array();
		$summary = isset( $payload['summary'] ) && is_array( $payload['summary'] ) ? $payload['summary'] : array();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Acciones Operativas Asistidas', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html__( 'Manual actions', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Safe navigation actions to execute operational follow-up manually.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Total actions', 'super-mechanic' ),
			isset( $summary['total'] ) ? absint( $summary['total'] ) : 0,
			__( 'Available manual actions', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Critical actions', 'super-mechanic' ),
			isset( $summary['critical'] ) ? absint( $summary['critical'] ) : 0,
			__( 'Highest operational priority', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Warning actions', 'super-mechanic' ),
			isset( $summary['warning'] ) ? absint( $summary['warning'] ) : 0,
			__( 'Recommended follow-up', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Action', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Context', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Navigate', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $actions ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No assisted actions available.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $actions as $action ) {
				$label   = isset( $action['label'] ) ? sanitize_text_field( (string) $action['label'] ) : __( 'Open module', 'super-mechanic' );
				$level   = isset( $action['level'] ) ? sanitize_key( (string) $action['level'] ) : 'warning';
				$context = isset( $action['context'] ) ? sanitize_text_field( (string) $action['context'] ) : '';
				$url     = isset( $action['url'] ) ? esc_url_raw( (string) $action['url'] ) : '';
				echo '<tr>';
				echo '<td>' . esc_html( $label ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $level ) ) . '</td>';
				echo '<td>' . esc_html( $context ) . '</td>';
				if ( '' !== $url ) {
					echo '<td><a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'super-mechanic' ) . '</a></td>';
				} else {
					echo '<td>' . esc_html__( 'No direct action available yet. Review the related recommendation context.', 'super-mechanic' ) . '</td>';
				}
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render operational bulk actions block.
	 *
	 * @param array<string,mixed> $payload Bulk actions payload.
	 * @return void
	 */
	protected function render_operational_bulk_actions( array $payload ) {
		$groups      = isset( $payload['groups'] ) && is_array( $payload['groups'] ) ? $payload['groups'] : array();
		$summary     = isset( $payload['summary'] ) && is_array( $payload['summary'] ) ? $payload['summary'] : array();
		$business_id = isset( $payload['meta']['business_id'] ) ? absint( $payload['meta']['business_id'] ) : 0;

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Acciones Masivas Seguras', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-warning">' . esc_html__( 'Bulk operations', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Controlled bulk operations for supported entities without automatic execution.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Total groups', 'super-mechanic' ),
			isset( $summary['total_groups'] ) ? absint( $summary['total_groups'] ) : 0,
			__( 'Bulk candidates detected', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Executable groups', 'super-mechanic' ),
			isset( $summary['executable_groups'] ) ? absint( $summary['executable_groups'] ) : 0,
			__( 'Ready for manual execution', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Group', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Entity', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Count', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Action', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Execute', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $groups ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No bulk action groups available.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $groups as $group ) {
				$group_key      = isset( $group['group_key'] ) ? sanitize_key( (string) $group['group_key'] ) : 'group';
				$entity_type    = isset( $group['entity_type'] ) ? sanitize_key( (string) $group['entity_type'] ) : '';
				$count          = isset( $group['count'] ) ? absint( $group['count'] ) : 0;
				$level          = isset( $group['level'] ) ? sanitize_key( (string) $group['level'] ) : 'warning';
				$action         = isset( $group['action'] ) ? sanitize_key( (string) $group['action'] ) : '';
				$is_executable  = ! empty( $group['executable'] );
				$ids            = isset( $group['items'] ) && is_array( $group['items'] ) ? implode( ',', array_map( 'absint', $group['items'] ) ) : '';
				$target_user_id = isset( $group['target_user_id'] ) ? absint( $group['target_user_id'] ) : 0;
				$button_label   = 'bulk_resolve' === $action ? __( 'Resolve all', 'super-mechanic' ) : __( 'Reassign all', 'super-mechanic' );

				echo '<tr>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $group_key ) ) ) . '</td>';
				echo '<td>' . esc_html( strtoupper( str_replace( '_', ' ', $entity_type ) ) ) . '</td>';
				echo '<td>' . esc_html( $count ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $level ) ) . '</td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $action ) ) ) . '</td>';
				if ( $is_executable && '' !== $ids && $business_id > 0 ) {
					echo '<td><form method="post" style="margin:0;">';
					echo '<input type="hidden" name="sm_operational_bulk_action" value="' . esc_attr( $action ) . '" />';
					echo '<input type="hidden" name="sm_execution_source" value="controlled" />';
					echo '<input type="hidden" name="business_id" value="' . esc_attr( $business_id ) . '" />';
					echo '<input type="hidden" name="entity_type" value="' . esc_attr( $entity_type ) . '" />';
					echo '<input type="hidden" name="ids" value="' . esc_attr( $ids ) . '" />';
					if ( $target_user_id > 0 ) {
						echo '<input type="hidden" name="target_user_id" value="' . esc_attr( $target_user_id ) . '" />';
					}
					wp_nonce_field( 'sm_operational_bulk_action', 'sm_operational_bulk_action_nonce', false, true );
					echo '<button type="submit" class="button button-secondary">' . esc_html( $button_label ) . '</button>';
					echo '</form></td>';
				} else {
					echo '<td>' . esc_html__( 'Not executable', 'super-mechanic' ) . '</td>';
				}
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render one workload bucket table.
	 *
	 * @param string                            $title Bucket title.
	 * @param array<int,array<string,mixed>>    $items Bucket items.
	 * @param string                            $empty_message Empty message.
	 * @return void
	 */
	protected function render_workload_bucket_table( $title, array $items, $empty_message ) {
		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h3>' . esc_html( $title ) . '</h3><span class="sm-badge sm-badge-neutral">' . esc_html( count( $items ) ) . '</span></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Title', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Date', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Priority', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $items ) ) {
			echo '<tr><td colspan="4">' . esc_html( $empty_message ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				$type      = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : 'task';
				$title     = isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : __( 'Work item', 'super-mechanic' );
				$url       = isset( $item['url'] ) ? esc_url_raw( (string) $item['url'] ) : '';
				$date      = isset( $item['date'] ) ? (string) $item['date'] : '';
				$priority  = isset( $item['priority'] ) ? sanitize_key( (string) $item['priority'] ) : 'normal';
				echo '<tr>';
				echo '<td>' . esc_html( ucfirst( $type ) ) . '</td>';
				if ( '' !== $url ) {
					echo '<td><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></td>';
				} else {
					echo '<td>' . esc_html( $title ) . '</td>';
				}
				echo '<td>' . esc_html( $this->format_datetime_label( $date ) ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $priority ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Register automation center submenu.
	 *
	 * @return void
	 */
	public function register_automation_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Automation Center', 'super-mechanic' ),
			__( 'Automation Center', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-automation',
			array( $this, 'render_automation_page' )
		);
	}

	/**
	 * Render automation center page.
	 *
	 * @return void
	 */
	public function render_automation_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}
		$this->start_request_profiling( 'automation_center' );

		$this->maybe_handle_operational_bulk_action_request();
		$this->maybe_handle_controlled_auto_execution_request();
		$this->maybe_handle_controlled_execution_rollback_request();
		$this->maybe_handle_operational_rule_update_request();

		$selected_workload_user_id = isset( $_GET['workload_user_id'] ) ? absint( wp_unslash( $_GET['workload_user_id'] ) ) : get_current_user_id();
		if ( $selected_workload_user_id <= 0 ) {
			$selected_workload_user_id = get_current_user_id();
		}

		$business_id = $this->resolve_business_id_for_user( $selected_workload_user_id );

		$timer = $this->profile_mark_start();
		$operational_rules_overview = $this->workload_service->get_operational_rules_overview( $business_id );
		$this->profile_mark_end( 'data.rules_overview', $timer );
		$timer = $this->profile_mark_start();
		$rules_admin_listing = $this->operational_rules_service->get_operational_rules_admin_listing( $business_id );
		$this->profile_mark_end( 'data.rules_listing', $timer );
		$timer = $this->profile_mark_start();
		$guided_rule_actions = $this->workload_service->get_guided_rule_actions( $business_id, $selected_workload_user_id );
		$this->profile_mark_end( 'data.guided_actions', $timer );
		$timer = $this->profile_mark_start();
		$confirmable_rule_actions = $this->workload_service->get_confirmable_rule_actions( $business_id, $selected_workload_user_id );
		$this->profile_mark_end( 'data.confirmable_actions', $timer );
		$timer = $this->profile_mark_start();
		$controlled_auto_execution  = is_array( $this->auto_execution_payload ) ? $this->auto_execution_payload : $this->workload_service->get_controlled_auto_execution_overview( $business_id, $selected_workload_user_id );
		$this->profile_mark_end( 'data.auto_execution', $timer );
		$timer = $this->profile_mark_start();
		$execution_safety_overview  = $this->workload_service->get_execution_safety_overview( $business_id, $selected_workload_user_id );
		$this->profile_mark_end( 'data.execution_safety', $timer );
		$timer = $this->profile_mark_start();
		$automation_console = $this->workload_service->get_operational_automation_console( $business_id, $selected_workload_user_id );
		$this->profile_mark_end( 'data.automation_console', $timer );

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Automation Center', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Supervision, controlled execution, safety, and rule configuration in one dedicated place.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<span class="sm-badge sm-badge-primary">' . esc_html__( 'Automation', 'super-mechanic' ) . '</span>';
		echo '</div>';

		$this->render_bulk_action_notice();
		$this->render_controlled_auto_execution_notice();
		$this->render_controlled_execution_rollback_notice();
		$this->render_operational_rules_update_notice();

		echo '<div class="sm-page-actions">';
		echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=super-mechanic' ) ) . '">' . esc_html__( 'Back to Dashboard', 'super-mechanic' ) . '</a>';
		echo '</div>';

		// A. Reglas Operativas.
		$this->render_operational_rules( $operational_rules_overview );
		// B. Rules by Business.
		$this->render_operational_rules_admin_listing( $rules_admin_listing );
		// C. Acciones Guiadas por Reglas.
		$this->render_guided_rule_actions( $guided_rule_actions );
		// D. Acciones Confirmables por Reglas.
		$this->render_confirmable_rule_actions( $confirmable_rule_actions );
		// E. Ejecución Automática Controlada.
		$this->render_controlled_auto_execution( $controlled_auto_execution );
		// F. Guardrails / Execution Safety.
		$this->render_execution_safety_section( $execution_safety_overview, $business_id );
		// G. Consola de Automatización.
		$this->render_operational_automation_console( $automation_console );

		$this->render_profiling_panel();
		echo '</div>';
	}

	/**
	 * Register operational logs submenu.
	 *
	 * @return void
	 */
	public function register_logs_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Operational Logs', 'super-mechanic' ),
			__( 'Operational Logs', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Render operational logs admin page.
	 *
	 * @return void
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}
		$this->start_request_profiling( 'operational_logs' );

		$current_user_id     = get_current_user_id();
		$current_business_id = $this->resolve_business_id_for_user( $current_user_id );

		$filters = array(
			'rule_key'    => isset( $_GET['rule_key'] ) ? sanitize_key( (string) wp_unslash( $_GET['rule_key'] ) ) : '',
			'result'      => isset( $_GET['result'] ) ? sanitize_key( (string) wp_unslash( $_GET['result'] ) ) : '',
			'date'        => isset( $_GET['date'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['date'] ) ) : '',
			'business_id' => isset( $_GET['business_id'] ) ? absint( wp_unslash( $_GET['business_id'] ) ) : $current_business_id,
		);
		if ( $current_business_id > 0 ) {
			$filters['business_id'] = $current_business_id;
		}
		if ( '' !== $filters['date'] && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date'] ) ) {
			$filters['date'] = '';
		}

		$page     = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$per_page = 20;
		$timer    = $this->profile_mark_start();
		$payload  = $this->execution_log_service->get_logs_list( $filters, $page, $per_page );
		$this->profile_mark_end( 'data.logs_list', $timer );
		$items    = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array();
		$pager    = isset( $payload['pagination'] ) && is_array( $payload['pagination'] ) ? $payload['pagination'] : array();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Operational Logs', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Read-only execution and rule-audit traceability for this business.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<span class="sm-badge sm-badge-neutral">' . esc_html__( 'Read-only', 'super-mechanic' ) . '</span>';
		echo '</div>';

		$this->render_logs_filters_form( $filters );

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Execution and Audit Logs', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html( sprintf( __( 'Total: %d', 'super-mechanic' ), isset( $pager['total'] ) ? absint( $pager['total'] ) : 0 ) ) . '</span></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Rule', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Mode', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Result', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Affected', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Actor', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Business', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Operational debug', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Context', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( empty( $items ) ) {
			echo '<tr><td colspan="10">' . esc_html__( 'No log rows found for current filters. Try widening date or result filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				$date           = isset( $item['date'] ) ? sanitize_text_field( (string) $item['date'] ) : '';
				$rule_key       = isset( $item['rule_key'] ) ? sanitize_key( (string) $item['rule_key'] ) : '';
				$action_type    = isset( $item['action_type'] ) ? sanitize_key( (string) $item['action_type'] ) : '';
				$execution_mode = isset( $item['execution_mode'] ) ? sanitize_key( (string) $item['execution_mode'] ) : 'manual';
				$result         = isset( $item['result'] ) ? sanitize_key( (string) $item['result'] ) : 'unknown';
				$affected_count = isset( $item['affected_count'] ) ? absint( $item['affected_count'] ) : 0;
				$actor_user_id  = isset( $item['actor_user_id'] ) ? absint( $item['actor_user_id'] ) : 0;
				$actor_label    = isset( $item['actor_label'] ) ? sanitize_text_field( (string) $item['actor_label'] ) : '';
				$business_id    = isset( $item['business_id'] ) ? absint( $item['business_id'] ) : 0;
				$debug_reason   = isset( $item['debug_reason'] ) ? sanitize_text_field( (string) $item['debug_reason'] ) : __( 'No debug detail available.', 'super-mechanic' );
				$context        = isset( $item['context_summary'] ) ? sanitize_text_field( (string) $item['context_summary'] ) : '—';
				$actor_render   = '' !== $actor_label ? $actor_label . ' (#' . $actor_user_id . ')' : '#' . $actor_user_id;

				echo '<tr>';
				echo '<td>' . esc_html( $this->format_datetime_label( $date ) ) . '</td>';
				echo '<td>' . esc_html( '' !== $rule_key ? $rule_key : '—' ) . '</td>';
				echo '<td>' . esc_html( $action_type ) . '</td>';
				echo '<td>' . esc_html( $execution_mode ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_execution_log_result_badge( $result ) ) . '</td>';
				echo '<td>' . esc_html( $affected_count ) . '</td>';
				echo '<td>' . esc_html( $actor_render ) . '</td>';
				echo '<td>' . esc_html( $business_id ) . '</td>';
				echo '<td>' . esc_html( $debug_reason ) . '</td>';
				echo '<td>' . esc_html( $context ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';

		$this->render_logs_pagination( $filters, $pager );
		echo '</section>';
		$this->render_profiling_panel();
		echo '</div>';
	}

	/**
	 * Render logs filters form.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return void
	 */
	protected function render_logs_filters_form( array $filters ) {
		$rule_key    = isset( $filters['rule_key'] ) ? sanitize_key( (string) $filters['rule_key'] ) : '';
		$result      = isset( $filters['result'] ) ? sanitize_key( (string) $filters['result'] ) : '';
		$date        = isset( $filters['date'] ) ? sanitize_text_field( (string) $filters['date'] ) : '';
		$business_id = isset( $filters['business_id'] ) ? absint( $filters['business_id'] ) : 0;

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Filters', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Basic', 'super-mechanic' ) . '</span></div>';
		echo '<form method="get" class="sm-inline-form">';
		echo '<input type="hidden" name="page" value="super-mechanic-logs" />';
		echo '<label>' . esc_html__( 'Rule key', 'super-mechanic' ) . ' <input type="text" name="rule_key" value="' . esc_attr( $rule_key ) . '" /></label>';
		echo '<label>' . esc_html__( 'Result', 'super-mechanic' ) . ' ';
		echo '<select name="result">';
		echo '<option value="">' . esc_html__( 'All', 'super-mechanic' ) . '</option>';
		echo '<option value="success"' . selected( $result, 'success', false ) . '>' . esc_html__( 'success', 'super-mechanic' ) . '</option>';
		echo '<option value="partial"' . selected( $result, 'partial', false ) . '>' . esc_html__( 'partial', 'super-mechanic' ) . '</option>';
		echo '<option value="failed"' . selected( $result, 'failed', false ) . '>' . esc_html__( 'failed', 'super-mechanic' ) . '</option>';
		echo '<option value="blocked"' . selected( $result, 'blocked', false ) . '>' . esc_html__( 'blocked', 'super-mechanic' ) . '</option>';
		echo '<option value="unknown"' . selected( $result, 'unknown', false ) . '>' . esc_html__( 'unknown', 'super-mechanic' ) . '</option>';
		echo '</select></label>';
		echo '<label>' . esc_html__( 'Date', 'super-mechanic' ) . ' <input type="date" name="date" value="' . esc_attr( $date ) . '" /></label>';
		if ( $business_id > 0 ) {
			echo '<label>' . esc_html__( 'Business', 'super-mechanic' ) . ' <input type="number" name="business_id" value="' . esc_attr( $business_id ) . '" readonly /></label>';
		}
		echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Apply filters', 'super-mechanic' ) . '</button>';
		echo '<a class="button button-link" href="' . esc_url( admin_url( 'admin.php?page=super-mechanic-logs' ) ) . '">' . esc_html__( 'Reset', 'super-mechanic' ) . '</a>';
		echo '</form>';
		echo '</section>';
	}

	/**
	 * Render logs pagination.
	 *
	 * @param array<string,mixed> $filters Active filters.
	 * @param array<string,mixed> $pager Pagination payload.
	 * @return void
	 */
	protected function render_logs_pagination( array $filters, array $pager ) {
		$total_pages = isset( $pager['total_pages'] ) ? max( 1, absint( $pager['total_pages'] ) ) : 1;
		$current     = isset( $pager['page'] ) ? max( 1, absint( $pager['page'] ) ) : 1;
		if ( $total_pages <= 1 ) {
			return;
		}

		$base_args = array(
			'page'        => 'super-mechanic-logs',
			'rule_key'    => isset( $filters['rule_key'] ) ? sanitize_key( (string) $filters['rule_key'] ) : '',
			'result'      => isset( $filters['result'] ) ? sanitize_key( (string) $filters['result'] ) : '',
			'date'        => isset( $filters['date'] ) ? sanitize_text_field( (string) $filters['date'] ) : '',
			'business_id' => isset( $filters['business_id'] ) ? absint( $filters['business_id'] ) : 0,
		);

		$links = paginate_links(
			array(
				'base'      => add_query_arg( array_merge( $base_args, array( 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ),
				'format'    => '',
				'current'   => $current,
				'total'     => $total_pages,
				'type'      => 'array',
				'prev_text' => __( '&laquo;', 'super-mechanic' ),
				'next_text' => __( '&raquo;', 'super-mechanic' ),
			)
		);

		if ( empty( $links ) || ! is_array( $links ) ) {
			return;
		}

		echo '<div class="tablenav"><div class="tablenav-pages"><span class="pagination-links">';
		foreach ( $links as $link ) {
			echo wp_kses_post( $link ) . ' ';
		}
		echo '</span></div></div>';
	}

	/**
	 * Render result badge for logs table.
	 *
	 * @param string $result Result key.
	 * @return string
	 */
	protected function render_execution_log_result_badge( $result ) {
		$result = sanitize_key( (string) $result );
		$class  = 'sm-badge sm-badge-neutral';
		if ( in_array( $result, array( 'success' ), true ) ) {
			$class = 'sm-badge sm-badge-success';
		} elseif ( in_array( $result, array( 'partial', 'blocked' ), true ) ) {
			$class = 'sm-badge sm-badge-warning';
		} elseif ( in_array( $result, array( 'failed', 'error' ), true ) ) {
			$class = 'sm-badge sm-badge-danger';
		}

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( ucfirst( $result ) ) . '</span>';
	}

	/**
	 * Resolve role-based dashboard view context.
	 *
	 * @return array<string,bool>
	 */
	protected function get_operational_dashboard_role_context() {
		$user             = wp_get_current_user();
		$roles            = $user instanceof \WP_User ? array_map( 'sanitize_key', (array) $user->roles ) : array();
		$is_admin_role    = in_array( 'administrator', $roles, true ) || in_array( 'sm_admin', $roles, true ) || current_user_can( 'manage_options' );
		$is_mechanic_role = in_array( 'sm_mechanic', $roles, true ) && ! $is_admin_role;

		return array(
			'is_admin'    => $is_admin_role,
			'is_mechanic' => $is_mechanic_role,
		);
	}

	/**
	 * Resolve current business context for one user without loading workload payload.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	protected function resolve_business_id_for_user( $user_id ) {
		$user_id     = absint( $user_id );
		$current_uid = get_current_user_id();
		if ( $user_id <= 0 ) {
			$user_id = $current_uid;
		}

		$business_id = absint( $this->business_context_service->resolve_business_id_for_user( $user_id ) );
		if ( $business_id <= 0 && $current_uid > 0 ) {
			$business_id = absint( $this->business_context_service->resolve_business_id_for_user( $current_uid ) );
		}

		return $business_id;
	}

	/**
	 * Get allowed secondary dashboard blocks for lightweight user preferences.
	 *
	 * @return array<string,string>
	 */
	protected function get_dashboard_secondary_blocks_map() {
		return array(
			'recommendations'   => __( 'Smart suggestions', 'super-mechanic' ),
			'automation_summary' => __( 'Automation summary', 'super-mechanic' ),
			'secondary_data'    => __( 'Secondary operational data', 'super-mechanic' ),
		);
	}

	/**
	 * Default dashboard UI preferences.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_dashboard_ui_preferences_defaults() {
		return array(
			'collapsed_blocks'        => array(),
			'hidden_secondary_blocks' => array(),
			'compact_mode'            => 0,
		);
	}

	/**
	 * Sanitize dashboard preferences payload against allowed secondary blocks.
	 *
	 * @param array<string,mixed> $preferences Raw preference payload.
	 * @return array<string,mixed>
	 */
	protected function sanitize_dashboard_ui_preferences( array $preferences ) {
		$defaults     = $this->get_dashboard_ui_preferences_defaults();
		$preferences  = wp_parse_args( $preferences, $defaults );
		$allowed_ids  = array_keys( $this->get_dashboard_secondary_blocks_map() );
		$allowed_keys = array_flip( $allowed_ids );

		$collapsed = array();
		if ( isset( $preferences['collapsed_blocks'] ) && is_array( $preferences['collapsed_blocks'] ) ) {
			foreach ( $preferences['collapsed_blocks'] as $block_id ) {
				$normalized = sanitize_key( (string) $block_id );
				if ( isset( $allowed_keys[ $normalized ] ) ) {
					$collapsed[ $normalized ] = true;
				}
			}
		}

		$hidden = array();
		if ( isset( $preferences['hidden_secondary_blocks'] ) && is_array( $preferences['hidden_secondary_blocks'] ) ) {
			foreach ( $preferences['hidden_secondary_blocks'] as $block_id ) {
				$normalized = sanitize_key( (string) $block_id );
				if ( isset( $allowed_keys[ $normalized ] ) ) {
					$hidden[ $normalized ] = true;
					unset( $collapsed[ $normalized ] );
				}
			}
		}

		return array(
			'collapsed_blocks'        => array_values( array_keys( $collapsed ) ),
			'hidden_secondary_blocks' => array_values( array_keys( $hidden ) ),
			'compact_mode'            => empty( $preferences['compact_mode'] ) ? 0 : 1,
		);
	}

	/**
	 * Resolve dashboard preferences for one user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>
	 */
	protected function get_dashboard_ui_preferences( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		$stored = get_user_meta( $user_id, 'sm_dashboard_ui_preferences', true );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return $this->sanitize_dashboard_ui_preferences( $stored );
	}

	/**
	 * Check if one preference-managed dashboard block is hidden.
	 *
	 * @param string             $block_id Block identifier.
	 * @param array<string,mixed> $preferences Dashboard preferences.
	 * @return bool
	 */
	protected function is_dashboard_block_hidden( $block_id, array $preferences ) {
		$block_id = sanitize_key( (string) $block_id );
		$hidden   = isset( $preferences['hidden_secondary_blocks'] ) && is_array( $preferences['hidden_secondary_blocks'] )
			? $preferences['hidden_secondary_blocks']
			: array();

		return in_array( $block_id, $hidden, true );
	}

	/**
	 * Check if one preference-managed dashboard block is collapsed.
	 *
	 * @param string             $block_id Block identifier.
	 * @param array<string,mixed> $preferences Dashboard preferences.
	 * @return bool
	 */
	protected function is_dashboard_block_collapsed( $block_id, array $preferences ) {
		$block_id   = sanitize_key( (string) $block_id );
		$collapsed  = isset( $preferences['collapsed_blocks'] ) && is_array( $preferences['collapsed_blocks'] )
			? $preferences['collapsed_blocks']
			: array();

		return in_array( $block_id, $collapsed, true );
	}

	/**
	 * Render lightweight dashboard preferences panel.
	 *
	 * @param array<string,mixed> $preferences Dashboard preferences.
	 * @return void
	 */
	protected function render_dashboard_preferences_panel( array $preferences ) {
		$secondary_blocks = $this->get_dashboard_secondary_blocks_map();
		echo '<section class="sm-card sm-section sm-dashboard-preferences" id="sm-dashboard-preferences">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Dashboard view preferences', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Per user', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Collapse or hide only secondary sections. Critical operational blocks always remain visible.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-pref-visibility-grid">';
		foreach ( $secondary_blocks as $block_id => $label ) {
			$checked = ! $this->is_dashboard_block_hidden( $block_id, $preferences );
			echo '<label class="sm-pref-toggle-row">';
			echo '<input type="checkbox" class="sm-pref-visibility-toggle" data-sm-pref-visibility="1" data-sm-pref-block-id="' . esc_attr( $block_id ) . '" ' . checked( $checked, true, false ) . ' />';
			echo '<span>' . esc_html( $label ) . '</span>';
			echo '</label>';
		}
		echo '</div>';
		echo '<div class="sm-pref-controls-row">';
		echo '<label class="sm-pref-toggle-row">';
		echo '<input type="checkbox" id="sm-pref-compact-mode" data-sm-pref-compact="1" ' . checked( ! empty( $preferences['compact_mode'] ), true, false ) . ' />';
		echo '<span>' . esc_html__( 'Compact mode', 'super-mechanic' ) . '</span>';
		echo '</label>';
		echo '<button type="button" class="button button-secondary sm-pref-reset-layout">' . esc_html__( 'Reset layout', 'super-mechanic' ) . '</button>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render start wrapper for one preference-managed dashboard block.
	 *
	 * @param string              $block_id Block identifier.
	 * @param string              $badge_label Badge label.
	 * @param array<string,mixed> $preferences Dashboard preferences.
	 * @return void
	 */
	protected function render_preference_managed_block_start( $block_id, $badge_label, array $preferences ) {
		$block_id   = sanitize_key( (string) $block_id );
		$collapsed  = $this->is_dashboard_block_collapsed( $block_id, $preferences );
		$is_hidden  = $this->is_dashboard_block_hidden( $block_id, $preferences );
		$block_name = isset( $this->get_dashboard_secondary_blocks_map()[ $block_id ] ) ? $this->get_dashboard_secondary_blocks_map()[ $block_id ] : __( 'Secondary block', 'super-mechanic' );
		$classes    = array( 'sm-pref-block' );

		if ( $collapsed ) {
			$classes[] = 'is-collapsed';
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-sm-pref-block-id="' . esc_attr( $block_id ) . '" data-sm-pref-secondary="1"' . ( $is_hidden ? ' hidden="hidden"' : '' ) . '>';
		echo '<div class="sm-pref-block-toolbar">';
		echo '<span class="sm-badge sm-badge-neutral">' . esc_html( $badge_label ) . '</span>';
		echo '<span class="sm-pref-block-title">' . esc_html( $block_name ) . '</span>';
		echo '<div class="sm-pref-block-actions">';
		echo '<button type="button" class="button button-secondary button-small sm-pref-toggle-collapse" data-sm-pref-action="toggle-collapse" data-sm-pref-block-id="' . esc_attr( $block_id ) . '">' . esc_html( $collapsed ? __( 'Expand block', 'super-mechanic' ) : __( 'Collapse block', 'super-mechanic' ) ) . '</button>';
		echo '<button type="button" class="button button-secondary button-small sm-pref-toggle-visibility" data-sm-pref-action="hide-block" data-sm-pref-block-id="' . esc_attr( $block_id ) . '">' . esc_html__( 'Hide block', 'super-mechanic' ) . '</button>';
		echo '</div>';
		echo '</div>';
		echo '<div class="sm-pref-block-body">';
	}

	/**
	 * Close one preference-managed dashboard block wrapper.
	 *
	 * @return void
	 */
	protected function render_preference_managed_block_end() {
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Enqueue lazy-loading assets for dashboard heavy sections.
	 *
	 * @param int $selected_workload_user_id Selected workload user.
	 * @return void
	 */
	protected function enqueue_dashboard_lazy_assets( $selected_workload_user_id ) {
		$script_path = SM_PLUGIN_PATH . 'assets/js/admin-dashboard.js';
		$script_ver  = file_exists( $script_path ) ? (string) filemtime( $script_path ) : SM_PLUGIN_VERSION;
		$handle      = 'sm-admin-dashboard-lazy';

		wp_enqueue_script(
			$handle,
			SM_PLUGIN_URL . 'assets/js/admin-dashboard.js',
			array(),
			$script_ver,
			true
		);

		wp_localize_script(
			$handle,
			'smDashboardLazy',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'sm_dashboard_lazy_sections' ),
				'action'                => 'sm_dashboard_lazy_sections',
				'profile'               => $this->is_profile_request_enabled() ? 1 : 0,
				'workloadUserId'        => absint( $selected_workload_user_id ),
				'loadingText'           => __( 'Loading section...', 'super-mechanic' ),
				'errorText'             => __( 'This section could not be loaded right now. You can retry from the message below.', 'super-mechanic' ),
				'preferencesAction'     => 'sm_dashboard_save_preferences',
				'preferencesNonce'      => wp_create_nonce( 'sm_dashboard_ui_preferences' ),
				'preferences'           => $this->get_dashboard_ui_preferences( get_current_user_id() ),
				'secondaryBlocks'       => $this->get_dashboard_secondary_blocks_map(),
				'preferenceSaveError'   => __( 'Could not save dashboard preferences right now.', 'super-mechanic' ),
				'collapseLabel'         => __( 'Collapse block', 'super-mechanic' ),
				'expandLabel'           => __( 'Expand block', 'super-mechanic' ),
				'hideLabel'             => __( 'Hide block', 'super-mechanic' ),
				'showLabel'             => __( 'Show block', 'super-mechanic' ),
				'resetPreferencesLabel' => __( 'Reset layout', 'super-mechanic' ),
			)
		);
	}

	/**
	 * Build heavy dashboard sections HTML (deferred via AJAX).
	 *
	 * @param int $selected_workload_user_id Selected workload user.
	 * @return string
	 */
	protected function get_dashboard_heavy_sections_html( $selected_workload_user_id ) {
		$selected_workload_user_id = absint( $selected_workload_user_id );
		$business_id               = $this->resolve_business_id_for_user( $selected_workload_user_id );

		$timer = $this->profile_mark_start();
		$kpis = $this->get_cached_dashboard_payload(
			'kpis',
			$business_id,
			30,
			function () {
				return $this->service->get_admin_kpis();
			}
		);
		$this->profile_mark_end( 'lazy.data.kpis', $timer );
		$timer          = $this->profile_mark_start();
		$process_status = $this->get_cached_dashboard_payload(
			'process_status_summary',
			$business_id,
			60,
			function () {
				return $this->service->get_processes_by_status();
			}
		);
		$this->profile_mark_end( 'lazy.data.process_status', $timer );
		$timer         = $this->profile_mark_start();
		$process_types = $this->get_cached_dashboard_payload(
			'process_type_summary',
			$business_id,
			60,
			function () {
				return $this->service->get_processes_by_type();
			}
		);
		$this->profile_mark_end( 'lazy.data.process_types', $timer );
		$timer            = $this->profile_mark_start();
		$recent_processes = $this->get_cached_dashboard_payload(
			'recent_processes',
			$business_id,
			90,
			function () {
				return $this->service->get_recent_processes( 10 );
			}
		);
		$this->profile_mark_end( 'lazy.data.recent_processes', $timer );
		$timer           = $this->profile_mark_start();
		$recent_vehicles = $this->get_cached_dashboard_payload(
			'recent_vehicles',
			$business_id,
			120,
			function () {
				return $this->service->get_recent_vehicles( 10 );
			}
		);
		$this->profile_mark_end( 'lazy.data.recent_vehicles', $timer );
		$timer          = $this->profile_mark_start();
		$recent_clients = $this->get_cached_dashboard_payload(
			'recent_clients',
			$business_id,
			120,
			function () {
				return $this->service->get_recent_clients( 10 );
			}
		);
		$this->profile_mark_end( 'lazy.data.recent_clients', $timer );
		$timer              = $this->profile_mark_start();
		$today_appointments = $this->get_cached_dashboard_payload(
			'today_appointments',
			$business_id,
			30,
			function () {
				return $this->service->get_today_appointments( 8 );
			}
		);
		$this->profile_mark_end( 'lazy.data.today_appointments', $timer );
		$timer                 = $this->profile_mark_start();
		$upcoming_appointments = $this->get_cached_dashboard_payload(
			'upcoming_appointments',
			$business_id,
			60,
			function () {
				return $this->service->get_upcoming_appointments( 7, 8 );
			}
		);
		$this->profile_mark_end( 'lazy.data.upcoming_appointments', $timer );

		$timer = $this->profile_mark_start();
		ob_start();
		echo '<div class="sm-secondary-sections">';

		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card( __( 'Clients', 'super-mechanic' ), $kpis['total_clients'], __( 'Total registered base', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-clients' ) );
		$this->render_kpi_card( __( 'Vehicles', 'super-mechanic' ), $kpis['total_vehicles'], __( 'Active in tracking', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-vehicles' ) );
		$this->render_kpi_card( __( 'Processes', 'super-mechanic' ), $kpis['total_processes'], __( 'Consolidated historical workload', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-processes' ) );
		$this->render_kpi_card( __( 'Open processes', 'super-mechanic' ), $kpis['open_processes'], __( 'Immediate operational workload', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-processes', array( 'filter_status' => 'open' ) ) );
		echo '</div>';

		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Processes by status', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html( count( $process_status ) ) . ' ' . esc_html__( 'groups', 'super-mechanic' ) . '</span></div>';
		$this->render_simple_summary_table( $process_status, __( 'Status', 'super-mechanic' ), 'status' );
		echo '</section>';

		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Processes by type', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html( count( $process_types ) ) . ' ' . esc_html__( 'groups', 'super-mechanic' ) . '</span></div>';
		$this->render_simple_summary_table( $process_types, __( 'Type', 'super-mechanic' ), 'process_type' );
		echo '</section>';
		echo '</div>';

		echo '<section class="sm-section sm-secondary-compact">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Latest processes', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Detailed view', 'super-mechanic' ) . '</span></div>';
		echo '<div class="sm-table-wrap sm-table-wrap-compact"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Title', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Actions', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_processes ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No recent processes found.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recent_processes as $process ) {
				$process_id = absint( $process['id'] );
				echo '<tr>';
				echo '<td>' . esc_html( $process_id ) . '</td>';
				echo '<td><a href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-processes', array( 'action' => 'edit', 'id' => $process_id ) ) ) . '">' . esc_html( $process['title'] ) . '</a></td>';
				echo '<td>' . esc_html( $this->humanize_key( $process['process_type'] ) ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_status_badge( $process['status'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_vehicle_label( $process ) ) . '</td>';
				echo '<td>' . esc_html( $process['client_name'] ? $process['client_name'] : __( 'Unassigned', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_process_quick_links( $process_id, isset( $process['process_type'] ) ? (string) $process['process_type'] : '' ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card sm-card-muted sm-secondary-compact">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Today appointments', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html( count( $today_appointments ) ) . '</span></div>';
		$this->render_appointments_table( $today_appointments, __( 'No appointments for today.', 'super-mechanic' ) );
		echo '</section>';

		echo '<section class="sm-card sm-card-muted sm-secondary-compact">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Upcoming appointments', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( '7 days', 'super-mechanic' ) . '</span></div>';
		$this->render_appointments_table( $upcoming_appointments, __( 'No upcoming appointments in the next 7 days.', 'super-mechanic' ) );
		echo '</section>';
		echo '</div>';

		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card sm-secondary-compact">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Latest vehicles', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap sm-table-wrap-compact"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Plate', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_vehicles ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No recent vehicles found.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recent_vehicles as $vehicle ) {
				echo '<tr><td>' . esc_html( $vehicle['id'] ) . '</td><td><a href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-vehicles', array( 'action' => 'view', 'id' => absint( $vehicle['id'] ) ) ) ) . '">' . esc_html( $this->format_vehicle_label( $vehicle ) ) . '</a></td><td>' . esc_html( $vehicle['plate'] ) . '</td><td>' . esc_html( ! empty( $vehicle['client_name'] ) ? $vehicle['client_name'] : __( 'Unassigned', 'super-mechanic' ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-card sm-secondary-compact">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Latest clients', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap sm-table-wrap-compact"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Name', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Email', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Phone', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_clients ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No recent clients found.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recent_clients as $client ) {
				$name = trim( $client['first_name'] . ' ' . $client['last_name'] );
				echo '<tr><td>' . esc_html( $client['id'] ) . '</td><td><a href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-clients', array( 'action' => 'view', 'id' => absint( $client['id'] ) ) ) ) . '">' . esc_html( $name ) . '</a></td><td>' . esc_html( $client['email'] ) . '</td><td>' . esc_html( $client['phone'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
		echo '</div>';
		echo '</div>';
		$this->profile_mark_end( 'lazy.render_sections', $timer );

		return (string) ob_get_clean();
	}

	/**
	 * Build transient key for dashboard aggregates.
	 *
	 * @param string $key Cache key.
	 * @param int    $business_id Business ID.
	 * @return string
	 */
	protected function build_dashboard_cache_key( $key, $business_id ) {
		$key         = sanitize_key( (string) $key );
		$business_id = absint( $business_id );

		return 'sm_dash_46b_' . md5( $key . '|' . $business_id );
	}

	/**
	 * Get dashboard payload with runtime + transient caching.
	 *
	 * @param string   $key Cache key.
	 * @param int      $business_id Business ID.
	 * @param int      $ttl Cache TTL in seconds.
	 * @param callable $resolver Resolver callback.
	 * @return mixed
	 */
	protected function get_cached_dashboard_payload( $key, $business_id, $ttl, callable $resolver ) {
		$key         = sanitize_key( (string) $key );
		$business_id = absint( $business_id );
		$ttl         = max( 30, min( 120, absint( $ttl ) ) );
		$request_key = $key . ':' . $business_id;

		if ( array_key_exists( $request_key, $this->dashboard_request_cache ) ) {
			return $this->dashboard_request_cache[ $request_key ];
		}

		if ( $business_id > 0 ) {
			$transient_key = $this->build_dashboard_cache_key( $key, $business_id );
			$cached_value  = get_transient( $transient_key );
			if ( false !== $cached_value ) {
				$this->dashboard_request_cache[ $request_key ] = $cached_value;
				return $cached_value;
			}
		}

		$value = call_user_func( $resolver );
		$this->dashboard_request_cache[ $request_key ] = $value;

		if ( $business_id > 0 ) {
			set_transient( $this->build_dashboard_cache_key( $key, $business_id ), $value, $ttl );
		}

		return $value;
	}

	/**
	 * Start per-request profiling when enabled by secure flag.
	 *
	 * @param string $context Context label.
	 * @return void
	 */
	protected function start_request_profiling( $context ) {
		if ( ! $this->is_profile_request_enabled() ) {
			$this->profiling_enabled = false;
			return;
		}

		$this->profiling_enabled            = true;
		$this->profiling_context            = sanitize_key( (string) $context );
		$this->profiling_request_started_at = microtime( true );
		$this->profiling_marks              = array();
	}

	/**
	 * Check if profiling is enabled in current request.
	 *
	 * @return bool
	 */
	protected function is_profile_request_enabled() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			return false;
		}

		$from_get  = isset( $_GET['sm_profile'] ) ? absint( wp_unslash( $_GET['sm_profile'] ) ) : 0;
		$from_post = isset( $_POST['sm_profile'] ) ? absint( wp_unslash( $_POST['sm_profile'] ) ) : 0;

		return 1 === $from_get || 1 === $from_post;
	}

	/**
	 * Start one profile mark.
	 *
	 * @return float
	 */
	protected function profile_mark_start() {
		return microtime( true );
	}

	/**
	 * End one profile mark.
	 *
	 * @param string $label Label.
	 * @param float  $started_at Started timestamp.
	 * @return void
	 */
	protected function profile_mark_end( $label, $started_at ) {
		if ( ! $this->profiling_enabled ) {
			return;
		}

		$elapsed_ms = max( 0, ( microtime( true ) - (float) $started_at ) * 1000 );
		$this->profiling_marks[] = array(
			'label'      => sanitize_text_field( (string) $label ),
			'elapsed_ms' => $elapsed_ms,
		);
	}

	/**
	 * Export profiling payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_profile_export_payload() {
		if ( ! $this->profiling_enabled ) {
			return array(
				'enabled' => false,
			);
		}

		$total_ms = max( 0, ( microtime( true ) - $this->profiling_request_started_at ) * 1000 );

		return array(
			'enabled' => true,
			'context' => $this->profiling_context,
			'total_ms' => round( $total_ms, 2 ),
			'marks'   => array_map(
				function ( $mark ) {
					if ( ! is_array( $mark ) ) {
						return array();
					}

					return array(
						'label'      => isset( $mark['label'] ) ? sanitize_text_field( (string) $mark['label'] ) : '',
						'elapsed_ms' => isset( $mark['elapsed_ms'] ) ? round( (float) $mark['elapsed_ms'], 2 ) : 0.0,
					);
				},
				$this->profiling_marks
			),
		);
	}

	/**
	 * Render internal profiling panel.
	 *
	 * @return void
	 */
	protected function render_profiling_panel() {
		$payload = $this->get_profile_export_payload();
		if ( empty( $payload['enabled'] ) ) {
			return;
		}

		$context = isset( $payload['context'] ) ? sanitize_text_field( (string) $payload['context'] ) : 'request';
		$total   = isset( $payload['total_ms'] ) ? (float) $payload['total_ms'] : 0.0;
		$marks   = isset( $payload['marks'] ) && is_array( $payload['marks'] ) ? $payload['marks'] : array();

		echo '<section id="sm-profile-panel" class="sm-card sm-section sm-profile-panel">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Internal profiling', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html( strtoupper( $context ) ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html( sprintf( __( 'Total request: %sms', 'super-mechanic' ), number_format_i18n( $total, 2 ) ) ) . '</p>';
		echo '<ul id="sm-profile-marks" class="sm-profile-list">';
		foreach ( $marks as $mark ) {
			if ( ! is_array( $mark ) ) {
				continue;
			}
			$label = isset( $mark['label'] ) ? sanitize_text_field( (string) $mark['label'] ) : '';
			$ms    = isset( $mark['elapsed_ms'] ) ? (float) $mark['elapsed_ms'] : 0.0;
			echo '<li><span>' . esc_html( $label ) . '</span><strong>' . esc_html( number_format_i18n( $ms, 2 ) ) . 'ms</strong></li>';
		}
		echo '</ul>';
		echo '</section>';
	}

	/**
	 * Render workload priority badge.
	 *
	 * @param string $priority Priority key.
	 * @return string
	 */
	protected function render_workload_priority_badge( $priority ) {
		$priority = sanitize_key( (string) $priority );
		$class    = 'sm-badge sm-badge-neutral';

		if ( 'critical' === $priority ) {
			$class = 'sm-badge sm-badge-danger';
		} elseif ( 'warning' === $priority ) {
			$class = 'sm-badge sm-badge-warning';
		} elseif ( 'normal' === $priority ) {
			$class = 'sm-badge sm-badge-success';
		}

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( ucfirst( $priority ) ) . '</span>';
	}
}




