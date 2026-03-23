<?php
/**
 * Pre-delivery admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Pre_Delivery;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles pre-delivery UI inside process edit screens.
 */
class Pre_Delivery_Admin_Controller {
	/**
	 * Service.
	 *
	 * @var Pre_Delivery_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Pre_Delivery_Service|null $service Service.
	 */
	public function __construct( Pre_Delivery_Service $service = null ) {
		$this->service = $service ? $service : new Pre_Delivery_Service();
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
	 * Maybe handle form actions.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_pre_delivery_screen() ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para gestionar pre-entrega.', 'super-mechanic' ) );
		}

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		$operation = isset( $_POST['sm_pre_delivery_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_pre_delivery_operation'] ) ) : '';

		if ( 'save_pre_delivery' === $operation ) {
			$this->handle_save();
		}
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! $this->is_pre_delivery_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';

		if ( 'pre_delivery_saved' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Datos de pre-entrega actualizados.', 'super-mechanic' ) . '</p></div>';
		}

		if ( 'pre_delivery_error' === $notice ) {
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

		$users = get_users(
			array(
				'role__in' => array( 'sm_admin', 'administrator', 'sm_mechanic' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		);

		echo '<h2>' . esc_html__( 'Pre-Delivery', 'super-mechanic' ) . '</h2>';
		echo '<form method="post">';
		echo '<input type="hidden" name="sm_pre_delivery_operation" value="save_pre_delivery" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		wp_nonce_field( 'sm_save_pre_delivery', 'sm_pre_delivery_nonce' );
		echo '<table class="form-table" role="presentation">';
		$this->render_user_select( $row['assigned_user_id'], $users );
		$this->render_checkbox_row( 'insurance_required', __( 'Seguro requerido', 'super-mechanic' ), ! empty( $row['insurance_required'] ) );
		$this->render_checkbox_row( 'insurance_completed', __( 'Seguro completado', 'super-mechanic' ), ! empty( $row['insurance_completed'] ) );
		$this->render_readonly_row( __( 'Fecha seguro', 'super-mechanic' ), $row['insurance_completed_at'] );
		$this->render_checkbox_row( 'plate_required', __( 'Placa requerida', 'super-mechanic' ), ! empty( $row['plate_required'] ) );
		$this->render_checkbox_row( 'plate_completed', __( 'Placa completada', 'super-mechanic' ), ! empty( $row['plate_completed'] ) );
		$this->render_readonly_row( __( 'Fecha placa', 'super-mechanic' ), $row['plate_completed_at'] );
		$this->render_checkbox_row( 'final_review_required', __( 'Revisión final requerida', 'super-mechanic' ), ! empty( $row['final_review_required'] ) );
		$this->render_checkbox_row( 'final_review_completed', __( 'Revisión final completada', 'super-mechanic' ), ! empty( $row['final_review_completed'] ) );
		$this->render_readonly_row( __( 'Fecha revisión final', 'super-mechanic' ), $row['final_review_completed_at'] );
		$this->render_checkbox_row( 'delivery_ready', __( 'Listo para entrega', 'super-mechanic' ), ! empty( $row['delivery_ready'] ) );
		$this->render_readonly_row( __( 'Fecha listo para entrega', 'super-mechanic' ), $row['delivery_ready_at'] );
		echo '<tr><th scope="row"><label for="pre_delivery_notes">' . esc_html__( 'Notas', 'super-mechanic' ) . '</label></th><td><textarea name="notes" id="pre_delivery_notes" class="large-text" rows="6">' . esc_textarea( $row['notes'] ) . '</textarea></td></tr>';
		echo '</table>';
		submit_button( __( 'Guardar pre-entrega', 'super-mechanic' ) );
		echo '</form>';
	}

	/**
	 * Handle save.
	 *
	 * @return void
	 */
	protected function handle_save() {
		check_admin_referer( 'sm_save_pre_delivery', 'sm_pre_delivery_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$result     = $this->service->save_pre_delivery(
			$process_id,
			array(
				'assigned_user_id'       => isset( $_POST['assigned_user_id'] ) ? wp_unslash( $_POST['assigned_user_id'] ) : 0,
				'insurance_required'     => isset( $_POST['insurance_required'] ) ? wp_unslash( $_POST['insurance_required'] ) : 0,
				'insurance_completed'    => isset( $_POST['insurance_completed'] ) ? wp_unslash( $_POST['insurance_completed'] ) : 0,
				'plate_required'         => isset( $_POST['plate_required'] ) ? wp_unslash( $_POST['plate_required'] ) : 0,
				'plate_completed'        => isset( $_POST['plate_completed'] ) ? wp_unslash( $_POST['plate_completed'] ) : 0,
				'final_review_required'  => isset( $_POST['final_review_required'] ) ? wp_unslash( $_POST['final_review_required'] ) : 0,
				'final_review_completed' => isset( $_POST['final_review_completed'] ) ? wp_unslash( $_POST['final_review_completed'] ) : 0,
				'delivery_ready'         => isset( $_POST['delivery_ready'] ) ? wp_unslash( $_POST['delivery_ready'] ) : 0,
				'notes'                  => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'pre_delivery_error' );
		}

		$this->redirect_to_process( $process_id, 'pre_delivery_saved' );
	}

	/**
	 * Render user selector.
	 *
	 * @param int                $selected_user_id Selected user ID.
	 * @param array<int,WP_User> $users            Users.
	 * @return void
	 */
	protected function render_user_select( $selected_user_id, $users ) {
		echo '<tr><th scope="row"><label for="assigned_user_id">' . esc_html__( 'Responsable asignado', 'super-mechanic' ) . '</label></th><td><select name="assigned_user_id" id="assigned_user_id"><option value="0">' . esc_html__( 'Sin asignar', 'super-mechanic' ) . '</option>';
		foreach ( $users as $user ) {
			echo '<option value="' . esc_attr( absint( $user->ID ) ) . '" ' . selected( absint( $selected_user_id ), absint( $user->ID ), false ) . '>' . esc_html( $user->display_name ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	/**
	 * Render checkbox row.
	 *
	 * @param string $field   Field name.
	 * @param string $label   Label.
	 * @param bool   $checked Checked.
	 * @return void
	 */
	protected function render_checkbox_row( $field, $label, $checked ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $field ) . '" value="1" ' . checked( $checked, true, false ) . ' /> ' . esc_html__( 'Sí', 'super-mechanic' ) . '</label></td></tr>';
	}

	/**
	 * Render readonly datetime row.
	 *
	 * @param string      $label Label.
	 * @param string|null $value Value.
	 * @return void
	 */
	protected function render_readonly_row( $label, $value ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><input type="text" class="regular-text" value="' . esc_attr( $value ? $value : '' ) . '" readonly /></td></tr>';
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
					'tab'       => 'pre-delivery',
					'sm_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Whether current screen is pre-delivery tab.
	 *
	 * @return bool
	 */
	protected function is_pre_delivery_screen() {
		return isset( $_GET['page'], $_GET['action'], $_GET['tab'] )
			&& 'super-mechanic-processes' === sanitize_key( wp_unslash( $_GET['page'] ) )
			&& 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) )
			&& 'pre-delivery' === sanitize_key( wp_unslash( $_GET['tab'] ) );
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
		return 'sm_pre_delivery_errors_' . get_current_user_id();
	}
}
