<?php
/**
 * Vehicle admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Vehicles;

use Super_Mechanic\Appointments\Appointment_Service;
use Super_Mechanic\Maintenance\Maintenance_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Relations\Client_Vehicle_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles vehicle admin flows.
 */
class Vehicle_Admin_Controller {
	/**
	 * Vehicle service.
	 *
	 * @var Vehicle_Service
	 */
	protected $service;
	protected $process_service;
	protected $client_vehicle_service;
	protected $appointment_service;
	protected $maintenance_service;

	/**
	 * Constructor.
	 *
	 * @param Vehicle_Service|null $service Vehicle service.
	 */
	public function __construct( Vehicle_Service $service = null ) {
		$this->service                = $service ? $service : new Vehicle_Service();
		$this->process_service        = new Process_Service();
		$this->client_vehicle_service = new Client_Vehicle_Service();
		$this->appointment_service    = new Appointment_Service();
		$this->maintenance_service    = new Maintenance_Service();
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
	 * Process vehicle actions before any admin output.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_vehicles_screen() ) {
			return;
		}

		$this->ensure_permissions();
		$this->handle_actions();
	}

	/**
	 * Render the vehicles admin page.
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
			$vehicle = $this->service->get_vehicle( $id );

			if ( empty( $vehicle ) ) {
				wp_die( esc_html__( 'El vehículo solicitado no existe.', 'super-mechanic' ) );
			}

			$this->render_form_page( $vehicle, true );
			return;
		}

		if ( 'view' === $action ) {
			$vehicle = $this->service->get_vehicle( $id );

			if ( empty( $vehicle ) ) {
				wp_die( esc_html__( 'El vehículo solicitado no existe.', 'super-mechanic' ) );
			}

			$this->render_detail_page( $vehicle );
			return;
		}

		$this->render_list_page();
	}

	/**
	 * Render admin notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! $this->is_vehicles_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$count  = isset( $_GET['deleted_count'] ) ? absint( $_GET['deleted_count'] ) : 0;

		if ( 'created' === $notice ) {
			$this->render_notice( __( 'Vehículo creado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'updated' === $notice ) {
			$this->render_notice( __( 'Vehículo actualizado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'deleted' === $notice ) {
			$this->render_notice( __( 'Vehículo eliminado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'bulk_deleted' === $notice ) {
			$this->render_notice(
				sprintf(
					/* translators: %d: number of deleted vehicles. */
					__( '%d vehículos eliminados correctamente.', 'super-mechanic' ),
					$count
				),
				'success'
			);
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
	 * Render the list page.
	 *
	 * @return void
	 */
	protected function render_list_page() {
		$list_table = new Vehicle_List_Table( $this->service );
		$list_table->prepare_items();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Vehículos', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Consulta, crea y organiza los vehículos asociados al taller con la misma capa visual del panel moderno.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ) . '" class="button button-primary">' . esc_html__( 'Añadir nuevo', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="sm-card sm-filter-card sm-section">';
		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="super-mechanic-vehicles" />';
		wp_nonce_field( 'sm_bulk_delete_vehicles', 'sm_bulk_delete_nonce' );
		$list_table->search_box( __( 'Buscar vehículos', 'super-mechanic' ), 'sm-vehicles' );
		echo '<div class="sm-table-wrap sm-list-table-wrap">';
		$list_table->display();
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render the form page.
	 *
	 * @param array<string, mixed> $vehicle Vehicle data.
	 * @param bool                 $is_edit Whether editing.
	 * @return void
	 */
	protected function render_form_page( $vehicle = array(), $is_edit = false ) {
		$defaults = array(
			'id'        => 0,
			'client_id' => isset( $_GET['client_id'] ) ? absint( wp_unslash( $_GET['client_id'] ) ) : 0,
			'vin'       => '',
			'plate'     => '',
			'brand'     => '',
			'model'     => '',
			'year'      => '',
			'color'     => '',
			'notes'     => '',
		);

		$stored = get_transient( $this->get_form_transient_key() );
		if ( is_array( $stored ) ) {
			$vehicle = array_merge( $vehicle, $stored );
			delete_transient( $this->get_form_transient_key() );
		}

		$vehicle = wp_parse_args( $vehicle, $defaults );
		$title   = $is_edit ? __( 'Editar vehículo', 'super-mechanic' ) : __( 'Nuevo vehículo', 'super-mechanic' );
		$clients = $this->service->get_client_options();
		$return  = $this->get_process_return_context();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Mantén la ficha del vehículo organizada sin alterar relaciones, nonces ni acciones existentes.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url() ) . '" class="button button-secondary">' . esc_html__( 'Volver al listado', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="sm-card sm-form-card">';
		echo '<form method="post" action="' . esc_url( $this->get_page_url( $is_edit ? array( 'action' => 'edit', 'id' => absint( $vehicle['id'] ) ) : array( 'action' => 'new' ) ) ) . '">';
		wp_nonce_field( 'sm_save_vehicle', 'sm_vehicle_nonce' );
		echo '<input type="hidden" name="sm_vehicle_operation" value="' . esc_attr( $is_edit ? 'update' : 'create' ) . '" />';
		echo '<input type="hidden" name="vehicle_id" value="' . esc_attr( absint( $vehicle['id'] ) ) . '" />';
		echo '<input type="hidden" name="return_page" value="' . esc_attr( $return['page'] ) . '" />';
		echo '<input type="hidden" name="return_action" value="' . esc_attr( $return['action'] ) . '" />';
		echo '<input type="hidden" name="return_process_id" value="' . esc_attr( $return['process_id'] ) . '" />';
		echo '<input type="hidden" name="return_client_id" value="' . esc_attr( $return['client_id'] ) . '" />';
		echo '<table class="form-table" role="presentation">';
		$this->render_client_select_field( $vehicle['client_id'], $clients );
		$this->render_text_field( 'vin', __( 'VIN', 'super-mechanic' ), $vehicle['vin'] );
		$this->render_text_field( 'plate', __( 'Placa', 'super-mechanic' ), $vehicle['plate'] );
		$this->render_text_field( 'brand', __( 'Marca', 'super-mechanic' ), $vehicle['brand'], true );
		$this->render_text_field( 'model', __( 'Modelo', 'super-mechanic' ), $vehicle['model'], true );
		$this->render_number_field( 'year', __( 'Año', 'super-mechanic' ), $vehicle['year'] );
		$this->render_text_field( 'color', __( 'Color', 'super-mechanic' ), $vehicle['color'] );
		$this->render_textarea_field( 'notes', __( 'Notas', 'super-mechanic' ), $vehicle['notes'] );
		echo '</table>';
		echo '<div class="sm-form-actions">';
		submit_button( $is_edit ? __( 'Actualizar vehículo', 'super-mechanic' ) : __( 'Crear vehículo', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render vehicle detail page.
	 *
	 * @param array<string, mixed> $vehicle Vehicle data.
	 * @return void
	 */
	protected function render_detail_page( array $vehicle ) {
		$vehicle_id      = absint( $vehicle['id'] );
		$related_clients = $this->client_vehicle_service->get_vehicle_clients(
			$vehicle_id,
			array(
				'per_page' => 100,
			)
		);
		$processes       = $this->process_service->get_processes(
			array(
				'vehicle_id' => $vehicle_id,
				'per_page'   => 100,
				'orderby'    => 'created_at',
				'order'      => 'DESC',
			)
		);
		$active_count    = 0;
		$active_process  = $this->process_service->get_active_vehicle_process( $vehicle_id );
		$appointments    = $this->appointment_service->get_appointments(
			array(
				'vehicle_id' => $vehicle_id,
				'per_page'   => 100,
				'page'       => 1,
				'orderby'    => 'start_at',
				'order'      => 'DESC',
			)
		);
		$maintenance_timeline = array();

		foreach ( $processes as $process ) {
			if ( $this->process_service->is_active_status( isset( $process['status'] ) ? $process['status'] : '' ) ) {
				++$active_count;
			}

			if ( isset( $process['process_type'] ) && 'maintenance' === $process['process_type'] ) {
				$maintenance = $this->maintenance_service->get_maintenance_by_process( absint( $process['id'] ) );
				if ( is_array( $maintenance ) ) {
					$maintenance_timeline[] = array(
						'process_id'      => absint( $process['id'] ),
						'process_title'   => isset( $process['title'] ) ? (string) $process['title'] : '',
						'status'          => isset( $process['status'] ) ? (string) $process['status'] : '',
						'diagnosis'       => isset( $maintenance['diagnosis'] ) ? (string) $maintenance['diagnosis'] : '',
						'estimated_hours' => isset( $maintenance['estimated_hours'] ) ? (float) $maintenance['estimated_hours'] : 0.0,
						'updated_at'      => isset( $maintenance['updated_at'] ) ? (string) $maintenance['updated_at'] : '',
					);
				}
			}
		}

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html( $this->format_vehicle_label( $vehicle ) ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Detalle del vehículo, cliente principal, procesos relacionados e historial de relaciones disponible en la arquitectura actual.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'edit', 'id' => $vehicle_id ) ) ) . '" class="button button-primary">' . esc_html__( 'Editar vehículo', 'super-mechanic' ) . '</a> ';
		echo '<a href="' . esc_url( $this->get_page_url() ) . '" class="button button-secondary">' . esc_html__( 'Volver al listado', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';

		echo '<div class="sm-grid sm-grid-two sm-section">';
		echo '<section class="sm-card">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Ficha del vehículo', 'super-mechanic' ) . '</h2></div>';
		echo '<table class="sm-table"><tbody>';
		$this->render_detail_row( __( 'ID', 'super-mechanic' ), (string) $vehicle_id );
		$this->render_detail_row( __( 'Cliente principal', 'super-mechanic' ), ! empty( $vehicle['client_name'] ) ? (string) $vehicle['client_name'] : __( 'Sin cliente asignado', 'super-mechanic' ) );
		$this->render_detail_row( __( 'VIN', 'super-mechanic' ), ! empty( $vehicle['vin'] ) ? (string) $vehicle['vin'] : __( 'Sin VIN', 'super-mechanic' ) );
		$this->render_detail_row( __( 'Placa', 'super-mechanic' ), ! empty( $vehicle['plate'] ) ? (string) $vehicle['plate'] : __( 'Sin placa', 'super-mechanic' ) );
		$this->render_detail_row( __( 'Marca', 'super-mechanic' ), ! empty( $vehicle['brand'] ) ? (string) $vehicle['brand'] : '-' );
		$this->render_detail_row( __( 'Modelo', 'super-mechanic' ), ! empty( $vehicle['model'] ) ? (string) $vehicle['model'] : '-' );
		$this->render_detail_row( __( 'Año', 'super-mechanic' ), ! empty( $vehicle['year'] ) ? (string) $vehicle['year'] : '-' );
		$this->render_detail_row( __( 'Color', 'super-mechanic' ), ! empty( $vehicle['color'] ) ? (string) $vehicle['color'] : '-' );
		$this->render_detail_row( __( 'Notas', 'super-mechanic' ), ! empty( $vehicle['notes'] ) ? (string) $vehicle['notes'] : __( 'Sin notas', 'super-mechanic' ) );
		echo '</tbody></table>';
		echo '</section>';

		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Contexto operativo', 'super-mechanic' ) . '</h2></div>';
		echo '<p><strong>' . esc_html__( 'Proceso activo actual', 'super-mechanic' ) . ':</strong> ' . esc_html( $this->get_active_process_summary( $active_process ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Estado actual visible', 'super-mechanic' ) . ':</strong> ' . esc_html( is_array( $active_process ) && ! empty( $active_process['status'] ) ? $this->humanize_key( $active_process['status'] ) : __( 'Sin proceso activo', 'super-mechanic' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Procesos activos', 'super-mechanic' ) . ':</strong> ' . esc_html( $active_count ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Procesos totales', 'super-mechanic' ) . ':</strong> ' . esc_html( count( $processes ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Citas relacionadas', 'super-mechanic' ) . ':</strong> ' . esc_html( count( $appointments ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Registros maintenance', 'super-mechanic' ) . ':</strong> ' . esc_html( count( $maintenance_timeline ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Historial de relaciones', 'super-mechanic' ) . ':</strong> ' . esc_html( is_array( $related_clients ) ? count( $related_clients ) : 0 ) . '</p>';
		echo '<p>' . esc_html__( 'Los campos de vencimiento de seguro, placa e inspección no forman parte del runtime activo actual, así que no se exponen ni se validan en esta subfase.', 'super-mechanic' ) . '</p>';
		echo '</section>';
		echo '</div>';

		echo '<section class="sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Procesos relacionados', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Título', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Apertura', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Objetivo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Finalización', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cliente', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $processes ) ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No hay procesos relacionados para este vehículo.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $processes as $process ) {
				$view_url = add_query_arg(
					array(
						'page'   => 'super-mechanic-processes',
						'action' => 'edit',
						'id'     => absint( $process['id'] ),
					),
					admin_url( 'admin.php' )
				);
				echo '<tr>';
				echo '<td>' . esc_html( absint( $process['id'] ) ) . '</td>';
				echo '<td>' . esc_html( $process['title'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $process['process_type'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $process['status'] ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $process['opened_at'] ) ? $process['opened_at'] : '-' ) . '</td>';
				echo '<td>' . esc_html( ! empty( $process['due_date'] ) ? $process['due_date'] : '-' ) . '</td>';
				echo '<td>' . esc_html( ! empty( $process['completed_at'] ) ? $process['completed_at'] : '-' ) . '</td>';
				echo '<td>' . esc_html( ! empty( $process['client_name'] ) ? $process['client_name'] : __( 'Sin asignar', 'super-mechanic' ) ) . '</td>';
				echo '<td><a href="' . esc_url( $view_url ) . '">' . esc_html__( 'Abrir proceso', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Citas relacionadas', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cliente', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Notas', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $appointments ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay citas relacionadas para este vehículo.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $appointments as $appointment ) {
				echo '<tr>';
				echo '<td>#' . esc_html( absint( $appointment['id'] ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $appointment['start_at'] ) ? (string) $appointment['start_at'] : ( ! empty( $appointment['appointment_date'] ) ? (string) $appointment['appointment_date'] : '-' ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( isset( $appointment['appointment_status'] ) ? (string) $appointment['appointment_status'] : '' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $appointment['client_name'] ) ? (string) $appointment['client_name'] : __( 'Sin cliente', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $appointment['notes'] ) ? (string) $appointment['notes'] : '-' ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Timeline maintenance', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Proceso', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Diagnóstico', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Horas estimadas', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Actualizado', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $maintenance_timeline ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay datos de mantenimiento registrados para este vehículo.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $maintenance_timeline as $maintenance_row ) {
				echo '<tr>';
				echo '<td>#' . esc_html( absint( $maintenance_row['process_id'] ) ) . ' ' . esc_html( $maintenance_row['process_title'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $maintenance_row['status'] ) ) . '</td>';
				echo '<td>' . esc_html( '' !== trim( $maintenance_row['diagnosis'] ) ? $maintenance_row['diagnosis'] : '-' ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $maintenance_row['estimated_hours'], 2 ) ) . '</td>';
				echo '<td>' . esc_html( '' !== trim( $maintenance_row['updated_at'] ) ? $maintenance_row['updated_at'] : '-' ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Historial relacionado', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Cliente', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo de vínculo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Inicio', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Fin', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( ! is_array( $related_clients ) || empty( $related_clients ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No hay historial de relaciones registrado para este vehículo.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $related_clients as $relation ) {
				$client_label = trim(
					sprintf(
						'%s %s',
						isset( $relation['first_name'] ) ? (string) $relation['first_name'] : '',
						isset( $relation['last_name'] ) ? (string) $relation['last_name'] : ''
					)
				);
				if ( '' === $client_label && ! empty( $relation['email'] ) ) {
					$client_label = (string) $relation['email'];
				}
				echo '<tr>';
				echo '<td>' . esc_html( '' !== $client_label ? $client_label : __( 'Cliente sin identificar', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $relation['ownership_type'] ) ? (string) $relation['ownership_type'] : '-' ) . '</td>';
				echo '<td>' . esc_html( ! empty( $relation['start_date'] ) ? (string) $relation['start_date'] : '-' ) . '</td>';
				echo '<td>' . esc_html( ! empty( $relation['end_date'] ) ? (string) $relation['end_date'] : __( 'Activa', 'super-mechanic' ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
		echo '</div>';
	}

	/**
	 * Handle incoming actions.
	 *
	 * @return void
	 */
	protected function handle_actions() {
		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			$operation   = isset( $_POST['sm_vehicle_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_vehicle_operation'] ) ) : '';
			$bulk_action = $this->get_bulk_action();

			if ( 'create' === $operation || 'update' === $operation ) {
				$this->handle_save_action( 'update' === $operation );
			}

			if ( 'bulk-delete' === $bulk_action ) {
				$this->handle_bulk_delete_action();
			}
		}

		if ( isset( $_GET['action'] ) && 'delete' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$this->handle_delete_action();
		}
	}

	/**
	 * Handle save action.
	 *
	 * @param bool $is_update Whether updating.
	 * @return void
	 */
	protected function handle_save_action( $is_update ) {
		check_admin_referer( 'sm_save_vehicle', 'sm_vehicle_nonce' );

		$vehicle_id = isset( $_POST['vehicle_id'] ) ? absint( wp_unslash( $_POST['vehicle_id'] ) ) : 0;
		$data       = array(
			'client_id' => isset( $_POST['client_id'] ) ? wp_unslash( $_POST['client_id'] ) : 0,
			'vin'       => isset( $_POST['vin'] ) ? wp_unslash( $_POST['vin'] ) : '',
			'plate'     => isset( $_POST['plate'] ) ? wp_unslash( $_POST['plate'] ) : '',
			'brand'     => isset( $_POST['brand'] ) ? wp_unslash( $_POST['brand'] ) : '',
			'model'     => isset( $_POST['model'] ) ? wp_unslash( $_POST['model'] ) : '',
			'year'      => isset( $_POST['year'] ) ? wp_unslash( $_POST['year'] ) : '',
			'color'     => isset( $_POST['color'] ) ? wp_unslash( $_POST['color'] ) : '',
			'notes'     => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
		);

		$result = $is_update
			? $this->service->update_vehicle( $vehicle_id, $data )
			: $this->service->create_vehicle( $data );

		if ( is_wp_error( $result ) ) {
			$this->store_form_state( $data );
			$this->store_errors( $result );
			$redirect_args = $is_update
				? array(
					'action'    => 'edit',
					'id'        => $vehicle_id,
					'sm_notice' => 'error',
				)
				: array(
					'action'    => 'new',
					'sm_notice' => 'error',
				);
			$this->redirect( $redirect_args );
		}

		$return_context = $this->get_process_return_context_from_post();

		if ( ! $is_update && $return_context['is_process'] ) {
			$target_vehicle_id = absint( $result );
			$args              = array(
				'page'       => 'super-mechanic-processes',
				'action'     => $return_context['action'],
				'vehicle_id' => $target_vehicle_id,
			);

			if ( $return_context['process_id'] > 0 ) {
				$args['id'] = $return_context['process_id'];
			}

			if ( $return_context['client_id'] > 0 ) {
				$args['client_id'] = $return_context['client_id'];
			}

			$this->redirect_to_url( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		}

		$this->redirect( array( 'sm_notice' => $is_update ? 'updated' : 'created' ) );
	}

	/**
	 * Handle single delete action.
	 *
	 * @return void
	 */
	protected function handle_delete_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'sm_delete_vehicle_' . $id );

		$result = $this->service->delete_vehicle( $id );

		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'sm_notice' => 'deleted' ) );
	}

	/**
	 * Handle bulk delete action.
	 *
	 * @return void
	 */
	protected function handle_bulk_delete_action() {
		check_admin_referer( 'sm_bulk_delete_vehicles', 'sm_bulk_delete_nonce' );

		$ids = isset( $_POST['vehicle_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['vehicle_ids'] ) ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			$this->store_errors( new WP_Error( 'sm_no_vehicles_selected', __( 'Selecciona al menos un vehículo para eliminar.', 'super-mechanic' ) ) );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			$result = $this->service->delete_vehicle( $id );
			if ( ! is_wp_error( $result ) ) {
				++$deleted;
			}
		}

		$this->redirect(
			array(
				'sm_notice'     => 'bulk_deleted',
				'deleted_count' => $deleted,
			)
		);
	}

	/**
	 * Render a standard notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 * @return void
	 */
	protected function render_notice( $message, $type ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible sm-notice-card"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Render client selector.
	 *
	 * @param int                                 $selected_client_id Selected client ID.
	 * @param array<int, array<string, mixed>>    $clients            Client options.
	 * @return void
	 */
	protected function render_client_select_field( $selected_client_id, $clients ) {
		echo '<tr>';
		echo '<th scope="row"><label for="client_id">' . esc_html__( 'Cliente principal', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="client_id" id="client_id" required>';
		echo '<option value="0">' . esc_html__( 'Selecciona un cliente', 'super-mechanic' ) . '</option>';

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
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="text" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text"' . ( $required ? ' required' : '' ) . ' /></td>';
		echo '</tr>';
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
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="number" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="small-text" min="1900" max="' . esc_attr( (string) ( (int) gmdate( 'Y' ) + 1 ) ) . '" /></td>';
		echo '</tr>';
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
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="large-text" rows="5">' . esc_textarea( $value ) . '</textarea></td>';
		echo '</tr>';
	}

	/**
	 * Render detail table row.
	 *
	 * @param string $label Row label.
	 * @param string $value Row value.
	 * @return void
	 */
	protected function render_detail_row( $label, $value ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}

	protected function get_active_process_summary( $active_process ) {
		if ( ! is_array( $active_process ) || empty( $active_process['id'] ) ) {
			return __( 'Sin proceso activo', 'super-mechanic' );
		}

		return sprintf(
			__( '#%1$d %2$s (%3$s)', 'super-mechanic' ),
			absint( $active_process['id'] ),
			$this->humanize_key( $active_process['process_type'] ),
			$this->humanize_key( $active_process['status'] )
		);
	}

	/**
	 * Humanize internal keys.
	 *
	 * @param string $value Raw key.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/**
	 * Format a vehicle label.
	 *
	 * @param array<string, mixed> $vehicle Vehicle-like row.
	 * @return string
	 */
	protected function format_vehicle_label( $vehicle ) {
		$make  = ! empty( $vehicle['brand'] ) ? $vehicle['brand'] : ( ! empty( $vehicle['make'] ) ? $vehicle['make'] : '' );
		$model = ! empty( $vehicle['model'] ) ? $vehicle['model'] : '';
		$plate = ! empty( $vehicle['plate'] ) ? $vehicle['plate'] : '';
		$label = trim( $make . ' ' . $model );

		if ( $plate ) {
			$label .= ' - ' . $plate;
		}

		return $label ? $label : __( 'Vehículo sin identificar', 'super-mechanic' );
	}

	/**
	 * Ensure the current user can access the module.
	 *
	 * @return void
	 */
	protected function ensure_permissions() {
		if ( ! current_user_can( 'sm_manage_vehicles' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para gestionar vehículos.', 'super-mechanic' ) );
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
	 * Store form state.
	 *
	 * @param array<string, mixed> $data Form data.
	 * @return void
	 */
	protected function store_form_state( $data ) {
		set_transient( $this->get_form_transient_key(), $data, MINUTE_IN_SECONDS );
	}

	/**
	 * Store error messages.
	 *
	 * @param WP_Error $error Error object.
	 * @return void
	 */
	protected function store_errors( WP_Error $error ) {
		set_transient( $this->get_error_transient_key(), $error->get_error_messages(), MINUTE_IN_SECONDS );
	}

	/**
	 * Redirect to vehicles page.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return void
	 */
	protected function redirect( $args = array() ) {
		wp_safe_redirect( $this->get_page_url( $args ) );
		exit;
	}

	/**
	 * Redirect to a full URL.
	 *
	 * @param string $url URL.
	 * @return void
	 */
	protected function redirect_to_url( $url ) {
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * Get the page URL.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	protected function get_page_url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'super-mechanic-vehicles',
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Whether current screen belongs to vehicles module.
	 *
	 * @return bool
	 */
	protected function is_vehicles_screen() {
		return isset( $_GET['page'] ) && 'super-mechanic-vehicles' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Get error transient key.
	 *
	 * @return string
	 */
	protected function get_error_transient_key() {
		return 'sm_vehicle_errors_' . get_current_user_id();
	}

	/**
	 * Get form transient key.
	 *
	 * @return string
	 */
	protected function get_form_transient_key() {
		return 'sm_vehicle_form_' . get_current_user_id();
	}

	/**
	 * Read return context from request.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_process_return_context() {
		$page       = isset( $_GET['return_page'] ) ? sanitize_key( wp_unslash( $_GET['return_page'] ) ) : ( isset( $_POST['return_page'] ) ? sanitize_key( wp_unslash( $_POST['return_page'] ) ) : '' );
		$action     = isset( $_GET['return_action'] ) ? sanitize_key( wp_unslash( $_GET['return_action'] ) ) : ( isset( $_POST['return_action'] ) ? sanitize_key( wp_unslash( $_POST['return_action'] ) ) : '' );
		$process_id = isset( $_GET['return_process_id'] ) ? absint( wp_unslash( $_GET['return_process_id'] ) ) : ( isset( $_POST['return_process_id'] ) ? absint( wp_unslash( $_POST['return_process_id'] ) ) : 0 );
		$client_id  = isset( $_GET['client_id'] ) ? absint( wp_unslash( $_GET['client_id'] ) ) : ( isset( $_POST['return_client_id'] ) ? absint( wp_unslash( $_POST['return_client_id'] ) ) : 0 );

		return array(
			'page'       => 'super-mechanic-processes' === $page ? $page : '',
			'action'     => in_array( $action, array( 'new', 'edit' ), true ) ? $action : 'new',
			'process_id' => $process_id,
			'client_id'  => $client_id,
		);
	}

	/**
	 * Read post return context.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_process_return_context_from_post() {
		$return = $this->get_process_return_context();

		return array(
			'is_process' => 'super-mechanic-processes' === $return['page'],
			'action'     => $return['action'],
			'process_id' => absint( $return['process_id'] ),
			'client_id'  => absint( $return['client_id'] ),
		);
	}
}
