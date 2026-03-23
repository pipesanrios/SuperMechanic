<?php
/**
 * Mechanic dashboard controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Attachments\Attachment_Service;
use Super_Mechanic\Attachments\Process_Timeline_Service;
use Super_Mechanic\Communication\Comment_Service;
use Super_Mechanic\Flows\Flow_Step_Service;
use Super_Mechanic\Helpers\Download_Service;
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

	public function __construct( Dashboard_Service $dashboard_service = null, Process_Service $process_service = null, Process_Timeline_Service $timeline_service = null, Comment_Service $comment_service = null, Attachment_Service $attachment_service = null, Maintenance_Service $maintenance_service = null, Flow_Step_Service $flow_step_service = null, Download_Service $download_service = null ) {
		$this->dashboard_service   = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->process_service     = $process_service ? $process_service : new Process_Service();
		$this->timeline_service    = $timeline_service ? $timeline_service : new Process_Timeline_Service();
		$this->comment_service     = $comment_service ? $comment_service : new Comment_Service();
		$this->attachment_service  = $attachment_service ? $attachment_service : new Attachment_Service();
		$this->maintenance_service = $maintenance_service ? $maintenance_service : new Maintenance_Service();
		$this->flow_step_service   = $flow_step_service ? $flow_step_service : new Flow_Step_Service();
		$this->download_service    = $download_service ? $download_service : new Download_Service();
	}

	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_handle_actions' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	public function maybe_handle_actions() {
		if ( ! $this->is_mechanic_screen() ) {
			return;
		}

		$this->ensure_permissions();

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
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

	public function render_page() {
		$this->ensure_permissions();

		$process_id = isset( $_GET['process_id'] ) ? absint( wp_unslash( $_GET['process_id'] ) ) : 0;

		echo '<div class="wrap sm-mechanic-dashboard">';
		echo '<h1>' . esc_html__( 'Portal mecánico', 'super-mechanic' ) . '</h1>';
		echo '<p>' . esc_html__( 'Trabaja solo sobre procesos asignados o permitidos por la política actual del sistema.', 'super-mechanic' ) . '</p>';

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
			$this->render_notice( __( 'Estado del proceso actualizado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'step_updated' === $notice ) {
			$this->render_notice( __( 'Paso del proceso actualizado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'comment_created' === $notice ) {
			$this->render_notice( __( 'Nota técnica registrada correctamente.', 'super-mechanic' ), 'success' );
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
		$current_status    = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : '';
		$current_type      = isset( $_GET['filter_process_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_process_type'] ) ) : '';
		$status_options    = $this->process_service->get_status_options();
		$process_type_opts = $this->process_service->get_process_type_options();

		echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0 24px;">';
		$this->render_kpi_card( __( 'Procesos activos', 'super-mechanic' ), $kpis['active_processes'] );
		$this->render_kpi_card( __( 'Esperando aprobación', 'super-mechanic' ), $kpis['pending_approvals'] );
		$this->render_kpi_card( __( 'Mantenimientos', 'super-mechanic' ), $kpis['maintenance_processes'] );
		echo '</div>';

		echo '<form method="get" style="margin:0 0 16px;">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
		echo '<select name="filter_process_type">';
		echo '<option value="">' . esc_html__( 'Todos los tipos', 'super-mechanic' ) . '</option>';
		foreach ( $process_type_opts as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current_type, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo '<select name="filter_status">';
		echo '<option value="">' . esc_html__( 'Todos los estados', 'super-mechanic' ) . '</option>';
		foreach ( $status_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current_status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Filtrar', 'super-mechanic' ), '', 'filter_action', false );
		echo '</form>';

		echo '<table class="widefat striped">';
		echo '<thead><tr><th>ID</th><th>' . esc_html__( 'Título', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Paso actual', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cliente', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehículo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Actualizado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Detalle', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $processes ) ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No hay procesos disponibles para este mecánico.', 'super-mechanic' ) . '</td></tr>';
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
				echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html__( 'Abrir', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	protected function render_process_detail_page( $process_id ) {
		$process = $this->get_accessible_process_row( $process_id );

		if ( empty( $process ) ) {
			$this->render_notice( __( 'No tienes acceso a este proceso o el proceso no existe.', 'super-mechanic' ), 'error' );
			echo '<p><a href="' . esc_url( $this->get_page_url() ) . '">' . esc_html__( 'Volver al portal mecánico', 'super-mechanic' ) . '</a></p>';
			return;
		}

		$timeline    = $this->timeline_service->get_process_timeline( $process_id, false );
		$comments    = $this->comment_service->get_process_comments( $process_id, array( 'per_page' => 100, 'orderby' => 'created_at', 'order' => 'DESC' ) );
		$attachments = $this->attachment_service->get_process_attachments( $process_id, array( 'per_page' => 100, 'orderby' => 'created_at', 'order' => 'DESC' ) );
		$steps       = $this->get_available_steps( $process );
		$status_opts = $this->process_service->get_status_options();
		$maintenance = 'maintenance' === $process['process_type'] ? $this->maintenance_service->get_maintenance_by_process( $process_id ) : array();

		echo '<p><a href="' . esc_url( $this->get_page_url() ) . '">' . esc_html__( '← Volver al listado', 'super-mechanic' ) . '</a></p>';
		echo '<h2>' . esc_html( $process['title'] ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:900px;margin-bottom:20px;"><tbody>';
		$this->render_summary_row( __( 'Tipo', 'super-mechanic' ), $this->humanize_key( $process['process_type'] ) );
		$this->render_summary_row( __( 'Estado', 'super-mechanic' ), $this->get_process_status_display( $process ) );
		$this->render_summary_row( __( 'Paso actual', 'super-mechanic' ), $this->get_process_step_label( $process ) );
		$this->render_summary_row( __( 'Cliente', 'super-mechanic' ), $this->get_process_client_label( $process ) );
		$this->render_summary_row( __( 'Vehículo', 'super-mechanic' ), $this->get_process_vehicle_label( $process ) );
		$this->render_summary_row( __( 'Fecha de apertura', 'super-mechanic' ), ! empty( $process['opened_at'] ) ? $process['opened_at'] : '-' );
		$this->render_summary_row( __( 'Fecha objetivo', 'super-mechanic' ), ! empty( $process['due_date'] ) ? $process['due_date'] : '-' );
		$this->render_summary_row( __( 'Notas internas', 'super-mechanic' ), ! empty( $process['internal_notes'] ) ? $process['internal_notes'] : '-' );
		echo '</tbody></table>';

		echo '<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">';
		echo '<div style="flex:1 1 320px;min-width:320px;">';
		$this->render_status_form( $process, $status_opts );
		$this->render_step_form( $process, $steps );
		$this->render_comment_form( $process );
		echo '</div>';
		echo '<div style="flex:1 1 320px;min-width:320px;">';
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
			$this->store_errors( new WP_Error( 'sm_mechanic_process_forbidden', __( 'No puedes actualizar el paso de un proceso ajeno.', 'super-mechanic' ) ) );
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
			$this->store_errors( new WP_Error( 'sm_mechanic_process_forbidden', __( 'No puedes actualizar el estado de un proceso ajeno.', 'super-mechanic' ) ) );
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
			$this->store_errors( new WP_Error( 'sm_mechanic_process_forbidden', __( 'No puedes registrar notas en un proceso ajeno.', 'super-mechanic' ) ) );
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
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px;min-width:180px;">';
		echo '<div style="font-size:13px;color:#50575e;margin-bottom:6px;">' . esc_html( $label ) . '</div>';
		echo '<div style="font-size:28px;font-weight:600;">' . esc_html( absint( $value ) ) . '</div>';
		echo '</div>';
	}

	protected function render_summary_row( $label, $value ) {
		echo '<tr><th style="width:220px;">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}

	protected function render_status_form( array $process, array $status_options ) {
		echo '<h3>' . esc_html__( 'Actualizar estado', 'super-mechanic' ) . '</h3>';
		echo '<form method="post" style="background:#fff;border:1px solid #dcdcde;padding:16px;margin-bottom:16px;">';
		wp_nonce_field( 'sm_mechanic_update_status', 'sm_mechanic_status_nonce' );
		echo '<input type="hidden" name="sm_mechanic_operation" value="update_status" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		echo '<select name="status">';
		foreach ( $status_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $process['status'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Guardar estado', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	protected function render_step_form( array $process, array $steps ) {
		echo '<h3>' . esc_html__( 'Actualizar paso', 'super-mechanic' ) . '</h3>';

		if ( empty( $steps ) ) {
			echo '<p>' . esc_html__( 'Este proceso no tiene pasos activos disponibles.', 'super-mechanic' ) . '</p>';
			return;
		}

		echo '<form method="post" style="background:#fff;border:1px solid #dcdcde;padding:16px;margin-bottom:16px;">';
		wp_nonce_field( 'sm_mechanic_update_step', 'sm_mechanic_step_nonce' );
		echo '<input type="hidden" name="sm_mechanic_operation" value="update_step" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		echo '<select name="current_step_id">';
		foreach ( $steps as $step ) {
			echo '<option value="' . esc_attr( absint( $step['id'] ) ) . '" ' . selected( absint( $process['current_step_id'] ), absint( $step['id'] ), false ) . '>' . esc_html( $step['step_label'] ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Guardar paso', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	protected function render_comment_form( array $process ) {
		echo '<h3>' . esc_html__( 'Registrar nota técnica', 'super-mechanic' ) . '</h3>';
		echo '<form method="post" style="background:#fff;border:1px solid #dcdcde;padding:16px;margin-bottom:16px;">';
		wp_nonce_field( 'sm_mechanic_create_comment', 'sm_mechanic_comment_nonce' );
		echo '<input type="hidden" name="sm_mechanic_operation" value="create_comment" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		echo '<p><label for="sm_mechanic_comment_type">' . esc_html__( 'Tipo', 'super-mechanic' ) . '</label><br />';
		echo '<select id="sm_mechanic_comment_type" name="comment_type">';
		echo '<option value="internal_note">' . esc_html__( 'Nota interna', 'super-mechanic' ) . '</option>';
		echo '<option value="staff_reply">' . esc_html__( 'Avance operativo', 'super-mechanic' ) . '</option>';
		echo '<option value="system_note">' . esc_html__( 'Nota de sistema', 'super-mechanic' ) . '</option>';
		echo '</select></p>';
		echo '<p><label for="sm_mechanic_comment_content">' . esc_html__( 'Contenido', 'super-mechanic' ) . '</label><br />';
		echo '<textarea id="sm_mechanic_comment_content" name="content" rows="5" class="large-text" required></textarea></p>';
		submit_button( __( 'Guardar nota', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</form>';
	}

	protected function render_maintenance_panel( array $process, $maintenance ) {
		echo '<h3>' . esc_html__( 'Mantenimiento', 'super-mechanic' ) . '</h3>';

		if ( 'maintenance' !== $process['process_type'] ) {
			echo '<p>' . esc_html__( 'Este proceso no pertenece al módulo de mantenimiento.', 'super-mechanic' ) . '</p>';
			return;
		}

		if ( empty( $maintenance ) ) {
			echo '<p>' . esc_html__( 'Todavía no hay ficha de mantenimiento registrada para este proceso.', 'super-mechanic' ) . '</p>';
			return;
		}

		$maintenance_id = absint( $maintenance['id'] );
		$parts          = $this->maintenance_service->get_parts( $maintenance_id );
		$labor          = $this->maintenance_service->get_labor( $maintenance_id );

		echo '<table class="widefat striped" style="margin-bottom:16px;"><tbody>';
		$this->render_summary_row( __( 'Diagnóstico', 'super-mechanic' ), ! empty( $maintenance['diagnosis'] ) ? $maintenance['diagnosis'] : '-' );
		$this->render_summary_row( __( 'Mecánico asignado', 'super-mechanic' ), ! empty( $maintenance['mechanic_id'] ) ? '#' . absint( $maintenance['mechanic_id'] ) : '-' );
		$this->render_summary_row( __( 'Horas estimadas', 'super-mechanic' ), isset( $maintenance['estimated_hours'] ) ? (string) $maintenance['estimated_hours'] : '-' );
		$this->render_summary_row( __( 'Aprobado por cliente', 'super-mechanic' ), ! empty( $maintenance['client_approved'] ) ? __( 'Sí', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) );
		$this->render_summary_row( __( 'Total repuestos', 'super-mechanic' ), (string) number_format_i18n( $this->maintenance_service->calculate_total_parts( $maintenance_id ), 2 ) );
		$this->render_summary_row( __( 'Total mano de obra', 'super-mechanic' ), (string) number_format_i18n( $this->maintenance_service->calculate_total_labor( $maintenance_id ), 2 ) );
		$this->render_summary_row( __( 'Total servicio', 'super-mechanic' ), (string) number_format_i18n( $this->maintenance_service->calculate_total_service( $maintenance_id ), 2 ) );
		echo '</tbody></table>';

		echo '<h4>' . esc_html__( 'Repuestos', 'super-mechanic' ) . '</h4>';
		echo '<table class="widefat striped" style="margin-bottom:16px;"><thead><tr><th>' . esc_html__( 'Nombre', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cantidad', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $parts ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'Sin repuestos registrados.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $parts as $part ) {
				echo '<tr><td>' . esc_html( $part['part_name'] ) . '</td><td>' . esc_html( $part['quantity'] ) . '</td><td>' . esc_html( number_format_i18n( (float) $part['total_price'], 2 ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h4>' . esc_html__( 'Mano de obra', 'super-mechanic' ) . '</h4>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Descripción', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Horas', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $labor ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'Sin mano de obra registrada.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $labor as $row ) {
				echo '<tr><td>' . esc_html( $row['description'] ) . '</td><td>' . esc_html( $row['hours'] ) . '</td><td>' . esc_html( number_format_i18n( (float) $row['total_price'], 2 ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';
	}

	protected function render_attachments_table( array $attachments ) {
		echo '<h3>' . esc_html__( 'Adjuntos relevantes', 'super-mechanic' ) . '</h3>';
		echo '<table class="widefat striped" style="margin-bottom:20px;"><thead><tr><th>' . esc_html__( 'Documento', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Visibilidad', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acción', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $attachments ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay adjuntos para este proceso.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $attachments as $attachment ) {
				$visibility = ! empty( $attachment['is_internal'] ) ? __( 'Interno', 'super-mechanic' ) : __( 'Operativo', 'super-mechanic' );
				if ( ! empty( $attachment['is_client_visible'] ) ) {
					$visibility .= ' / ' . __( 'Visible cliente', 'super-mechanic' );
				}

				echo '<tr>';
				echo '<td>' . esc_html( $attachment['title'] ) . '</td>';
				echo '<td>' . esc_html( $attachment['attachment_type'] ) . '</td>';
				echo '<td>' . esc_html( $visibility ) . '</td>';
				echo '<td>' . esc_html( $attachment['created_at'] ) . '</td>';
				echo '<td><a href="' . esc_url( $this->download_service->get_download_url( 'attachment', absint( $attachment['id'] ) ) ) . '">' . esc_html__( 'Descargar', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	protected function render_comments_table( array $comments ) {
		echo '<h3>' . esc_html__( 'Comentarios relevantes', 'super-mechanic' ) . '</h3>';
		echo '<table class="widefat striped" style="margin-bottom:20px;"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Visibilidad', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Contenido', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $comments ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No hay comentarios para este proceso.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $comments as $comment ) {
				$visibility = ! empty( $comment['is_internal'] ) ? __( 'Interno', 'super-mechanic' ) : __( 'Operativo', 'super-mechanic' );
				if ( ! empty( $comment['is_client_visible'] ) ) {
					$visibility .= ' / ' . __( 'Visible cliente', 'super-mechanic' );
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
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Evento', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $timeline ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No hay eventos registrados para este proceso.', 'super-mechanic' ) . '</td></tr>';
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

		return ! empty( $process['current_step_id'] ) ? '#' . absint( $process['current_step_id'] ) : __( 'Sin paso', 'super-mechanic' );
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

		return '' !== $label ? $label : __( 'Sin cliente', 'super-mechanic' );
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

		return '' !== $label ? $label : __( 'Vehículo no identificado', 'super-mechanic' );
	}

	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	protected function current_user_can_access_process( $process_id ) {
		return $this->process_service->user_can_access_process( get_current_user_id(), absint( $process_id ) );
	}

	protected function ensure_permissions() {
		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para usar el portal mecánico.', 'super-mechanic' ) );
		}
	}

	protected function is_mechanic_screen() {
		return isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	protected function get_page_url( $args = array() ) {
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
}
