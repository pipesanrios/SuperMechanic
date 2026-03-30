<?php
/**
 * Process admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Processes;

use Super_Mechanic\Attachments\Attachment_Admin_Controller;
use Super_Mechanic\Communication\Comment_Service;
use Super_Mechanic\Communication\Notification_Service;
use Super_Mechanic\Invoices\Invoice_Admin_Controller;
use Super_Mechanic\Maintenance\Maintenance_Admin_Controller;
use Super_Mechanic\Paperwork\Paperwork_Admin_Controller;
use Super_Mechanic\Pre_Delivery\Pre_Delivery_Admin_Controller;
use Super_Mechanic\Quotes\Quote_Admin_Controller;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles process admin flows.
 */
class Process_Admin_Controller {
	protected $service;
	protected $maintenance_admin_controller;
	protected $pre_delivery_admin_controller;
	protected $paperwork_admin_controller;
	protected $quote_admin_controller;
	protected $invoice_admin_controller;
	protected $attachment_admin_controller;
	protected $comment_service;
	protected $notification_service;

	public function __construct( Process_Service $service = null, Maintenance_Admin_Controller $maintenance_admin_controller = null, Pre_Delivery_Admin_Controller $pre_delivery_admin_controller = null, Paperwork_Admin_Controller $paperwork_admin_controller = null, Quote_Admin_Controller $quote_admin_controller = null, Invoice_Admin_Controller $invoice_admin_controller = null, Attachment_Admin_Controller $attachment_admin_controller = null, Comment_Service $comment_service = null, Notification_Service $notification_service = null ) {
		$this->service                       = $service ? $service : new Process_Service();
		$this->maintenance_admin_controller  = $maintenance_admin_controller ? $maintenance_admin_controller : new Maintenance_Admin_Controller();
		$this->pre_delivery_admin_controller = $pre_delivery_admin_controller ? $pre_delivery_admin_controller : new Pre_Delivery_Admin_Controller();
		$this->paperwork_admin_controller    = $paperwork_admin_controller ? $paperwork_admin_controller : new Paperwork_Admin_Controller();
		$this->quote_admin_controller        = $quote_admin_controller ? $quote_admin_controller : new Quote_Admin_Controller();
		$this->invoice_admin_controller      = $invoice_admin_controller ? $invoice_admin_controller : new Invoice_Admin_Controller();
		$this->attachment_admin_controller   = $attachment_admin_controller ? $attachment_admin_controller : new Attachment_Admin_Controller();
		$this->comment_service               = $comment_service ? $comment_service : new Comment_Service();
		$this->notification_service          = $notification_service ? $notification_service : new Notification_Service();
	}

	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_handle_actions' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	public function maybe_handle_actions() {
		if ( ! $this->is_processes_screen() ) {
			return;
		}

		$this->ensure_permissions();
		$this->handle_actions();
	}

	public function render_page() {
		$this->ensure_permissions();

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( 'new' === $action ) {
			$this->render_form_page();
			return;
		}

		if ( 'edit' === $action ) {
			$process = $this->service->get_process( $id );

			if ( empty( $process ) ) {
				wp_die( esc_html__( 'The requested process does not exist.', 'super-mechanic' ) );
			}

			$this->render_form_page( $process, true );
			return;
		}

		$this->render_list_page();
	}

	public function render_admin_notices() {
		if ( ! $this->is_processes_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$count  = isset( $_GET['deleted_count'] ) ? absint( $_GET['deleted_count'] ) : 0;

		if ( 'created' === $notice ) {
			$this->render_notice( __( 'Process created successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'updated' === $notice ) {
			$this->render_notice( __( 'Process updated successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'status_updated' === $notice ) {
			$next_status = isset( $_GET['sm_status'] ) ? sanitize_key( wp_unslash( $_GET['sm_status'] ) ) : '';
			if ( '' !== $next_status ) {
				$this->render_notice(
					sprintf(
						/* translators: %s: process status label. */
						__( 'Process status updated to %s.', 'super-mechanic' ),
						ucwords( str_replace( '_', ' ', $next_status ) )
					),
					'success'
				);
			} else {
				$this->render_notice( __( 'Process status updated successfully.', 'super-mechanic' ), 'success' );
			}
		}

		if ( 'deleted' === $notice ) {
			$this->render_notice( __( 'Process deleted successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'bulk_deleted' === $notice ) {
			$this->render_notice( sprintf( __( '%d processes deleted successfully.', 'super-mechanic' ), $count ), 'success' );
		}

		if ( 'comment_created' === $notice ) {
			$this->render_notice( __( 'Comment saved successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'comment_updated' === $notice ) {
			$this->render_notice( __( 'Comment updated successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'comment_deleted' === $notice ) {
			$this->render_notice( __( 'Comment deleted successfully.', 'super-mechanic' ), 'success' );
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

	protected function render_list_page() {
		$list_table = new Process_List_Table( $this->service );
		$list_table->prepare_items();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Processes', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Manage the operational hub with clearer filters, statuses, and actions without changing domain logic.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ) . '" class="button button-primary">' . esc_html__( 'Create process', 'super-mechanic' ) . '</a>';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'filter_process_type' => 'maintenance' ) ) ) . '" class="button button-secondary">' . esc_html__( 'Open maintenance', 'super-mechanic' ) . '</a>';
		echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'super-mechanic-financial-invoices' ), admin_url( 'admin.php' ) ) ) . '" class="button button-secondary">' . esc_html__( 'Invoices center', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';
		$this->render_filter_form( $list_table );
		echo '<div class="sm-card sm-section">';
		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="super-mechanic-processes" />';
		wp_nonce_field( 'sm_bulk_delete_processes', 'sm_bulk_delete_nonce' );
		echo '<div class="sm-table-wrap sm-list-table-wrap">';
		$list_table->display();
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	protected function render_form_page( $process = array(), $is_edit = false ) {
		$defaults = array(
			'id'             => 0,
			'vehicle_id'     => isset( $_GET['vehicle_id'] ) ? absint( wp_unslash( $_GET['vehicle_id'] ) ) : 0,
			'client_id'      => isset( $_GET['client_id'] ) ? absint( wp_unslash( $_GET['client_id'] ) ) : 0,
			'process_type'   => 'maintenance',
			'status'         => 'draft',
			'title'          => '',
			'internal_notes' => '',
			'opened_at'      => '',
			'due_date'       => '',
			'completed_at'   => '',
		);

		if ( ! $is_edit && ! empty( $defaults['vehicle_id'] ) ) {
			$defaults['client_id'] = $this->service->get_default_client_id_for_vehicle( $defaults['vehicle_id'] );
		}

		$stored = get_transient( $this->get_form_transient_key() );
		if ( is_array( $stored ) ) {
			$process = array_merge( $process, $stored );
			delete_transient( $this->get_form_transient_key() );
		}

		$process        = wp_parse_args( $process, $defaults );

		if ( $is_edit ) {
			if ( isset( $_GET['client_id'] ) ) {
				$process['client_id'] = absint( wp_unslash( $_GET['client_id'] ) );
			}
			if ( isset( $_GET['vehicle_id'] ) ) {
				$process['vehicle_id'] = absint( wp_unslash( $_GET['vehicle_id'] ) );
			}
		}

		$title          = $is_edit ? __( 'Edit process', 'super-mechanic' ) : __( 'New process', 'super-mechanic' );
		$vehicles       = $this->service->get_vehicle_options();
		$clients        = $this->service->get_client_options();
		$relation_map   = $this->build_process_relation_map( $vehicles, $clients );
		$process_types  = $this->service->get_process_type_options();
		$status_options = $this->service->get_status_options();
		$current_tab    = $this->get_current_tab( $process, $is_edit );

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Keep the operational process flow while improving visual clarity, navigation, and status readability.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url() ) . '" class="button button-secondary">' . esc_html__( 'Back to list', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';

		if ( $is_edit ) {
			$this->render_tabs( absint( $process['id'] ), $process['process_type'], $current_tab );
		}

		if ( $is_edit && 'maintenance' === $process['process_type'] && 'maintenance' === $current_tab ) {
			$this->maintenance_admin_controller->render_process_panel( $process );
			echo '</div>';
			return;
		}

		if ( $is_edit && 'quote' === $current_tab ) {
			$this->quote_admin_controller->render_process_panel( $process );
			echo '</div>';
			return;
		}

		if ( $is_edit && 'invoice' === $current_tab ) {
			$this->invoice_admin_controller->render_process_panel( $process );
			echo '</div>';
			return;
		}

		if ( $is_edit && 'documents' === $current_tab ) {
			$this->attachment_admin_controller->render_process_panel( $process );
			echo '</div>';
			return;
		}

		if ( $is_edit && 'communication' === $current_tab ) {
			$this->render_communication_panel( $process );
			echo '</div>';
			return;
		}

		if ( $is_edit && 'pre_delivery' === $process['process_type'] && 'pre-delivery' === $current_tab ) {
			$this->pre_delivery_admin_controller->render_process_panel( $process );
			echo '</div>';
			return;
		}

		if ( $is_edit && 'paperwork' === $process['process_type'] && 'paperwork' === $current_tab ) {
			$this->paperwork_admin_controller->render_process_panel( $process );
			echo '</div>';
			return;
		}

		echo '<div class="sm-card sm-form-card sm-section">';
		echo '<form method="post" class="sm-process-form" data-sm-process-relations="' . esc_attr( wp_json_encode( $relation_map ) ) . '" action="' . esc_url( $this->get_page_url( $is_edit ? array( 'action' => 'edit', 'id' => absint( $process['id'] ) ) : array( 'action' => 'new' ) ) ) . '">';
		wp_nonce_field( 'sm_save_process', 'sm_process_nonce' );
		echo '<input type="hidden" name="sm_process_operation" value="' . esc_attr( $is_edit ? 'update' : 'create' ) . '" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		echo '<table class="form-table" role="presentation">';
		$this->render_vehicle_select_field( $process['vehicle_id'], $vehicles );
		$this->render_client_select_field( $process['client_id'], $clients );
		$this->render_relation_context_row( $process, $relation_map );
		$this->render_quick_add_context_row( $process, $is_edit );
		$this->render_select_field( 'process_type', __( 'Process type', 'super-mechanic' ), $process['process_type'], $process_types );
		$this->render_select_field( 'status', __( 'Status', 'super-mechanic' ), $process['status'], $status_options );
		$this->render_text_field( 'title', __( 'Title', 'super-mechanic' ), $process['title'], true );
		$this->render_textarea_field( 'internal_notes', __( 'Internal notes', 'super-mechanic' ), $process['internal_notes'] );
		$this->render_datetime_field( 'opened_at', __( 'Open date', 'super-mechanic' ), $process['opened_at'] );
		$this->render_datetime_field( 'due_date', __( 'Target date', 'super-mechanic' ), $process['due_date'] );
		$this->render_datetime_field( 'completed_at', __( 'Completion date', 'super-mechanic' ), $process['completed_at'] );
		echo '</table>';
		echo '<div class="sm-form-actions">';
		submit_button( $is_edit ? __( 'Update process', 'super-mechanic' ) : __( 'Create process', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	protected function handle_actions() {
		$this->attachment_admin_controller->handle_process_actions();

		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			$operation   = isset( $_POST['sm_process_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_process_operation'] ) ) : '';
			$bulk_action = $this->get_bulk_action();
			$comment_op  = isset( $_POST['sm_process_comment_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_process_comment_operation'] ) ) : '';

			if ( 'create' === $operation || 'update' === $operation ) {
				$this->handle_save_action( 'update' === $operation );
			}

			if ( 'bulk-delete' === $bulk_action ) {
				$this->handle_bulk_delete_action();
			}

			if ( 'create' === $comment_op ) {
				$this->handle_comment_create_action();
			}

			if ( 'update' === $comment_op ) {
				$this->handle_comment_update_action();
			}
		}

		if ( isset( $_GET['action'] ) && 'delete' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$this->handle_delete_action();
		}

		if ( isset( $_GET['action'] ) && 'quick_status' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$this->handle_quick_status_action();
		}

		if ( isset( $_GET['comment_action'] ) && 'delete' === sanitize_key( wp_unslash( $_GET['comment_action'] ) ) ) {
			$this->handle_comment_delete_action();
		}
	}

	protected function handle_save_action( $is_update ) {
		check_admin_referer( 'sm_save_process', 'sm_process_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$data       = array(
			'vehicle_id'     => isset( $_POST['vehicle_id'] ) ? wp_unslash( $_POST['vehicle_id'] ) : 0,
			'client_id'      => isset( $_POST['client_id'] ) ? wp_unslash( $_POST['client_id'] ) : 0,
			'process_type'   => isset( $_POST['process_type'] ) ? wp_unslash( $_POST['process_type'] ) : '',
			'status'         => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : '',
			'title'          => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '',
			'internal_notes' => isset( $_POST['internal_notes'] ) ? wp_unslash( $_POST['internal_notes'] ) : '',
			'opened_at'      => isset( $_POST['opened_at'] ) ? wp_unslash( $_POST['opened_at'] ) : '',
			'due_date'       => isset( $_POST['due_date'] ) ? wp_unslash( $_POST['due_date'] ) : '',
			'completed_at'   => isset( $_POST['completed_at'] ) ? wp_unslash( $_POST['completed_at'] ) : '',
		);

		$result = $is_update ? $this->service->update_process( $process_id, $data ) : $this->service->create_process( $data );

		if ( is_wp_error( $result ) ) {
			$this->store_form_state( $data );
			$this->store_errors( $result );
			$this->redirect( $is_update ? array( 'action' => 'edit', 'id' => $process_id, 'sm_notice' => 'error' ) : array( 'action' => 'new', 'sm_notice' => 'error' ) );
		}

		$this->redirect( $is_update ? array( 'action' => 'edit', 'id' => $process_id, 'sm_notice' => 'updated' ) : array( 'sm_notice' => 'created' ) );
	}

	protected function handle_delete_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'sm_delete_process_' . $id );

		$result = $this->service->delete_process( $id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'sm_notice' => 'deleted' ) );
	}

	/**
	 * Handle quick status update from list table.
	 *
	 * @return void
	 */
	protected function handle_quick_status_action() {
		$process_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$status     = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		check_admin_referer( 'sm_quick_process_status_' . $process_id . '_' . $status );

		$result = $this->service->update_process_status( $process_id, $status );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$this->redirect(
			array(
				'sm_notice' => 'status_updated',
				'sm_status' => $status,
			)
		);
	}

	protected function handle_bulk_delete_action() {
		check_admin_referer( 'sm_bulk_delete_processes', 'sm_bulk_delete_nonce' );

		$ids = isset( $_POST['process_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['process_ids'] ) ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			$this->store_errors( new WP_Error( 'sm_no_processes_selected', __( 'Select at least one process to delete.', 'super-mechanic' ) ) );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			$result = $this->service->delete_process( $id );
			if ( ! is_wp_error( $result ) ) {
				++$deleted;
			}
		}

		$this->redirect( array( 'sm_notice' => 'bulk_deleted', 'deleted_count' => $deleted ) );
	}

	protected function handle_comment_create_action() {
		check_admin_referer( 'sm_process_comment_action', 'sm_process_comment_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$process    = $this->service->get_process( $process_id );

		if ( ! $process ) {
			$this->store_errors( new WP_Error( 'sm_invalid_process_comment', __( 'The process associated with the comment does not exist.', 'super-mechanic' ) ) );
			$this->redirect( array( 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'sm_notice' => 'error' ) );
		}

		$result = $this->comment_service->create_comment(
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'client_id'         => absint( $process['client_id'] ),
				'vehicle_id'        => absint( $process['vehicle_id'] ),
				'comment_type'      => isset( $_POST['comment_type'] ) ? wp_unslash( $_POST['comment_type'] ) : 'internal_note',
				'content'           => isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '',
				'is_internal'       => isset( $_POST['is_internal'] ) ? 1 : 0,
				'is_client_visible' => isset( $_POST['is_client_visible'] ) ? 1 : 0,
				'author_user_id'    => get_current_user_id(),
				'status'            => 'published',
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'sm_notice' => 'comment_created' ) );
	}

	protected function handle_comment_update_action() {
		check_admin_referer( 'sm_process_comment_action', 'sm_process_comment_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$comment_id = isset( $_POST['comment_id'] ) ? absint( wp_unslash( $_POST['comment_id'] ) ) : 0;
		$process    = $this->service->get_process( $process_id );
		$comment    = $this->comment_service->get_comment( $comment_id );

		if ( ! $process || ! $comment || absint( $comment['process_id'] ) !== $process_id ) {
			$this->store_errors( new WP_Error( 'sm_invalid_process_comment', __( 'The comment you are trying to edit does not belong to the current process.', 'super-mechanic' ) ) );
			$this->redirect( array( 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'sm_notice' => 'error' ) );
		}

		$result = $this->comment_service->update_comment(
			$comment_id,
			array(
				'content'           => isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '',
				'comment_type'      => isset( $_POST['comment_type'] ) ? wp_unslash( $_POST['comment_type'] ) : 'internal_note',
				'is_internal'       => isset( $_POST['is_internal'] ) ? 1 : 0,
				'is_client_visible' => isset( $_POST['is_client_visible'] ) ? 1 : 0,
				'status'            => 'published',
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'comment_action' => 'edit', 'comment_id' => $comment_id, 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'sm_notice' => 'comment_updated' ) );
	}

	protected function handle_comment_delete_action() {
		$comment_id = isset( $_GET['comment_id'] ) ? absint( wp_unslash( $_GET['comment_id'] ) ) : 0;
		$process_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		check_admin_referer( 'sm_delete_process_comment_' . $comment_id );

		$result = $this->comment_service->delete_comment( $comment_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'sm_notice' => 'comment_deleted' ) );
	}

	protected function render_filter_form( Process_List_Table $list_table ) {
		$process_type  = isset( $_GET['filter_process_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_process_type'] ) ) : '';
		$status        = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : '';
		$process_types = $this->service->get_process_type_options();
		$statuses      = $this->service->get_status_options();

		echo '<div class="sm-card sm-filter-card sm-section">';
		echo '<form method="get" class="sm-process-filter-form">';
		echo '<input type="hidden" name="page" value="super-mechanic-processes" />';
		echo '<div class="sm-filter-grid">';
		echo '<div class="sm-filter-field"><label for="filter_process_type">' . esc_html__( 'Process type', 'super-mechanic' ) . '</label><select id="filter_process_type" name="filter_process_type">';
		echo '<option value="">' . esc_html__( 'All types', 'super-mechanic' ) . '</option>';
		foreach ( $process_types as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $process_type, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="sm-filter-field"><label for="filter_status">' . esc_html__( 'Status', 'super-mechanic' ) . '</label><select id="filter_status" name="filter_status">';
		echo '<option value="">' . esc_html__( 'All statuses', 'super-mechanic' ) . '</option>';
		foreach ( $statuses as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';
		echo '</div>';
		echo '<div class="sm-form-actions">';
		submit_button( __( 'Filter', 'super-mechanic' ), 'secondary', 'filter_action', false );
		$list_table->search_box( __( 'Search processes', 'super-mechanic' ), 'sm-processes' );
		echo '</div>';
		echo '</form>';
		echo '</div>';
	}

	protected function render_tabs( $process_id, $process_type, $current_tab ) {
		$tabs = array(
			'general'       => __( 'General', 'super-mechanic' ),
			'invoice'       => __( 'Invoices', 'super-mechanic' ),
			'documents'     => __( 'Documents / Attachments', 'super-mechanic' ),
			'communication' => __( 'Communication', 'super-mechanic' ),
		);

		if ( 'maintenance' === $process_type ) {
			$tabs['maintenance'] = __( 'Maintenance', 'super-mechanic' );
			$tabs['quote']       = __( 'Quote', 'super-mechanic' );
		}

		if ( 'pre_delivery' === $process_type ) {
			$tabs['pre-delivery'] = __( 'Pre-Delivery', 'super-mechanic' );
		}

		if ( 'paperwork' === $process_type ) {
			$tabs['paperwork'] = __( 'Paperwork', 'super-mechanic' );
		}

		echo '<nav class="nav-tab-wrapper sm-nav-tab-wrapper sm-section" aria-label="' . esc_attr__( 'Process navigation', 'super-mechanic' ) . '">';
		foreach ( $tabs as $tab => $label ) {
			$class = 'nav-tab';
			if ( $tab === $current_tab ) {
				$class .= ' nav-tab-active';
			}

			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $this->get_page_url( array( 'action' => 'edit', 'id' => absint( $process_id ), 'tab' => $tab ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	protected function get_current_tab( $process, $is_edit ) {
		if ( ! $is_edit ) {
			return 'general';
		}

		$allowed = array( 'general', 'invoice', 'documents', 'communication' );

		if ( 'maintenance' === $process['process_type'] ) {
			$allowed[] = 'maintenance';
			$allowed[] = 'quote';
		}

		if ( 'pre_delivery' === $process['process_type'] ) {
			$allowed[] = 'pre-delivery';
		}

		if ( 'paperwork' === $process['process_type'] ) {
			$allowed[] = 'paperwork';
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		return in_array( $tab, $allowed, true ) ? $tab : 'general';
	}

	protected function render_communication_panel( $process ) {
		$process_id     = absint( $process['id'] );
		$comments       = $this->comment_service->get_process_comments( $process_id, array( 'per_page' => 200, 'orderby' => 'created_at', 'order' => 'DESC' ) );
		$notifications  = $this->notification_service->get_notifications( array( 'process_id' => $process_id, 'per_page' => 100, 'orderby' => 'created_at', 'order' => 'DESC' ) );
		$edit_comment_id = isset( $_GET['comment_id'] ) ? absint( wp_unslash( $_GET['comment_id'] ) ) : 0;
		$edit_mode       = 'edit' === ( isset( $_GET['comment_action'] ) ? sanitize_key( wp_unslash( $_GET['comment_action'] ) ) : '' );
		$edit_comment    = $edit_mode ? $this->comment_service->get_comment( $edit_comment_id ) : null;

		echo '<div class="sm-grid sm-grid-two sm-section">';
		echo '<div class="sm-card sm-form-card">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( $edit_comment ? 'Edit comment' : 'Comments and messages', 'super-mechanic' ) . '</h2></div>';
		echo '<form method="post" class="sm-process-comment-form">';
		wp_nonce_field( 'sm_process_comment_action', 'sm_process_comment_nonce' );
		echo '<input type="hidden" name="sm_process_comment_operation" value="' . esc_attr( $edit_comment ? 'update' : 'create' ) . '" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		echo '<input type="hidden" name="comment_id" value="' . esc_attr( $edit_comment ? absint( $edit_comment['id'] ) : 0 ) . '" />';
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="sm_process_comment_type">' . esc_html__( 'Type', 'super-mechanic' ) . '</label></th><td><select id="sm_process_comment_type" name="comment_type"><option value="internal_note" ' . selected( $edit_comment ? $edit_comment['comment_type'] : 'internal_note', 'internal_note', false ) . '>' . esc_html__( 'Internal note', 'super-mechanic' ) . '</option><option value="staff_reply" ' . selected( $edit_comment ? $edit_comment['comment_type'] : '', 'staff_reply', false ) . '>' . esc_html__( 'Staff reply', 'super-mechanic' ) . '</option><option value="system_note" ' . selected( $edit_comment ? $edit_comment['comment_type'] : '', 'system_note', false ) . '>' . esc_html__( 'System note', 'super-mechanic' ) . '</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="sm_process_comment_content">' . esc_html__( 'Content', 'super-mechanic' ) . '</label></th><td><textarea id="sm_process_comment_content" name="content" rows="5" class="large-text" required>' . esc_textarea( $edit_comment ? $edit_comment['content'] : '' ) . '</textarea></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Visibility', 'super-mechanic' ) . '</th><td><label><input type="checkbox" name="is_internal" value="1" ' . checked( $edit_comment ? ! empty( $edit_comment['is_internal'] ) : true, true, false ) . ' /> ' . esc_html__( 'Internal use only', 'super-mechanic' ) . '</label><br /><label><input type="checkbox" name="is_client_visible" value="1" ' . checked( $edit_comment ? ! empty( $edit_comment['is_client_visible'] ) : false, true, false ) . ' /> ' . esc_html__( 'Visible to client', 'super-mechanic' ) . '</label></td></tr>';
		echo '</table>';
		echo '<div class="sm-form-actions">';
		submit_button( $edit_comment ? __( 'Update comment', 'super-mechanic' ) : __( 'Save comment', 'super-mechanic' ), 'primary', 'submit', false );
		if ( $edit_comment ) {
			echo '<a class="button button-secondary" href="' . esc_url( $this->get_page_url( array( 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication' ) ) ) . '">' . esc_html__( 'Cancel edit', 'super-mechanic' ) . '</a>';
		}
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '<div class="sm-card">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Operational history', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap">';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Content', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Visibility', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Actions', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $comments ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No comments for this process.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $comments as $comment ) {
				$edit_url   = add_query_arg( array( 'page' => 'super-mechanic-processes', 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'comment_action' => 'edit', 'comment_id' => absint( $comment['id'] ) ), admin_url( 'admin.php' ) );
				$delete_url = wp_nonce_url( add_query_arg( array( 'page' => 'super-mechanic-processes', 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'comment_action' => 'delete', 'comment_id' => absint( $comment['id'] ) ), admin_url( 'admin.php' ) ), 'sm_delete_process_comment_' . absint( $comment['id'] ) );
				echo '<tr>';
				echo '<td>' . esc_html( $comment['created_at'] ) . '</td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $comment['comment_type'] ) ) ) . '</td>';
				echo '<td>' . esc_html( $comment['content'] ) . '</td>';
				echo '<td>' . esc_html( ! empty( $comment['is_internal'] ) ? __( 'Internal', 'super-mechanic' ) : __( 'Operational', 'super-mechanic' ) ) . ' / ' . esc_html( ! empty( $comment['is_client_visible'] ) ? __( 'Visible to client', 'super-mechanic' ) : __( 'Hidden from client', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $comment['status'] ) . '</td>';
				echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'super-mechanic' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this comment?', 'super-mechanic' ) ) . '\');">' . esc_html__( 'Delete', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '<div class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Notification feed', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap">';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Title', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Message', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Recipient', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $notifications ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No notifications for this process.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $notifications as $notification ) {
				echo '<tr>';
				echo '<td>' . esc_html( $notification['created_at'] ) . '</td>';
				echo '<td>' . esc_html( $notification['notification_type'] ) . '</td>';
				echo '<td>' . esc_html( $notification['title'] ) . '</td>';
				echo '<td>' . esc_html( $notification['message'] ) . '</td>';
				echo '<td>' . esc_html( $notification['recipient_type'] . ':' . $notification['recipient_id'] ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
		echo '</div>';
	}

	protected function render_notice( $message, $type ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible sm-notice-card"><p>' . esc_html( $message ) . '</p></div>';
	}

	protected function render_vehicle_select_field( $selected_vehicle_id, $vehicles ) {
		echo '<tr>';
		echo '<th scope="row"><label for="vehicle_id">' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="vehicle_id" id="vehicle_id" required>';
		echo '<option value="0">' . esc_html__( 'Select a vehicle', 'super-mechanic' ) . '</option>';

		foreach ( $vehicles as $vehicle ) {
			$label = $this->get_vehicle_label( $vehicle );
			echo '<option value="' . esc_attr( absint( $vehicle['id'] ) ) . '" ' . selected( absint( $selected_vehicle_id ), absint( $vehicle['id'] ), false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select></td>';
		echo '</tr>';
	}

	protected function render_client_select_field( $selected_client_id, $clients ) {
		echo '<tr>';
		echo '<th scope="row"><label for="client_id">' . esc_html__( 'Client', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="client_id" id="client_id">';
		echo '<option value="0">' . esc_html__( 'Unassigned', 'super-mechanic' ) . '</option>';

		foreach ( $clients as $client ) {
			$label = trim( sprintf( '%s %s', isset( $client['first_name'] ) ? $client['first_name'] : '', isset( $client['last_name'] ) ? $client['last_name'] : '' ) );
			if ( '' === $label && ! empty( $client['email'] ) ) {
				$label = (string) $client['email'];
			}

			echo '<option value="' . esc_attr( absint( $client['id'] ) ) . '" ' . selected( absint( $selected_client_id ), absint( $client['id'] ), false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select></td>';
		echo '</tr>';
	}

	protected function render_relation_context_row( $process, $relation_map ) {
		$client_id            = isset( $process['client_id'] ) ? absint( $process['client_id'] ) : 0;
		$vehicle_id           = isset( $process['vehicle_id'] ) ? absint( $process['vehicle_id'] ) : 0;
		$client_label         = isset( $relation_map['client_labels'][ $client_id ] ) ? $relation_map['client_labels'][ $client_id ] : '';
		$vehicle_label        = isset( $relation_map['vehicle_labels'][ $vehicle_id ] ) ? $relation_map['vehicle_labels'][ $vehicle_id ] : '';
		$client_vehicle_count = isset( $relation_map['client_to_vehicles'][ $client_id ] ) && is_array( $relation_map['client_to_vehicles'][ $client_id ] ) ? count( $relation_map['client_to_vehicles'][ $client_id ] ) : 0;
		$active_process       = $vehicle_id > 0 ? $this->service->get_active_vehicle_process( $vehicle_id, isset( $process['id'] ) ? absint( $process['id'] ) : 0 ) : null;
		$messages             = array();

		if ( $vehicle_id > 0 && '' !== $vehicle_label ) {
			$messages[] = sprintf( __( 'Selected vehicle: %s.', 'super-mechanic' ), $vehicle_label );
		}

		if ( $client_id > 0 && '' !== $client_label ) {
			$messages[] = sprintf( _n( 'Linked client: %1$s (%2$d associated vehicle).', 'Linked client: %1$s (%2$d associated vehicles).', $client_vehicle_count, 'super-mechanic' ), $client_label, $client_vehicle_count );
		}

		if ( is_array( $active_process ) && ! empty( $active_process['id'] ) ) {
			$messages[] = sprintf( __( 'The vehicle already has an active process: #%1$d (%2$s / %3$s).', 'super-mechanic' ), absint( $active_process['id'] ), $this->humanize_key( $active_process['process_type'] ), $this->humanize_key( $active_process['status'] ) );
		}

		if ( empty( $messages ) ) {
			$messages[] = __( 'Select client and vehicle to validate the relationship and continue with consistent data.', 'super-mechanic' );
		}

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Operational relationship', 'super-mechanic' ) . '</th>';
		echo '<td><p id="sm-process-relation-hint" class="description">' . esc_html( implode( ' ', $messages ) ) . '</p></td>';
		echo '</tr>';
	}

	/**
	 * Render quick-add links to create missing client/vehicle in context.
	 *
	 * @param array<string, mixed> $process Process payload.
	 * @param bool                 $is_edit Whether editing current process.
	 * @return void
	 */
	protected function render_quick_add_context_row( $process, $is_edit ) {
		$client_url  = add_query_arg(
			array(
				'page' => 'super-mechanic-clients',
				'action' => 'new',
			),
			admin_url( 'admin.php' )
		);
		$vehicle_url = add_query_arg(
			array(
				'page' => 'super-mechanic-vehicles',
				'action' => 'new',
			),
			admin_url( 'admin.php' )
		);

		$return_action = $is_edit ? 'edit' : 'new';
		$return_id     = $is_edit && ! empty( $process['id'] ) ? absint( $process['id'] ) : 0;

		$client_url = add_query_arg(
			array(
				'return_page'      => 'super-mechanic-processes',
				'return_action'    => $return_action,
				'return_process_id'=> $return_id,
				'vehicle_id'       => isset( $process['vehicle_id'] ) ? absint( $process['vehicle_id'] ) : 0,
			),
			$client_url
		);
		$vehicle_url = add_query_arg(
			array(
				'return_page'      => 'super-mechanic-processes',
				'return_action'    => $return_action,
				'return_process_id'=> $return_id,
				'client_id'        => isset( $process['client_id'] ) ? absint( $process['client_id'] ) : 0,
			),
			$vehicle_url
		);

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Quick add', 'super-mechanic' ) . '</th>';
		echo '<td>';
		echo '<a id="sm-quick-add-client" class="button button-secondary" href="' . esc_url( $client_url ) . '">' . esc_html__( 'Add client', 'super-mechanic' ) . '</a> ';
		echo '<a id="sm-quick-add-vehicle" class="button button-secondary" href="' . esc_url( $vehicle_url ) . '">' . esc_html__( 'Add vehicle', 'super-mechanic' ) . '</a>';
		echo '<p class="description">' . esc_html__( 'If a client or vehicle is missing, you can create it here and return to the process flow without opening a new UI.', 'super-mechanic' ) . '</p>';
		echo '</td>';
		echo '</tr>';
	}

	protected function build_process_relation_map( $vehicles, $clients ) {
		$map = array(
			'vehicle_to_client' => array(),
			'client_to_vehicles' => array(),
			'vehicle_labels'    => array(),
			'client_labels'     => array(),
		);

		foreach ( $clients as $client ) {
			$client_id = isset( $client['id'] ) ? absint( $client['id'] ) : 0;
			if ( $client_id <= 0 ) {
				continue;
			}

			$label = trim( sprintf( '%s %s', isset( $client['first_name'] ) ? $client['first_name'] : '', isset( $client['last_name'] ) ? $client['last_name'] : '' ) );
			if ( '' === $label && ! empty( $client['email'] ) ) {
				$label = (string) $client['email'];
			}

			$map['client_labels'][ $client_id ] = $label;
		}

		foreach ( $vehicles as $vehicle ) {
			$vehicle_id = isset( $vehicle['id'] ) ? absint( $vehicle['id'] ) : 0;
			$client_id  = isset( $vehicle['client_id'] ) ? absint( $vehicle['client_id'] ) : 0;

			if ( $vehicle_id <= 0 ) {
				continue;
			}

			$map['vehicle_labels'][ $vehicle_id ] = $this->get_vehicle_label( $vehicle );

			if ( $client_id <= 0 ) {
				continue;
			}

			$map['vehicle_to_client'][ $vehicle_id ] = $client_id;

			if ( ! isset( $map['client_to_vehicles'][ $client_id ] ) ) {
				$map['client_to_vehicles'][ $client_id ] = array();
			}

			$map['client_to_vehicles'][ $client_id ][] = $vehicle_id;
		}

		return $map;
	}

	protected function render_select_field( $name, $label, $selected, $options ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $option_label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';
	}

	protected function render_text_field( $name, $label, $value, $required = false ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="text" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text"' . ( $required ? ' required' : '' ) . ' /></td>';
		echo '</tr>';
	}

	protected function render_textarea_field( $name, $label, $value ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="large-text" rows="6">' . esc_textarea( $value ) . '</textarea></td>';
		echo '</tr>';
	}

	protected function render_datetime_field( $name, $label, $value ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="datetime-local" id="' . esc_attr( $name ) . '" value="' . esc_attr( $this->format_datetime_for_input( $value ) ) . '" class="regular-text" /></td>';
		echo '</tr>';
	}

	protected function get_vehicle_label( $vehicle ) {
		$label = trim( sprintf( '%s %s', isset( $vehicle['brand'] ) ? $vehicle['brand'] : ( isset( $vehicle['make'] ) ? $vehicle['make'] : '' ), isset( $vehicle['model'] ) ? $vehicle['model'] : '' ) );

		if ( ! empty( $vehicle['plate'] ) ) {
			$label .= ' - ' . $vehicle['plate'];
		} elseif ( ! empty( $vehicle['vin'] ) ) {
			$label .= ' - ' . $vehicle['vin'];
		}

		return '' !== trim( $label ) ? trim( $label ) : __( 'Unidentified vehicle', 'super-mechanic' );
	}

	/**
	 * Humanize an internal key for UI labels.
	 *
	 * @param string $value Raw key value.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	protected function format_datetime_for_input( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? '' : gmdate( 'Y-m-d\TH:i', $timestamp );
	}

	protected function ensure_permissions() {
		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage processes.', 'super-mechanic' ) );
		}
	}

	protected function get_bulk_action() {
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( '-1' === $action ) {
			$action = isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '';
		}

		return $action;
	}

	protected function store_form_state( $data ) {
		set_transient( $this->get_form_transient_key(), $data, MINUTE_IN_SECONDS );
	}

	protected function store_errors( WP_Error $error ) {
		set_transient( $this->get_error_transient_key(), $error->get_error_messages(), MINUTE_IN_SECONDS );
	}

	protected function redirect( $args = array() ) {
		wp_safe_redirect( $this->get_page_url( $args ) );
		exit;
	}

	protected function get_page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'super-mechanic-processes' ), $args ), admin_url( 'admin.php' ) );
	}

	protected function is_processes_screen() {
		return isset( $_GET['page'] ) && 'super-mechanic-processes' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	protected function get_error_transient_key() {
		return 'sm_process_errors_' . get_current_user_id();
	}

	protected function get_form_transient_key() {
		return 'sm_process_form_' . get_current_user_id();
	}
}




