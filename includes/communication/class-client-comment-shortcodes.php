<?php
/**
 * Client comment shortcodes.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Communication;

use Super_Mechanic\Dashboard\Client_Dashboard_Controller;
use Super_Mechanic\Dashboard\Dashboard_Service;
use Super_Mechanic\Helpers\Permission_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Registers client comment and notification shortcodes.
 */
class Client_Comment_Shortcodes {
	protected $comment_service;
	protected $notification_service;
	protected $dashboard_service;
	protected $client_dashboard_controller;
	protected $permission_service;

	public function __construct( Comment_Service $comment_service = null, Notification_Service $notification_service = null, Dashboard_Service $dashboard_service = null, Client_Dashboard_Controller $client_dashboard_controller = null, Permission_Service $permission_service = null ) {
		$this->comment_service             = $comment_service ? $comment_service : new Comment_Service();
		$this->notification_service        = $notification_service ? $notification_service : new Notification_Service();
		$this->dashboard_service           = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->client_dashboard_controller = $client_dashboard_controller ? $client_dashboard_controller : new Client_Dashboard_Controller();
		$this->permission_service          = $permission_service ? $permission_service : new Permission_Service();
	}

	public function register_hooks() {
		add_action( 'init', array( $this, 'maybe_handle_form_submission' ) );
		add_shortcode( 'sm_client_process_comments', array( $this, 'render_client_process_comments' ) );
		add_shortcode( 'sm_client_process_comment_form', array( $this, 'render_client_process_comment_form' ) );
		add_shortcode( 'sm_client_notifications', array( $this, 'render_client_notifications' ) );
	}

	public function maybe_handle_form_submission() {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';

		if ( 'POST' !== $request_method ) {
			return;
		}

		$operation = isset( $_POST['sm_client_comment_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_client_comment_operation'] ) ) : '';

		if ( 'create' === $operation ) {
			$this->handle_comment_submission();
		}

		if ( 'mark_read' === $operation ) {
			$this->handle_mark_read_submission();
		}

		if ( 'mark_all_read' === $operation ) {
			$this->handle_mark_all_read_submission();
		}
	}

	public function render_client_process_comments( $atts = array() ) {
		$atts = shortcode_atts( array( 'process_id' => 0 ), $atts, 'sm_client_process_comments' );

		if ( ! $this->can_render_client_content() ) {
			return $this->get_access_denied_message();
		}

		$process_id = absint( $atts['process_id'] );
		if ( ! $process_id && isset( $_GET['process_id'] ) ) {
			$process_id = absint( wp_unslash( $_GET['process_id'] ) );
		}

		if ( ! $process_id || ! $this->dashboard_service->user_can_access_client_process( get_current_user_id(), $process_id ) ) {
			return '<p>' . esc_html__( 'No tienes acceso a los comentarios de este proceso.', 'super-mechanic' ) . '</p>';
		}

		return $this->client_dashboard_controller->render_process_comments( $process_id, get_current_user_id() );
	}

	public function render_client_process_comment_form( $atts = array() ) {
		$atts = shortcode_atts( array( 'process_id' => 0 ), $atts, 'sm_client_process_comment_form' );

		if ( ! $this->can_render_client_content() ) {
			return $this->get_access_denied_message();
		}

		$process_id = absint( $atts['process_id'] );
		if ( ! $process_id && isset( $_GET['process_id'] ) ) {
			$process_id = absint( wp_unslash( $_GET['process_id'] ) );
		}

		if ( ! $process_id || ! $this->dashboard_service->user_can_access_client_process( get_current_user_id(), $process_id ) ) {
			return '<p>' . esc_html__( 'No tienes acceso a este formulario.', 'super-mechanic' ) . '</p>';
		}

		ob_start();
		if ( isset( $_GET['sm_comment_notice'] ) && 'created' === sanitize_key( wp_unslash( $_GET['sm_comment_notice'] ) ) ) {
			echo '<div class="sm-notice-success"><p>' . esc_html__( 'Mensaje enviado correctamente.', 'super-mechanic' ) . '</p></div>';
		}

		if ( isset( $_GET['sm_comment_notice'] ) && 'error' === sanitize_key( wp_unslash( $_GET['sm_comment_notice'] ) ) ) {
			echo '<div class="sm-notice-error"><p>' . esc_html__( 'No fue posible enviar el mensaje.', 'super-mechanic' ) . '</p></div>';
		}

		echo '<form method="post" class="sm-client-comment-form">';
		wp_nonce_field( 'sm_client_comment_form', 'sm_client_comment_nonce' );
		echo '<input type="hidden" name="sm_client_comment_operation" value="create" />';
		echo '<input type="hidden" name="process_id" value="' . esc_attr( $process_id ) . '" />';
		echo '<p><label for="sm_client_comment_content">' . esc_html__( 'Mensaje', 'super-mechanic' ) . '</label><br />';
		echo '<textarea id="sm_client_comment_content" name="content" rows="5" class="widefat" required></textarea></p>';
		echo '<p><button type="submit">' . esc_html__( 'Enviar mensaje', 'super-mechanic' ) . '</button></p>';
		echo '</form>';

		return (string) ob_get_clean();
	}

	public function render_client_notifications( $atts = array() ) {
		$atts = shortcode_atts( array(), $atts, 'sm_client_notifications' );

		if ( ! $this->can_render_client_content() ) {
			return $this->get_access_denied_message();
		}

		return $this->client_dashboard_controller->render_client_notifications( get_current_user_id() );
	}

	protected function handle_comment_submission() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		check_admin_referer( 'sm_client_comment_form', 'sm_client_comment_nonce' );

		$user_id    = get_current_user_id();
		$process_id = isset( $_POST['process_id'] ) ? absint( wp_unslash( $_POST['process_id'] ) ) : 0;
		$client_id  = $this->dashboard_service->get_client_id_by_user_id( $user_id );

		if ( ! $client_id || ! $process_id || ! $this->dashboard_service->user_can_access_client_process( $user_id, $process_id ) ) {
			wp_safe_redirect( add_query_arg( 'sm_comment_notice', 'error', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
			exit;
		}

		$result = $this->comment_service->create_comment(
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'client_id'         => $client_id,
				'comment_type'      => 'client_message',
				'content'           => isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '',
				'is_internal'       => 0,
				'is_client_visible' => 1,
				'author_user_id'    => $user_id,
				'author_client_id'  => $client_id,
				'status'            => 'published',
			)
		);

		wp_safe_redirect( add_query_arg( 'sm_comment_notice', is_wp_error( $result ) ? 'error' : 'created', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}

	protected function handle_mark_read_submission() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		check_admin_referer( 'sm_client_notification_mark_read', 'sm_client_notification_nonce' );

		$notification_id = isset( $_POST['notification_id'] ) ? absint( wp_unslash( $_POST['notification_id'] ) ) : 0;
		$this->notification_service->mark_notification_read( $notification_id, get_current_user_id() );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
		exit;
	}

	protected function handle_mark_all_read_submission() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		check_admin_referer( 'sm_client_notification_mark_all_read', 'sm_client_notification_all_nonce' );

		$client_id = $this->dashboard_service->get_client_id_by_user_id( get_current_user_id() );
		if ( $client_id ) {
			$this->notification_service->mark_all_read( 'client', $client_id );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
		exit;
	}

	protected function can_render_client_content() {
		return ! is_wp_error( $this->permission_service->user_can_access_client_portal( get_current_user_id() ) );
	}

	protected function get_access_denied_message() {
		return $this->permission_service->get_error_message( $this->permission_service->user_can_access_client_portal( get_current_user_id() ) );
	}
}
