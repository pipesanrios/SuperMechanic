<?php
/**
 * Export admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Export\Export_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles admin UI and download flow for data export.
 */
class Export_Admin_Controller {
	/**
	 * Export service dependency.
	 *
	 * @var Export_Service
	 */
	protected $export_service;

	/**
	 * Constructor.
	 *
	 * @param Export_Service|null $export_service Service dependency.
	 */
	public function __construct( Export_Service $export_service = null ) {
		$this->export_service = $export_service ? $export_service : new Export_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 108 );
		add_action( 'admin_post_sm_export_dataset', array( $this, 'handle_export_dataset' ) );
	}

	/**
	 * Register submenu.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Export', 'super-mechanic' ),
			__( 'Export', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-export',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render export page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		$datasets = $this->export_service->get_supported_datasets();

		echo '<div class="wrap sm-admin-shell">';
		echo '<h1>' . esc_html__( 'Data Export', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Export operational datasets for portability and backup workflows.', 'super-mechanic' ) . '</p>';

		$this->render_notice();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Available datasets', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap">';
		echo '<table class="sm-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Dataset', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Description', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Format', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $datasets as $dataset_key => $dataset_meta ) {
			$dataset_label       = isset( $dataset_meta['label'] ) ? (string) $dataset_meta['label'] : $dataset_key;
			$dataset_description = isset( $dataset_meta['description'] ) ? (string) $dataset_meta['description'] : '';

			echo '<tr>';
			echo '<td><strong>' . esc_html( $dataset_label ) . '</strong><br /><code>' . esc_html( (string) $dataset_key ) . '</code></td>';
			echo '<td>' . esc_html( $dataset_description ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:8px;align-items:center;">';
			wp_nonce_field( 'sm_export_dataset_' . $dataset_key );
			echo '<input type="hidden" name="action" value="sm_export_dataset" />';
			echo '<input type="hidden" name="dataset_key" value="' . esc_attr( (string) $dataset_key ) . '" />';
			echo '<select name="format">';
			echo '<option value="json">JSON</option>';
			echo '<option value="csv">CSV</option>';
			echo '</select>';
			echo '</td>';
			echo '<td><button type="submit" class="button button-primary">' . esc_html__( 'Export', 'super-mechanic' ) . '</button></form></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';
		echo '</div>';
	}

	/**
	 * Handle export action.
	 *
	 * @return void
	 */
	public function handle_export_dataset() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'super-mechanic' ) );
		}

		$dataset_key = isset( $_POST['dataset_key'] ) ? sanitize_key( (string) wp_unslash( $_POST['dataset_key'] ) ) : '';
		$format      = isset( $_POST['format'] ) ? sanitize_key( (string) wp_unslash( $_POST['format'] ) ) : 'json';

		if ( '' === $dataset_key ) {
			$this->redirect_with_notice( 'error', 'invalid_dataset' );
		}

		check_admin_referer( 'sm_export_dataset_' . $dataset_key );
		$export = $this->export_service->export_dataset( $dataset_key, $format );

		if ( empty( $export['success'] ) ) {
			$this->redirect_with_notice(
				'error',
				'export_failed',
				array( 'sm_notice_message' => isset( $export['message'] ) ? sanitize_text_field( (string) $export['message'] ) : '' )
			);
		}

		$filename = isset( $export['filename'] ) ? sanitize_file_name( (string) $export['filename'] ) : 'sm-export.json';
		$mime     = isset( $export['mime'] ) ? sanitize_text_field( (string) $export['mime'] ) : 'application/octet-stream';
		$content  = isset( $export['content'] ) ? (string) $export['content'] : '';

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $content ) );

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Render page notices.
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
			'invalid_dataset' => __( 'Invalid dataset selected.', 'super-mechanic' ),
			'export_failed'   => __( 'Could not export dataset.', 'super-mechanic' ),
		);
		$message  = isset( $messages[ $notice_code ] ) ? $messages[ $notice_code ] : '';
		if ( '' === $message && '' === $notice_message ) {
			return;
		}

		$final_message = '' !== $notice_message ? $notice_message : $message;
		echo '<div class="notice notice-' . esc_attr( 'success' === $notice_type ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $final_message ) . '</p></div>';
	}

	/**
	 * Redirect with notice.
	 *
	 * @param string              $type Notice type.
	 * @param string              $code Notice code.
	 * @param array<string,mixed> $extra Extra query args.
	 * @return void
	 */
	protected function redirect_with_notice( $type, $code, array $extra = array() ) {
		$args = array_merge(
			array(
				'page'           => 'super-mechanic-export',
				'sm_notice_type' => sanitize_key( (string) $type ),
				'sm_notice_code' => sanitize_key( (string) $code ),
			),
			$extra
		);

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
