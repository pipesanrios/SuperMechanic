<?php
/**
 * Vehicle admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Vehicles;

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

	/**
	 * Constructor.
	 *
	 * @param Vehicle_Service|null $service Vehicle service.
	 */
	public function __construct( Vehicle_Service $service = null ) {
		$this->service = $service ? $service : new Vehicle_Service();
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
			'client_id' => 0,
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
}
