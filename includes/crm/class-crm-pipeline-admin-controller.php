<?php
/**
 * CRM pipeline admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\CRM;

use Super_Mechanic\Clients\Client_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CRM pipeline admin flows.
 */
class Crm_Pipeline_Admin_Controller {
	/**
	 * Service.
	 *
	 * @var Crm_Pipeline_Service
	 */
	protected $service;

	/**
	 * Client service.
	 *
	 * @var Client_Service
	 */
	protected $client_service;

	/**
	 * Constructor.
	 *
	 * @param Crm_Pipeline_Service|null $service Service.
	 */
	public function __construct( Crm_Pipeline_Service $service = null ) {
		$this->service        = $service ? $service : new Crm_Pipeline_Service();
		$this->client_service = new Client_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_handle_actions' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render_page() {
		$this->ensure_permissions();

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( 'new' === $action ) {
			$this->render_form_page();
			return;
		}

		if ( 'edit' === $action ) {
			$item = $this->service->get_opportunity( $id );
			if ( empty( $item ) ) {
				wp_die( esc_html__( 'The requested CRM opportunity does not exist.', 'super-mechanic' ) );
			}
			$this->render_form_page( $item, true );
			return;
		}

		if ( 'view' === $action ) {
			$item = $this->service->get_opportunity( $id );
			if ( empty( $item ) ) {
				wp_die( esc_html__( 'The requested CRM opportunity does not exist.', 'super-mechanic' ) );
			}
			$this->render_view_page( $item );
			return;
		}

		$view_mode = $this->get_current_view_mode();
		if ( 'kanban' === $view_mode ) {
			$this->render_kanban_page();
			return;
		}

		$this->render_list_page();
	}

	/**
	 * Handle actions.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_crm_screen() ) {
			return;
		}

		$this->ensure_permissions();

		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			$operation = isset( $_POST['sm_crm_pipeline_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_crm_pipeline_operation'] ) ) : '';
			if ( 'create' === $operation || 'update' === $operation ) {
				$this->handle_save_action( 'update' === $operation );
			}
			if ( 'create_task' === $operation || 'update_task' === $operation ) {
				$this->handle_task_save_action( 'update_task' === $operation );
			}
			if ( 'create_process' === $operation ) {
				$this->handle_create_process_action();
			}
			if ( 'link_process' === $operation ) {
				$this->handle_link_existing_process_action();
			}
		}

		if ( isset( $_GET['action'] ) && 'quick_stage' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$this->handle_quick_stage_action();
		}

		if ( isset( $_GET['action'] ) && 'complete_task' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$this->handle_complete_task_action();
		}

		if ( isset( $_GET['action'] ) && 'delete' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$this->handle_delete_action();
		}
	}

	/**
	 * Render admin notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! $this->is_crm_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';

		if ( 'created' === $notice ) {
			$this->render_notice( __( 'CRM opportunity created successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'updated' === $notice ) {
			$this->render_notice( __( 'CRM opportunity updated successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'deleted' === $notice ) {
			$this->render_notice( __( 'CRM opportunity deleted successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'stage_updated' === $notice ) {
			$label = isset( $_GET['stage_label'] ) ? sanitize_text_field( wp_unslash( $_GET['stage_label'] ) ) : '';
			if ( '' !== $label ) {
				$this->render_notice(
					sprintf(
						/* translators: %s stage label */
						__( 'CRM stage changed to %s.', 'super-mechanic' ),
						$label
					),
					'success'
				);
			} else {
				$this->render_notice( __( 'CRM stage updated successfully.', 'super-mechanic' ), 'success' );
			}
		}

		$hint = isset( $_GET['sm_hint'] ) ? sanitize_key( wp_unslash( $_GET['sm_hint'] ) ) : '';
		if ( 'follow_up_missing' === $hint ) {
			$this->render_notice( __( 'Suggestion: this stage has no pending follow-up task. Consider creating one.', 'super-mechanic' ), 'warning' );
		}

		if ( 'conversion_pending' === $hint ) {
			$this->render_notice( __( 'Conversion pending: this won opportunity is not linked to a process yet.', 'super-mechanic' ), 'warning' );
		}

		if ( 'process_created' === $notice ) {
			$this->render_notice( __( 'Process created and linked successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'process_linked' === $notice ) {
			$this->render_notice( __( 'Existing process linked successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'task_created' === $notice ) {
			$this->render_notice( __( 'CRM task created successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'task_updated' === $notice ) {
			$this->render_notice( __( 'CRM task updated successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'task_completed' === $notice ) {
			$this->render_notice( __( 'CRM task marked as completed.', 'super-mechanic' ), 'success' );
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

	/**
	 * Render list page.
	 *
	 * @return void
	 */
	protected function render_list_page() {
		$search            = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$stage             = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( $_GET['stage'] ) ) : '';
		$assigned_user_id  = isset( $_GET['assigned_user_id'] ) ? absint( $_GET['assigned_user_id'] ) : 0;
		$requires_attention = isset( $_GET['requires_attention'] ) && '1' === sanitize_key( wp_unslash( $_GET['requires_attention'] ) );
		$overdue            = isset( $_GET['overdue'] ) && '1' === sanitize_key( wp_unslash( $_GET['overdue'] ) );
		$view_mode = $this->get_current_view_mode();
		$items  = $this->service->get_opportunities(
			array(
				'search'             => $search,
				'stage'              => $stage,
				'assigned_user_id'   => $assigned_user_id,
				'requires_attention' => $requires_attention ? '1' : '',
				'overdue'            => $overdue ? '1' : '',
				'per_page'           => 200,
				'orderby'            => 'position',
				'order'              => 'ASC',
			)
		);
		$signals_by_id = $this->service->get_automation_signals_for_opportunities( $items );

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header"><div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'CRM Pipeline', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Independent commercial opportunities linked to client, with optional vehicle/process references.', 'super-mechanic' ) . '</p>';
		echo '</div><div class="sm-page-actions">';
		$shared_filters = array(
			's'                 => $search,
			'stage'             => $stage,
			'assigned_user_id'  => $assigned_user_id > 0 ? $assigned_user_id : '',
			'requires_attention' => $requires_attention ? '1' : '',
			'overdue'            => $overdue ? '1' : '',
		);
		echo '<a class="button button-secondary' . ( 'list' === $view_mode ? ' disabled' : '' ) . '" href="' . esc_url( $this->get_page_url( array_merge( $shared_filters, array( 'view_mode' => 'list' ) ) ) ) . '">' . esc_html__( 'List view', 'super-mechanic' ) . '</a>';
		echo '<a class="button button-secondary' . ( 'kanban' === $view_mode ? ' disabled' : '' ) . '" href="' . esc_url( $this->get_page_url( array_merge( $shared_filters, array( 'view_mode' => 'kanban' ) ) ) ) . '">' . esc_html__( 'Kanban view', 'super-mechanic' ) . '</a>';
		echo '<a class="button button-primary" href="' . esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Create opportunity', 'super-mechanic' ) . '</a>';
		echo '</div></div>';

		$this->render_operational_task_views();

		echo '<div class="sm-card sm-filter-card sm-section">';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="super-mechanic-crm-pipeline" />';
		echo '<input type="hidden" name="view_mode" value="list" />';
		echo '<p class="search-box">';
		echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" />';
		echo '<select name="stage"><option value="">' . esc_html__( 'All stages', 'super-mechanic' ) . '</option>';
		foreach ( $this->service->get_stage_catalog() as $stage_key ) {
			echo '<option value="' . esc_attr( $stage_key ) . '"' . selected( $stage, $stage_key, false ) . '>' . esc_html( $this->humanize_stage( $stage_key ) ) . '</option>';
		}
		echo '</select> ';
		echo '<select name="assigned_user_id"><option value="0">' . esc_html__( 'All assignees', 'super-mechanic' ) . '</option>';
		foreach ( $this->get_assignable_users() as $user ) {
			echo '<option value="' . esc_attr( absint( $user->ID ) ) . '"' . selected( $assigned_user_id, absint( $user->ID ), false ) . '>' . esc_html( (string) $user->display_name ) . '</option>';
		}
		echo '</select> ';
		echo '<select name="requires_attention">';
		echo '<option value="">' . esc_html__( 'Attention: all', 'super-mechanic' ) . '</option>';
		echo '<option value="1"' . selected( $requires_attention, true, false ) . '>' . esc_html__( 'Requires attention', 'super-mechanic' ) . '</option>';
		echo '</select> ';
		echo '<select name="overdue">';
		echo '<option value="">' . esc_html__( 'Overdue: all', 'super-mechanic' ) . '</option>';
		echo '<option value="1"' . selected( $overdue, true, false ) . '>' . esc_html__( 'Overdue only', 'super-mechanic' ) . '</option>';
		echo '</select> ';
		submit_button( __( 'Filter', 'super-mechanic' ), 'secondary', '', false );
		echo '</p>';
		echo '</form>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr>';
		echo '<th>ID</th><th>' . esc_html__( 'Stage', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Title', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Phone', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Email', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estimated value', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Position', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Actions', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $items ) ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No CRM opportunities found.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				$view_url   = $this->get_page_url( array( 'action' => 'view', 'id' => absint( $item['id'] ) ) );
				$edit_url   = $this->get_page_url( array( 'action' => 'edit', 'id' => absint( $item['id'] ) ) );
				$delete_url = wp_nonce_url( $this->get_page_url( array( 'action' => 'delete', 'id' => absint( $item['id'] ) ) ), 'sm_delete_crm_pipeline_' . absint( $item['id'] ) );
				$item_signals = isset( $signals_by_id[ absint( $item['id'] ) ] ) ? $signals_by_id[ absint( $item['id'] ) ] : array();
				$row_class    = $this->get_priority_row_class( $item_signals );
				echo '<tr class="' . esc_attr( $row_class ) . '">';
				echo '<td>#' . esc_html( absint( $item['id'] ) ) . '</td>';
				echo '<td>' . $this->render_stage_cell( $item, $item_signals ) . '</td>';
				echo '<td>' . esc_html( (string) $item['title'] ) . $this->render_automation_badges( $item_signals, 'list' ) . '</td>';
				echo '<td>' . esc_html( ! empty( $item['client_name'] ) ? (string) $item['client_name'] : '#' . absint( $item['client_id'] ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $item['client_phone'] ) ? (string) $item['client_phone'] : '-' ) . '</td>';
				echo '<td>' . esc_html( ! empty( $item['client_email'] ) ? (string) $item['client_email'] : '-' ) . '</td>';
				echo '<td>' . esc_html( strtoupper( (string) $item['currency'] ) . ' ' . number_format_i18n( (float) $item['estimated_value'], 2 ) ) . '</td>';
				echo '<td>' . esc_html( absint( $item['position'] ) ) . '</td>';
				echo '<td><a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'super-mechanic' ) . '</a> | <a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'super-mechanic' ) . '</a> | <a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div></div></div>';
	}

	/**
	 * Render kanban page.
	 *
	 * @return void
	 */
	protected function render_kanban_page() {
		$search             = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$stage              = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( $_GET['stage'] ) ) : '';
		$assigned_user_id   = isset( $_GET['assigned_user_id'] ) ? absint( $_GET['assigned_user_id'] ) : 0;
		$requires_attention = isset( $_GET['requires_attention'] ) && '1' === sanitize_key( wp_unslash( $_GET['requires_attention'] ) );
		$overdue            = isset( $_GET['overdue'] ) && '1' === sanitize_key( wp_unslash( $_GET['overdue'] ) );
		$view_mode = $this->get_current_view_mode();
		$catalog   = $this->service->get_stage_catalog();
		$items     = $this->service->get_opportunities(
			array(
				'search'             => $search,
				'stage'              => $stage,
				'assigned_user_id'   => $assigned_user_id,
				'requires_attention' => $requires_attention ? '1' : '',
				'overdue'            => $overdue ? '1' : '',
				'per_page'           => 300,
				'orderby'            => 'position',
				'order'              => 'ASC',
			)
		);
		$signals_by_id = $this->service->get_automation_signals_for_opportunities( $items );

		$stages = $catalog;
		if ( '' !== $stage && in_array( $stage, $catalog, true ) ) {
			$stages = array( $stage );
		}

		$items_by_stage = array();
		foreach ( $catalog as $catalog_stage ) {
			$items_by_stage[ $catalog_stage ] = array();
		}

		foreach ( $items as $item ) {
			$item_stage = isset( $item['stage'] ) ? (string) $item['stage'] : '';
			if ( isset( $items_by_stage[ $item_stage ] ) ) {
				$items_by_stage[ $item_stage ][] = $item;
			}
		}

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header"><div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'CRM Pipeline', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Kanban view by stage. Pipeline remains independent from process workflows.', 'super-mechanic' ) . '</p>';
		echo '</div><div class="sm-page-actions">';
		$shared_filters = array(
			's'                  => $search,
			'stage'              => $stage,
			'assigned_user_id'   => $assigned_user_id > 0 ? $assigned_user_id : '',
			'requires_attention' => $requires_attention ? '1' : '',
			'overdue'            => $overdue ? '1' : '',
		);
		echo '<a class="button button-secondary' . ( 'list' === $view_mode ? ' disabled' : '' ) . '" href="' . esc_url( $this->get_page_url( array_merge( $shared_filters, array( 'view_mode' => 'list' ) ) ) ) . '">' . esc_html__( 'List view', 'super-mechanic' ) . '</a>';
		echo '<a class="button button-secondary' . ( 'kanban' === $view_mode ? ' disabled' : '' ) . '" href="' . esc_url( $this->get_page_url( array_merge( $shared_filters, array( 'view_mode' => 'kanban' ) ) ) ) . '">' . esc_html__( 'Kanban view', 'super-mechanic' ) . '</a>';
		echo '<a class="button button-primary" href="' . esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Create opportunity', 'super-mechanic' ) . '</a>';
		echo '</div></div>';

		$this->render_operational_task_views();

		echo '<div class="sm-card sm-filter-card sm-section">';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="super-mechanic-crm-pipeline" />';
		echo '<input type="hidden" name="view_mode" value="kanban" />';
		echo '<p class="search-box">';
		echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" />';
		echo '<select name="stage"><option value="">' . esc_html__( 'All stages', 'super-mechanic' ) . '</option>';
		foreach ( $catalog as $stage_key ) {
			echo '<option value="' . esc_attr( $stage_key ) . '"' . selected( $stage, $stage_key, false ) . '>' . esc_html( $this->humanize_stage( $stage_key ) ) . '</option>';
		}
		echo '</select> ';
		echo '<select name="assigned_user_id"><option value="0">' . esc_html__( 'All assignees', 'super-mechanic' ) . '</option>';
		foreach ( $this->get_assignable_users() as $user ) {
			echo '<option value="' . esc_attr( absint( $user->ID ) ) . '"' . selected( $assigned_user_id, absint( $user->ID ), false ) . '>' . esc_html( (string) $user->display_name ) . '</option>';
		}
		echo '</select> ';
		echo '<select name="requires_attention">';
		echo '<option value="">' . esc_html__( 'Attention: all', 'super-mechanic' ) . '</option>';
		echo '<option value="1"' . selected( $requires_attention, true, false ) . '>' . esc_html__( 'Requires attention', 'super-mechanic' ) . '</option>';
		echo '</select> ';
		echo '<select name="overdue">';
		echo '<option value="">' . esc_html__( 'Overdue: all', 'super-mechanic' ) . '</option>';
		echo '<option value="1"' . selected( $overdue, true, false ) . '>' . esc_html__( 'Overdue only', 'super-mechanic' ) . '</option>';
		echo '</select> ';
		submit_button( __( 'Filter', 'super-mechanic' ), 'secondary', '', false );
		echo '</p>';
		echo '</form>';
		echo '</div>';

		echo '<div class="sm-section sm-crm-kanban-scroll" style="width:100%;overflow-x:auto;overflow-y:hidden;padding-bottom:6px;">';
		echo '<div class="sm-crm-kanban" style="display:flex;flex-direction:row;flex-wrap:nowrap;align-items:flex-start;gap:14px;width:max-content;min-width:100%;">';
		foreach ( $stages as $stage_key ) {
			$stage_items = isset( $items_by_stage[ $stage_key ] ) ? $items_by_stage[ $stage_key ] : array();
			echo '<div class="sm-card sm-crm-kanban-column" style="flex:0 0 300px;width:300px;min-width:300px;max-width:300px;">';
			echo '<div class="sm-card-header">';
			echo '<h3 class="sm-card-title">' . esc_html( $this->humanize_stage( $stage_key ) ) . '</h3>';
			echo '<span class="sm-badge sm-badge-' . esc_attr( $this->get_stage_tone( $stage_key ) ) . '">' . esc_html( count( $stage_items ) ) . '</span>';
			echo '</div>';

			if ( empty( $stage_items ) ) {
				echo '<p class="sm-card-copy">' . esc_html__( 'No opportunities in this stage.', 'super-mechanic' ) . '</p>';
			} else {
				echo '<div class="sm-crm-kanban-cards">';
				foreach ( $stage_items as $item ) {
					$item_signals = isset( $signals_by_id[ absint( $item['id'] ) ] ) ? $signals_by_id[ absint( $item['id'] ) ] : array();
					echo $this->render_kanban_card(
						$item,
						$search,
						$stage,
						$item_signals,
						array(
							'assigned_user_id'   => $assigned_user_id,
							'requires_attention' => $requires_attention,
							'overdue'            => $overdue,
						)
					);
				}
				echo '</div>';
			}

			echo '</div>';
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render one kanban card.
	 *
	 * @param array<string, mixed> $item Opportunity data.
	 * @param string               $search Active search.
	 * @param string               $stage Active stage filter.
	 * @param array<string,mixed>  $signals Automation signals.
	 * @param array<string,mixed>  $active_filters Active list/kanban filters.
	 * @return string
	 */
	protected function render_kanban_card( array $item, $search, $stage, array $signals = array(), array $active_filters = array() ) {
		$id          = absint( $item['id'] );
		$current     = isset( $item['stage'] ) ? (string) $item['stage'] : 'new_lead';
		$client_name = ! empty( $item['client_name'] ) ? (string) $item['client_name'] : '#' . absint( $item['client_id'] );
		$amount      = strtoupper( (string) $item['currency'] ) . ' ' . number_format_i18n( (float) $item['estimated_value'], 2 );
		$assigned    = $this->get_user_display_name( ! empty( $item['assigned_user_id'] ) ? absint( $item['assigned_user_id'] ) : 0 );

		$vehicle_ref = '';
		if ( ! empty( $item['vehicle_id'] ) ) {
			$vehicle_ref = '#' . absint( $item['vehicle_id'] );
		}

		$process_ref = '';
		if ( ! empty( $item['process_id'] ) ) {
			$process_ref = '#' . absint( $item['process_id'] );
		}

		$view_url = $this->get_page_url( array( 'action' => 'view', 'id' => $id ) );
		$edit_url = $this->get_page_url( array( 'action' => 'edit', 'id' => $id ) );
		$delete_url = wp_nonce_url(
			$this->get_page_url( array( 'action' => 'delete', 'id' => $id, 'view_mode' => 'kanban', 's' => $search, 'stage' => $stage ) ),
			'sm_delete_crm_pipeline_' . $id
		);
		$move_actions = $this->build_quick_stage_actions(
			$id,
			$current,
			array(
				'view_mode'    => 'kanban',
				's'            => $search,
				'filter_stage' => $stage,
				'assigned_user_id'   => ! empty( $active_filters['assigned_user_id'] ) ? absint( $active_filters['assigned_user_id'] ) : '',
				'requires_attention' => ! empty( $active_filters['requires_attention'] ) ? '1' : '',
				'overdue'            => ! empty( $active_filters['overdue'] ) ? '1' : '',
			)
		);

		$html  = '<article class="sm-crm-kanban-card ' . esc_attr( $this->get_priority_row_class( $signals ) ) . '">';
		$html .= '<h4 class="sm-crm-kanban-title">' . esc_html( (string) $item['title'] ) . '</h4>';
		$html .= $this->render_automation_badges( $signals, 'kanban' );
		$html .= '<p class="sm-crm-kanban-line"><strong>' . esc_html__( 'Client:', 'super-mechanic' ) . '</strong> ' . esc_html( $client_name ) . '</p>';
		$html .= '<p class="sm-crm-kanban-line"><strong>' . esc_html__( 'Estimated:', 'super-mechanic' ) . '</strong> ' . esc_html( $amount ) . '</p>';
		$html .= '<p class="sm-crm-kanban-line"><strong>' . esc_html__( 'Assigned:', 'super-mechanic' ) . '</strong> ' . esc_html( $assigned ) . '</p>';

		if ( '' !== $vehicle_ref || '' !== $process_ref ) {
			$refs = array();
			if ( '' !== $vehicle_ref ) {
				$refs[] = __( 'Vehicle', 'super-mechanic' ) . ' ' . $vehicle_ref;
			}
			if ( '' !== $process_ref ) {
				$refs[] = __( 'Process', 'super-mechanic' ) . ' ' . $process_ref;
			}
			$html .= '<p class="sm-crm-kanban-line sm-crm-kanban-muted">' . esc_html( implode( ' | ', $refs ) ) . '</p>';
		}

		$html .= '<p class="sm-crm-kanban-actions"><a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'super-mechanic' ) . '</a> | <a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'super-mechanic' ) . '</a> | <a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete', 'super-mechanic' ) . '</a></p>';

		if ( ! empty( $move_actions ) ) {
			$html .= '<p class="sm-crm-kanban-moves"><span class="sm-crm-kanban-move-label">' . esc_html__( 'Move to:', 'super-mechanic' ) . '</span> ' . implode( ' | ', $move_actions ) . '</p>';
		}

		$html .= '</article>';

		return $html;
	}

	/**
	 * Render read-only detail page.
	 *
	 * @param array<string, mixed> $item Opportunity data.
	 * @return void
	 */
	protected function render_view_page( array $item ) {
		$opportunity_id = absint( $item['id'] );
		$has_process    = ! empty( $item['process_id'] );
		$can_convert    = ( ! $has_process ) && ( 'won' === (string) $item['stage'] || 'view' === ( isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '' ) );
		$signals        = $this->service->get_opportunity_automation_signals( $item );

		$vehicle_text = '-';
		if ( ! empty( $item['vehicle_id'] ) ) {
			$vehicle_text = '#' . absint( $item['vehicle_id'] );
			$vehicle_meta = trim( (string) ( $item['vehicle_make'] ?? '' ) . ' ' . (string) ( $item['vehicle_model'] ?? '' ) . ' ' . (string) ( $item['vehicle_plate'] ?? '' ) );
			if ( '' !== trim( $vehicle_meta ) ) {
				$vehicle_text .= ' - ' . trim( $vehicle_meta );
			}
		}

		$process_text = '-';
		if ( ! empty( $item['process_id'] ) ) {
			$process_text = '#' . absint( $item['process_id'] );
			if ( ! empty( $item['process_title'] ) ) {
				$process_text .= ' - ' . (string) $item['process_title'];
			}
		}

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header"><div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'CRM opportunity detail', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Read-only view for quick commercial context without entering edit mode.', 'super-mechanic' ) . '</p>';
		echo '</div><div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'edit', 'id' => absint( $item['id'] ) ) ) ) . '" class="button button-primary">' . esc_html__( 'Edit opportunity', 'super-mechanic' ) . '</a> ';
		echo '<a href="' . esc_url( $this->get_page_url() ) . '" class="button button-secondary">' . esc_html__( 'Back to pipeline', 'super-mechanic' ) . '</a>';
		echo '</div></div>';

		$view_alert_messages = array();
		$view_alert_type     = 'notice-warning';

		if ( ! empty( $signals['overdue_task_count'] ) ) {
			$view_alert_type       = 'notice-error';
			$view_alert_messages[] = sprintf(
				/* translators: %d overdue tasks count. */
				__( 'Attention required: %d overdue CRM task(s).', 'super-mechanic' ),
				absint( $signals['overdue_task_count'] )
			);
		}
		if ( ! empty( $signals['suggest_follow_up'] ) ) {
			$view_alert_messages[] = __( 'Suggestion: this stage has no pending follow-up task.', 'super-mechanic' );
		}
		if ( ! empty( $signals['conversion_pending'] ) ) {
			$view_alert_messages[] = __( 'Conversion pending: this won opportunity is not linked to a process yet.', 'super-mechanic' );
		}
		if ( ! empty( $signals['inactive_attention'] ) ) {
			$view_alert_messages[] = __( 'Attention required: inactive opportunity without recent CRM activity.', 'super-mechanic' );
		}

		if ( ! empty( $view_alert_messages ) ) {
			echo '<div class="notice ' . esc_attr( $view_alert_type ) . '"><p>' . esc_html__( 'CRM alerts', 'super-mechanic' ) . '</p><ul>';
			foreach ( $view_alert_messages as $view_alert_message ) {
				echo '<li>' . esc_html( $view_alert_message ) . '</li>';
			}
			echo '</ul></div>';
		}

		echo '<div class="sm-card sm-section">';
		echo '<table class="sm-table"><tbody>';
		$this->render_detail_row( __( 'Title', 'super-mechanic' ), (string) $item['title'] );
		$this->render_detail_row( __( 'Client', 'super-mechanic' ), ! empty( $item['client_name'] ) ? (string) $item['client_name'] : '#' . absint( $item['client_id'] ) );
		$this->render_detail_row( __( 'Client phone', 'super-mechanic' ), ! empty( $item['client_phone'] ) ? (string) $item['client_phone'] : '-' );
		$this->render_detail_row( __( 'Client email', 'super-mechanic' ), ! empty( $item['client_email'] ) ? (string) $item['client_email'] : '-' );
		$this->render_detail_row( __( 'Vehicle', 'super-mechanic' ), $vehicle_text );
		$this->render_detail_row( __( 'Process', 'super-mechanic' ), $process_text );
		$this->render_detail_row(
			__( 'Operational linkage', 'super-mechanic' ),
			$has_process
				? sprintf(
					/* translators: %d process id */
					__( 'Linked to process #%d', 'super-mechanic' ),
					absint( $item['process_id'] )
				)
				: __( 'Not linked', 'super-mechanic' )
		);
		$this->render_detail_row( __( 'Stage', 'super-mechanic' ), $this->humanize_stage( (string) $item['stage'] ) );
		$this->render_detail_row( __( 'Assigned user', 'super-mechanic' ), $this->get_user_display_name( ! empty( $item['assigned_user_id'] ) ? absint( $item['assigned_user_id'] ) : 0 ) );
		$this->render_detail_row( __( 'Estimated value', 'super-mechanic' ), strtoupper( (string) $item['currency'] ) . ' ' . number_format_i18n( (float) $item['estimated_value'], 2 ) );
		$this->render_detail_row( __( 'Currency', 'super-mechanic' ), strtoupper( (string) $item['currency'] ) );
		$this->render_detail_row( __( 'Notes', 'super-mechanic' ), '' !== trim( (string) $item['notes'] ) ? (string) $item['notes'] : '-' );
		$this->render_detail_row( __( 'Created at', 'super-mechanic' ), ! empty( $item['created_at'] ) ? (string) $item['created_at'] : '-' );
		$this->render_detail_row( __( 'Updated at', 'super-mechanic' ), ! empty( $item['updated_at'] ) ? (string) $item['updated_at'] : '-' );
		echo '</tbody></table>';
		$this->render_tasks_section( $opportunity_id );
		echo '</div></div>';

		if ( $can_convert ) {
			echo '<div class="wrap sm-admin-shell">';
			echo '<div class="sm-card sm-section">';
			echo '<h3 class="sm-card-title">' . esc_html__( 'Create process', 'super-mechanic' ) . '</h3>';
			echo '<p class="sm-card-copy">' . esc_html__( 'Select process type explicitly before creating from CRM opportunity.', 'super-mechanic' ) . '</p>';
			echo '<form method="post" action="' . esc_url( $this->get_page_url( array( 'action' => 'view', 'id' => $opportunity_id ) ) ) . '">';
			wp_nonce_field( 'sm_create_process_from_crm_' . $opportunity_id, 'sm_create_process_nonce' );
			echo '<input type="hidden" name="sm_crm_pipeline_operation" value="create_process" />';
			echo '<input type="hidden" name="crm_pipeline_id" value="' . esc_attr( $opportunity_id ) . '" />';
			echo '<p><label for="sm_create_process_type">' . esc_html__( 'Process type', 'super-mechanic' ) . '</label> ';
			echo '<select id="sm_create_process_type" name="process_type">';
			echo '<option value="maintenance">' . esc_html__( 'Maintenance', 'super-mechanic' ) . '</option>';
			echo '<option value="pre_delivery">' . esc_html__( 'Pre-delivery', 'super-mechanic' ) . '</option>';
			echo '<option value="paperwork">' . esc_html__( 'Paperwork', 'super-mechanic' ) . '</option>';
			echo '</select> ';
			submit_button( __( 'Create process', 'super-mechanic' ), 'secondary', 'submit', false );
			echo '</p></form>';

			echo '<h3 class="sm-card-title">' . esc_html__( 'Link existing process', 'super-mechanic' ) . '</h3>';
			echo '<p class="sm-card-copy">' . esc_html__( 'Use this only when the process already exists and matches this opportunity context.', 'super-mechanic' ) . '</p>';
			echo '<form method="post" action="' . esc_url( $this->get_page_url( array( 'action' => 'view', 'id' => $opportunity_id ) ) ) . '">';
			wp_nonce_field( 'sm_link_process_from_crm_' . $opportunity_id, 'sm_link_process_nonce' );
			echo '<input type="hidden" name="sm_crm_pipeline_operation" value="link_process" />';
			echo '<input type="hidden" name="crm_pipeline_id" value="' . esc_attr( $opportunity_id ) . '" />';
			echo '<p><label for="sm_link_process_id">' . esc_html__( 'Process ID', 'super-mechanic' ) . '</label> ';
			echo '<input type="number" id="sm_link_process_id" name="process_id" min="1" class="small-text" required /> ';
			submit_button( __( 'Link existing process', 'super-mechanic' ), 'secondary', 'submit', false );
			echo '</p></form></div></div>';
		}
	}

	/**
	 * Render create/edit form.
	 *
	 * @param array<string, mixed> $item Item.
	 * @param bool                 $is_edit Edit mode.
	 * @return void
	 */
	protected function render_form_page( $item = array(), $is_edit = false ) {
		$defaults = array(
			'id'                 => 0,
			'client_id'          => 0,
			'vehicle_id'         => 0,
			'process_id'         => 0,
			'stage'              => 'new_lead',
			'title'              => '',
			'estimated_value'    => '0.00',
			'currency'           => 'USD',
			'assigned_user_id'   => 0,
			'notes'              => '',
			'position'           => 0,
			'quick_client_name'  => '',
			'quick_client_phone' => '',
			'quick_client_email' => '',
		);

		$stored = get_transient( $this->get_form_transient_key() );
		if ( is_array( $stored ) ) {
			$item = array_merge( $item, $stored );
			delete_transient( $this->get_form_transient_key() );
		}

		$item    = wp_parse_args( $item, $defaults );
		$title   = $is_edit ? __( 'Edit CRM opportunity', 'super-mechanic' ) : __( 'Create CRM opportunity', 'super-mechanic' );
		$clients = $this->client_service->get_clients(
			array(
				'per_page' => 200,
				'orderby'  => 'first_name',
				'order'    => 'ASC',
			)
		);

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header"><div class="sm-admin-title">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Pipeline is independent from processes; optional links are references only in this phase.', 'super-mechanic' ) . '</p>';
		echo '</div><div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url() ) . '" class="button button-secondary">' . esc_html__( 'Back to pipeline', 'super-mechanic' ) . '</a>';
		echo '</div></div>';
		echo '<div class="sm-card sm-form-card">';
		echo '<form method="post" action="' . esc_url( $this->get_page_url( $is_edit ? array( 'action' => 'edit', 'id' => absint( $item['id'] ) ) : array( 'action' => 'new' ) ) ) . '">';
		wp_nonce_field( 'sm_save_crm_pipeline', 'sm_crm_pipeline_nonce' );
		echo '<input type="hidden" name="sm_crm_pipeline_operation" value="' . esc_attr( $is_edit ? 'update' : 'create' ) . '" />';
		echo '<input type="hidden" name="crm_pipeline_id" value="' . esc_attr( absint( $item['id'] ) ) . '" />';
		echo '<table class="form-table" role="presentation">';

		$quick_client_selected = (
			0 === absint( $item['client_id'] ) &&
			(
				'' !== trim( (string) $item['quick_client_name'] ) ||
				'' !== trim( (string) $item['quick_client_phone'] ) ||
				'' !== trim( (string) $item['quick_client_email'] )
			)
		);

		echo '<tr><th><label for="client_id">' . esc_html__( 'Client', 'super-mechanic' ) . '</label></th><td><select name="client_id" id="client_id">';
		echo '<option value="0">' . esc_html__( 'Select existing client', 'super-mechanic' ) . '</option>';
		foreach ( $clients as $client ) {
			$label = trim( (string) $client['first_name'] . ' ' . (string) $client['last_name'] );
			if ( '' === $label ) {
				$label = '#' . absint( $client['id'] );
			}
			echo '<option value="' . esc_attr( absint( $client['id'] ) ) . '"' . selected( absint( $item['client_id'] ), absint( $client['id'] ), false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '<option value="new"' . selected( $quick_client_selected, true, false ) . '>' . esc_html__( 'Create new client', 'super-mechanic' ) . '</option>';
		echo '</select><p class="description">' . esc_html__( 'Select an existing client or use quick create below.', 'super-mechanic' ) . '</p></td></tr>';

		$show_quick_client = (
			( '' !== trim( (string) $item['quick_client_name'] ) ) ||
			( '' !== trim( (string) $item['quick_client_phone'] ) ) ||
			( '' !== trim( (string) $item['quick_client_email'] ) )
		);
		echo '<tbody id="sm-crm-quick-client-wrap"' . ( $show_quick_client ? '' : ' style="display:none;"' ) . '>';
		echo '<tr><th colspan="2"><h2 class="sm-subsection-title">' . esc_html__( 'Quick create client (optional)', 'super-mechanic' ) . '</h2></th></tr>';
		echo '<tr><th><label for="quick_client_name">' . esc_html__( 'Client name', 'super-mechanic' ) . '</label></th><td><input name="quick_client_name" type="text" id="quick_client_name" value="' . esc_attr( (string) $item['quick_client_name'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="quick_client_phone">' . esc_html__( 'Client phone', 'super-mechanic' ) . '</label></th><td><input name="quick_client_phone" type="text" id="quick_client_phone" value="' . esc_attr( (string) $item['quick_client_phone'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="quick_client_email">' . esc_html__( 'Client email', 'super-mechanic' ) . '</label></th><td><input name="quick_client_email" type="email" id="quick_client_email" value="' . esc_attr( (string) $item['quick_client_email'] ) . '" class="regular-text" /></td></tr>';
		echo '</tbody>';

		echo '<tr><th><label for="vehicle_id">' . esc_html__( 'Vehicle ID (optional)', 'super-mechanic' ) . '</label></th><td><input name="vehicle_id" type="number" id="vehicle_id" value="' . esc_attr( absint( $item['vehicle_id'] ) ) . '" class="regular-text" min="0" /></td></tr>';
		echo '<tr><th><label for="process_id">' . esc_html__( 'Process ID (optional)', 'super-mechanic' ) . '</label></th><td><input name="process_id" type="number" id="process_id" value="' . esc_attr( absint( $item['process_id'] ) ) . '" class="regular-text" min="0" /></td></tr>';
		echo '<tr><th><label for="stage">' . esc_html__( 'Stage', 'super-mechanic' ) . '</label></th><td><select name="stage" id="stage">';
		foreach ( $this->service->get_stage_catalog() as $stage_key ) {
			echo '<option value="' . esc_attr( $stage_key ) . '"' . selected( (string) $item['stage'], $stage_key, false ) . '>' . esc_html( $this->humanize_stage( $stage_key ) ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="title">' . esc_html__( 'Title', 'super-mechanic' ) . '</label></th><td><input name="title" type="text" id="title" value="' . esc_attr( (string) $item['title'] ) . '" class="regular-text" required /></td></tr>';
		echo '<tr><th><label for="estimated_value">' . esc_html__( 'Estimated value', 'super-mechanic' ) . '</label></th><td><input name="estimated_value" type="number" id="estimated_value" value="' . esc_attr( (string) $item['estimated_value'] ) . '" class="regular-text" step="0.01" min="0" /></td></tr>';
		echo '<tr><th><label for="currency">' . esc_html__( 'Currency', 'super-mechanic' ) . '</label></th><td><input name="currency" type="text" id="currency" value="' . esc_attr( (string) $item['currency'] ) . '" class="small-text" maxlength="10" /></td></tr>';
		echo '<tr><th><label for="assigned_user_id">' . esc_html__( 'Assigned user ID', 'super-mechanic' ) . '</label></th><td><input name="assigned_user_id" type="number" id="assigned_user_id" value="' . esc_attr( absint( $item['assigned_user_id'] ) ) . '" class="regular-text" min="0" /></td></tr>';
		echo '<tr><th><label for="position">' . esc_html__( 'Position in stage', 'super-mechanic' ) . '</label></th><td><input name="position" type="number" id="position" value="' . esc_attr( absint( $item['position'] ) ) . '" class="small-text" min="0" /><p class="description">' . esc_html__( 'Use 0 to auto-assign at the end of the selected stage.', 'super-mechanic' ) . '</p></td></tr>';
		echo '<tr><th><label for="notes">' . esc_html__( 'Notes', 'super-mechanic' ) . '</label></th><td><textarea name="notes" id="notes" class="large-text" rows="5">' . esc_textarea( (string) $item['notes'] ) . '</textarea></td></tr>';
		echo '</table>';
		echo '<div class="sm-form-actions">';
		submit_button( $is_edit ? __( 'Update opportunity', 'super-mechanic' ) : __( 'Create opportunity', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</div></form></div></div>';
		echo '<script>(function(){var s=document.getElementById("client_id");var q=document.getElementById("sm-crm-quick-client-wrap");if(!s||!q){return;}var t=function(){q.style.display=("new"===s.value)?"table-row-group":"none";};s.addEventListener("change",t);t();})();</script>';
	}

	/**
	 * Render operational task views for CRM.
	 *
	 * @return void
	 */
	protected function render_operational_task_views() {
		$buckets = $this->service->get_task_operational_buckets( 7, 8 );

		if ( ! is_array( $buckets ) || empty( $buckets['pending'] ) ) {
			return;
		}

		echo '<div class="sm-card sm-section" style="margin-bottom:14px;">';
		echo '<h2 class="sm-card-title">' . esc_html__( 'Operational CRM tasks', 'super-mechanic' ) . '</h2>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Overdue and upcoming are operational subsets of pending tasks.', 'super-mechanic' ) . '</p>';
		// Inline layout guard keeps KPI cards aligned even if cached stylesheet versions lag.
		echo '<style>.sm-crm-kpi-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:12px;}@media (max-width:782px){.sm-crm-kpi-grid{grid-template-columns:1fr;}}</style>';

		echo '<div class="sm-kpi-grid sm-crm-kpi-grid">';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">' . esc_html__( 'Pending', 'super-mechanic' ) . '</div><div class="sm-kpi-value">' . esc_html( absint( $buckets['pending']['count'] ) ) . '</div></div>';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">' . esc_html__( 'Overdue (subset)', 'super-mechanic' ) . '</div><div class="sm-kpi-value">' . esc_html( absint( $buckets['overdue']['count'] ) ) . '</div></div>';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">' . esc_html__( 'Upcoming 7 days (subset)', 'super-mechanic' ) . '</div><div class="sm-kpi-value">' . esc_html( absint( $buckets['upcoming']['count'] ) ) . '</div></div>';
		echo '</div>';

		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:12px;align-items:start;">';
		$this->render_operational_task_table( __( 'Overdue tasks', 'super-mechanic' ), isset( $buckets['overdue']['items'] ) ? $buckets['overdue']['items'] : array(), 'sm-badge-danger' );
		$this->render_operational_task_table( __( 'Upcoming tasks (next 7 days)', 'super-mechanic' ), isset( $buckets['upcoming']['items'] ) ? $buckets['upcoming']['items'] : array(), 'sm-badge-warning' );
		$this->render_operational_task_table( __( 'Pending tasks', 'super-mechanic' ), isset( $buckets['pending']['items'] ) ? $buckets['pending']['items'] : array(), 'sm-badge-neutral' );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render one operational task table.
	 *
	 * @param string                    $title Section title.
	 * @param array<int, array<string,mixed>> $tasks Tasks.
	 * @return void
	 */
	protected function render_operational_task_table( $title, array $tasks, $badge_class = 'sm-badge-neutral' ) {
		echo '<section class="sm-card sm-card-muted" style="margin-top:12px;">';
		echo '<div class="sm-section-heading"><h3 class="sm-card-title" style="margin:0;">' . esc_html( $title ) . '</h3><span class="sm-badge ' . esc_attr( $badge_class ) . '">' . esc_html( count( $tasks ) ) . '</span></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Task', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Due', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Assigned', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Opportunity', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $tasks ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No tasks in this view.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $tasks as $task ) {
				$opportunity_id = absint( $task['crm_pipeline_id'] );
				$view_url       = $this->get_page_url(
					array(
						'action' => 'view',
						'id'     => $opportunity_id,
					)
				);
				echo '<tr>';
				echo '<td>' . esc_html( (string) $task['title'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( (string) $task['task_type'] ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $task['due_at'] ) ? (string) $task['due_at'] : '-' ) . '</td>';
				echo '<td>' . esc_html( $this->get_user_display_name( ! empty( $task['assigned_user_id'] ) ? absint( $task['assigned_user_id'] ) : 0 ) ) . '</td>';
				echo '<td><a href="' . esc_url( $view_url ) . '">#' . esc_html( $opportunity_id ) . '</a></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render tasks section in opportunity detail.
	 *
	 * @param int $opportunity_id Opportunity ID.
	 * @return void
	 */
	protected function render_tasks_section( $opportunity_id ) {
		$tasks_result  = $this->service->get_tasks_for_opportunity( $opportunity_id );
		$tasks         = is_wp_error( $tasks_result ) ? array() : $tasks_result;
		$edit_task_id  = isset( $_GET['task_id'] ) ? absint( $_GET['task_id'] ) : 0;
		$task_action   = isset( $_GET['task_action'] ) ? sanitize_key( wp_unslash( $_GET['task_action'] ) ) : '';
		$is_edit_task  = ( 'edit_task' === $task_action && $edit_task_id > 0 );
		$task_defaults = array(
			'id'               => 0,
			'title'            => '',
			'task_type'        => 'follow_up',
			'assigned_user_id' => 0,
			'due_at'           => '',
			'status'           => 'pending',
			'notes'            => '',
		);
		$task_item      = $task_defaults;

		if ( $is_edit_task ) {
			$task = $this->service->get_task( $edit_task_id );
			if ( is_array( $task ) && absint( $task['crm_pipeline_id'] ) === absint( $opportunity_id ) ) {
				$task_item = array_merge( $task_item, $task );
			} else {
				$is_edit_task = false;
			}
		}

		echo '<div class="sm-card sm-section" style="margin-top:14px;">';
		echo '<h3 class="sm-card-title">' . esc_html__( 'CRM tasks', 'super-mechanic' ) . '</h3>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Manual tasks for commercial follow-up. No automation is applied in this phase.', 'super-mechanic' ) . '</p>';

		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Title', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Assigned', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Due', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $tasks ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No tasks for this opportunity yet.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $tasks as $task ) {
				$task_id      = absint( $task['id'] );
				$status       = isset( $task['status'] ) ? (string) $task['status'] : 'pending';
				$edit_url     = $this->get_page_url(
					array(
						'action'      => 'view',
						'id'          => $opportunity_id,
						'task_action' => 'edit_task',
						'task_id'     => $task_id,
					)
				);
				$complete_url = wp_nonce_url(
					$this->get_page_url(
						array(
							'action'  => 'complete_task',
							'id'      => $opportunity_id,
							'task_id' => $task_id,
						)
					),
					'sm_complete_crm_task_' . $task_id
				);

				echo '<tr>';
				echo '<td>' . esc_html( (string) $task['title'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( (string) $task['task_type'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->get_user_display_name( ! empty( $task['assigned_user_id'] ) ? absint( $task['assigned_user_id'] ) : 0 ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $task['due_at'] ) ? (string) $task['due_at'] : '-' ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $status ) ) . '</td>';

				$actions = array();
				$actions[] = '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'super-mechanic' ) . '</a>';
				if ( 'completed' !== $status ) {
					$actions[] = '<a href="' . esc_url( $complete_url ) . '">' . esc_html__( 'Mark completed', 'super-mechanic' ) . '</a>';
				}
				echo '<td>' . implode( ' | ', $actions ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';

		echo '<h3 class="sm-card-title" style="margin-top:14px;">' . esc_html( $is_edit_task ? __( 'Edit task', 'super-mechanic' ) : __( 'Create task', 'super-mechanic' ) ) . '</h3>';
		echo '<form method="post" action="' . esc_url( $this->get_page_url( array( 'action' => 'view', 'id' => $opportunity_id ) ) ) . '">';
		wp_nonce_field( 'sm_save_crm_task', 'sm_crm_task_nonce' );
		echo '<input type="hidden" name="sm_crm_pipeline_operation" value="' . esc_attr( $is_edit_task ? 'update_task' : 'create_task' ) . '" />';
		echo '<input type="hidden" name="crm_pipeline_id" value="' . esc_attr( $opportunity_id ) . '" />';
		echo '<input type="hidden" name="task_id" value="' . esc_attr( absint( $task_item['id'] ) ) . '" />';

		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="sm_crm_task_title">' . esc_html__( 'Title', 'super-mechanic' ) . '</label></th><td><input type="text" class="regular-text" required id="sm_crm_task_title" name="task_title" value="' . esc_attr( (string) $task_item['title'] ) . '" /></td></tr>';

		echo '<tr><th><label for="sm_crm_task_type">' . esc_html__( 'Task type', 'super-mechanic' ) . '</label></th><td><select id="sm_crm_task_type" name="task_type">';
		foreach ( $this->service->get_task_type_catalog() as $type_key ) {
			echo '<option value="' . esc_attr( $type_key ) . '"' . selected( (string) $task_item['task_type'], $type_key, false ) . '>' . esc_html( $this->humanize_key( $type_key ) ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th><label for="sm_crm_task_assigned_user_id">' . esc_html__( 'Assigned user ID', 'super-mechanic' ) . '</label></th><td><input type="number" class="small-text" min="0" id="sm_crm_task_assigned_user_id" name="assigned_user_id" value="' . esc_attr( absint( $task_item['assigned_user_id'] ) ) . '" /></td></tr>';

		echo '<tr><th><label for="sm_crm_task_due_at">' . esc_html__( 'Due at', 'super-mechanic' ) . '</label></th><td><input type="datetime-local" id="sm_crm_task_due_at" name="due_at" value="' . esc_attr( $this->to_datetime_local( (string) $task_item['due_at'] ) ) . '" /></td></tr>';

		echo '<tr><th><label for="sm_crm_task_status">' . esc_html__( 'Status', 'super-mechanic' ) . '</label></th><td><select id="sm_crm_task_status" name="status">';
		foreach ( $this->service->get_task_status_catalog() as $status_key ) {
			echo '<option value="' . esc_attr( $status_key ) . '"' . selected( (string) $task_item['status'], $status_key, false ) . '>' . esc_html( $this->humanize_key( $status_key ) ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th><label for="sm_crm_task_notes">' . esc_html__( 'Notes', 'super-mechanic' ) . '</label></th><td><textarea class="large-text" rows="4" id="sm_crm_task_notes" name="notes">' . esc_textarea( (string) $task_item['notes'] ) . '</textarea></td></tr>';
		echo '</tbody></table>';

		submit_button( $is_edit_task ? __( 'Update task', 'super-mechanic' ) : __( 'Create task', 'super-mechanic' ), 'secondary', 'submit', false );
		if ( $is_edit_task ) {
			echo ' <a class="button button-link" href="' . esc_url( $this->get_page_url( array( 'action' => 'view', 'id' => $opportunity_id ) ) ) . '">' . esc_html__( 'Cancel edit', 'super-mechanic' ) . '</a>';
		}
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle create/update.
	 *
	 * @param bool $is_update Update mode.
	 * @return void
	 */
	protected function handle_save_action( $is_update ) {
		check_admin_referer( 'sm_save_crm_pipeline', 'sm_crm_pipeline_nonce' );

		$id   = isset( $_POST['crm_pipeline_id'] ) ? absint( wp_unslash( $_POST['crm_pipeline_id'] ) ) : 0;
		$data = array(
			'client_id'        => isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0,
			'vehicle_id'       => isset( $_POST['vehicle_id'] ) ? wp_unslash( $_POST['vehicle_id'] ) : 0,
			'process_id'       => isset( $_POST['process_id'] ) ? wp_unslash( $_POST['process_id'] ) : 0,
			'stage'            => isset( $_POST['stage'] ) ? wp_unslash( $_POST['stage'] ) : '',
			'title'            => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '',
			'estimated_value'  => isset( $_POST['estimated_value'] ) ? wp_unslash( $_POST['estimated_value'] ) : 0,
			'currency'         => isset( $_POST['currency'] ) ? wp_unslash( $_POST['currency'] ) : 'USD',
			'assigned_user_id' => isset( $_POST['assigned_user_id'] ) ? wp_unslash( $_POST['assigned_user_id'] ) : 0,
			'position'         => isset( $_POST['position'] ) ? wp_unslash( $_POST['position'] ) : 0,
			'notes'            => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
		);

		$client_selection   = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		$quick_client_name  = isset( $_POST['quick_client_name'] ) ? wp_unslash( $_POST['quick_client_name'] ) : '';
		$quick_client_phone = isset( $_POST['quick_client_phone'] ) ? wp_unslash( $_POST['quick_client_phone'] ) : '';
		$quick_client_email = isset( $_POST['quick_client_email'] ) ? wp_unslash( $_POST['quick_client_email'] ) : '';

		if ( 'new' === $client_selection ) {
			$data['client_id'] = 0;
		}

		if ( $data['client_id'] <= 0 && ( '' !== trim( (string) $quick_client_name ) || '' !== trim( (string) $quick_client_phone ) || '' !== trim( (string) $quick_client_email ) ) ) {
			$quick_client_result = $this->client_service->create_client_from_crm_quick(
				array(
					'name'  => $quick_client_name,
					'phone' => $quick_client_phone,
					'email' => $quick_client_email,
				)
			);

			if ( is_wp_error( $quick_client_result ) ) {
				$this->store_errors( $quick_client_result );
				$this->store_form_state(
					array_merge(
						$data,
						array(
							'quick_client_name'  => $quick_client_name,
							'quick_client_phone' => $quick_client_phone,
							'quick_client_email' => $quick_client_email,
						)
					)
				);
				$this->redirect(
					$is_update
						? array( 'action' => 'edit', 'id' => $id, 'sm_notice' => 'error' )
						: array( 'action' => 'new', 'sm_notice' => 'error' )
				);
			}

			$data['client_id'] = absint( $quick_client_result );
		}

		$result = $is_update
			? $this->service->update_opportunity( $id, $data )
			: $this->service->create_opportunity( $data );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->store_form_state(
				array_merge(
					$data,
					array(
						'quick_client_name'  => $quick_client_name,
						'quick_client_phone' => $quick_client_phone,
						'quick_client_email' => $quick_client_email,
					)
				)
			);
			$this->redirect(
				$is_update
					? array( 'action' => 'edit', 'id' => $id, 'sm_notice' => 'error' )
					: array( 'action' => 'new', 'sm_notice' => 'error' )
			);
		}

		$this->redirect( array( 'sm_notice' => $is_update ? 'updated' : 'created' ) );
	}

	/**
	 * Handle task create/update.
	 *
	 * @param bool $is_update Update mode.
	 * @return void
	 */
	protected function handle_task_save_action( $is_update ) {
		check_admin_referer( 'sm_save_crm_task', 'sm_crm_task_nonce' );

		$opportunity_id = isset( $_POST['crm_pipeline_id'] ) ? absint( wp_unslash( $_POST['crm_pipeline_id'] ) ) : 0;
		$task_id        = isset( $_POST['task_id'] ) ? absint( wp_unslash( $_POST['task_id'] ) ) : 0;
		$data           = array(
			'title'            => isset( $_POST['task_title'] ) ? wp_unslash( $_POST['task_title'] ) : '',
			'task_type'        => isset( $_POST['task_type'] ) ? wp_unslash( $_POST['task_type'] ) : '',
			'assigned_user_id' => isset( $_POST['assigned_user_id'] ) ? wp_unslash( $_POST['assigned_user_id'] ) : 0,
			'due_at'           => isset( $_POST['due_at'] ) ? wp_unslash( $_POST['due_at'] ) : '',
			'status'           => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'pending',
			'notes'            => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
		);

		$result = $is_update
			? $this->service->update_task( $task_id, $data )
			: $this->service->create_task_for_opportunity( $opportunity_id, $data );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect(
				array(
					'action'    => 'view',
					'id'        => $opportunity_id,
					'sm_notice' => 'error',
				)
			);
		}

		$this->redirect(
			array(
				'action'    => 'view',
				'id'        => $opportunity_id,
				'sm_notice' => $is_update ? 'task_updated' : 'task_created',
			)
		);
	}

	/**
	 * Handle complete task action.
	 *
	 * @return void
	 */
	protected function handle_complete_task_action() {
		$opportunity_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$task_id        = isset( $_GET['task_id'] ) ? absint( $_GET['task_id'] ) : 0;

		check_admin_referer( 'sm_complete_crm_task_' . $task_id );

		$result = $this->service->complete_task( $task_id );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect(
				array(
					'action'    => 'view',
					'id'        => $opportunity_id,
					'sm_notice' => 'error',
				)
			);
		}

		$this->redirect(
			array(
				'action'    => 'view',
				'id'        => $opportunity_id,
				'sm_notice' => 'task_completed',
			)
		);
	}

	/**
	 * Handle create process from CRM opportunity.
	 *
	 * @return void
	 */
	protected function handle_create_process_action() {
		$id           = isset( $_POST['crm_pipeline_id'] ) ? absint( wp_unslash( $_POST['crm_pipeline_id'] ) ) : 0;
		$process_type = isset( $_POST['process_type'] ) ? sanitize_key( wp_unslash( $_POST['process_type'] ) ) : '';
		check_admin_referer( 'sm_create_process_from_crm_' . $id, 'sm_create_process_nonce' );

		$result = $this->service->create_process_from_opportunity( $id, $process_type );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect(
				array(
					'action'    => 'view',
					'id'        => $id,
					'sm_notice' => 'error',
				)
			);
		}

		$this->redirect(
			array(
				'action'    => 'view',
				'id'        => $id,
				'sm_notice' => 'process_created',
			)
		);
	}

	/**
	 * Handle link existing process action.
	 *
	 * @return void
	 */
	protected function handle_link_existing_process_action() {
		$id         = isset( $_POST['crm_pipeline_id'] ) ? absint( wp_unslash( $_POST['crm_pipeline_id'] ) ) : 0;
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;

		check_admin_referer( 'sm_link_process_from_crm_' . $id, 'sm_link_process_nonce' );

		$result = $this->service->link_existing_process( $id, $process_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect(
				array(
					'action'    => 'view',
					'id'        => $id,
					'sm_notice' => 'error',
				)
			);
		}

		$this->redirect(
			array(
				'action'    => 'view',
				'id'        => $id,
				'sm_notice' => 'process_linked',
			)
		);
	}

	/**
	 * Handle quick stage update.
	 *
	 * @return void
	 */
	protected function handle_quick_stage_action() {
		$id           = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$stage        = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( $_GET['stage'] ) ) : '';
		$view_mode    = isset( $_GET['view_mode'] ) ? sanitize_key( wp_unslash( $_GET['view_mode'] ) ) : '';
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$filter_stage = isset( $_GET['filter_stage'] ) ? sanitize_key( wp_unslash( $_GET['filter_stage'] ) ) : '';
		$assigned_user_id   = isset( $_GET['assigned_user_id'] ) ? absint( $_GET['assigned_user_id'] ) : 0;
		$requires_attention = isset( $_GET['requires_attention'] ) && '1' === sanitize_key( wp_unslash( $_GET['requires_attention'] ) );
		$overdue            = isset( $_GET['overdue'] ) && '1' === sanitize_key( wp_unslash( $_GET['overdue'] ) );

		check_admin_referer( 'sm_quick_crm_pipeline_stage_' . $id . '_' . $stage );

		$result = $this->service->quick_update_stage( $id, $stage );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$redirect_args = array( 'sm_notice' => 'error' );
			if ( 'kanban' === $view_mode ) {
				$redirect_args['view_mode'] = 'kanban';
				if ( '' !== $search ) {
					$redirect_args['s'] = $search;
				}
				if ( '' !== $filter_stage ) {
					$redirect_args['stage'] = $filter_stage;
				}
				if ( $assigned_user_id > 0 ) {
					$redirect_args['assigned_user_id'] = $assigned_user_id;
				}
				if ( $requires_attention ) {
					$redirect_args['requires_attention'] = '1';
				}
				if ( $overdue ) {
					$redirect_args['overdue'] = '1';
				}
			}
			if ( 'kanban' !== $view_mode ) {
				if ( '' !== $search ) {
					$redirect_args['s'] = $search;
				}
				if ( '' !== $filter_stage ) {
					$redirect_args['stage'] = $filter_stage;
				}
				if ( $assigned_user_id > 0 ) {
					$redirect_args['assigned_user_id'] = $assigned_user_id;
				}
				if ( $requires_attention ) {
					$redirect_args['requires_attention'] = '1';
				}
				if ( $overdue ) {
					$redirect_args['overdue'] = '1';
				}
			}
			$this->redirect( $redirect_args );
		}

		$redirect_args = array(
			'sm_notice'   => 'stage_updated',
			'stage_label' => rawurlencode( $this->humanize_stage( $stage ) ),
		);
		$updated_item = $this->service->get_opportunity( $id );
		if ( is_array( $updated_item ) ) {
			$signals = $this->service->get_opportunity_automation_signals( $updated_item );
			if ( ! empty( $signals['conversion_pending'] ) ) {
				$redirect_args['sm_hint'] = 'conversion_pending';
			} elseif ( ! empty( $signals['suggest_follow_up'] ) ) {
				$redirect_args['sm_hint'] = 'follow_up_missing';
			}
		}
		if ( 'kanban' === $view_mode ) {
			$redirect_args['view_mode'] = 'kanban';
			if ( '' !== $search ) {
				$redirect_args['s'] = $search;
			}
			if ( '' !== $filter_stage ) {
				$redirect_args['stage'] = $filter_stage;
			}
			if ( $assigned_user_id > 0 ) {
				$redirect_args['assigned_user_id'] = $assigned_user_id;
			}
			if ( $requires_attention ) {
				$redirect_args['requires_attention'] = '1';
			}
			if ( $overdue ) {
				$redirect_args['overdue'] = '1';
			}
		}
		if ( 'kanban' !== $view_mode ) {
			if ( '' !== $search ) {
				$redirect_args['s'] = $search;
			}
			if ( '' !== $filter_stage ) {
				$redirect_args['stage'] = $filter_stage;
			}
			if ( $assigned_user_id > 0 ) {
				$redirect_args['assigned_user_id'] = $assigned_user_id;
			}
			if ( $requires_attention ) {
				$redirect_args['requires_attention'] = '1';
			}
			if ( $overdue ) {
				$redirect_args['overdue'] = '1';
			}
		}
		$this->redirect( $redirect_args );
	}

	/**
	 * Handle delete.
	 *
	 * @return void
	 */
	protected function handle_delete_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'sm_delete_crm_pipeline_' . $id );

		$result = $this->service->delete_opportunity( $id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'sm_notice' => 'deleted' ) );
	}

	/**
	 * Ensure permissions.
	 *
	 * @return void
	 */
	protected function ensure_permissions() {
		if ( ! current_user_can( 'sm_manage_clients' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage CRM pipeline opportunities.', 'super-mechanic' ) );
		}
	}

	/**
	 * Render notice.
	 *
	 * @param string $message Message.
	 * @param string $type    Type.
	 * @return void
	 */
	protected function render_notice( $message, $type ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible sm-notice-card"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Render stage badge cell with quick actions.
	 *
	 * @param array<string, mixed> $item Row.
	 * @param array<string, mixed> $signals Automation signals.
	 * @return string
	 */
	protected function render_stage_cell( array $item, array $signals = array() ) {
		$current_stage = isset( $item['stage'] ) ? (string) $item['stage'] : 'new_lead';
		$badge         = '<span class="sm-badge sm-badge-' . esc_attr( $this->get_stage_tone( $current_stage ) ) . '">' . esc_html( $this->humanize_stage( $current_stage ) ) . '</span>';
		if ( ! empty( $signals['overdue_task_count'] ) ) {
			$badge .= ' <span class="sm-badge sm-badge-danger">' . esc_html__( 'Critical', 'super-mechanic' ) . '</span>';
		} elseif ( ! empty( $signals['requires_attention'] ) ) {
			$badge .= ' <span class="sm-badge sm-badge-warning">' . esc_html__( 'Attention', 'super-mechanic' ) . '</span>';
		}
		$actions       = $this->build_quick_stage_actions( absint( $item['id'] ), $current_stage, $this->get_active_filter_args() );

		if ( empty( $actions ) ) {
			return $badge;
		}

		return $badge . '<div class="row-actions">' . implode( ' | ', $actions ) . '</div>';
	}

	/**
	 * Build quick stage actions.
	 *
	 * @param int    $id Opportunity ID.
	 * @param string $current_stage Current stage.
	 * @return array<int, string>
	 */
	protected function build_quick_stage_actions( $id, $current_stage, $extra_args = array() ) {
		$actions = array();

		foreach ( $this->service->get_stage_catalog() as $stage ) {
			if ( $stage === $current_stage ) {
				continue;
			}

			$url = wp_nonce_url(
				$this->get_page_url(
				array_merge(
					$extra_args,
					array(
						'action' => 'quick_stage',
						'id'     => absint( $id ),
						'stage'  => $stage,
					)
				)
			),
				'sm_quick_crm_pipeline_stage_' . absint( $id ) . '_' . $stage
			);

			$actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $this->humanize_stage( $stage ) ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Resolve badge tone for stage.
	 *
	 * @param string $stage Stage.
	 * @return string
	 */
	protected function get_stage_tone( $stage ) {
		$map = array(
			'new_lead'    => 'neutral',
			'contacted'   => 'primary',
			'quoted'      => 'warning',
			'negotiating' => 'warning',
			'won'         => 'success',
			'lost'        => 'danger',
		);

		return isset( $map[ $stage ] ) ? $map[ $stage ] : 'neutral';
	}

	/**
	 * Render detail row.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	protected function render_detail_row( $label, $value ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}

	/**
	 * Render compact automation badges for list/kanban.
	 *
	 * @param array<string,mixed> $signals Signal payload.
	 * @param string              $context Render context.
	 * @return string
	 */
	protected function render_automation_badges( array $signals, $context = 'default' ) {
		$badges = array();
		$has_overdue = ! empty( $signals['overdue_task_count'] );

		if ( $has_overdue && 'list' !== $context ) {
			$badges[] = '<span class="sm-badge sm-badge-danger">' . esc_html( sprintf( __( 'Overdue: %d', 'super-mechanic' ), absint( $signals['overdue_task_count'] ) ) ) . '</span>';
		}
		if ( ! $has_overdue && ! empty( $signals['suggest_follow_up'] ) ) {
			$badges[] = '<span class="sm-badge sm-badge-warning">' . esc_html__( 'Follow-up suggested', 'super-mechanic' ) . '</span>';
		}
		if ( ! $has_overdue && ! empty( $signals['conversion_pending'] ) ) {
			$badges[] = '<span class="sm-badge sm-badge-warning">' . esc_html__( 'Conversion pending', 'super-mechanic' ) . '</span>';
		}
		if ( ! $has_overdue && ! empty( $signals['inactive_attention'] ) ) {
			$badges[] = '<span class="sm-badge sm-badge-warning">' . esc_html__( 'Inactive', 'super-mechanic' ) . '</span>';
		}

		if ( empty( $badges ) ) {
			return '';
		}

		return '<div class="sm-crm-automation-badges">' . implode( ' ', $badges ) . '</div>';
	}

	/**
	 * Resolve row/card priority CSS class from automation signals.
	 *
	 * @param array<string,mixed> $signals Signal payload.
	 * @return string
	 */
	protected function get_priority_row_class( array $signals ) {
		if ( ! empty( $signals['overdue_task_count'] ) ) {
			return 'sm-crm-priority-critical';
		}

		if ( ! empty( $signals['requires_attention'] ) ) {
			return 'sm-crm-priority-attention';
		}

		return 'sm-crm-priority-normal';
	}

	/**
	 * Get active filter args for action links.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_active_filter_args() {
		$args = array();

		if ( isset( $_GET['s'] ) ) {
			$args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}
		if ( isset( $_GET['stage'] ) ) {
			$args['filter_stage'] = sanitize_key( wp_unslash( $_GET['stage'] ) );
		}
		if ( isset( $_GET['view_mode'] ) ) {
			$args['view_mode'] = $this->get_current_view_mode();
		}
		if ( isset( $_GET['assigned_user_id'] ) ) {
			$assigned_user_id = absint( $_GET['assigned_user_id'] );
			if ( $assigned_user_id > 0 ) {
				$args['assigned_user_id'] = $assigned_user_id;
			}
		}
		if ( isset( $_GET['requires_attention'] ) && '1' === sanitize_key( wp_unslash( $_GET['requires_attention'] ) ) ) {
			$args['requires_attention'] = '1';
		}
		if ( isset( $_GET['overdue'] ) && '1' === sanitize_key( wp_unslash( $_GET['overdue'] ) ) ) {
			$args['overdue'] = '1';
		}

		return $args;
	}

	/**
	 * Get users for assigned-user filter.
	 *
	 * @return array<int,\WP_User>
	 */
	protected function get_assignable_users() {
		$users = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name' ),
			)
		);

		return is_array( $users ) ? $users : array();
	}

	/**
	 * Resolve user display name.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	protected function get_user_display_name( $user_id ) {
		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return __( 'Unassigned', 'super-mechanic' );
		}

		$user = get_userdata( $user_id );

		if ( ! $user || empty( $user->display_name ) ) {
			return __( 'Unknown user', 'super-mechanic' );
		}

		return (string) $user->display_name;
	}

	/**
	 * Redirect to page URL.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return void
	 */
	protected function redirect( $args = array() ) {
		wp_safe_redirect( $this->get_page_url( $args ) );
		exit;
	}

	/**
	 * Get page URL.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	protected function get_page_url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'super-mechanic-crm-pipeline',
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Check current page.
	 *
	 * @return bool
	 */
	protected function is_crm_screen() {
		return isset( $_GET['page'] ) && 'super-mechanic-crm-pipeline' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Get error transient key.
	 *
	 * @return string
	 */
	protected function get_error_transient_key() {
		return 'sm_crm_pipeline_errors_' . get_current_user_id();
	}

	/**
	 * Get form transient key.
	 *
	 * @return string
	 */
	protected function get_form_transient_key() {
		return 'sm_crm_pipeline_form_' . get_current_user_id();
	}

	/**
	 * Store error messages.
	 *
	 * @param WP_Error $error Error.
	 * @return void
	 */
	protected function store_errors( WP_Error $error ) {
		set_transient( $this->get_error_transient_key(), $error->get_error_messages(), MINUTE_IN_SECONDS );
	}

	/**
	 * Store form state.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @return void
	 */
	protected function store_form_state( array $data ) {
		set_transient( $this->get_form_transient_key(), $data, MINUTE_IN_SECONDS );
	}

	/**
	 * Humanize stage key.
	 *
	 * @param string $stage Stage key.
	 * @return string
	 */
	protected function humanize_stage( $stage ) {
		return ucwords( str_replace( '_', ' ', (string) $stage ) );
	}

	/**
	 * Humanize generic key.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/**
	 * Convert mysql datetime to datetime-local value.
	 *
	 * @param string $value Raw datetime.
	 * @return string
	 */
	protected function to_datetime_local( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d\TH:i', $timestamp );
	}

	/**
	 * Get current view mode.
	 *
	 * @return string
	 */
	protected function get_current_view_mode() {
		$view_mode = isset( $_GET['view_mode'] ) ? sanitize_key( wp_unslash( $_GET['view_mode'] ) ) : 'list';

		return 'kanban' === $view_mode ? 'kanban' : 'list';
	}
}
