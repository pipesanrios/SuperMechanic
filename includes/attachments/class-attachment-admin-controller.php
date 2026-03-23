<?php
/**
 * Attachment admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Attachments;

use Super_Mechanic\Processes\Process_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles admin UI for process attachments.
 */
class Attachment_Admin_Controller {
	protected $attachment_service;
	protected $timeline_service;
	protected $process_service;

	public function __construct( Attachment_Service $attachment_service = null, Process_Timeline_Service $timeline_service = null, Process_Service $process_service = null ) {
		$this->attachment_service = $attachment_service ? $attachment_service : new Attachment_Service();
		$this->timeline_service   = $timeline_service ? $timeline_service : new Process_Timeline_Service();
		$this->process_service    = $process_service ? $process_service : new Process_Service();
	}

	public function register_hooks() {
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	public function handle_process_actions() {
		if ( ! $this->is_processes_screen() || ! current_user_can( 'sm_manage_processes' ) ) {
			return;
		}

		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			$operation = isset( $_POST['sm_attachment_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_attachment_operation'] ) ) : '';

			if ( 'upload' === $operation ) {
				$this->handle_upload_action();
			}

			if ( 'update' === $operation ) {
				$this->handle_update_action();
			}
		}

		$action = isset( $_GET['attachment_action'] ) ? sanitize_key( wp_unslash( $_GET['attachment_action'] ) ) : '';

		if ( 'delete' === $action ) {
			$this->handle_delete_action();
		}

		if ( 'toggle-client-visible' === $action ) {
			$this->handle_toggle_client_visible_action();
		}
	}

	public function render_admin_notices() {
		if ( ! $this->is_processes_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_attachment_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_attachment_notice'] ) ) : '';
		$map    = array(
			'created'            => __( 'Documento adjunto cargado correctamente.', 'super-mechanic' ),
			'updated'            => __( 'Documento adjunto actualizado correctamente.', 'super-mechanic' ),
			'deleted'            => __( 'Documento adjunto eliminado correctamente.', 'super-mechanic' ),
			'visibility_updated' => __( 'Visibilidad del documento actualizada.', 'super-mechanic' ),
		);

		if ( isset( $map[ $notice ] ) ) {
			$this->render_notice( $map[ $notice ], 'success' );
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

	public function render_process_panel( $process ) {
		$process_id  = absint( $process['id'] );
		$attachments = $this->attachment_service->get_process_attachments( $process_id, array( 'per_page' => 200 ) );
		$timeline    = $this->timeline_service->get_process_timeline( $process_id, false );
		$edit_id     = isset( $_GET['attachment_id'] ) ? absint( wp_unslash( $_GET['attachment_id'] ) ) : 0;
		$edit_mode   = 'edit' === ( isset( $_GET['attachment_action'] ) ? sanitize_key( wp_unslash( $_GET['attachment_action'] ) ) : '' );
		$attachment  = $edit_mode ? $this->attachment_service->get_attachment( $edit_id ) : null;

		echo '<h2>' . esc_html__( 'Documentos / Adjuntos', 'super-mechanic' ) . '</h2>';
		$this->render_upload_form( $process );

		if ( $attachment && absint( $attachment['process_id'] ) === $process_id ) {
			$this->render_edit_form( $process, $attachment );
		}

		$this->render_attachment_table( $process, $attachments );
		echo '<hr />';
		echo '<h2>' . esc_html__( 'Timeline del proceso', 'super-mechanic' ) . '</h2>';
		$this->render_timeline( $timeline );
	}

	protected function render_upload_form( $process ) {
		echo '<form method="post" enctype="multipart/form-data" style="margin:16px 0 24px;">';
		wp_nonce_field( 'sm_upload_attachment', 'sm_attachment_nonce' );
		echo '<input type="hidden" name="sm_attachment_operation" value="upload" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="sm_attachment_file">' . esc_html__( 'Archivo', 'super-mechanic' ) . '</label></th><td><input type="file" name="sm_attachment_file" id="sm_attachment_file" required /></td></tr>';
		echo '<tr><th scope="row"><label for="sm_attachment_title">' . esc_html__( 'Título', 'super-mechanic' ) . '</label></th><td><input type="text" class="regular-text" name="title" id="sm_attachment_title" value="" /></td></tr>';
		echo '<tr><th scope="row"><label for="sm_attachment_type">' . esc_html__( 'Tipo', 'super-mechanic' ) . '</label></th><td><input type="text" class="regular-text" name="attachment_type" id="sm_attachment_type" value="document" /></td></tr>';
		echo '<tr><th scope="row"><label for="sm_attachment_description">' . esc_html__( 'Descripción', 'super-mechanic' ) . '</label></th><td><textarea name="description" id="sm_attachment_description" class="large-text" rows="4"></textarea></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Visibilidad', 'super-mechanic' ) . '</th><td>';
		echo '<label><input type="checkbox" name="is_internal" value="1" checked /> ' . esc_html__( 'Solo uso interno', 'super-mechanic' ) . '</label><br />';
		echo '<label><input type="checkbox" name="is_client_visible" value="1" /> ' . esc_html__( 'Visible para el cliente', 'super-mechanic' ) . '</label>';
		echo '</td></tr>';
		echo '</table>';
		submit_button( __( 'Subir documento', 'super-mechanic' ) );
		echo '</form>';
	}

	protected function render_edit_form( $process, $attachment ) {
		echo '<h3>' . esc_html__( 'Editar documento', 'super-mechanic' ) . '</h3>';
		echo '<form method="post" style="margin:16px 0 24px;">';
		wp_nonce_field( 'sm_update_attachment', 'sm_attachment_update_nonce' );
		echo '<input type="hidden" name="sm_attachment_operation" value="update" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		echo '<input type="hidden" name="attachment_id" value="' . esc_attr( absint( $attachment['id'] ) ) . '" />';
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="sm_attachment_edit_title">' . esc_html__( 'Título', 'super-mechanic' ) . '</label></th><td><input type="text" class="regular-text" name="title" id="sm_attachment_edit_title" value="' . esc_attr( $attachment['title'] ) . '" required /></td></tr>';
		echo '<tr><th scope="row"><label for="sm_attachment_edit_type">' . esc_html__( 'Tipo', 'super-mechanic' ) . '</label></th><td><input type="text" class="regular-text" name="attachment_type" id="sm_attachment_edit_type" value="' . esc_attr( $attachment['attachment_type'] ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="sm_attachment_edit_description">' . esc_html__( 'Descripción', 'super-mechanic' ) . '</label></th><td><textarea name="description" id="sm_attachment_edit_description" class="large-text" rows="4">' . esc_textarea( $attachment['description'] ) . '</textarea></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Visibilidad', 'super-mechanic' ) . '</th><td>';
		echo '<label><input type="checkbox" name="is_internal" value="1" ' . checked( ! empty( $attachment['is_internal'] ), true, false ) . ' /> ' . esc_html__( 'Solo uso interno', 'super-mechanic' ) . '</label><br />';
		echo '<label><input type="checkbox" name="is_client_visible" value="1" ' . checked( ! empty( $attachment['is_client_visible'] ), true, false ) . ' /> ' . esc_html__( 'Visible para el cliente', 'super-mechanic' ) . '</label>';
		echo '</td></tr>';
		echo '</table>';
		submit_button( __( 'Guardar cambios del documento', 'super-mechanic' ) );
		echo '</form>';
	}

	protected function render_attachment_table( $process, $attachments ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Título', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Archivo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Visibilidad', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $attachments ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay documentos adjuntos para este proceso.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $attachments as $attachment ) {
				$edit_url   = add_query_arg( array( 'page' => 'super-mechanic-processes', 'action' => 'edit', 'id' => absint( $process['id'] ), 'tab' => 'documents', 'attachment_action' => 'edit', 'attachment_id' => absint( $attachment['id'] ) ), admin_url( 'admin.php' ) );
				$toggle_url = wp_nonce_url( add_query_arg( array( 'page' => 'super-mechanic-processes', 'action' => 'edit', 'id' => absint( $process['id'] ), 'tab' => 'documents', 'attachment_action' => 'toggle-client-visible', 'attachment_id' => absint( $attachment['id'] ) ), admin_url( 'admin.php' ) ), 'sm_toggle_attachment_visibility_' . absint( $attachment['id'] ) );
				$delete_url = wp_nonce_url( add_query_arg( array( 'page' => 'super-mechanic-processes', 'action' => 'edit', 'id' => absint( $process['id'] ), 'tab' => 'documents', 'attachment_action' => 'delete', 'attachment_id' => absint( $attachment['id'] ) ), admin_url( 'admin.php' ) ), 'sm_delete_attachment_' . absint( $attachment['id'] ) );

				echo '<tr>';
				echo '<td>' . esc_html( $attachment['title'] ) . '<br /><small>' . esc_html( $attachment['description'] ) . '</small></td>';
				echo '<td>' . esc_html( $attachment['attachment_type'] ) . '</td>';
				echo '<td><a href="' . esc_url( $attachment['file_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir archivo', 'super-mechanic' ) . '</a><br /><small>' . esc_html( $attachment['mime_type'] ) . '</small></td>';
				echo '<td>' . esc_html( ! empty( $attachment['is_internal'] ) ? __( 'Interno', 'super-mechanic' ) : __( 'Operativo', 'super-mechanic' ) ) . ' / ' . esc_html( ! empty( $attachment['is_client_visible'] ) ? __( 'Visible cliente', 'super-mechanic' ) : __( 'Oculto cliente', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $attachment['created_at'] ) . '</td>';
				echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'super-mechanic' ) . '</a> | <a href="' . esc_url( $toggle_url ) . '">' . esc_html__( 'Cambiar visibilidad', 'super-mechanic' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( '¿Eliminar este documento?', 'super-mechanic' ) ) . '\');">' . esc_html__( 'Eliminar', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	protected function render_timeline( $timeline ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Evento', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Detalle', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $timeline ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No hay eventos para este proceso.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $timeline as $event ) {
				echo '<tr>';
				echo '<td>' . esc_html( $event['event_date'] ) . '</td>';
				echo '<td>' . esc_html( $event['event_label'] ) . '</td>';
				echo '<td>' . esc_html( $event['event_type'] ) . '</td>';
				echo '<td>' . esc_html( wp_json_encode( $event['metadata'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	protected function handle_upload_action() {
		check_admin_referer( 'sm_upload_attachment', 'sm_attachment_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$process    = $this->process_service->get_process( $process_id );

		if ( ! $process ) {
			$this->store_errors( new WP_Error( 'sm_attachment_invalid_process', __( 'El proceso asociado no existe.', 'super-mechanic' ) ) );
			$this->redirect( $process_id, 'error' );
		}

		$upload = isset( $_FILES['sm_attachment_file'] ) ? $this->attachment_service->handle_upload( $_FILES['sm_attachment_file'] ) : new WP_Error( 'sm_attachment_missing_file', __( 'Debes seleccionar un archivo.', 'super-mechanic' ) );

		if ( is_wp_error( $upload ) ) {
			$this->store_errors( $upload );
			$this->redirect( $process_id, 'error' );
		}

		$result = $this->attachment_service->create_attachment(
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'client_id'         => absint( $process['client_id'] ),
				'vehicle_id'        => absint( $process['vehicle_id'] ),
				'attachment_type'   => isset( $_POST['attachment_type'] ) ? wp_unslash( $_POST['attachment_type'] ) : 'document',
				'title'             => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : $upload['title'],
				'description'       => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'file_url'          => $upload['file_url'],
				'file_path'         => $upload['file_path'],
				'mime_type'         => $upload['mime_type'],
				'file_size'         => $upload['file_size'],
				'is_internal'       => isset( $_POST['is_internal'] ) ? 1 : 0,
				'is_client_visible' => isset( $_POST['is_client_visible'] ) ? 1 : 0,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( $process_id, 'error' );
		}

		$this->redirect( $process_id, 'created' );
	}

	protected function handle_update_action() {
		check_admin_referer( 'sm_update_attachment', 'sm_attachment_update_nonce' );

		$process_id    = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;

		$result = $this->attachment_service->update_attachment(
			$attachment_id,
			array(
				'attachment_type'   => isset( $_POST['attachment_type'] ) ? wp_unslash( $_POST['attachment_type'] ) : 'document',
				'title'             => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '',
				'description'       => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'is_internal'       => isset( $_POST['is_internal'] ) ? 1 : 0,
				'is_client_visible' => isset( $_POST['is_client_visible'] ) ? 1 : 0,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( $process_id, 'error' );
		}

		$this->redirect( $process_id, 'updated' );
	}

	protected function handle_delete_action() {
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( wp_unslash( $_GET['attachment_id'] ) ) : 0;
		$process_id    = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

		check_admin_referer( 'sm_delete_attachment_' . $attachment_id );

		$result = $this->attachment_service->delete_attachment( $attachment_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( $process_id, 'error' );
		}

		$this->redirect( $process_id, 'deleted' );
	}

	protected function handle_toggle_client_visible_action() {
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( wp_unslash( $_GET['attachment_id'] ) ) : 0;
		$process_id    = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$attachment    = $this->attachment_service->get_attachment( $attachment_id );

		check_admin_referer( 'sm_toggle_attachment_visibility_' . $attachment_id );

		if ( ! $attachment ) {
			$this->store_errors( new WP_Error( 'sm_attachment_not_found', __( 'El documento adjunto no existe.', 'super-mechanic' ) ) );
			$this->redirect( $process_id, 'error' );
		}

		$make_visible = empty( $attachment['is_client_visible'] );
		$result       = $this->attachment_service->update_attachment(
			$attachment_id,
			array(
				'is_client_visible' => $make_visible ? 1 : 0,
				'is_internal'       => $make_visible ? 0 : (int) $attachment['is_internal'],
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( $process_id, 'error' );
		}

		$this->redirect( $process_id, 'visibility_updated' );
	}

	protected function redirect( $process_id, $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'super-mechanic-processes', 'action' => 'edit', 'id' => absint( $process_id ), 'tab' => 'documents', 'sm_attachment_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	protected function store_errors( WP_Error $error ) {
		set_transient( $this->get_error_transient_key(), $error->get_error_messages(), MINUTE_IN_SECONDS );
	}

	protected function render_notice( $message, $type ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	protected function is_processes_screen() {
		return isset( $_GET['page'] ) && 'super-mechanic-processes' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	protected function get_error_transient_key() {
		return 'sm_attachment_errors_' . get_current_user_id();
	}
}
