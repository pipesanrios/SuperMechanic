<?php
/**
 * Flow admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Flows;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles flow admin flows.
 */
class Flow_Admin_Controller {
	/**
	 * Flow service.
	 *
	 * @var Flow_Service
	 */
	protected $flow_service;

	/**
	 * Flow step service.
	 *
	 * @var Flow_Step_Service
	 */
	protected $step_service;

	/**
	 * Constructor.
	 *
	 * @param Flow_Service|null      $flow_service Flow service.
	 * @param Flow_Step_Service|null $step_service Step service.
	 */
	public function __construct( Flow_Service $flow_service = null, Flow_Step_Service $step_service = null ) {
		$this->flow_service = $flow_service ? $flow_service : new Flow_Service();
		$this->step_service = $step_service ? $step_service : new Flow_Step_Service();
	}

	/**
	 * Register controller hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_handle_actions' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Process actions before output.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_flows_screen() ) {
			return;
		}

		$this->ensure_permissions();
		$this->handle_actions();
	}

	/**
	 * Render module page.
	 *
	 * @return void
	 */
	public function render_page() {
		$this->ensure_permissions();

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$flow_id = isset( $_GET['flow_id'] ) ? absint( wp_unslash( $_GET['flow_id'] ) ) : 0;

		if ( 'new' === $action ) {
			$this->render_flow_form_page();
			return;
		}

		if ( 'edit' === $action ) {
			$flow = $this->flow_service->get_flow( $id );
			if ( empty( $flow ) ) {
				wp_die( esc_html__( 'El flujo solicitado no existe.', 'super-mechanic' ) );
			}

			$this->render_flow_form_page( $flow, true );
			return;
		}

		if ( 'steps' === $action ) {
			$this->render_steps_page( $id );
			return;
		}

		if ( 'new_step' === $action ) {
			$this->render_step_form_page( array( 'flow_id' => $flow_id ) );
			return;
		}

		if ( 'edit_step' === $action ) {
			$step = $this->step_service->get_step( $id );
			if ( empty( $step ) ) {
				wp_die( esc_html__( 'El paso solicitado no existe.', 'super-mechanic' ) );
			}

			$this->render_step_form_page( $step, true );
			return;
		}

		$this->render_list_page();
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! $this->is_flows_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$count  = isset( $_GET['deleted_count'] ) ? absint( $_GET['deleted_count'] ) : 0;

		$messages = array(
			'created'      => __( 'Flujo creado correctamente.', 'super-mechanic' ),
			'updated'      => __( 'Flujo actualizado correctamente.', 'super-mechanic' ),
			'deleted'      => __( 'Flujo eliminado correctamente.', 'super-mechanic' ),
			'step_created' => __( 'Paso creado correctamente.', 'super-mechanic' ),
			'step_updated' => __( 'Paso actualizado correctamente.', 'super-mechanic' ),
			'step_deleted' => __( 'Paso eliminado correctamente.', 'super-mechanic' ),
			'reordered'    => __( 'Orden de pasos actualizado correctamente.', 'super-mechanic' ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			$this->render_notice( $messages[ $notice ], 'success' );
		}

		if ( 'bulk_deleted' === $notice ) {
			$this->render_notice(
				sprintf(
					/* translators: %d: number of deleted flows. */
					__( '%d flujos eliminados correctamente.', 'super-mechanic' ),
					$count
				),
				'success'
			);
		}

		if ( 'error' === $notice ) {
			$error_messages = get_transient( $this->get_error_transient_key() );
			delete_transient( $this->get_error_transient_key() );

			if ( is_array( $error_messages ) ) {
				foreach ( $error_messages as $message ) {
					$this->render_notice( $message, 'error' );
				}
			}
		}
	}

	/**
	 * Render flows list page.
	 *
	 * @return void
	 */
	protected function render_list_page() {
		$list_table = new Flow_List_Table( $this->flow_service );
		$list_table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Flujos', 'super-mechanic' ) . '</h1>';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ) . '" class="page-title-action">' . esc_html__( 'Añadir nuevo', 'super-mechanic' ) . '</a>';
		echo '<hr class="wp-header-end" />';
		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="super-mechanic-flows" />';
		wp_nonce_field( 'sm_bulk_delete_flows', 'sm_bulk_delete_nonce' );
		$list_table->search_box( __( 'Buscar flujos', 'super-mechanic' ), 'sm-flows' );
		$list_table->display();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render flow form page.
	 *
	 * @param array<string, mixed> $flow    Flow data.
	 * @param bool                 $is_edit Whether editing.
	 * @return void
	 */
	protected function render_flow_form_page( $flow = array(), $is_edit = false ) {
		$defaults = array(
			'id'           => 0,
			'name'         => '',
			'process_type' => 'maintenance',
			'description'  => '',
			'is_active'    => 1,
		);

		$stored = get_transient( $this->get_flow_form_transient_key() );
		if ( is_array( $stored ) ) {
			$flow = array_merge( $flow, $stored );
			delete_transient( $this->get_flow_form_transient_key() );
		}

		$flow          = wp_parse_args( $flow, $defaults );
		$title         = $is_edit ? __( 'Editar flujo', 'super-mechanic' ) : __( 'Nuevo flujo', 'super-mechanic' );
		$process_types = $this->flow_service->get_process_type_options();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<form method="post" action="' . esc_url( $this->get_page_url( $is_edit ? array( 'action' => 'edit', 'id' => absint( $flow['id'] ) ) : array( 'action' => 'new' ) ) ) . '">';
		wp_nonce_field( 'sm_save_flow', 'sm_flow_nonce' );
		echo '<input type="hidden" name="sm_flow_operation" value="' . esc_attr( $is_edit ? 'update' : 'create' ) . '" />';
		echo '<input type="hidden" name="flow_id" value="' . esc_attr( absint( $flow['id'] ) ) . '" />';
		echo '<table class="form-table" role="presentation">';
		$this->render_text_field( 'name', __( 'Nombre', 'super-mechanic' ), $flow['name'], true );
		$this->render_select_field( 'process_type', __( 'Tipo de proceso', 'super-mechanic' ), $flow['process_type'], $process_types );
		$this->render_textarea_field( 'description', __( 'Descripción', 'super-mechanic' ), $flow['description'] );
		$this->render_checkbox_field( 'is_active', __( 'Activo', 'super-mechanic' ), ! empty( $flow['is_active'] ), __( 'Habilitar este flujo para su uso.', 'super-mechanic' ) );
		echo '</table>';
		submit_button( $is_edit ? __( 'Actualizar flujo', 'super-mechanic' ) : __( 'Crear flujo', 'super-mechanic' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render steps page.
	 *
	 * @param int $flow_id Flow ID.
	 * @return void
	 */
	protected function render_steps_page( $flow_id ) {
		$flow = $this->flow_service->get_flow( $flow_id );

		if ( empty( $flow ) ) {
			wp_die( esc_html__( 'El flujo solicitado no existe.', 'super-mechanic' ) );
		}

		$steps = $this->step_service->get_steps_by_flow( $flow_id );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( sprintf( __( 'Pasos del flujo: %s', 'super-mechanic' ), $flow['name'] ) ) . '</h1>';
		echo '<p>' . esc_html__( 'Gestiona el pipeline del flujo y el orden operativo de sus pasos.', 'super-mechanic' ) . '</p>';
		echo '<p><a href="' . esc_url( $this->get_page_url( array( 'action' => 'new_step', 'flow_id' => absint( $flow_id ) ) ) ) . '" class="button button-primary">' . esc_html__( 'Añadir paso', 'super-mechanic' ) . '</a> '; 
		echo '<a href="' . esc_url( $this->get_page_url() ) . '" class="button">' . esc_html__( 'Volver a flujos', 'super-mechanic' ) . '</a></p>';
		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="super-mechanic-flows" />';
		echo '<input type="hidden" name="flow_id" value="' . esc_attr( absint( $flow_id ) ) . '" />';
		echo '<input type="hidden" name="sm_flow_operation" value="reorder_steps" />';
		wp_nonce_field( 'sm_reorder_flow_steps', 'sm_flow_steps_nonce' );
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Orden', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Clave', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Etiqueta', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Inicial', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Final', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Apr.', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Nota', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Activo', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $steps ) ) {
			echo '<tr><td colspan="9">' . esc_html__( 'Aún no hay pasos definidos para este flujo.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $steps as $step ) {
				$edit_url = $this->get_page_url(
					array(
						'action'  => 'edit_step',
						'id'      => absint( $step['id'] ),
						'flow_id' => absint( $flow_id ),
					)
				);
				$delete_url = wp_nonce_url(
					$this->get_page_url(
						array(
							'action'  => 'delete_step',
							'id'      => absint( $step['id'] ),
							'flow_id' => absint( $flow_id ),
						)
					),
					'sm_delete_flow_step_' . absint( $step['id'] )
				);

				echo '<tr>';
				echo '<td><input type="number" name="step_positions[' . esc_attr( absint( $step['id'] ) ) . ']" value="' . esc_attr( absint( $step['step_order'] ) ) . '" min="1" class="small-text" /></td>';
				echo '<td>' . esc_html( $step['step_key'] ) . '</td>';
				echo '<td>' . esc_html( $step['step_label'] ) . '</td>';
				echo '<td>' . esc_html( ! empty( $step['is_initial'] ) ? __( 'Sí', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $step['is_final'] ) ? __( 'Sí', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $step['requires_approval'] ) ? __( 'Sí', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $step['requires_note'] ) ? __( 'Sí', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $step['is_active'] ) ? __( 'Sí', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</td>';
				echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'super-mechanic' ) . '</a> | <a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Eliminar', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		submit_button( __( 'Guardar orden', 'super-mechanic' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render step form page.
	 *
	 * @param array<string, mixed> $step    Step data.
	 * @param bool                 $is_edit Whether editing.
	 * @return void
	 */
	protected function render_step_form_page( $step = array(), $is_edit = false ) {
		$defaults = array(
			'id'                => 0,
			'flow_id'           => 0,
			'step_key'          => '',
			'step_label'        => '',
			'step_order'        => 1,
			'is_initial'        => 0,
			'is_final'          => 0,
			'requires_approval' => 0,
			'requires_note'     => 0,
			'is_active'         => 1,
		);

		$stored = get_transient( $this->get_step_form_transient_key() );
		if ( is_array( $stored ) ) {
			$step = array_merge( $step, $stored );
			delete_transient( $this->get_step_form_transient_key() );
		}

		$step  = wp_parse_args( $step, $defaults );
		$flow  = $this->flow_service->get_flow( absint( $step['flow_id'] ) );
		$title = $is_edit ? __( 'Editar paso del flujo', 'super-mechanic' ) : __( 'Nuevo paso del flujo', 'super-mechanic' );

		if ( empty( $flow ) ) {
			wp_die( esc_html__( 'Debes seleccionar un flujo válido para gestionar pasos.', 'super-mechanic' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p>' . esc_html( sprintf( __( 'Flujo: %s', 'super-mechanic' ), $flow['name'] ) ) . '</p>';
		echo '<form method="post" action="' . esc_url( $this->get_page_url( $is_edit ? array( 'action' => 'edit_step', 'id' => absint( $step['id'] ), 'flow_id' => absint( $step['flow_id'] ) ) : array( 'action' => 'new_step', 'flow_id' => absint( $step['flow_id'] ) ) ) ) . '">';
		wp_nonce_field( 'sm_save_flow_step', 'sm_flow_step_nonce' );
		echo '<input type="hidden" name="sm_flow_operation" value="' . esc_attr( $is_edit ? 'update_step' : 'create_step' ) . '" />';
		echo '<input type="hidden" name="step_id" value="' . esc_attr( absint( $step['id'] ) ) . '" />';
		echo '<input type="hidden" name="flow_id" value="' . esc_attr( absint( $step['flow_id'] ) ) . '" />';
		echo '<table class="form-table" role="presentation">';
		$this->render_text_field( 'step_key', __( 'Clave técnica', 'super-mechanic' ), $step['step_key'], true );
		$this->render_text_field( 'step_label', __( 'Etiqueta visible', 'super-mechanic' ), $step['step_label'], true );
		$this->render_number_field( 'step_order', __( 'Orden', 'super-mechanic' ), $step['step_order'] );
		$this->render_checkbox_field( 'is_initial', __( 'Paso inicial', 'super-mechanic' ), ! empty( $step['is_initial'] ) );
		$this->render_checkbox_field( 'is_final', __( 'Paso final', 'super-mechanic' ), ! empty( $step['is_final'] ) );
		$this->render_checkbox_field( 'requires_approval', __( 'Requiere aprobación', 'super-mechanic' ), ! empty( $step['requires_approval'] ) );
		$this->render_checkbox_field( 'requires_note', __( 'Requiere nota', 'super-mechanic' ), ! empty( $step['requires_note'] ) );
		$this->render_checkbox_field( 'is_active', __( 'Activo', 'super-mechanic' ), ! empty( $step['is_active'] ) );
		echo '</table>';
		submit_button( $is_edit ? __( 'Actualizar paso', 'super-mechanic' ) : __( 'Crear paso', 'super-mechanic' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle actions.
	 *
	 * @return void
	 */
	protected function handle_actions() {
		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			$operation   = isset( $_POST['sm_flow_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_flow_operation'] ) ) : '';
			$bulk_action = $this->get_bulk_action();

			if ( 'create' === $operation || 'update' === $operation ) {
				$this->handle_flow_save_action( 'update' === $operation );
			}

			if ( 'create_step' === $operation || 'update_step' === $operation ) {
				$this->handle_step_save_action( 'update_step' === $operation );
			}

			if ( 'reorder_steps' === $operation ) {
				$this->handle_reorder_steps_action();
			}

			if ( 'bulk-delete' === $bulk_action ) {
				$this->handle_bulk_delete_action();
			}
		}

		if ( isset( $_GET['action'] ) ) {
			$action = sanitize_key( wp_unslash( $_GET['action'] ) );

			if ( 'delete' === $action ) {
				$this->handle_flow_delete_action();
			}

			if ( 'delete_step' === $action ) {
				$this->handle_step_delete_action();
			}
		}
	}

	/**
	 * Handle flow save action.
	 *
	 * @param bool $is_update Whether updating.
	 * @return void
	 */
	protected function handle_flow_save_action( $is_update ) {
		check_admin_referer( 'sm_save_flow', 'sm_flow_nonce' );

		$flow_id = isset( $_POST['flow_id'] ) ? absint( wp_unslash( $_POST['flow_id'] ) ) : 0;
		$data    = array(
			'name'         => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'process_type' => isset( $_POST['process_type'] ) ? wp_unslash( $_POST['process_type'] ) : '',
			'description'  => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
			'is_active'    => isset( $_POST['is_active'] ) ? wp_unslash( $_POST['is_active'] ) : 0,
		);

		$result = $is_update
			? $this->flow_service->update_flow( $flow_id, $data )
			: $this->flow_service->create_flow( $data );

		if ( is_wp_error( $result ) ) {
			$this->store_flow_form_state( $data );
			$this->store_errors( $result );
			$this->redirect( $is_update ? array( 'action' => 'edit', 'id' => $flow_id, 'sm_notice' => 'error' ) : array( 'action' => 'new', 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'sm_notice' => $is_update ? 'updated' : 'created' ) );
	}

	/**
	 * Handle flow delete action.
	 *
	 * @return void
	 */
	protected function handle_flow_delete_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'sm_delete_flow_' . $id );

		$result = $this->flow_service->delete_flow( $id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'sm_notice' => 'deleted' ) );
	}

	/**
	 * Handle flow bulk delete.
	 *
	 * @return void
	 */
	protected function handle_bulk_delete_action() {
		check_admin_referer( 'sm_bulk_delete_flows', 'sm_bulk_delete_nonce' );

		$ids = isset( $_POST['flow_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['flow_ids'] ) ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			$this->store_errors( new WP_Error( 'sm_no_flows_selected', __( 'Selecciona al menos un flujo para eliminar.', 'super-mechanic' ) ) );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			$result = $this->flow_service->delete_flow( $id );
			if ( ! is_wp_error( $result ) ) {
				++$deleted;
			}
		}

		$this->redirect( array( 'sm_notice' => 'bulk_deleted', 'deleted_count' => $deleted ) );
	}

	/**
	 * Handle step save action.
	 *
	 * @param bool $is_update Whether updating.
	 * @return void
	 */
	protected function handle_step_save_action( $is_update ) {
		check_admin_referer( 'sm_save_flow_step', 'sm_flow_step_nonce' );

		$step_id  = isset( $_POST['step_id'] ) ? absint( wp_unslash( $_POST['step_id'] ) ) : 0;
		$flow_id  = isset( $_POST['flow_id'] ) ? absint( wp_unslash( $_POST['flow_id'] ) ) : 0;
		$data     = array(
			'flow_id'           => $flow_id,
			'step_key'          => isset( $_POST['step_key'] ) ? wp_unslash( $_POST['step_key'] ) : '',
			'step_label'        => isset( $_POST['step_label'] ) ? wp_unslash( $_POST['step_label'] ) : '',
			'step_order'        => isset( $_POST['step_order'] ) ? wp_unslash( $_POST['step_order'] ) : 0,
			'is_initial'        => isset( $_POST['is_initial'] ) ? wp_unslash( $_POST['is_initial'] ) : 0,
			'is_final'          => isset( $_POST['is_final'] ) ? wp_unslash( $_POST['is_final'] ) : 0,
			'requires_approval' => isset( $_POST['requires_approval'] ) ? wp_unslash( $_POST['requires_approval'] ) : 0,
			'requires_note'     => isset( $_POST['requires_note'] ) ? wp_unslash( $_POST['requires_note'] ) : 0,
			'is_active'         => isset( $_POST['is_active'] ) ? wp_unslash( $_POST['is_active'] ) : 0,
		);

		$result = $is_update
			? $this->step_service->update_step( $step_id, $data )
			: $this->step_service->create_step( $data );

		if ( is_wp_error( $result ) ) {
			$this->store_step_form_state( $data );
			$this->store_errors( $result );
			$this->redirect(
				$is_update
					? array( 'action' => 'edit_step', 'id' => $step_id, 'flow_id' => $flow_id, 'sm_notice' => 'error' )
					: array( 'action' => 'new_step', 'flow_id' => $flow_id, 'sm_notice' => 'error' )
			);
		}

		$this->redirect( array( 'action' => 'steps', 'id' => $flow_id, 'sm_notice' => $is_update ? 'step_updated' : 'step_created' ) );
	}

	/**
	 * Handle step delete action.
	 *
	 * @return void
	 */
	protected function handle_step_delete_action() {
		$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$flow_id = isset( $_GET['flow_id'] ) ? absint( $_GET['flow_id'] ) : 0;
		check_admin_referer( 'sm_delete_flow_step_' . $id );

		$result = $this->step_service->delete_step( $id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'action' => 'steps', 'id' => $flow_id, 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'action' => 'steps', 'id' => $flow_id, 'sm_notice' => 'step_deleted' ) );
	}

	/**
	 * Handle step reorder action.
	 *
	 * @return void
	 */
	protected function handle_reorder_steps_action() {
		check_admin_referer( 'sm_reorder_flow_steps', 'sm_flow_steps_nonce' );

		$flow_id        = isset( $_POST['flow_id'] ) ? absint( wp_unslash( $_POST['flow_id'] ) ) : 0;
		$step_positions = isset( $_POST['step_positions'] ) ? (array) wp_unslash( $_POST['step_positions'] ) : array();

		if ( empty( $step_positions ) ) {
			$this->store_errors( new WP_Error( 'sm_no_step_order', __( 'No se recibieron pasos para reordenar.', 'super-mechanic' ) ) );
			$this->redirect( array( 'action' => 'steps', 'id' => $flow_id, 'sm_notice' => 'error' ) );
		}

		asort( $step_positions, SORT_NUMERIC );
		$ordered_ids = array_map( 'absint', array_keys( $step_positions ) );
		$result      = $this->step_service->reorder_steps( $flow_id, $ordered_ids );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'action' => 'steps', 'id' => $flow_id, 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'action' => 'steps', 'id' => $flow_id, 'sm_notice' => 'reordered' ) );
	}

	/**
	 * Render notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 * @return void
	 */
	protected function render_notice( $message, $type ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Render text field.
	 *
	 * @param string $name     Field name.
	 * @param string $label    Field label.
	 * @param string $value    Field value.
	 * @param bool   $required Whether required.
	 * @return void
	 */
	protected function render_text_field( $name, $label, $value, $required = false ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><input name="' . esc_attr( $name ) . '" type="text" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text"' . ( $required ? ' required' : '' ) . ' /></td></tr>';
	}

	/**
	 * Render textarea field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return void
	 */
	protected function render_textarea_field( $name, $label, $value ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="large-text" rows="5">' . esc_textarea( $value ) . '</textarea></td></tr>';
	}

	/**
	 * Render select field.
	 *
	 * @param string               $name     Field name.
	 * @param string               $label    Field label.
	 * @param string               $selected Selected value.
	 * @param array<string, mixed> $options  Select options.
	 * @return void
	 */
	protected function render_select_field( $name, $label, $selected, $options ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $option_label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	/**
	 * Render number field.
	 *
	 * @param string     $name  Field name.
	 * @param string     $label Field label.
	 * @param string|int $value Field value.
	 * @return void
	 */
	protected function render_number_field( $name, $label, $value ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><input name="' . esc_attr( $name ) . '" type="number" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="small-text" min="1" /></td></tr>';
	}

	/**
	 * Render checkbox field.
	 *
	 * @param string      $name        Field name.
	 * @param string      $label       Field label.
	 * @param bool        $checked     Whether checked.
	 * @param string|null $description Optional description.
	 * @return void
	 */
	protected function render_checkbox_field( $name, $label, $checked, $description = null ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label><input name="' . esc_attr( $name ) . '" type="checkbox" value="1" ' . checked( $checked, true, false ) . ' /> ' . esc_html( $description ? $description : $label ) . '</label></td></tr>';
	}

	/**
	 * Ensure permissions.
	 *
	 * @return void
	 */
	protected function ensure_permissions() {
		if ( ! current_user_can( 'sm_manage_flows' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para gestionar flujos.', 'super-mechanic' ) );
		}
	}

	/**
	 * Get current bulk action.
	 *
	 * @return string
	 */
	protected function get_bulk_action() {
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( '-1' === $action ) {
			$action = isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '';
		}

		return $action;
	}

	/**
	 * Store errors.
	 *
	 * @param WP_Error $error Error object.
	 * @return void
	 */
	protected function store_errors( WP_Error $error ) {
		set_transient( $this->get_error_transient_key(), $error->get_error_messages(), MINUTE_IN_SECONDS );
	}

	/**
	 * Store flow form state.
	 *
	 * @param array<string, mixed> $data Flow form data.
	 * @return void
	 */
	protected function store_flow_form_state( $data ) {
		set_transient( $this->get_flow_form_transient_key(), $data, MINUTE_IN_SECONDS );
	}

	/**
	 * Store step form state.
	 *
	 * @param array<string, mixed> $data Step form data.
	 * @return void
	 */
	protected function store_step_form_state( $data ) {
		set_transient( $this->get_step_form_transient_key(), $data, MINUTE_IN_SECONDS );
	}

	/**
	 * Redirect to flows page.
	 *
	 * @param array<string, mixed> $args Query args.
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
					'page' => 'super-mechanic-flows',
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Whether this is the flows screen.
	 *
	 * @return bool
	 */
	protected function is_flows_screen() {
		return isset( $_GET['page'] ) && 'super-mechanic-flows' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Get error transient key.
	 *
	 * @return string
	 */
	protected function get_error_transient_key() {
		return 'sm_flow_errors_' . get_current_user_id();
	}

	/**
	 * Get flow form transient key.
	 *
	 * @return string
	 */
	protected function get_flow_form_transient_key() {
		return 'sm_flow_form_' . get_current_user_id();
	}

	/**
	 * Get step form transient key.
	 *
	 * @return string
	 */
	protected function get_step_form_transient_key() {
		return 'sm_flow_step_form_' . get_current_user_id();
	}
}
