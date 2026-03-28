<?php
/**
 * Client attachment shortcodes.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Attachments;

use Super_Mechanic\Dashboard\Client_Dashboard_Controller;
use Super_Mechanic\Dashboard\Dashboard_Service;
use Super_Mechanic\Helpers\Download_Service;
use Super_Mechanic\Helpers\Permission_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Registers client document and timeline shortcodes.
 */
class Client_Attachment_Shortcodes {
	protected $client_dashboard_controller;
	protected $dashboard_service;
	protected $attachment_service;
	protected $download_service;
	protected $permission_service;

	public function __construct( Client_Dashboard_Controller $client_dashboard_controller = null, Dashboard_Service $dashboard_service = null, Attachment_Service $attachment_service = null, Download_Service $download_service = null, Permission_Service $permission_service = null ) {
		$this->client_dashboard_controller = $client_dashboard_controller ? $client_dashboard_controller : new Client_Dashboard_Controller();
		$this->dashboard_service           = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->attachment_service          = $attachment_service ? $attachment_service : new Attachment_Service();
		$this->download_service            = $download_service ? $download_service : new Download_Service();
		$this->permission_service          = $permission_service ? $permission_service : new Permission_Service();
	}

	public function register_hooks() {
		add_shortcode( 'sm_client_process_documents', array( $this, 'render_client_process_documents' ) );
		add_shortcode( 'sm_client_process_timeline', array( $this, 'render_client_process_timeline' ) );
	}

	public function render_client_process_documents( $atts = array() ) {
		$atts = shortcode_atts( array( 'process_id' => 0 ), $atts, 'sm_client_process_documents' );

		if ( ! $this->can_render_process_content() ) {
			return $this->get_access_denied_message();
		}

		$process_id = absint( $atts['process_id'] );
		if ( ! $process_id && isset( $_GET['process_id'] ) ) {
			$process_id = absint( wp_unslash( $_GET['process_id'] ) );
		}

		if ( ! $process_id || ! $this->dashboard_service->user_can_access_client_process( get_current_user_id(), $process_id ) ) {
			return '<p>' . esc_html__( 'No tienes acceso a los documentos de este proceso.', 'super-mechanic' ) . '</p>';
		}

		$attachments = $this->attachment_service->get_client_visible_process_attachments( $process_id );

		ob_start();
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Documento', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Fecha', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Archivo', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $attachments ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No hay documentos visibles para este proceso.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $attachments as $attachment ) {
				$download_url = $this->get_attachment_download_url( $attachment );

				echo '<tr>';
				echo '<td>' . esc_html( $attachment['title'] ) . '<br /><small>' . esc_html( $attachment['description'] ) . '</small></td>';
				echo '<td>' . esc_html( $attachment['attachment_type'] ) . '</td>';
				echo '<td>' . esc_html( $attachment['created_at'] ) . '</td>';
				if ( '' !== $download_url ) {
					echo '<td><a href="' . esc_url( $download_url ) . '">' . esc_html__( 'Descargar', 'super-mechanic' ) . '</a></td>';
				} else {
					echo '<td>' . esc_html__( 'No disponible', 'super-mechanic' ) . '</td>';
				}
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	public function render_client_process_timeline( $atts = array() ) {
		$atts = shortcode_atts( array( 'process_id' => 0 ), $atts, 'sm_client_process_timeline' );

		if ( ! $this->can_render_process_content() ) {
			return $this->get_access_denied_message();
		}

		$process_id = absint( $atts['process_id'] );
		if ( ! $process_id && isset( $_GET['process_id'] ) ) {
			$process_id = absint( wp_unslash( $_GET['process_id'] ) );
		}

		if ( ! $process_id || ! $this->dashboard_service->user_can_access_client_process( get_current_user_id(), $process_id ) ) {
			return '<p>' . esc_html__( 'No tienes acceso a la timeline de este proceso.', 'super-mechanic' ) . '</p>';
		}

		return $this->client_dashboard_controller->render_process_timeline( $process_id, get_current_user_id() );
	}

	protected function can_render_process_content() {
		return ! is_wp_error( $this->permission_service->user_can_access_client_portal( get_current_user_id() ) );
	}

	protected function get_access_denied_message() {
		return $this->permission_service->get_error_message( $this->permission_service->user_can_access_client_portal( get_current_user_id() ) );
	}

	/**
	 * Build a protected client download URL for an attachment.
	 *
	 * @param array<string, mixed> $attachment Attachment data.
	 * @return string
	 */
	protected function get_attachment_download_url( array $attachment ) {
		if ( empty( $attachment['id'] ) ) {
			return '';
		}

		if ( ! $this->attachment_service->is_client_downloadable_attachment( $attachment ) ) {
			return '';
		}

		return $this->download_service->get_download_url( 'attachment', absint( $attachment['id'] ) );
	}
}
