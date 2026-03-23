<?php
/**
 * Paperwork admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Paperwork;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles paperwork UI inside process edit screens.
 */
class Paperwork_Admin_Controller {
	/**
	 * Service.
	 *
	 * @var Paperwork_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Paperwork_Service|null $service Service.
	 */
	public function __construct( Paperwork_Service $service = null ) {
		$this->service = $service ? $service : new Paperwork_Service();
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
	 * Maybe handle actions.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_paperwork_screen() ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para gestionar trámites.', 'super-mechanic' ) );
		}

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		$operation = isset( $_POST['sm_paperwork_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_paperwork_operation'] ) ) : '';

		if ( 'save_paperwork' === $operation ) {
			$this->handle_save();
		}

		if ( 'add_item' === $operation ) {
			$this->handle_add_item();
		}

		if ( 'update_item' === $operation ) {
			$this->handle_update_item();
		}

		if ( 'delete_item' === $operation ) {
			$this->handle_delete_item();
		}
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! $this->is_paperwork_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$map    = array(
			'paperwork_saved'  => __( 'Datos de trámite actualizados.', 'super-mechanic' ),
			'paperwork_added'  => __( 'Ítem agregado correctamente.', 'super-mechanic' ),
			'paperwork_updated'=> __( 'Ítem actualizado correctamente.', 'super-mechanic' ),
			'paperwork_deleted'=> __( 'Ítem eliminado correctamente.', 'super-mechanic' ),
		);

		if ( isset( $map[ $notice ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $notice ] ) . '</p></div>';
		}

		if ( 'paperwork_error' === $notice ) {
			$messages = get_transient( $this->get_error_transient_key() );
			delete_transient( $this->get_error_transient_key() );

			if ( is_array( $messages ) ) {
				foreach ( $messages as $message ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
				}
			}
		}
	}

	/**
	 * Render panel.
	 *
	 * @param array<string, mixed> $process Process data.
	 * @return void
	 */
	public function render_process_panel( $process ) {
		$row = $this->service->ensure_record( absint( $process['id'] ) );

		if ( is_wp_error( $row ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $row->get_error_message() ) . '</p></div>';
			return;
		}

		$items = $this->service->get_items( absint( $row['id'] ) );
		$users = get_users(
			array(
				'role__in' => array( 'sm_admin', 'administrator', 'sm_mechanic' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		);

		echo '<h2>' . esc_html__( 'Paperwork', 'super-mechanic' ) . '</h2>';
		echo '<form method="post">';
		echo '<input type="hidden" name="sm_paperwork_operation" value="save_paperwork" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		wp_nonce_field( 'sm_save_paperwork', 'sm_paperwork_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="paperwork_type">' . esc_html__( 'Tipo de trámite', 'super-mechanic' ) . '</label></th><td><input type="text" name="paperwork_type" id="paperwork_type" value="' . esc_attr( $row['paperwork_type'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th scope="row"><label for="target_date">' . esc_html__( 'Fecha objetivo', 'super-mechanic' ) . '</label></th><td><input type="date" name="target_date" id="target_date" value="' . esc_attr( $row['target_date'] ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="completed_date">' . esc_html__( 'Fecha completado', 'super-mechanic' ) . '</label></th><td><input type="date" name="completed_date" id="completed_date" value="' . esc_attr( $row['completed_date'] ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="paperwork_assigned_user_id">' . esc_html__( 'Responsable asignado', 'super-mechanic' ) . '</label></th><td><select name="assigned_user_id" id="paperwork_assigned_user_id"><option value="0">' . esc_html__( 'Sin asignar', 'super-mechanic' ) . '</option>';
		foreach ( $users as $user ) {
			echo '<option value="' . esc_attr( absint( $user->ID ) ) . '" ' . selected( absint( $row['assigned_user_id'] ), absint( $user->ID ), false ) . '>' . esc_html( $user->display_name ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="paperwork_status">' . esc_html__( 'Estado', 'super-mechanic' ) . '</label></th><td><select name="status" id="paperwork_status">';
		foreach ( $this->service->get_status_options() as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $row['status'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="paperwork_notes">' . esc_html__( 'Notas', 'super-mechanic' ) . '</label></th><td><textarea name="notes" id="paperwork_notes" class="large-text" rows="6">' . esc_textarea( $row['notes'] ) . '</textarea></td></tr>';
		echo '</table>';
		submit_button( __( 'Guardar trámite', 'super-mechanic' ) );
		echo '</form>';

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Checklist administrativo', 'super-mechanic' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Orden', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Clave', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Etiqueta', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Requerido', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Completado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Notas', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $items ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'Aún no hay ítems cargados.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				echo '<tr><td colspan="8"><form method="post" style="margin:0;">';
				echo '<input type="hidden" name="sm_paperwork_operation" value="update_item" />';
				echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
				echo '<input type="hidden" name="item_id" value="' . esc_attr( absint( $item['id'] ) ) . '" />';
				echo '<input type="hidden" name="paperwork_id" value="' . esc_attr( absint( $row['id'] ) ) . '" />';
				wp_nonce_field( 'sm_update_paperwork_item', 'sm_update_paperwork_item_nonce' );
				echo '<table style="width:100%;"><tr>';
				echo '<td><input type="number" name="sort_order" value="' . esc_attr( $item['sort_order'] ) . '" class="small-text" /></td>';
				echo '<td><input type="text" name="item_key" value="' . esc_attr( $item['item_key'] ) . '" class="regular-text" /></td>';
				echo '<td><input type="text" name="item_label" value="' . esc_attr( $item['item_label'] ) . '" class="regular-text" /></td>';
				echo '<td><input type="checkbox" name="is_required" value="1" ' . checked( ! empty( $item['is_required'] ), true, false ) . ' /></td>';
				echo '<td><input type="checkbox" name="is_completed" value="1" ' . checked( ! empty( $item['is_completed'] ), true, false ) . ' /></td>';
				echo '<td>' . esc_html( $item['completed_at'] ) . '</td>';
				echo '<td><input type="text" name="notes" value="' . esc_attr( $item['notes'] ) . '" class="regular-text" /></td>';
				echo '<td>';
				submit_button( __( 'Actualizar', 'super-mechanic' ), 'secondary small', 'submit', false );
				echo ' ';
				echo '<button type="submit" name="sm_paperwork_operation" value="delete_item" class="button button-link-delete">' . esc_html__( 'Eliminar', 'super-mechanic' ) . '</button>';
				echo '</td>';
				echo '</tr></table>';
				echo '</form></td></tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Agregar ítem', 'super-mechanic' ) . '</h3>';
		echo '<form method="post">';
		echo '<input type="hidden" name="sm_paperwork_operation" value="add_item" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		echo '<input type="hidden" name="paperwork_id" value="' . esc_attr( absint( $row['id'] ) ) . '" />';
		wp_nonce_field( 'sm_add_paperwork_item', 'sm_add_paperwork_item_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="item_key">' . esc_html__( 'Clave', 'super-mechanic' ) . '</label></th><td><input type="text" name="item_key" id="item_key" class="regular-text" required /></td></tr>';
		echo '<tr><th scope="row"><label for="item_label">' . esc_html__( 'Etiqueta', 'super-mechanic' ) . '</label></th><td><input type="text" name="item_label" id="item_label" class="regular-text" required /></td></tr>';
		echo '<tr><th scope="row"><label for="item_sort_order">' . esc_html__( 'Orden', 'super-mechanic' ) . '</label></th><td><input type="number" name="sort_order" id="item_sort_order" value="' . esc_attr( count( $items ) + 1 ) . '" class="small-text" /></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Opciones', 'super-mechanic' ) . '</th><td><label><input type="checkbox" name="is_required" value="1" /> ' . esc_html__( 'Requerido', 'super-mechanic' ) . '</label> <label style="margin-left:12px;"><input type="checkbox" name="is_completed" value="1" /> ' . esc_html__( 'Completado', 'super-mechanic' ) . '</label></td></tr>';
		echo '<tr><th scope="row"><label for="item_notes">' . esc_html__( 'Notas', 'super-mechanic' ) . '</label></th><td><textarea name="notes" id="item_notes" class="large-text" rows="3"></textarea></td></tr>';
		echo '</table>';
		submit_button( __( 'Agregar ítem', 'super-mechanic' ) );
		echo '</form>';
	}

	/**
	 * Handle save.
	 *
	 * @return void
	 */
	protected function handle_save() {
		check_admin_referer( 'sm_save_paperwork', 'sm_paperwork_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$result = $this->service->save_paperwork(
			$process_id,
			array(
				'paperwork_type'   => isset( $_POST['paperwork_type'] ) ? wp_unslash( $_POST['paperwork_type'] ) : '',
				'target_date'      => isset( $_POST['target_date'] ) ? wp_unslash( $_POST['target_date'] ) : '',
				'completed_date'   => isset( $_POST['completed_date'] ) ? wp_unslash( $_POST['completed_date'] ) : '',
				'assigned_user_id' => isset( $_POST['assigned_user_id'] ) ? wp_unslash( $_POST['assigned_user_id'] ) : 0,
				'status'           => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'pending',
				'notes'            => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'paperwork_error' );
		}

		$this->redirect_to_process( $process_id, 'paperwork_saved' );
	}

	/**
	 * Handle add item.
	 *
	 * @return void
	 */
	protected function handle_add_item() {
		check_admin_referer( 'sm_add_paperwork_item', 'sm_add_paperwork_item_nonce' );

		$process_id   = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$paperwork_id = isset( $_POST['paperwork_id'] ) ? absint( wp_unslash( $_POST['paperwork_id'] ) ) : 0;
		$result       = $this->service->add_item(
			$paperwork_id,
			array(
				'item_key'     => isset( $_POST['item_key'] ) ? wp_unslash( $_POST['item_key'] ) : '',
				'item_label'   => isset( $_POST['item_label'] ) ? wp_unslash( $_POST['item_label'] ) : '',
				'is_required'  => isset( $_POST['is_required'] ) ? wp_unslash( $_POST['is_required'] ) : 0,
				'is_completed' => isset( $_POST['is_completed'] ) ? wp_unslash( $_POST['is_completed'] ) : 0,
				'notes'        => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
				'sort_order'   => isset( $_POST['sort_order'] ) ? wp_unslash( $_POST['sort_order'] ) : 0,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'paperwork_error' );
		}

		$this->redirect_to_process( $process_id, 'paperwork_added' );
	}

	/**
	 * Handle update item.
	 *
	 * @return void
	 */
	protected function handle_update_item() {
		check_admin_referer( 'sm_update_paperwork_item', 'sm_update_paperwork_item_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$item_id    = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$result     = $this->service->update_item(
			$item_id,
			array(
				'paperwork_id' => isset( $_POST['paperwork_id'] ) ? wp_unslash( $_POST['paperwork_id'] ) : 0,
				'item_key'     => isset( $_POST['item_key'] ) ? wp_unslash( $_POST['item_key'] ) : '',
				'item_label'   => isset( $_POST['item_label'] ) ? wp_unslash( $_POST['item_label'] ) : '',
				'is_required'  => isset( $_POST['is_required'] ) ? wp_unslash( $_POST['is_required'] ) : 0,
				'is_completed' => isset( $_POST['is_completed'] ) ? wp_unslash( $_POST['is_completed'] ) : 0,
				'notes'        => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
				'sort_order'   => isset( $_POST['sort_order'] ) ? wp_unslash( $_POST['sort_order'] ) : 0,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'paperwork_error' );
		}

		$this->redirect_to_process( $process_id, 'paperwork_updated' );
	}

	/**
	 * Handle delete item.
	 *
	 * @return void
	 */
	protected function handle_delete_item() {
		check_admin_referer( 'sm_update_paperwork_item', 'sm_update_paperwork_item_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$item_id    = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
		$result     = $this->service->delete_item( $item_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'paperwork_error' );
		}

		$this->redirect_to_process( $process_id, 'paperwork_deleted' );
	}

	/**
	 * Redirect to process tab.
	 *
	 * @param int    $process_id Process ID.
	 * @param string $notice     Notice.
	 * @return void
	 */
	protected function redirect_to_process( $process_id, $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'super-mechanic-processes',
					'action'    => 'edit',
					'id'        => absint( $process_id ),
					'tab'       => 'paperwork',
					'sm_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Whether current screen is paperwork tab.
	 *
	 * @return bool
	 */
	protected function is_paperwork_screen() {
		return isset( $_GET['page'], $_GET['action'], $_GET['tab'] )
			&& 'super-mechanic-processes' === sanitize_key( wp_unslash( $_GET['page'] ) )
			&& 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) )
			&& 'paperwork' === sanitize_key( wp_unslash( $_GET['tab'] ) );
	}

	/**
	 * Store errors.
	 *
	 * @param WP_Error $error Error.
	 * @return void
	 */
	protected function store_errors( WP_Error $error ) {
		set_transient( $this->get_error_transient_key(), $error->get_error_messages(), MINUTE_IN_SECONDS );
	}

	/**
	 * Get transient key.
	 *
	 * @return string
	 */
	protected function get_error_transient_key() {
		return 'sm_paperwork_errors_' . get_current_user_id();
	}
}
