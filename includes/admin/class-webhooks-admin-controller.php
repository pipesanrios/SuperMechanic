<?php
/**
 * Webhooks admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Webhooks\Webhook_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles webhook admin UI.
 */
class Webhooks_Admin_Controller {
	/**
	 * Webhook service.
	 *
	 * @var Webhook_Service
	 */
	protected $webhook_service;

	/**
	 * Constructor.
	 *
	 * @param Webhook_Service|null $webhook_service Service dependency.
	 */
	public function __construct( Webhook_Service $webhook_service = null ) {
		$this->webhook_service = $webhook_service ? $webhook_service : new Webhook_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 106 );
		add_action( 'admin_post_sm_save_webhook', array( $this, 'handle_save_webhook' ) );
		add_action( 'admin_post_sm_toggle_webhook', array( $this, 'handle_toggle_webhook' ) );
		add_action( 'admin_post_sm_delete_webhook', array( $this, 'handle_delete_webhook' ) );
		add_action( 'admin_post_sm_test_webhook', array( $this, 'handle_test_webhook' ) );
	}

	/**
	 * Register submenu page.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Webhooks', 'super-mechanic' ),
			__( 'Webhooks', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-webhooks',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		$webhooks        = $this->webhook_service->get_webhooks();
		$events          = $this->webhook_service->get_supported_events_for_admin();
		$edit_webhook    = null;
		$edit_webhook_id = isset( $_GET['edit_webhook_id'] ) ? absint( wp_unslash( $_GET['edit_webhook_id'] ) ) : 0;
		if ( $edit_webhook_id > 0 ) {
			$edit_webhook = $this->webhook_service->get_webhook_by_id( $edit_webhook_id );
		}

		echo '<div class="wrap sm-admin-shell">';
		echo '<h1>' . esc_html__( 'Webhooks', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Manage outbound webhook endpoints for operational events.', 'super-mechanic' ) . '</p>';

		$this->render_notice();
		$this->render_form( $events, $edit_webhook );
		$this->render_table( $webhooks );

		echo '</div>';
	}

	/**
	 * Handle create/update action.
	 *
	 * @return void
	 */
	public function handle_save_webhook() {
		$this->assert_manage_permission();
		check_admin_referer( 'sm_save_webhook' );

		$webhook_id = isset( $_POST['webhook_id'] ) ? absint( wp_unslash( $_POST['webhook_id'] ) ) : 0;
		$payload    = array(
			'name'       => isset( $_POST['name'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['name'] ) ) : '',
			'url'        => isset( $_POST['url'] ) ? esc_url_raw( (string) wp_unslash( $_POST['url'] ) ) : '',
			'event_type' => isset( $_POST['event_type'] ) ? $this->sanitize_event_type( (string) wp_unslash( $_POST['event_type'] ) ) : '',
			'secret_key' => isset( $_POST['secret_key'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['secret_key'] ) ) : '',
			'is_active'  => ! empty( $_POST['is_active'] ) ? 1 : 0,
		);

		$result = $webhook_id > 0
			? $this->webhook_service->update_webhook( $webhook_id, $payload )
			: $this->webhook_service->create_webhook( $payload );

		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'success', 'save_success' );
		}

		$this->redirect_with_notice(
			'error',
			'save_failed',
			array( 'sm_notice_message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '' )
		);
	}

	/**
	 * Handle active/inactive action.
	 *
	 * @return void
	 */
	public function handle_toggle_webhook() {
		$this->assert_manage_permission();
		$webhook_id = isset( $_GET['webhook_id'] ) ? absint( wp_unslash( $_GET['webhook_id'] ) ) : 0;
		$is_active  = isset( $_GET['is_active'] ) ? absint( wp_unslash( $_GET['is_active'] ) ) : 0;
		check_admin_referer( 'sm_toggle_webhook_' . $webhook_id );

		$result = $this->webhook_service->set_webhook_active( $webhook_id, 1 === $is_active );
		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'success', 'toggle_success' );
		}

		$this->redirect_with_notice(
			'error',
			'toggle_failed',
			array( 'sm_notice_message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '' )
		);
	}

	/**
	 * Handle delete action.
	 *
	 * @return void
	 */
	public function handle_delete_webhook() {
		$this->assert_manage_permission();
		$webhook_id = isset( $_GET['webhook_id'] ) ? absint( wp_unslash( $_GET['webhook_id'] ) ) : 0;
		check_admin_referer( 'sm_delete_webhook_' . $webhook_id );

		$result = $this->webhook_service->delete_webhook( $webhook_id );
		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'success', 'delete_success' );
		}

		$this->redirect_with_notice(
			'error',
			'delete_failed',
			array( 'sm_notice_message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '' )
		);
	}

	/**
	 * Handle test action.
	 *
	 * @return void
	 */
	public function handle_test_webhook() {
		$this->assert_manage_permission();
		$webhook_id = isset( $_GET['webhook_id'] ) ? absint( wp_unslash( $_GET['webhook_id'] ) ) : 0;
		check_admin_referer( 'sm_test_webhook_' . $webhook_id );

		$result = $this->webhook_service->send_test_webhook( $webhook_id );
		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'success', 'test_success' );
		}

		$this->redirect_with_notice(
			'error',
			'test_failed',
			array( 'sm_notice_message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '' )
		);
	}

	/**
	 * Render page notice.
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
			'save_success'   => __( 'Webhook saved successfully.', 'super-mechanic' ),
			'save_failed'    => __( 'Could not save webhook.', 'super-mechanic' ),
			'toggle_success' => __( 'Webhook status updated successfully.', 'super-mechanic' ),
			'toggle_failed'  => __( 'Could not update webhook status.', 'super-mechanic' ),
			'delete_success' => __( 'Webhook deleted successfully.', 'super-mechanic' ),
			'delete_failed'  => __( 'Could not delete webhook.', 'super-mechanic' ),
			'test_success'   => __( 'Webhook test sent successfully.', 'super-mechanic' ),
			'test_failed'    => __( 'Webhook test failed.', 'super-mechanic' ),
		);
		$message  = isset( $messages[ $notice_code ] ) ? $messages[ $notice_code ] : '';
		if ( '' === $message && '' === $notice_message ) {
			return;
		}

		$final_message = '' !== $notice_message ? $notice_message : $message;
		echo '<div class="notice notice-' . esc_attr( 'success' === $notice_type ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $final_message ) . '</p></div>';
	}

	/**
	 * Render create/edit form.
	 *
	 * @param array<int,string>        $events Supported events.
	 * @param array<string,mixed>|null $webhook Editing webhook.
	 * @return void
	 */
	protected function render_form( array $events, $webhook = null ) {
		$webhook_id = is_array( $webhook ) && isset( $webhook['id'] ) ? absint( $webhook['id'] ) : 0;
		$name       = is_array( $webhook ) && isset( $webhook['name'] ) ? sanitize_text_field( (string) $webhook['name'] ) : '';
		$url        = is_array( $webhook ) && isset( $webhook['url'] ) ? esc_url_raw( (string) $webhook['url'] ) : '';
		$event_type = is_array( $webhook ) && isset( $webhook['event_type'] ) ? $this->webhook_service->get_canonical_event_name( (string) $webhook['event_type'] ) : '';
		$secret_key = is_array( $webhook ) && isset( $webhook['secret_key'] ) ? sanitize_text_field( (string) $webhook['secret_key'] ) : '';
		$is_active  = is_array( $webhook ) && isset( $webhook['is_active'] ) ? absint( $webhook['is_active'] ) : 1;

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html( $webhook_id > 0 ? __( 'Edit webhook', 'super-mechanic' ) : __( 'Create webhook', 'super-mechanic' ) ) . '</h2></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sm-webhook-form">';
		wp_nonce_field( 'sm_save_webhook' );
		echo '<input type="hidden" name="action" value="sm_save_webhook" />';
		echo '<input type="hidden" name="webhook_id" value="' . esc_attr( (string) $webhook_id ) . '" />';

		echo '<div class="sm-filter-grid">';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Name', 'super-mechanic' ) . '</span><input type="text" name="name" value="' . esc_attr( $name ) . '" required /></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'URL', 'super-mechanic' ) . '</span><input type="url" name="url" value="' . esc_attr( $url ) . '" placeholder="https://example.com/webhook" required /></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Event type', 'super-mechanic' ) . '</span><select name="event_type" required>';
		echo '<option value="">' . esc_html__( 'Select event', 'super-mechanic' ) . '</option>';
		foreach ( $events as $event ) {
			echo '<option value="' . esc_attr( $event ) . '"' . selected( $event_type, $event, false ) . '>' . esc_html( $event ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Secret key (optional)', 'super-mechanic' ) . '</span><input type="text" name="secret_key" value="' . esc_attr( $secret_key ) . '" /></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Status', 'super-mechanic' ) . '</span><select name="is_active">';
		echo '<option value="1"' . selected( 1, $is_active, false ) . '>' . esc_html__( 'Active', 'super-mechanic' ) . '</option>';
		echo '<option value="0"' . selected( 0, $is_active, false ) . '>' . esc_html__( 'Inactive', 'super-mechanic' ) . '</option>';
		echo '</select></label>';
		echo '</div>';

		echo '<div class="sm-form-actions">';
		echo '<button type="submit" class="button button-primary">' . esc_html( $webhook_id > 0 ? __( 'Update webhook', 'super-mechanic' ) : __( 'Create webhook', 'super-mechanic' ) ) . '</button>';
		if ( $webhook_id > 0 ) {
			echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=super-mechanic-webhooks' ) ) . '">' . esc_html__( 'Cancel edit', 'super-mechanic' ) . '</a>';
		}
		echo '</div>';
		echo '</form>';
		echo '</section>';
	}

	/**
	 * Render webhook list table.
	 *
	 * @param array<int,array<string,mixed>> $webhooks Webhooks.
	 * @return void
	 */
	protected function render_table( array $webhooks ) {
		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Webhook endpoints', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap">';
		echo '<table class="sm-table sm-webhooks-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'URL', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Event type', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Secret key', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Created', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Updated', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $webhooks ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No webhooks configured yet.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $webhooks as $webhook ) {
				if ( ! is_array( $webhook ) ) {
					continue;
				}

				$webhook_id = isset( $webhook['id'] ) ? absint( $webhook['id'] ) : 0;
				$name       = isset( $webhook['name'] ) ? sanitize_text_field( (string) $webhook['name'] ) : '';
				$url        = isset( $webhook['url'] ) ? esc_url_raw( (string) $webhook['url'] ) : '';
				$event_type = isset( $webhook['event_type'] ) ? $this->webhook_service->get_canonical_event_name( (string) $webhook['event_type'] ) : '';
				$is_active  = isset( $webhook['is_active'] ) ? absint( $webhook['is_active'] ) : 0;
				$secret_key = isset( $webhook['secret_key'] ) ? sanitize_text_field( (string) $webhook['secret_key'] ) : '';
				$created_at = isset( $webhook['created_at'] ) ? sanitize_text_field( (string) $webhook['created_at'] ) : '';
				$updated_at = isset( $webhook['updated_at'] ) ? sanitize_text_field( (string) $webhook['updated_at'] ) : '';

				$edit_url = add_query_arg(
					array(
						'page'            => 'super-mechanic-webhooks',
						'edit_webhook_id' => $webhook_id,
					),
					admin_url( 'admin.php' )
				);
				$toggle_url = wp_nonce_url(
					add_query_arg(
						array(
							'action'     => 'sm_toggle_webhook',
							'webhook_id' => $webhook_id,
							'is_active'  => $is_active ? 0 : 1,
						),
						admin_url( 'admin-post.php' )
					),
					'sm_toggle_webhook_' . $webhook_id
				);
				$delete_url = wp_nonce_url(
					add_query_arg(
						array(
							'action'     => 'sm_delete_webhook',
							'webhook_id' => $webhook_id,
						),
						admin_url( 'admin-post.php' )
					),
					'sm_delete_webhook_' . $webhook_id
				);
				$test_url   = wp_nonce_url(
					add_query_arg(
						array(
							'action'     => 'sm_test_webhook',
							'webhook_id' => $webhook_id,
						),
						admin_url( 'admin-post.php' )
					),
					'sm_test_webhook_' . $webhook_id
				);

				echo '<tr>';
				echo '<td>' . esc_html( $name ) . '</td>';
				echo '<td><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a></td>';
				echo '<td>' . esc_html( $event_type ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_status_badge( $is_active > 0 ) ) . '</td>';
				echo '<td>' . esc_html( $this->mask_secret_key( $secret_key ) ) . '</td>';
				echo '<td>' . esc_html( $created_at ) . '</td>';
				echo '<td>' . esc_html( $updated_at ) . '</td>';
				echo '<td><div class="sm-webhook-actions">';
				echo '<a class="button button-small" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'super-mechanic' ) . '</a>';
				echo '<a class="button button-small" href="' . esc_url( $toggle_url ) . '">' . esc_html( $is_active > 0 ? __( 'Deactivate', 'super-mechanic' ) : __( 'Activate', 'super-mechanic' ) ) . '</a>';
				echo '<a class="button button-small" href="' . esc_url( $test_url ) . '">' . esc_html__( 'Send test', 'super-mechanic' ) . '</a>';
				echo '<a class="button button-small button-link-delete" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this webhook?', 'super-mechanic' ) ) . '\');">' . esc_html__( 'Delete', 'super-mechanic' ) . '</a>';
				echo '</div></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render active status badge.
	 *
	 * @param bool $is_active Active flag.
	 * @return string
	 */
	protected function render_status_badge( $is_active ) {
		if ( $is_active ) {
			return '<span class="sm-badge sm-badge-success">' . esc_html__( 'Active', 'super-mechanic' ) . '</span>';
		}

		return '<span class="sm-badge sm-badge-neutral">' . esc_html__( 'Inactive', 'super-mechanic' ) . '</span>';
	}

	/**
	 * Mask secret key for list rendering.
	 *
	 * @param string $secret_key Secret key.
	 * @return string
	 */
	protected function mask_secret_key( $secret_key ) {
		$secret_key = (string) $secret_key;
		if ( '' === $secret_key ) {
			return '-';
		}

		$length = strlen( $secret_key );
		if ( $length <= 4 ) {
			return str_repeat( '*', $length );
		}

		return str_repeat( '*', $length - 4 ) . substr( $secret_key, -4 );
	}

	/**
	 * Ensure user has manage capability.
	 *
	 * @return void
	 */
	protected function assert_manage_permission() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'super-mechanic' ) );
		}
	}

	/**
	 * Sanitize event type while preserving canonical dot notation.
	 *
	 * @param string $event_type Raw event type.
	 * @return string
	 */
	protected function sanitize_event_type( $event_type ) {
		$event_type = strtolower( trim( (string) $event_type ) );
		$event_type = preg_replace( '/[^a-z0-9._-]/', '', $event_type );

		return is_string( $event_type ) ? $event_type : '';
	}

	/**
	 * Redirect to page with notice.
	 *
	 * @param string              $type Type.
	 * @param string              $code Code.
	 * @param array<string,mixed> $extra Extra query args.
	 * @return void
	 */
	protected function redirect_with_notice( $type, $code, array $extra = array() ) {
		$args = array_merge(
			array(
				'page'           => 'super-mechanic-webhooks',
				'sm_notice_type' => sanitize_key( (string) $type ),
				'sm_notice_code' => sanitize_key( (string) $code ),
			),
			$extra
		);

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}








