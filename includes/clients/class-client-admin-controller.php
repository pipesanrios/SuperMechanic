<?php
/**
 * Client admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Clients;

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

	/**
	 * Constructor.
	 *
	 * @param Client_Service|null $service Client service.
	 */
	public function __construct( Client_Service $service = null ) {
		$this->service = $service ? $service : new Client_Service();
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

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Clientes', 'super-mechanic' ) . '</h1>';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ) . '" class="page-title-action">' . esc_html__( 'Añadir nuevo', 'super-mechanic' ) . '</a>';
		echo '<hr class="wp-header-end" />';
		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="super-mechanic-clients" />';
		wp_nonce_field( 'sm_bulk_delete_clients', 'sm_bulk_delete_nonce' );
		$list_table->search_box( __( 'Buscar clientes', 'super-mechanic' ), 'sm-clients' );
		$list_table->display();
		echo '</form>';
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

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<form method="post" action="' . esc_url( $this->get_page_url( $is_edit ? array( 'action' => 'edit', 'id' => absint( $client['id'] ) ) : array( 'action' => 'new' ) ) ) . '">';
		wp_nonce_field( 'sm_save_client', 'sm_client_nonce' );
		echo '<input type="hidden" name="sm_client_operation" value="' . esc_attr( $is_edit ? 'update' : 'create' ) . '" />';
		echo '<input type="hidden" name="client_id" value="' . esc_attr( absint( $client['id'] ) ) . '" />';
		echo '<table class="form-table" role="presentation">';
		$this->render_text_field( 'first_name', __( 'Nombre', 'super-mechanic' ), $client['first_name'], true );
		$this->render_text_field( 'last_name', __( 'Apellido', 'super-mechanic' ), $client['last_name'] );
		$this->render_email_field( 'email', __( 'Correo electrónico', 'super-mechanic' ), $client['email'] );
		$this->render_text_field( 'phone', __( 'Teléfono', 'super-mechanic' ), $client['phone'] );
		$this->render_text_field( 'document_id', __( 'Documento', 'super-mechanic' ), $client['document_id'] );
		$this->render_textarea_field( 'notes', __( 'Notas', 'super-mechanic' ), $client['notes'] );
		echo '</table>';
		submit_button( $is_edit ? __( 'Actualizar cliente', 'super-mechanic' ) : __( 'Crear cliente', 'super-mechanic' ) );
		echo '</form>';
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
	protected function render_email_field( $name, $label, $value ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="email" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text" /></td>';
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
}
