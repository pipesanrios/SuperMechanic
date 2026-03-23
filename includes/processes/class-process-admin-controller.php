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
				wp_die( esc_html__( 'El proceso solicitado no existe.', 'super-mechanic' ) );
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
			$this->render_notice( __( 'Proceso creado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'updated' === $notice ) {
			$this->render_notice( __( 'Proceso actualizado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'deleted' === $notice ) {
			$this->render_notice( __( 'Proceso eliminado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'bulk_deleted' === $notice ) {
			$this->render_notice( sprintf( __( '%d procesos eliminados correctamente.', 'super-mechanic' ), $count ), 'success' );
		}

		if ( 'comment_created' === $notice ) {
			$this->render_notice( __( 'Comentario registrado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'comment_archived' === $notice ) {
			$this->render_notice( __( 'Comentario archivado correctamente.', 'super-mechanic' ), 'success' );
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

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Procesos', 'super-mechanic' ) . '</h1>';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ) . '" class="page-title-action">' . esc_html__( 'Anadir nuevo', 'super-mechanic' ) . '</a>';
		echo '<hr class="wp-header-end" />';
		$this->render_filter_form( $list_table );
		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="super-mechanic-processes" />';
		wp_nonce_field( 'sm_bulk_delete_processes', 'sm_bulk_delete_nonce' );
		$list_table->display();
		echo '</form>';
		echo '</div>';
	}

	protected function render_form_page( $process = array(), $is_edit = false ) {
		$defaults = array(
			'id'             => 0,
			'vehicle_id'     => isset( $_GET['vehicle_id'] ) ? absint( wp_unslash( $_GET['vehicle_id'] ) ) : 0,
			'client_id'      => 0,
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
		$title          = $is_edit ? __( 'Editar proceso', 'super-mechanic' ) : __( 'Nuevo proceso', 'super-mechanic' );
		$vehicles       = $this->service->get_vehicle_options();
		$clients        = $this->service->get_client_options();
		$process_types  = $this->service->get_process_type_options();
		$status_options = $this->service->get_status_options();
		$current_tab    = $this->get_current_tab( $process, $is_edit );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $title ) . '</h1>';

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

		echo '<form method="post" action="' . esc_url( $this->get_page_url( $is_edit ? array( 'action' => 'edit', 'id' => absint( $process['id'] ) ) : array( 'action' => 'new' ) ) ) . '">';
		wp_nonce_field( 'sm_save_process', 'sm_process_nonce' );
		echo '<input type="hidden" name="sm_process_operation" value="' . esc_attr( $is_edit ? 'update' : 'create' ) . '" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		echo '<table class="form-table" role="presentation">';
		$this->render_vehicle_select_field( $process['vehicle_id'], $vehicles );
		$this->render_client_select_field( $process['client_id'], $clients );
		$this->render_select_field( 'process_type', __( 'Tipo de proceso', 'super-mechanic' ), $process['process_type'], $process_types );
		$this->render_select_field( 'status', __( 'Estado', 'super-mechanic' ), $process['status'], $status_options );
		$this->render_text_field( 'title', __( 'Titulo', 'super-mechanic' ), $process['title'], true );
		$this->render_textarea_field( 'internal_notes', __( 'Notas internas', 'super-mechanic' ), $process['internal_notes'] );
		$this->render_datetime_field( 'opened_at', __( 'Fecha de apertura', 'super-mechanic' ), $process['opened_at'] );
		$this->render_datetime_field( 'due_date', __( 'Fecha objetivo', 'super-mechanic' ), $process['due_date'] );
		$this->render_datetime_field( 'completed_at', __( 'Fecha de finalizacion', 'super-mechanic' ), $process['completed_at'] );
		echo '</table>';
		submit_button( $is_edit ? __( 'Actualizar proceso', 'super-mechanic' ) : __( 'Crear proceso', 'super-mechanic' ) );
		echo '</form>';
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
		}

		if ( isset( $_GET['action'] ) && 'delete' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$this->handle_delete_action();
		}

		if ( isset( $_GET['comment_action'] ) && 'archive' === sanitize_key( wp_unslash( $_GET['comment_action'] ) ) ) {
			$this->handle_comment_archive_action();
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

	protected function handle_bulk_delete_action() {
		check_admin_referer( 'sm_bulk_delete_processes', 'sm_bulk_delete_nonce' );

		$ids = isset( $_POST['process_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['process_ids'] ) ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			$this->store_errors( new WP_Error( 'sm_no_processes_selected', __( 'Selecciona al menos un proceso para eliminar.', 'super-mechanic' ) ) );
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
			$this->store_errors( new WP_Error( 'sm_invalid_process_comment', __( 'El proceso asociado al comentario no existe.', 'super-mechanic' ) ) );
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

	protected function handle_comment_archive_action() {
		$comment_id = isset( $_GET['comment_id'] ) ? absint( wp_unslash( $_GET['comment_id'] ) ) : 0;
		$process_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		check_admin_referer( 'sm_archive_process_comment_' . $comment_id );

		$result = $this->comment_service->delete_comment( $comment_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'sm_notice' => 'comment_archived' ) );
	}

	protected function render_filter_form( Process_List_Table $list_table ) {
		$process_type  = isset( $_GET['filter_process_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_process_type'] ) ) : '';
		$status        = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : '';
		$process_types = $this->service->get_process_type_options();
		$statuses      = $this->service->get_status_options();

		echo '<form method="get" style="margin: 0 0 12px;">';
		echo '<input type="hidden" name="page" value="super-mechanic-processes" />';
		echo '<select name="filter_process_type">';
		echo '<option value="">' . esc_html__( 'Todos los tipos', 'super-mechanic' ) . '</option>';
		foreach ( $process_types as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $process_type, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo '<select name="filter_status">';
		echo '<option value="">' . esc_html__( 'Todos los estados', 'super-mechanic' ) . '</option>';
		foreach ( $statuses as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Filtrar', 'super-mechanic' ), '', 'filter_action', false );
		$list_table->search_box( __( 'Buscar procesos', 'super-mechanic' ), 'sm-processes' );
		echo '</form>';
	}

	protected function render_tabs( $process_id, $process_type, $current_tab ) {
		$tabs = array(
			'general'       => __( 'General', 'super-mechanic' ),
			'invoice'       => __( 'Facturas', 'super-mechanic' ),
			'documents'     => __( 'Documentos / Adjuntos', 'super-mechanic' ),
			'communication' => __( 'Comunicacion', 'super-mechanic' ),
		);

		if ( 'maintenance' === $process_type ) {
			$tabs['maintenance'] = __( 'Maintenance', 'super-mechanic' );
			$tabs['quote']       = __( 'Cotizacion', 'super-mechanic' );
		}

		if ( 'pre_delivery' === $process_type ) {
			$tabs['pre-delivery'] = __( 'Pre-Delivery', 'super-mechanic' );
		}

		if ( 'paperwork' === $process_type ) {
			$tabs['paperwork'] = __( 'Paperwork', 'super-mechanic' );
		}

		echo '<nav class="nav-tab-wrapper" style="margin-bottom:16px;">';
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

		echo '<h2>' . esc_html__( 'Comentarios y mensajes', 'super-mechanic' ) . '</h2>';
		echo '<form method="post" style="margin:16px 0 24px;">';
		wp_nonce_field( 'sm_process_comment_action', 'sm_process_comment_nonce' );
		echo '<input type="hidden" name="sm_process_comment_operation" value="create" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="sm_process_comment_type">' . esc_html__( 'Tipo', 'super-mechanic' ) . '</label></th><td><select id="sm_process_comment_type" name="comment_type"><option value="internal_note">' . esc_html__( 'Nota interna', 'super-mechanic' ) . '</option><option value="staff_reply">' . esc_html__( 'Respuesta staff', 'super-mechanic' ) . '</option><option value="system_note">' . esc_html__( 'Nota sistema', 'super-mechanic' ) . '</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="sm_process_comment_content">' . esc_html__( 'Contenido', 'super-mechanic' ) . '</label></th><td><textarea id="sm_process_comment_content" name="content" rows="5" class="large-text" required></textarea></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Visibilidad', 'super-mechanic' ) . '</th><td><label><input type="checkbox" name="is_internal" value="1" checked /> ' . esc_html__( 'Solo uso interno', 'super-mechanic' ) . '</label><br /><label><input type="checkbox" name="is_client_visible" value="1" /> ' . esc_html__( 'Visible para el cliente', 'super-mechanic' ) . '</label></td></tr>';
		echo '</table>';
		submit_button( __( 'Guardar comentario', 'super-mechanic' ) );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Contenido', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Visibilidad', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $comments ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay comentarios para este proceso.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $comments as $comment ) {
				$archive_url = wp_nonce_url( add_query_arg( array( 'page' => 'super-mechanic-processes', 'action' => 'edit', 'id' => $process_id, 'tab' => 'communication', 'comment_action' => 'archive', 'comment_id' => absint( $comment['id'] ) ), admin_url( 'admin.php' ) ), 'sm_archive_process_comment_' . absint( $comment['id'] ) );
				echo '<tr>';
				echo '<td>' . esc_html( $comment['created_at'] ) . '</td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $comment['comment_type'] ) ) ) . '</td>';
				echo '<td>' . esc_html( $comment['content'] ) . '</td>';
				echo '<td>' . esc_html( ! empty( $comment['is_internal'] ) ? __( 'Interno', 'super-mechanic' ) : __( 'Operativo', 'super-mechanic' ) ) . ' / ' . esc_html( ! empty( $comment['is_client_visible'] ) ? __( 'Visible cliente', 'super-mechanic' ) : __( 'Oculto cliente', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $comment['status'] ) . '</td>';
				echo '<td><a href="' . esc_url( $archive_url ) . '">' . esc_html__( 'Archivar', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Feed de notificaciones', 'super-mechanic' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Titulo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Mensaje', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Destinatario', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $notifications ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay notificaciones para este proceso.', 'super-mechanic' ) . '</td></tr>';
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
	}

	protected function render_notice( $message, $type ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	protected function render_vehicle_select_field( $selected_vehicle_id, $vehicles ) {
		echo '<tr>';
		echo '<th scope="row"><label for="vehicle_id">' . esc_html__( 'Vehiculo', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="vehicle_id" id="vehicle_id" required>';
		echo '<option value="0">' . esc_html__( 'Selecciona un vehiculo', 'super-mechanic' ) . '</option>';

		foreach ( $vehicles as $vehicle ) {
			$label = $this->get_vehicle_label( $vehicle );
			echo '<option value="' . esc_attr( absint( $vehicle['id'] ) ) . '" ' . selected( absint( $selected_vehicle_id ), absint( $vehicle['id'] ), false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select></td>';
		echo '</tr>';
	}

	protected function render_client_select_field( $selected_client_id, $clients ) {
		echo '<tr>';
		echo '<th scope="row"><label for="client_id">' . esc_html__( 'Cliente', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="client_id" id="client_id">';
		echo '<option value="0">' . esc_html__( 'Sin asignar', 'super-mechanic' ) . '</option>';

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

		return '' !== trim( $label ) ? trim( $label ) : __( 'Vehiculo sin identificar', 'super-mechanic' );
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
			wp_die( esc_html__( 'No tienes permisos suficientes para gestionar procesos.', 'super-mechanic' ) );
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
