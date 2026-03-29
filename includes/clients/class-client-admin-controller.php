<?php
/**
 * Client admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Clients;

use Super_Mechanic\Invoices\Payment_Repository;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Relations\Client_Vehicle_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles client admin flows.
 */
class Client_Admin_Controller {
	/**
	 * Client service.
	 *
	 * @var Client_Service
	 */
	protected $service;
	protected $client_vehicle_service;
	protected $process_service;
	protected $payment_repository;

	/**
	 * Constructor.
	 *
	 * @param Client_Service|null $service Client service.
	 */
	public function __construct( Client_Service $service = null ) {
		$this->service                = $service ? $service : new Client_Service();
		$this->client_vehicle_service = new Client_Vehicle_Service();
		$this->process_service        = new Process_Service();
		$this->payment_repository     = new Payment_Repository();
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
	 * Process client actions before any admin output.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_clients_screen() ) {
			return;
		}

		$this->ensure_permissions();
		$this->handle_actions();
	}

	/**
	 * Render the clients admin page.
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
			$client = $this->service->get_client( $id );

			if ( empty( $client ) ) {
				wp_die( esc_html__( 'El cliente solicitado no existe.', 'super-mechanic' ) );
			}

			$this->render_form_page( $client, true );
			return;
		}

		if ( 'view' === $action ) {
			$client = $this->service->get_client( $id );

			if ( empty( $client ) ) {
				wp_die( esc_html__( 'El cliente solicitado no existe.', 'super-mechanic' ) );
			}

			$this->render_detail_page( $client );
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
		if ( ! $this->is_clients_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$count  = isset( $_GET['deleted_count'] ) ? absint( $_GET['deleted_count'] ) : 0;

		if ( 'created' === $notice ) {
			$this->render_notice( __( 'Cliente creado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'updated' === $notice ) {
			$this->render_notice( __( 'Cliente actualizado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'deleted' === $notice ) {
			$this->render_notice( __( 'Cliente eliminado correctamente.', 'super-mechanic' ), 'success' );
		}

		if ( 'bulk_deleted' === $notice ) {
			$this->render_notice(
				sprintf(
					/* translators: %d: number of deleted clients. */
					__( '%d clientes eliminados correctamente.', 'super-mechanic' ),
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
		$list_table = new Client_List_Table( $this->service );
		$list_table->prepare_items();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Clientes', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Gestiona la base de clientes del taller con un flujo administrativo mas claro y consistente.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ) . '" class="button button-primary">' . esc_html__( 'Añadir nuevo', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="sm-card sm-filter-card sm-section">';
		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="super-mechanic-clients" />';
		wp_nonce_field( 'sm_bulk_delete_clients', 'sm_bulk_delete_nonce' );
		$list_table->search_box( __( 'Buscar clientes', 'super-mechanic' ), 'sm-clients' );
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
	 * @param array<string, mixed> $client  Client data.
	 * @param bool                 $is_edit Whether editing.
	 * @return void
	 */
	protected function render_form_page( $client = array(), $is_edit = false ) {
		$defaults = array(
			'id'          => 0,
			'first_name'  => '',
			'last_name'   => '',
			'email'       => '',
			'phone'       => '',
			'document_id' => '',
			'notes'       => '',
		);

		$stored = get_transient( $this->get_form_transient_key() );
		if ( is_array( $stored ) ) {
			$client = array_merge( $client, $stored );
			delete_transient( $this->get_form_transient_key() );
		}

		$client = wp_parse_args( $client, $defaults );
		$title  = $is_edit ? __( 'Editar cliente', 'super-mechanic' ) : __( 'Nuevo cliente', 'super-mechanic' );
		$return = $this->get_process_return_context();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Completa los datos clave del cliente sin alterar el flujo operativo existente del modulo.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url() ) . '" class="button button-secondary">' . esc_html__( 'Volver al listado', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="sm-card sm-form-card">';
		echo '<form method="post" action="' . esc_url( $this->get_page_url( $is_edit ? array( 'action' => 'edit', 'id' => absint( $client['id'] ) ) : array( 'action' => 'new' ) ) ) . '">';
		wp_nonce_field( 'sm_save_client', 'sm_client_nonce' );
		echo '<input type="hidden" name="sm_client_operation" value="' . esc_attr( $is_edit ? 'update' : 'create' ) . '" />';
		echo '<input type="hidden" name="client_id" value="' . esc_attr( absint( $client['id'] ) ) . '" />';
		echo '<input type="hidden" name="return_page" value="' . esc_attr( $return['page'] ) . '" />';
		echo '<input type="hidden" name="return_action" value="' . esc_attr( $return['action'] ) . '" />';
		echo '<input type="hidden" name="return_process_id" value="' . esc_attr( $return['process_id'] ) . '" />';
		echo '<input type="hidden" name="return_vehicle_id" value="' . esc_attr( $return['vehicle_id'] ) . '" />';
		echo '<table class="form-table" role="presentation">';
		$this->render_text_field( 'first_name', __( 'Nombre', 'super-mechanic' ), $client['first_name'], true );
		$this->render_text_field( 'last_name', __( 'Apellido', 'super-mechanic' ), $client['last_name'] );
		$this->render_email_field( 'email', __( 'Correo electrónico', 'super-mechanic' ), $client['email'], true );
		$this->render_text_field( 'phone', __( 'Teléfono', 'super-mechanic' ), $client['phone'], true );
		$this->render_text_field( 'document_id', __( 'Documento', 'super-mechanic' ), $client['document_id'], true );
		$this->render_textarea_field( 'notes', __( 'Notas', 'super-mechanic' ), $client['notes'] );
		echo '</table>';
		echo '<div class="sm-form-actions">';
		submit_button( $is_edit ? __( 'Actualizar cliente', 'super-mechanic' ) : __( 'Crear cliente', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render client detail page.
	 *
	 * @param array<string, mixed> $client Client data.
	 * @return void
	 */
	protected function render_detail_page( array $client ) {
		$client_id = absint( $client['id'] );
		$vehicles  = $this->client_vehicle_service->get_client_vehicles(
			$client_id,
			array(
				'per_page' => 100,
			)
		);
		$processes = $this->process_service->get_processes(
			array(
				'client_id' => $client_id,
				'per_page'  => 100,
				'orderby'   => 'created_at',
				'order'     => 'DESC',
			)
		);
		$payments  = $this->payment_repository->get_all(
			array(
				'client_id' => $client_id,
				'per_page'  => 100,
				'page'      => 1,
				'orderby'   => 'payment_date',
				'order'     => 'DESC',
			)
		);
		$name      = trim( $client['first_name'] . ' ' . $client['last_name'] );

		if ( '' === $name ) {
			$name = __( 'Cliente sin nombre', 'super-mechanic' );
		}

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html( $name ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Detalle administrativo del cliente, sus vehículos vinculados y los procesos relacionados en la arquitectura activa.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'edit', 'id' => $client_id ) ) ) . '" class="button button-primary">' . esc_html__( 'Editar cliente', 'super-mechanic' ) . '</a> ';
		echo '<a href="' . esc_url( $this->get_page_url() ) . '" class="button button-secondary">' . esc_html__( 'Volver al listado', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';

		echo '<div class="sm-grid sm-grid-two sm-section">';
		echo '<section class="sm-card">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Datos del cliente', 'super-mechanic' ) . '</h2></div>';
		echo '<table class="sm-table"><tbody>';
		$this->render_detail_row( __( 'ID', 'super-mechanic' ), (string) $client_id );
		$this->render_detail_row( __( 'Nombre', 'super-mechanic' ), $name );
		$this->render_detail_row( __( 'Correo electrónico', 'super-mechanic' ), ! empty( $client['email'] ) ? (string) $client['email'] : __( 'Sin correo registrado', 'super-mechanic' ) );
		$this->render_detail_row( __( 'Teléfono', 'super-mechanic' ), ! empty( $client['phone'] ) ? (string) $client['phone'] : __( 'Sin teléfono registrado', 'super-mechanic' ) );
		$this->render_detail_row( __( 'Documento', 'super-mechanic' ), ! empty( $client['document_id'] ) ? (string) $client['document_id'] : __( 'Sin documento registrado', 'super-mechanic' ) );
		$this->render_detail_row( __( 'Estado', 'super-mechanic' ), ! empty( $client['status'] ) ? (string) $client['status'] : __( 'Sin estado', 'super-mechanic' ) );
		$this->render_detail_row( __( 'Creado', 'super-mechanic' ), ! empty( $client['created_at'] ) ? (string) $client['created_at'] : '-' );
		$this->render_detail_row( __( 'Notas', 'super-mechanic' ), ! empty( $client['notes'] ) ? (string) $client['notes'] : __( 'Sin notas', 'super-mechanic' ) );
		echo '</tbody></table>';
		echo '</section>';

		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Contexto operativo', 'super-mechanic' ) . '</h2></div>';
		echo '<p><strong>' . esc_html__( 'Vehículos vinculados', 'super-mechanic' ) . ':</strong> ' . esc_html( is_array( $vehicles ) ? count( $vehicles ) : 0 ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Procesos relacionados', 'super-mechanic' ) . ':</strong> ' . esc_html( count( $processes ) ) . '</p>';
		echo '<p>' . esc_html__( 'El vínculo con usuarios WordPress en el runtime activo depende de la meta `sm_client_id`. Esta fase no crea todavía un flujo admin nuevo para enlazar usuarios si no existe uno ya operativo.', 'super-mechanic' ) . '</p>';
		echo '</section>';
		echo '</div>';

		echo '<section class="sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Vehículos vinculados', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Vehículo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Placa / VIN', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Relación', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( ! is_array( $vehicles ) || empty( $vehicles ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay vehículos vinculados para este cliente.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $vehicles as $vehicle ) {
				$vehicle_id = ! empty( $vehicle['vehicle_id'] ) ? absint( $vehicle['vehicle_id'] ) : ( ! empty( $vehicle['id'] ) ? absint( $vehicle['id'] ) : 0 );
				$view_url   = add_query_arg(
					array(
						'page'   => 'super-mechanic-vehicles',
						'action' => 'view',
						'id'     => $vehicle_id,
					),
					admin_url( 'admin.php' )
				);
				$label      = $this->format_vehicle_label( $vehicle );
				$identifier = ! empty( $vehicle['plate'] ) ? (string) $vehicle['plate'] : ( ! empty( $vehicle['vin'] ) ? (string) $vehicle['vin'] : '-' );
				echo '<tr>';
				echo '<td>' . esc_html( $vehicle_id ) . '</td>';
				echo '<td>' . esc_html( $label ) . '</td>';
				echo '<td>' . esc_html( $identifier ) . '</td>';
				echo '<td>' . esc_html( ! empty( $vehicle['ownership_type'] ) ? (string) $vehicle['ownership_type'] : __( 'Vínculo activo', 'super-mechanic' ) ) . '</td>';
				echo '<td><a href="' . esc_url( $view_url ) . '">' . esc_html__( 'Ver', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Procesos relacionados', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Título', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehículo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Acciones', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $processes ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay procesos relacionados para este cliente.', 'super-mechanic' ) . '</td></tr>';
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
				echo '<td>' . esc_html( $this->format_vehicle_label( $process ) ) . '</td>';
				echo '<td><a href="' . esc_url( $view_url ) . '">' . esc_html__( 'Abrir proceso', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Historial de pagos', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Invoice', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Monto', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Método', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Referencia', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $payments ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay pagos registrados para este cliente en el negocio activo.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $payments as $payment ) {
				$currency = isset( $payment['currency'] ) ? (string) $payment['currency'] : 'USD';
				echo '<tr>';
				echo '<td>#' . esc_html( absint( $payment['id'] ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $payment['payment_date'] ) ? (string) $payment['payment_date'] : '-' ) . '</td>';
				echo '<td>' . esc_html( ! empty( $payment['invoice_number'] ) ? (string) $payment['invoice_number'] : '#' . absint( $payment['invoice_id'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money( isset( $payment['amount'] ) ? (float) $payment['amount'] : 0, $currency ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( isset( $payment['payment_method'] ) ? (string) $payment['payment_method'] : '' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $payment['reference'] ) ? (string) $payment['reference'] : '-' ) . '</td>';
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
			$operation   = isset( $_POST['sm_client_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_client_operation'] ) ) : '';
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
		check_admin_referer( 'sm_save_client', 'sm_client_nonce' );

		$client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
		$data      = array(
			'first_name'  => isset( $_POST['first_name'] ) ? wp_unslash( $_POST['first_name'] ) : '',
			'last_name'   => isset( $_POST['last_name'] ) ? wp_unslash( $_POST['last_name'] ) : '',
			'email'       => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
			'phone'       => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
			'document_id' => isset( $_POST['document_id'] ) ? wp_unslash( $_POST['document_id'] ) : '',
			'notes'       => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
		);

		$result = $is_update
			? $this->service->update_client( $client_id, $data )
			: $this->service->create_client( $data );

		if ( is_wp_error( $result ) ) {
			$this->store_form_state( $data );
			$this->store_errors( $result );
			$redirect_args = $is_update
				? array(
					'action'    => 'edit',
					'id'        => $client_id,
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
			$target_client_id = absint( $result );
			$args             = array(
				'page'      => 'super-mechanic-processes',
				'action'    => $return_context['action'],
				'client_id' => $target_client_id,
			);

			if ( $return_context['process_id'] > 0 ) {
				$args['id'] = $return_context['process_id'];
			}

			if ( $return_context['vehicle_id'] > 0 ) {
				$args['vehicle_id'] = $return_context['vehicle_id'];
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
		check_admin_referer( 'sm_delete_client_' . $id );

		$result = $this->service->delete_client( $id );

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
		check_admin_referer( 'sm_bulk_delete_clients', 'sm_bulk_delete_nonce' );

		$ids = isset( $_POST['client_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['client_ids'] ) ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			$this->store_errors( new WP_Error( 'sm_no_clients_selected', __( 'Selecciona al menos un cliente para eliminar.', 'super-mechanic' ) ) );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			$result = $this->service->delete_client( $id );
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
	 * Render email field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return void
	 */
	protected function render_email_field( $name, $label, $value, $required = false ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="email" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text"' . ( $required ? ' required' : '' ) . ' /></td>';
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
	 * Render detail row markup.
	 *
	 * @param string $label Detail label.
	 * @param string $value Detail value.
	 * @return void
	 */
	protected function render_detail_row( $label, $value ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}

	/**
	 * Format a vehicle label.
	 *
	 * @param array<string, mixed> $vehicle Vehicle-like data.
	 * @return string
	 */
	protected function format_vehicle_label( $vehicle ) {
		$make  = ! empty( $vehicle['brand'] ) ? $vehicle['brand'] : ( ! empty( $vehicle['make'] ) ? $vehicle['make'] : ( ! empty( $vehicle['vehicle_make'] ) ? $vehicle['vehicle_make'] : '' ) );
		$model = ! empty( $vehicle['model'] ) ? $vehicle['model'] : ( ! empty( $vehicle['vehicle_model'] ) ? $vehicle['vehicle_model'] : '' );
		$plate = ! empty( $vehicle['plate'] ) ? $vehicle['plate'] : ( ! empty( $vehicle['vehicle_plate'] ) ? $vehicle['vehicle_plate'] : '' );
		$label = trim( $make . ' ' . $model );

		if ( $plate ) {
			$label .= ' - ' . $plate;
		}

		return $label ? $label : __( 'Vehículo sin identificar', 'super-mechanic' );
	}

	/**
	 * Humanize internal keys for UI output.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/**
	 * Format amount using currency code.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	protected function format_money( $amount, $currency ) {
		return strtoupper( sanitize_text_field( (string) $currency ) ) . ' ' . number_format_i18n( (float) $amount, 2 );
	}

	/**
	 * Ensure the current user can access the module.
	 *
	 * @return void
	 */
	protected function ensure_permissions() {
		if ( ! current_user_can( 'sm_manage_clients' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para gestionar clientes.', 'super-mechanic' ) );
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
	 * Redirect to clients page.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return void
	 */
	protected function redirect( $args = array() ) {
		wp_safe_redirect( $this->get_page_url( $args ) );
		exit;
	}

	/**
	 * Redirect to a fully resolved URL.
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
					'page' => 'super-mechanic-clients',
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Whether current screen belongs to clients module.
	 *
	 * @return bool
	 */
	protected function is_clients_screen() {
		return isset( $_GET['page'] ) && 'super-mechanic-clients' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Get error transient key.
	 *
	 * @return string
	 */
	protected function get_error_transient_key() {
		return 'sm_client_errors_' . get_current_user_id();
	}

	/**
	 * Get form transient key.
	 *
	 * @return string
	 */
	protected function get_form_transient_key() {
		return 'sm_client_form_' . get_current_user_id();
	}

	/**
	 * Read return context from request (GET first, then POST).
	 *
	 * @return array<string, mixed>
	 */
	protected function get_process_return_context() {
		$page      = isset( $_GET['return_page'] ) ? sanitize_key( wp_unslash( $_GET['return_page'] ) ) : ( isset( $_POST['return_page'] ) ? sanitize_key( wp_unslash( $_POST['return_page'] ) ) : '' );
		$action    = isset( $_GET['return_action'] ) ? sanitize_key( wp_unslash( $_GET['return_action'] ) ) : ( isset( $_POST['return_action'] ) ? sanitize_key( wp_unslash( $_POST['return_action'] ) ) : '' );
		$process_id = isset( $_GET['return_process_id'] ) ? absint( wp_unslash( $_GET['return_process_id'] ) ) : ( isset( $_POST['return_process_id'] ) ? absint( wp_unslash( $_POST['return_process_id'] ) ) : 0 );
		$vehicle_id = isset( $_GET['vehicle_id'] ) ? absint( wp_unslash( $_GET['vehicle_id'] ) ) : ( isset( $_POST['return_vehicle_id'] ) ? absint( wp_unslash( $_POST['return_vehicle_id'] ) ) : 0 );

		return array(
			'page'       => 'super-mechanic-processes' === $page ? $page : '',
			'action'     => in_array( $action, array( 'new', 'edit' ), true ) ? $action : 'new',
			'process_id' => $process_id,
			'vehicle_id' => $vehicle_id,
		);
	}

	/**
	 * Read and normalize post return context.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_process_return_context_from_post() {
		$return = $this->get_process_return_context();

		return array(
			'is_process' => 'super-mechanic-processes' === $return['page'],
			'action'     => $return['action'],
			'process_id' => absint( $return['process_id'] ),
			'vehicle_id' => absint( $return['vehicle_id'] ),
		);
	}
}
