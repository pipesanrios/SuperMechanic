<?php
/**
 * Connectors admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Integrations\Connectors\Connector_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles connectors admin UI.
 */
class Connectors_Admin_Controller {
	/**
	 * Connector service dependency.
	 *
	 * @var Connector_Service
	 */
	protected $connector_service;

	/**
	 * Constructor.
	 *
	 * @param Connector_Service|null $connector_service Service dependency.
	 */
	public function __construct( Connector_Service $connector_service = null ) {
		$this->connector_service = $connector_service ? $connector_service : new Connector_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 109 );
		add_action( 'admin_post_sm_save_connector', array( $this, 'handle_save_connector' ) );
		add_action( 'admin_post_sm_delete_connector', array( $this, 'handle_delete_connector' ) );
		add_action( 'admin_post_sm_toggle_connector', array( $this, 'handle_toggle_connector' ) );
		add_action( 'admin_post_sm_test_connector', array( $this, 'handle_test_connector' ) );
	}

	/**
	 * Register submenu page.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Connectors', 'super-mechanic' ),
			__( 'Connectors', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-connectors',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render connectors page.
	 *
	 * @return void
	 */
	public function render_page() {
		$this->assert_manage_permission();

		$connectors        = $this->connector_service->get_connectors();
		$supported_types   = $this->connector_service->get_supported_connector_types();
		$supported_events  = $this->connector_service->get_supported_events();
		$edit_connector    = null;
		$edit_connector_id = isset( $_GET['edit_connector_id'] ) ? absint( wp_unslash( $_GET['edit_connector_id'] ) ) : 0;

		if ( $edit_connector_id > 0 ) {
			$edit_connector = $this->connector_service->get_connector( $edit_connector_id );
		}

		echo '<div class="wrap sm-admin-shell">';
		echo '<h1>' . esc_html__( 'External Connectors', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Configure outbound connectors for formalized commercial and operational events.', 'super-mechanic' ) . '</p>';

		$this->render_notice();
		$this->render_form( $supported_types, $supported_events, $edit_connector );
		$this->render_table( $connectors );

		echo '</div>';
	}

	/**
	 * Handle create/update connector action.
	 *
	 * @return void
	 */
	public function handle_save_connector() {
		$this->assert_manage_permission();
		check_admin_referer( 'sm_save_connector' );

		$connector_id = isset( $_POST['connector_id'] ) ? absint( wp_unslash( $_POST['connector_id'] ) ) : 0;
		$payload      = array(
			'name'           => isset( $_POST['name'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['name'] ) ) : '',
			'connector_type' => isset( $_POST['connector_type'] ) ? sanitize_key( (string) wp_unslash( $_POST['connector_type'] ) ) : '',
			'endpoint_url'   => isset( $_POST['endpoint_url'] ) ? esc_url_raw( (string) wp_unslash( $_POST['endpoint_url'] ) ) : '',
			'event_name'     => isset( $_POST['event_name'] ) ? $this->sanitize_event_name( (string) wp_unslash( $_POST['event_name'] ) ) : '',
			'config_json'    => isset( $_POST['config_json'] ) ? (string) wp_unslash( $_POST['config_json'] ) : '{}',
			'status'         => isset( $_POST['status'] ) ? sanitize_key( (string) wp_unslash( $_POST['status'] ) ) : 'active',
		);

		$result = $connector_id > 0
			? $this->connector_service->update_connector( $connector_id, $payload )
			: $this->connector_service->create_connector( $payload );

		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'success', 'save_success' );
		}

		$this->redirect_with_notice(
			'error',
			'save_failed',
			array(
				'sm_notice_message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '',
			)
		);
	}

	/**
	 * Handle delete connector action.
	 *
	 * @return void
	 */
	public function handle_delete_connector() {
		$this->assert_manage_permission();

		$connector_id = isset( $_GET['connector_id'] ) ? absint( wp_unslash( $_GET['connector_id'] ) ) : 0;
		check_admin_referer( 'sm_delete_connector_' . $connector_id );

		$result = $this->connector_service->delete_connector( $connector_id );
		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'success', 'delete_success' );
		}

		$this->redirect_with_notice(
			'error',
			'delete_failed',
			array(
				'sm_notice_message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '',
			)
		);
	}

	/**
	 * Handle activate/deactivate connector action.
	 *
	 * @return void
	 */
	public function handle_toggle_connector() {
		$this->assert_manage_permission();

		$connector_id = isset( $_GET['connector_id'] ) ? absint( wp_unslash( $_GET['connector_id'] ) ) : 0;
		$status       = isset( $_GET['status'] ) ? sanitize_key( (string) wp_unslash( $_GET['status'] ) ) : '';
		check_admin_referer( 'sm_toggle_connector_' . $connector_id );

		$result = $this->connector_service->set_connector_status( $connector_id, $status );
		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'success', 'toggle_success' );
		}

		$this->redirect_with_notice(
			'error',
			'toggle_failed',
			array(
				'sm_notice_message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '',
			)
		);
	}

	/**
	 * Handle test dispatch action.
	 *
	 * @return void
	 */
	public function handle_test_connector() {
		$this->assert_manage_permission();

		$connector_id = isset( $_GET['connector_id'] ) ? absint( wp_unslash( $_GET['connector_id'] ) ) : 0;
		check_admin_referer( 'sm_test_connector_' . $connector_id );

		$result = $this->connector_service->test_dispatch_connector( $connector_id );
		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'success', 'test_success' );
		}

		$this->redirect_with_notice(
			'error',
			'test_failed',
			array(
				'sm_notice_message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '',
			)
		);
	}

	/**
	 * Render notice.
	 *
	 * @return void
	 */
	protected function render_notice() {
		$notice_type    = isset( $_GET['sm_notice_type'] ) ? sanitize_key( (string) wp_unslash( $_GET['sm_notice_type'] ) ) : '';
		$notice_code    = isset( $_GET['sm_notice_code'] ) ? sanitize_key( (string) wp_unslash( $_GET['sm_notice_code'] ) ) : '';
		$notice_message = isset( $_GET['sm_notice_message'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['sm_notice_message'] ) ) : '';

		if ( '' === $notice_type || '' === $notice_code ) {
			return;
		}

		$messages = array(
			'save_success'   => __( 'Connector saved successfully.', 'super-mechanic' ),
			'save_failed'    => __( 'Could not save connector.', 'super-mechanic' ),
			'delete_success' => __( 'Connector deleted successfully.', 'super-mechanic' ),
			'delete_failed'  => __( 'Could not delete connector.', 'super-mechanic' ),
			'toggle_success' => __( 'Connector status updated successfully.', 'super-mechanic' ),
			'toggle_failed'  => __( 'Could not update connector status.', 'super-mechanic' ),
			'test_success'   => __( 'Test dispatch sent successfully.', 'super-mechanic' ),
			'test_failed'    => __( 'Test dispatch failed.', 'super-mechanic' ),
		);

		$message = isset( $messages[ $notice_code ] ) ? $messages[ $notice_code ] : '';
		if ( '' === $message && '' === $notice_message ) {
			return;
		}

		$final_message = '' !== $notice_message ? $notice_message : $message;
		echo '<div class="notice notice-' . esc_attr( 'success' === $notice_type ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $final_message ) . '</p></div>';
	}

	/**
	 * Render create/edit form.
	 *
	 * @param array<int,string>        $supported_types  Connector types.
	 * @param array<int,string>        $supported_events Event names.
	 * @param array<string,mixed>|null $connector        Editing connector.
	 * @return void
	 */
	protected function render_form( array $supported_types, array $supported_events, $connector = null ) {
		$connector_id   = is_array( $connector ) && isset( $connector['id'] ) ? absint( $connector['id'] ) : 0;
		$name           = is_array( $connector ) && isset( $connector['name'] ) ? sanitize_text_field( (string) $connector['name'] ) : '';
		$connector_type = is_array( $connector ) && isset( $connector['connector_type'] ) ? sanitize_key( (string) $connector['connector_type'] ) : 'webhook';
		$endpoint_url   = is_array( $connector ) && isset( $connector['endpoint_url'] ) ? esc_url_raw( (string) $connector['endpoint_url'] ) : '';
		$event_name     = is_array( $connector ) && isset( $connector['event_name'] ) ? $this->sanitize_event_name( (string) $connector['event_name'] ) : '';
		$config_json    = is_array( $connector ) && isset( $connector['config_json'] ) ? (string) $connector['config_json'] : '{}';
		$status         = is_array( $connector ) && isset( $connector['status'] ) ? sanitize_key( (string) $connector['status'] ) : 'active';

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html( $connector_id > 0 ? __( 'Edit connector', 'super-mechanic' ) : __( 'Create connector', 'super-mechanic' ) ) . '</h2></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'sm_save_connector' );
		echo '<input type="hidden" name="action" value="sm_save_connector" />';
		echo '<input type="hidden" name="connector_id" value="' . esc_attr( (string) $connector_id ) . '" />';

		echo '<div class="sm-filter-grid">';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Name', 'super-mechanic' ) . '</span><input type="text" name="name" value="' . esc_attr( $name ) . '" required /></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Type', 'super-mechanic' ) . '</span><select name="connector_type" required>';
		echo '<option value="">' . esc_html__( 'Select type', 'super-mechanic' ) . '</option>';
		foreach ( $supported_types as $type ) {
			$label = ucwords( str_replace( '_', ' ', (string) $type ) );
			echo '<option value="' . esc_attr( (string) $type ) . '"' . selected( $connector_type, $type, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Endpoint URL', 'super-mechanic' ) . '</span><input type="url" name="endpoint_url" value="' . esc_attr( $endpoint_url ) . '" placeholder="https://example.com/endpoint" required /></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Event', 'super-mechanic' ) . '</span><select name="event_name" required>';
		echo '<option value="">' . esc_html__( 'Select event', 'super-mechanic' ) . '</option>';
		foreach ( $supported_events as $event ) {
			echo '<option value="' . esc_attr( (string) $event ) . '"' . selected( $event_name, $event, false ) . '>' . esc_html( (string) $event ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Status', 'super-mechanic' ) . '</span><select name="status">';
		echo '<option value="active"' . selected( $status, 'active', false ) . '>' . esc_html__( 'Active', 'super-mechanic' ) . '</option>';
		echo '<option value="inactive"' . selected( $status, 'inactive', false ) . '>' . esc_html__( 'Inactive', 'super-mechanic' ) . '</option>';
		echo '</select></label>';
		echo '<label class="sm-filter-field sm-filter-field--full"><span>' . esc_html__( 'Config JSON (optional)', 'super-mechanic' ) . '</span><textarea name="config_json" rows="4">' . esc_textarea( $config_json ) . '</textarea></label>';
		echo '</div>';

		echo '<div class="sm-form-actions">';
		echo '<button type="submit" class="button button-primary">' . esc_html( $connector_id > 0 ? __( 'Update connector', 'super-mechanic' ) : __( 'Create connector', 'super-mechanic' ) ) . '</button>';
		if ( $connector_id > 0 ) {
			echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=super-mechanic-connectors' ) ) . '">' . esc_html__( 'Cancel edit', 'super-mechanic' ) . '</a>';
		}
		echo '</div>';
		echo '</form>';
		echo '</section>';
	}

	/**
	 * Render connectors table.
	 *
	 * @param array<int,array<string,mixed>> $connectors Connectors rows.
	 * @return void
	 */
	protected function render_table( array $connectors ) {
		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Configured connectors', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap">';
		echo '<table class="sm-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Event', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Endpoint', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Created', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Updated', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $connectors ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No connectors configured yet.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $connectors as $connector ) {
				if ( ! is_array( $connector ) ) {
					continue;
				}

				$connector_id   = isset( $connector['id'] ) ? absint( $connector['id'] ) : 0;
				$name           = isset( $connector['name'] ) ? sanitize_text_field( (string) $connector['name'] ) : '';
				$connector_type = isset( $connector['connector_type'] ) ? sanitize_key( (string) $connector['connector_type'] ) : '';
				$event_name     = isset( $connector['event_name'] ) ? $this->sanitize_event_name( (string) $connector['event_name'] ) : '';
				$endpoint_url   = isset( $connector['endpoint_url'] ) ? esc_url_raw( (string) $connector['endpoint_url'] ) : '';
				$status         = isset( $connector['status'] ) ? sanitize_key( (string) $connector['status'] ) : 'inactive';
				$created_at     = isset( $connector['created_at'] ) ? sanitize_text_field( (string) $connector['created_at'] ) : '';
				$updated_at     = isset( $connector['updated_at'] ) ? sanitize_text_field( (string) $connector['updated_at'] ) : '';

				$edit_url = add_query_arg(
					array(
						'page'              => 'super-mechanic-connectors',
						'edit_connector_id' => $connector_id,
					),
					admin_url( 'admin.php' )
				);
				$toggle_url = wp_nonce_url(
					add_query_arg(
						array(
							'action'       => 'sm_toggle_connector',
							'connector_id' => $connector_id,
							'status'       => 'active' === $status ? 'inactive' : 'active',
						),
						admin_url( 'admin-post.php' )
					),
					'sm_toggle_connector_' . $connector_id
				);
				$delete_url = wp_nonce_url(
					add_query_arg(
						array(
							'action'       => 'sm_delete_connector',
							'connector_id' => $connector_id,
						),
						admin_url( 'admin-post.php' )
					),
					'sm_delete_connector_' . $connector_id
				);
				$test_url   = wp_nonce_url(
					add_query_arg(
						array(
							'action'       => 'sm_test_connector',
							'connector_id' => $connector_id,
						),
						admin_url( 'admin-post.php' )
					),
					'sm_test_connector_' . $connector_id
				);

				echo '<tr>';
				echo '<td><strong>' . esc_html( $name ) . '</strong><br /><code>#' . esc_html( (string) $connector_id ) . '</code></td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $connector_type ) ) ) . '</td>';
				echo '<td><code>' . esc_html( $event_name ) . '</code></td>';
				echo '<td><a href="' . esc_url( $endpoint_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $endpoint_url ) . '</a></td>';
				echo '<td>' . wp_kses_post( $this->render_status_badge( 'active' === $status ) ) . '</td>';
				echo '<td>' . esc_html( $created_at ) . '</td>';
				echo '<td>' . esc_html( $updated_at ) . '</td>';
				echo '<td><div class="sm-webhook-actions">';
				echo '<a class="button button-small" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'super-mechanic' ) . '</a>';
				echo '<a class="button button-small" href="' . esc_url( $toggle_url ) . '">' . esc_html( 'active' === $status ? __( 'Deactivate', 'super-mechanic' ) : __( 'Activate', 'super-mechanic' ) ) . '</a>';
				echo '<a class="button button-small" href="' . esc_url( $test_url ) . '">' . esc_html__( 'Send test', 'super-mechanic' ) . '</a>';
				echo '<a class="button button-small button-link-delete" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this connector?', 'super-mechanic' ) ) . '\');">' . esc_html__( 'Delete', 'super-mechanic' ) . '</a>';
				echo '</div></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render status badge.
	 *
	 * @param bool $is_active Active status.
	 * @return string
	 */
	protected function render_status_badge( $is_active ) {
		if ( $is_active ) {
			return '<span class="sm-badge sm-badge-success">' . esc_html__( 'Active', 'super-mechanic' ) . '</span>';
		}

		return '<span class="sm-badge sm-badge-neutral">' . esc_html__( 'Inactive', 'super-mechanic' ) . '</span>';
	}

	/**
	 * Ensure user has management capability.
	 *
	 * @return void
	 */
	protected function assert_manage_permission() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'super-mechanic' ) );
		}
	}

	/**
	 * Sanitize event name preserving dot notation.
	 *
	 * @param string $event_name Event name.
	 * @return string
	 */
	protected function sanitize_event_name( $event_name ) {
		$event_name = strtolower( trim( (string) $event_name ) );
		$event_name = preg_replace( '/[^a-z0-9._-]/', '', $event_name );

		return is_string( $event_name ) ? $event_name : '';
	}

	/**
	 * Redirect with admin notice.
	 *
	 * @param string              $type  Notice type.
	 * @param string              $code  Notice code.
	 * @param array<string,mixed> $extra Extra query arguments.
	 * @return void
	 */
	protected function redirect_with_notice( $type, $code, array $extra = array() ) {
		$args = array_merge(
			array(
				'page'           => 'super-mechanic-connectors',
				'sm_notice_type' => sanitize_key( (string) $type ),
				'sm_notice_code' => sanitize_key( (string) $code ),
			),
			$extra
		);

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
