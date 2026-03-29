<?php
/**
 * Maintenance admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Maintenance;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles maintenance admin UI inside process edit screens.
 */
class Maintenance_Admin_Controller {
	/**
	 * Maintenance service.
	 *
	 * @var Maintenance_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Maintenance_Service|null $service Maintenance service.
	 */
	public function __construct( Maintenance_Service $service = null ) {
		$this->service = $service ? $service : new Maintenance_Service();
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
	 * Handle maintenance actions.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_maintenance_process_screen() ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para gestionar mantenimiento.', 'super-mechanic' ) );
		}

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		$operation = isset( $_POST['sm_maintenance_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_maintenance_operation'] ) ) : '';

		if ( 'save_maintenance' === $operation ) {
			$this->handle_save_maintenance();
		}

		if ( 'add_part' === $operation ) {
			$this->handle_add_part();
		}

		if ( 'remove_part' === $operation ) {
			$this->handle_remove_part();
		}

		if ( 'add_labor' === $operation ) {
			$this->handle_add_labor();
		}

		if ( 'remove_labor' === $operation ) {
			$this->handle_remove_labor();
		}
	}

	/**
	 * Render maintenance admin notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! $this->is_maintenance_process_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$map    = array(
			'maintenance_saved' => __( 'Datos de mantenimiento actualizados.', 'super-mechanic' ),
			'part_added'        => __( 'Repuesto agregado correctamente.', 'super-mechanic' ),
			'part_removed'      => __( 'Repuesto eliminado correctamente.', 'super-mechanic' ),
			'labor_added'       => __( 'Mano de obra agregada correctamente.', 'super-mechanic' ),
			'labor_removed'     => __( 'Mano de obra eliminada correctamente.', 'super-mechanic' ),
		);

		if ( isset( $map[ $notice ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $notice ] ) . '</p></div>';
		}

		if ( 'maintenance_error' === $notice ) {
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
	 * Render maintenance panel for a process.
	 *
	 * @param array<string, mixed> $process Process data.
	 * @return void
	 */
	public function render_process_panel( $process ) {
		$maintenance = $this->service->create_maintenance( absint( $process['id'] ) );
		if ( is_wp_error( $maintenance ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $maintenance->get_error_message() ) . '</p></div>';
			return;
		}

		$parts         = $this->service->get_parts( absint( $maintenance['id'] ) );
		$labor         = $this->service->get_labor( absint( $maintenance['id'] ) );
		$total_parts   = $this->service->calculate_total_parts( absint( $maintenance['id'] ) );
		$total_labor   = $this->service->calculate_total_labor( absint( $maintenance['id'] ) );
		$total_service = $this->service->calculate_total_service( absint( $maintenance['id'] ) );
		$mechanics     = $this->get_mechanics();

		echo '<h2>' . esc_html__( 'Diagnóstico', 'super-mechanic' ) . '</h2>';
		echo '<form method="post">';
		echo '<input type="hidden" name="sm_maintenance_operation" value="save_maintenance" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		wp_nonce_field( 'sm_save_maintenance', 'sm_maintenance_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="diagnosis">' . esc_html__( 'Diagnóstico', 'super-mechanic' ) . '</label></th><td><textarea name="diagnosis" id="diagnosis" class="large-text" rows="6">' . esc_textarea( $maintenance['diagnosis'] ) . '</textarea></td></tr>';
		echo '<tr><th scope="row"><label for="mechanic_id">' . esc_html__( 'Mecánico asignado', 'super-mechanic' ) . '</label></th><td><select name="mechanic_id" id="mechanic_id"><option value="0">' . esc_html__( 'Sin asignar', 'super-mechanic' ) . '</option>';
		foreach ( $mechanics as $mechanic ) {
			echo '<option value="' . esc_attr( absint( $mechanic->ID ) ) . '" ' . selected( absint( $maintenance['mechanic_id'] ), absint( $mechanic->ID ), false ) . '>' . esc_html( $mechanic->display_name ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="reassignment_note">' . esc_html__( 'Nota de traspaso (si cambia mecánico)', 'super-mechanic' ) . '</label></th><td><textarea name="reassignment_note" id="reassignment_note" class="large-text" rows="3"></textarea><p class="description">' . esc_html__( 'Si el trabajo ya inició, este campo es obligatorio para registrar qué se hizo antes de reasignar.', 'super-mechanic' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="estimated_hours">' . esc_html__( 'Horas estimadas', 'super-mechanic' ) . '</label></th><td><input type="number" step="0.01" min="0" name="estimated_hours" id="estimated_hours" value="' . esc_attr( $maintenance['estimated_hours'] ) . '" class="small-text" /></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Aprobación del cliente', 'super-mechanic' ) . '</th><td><label><input type="checkbox" name="client_approved" value="1" ' . checked( ! empty( $maintenance['client_approved'] ), true, false ) . ' /> ' . esc_html__( 'Cliente aprobó el servicio', 'super-mechanic' ) . '</label></td></tr>';
		echo '<tr><th scope="row"><label for="approved_at">' . esc_html__( 'Fecha de aprobación', 'super-mechanic' ) . '</label></th><td><input type="datetime-local" name="approved_at" id="approved_at" value="' . esc_attr( $this->format_datetime_for_input( $maintenance['approved_at'] ) ) . '" class="regular-text" /></td></tr>';
		echo '</table>';
		submit_button( __( 'Guardar mantenimiento', 'super-mechanic' ) );
		echo '</form>';

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Repuestos', 'super-mechanic' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Repuesto', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cantidad', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Precio unitario', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Notas', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $parts ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay repuestos cargados.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $parts as $part ) {
				echo '<tr><td>' . esc_html( $part['part_name'] ) . '</td><td>' . esc_html( $part['quantity'] ) . '</td><td>' . esc_html( $part['unit_price'] ) . '</td><td>' . esc_html( $part['total_price'] ) . '</td><td>' . esc_html( $part['notes'] ) . '</td><td>';
				echo '<form method="post" style="display:inline;">';
				echo '<input type="hidden" name="sm_maintenance_operation" value="remove_part" />';
				echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
				echo '<input type="hidden" name="part_id" value="' . esc_attr( absint( $part['id'] ) ) . '" />';
				wp_nonce_field( 'sm_remove_maintenance_part', 'sm_remove_part_nonce' );
				submit_button( __( 'Eliminar', 'super-mechanic' ), 'delete small', 'submit', false );
				echo '</form>';
				echo '</td></tr>';
			}
		}
		echo '</tbody></table>';
		echo '<p><strong>' . esc_html__( 'Total repuestos:', 'super-mechanic' ) . '</strong> ' . esc_html( number_format_i18n( $total_parts, 2 ) ) . '</p>';
		echo '<form method="post">';
		echo '<input type="hidden" name="sm_maintenance_operation" value="add_part" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		wp_nonce_field( 'sm_add_maintenance_part', 'sm_add_part_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="part_name">' . esc_html__( 'Repuesto', 'super-mechanic' ) . '</label></th><td><input type="text" name="part_name" id="part_name" class="regular-text" required /></td></tr>';
		echo '<tr><th scope="row"><label for="part_quantity">' . esc_html__( 'Cantidad', 'super-mechanic' ) . '</label></th><td><input type="number" step="0.01" min="0.01" name="quantity" id="part_quantity" class="small-text" required /></td></tr>';
		echo '<tr><th scope="row"><label for="part_unit_price">' . esc_html__( 'Precio unitario', 'super-mechanic' ) . '</label></th><td><input type="number" step="0.01" min="0" name="unit_price" id="part_unit_price" class="small-text" required /></td></tr>';
		echo '<tr><th scope="row"><label for="part_notes">' . esc_html__( 'Notas', 'super-mechanic' ) . '</label></th><td><textarea name="notes" id="part_notes" class="large-text" rows="3"></textarea></td></tr>';
		echo '</table>';
		submit_button( __( 'Agregar repuesto', 'super-mechanic' ) );
		echo '</form>';

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Mano de obra', 'super-mechanic' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Descripción', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Horas', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tarifa', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $labor ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay mano de obra cargada.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $labor as $row ) {
				echo '<tr><td>' . esc_html( $row['description'] ) . '</td><td>' . esc_html( $row['hours'] ) . '</td><td>' . esc_html( $row['hour_rate'] ) . '</td><td>' . esc_html( $row['total_price'] ) . '</td><td>';
				echo '<form method="post" style="display:inline;">';
				echo '<input type="hidden" name="sm_maintenance_operation" value="remove_labor" />';
				echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
				echo '<input type="hidden" name="labor_id" value="' . esc_attr( absint( $row['id'] ) ) . '" />';
				wp_nonce_field( 'sm_remove_maintenance_labor', 'sm_remove_labor_nonce' );
				submit_button( __( 'Eliminar', 'super-mechanic' ), 'delete small', 'submit', false );
				echo '</form>';
				echo '</td></tr>';
			}
		}
		echo '</tbody></table>';
		echo '<p><strong>' . esc_html__( 'Total mano de obra:', 'super-mechanic' ) . '</strong> ' . esc_html( number_format_i18n( $total_labor, 2 ) ) . '</p>';
		echo '<form method="post">';
		echo '<input type="hidden" name="sm_maintenance_operation" value="add_labor" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( absint( $process['id'] ) ) . '" />';
		wp_nonce_field( 'sm_add_maintenance_labor', 'sm_add_labor_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="labor_description">' . esc_html__( 'Descripción', 'super-mechanic' ) . '</label></th><td><input type="text" name="description" id="labor_description" class="regular-text" required /></td></tr>';
		echo '<tr><th scope="row"><label for="labor_hours">' . esc_html__( 'Horas', 'super-mechanic' ) . '</label></th><td><input type="number" step="0.01" min="0.01" name="hours" id="labor_hours" class="small-text" required /></td></tr>';
		echo '<tr><th scope="row"><label for="labor_rate">' . esc_html__( 'Tarifa por hora', 'super-mechanic' ) . '</label></th><td><input type="number" step="0.01" min="0" name="hour_rate" id="labor_rate" class="small-text" required /></td></tr>';
		echo '</table>';
		submit_button( __( 'Agregar mano de obra', 'super-mechanic' ) );
		echo '</form>';

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Resumen', 'super-mechanic' ) . '</h2>';
		echo '<p><strong>' . esc_html__( 'Total servicio:', 'super-mechanic' ) . '</strong> ' . esc_html( number_format_i18n( $total_service, 2 ) ) . '</p>';
	}

	/**
	 * Handle save maintenance.
	 *
	 * @return void
	 */
	protected function handle_save_maintenance() {
		check_admin_referer( 'sm_save_maintenance', 'sm_maintenance_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$result     = $this->service->update_maintenance(
			$process_id,
			array(
				'diagnosis'       => isset( $_POST['diagnosis'] ) ? wp_unslash( $_POST['diagnosis'] ) : '',
				'client_approved' => isset( $_POST['client_approved'] ) ? wp_unslash( $_POST['client_approved'] ) : 0,
				'approved_at'     => isset( $_POST['approved_at'] ) ? wp_unslash( $_POST['approved_at'] ) : '',
				'mechanic_id'     => isset( $_POST['mechanic_id'] ) ? wp_unslash( $_POST['mechanic_id'] ) : 0,
				'reassignment_note' => isset( $_POST['reassignment_note'] ) ? wp_unslash( $_POST['reassignment_note'] ) : '',
				'estimated_hours' => isset( $_POST['estimated_hours'] ) ? wp_unslash( $_POST['estimated_hours'] ) : 0,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'maintenance_error' );
		}

		$this->redirect_to_process( $process_id, 'maintenance_saved' );
	}

	/**
	 * Handle add part.
	 *
	 * @return void
	 */
	protected function handle_add_part() {
		check_admin_referer( 'sm_add_maintenance_part', 'sm_add_part_nonce' );

		$process_id   = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$maintenance  = $this->service->create_maintenance( $process_id );
		if ( is_wp_error( $maintenance ) ) {
			$this->store_errors( $maintenance );
			$this->redirect_to_process( $process_id, 'maintenance_error' );
		}

		$result = $this->service->add_part(
			absint( $maintenance['id'] ),
			array(
				'part_name'  => isset( $_POST['part_name'] ) ? wp_unslash( $_POST['part_name'] ) : '',
				'quantity'   => isset( $_POST['quantity'] ) ? wp_unslash( $_POST['quantity'] ) : 0,
				'unit_price' => isset( $_POST['unit_price'] ) ? wp_unslash( $_POST['unit_price'] ) : 0,
				'notes'      => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'maintenance_error' );
		}

		$this->redirect_to_process( $process_id, 'part_added' );
	}

	/**
	 * Handle remove part.
	 *
	 * @return void
	 */
	protected function handle_remove_part() {
		check_admin_referer( 'sm_remove_maintenance_part', 'sm_remove_part_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$part_id    = isset( $_POST['part_id'] ) ? absint( wp_unslash( $_POST['part_id'] ) ) : 0;
		$result     = $this->service->remove_part( $part_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'maintenance_error' );
		}

		$this->redirect_to_process( $process_id, 'part_removed' );
	}

	/**
	 * Handle add labor.
	 *
	 * @return void
	 */
	protected function handle_add_labor() {
		check_admin_referer( 'sm_add_maintenance_labor', 'sm_add_labor_nonce' );

		$process_id   = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$maintenance  = $this->service->create_maintenance( $process_id );
		if ( is_wp_error( $maintenance ) ) {
			$this->store_errors( $maintenance );
			$this->redirect_to_process( $process_id, 'maintenance_error' );
		}

		$result = $this->service->add_labor(
			absint( $maintenance['id'] ),
			array(
				'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'hours'       => isset( $_POST['hours'] ) ? wp_unslash( $_POST['hours'] ) : 0,
				'hour_rate'   => isset( $_POST['hour_rate'] ) ? wp_unslash( $_POST['hour_rate'] ) : 0,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'maintenance_error' );
		}

		$this->redirect_to_process( $process_id, 'labor_added' );
	}

	/**
	 * Handle remove labor.
	 *
	 * @return void
	 */
	protected function handle_remove_labor() {
		check_admin_referer( 'sm_remove_maintenance_labor', 'sm_remove_labor_nonce' );

		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$labor_id   = isset( $_POST['labor_id'] ) ? absint( wp_unslash( $_POST['labor_id'] ) ) : 0;
		$result     = $this->service->remove_labor( $labor_id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect_to_process( $process_id, 'maintenance_error' );
		}

		$this->redirect_to_process( $process_id, 'labor_removed' );
	}

	/**
	 * Get mechanics list.
	 *
	 * @return array<int, \WP_User>
	 */
	protected function get_mechanics() {
		return get_users(
			array(
				'role__in' => array( 'sm_mechanic', 'sm_admin', 'administrator' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		);
	}

	/**
	 * Redirect back to process maintenance tab.
	 *
	 * @param int    $process_id Process ID.
	 * @param string $notice     Notice slug.
	 * @return void
	 */
	protected function redirect_to_process( $process_id, $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'super-mechanic-processes',
					'action'    => 'edit',
					'id'        => absint( $process_id ),
					'tab'       => 'maintenance',
					'sm_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Whether the current screen is a maintenance process edit screen.
	 *
	 * @return bool
	 */
	protected function is_maintenance_process_screen() {
		return isset( $_GET['page'], $_GET['action'], $_GET['tab'] )
			&& 'super-mechanic-processes' === sanitize_key( wp_unslash( $_GET['page'] ) )
			&& 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) )
			&& 'maintenance' === sanitize_key( wp_unslash( $_GET['tab'] ) );
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
	 * Get error transient key.
	 *
	 * @return string
	 */
	protected function get_error_transient_key() {
		return 'sm_maintenance_errors_' . get_current_user_id();
	}

	/**
	 * Format datetime for input.
	 *
	 * @param string|null $value Datetime value.
	 * @return string
	 */
	protected function format_datetime_for_input( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? '' : gmdate( 'Y-m-d\TH:i', $timestamp );
	}
}
