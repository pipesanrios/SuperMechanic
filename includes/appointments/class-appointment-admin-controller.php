<?php
/**
 * Appointment admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Appointments;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles appointment admin flows.
 */
class Appointment_Admin_Controller {
	/**
	 * Service.
	 *
	 * @var Appointment_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Appointment_Service|null $service Service.
	 */
	public function __construct( Appointment_Service $service = null ) {
		$this->service = $service ? $service : new Appointment_Service();
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
	 * Handle actions.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_appointments_screen() ) {
			return;
		}

		$this->ensure_permissions();
		$this->handle_actions();
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
			$appointment = $this->service->get_appointment( $id );
			if ( ! is_array( $appointment ) ) {
				wp_die( esc_html__( 'La cita solicitada no existe.', 'super-mechanic' ) );
			}

			$this->render_form_page( $appointment, true );
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
		if ( ! $this->is_appointments_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$count  = isset( $_GET['deleted_count'] ) ? absint( $_GET['deleted_count'] ) : 0;

		if ( 'created' === $notice ) {
			$this->render_notice( __( 'Cita creada correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'updated' === $notice ) {
			$this->render_notice( __( 'Cita actualizada correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'deleted' === $notice ) {
			$this->render_notice( __( 'Cita eliminada correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'bulk_deleted' === $notice ) {
			$this->render_notice( sprintf( __( '%d citas eliminadas correctamente.', 'super-mechanic' ), $count ), 'success' );
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
	 * Render list.
	 *
	 * @return void
	 */
	protected function render_list_page() {
		$list_table = new Appointment_List_Table( $this->service );
		$list_table->prepare_items();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Citas', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Administra agenda operativa base del taller con filtro por fecha, mecanico y estado.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ) . '" class="button button-primary">' . esc_html__( 'Nueva cita', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';

		$this->render_filter_form();

		echo '<div class="sm-card sm-section">';
		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="super-mechanic-appointments" />';
		wp_nonce_field( 'sm_bulk_delete_appointments', 'sm_bulk_delete_nonce' );
		$list_table->search_box( __( 'Buscar citas', 'super-mechanic' ), 'sm-appointments' );
		echo '<div class="sm-table-wrap sm-list-table-wrap">';
		$list_table->display();
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render form.
	 *
	 * @param array<string,mixed> $appointment Appointment data.
	 * @param bool                $is_edit     Edit mode.
	 * @return void
	 */
	protected function render_form_page( array $appointment = array(), $is_edit = false ) {
		$defaults = array(
			'id'                 => 0,
			'client_id'          => 0,
			'vehicle_id'         => 0,
			'process_id'         => 0,
			'assigned_to'        => 0,
			'appointment_status' => 'scheduled',
			'appointment_date'   => '',
			'start_at'           => '',
			'notes'              => '',
		);

		$stored = get_transient( $this->get_form_transient_key() );
		if ( is_array( $stored ) ) {
			$appointment = array_merge( $appointment, $stored );
			delete_transient( $this->get_form_transient_key() );
		}

		$appointment = wp_parse_args( $appointment, $defaults );
		$title       = $is_edit ? __( 'Editar cita', 'super-mechanic' ) : __( 'Nueva cita', 'super-mechanic' );
		$clients     = $this->service->get_client_options();
		$vehicles    = $this->service->get_vehicle_options();
		$mechanics   = $this->service->get_mechanic_options();
		$statuses    = $this->service->get_status_options();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Base operativa de citas: cliente, vehiculo, mecanico, estado y horario.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url() ) . '" class="button button-secondary">' . esc_html__( 'Volver al listado', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';

		echo '<div class="sm-card sm-form-card">';
		echo '<form method="post" action="' . esc_url( $this->get_page_url( $is_edit ? array( 'action' => 'edit', 'id' => absint( $appointment['id'] ) ) : array( 'action' => 'new' ) ) ) . '">';
		wp_nonce_field( 'sm_save_appointment', 'sm_appointment_nonce' );
		echo '<input type="hidden" name="sm_appointment_operation" value="' . esc_attr( $is_edit ? 'update' : 'create' ) . '" />';
		echo '<input type="hidden" name="appointment_id" value="' . esc_attr( absint( $appointment['id'] ) ) . '" />';
		echo '<table class="form-table" role="presentation">';
		$this->render_client_select_field( $appointment['client_id'], $clients );
		$this->render_vehicle_select_field( $appointment['vehicle_id'], $vehicles );
		$this->render_number_field( 'process_id', __( 'Proceso (opcional)', 'super-mechanic' ), $appointment['process_id'] );
		$this->render_mechanic_select_field( $appointment['assigned_to'], $mechanics );
		$this->render_select_field( 'appointment_status', __( 'Estado', 'super-mechanic' ), $appointment['appointment_status'], $statuses );
		$this->render_date_field( 'appointment_date', __( 'Fecha de cita', 'super-mechanic' ), $appointment['appointment_date'] );
		$this->render_datetime_field( 'start_at', __( 'Fecha y hora inicio', 'super-mechanic' ), $appointment['start_at'] );
		$this->render_textarea_field( 'notes', __( 'Notas', 'super-mechanic' ), $appointment['notes'] );
		echo '</table>';
		echo '<div class="sm-form-actions">';
		submit_button( $is_edit ? __( 'Actualizar cita', 'super-mechanic' ) : __( 'Crear cita', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Handle incoming actions.
	 *
	 * @return void
	 */
	protected function handle_actions() {
		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			$operation   = isset( $_POST['sm_appointment_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_appointment_operation'] ) ) : '';
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
	 * Handle save.
	 *
	 * @param bool $is_update Update mode.
	 * @return void
	 */
	protected function handle_save_action( $is_update ) {
		check_admin_referer( 'sm_save_appointment', 'sm_appointment_nonce' );

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$data           = array(
			'client_id'          => isset( $_POST['client_id'] ) ? wp_unslash( $_POST['client_id'] ) : 0,
			'vehicle_id'         => isset( $_POST['vehicle_id'] ) ? wp_unslash( $_POST['vehicle_id'] ) : 0,
			'process_id'         => isset( $_POST['process_id'] ) ? wp_unslash( $_POST['process_id'] ) : 0,
			'assigned_to'        => isset( $_POST['assigned_to'] ) ? wp_unslash( $_POST['assigned_to'] ) : 0,
			'appointment_status' => isset( $_POST['appointment_status'] ) ? wp_unslash( $_POST['appointment_status'] ) : 'scheduled',
			'appointment_date'   => isset( $_POST['appointment_date'] ) ? wp_unslash( $_POST['appointment_date'] ) : '',
			'start_at'           => isset( $_POST['start_at'] ) ? wp_unslash( $_POST['start_at'] ) : '',
			'notes'              => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
		);

		$result = $is_update ? $this->service->update_appointment( $appointment_id, $data ) : $this->service->create_appointment( $data );

		if ( is_wp_error( $result ) ) {
			$this->store_form_state( $data );
			$this->store_errors( $result );
			$this->redirect(
				$is_update
					? array(
						'action'    => 'edit',
						'id'        => $appointment_id,
						'sm_notice' => 'error',
					)
					: array(
						'action'    => 'new',
						'sm_notice' => 'error',
					)
			);
		}

		$this->redirect( array( 'sm_notice' => $is_update ? 'updated' : 'created' ) );
	}

	/**
	 * Handle delete.
	 *
	 * @return void
	 */
	protected function handle_delete_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'sm_delete_appointment_' . $id );

		$result = $this->service->delete_appointment( $id );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'sm_notice' => 'deleted' ) );
	}

	/**
	 * Handle bulk delete.
	 *
	 * @return void
	 */
	protected function handle_bulk_delete_action() {
		check_admin_referer( 'sm_bulk_delete_appointments', 'sm_bulk_delete_nonce' );

		$ids = isset( $_POST['appointment_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['appointment_ids'] ) ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			$this->store_errors( new WP_Error( 'sm_no_appointments_selected', __( 'Selecciona al menos una cita para eliminar.', 'super-mechanic' ) ) );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			$result = $this->service->delete_appointment( $id );
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
	 * Render filters.
	 *
	 * @return void
	 */
	protected function render_filter_form() {
		$statuses    = $this->service->get_status_options();
		$mechanics   = $this->service->get_mechanic_options();
		$status      = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : '';
		$assigned_to = isset( $_GET['filter_assigned_to'] ) ? absint( wp_unslash( $_GET['filter_assigned_to'] ) ) : 0;
		$date_from   = isset( $_GET['filter_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_from'] ) ) : '';
		$date_to     = isset( $_GET['filter_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_to'] ) ) : '';

		echo '<div class="sm-card sm-filter-card sm-section">';
		echo '<form method="get" class="sm-inline-filters">';
		echo '<input type="hidden" name="page" value="super-mechanic-appointments" />';

		echo '<label for="filter_status" class="screen-reader-text">' . esc_html__( 'Filtrar por estado', 'super-mechanic' ) . '</label>';
		echo '<select id="filter_status" name="filter_status">';
		echo '<option value="">' . esc_html__( 'Todos los estados', 'super-mechanic' ) . '</option>';
		foreach ( $statuses as $status_key => $status_label ) {
			echo '<option value="' . esc_attr( $status_key ) . '" ' . selected( $status, $status_key, false ) . '>' . esc_html( $status_label ) . '</option>';
		}
		echo '</select>';

		echo '<label for="filter_assigned_to" class="screen-reader-text">' . esc_html__( 'Filtrar por mecanico', 'super-mechanic' ) . '</label>';
		echo '<select id="filter_assigned_to" name="filter_assigned_to">';
		echo '<option value="0">' . esc_html__( 'Todos los mecanicos', 'super-mechanic' ) . '</option>';
		foreach ( $mechanics as $mechanic ) {
			echo '<option value="' . esc_attr( absint( $mechanic['id'] ) ) . '" ' . selected( $assigned_to, absint( $mechanic['id'] ), false ) . '>' . esc_html( $mechanic['display_name'] ) . '</option>';
		}
		echo '</select>';

		echo '<label for="filter_date_from" class="screen-reader-text">' . esc_html__( 'Desde fecha', 'super-mechanic' ) . '</label>';
		echo '<input type="date" id="filter_date_from" name="filter_date_from" value="' . esc_attr( $date_from ) . '" />';

		echo '<label for="filter_date_to" class="screen-reader-text">' . esc_html__( 'Hasta fecha', 'super-mechanic' ) . '</label>';
		echo '<input type="date" id="filter_date_to" name="filter_date_to" value="' . esc_attr( $date_to ) . '" />';

		submit_button( __( 'Filtrar', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '<a class="button button-link" href="' . esc_url( $this->get_page_url() ) . '">' . esc_html__( 'Limpiar', 'super-mechanic' ) . '</a>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render client select field.
	 *
	 * @param int                      $selected Selected ID.
	 * @param array<int,array<string,mixed>> $clients Clients.
	 * @return void
	 */
	protected function render_client_select_field( $selected, array $clients ) {
		echo '<tr>';
		echo '<th scope="row"><label for="client_id">' . esc_html__( 'Cliente', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="client_id" id="client_id" required>';
		echo '<option value="0">' . esc_html__( 'Selecciona un cliente', 'super-mechanic' ) . '</option>';
		foreach ( $clients as $client ) {
			$label = trim( sprintf( '%s %s', isset( $client['first_name'] ) ? $client['first_name'] : '', isset( $client['last_name'] ) ? $client['last_name'] : '' ) );
			if ( '' === $label && ! empty( $client['email'] ) ) {
				$label = (string) $client['email'];
			}
			echo '<option value="' . esc_attr( absint( $client['id'] ) ) . '" ' . selected( absint( $selected ), absint( $client['id'] ), false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';
	}

	/**
	 * Render vehicle select field.
	 *
	 * @param int                           $selected Selected ID.
	 * @param array<int,array<string,mixed>> $vehicles Vehicles.
	 * @return void
	 */
	protected function render_vehicle_select_field( $selected, array $vehicles ) {
		echo '<tr>';
		echo '<th scope="row"><label for="vehicle_id">' . esc_html__( 'Vehiculo', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="vehicle_id" id="vehicle_id" required>';
		echo '<option value="0">' . esc_html__( 'Selecciona un vehiculo', 'super-mechanic' ) . '</option>';
		foreach ( $vehicles as $vehicle ) {
			$label = trim( sprintf( '%s %s', isset( $vehicle['make'] ) ? $vehicle['make'] : '', isset( $vehicle['model'] ) ? $vehicle['model'] : '' ) );
			if ( ! empty( $vehicle['plate'] ) ) {
				$label .= ' - ' . $vehicle['plate'];
			} elseif ( ! empty( $vehicle['vin'] ) ) {
				$label .= ' - ' . $vehicle['vin'];
			}
			echo '<option value="' . esc_attr( absint( $vehicle['id'] ) ) . '" ' . selected( absint( $selected ), absint( $vehicle['id'] ), false ) . '>' . esc_html( '' !== trim( $label ) ? $label : __( 'Vehiculo sin identificar', 'super-mechanic' ) ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';
	}

	/**
	 * Render mechanic select.
	 *
	 * @param int                           $selected  Selected ID.
	 * @param array<int,array<string,mixed>> $mechanics Mechanics.
	 * @return void
	 */
	protected function render_mechanic_select_field( $selected, array $mechanics ) {
		echo '<tr>';
		echo '<th scope="row"><label for="assigned_to">' . esc_html__( 'Mecanico asignado', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="assigned_to" id="assigned_to" required>';
		echo '<option value="0">' . esc_html__( 'Selecciona un mecanico', 'super-mechanic' ) . '</option>';
		foreach ( $mechanics as $mechanic ) {
			echo '<option value="' . esc_attr( absint( $mechanic['id'] ) ) . '" ' . selected( absint( $selected ), absint( $mechanic['id'] ), false ) . '>' . esc_html( $mechanic['display_name'] ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';
	}

	/**
	 * Render select field.
	 *
	 * @param string              $name     Field name.
	 * @param string              $label    Label.
	 * @param string              $selected Selected value.
	 * @param array<string,string> $options  Options.
	 * @return void
	 */
	protected function render_select_field( $name, $label, $selected, array $options ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $option_label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';
	}

	/**
	 * Render date field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	protected function render_date_field( $name, $label, $value ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="date" id="' . esc_attr( $name ) . '" value="' . esc_attr( $this->format_date_for_input( $value ) ) . '" required /></td>';
		echo '</tr>';
	}

	/**
	 * Render datetime field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	protected function render_datetime_field( $name, $label, $value ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="datetime-local" id="' . esc_attr( $name ) . '" value="' . esc_attr( $this->format_datetime_for_input( $value ) ) . '" class="regular-text" required /></td>';
		echo '</tr>';
	}

	/**
	 * Render number field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @return void
	 */
	protected function render_number_field( $name, $label, $value ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="number" min="0" id="' . esc_attr( $name ) . '" value="' . esc_attr( absint( $value ) ) . '" class="regular-text" /></td>';
		echo '</tr>';
	}

	/**
	 * Render textarea field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	protected function render_textarea_field( $name, $label, $value ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="large-text" rows="5">' . esc_textarea( $value ) . '</textarea></td>';
		echo '</tr>';
	}

	/**
	 * Render admin notice.
	 *
	 * @param string $message Message.
	 * @param string $type    Type.
	 * @return void
	 */
	protected function render_notice( $message, $type ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible sm-notice-card"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Ensure capability.
	 *
	 * @return void
	 */
	protected function ensure_permissions() {
		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para gestionar citas.', 'super-mechanic' ) );
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
	 * @param array<string,mixed> $data Data.
	 * @return void
	 */
	protected function store_form_state( array $data ) {
		set_transient( $this->get_form_transient_key(), $data, MINUTE_IN_SECONDS );
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
	 * Redirect page.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return void
	 */
	protected function redirect( array $args = array() ) {
		wp_safe_redirect( $this->get_page_url( $args ) );
		exit;
	}

	/**
	 * Get page url.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return string
	 */
	protected function get_page_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'super-mechanic-appointments' ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Check current screen.
	 *
	 * @return bool
	 */
	protected function is_appointments_screen() {
		return isset( $_GET['page'] ) && 'super-mechanic-appointments' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Error transient key.
	 *
	 * @return string
	 */
	protected function get_error_transient_key() {
		return 'sm_appointment_errors_' . get_current_user_id();
	}

	/**
	 * Form transient key.
	 *
	 * @return string
	 */
	protected function get_form_transient_key() {
		return 'sm_appointment_form_' . get_current_user_id();
	}

	/**
	 * Format datetime for input.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function format_datetime_for_input( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$timestamp = strtotime( (string) $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d\TH:i', $timestamp );
	}

	/**
	 * Format date for input.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function format_date_for_input( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$timestamp = strtotime( (string) $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}
}
