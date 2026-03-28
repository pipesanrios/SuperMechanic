<?php
/**
 * Business admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Businesses;

use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Helpers\Settings_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles businesses admin page and user context selector.
 */
class Business_Admin_Controller {
	/**
	 * Service.
	 *
	 * @var Business_Service
	 */
	protected $service;

	/**
	 * Business context.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Constructor.
	 *
	 * @param Business_Service|null         $service                  Service.
	 * @param Business_Context_Service|null $business_context_service Context service.
	 * @param Settings_Service|null         $settings_service         Settings service.
	 */
	public function __construct( Business_Service $service = null, Business_Context_Service $business_context_service = null, Settings_Service $settings_service = null ) {
		$this->service                  = $service ? $service : new Business_Service();
		$this->settings_service         = $settings_service ? $settings_service : new Settings_Service();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service( $this->settings_service, $this->service );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_sm_business_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_sm_business_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_sm_business_switch_context', array( $this, 'handle_switch_context' ) );
	}

	/**
	 * Register businesses submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Negocios', 'super-mechanic' ),
			__( 'Negocios', 'super-mechanic' ),
			'sm_manage_settings',
			'super-mechanic-businesses',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render businesses page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_settings' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'super-mechanic' ) );
		}

		$action   = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$edit_id  = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$editing  = 'edit' === $action && $edit_id > 0;
		$business = $editing ? $this->service->get_business( $edit_id ) : null;

		if ( $editing && ! is_array( $business ) ) {
			$editing = false;
		}

		$list_table = new Business_List_Table( $this->service );
		$list_table->prepare_items();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header"><div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Negocios', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Gestiona los talleres y selecciona el contexto operativo actual por usuario.', 'super-mechanic' ) . '</p>';
		echo '</div></div>';

		$this->render_notice();
		$this->render_context_switcher();
		$this->render_form( $editing ? $business : null );

		echo '<div class="sm-card sm-form-card sm-settings-card">';
		echo '<h2>' . esc_html__( 'Listado de negocios', 'super-mechanic' ) . '</h2>';
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="super-mechanic-businesses" />';
		$list_table->search_box( __( 'Buscar negocios', 'super-mechanic' ), 'sm-business-search' );
		$list_table->display();
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Handle save create/update.
	 *
	 * @return void
	 */
	public function handle_save() {
		$this->assert_permissions();
		check_admin_referer( 'sm_business_save', 'sm_business_nonce' );

		$business_id = isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0;
		$data        = array(
			'name'                       => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'slug'                       => isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '',
			'status'                     => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'active',
			'is_default'                 => ! empty( $_POST['is_default'] ) ? 1 : 0,
			'timezone'                   => isset( $_POST['timezone'] ) ? wp_unslash( $_POST['timezone'] ) : 'UTC',
			'currency'                   => isset( $_POST['currency'] ) ? wp_unslash( $_POST['currency'] ) : 'USD',
			'branding_logo_attachment_id' => isset( $_POST['branding_logo_attachment_id'] ) ? absint( wp_unslash( $_POST['branding_logo_attachment_id'] ) ) : 0,
			'primary_color'              => isset( $_POST['primary_color'] ) ? wp_unslash( $_POST['primary_color'] ) : '',
		);

		if ( $business_id > 0 ) {
			$result = $this->service->update_business( $business_id, $data );
		} else {
			$result = $this->service->create_business( $data );
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message(), $business_id );
		}

		$this->redirect_with_notice(
			'success',
			$business_id > 0 ? __( 'Negocio actualizado.', 'super-mechanic' ) : __( 'Negocio creado.', 'super-mechanic' ),
			0
		);
	}

	/**
	 * Handle delete.
	 *
	 * @return void
	 */
	public function handle_delete() {
		$this->assert_permissions();

		$business_id = isset( $_GET['business_id'] ) ? absint( wp_unslash( $_GET['business_id'] ) ) : 0;
		check_admin_referer( 'sm_business_delete_' . $business_id );

		$result = $this->service->delete_business( $business_id );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message(), 0 );
		}

		$this->redirect_with_notice( 'success', __( 'Negocio eliminado.', 'super-mechanic' ), 0 );
	}

	/**
	 * Handle context switch by user.
	 *
	 * @return void
	 */
	public function handle_switch_context() {
		$this->assert_permissions();
		check_admin_referer( 'sm_business_switch_context', 'sm_business_context_nonce' );

		$business_id = isset( $_POST['active_business_id'] ) ? absint( wp_unslash( $_POST['active_business_id'] ) ) : 0;
		$this->business_context_service->set_user_selected_business_id( $business_id );

		$this->redirect_with_notice( 'success', __( 'Contexto de negocio actualizado para tu usuario.', 'super-mechanic' ), 0 );
	}

	/**
	 * Render context switch form.
	 *
	 * @return void
	 */
	protected function render_context_switcher() {
		$all         = $this->service->get_businesses(
			array(
				'status'   => 'active',
				'per_page' => 200,
				'page'     => 1,
				'orderby'  => 'name',
				'order'    => 'ASC',
			)
		);
		$current_id  = $this->business_context_service->resolve_business_id();

		echo '<div class="sm-card sm-form-card sm-settings-card">';
		echo '<h2>' . esc_html__( 'Contexto operativo actual', 'super-mechanic' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sm_business_switch_context" />';
		wp_nonce_field( 'sm_business_switch_context', 'sm_business_context_nonce' );
		echo '<table class="form-table" role="presentation"><tr>';
		echo '<th scope="row"><label for="active_business_id">' . esc_html__( 'Negocio activo (usuario)', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="active_business_id" id="active_business_id">';
		foreach ( $all as $row ) {
			$row_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $row_id <= 0 ) {
				continue;
			}
			echo '<option value="' . esc_attr( (string) $row_id ) . '" ' . selected( $current_id, $row_id, false ) . '>' . esc_html( (string) $row['name'] . ' (#' . $row_id . ')' ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Prioridad de resolución: user meta -> sm_settings.business.business_id -> default id=1.', 'super-mechanic' ) . '</p></td>';
		echo '</tr></table>';
		echo '<div class="sm-form-actions">';
		submit_button( __( 'Cambiar contexto', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</div>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render create/edit form.
	 *
	 * @param array<string,mixed>|null $business Business data.
	 * @return void
	 */
	protected function render_form( $business = null ) {
		$is_edit = is_array( $business ) && ! empty( $business['id'] );

		$data = wp_parse_args(
			is_array( $business ) ? $business : array(),
			array(
				'id'                         => 0,
				'name'                       => '',
				'slug'                       => '',
				'status'                     => 'active',
				'is_default'                 => 0,
				'timezone'                   => 'UTC',
				'currency'                   => 'USD',
				'branding_logo_attachment_id' => 0,
				'primary_color'              => '',
			)
		);

		echo '<div class="sm-card sm-form-card sm-settings-card">';
		echo '<h2>' . esc_html( $is_edit ? __( 'Editar negocio', 'super-mechanic' ) : __( 'Nuevo negocio', 'super-mechanic' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sm_business_save" />';
		echo '<input type="hidden" name="business_id" value="' . esc_attr( (string) absint( $data['id'] ) ) . '" />';
		wp_nonce_field( 'sm_business_save', 'sm_business_nonce' );

		echo '<table class="form-table" role="presentation">';
		$this->render_text_row( 'name', __( 'Nombre', 'super-mechanic' ), (string) $data['name'], true );
		$this->render_text_row( 'slug', __( 'Slug', 'super-mechanic' ), (string) $data['slug'], true );
		$this->render_select_row( 'status', __( 'Estado', 'super-mechanic' ), (string) $data['status'], array( 'active' => __( 'Activo', 'super-mechanic' ), 'inactive' => __( 'Inactivo', 'super-mechanic' ) ) );
		$this->render_text_row( 'timezone', __( 'Timezone', 'super-mechanic' ), (string) $data['timezone'], true );
		$this->render_text_row( 'currency', __( 'Moneda', 'super-mechanic' ), (string) $data['currency'], true );
		$this->render_text_row( 'branding_logo_attachment_id', __( 'Branding logo attachment_id', 'super-mechanic' ), (string) absint( $data['branding_logo_attachment_id'] ), false );
		$this->render_text_row( 'primary_color', __( 'Color primario', 'super-mechanic' ), (string) $data['primary_color'], false );
		echo '<tr><th scope="row">' . esc_html__( 'Default legacy', 'super-mechanic' ) . '</th><td><label><input type="checkbox" name="is_default" value="1" ' . checked( absint( $data['is_default'] ), 1, false ) . ' /> ' . esc_html__( 'Marcar como negocio por defecto', 'super-mechanic' ) . '</label></td></tr>';
		echo '</table>';
		echo '<div class="sm-form-actions">';
		submit_button( $is_edit ? __( 'Actualizar negocio', 'super-mechanic' ) : __( 'Crear negocio', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</div>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render text row.
	 *
	 * @param string $name     Field name.
	 * @param string $label    Label.
	 * @param string $value    Value.
	 * @param bool   $required Required.
	 * @return void
	 */
	protected function render_text_row( $name, $label, $value, $required ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . ' />';
		echo '</td></tr>';
	}

	/**
	 * Render select row.
	 *
	 * @param string               $name    Name.
	 * @param string               $label   Label.
	 * @param string               $value   Value.
	 * @param array<string,string> $options Options.
	 * @return void
	 */
	protected function render_select_row( $name, $label, $value, array $options ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $options as $key => $option_label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	/**
	 * Render notices from query params.
	 *
	 * @return void
	 */
	protected function render_notice() {
		$type    = isset( $_GET['sm_business_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_business_notice'] ) ) : '';
		$message = isset( $_GET['sm_business_message'] ) ? sanitize_text_field( wp_unslash( $_GET['sm_business_message'] ) ) : '';

		if ( '' === $type || '' === $message ) {
			return;
		}

		$class = 'success' === $type ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Redirect with notice.
	 *
	 * @param string $type       Type.
	 * @param string $message    Message.
	 * @param int    $business_id Business ID.
	 * @return void
	 */
	protected function redirect_with_notice( $type, $message, $business_id ) {
		$args = array(
			'page'                => 'super-mechanic-businesses',
			'sm_business_notice'  => $type,
			'sm_business_message' => $message,
		);

		if ( $business_id > 0 ) {
			$args['action'] = 'edit';
			$args['id']     = $business_id;
		}

		$target = add_query_arg( $args, admin_url( 'admin.php' ) );
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Assert page permissions.
	 *
	 * @return void
	 */
	protected function assert_permissions() {
		if ( ! current_user_can( 'sm_manage_settings' ) ) {
			wp_die( esc_html__( 'No tienes permisos para gestionar negocios.', 'super-mechanic' ) );
		}
	}
}

