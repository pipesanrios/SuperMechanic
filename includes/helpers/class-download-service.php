<?php
/**
 * Download service helper.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles secure download entry points and file/binary streaming.
 */
class Download_Service {
	/**
	 * Document service.
	 *
	 * @var Document_Service
	 */
	protected $document_service;

	/**
	 * Constructor.
	 *
	 * @param Document_Service|null $document_service Document service.
	 */
	public function __construct( Document_Service $document_service = null ) {
		$this->document_service = $document_service ? $document_service : new Document_Service();
	}

	/**
	 * Register protected download hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'template_redirect', array( $this, 'maybe_handle_download_request' ) );
	}

	/**
	 * Check if a PDF engine is available.
	 *
	 * @return bool
	 */
	public function can_generate_pdf() {
		return $this->document_service->can_download_document_type( 'invoice_pdf' );
	}

	/**
	 * Build a secure download URL.
	 *
	 * @param string $type Document type.
	 * @param int    $id   Document ID.
	 * @return string
	 */
	public function get_download_url( $type, $id ) {
		$type = sanitize_key( $type );
		$id   = absint( $id );

		if ( ! $id ) {
			return '';
		}

		$url = add_query_arg(
			array(
				'sm_document_download' => 1,
				'sm_document_type'     => $type,
				'sm_document_id'       => $id,
			),
			home_url( '/' )
		);

		return wp_nonce_url( $url, $this->get_download_nonce_action( $type, $id ) );
	}

	/**
	 * Serve the requested document if the current request is a protected download.
	 *
	 * @return void
	 */
	public function maybe_handle_download_request() {
		$type = isset( $_GET['sm_document_type'] ) ? sanitize_key( wp_unslash( $_GET['sm_document_type'] ) ) : '';
		$id   = isset( $_GET['sm_document_id'] ) ? absint( wp_unslash( $_GET['sm_document_id'] ) ) : 0;

		if ( empty( $_GET['sm_document_download'] ) || ! $type || ! $id ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, $this->get_download_nonce_action( $type, $id ) ) ) {
			$this->deny_download( new WP_Error( 'sm_document_invalid_nonce', __( 'No fue posible validar la descarga solicitada.', 'super-mechanic' ) ) );
		}

		if ( ! is_user_logged_in() ) {
			$this->deny_download( new WP_Error( 'sm_document_login_required', __( 'Debe iniciar sesion para descargar este documento.', 'super-mechanic' ) ) );
		}

		if ( ! $this->document_service->user_can_access_document( get_current_user_id(), $type, $id ) ) {
			$this->deny_download( new WP_Error( 'sm_document_access_denied', __( 'No tienes permisos para descargar este documento.', 'super-mechanic' ) ) );
		}

		$response = $this->document_service->build_download_response( $type, $id );

		if ( is_wp_error( $response ) ) {
			$this->deny_download( $response );
		}

		$this->stream_download_response( $response );
	}

	/**
	 * Stream a normalized download response.
	 *
	 * @param array<string, mixed> $response Download response.
	 * @return void
	 */
	public function stream_download_response( array $response ) {
		$delivery = isset( $response['delivery'] ) ? sanitize_key( $response['delivery'] ) : '';

		if ( 'binary' === $delivery ) {
			$this->serve_binary_download(
				isset( $response['filename'] ) ? $response['filename'] : 'documento.pdf',
				isset( $response['content'] ) ? (string) $response['content'] : '',
				isset( $response['content_type'] ) ? $response['content_type'] : 'application/octet-stream'
			);
		}

		if ( 'file' === $delivery ) {
			$this->serve_file_download(
				isset( $response['file_path'] ) ? $response['file_path'] : '',
				isset( $response['filename'] ) ? $response['filename'] : '',
				isset( $response['content_type'] ) ? $response['content_type'] : ''
			);
		}

		$this->deny_download( new WP_Error( 'sm_document_invalid_delivery', __( 'No fue posible servir el documento solicitado.', 'super-mechanic' ) ) );
	}

	/**
	 * Stream binary content.
	 *
	 * @param string $filename     Download file name.
	 * @param string $content      Binary content.
	 * @param string $content_type MIME type.
	 * @return void
	 */
	public function serve_binary_download( $filename, $content, $content_type = 'application/octet-stream' ) {
		if ( headers_sent() ) {
			exit;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: ' . sanitize_text_field( $content_type ) );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $content ) );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Stream a local file download.
	 *
	 * @param string $file_path    Local file path.
	 * @param string $filename     Download file name.
	 * @param string $content_type MIME type.
	 * @return void
	 */
	public function serve_file_download( $file_path, $filename = '', $content_type = '' ) {
		$file_path = wp_normalize_path( (string) $file_path );

		if ( '' === $file_path || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			$this->deny_download( new WP_Error( 'sm_document_file_missing', __( 'El archivo solicitado no esta disponible.', 'super-mechanic' ) ) );
		}

		$filename     = '' !== $filename ? $filename : wp_basename( $file_path );
		$content_type = '' !== $content_type ? $content_type : $this->detect_file_content_type( $file_path );

		if ( headers_sent() ) {
			exit;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: ' . sanitize_text_field( $content_type ) );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $file_path ) );
		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Detect file MIME type.
	 *
	 * @param string $file_path File path.
	 * @return string
	 */
	protected function detect_file_content_type( $file_path ) {
		$type = wp_check_filetype( wp_basename( $file_path ) );

		if ( ! empty( $type['type'] ) ) {
			return $type['type'];
		}

		return 'application/octet-stream';
	}

	/**
	 * Deny the current download request with a clean error response.
	 *
	 * @param WP_Error $error Error object.
	 * @return void
	 */
	protected function deny_download( WP_Error $error ) {
		wp_die(
			esc_html( $error->get_error_message() ),
			esc_html__( 'Descarga no disponible', 'super-mechanic' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Build the nonce action for a protected download.
	 *
	 * @param string $type Document type.
	 * @param int    $id   Document ID.
	 * @return string
	 */
	protected function get_download_nonce_action( $type, $id ) {
		return 'sm_download_document_' . sanitize_key( $type ) . '_' . absint( $id );
	}
}
