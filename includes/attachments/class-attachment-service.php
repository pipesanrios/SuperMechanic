<?php
/**
 * Attachment service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Attachments;

use Super_Mechanic\Communication\Event_Dispatcher;
use Super_Mechanic\Dashboard\Dashboard_Service;
use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles attachment business rules.
 */
class Attachment_Service {
	protected $repository;
	protected $process_service;
	protected $dashboard_service;
	protected $quote_service;
	protected $invoice_service;
	protected $event_dispatcher;
	protected $access_control_service;
	protected $business_context_service;

	public function __construct( Attachment_Repository $repository = null, Process_Service $process_service = null, Dashboard_Service $dashboard_service = null, Quote_Service $quote_service = null, Invoice_Service $invoice_service = null, Event_Dispatcher $event_dispatcher = null, Access_Control_Service $access_control_service = null, Business_Context_Service $business_context_service = null ) {
		$this->repository        = $repository ? $repository : new Attachment_Repository();
		$this->process_service   = $process_service ? $process_service : new Process_Service();
		$this->dashboard_service = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->quote_service     = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service   = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->event_dispatcher  = $event_dispatcher ? $event_dispatcher : Event_Dispatcher::get_instance();
		$this->access_control_service = $access_control_service ? $access_control_service : new Access_Control_Service( null, null, null, null, null, $this->repository );
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	public function create_attachment( array $data ) {
		$data  = $this->prepare_attachment_data( $data );
		$valid = $this->validate_attachment_data( $data );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $this->repository->insert( $data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_attachment_create_failed', __( 'No fue posible registrar el documento adjunto.', 'super-mechanic' ) );
		}

		$this->event_dispatcher->dispatch(
			'document_uploaded',
			array(
				'attachment_id' => $inserted,
				'process_id'    => absint( $data['process_id'] ),
				'client_id'     => absint( $data['client_id'] ),
				'triggered_by'  => ! empty( $data['uploaded_by'] ) ? absint( $data['uploaded_by'] ) : get_current_user_id(),
			)
		);

		return $inserted;
	}

	public function update_attachment( $attachment_id, array $data ) {
		$attachment_id = absint( $attachment_id );
		$current       = $this->repository->get_by_id( $attachment_id );

		if ( ! $current ) {
			return new WP_Error( 'sm_attachment_not_found', __( 'El documento adjunto no existe.', 'super-mechanic' ) );
		}

		$data  = $this->prepare_attachment_data( array_merge( $current, $data ) );
		$valid = $this->validate_attachment_data( $data );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! $this->repository->update( $attachment_id, $data ) ) {
			return new WP_Error( 'sm_attachment_update_failed', __( 'No fue posible actualizar el documento adjunto.', 'super-mechanic' ) );
		}

		return true;
	}

	public function delete_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$attachment    = $this->repository->get_by_id( $attachment_id );

		if ( ! $attachment ) {
			return new WP_Error( 'sm_attachment_not_found', __( 'El documento adjunto no existe.', 'super-mechanic' ) );
		}

		$file_path = $this->resolve_attachment_file_path( $attachment );

		if ( ! is_wp_error( $file_path ) && file_exists( $file_path ) ) {
			wp_delete_file( $file_path );
		}

		if ( ! $this->repository->delete( $attachment_id ) ) {
			return new WP_Error( 'sm_attachment_delete_failed', __( 'No fue posible eliminar el documento adjunto.', 'super-mechanic' ) );
		}

		return true;
	}

	public function get_attachment( $attachment_id ) {
		return $this->repository->get_by_id( $attachment_id );
	}

	public function get_attachments( array $args = array() ) {
		if ( empty( $args['business_id'] ) ) {
			$args['business_id'] = $this->resolve_business_id();
		}

		return $this->repository->get_all( $args );
	}

	public function get_process_attachments( $process_id, array $args = array() ) {
		return $this->repository->get_by_process_id( $process_id, $args );
	}

	public function get_client_visible_process_attachments( $process_id ) {
		return $this->repository->get_by_process_id(
			$process_id,
			array(
				'is_internal'       => 0,
				'is_client_visible' => 1,
				'per_page'          => 200,
				'orderby'           => 'created_at',
				'order'             => 'DESC',
			)
		);
	}

	public function user_can_access_attachment( $user_id, $attachment_id ) {
		return $this->access_control_service->user_can_access_attachment( $user_id, $attachment_id, true );
	}

	public function resolve_attachment_file_path( $attachment ) {
		if ( ! is_array( $attachment ) ) {
			$attachment = $this->get_attachment( $attachment );
		}

		if ( ! is_array( $attachment ) ) {
			return new WP_Error( 'sm_attachment_not_found', __( 'El documento solicitado no existe.', 'super-mechanic' ) );
		}

		if ( ! empty( $attachment['file_path'] ) ) {
			$path = wp_normalize_path( (string) $attachment['file_path'] );

			if ( $this->is_safe_upload_path( $path ) && file_exists( $path ) && is_readable( $path ) ) {
				return $path;
			}
		}

		if ( ! empty( $attachment['file_url'] ) ) {
			$uploads = wp_get_upload_dir();

			if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( (string) $attachment['file_url'], (string) $uploads['baseurl'] ) ) {
				$relative = ltrim( substr( (string) $attachment['file_url'], strlen( (string) $uploads['baseurl'] ) ), '/\\' );
				$path     = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $relative ) );

				if ( $this->is_safe_upload_path( $path ) && file_exists( $path ) && is_readable( $path ) ) {
					return $path;
				}
			}
		}

		return new WP_Error( 'sm_attachment_file_missing', __( 'El archivo solicitado no esta disponible para descarga.', 'super-mechanic' ) );
	}

	public function get_attachment_download_filename( $attachment ) {
		if ( ! is_array( $attachment ) ) {
			$attachment = $this->get_attachment( $attachment );
		}

		if ( ! is_array( $attachment ) ) {
			return 'documento.pdf';
		}

		$title     = ! empty( $attachment['title'] ) ? sanitize_file_name( (string) $attachment['title'] ) : 'documento';
		$extension = '';
		$candidate = ! empty( $attachment['file_path'] ) ? (string) $attachment['file_path'] : ( ! empty( $attachment['file_url'] ) ? (string) $attachment['file_url'] : '' );

		if ( '' !== $candidate ) {
			$path_info = pathinfo( $candidate );
			$extension = ! empty( $path_info['extension'] ) ? strtolower( (string) $path_info['extension'] ) : '';
		}

		if ( '' !== $extension && ! preg_match( '/\.' . preg_quote( $extension, '/' ) . '$/i', $title ) ) {
			$title .= '.' . $extension;
		}

		return $title;
	}

	public function get_attachment_download_data( $attachment_id ) {
		$attachment = $this->get_attachment( $attachment_id );

		if ( ! $attachment ) {
			return new WP_Error( 'sm_attachment_not_found', __( 'El documento solicitado no existe.', 'super-mechanic' ) );
		}

		$file_path = $this->resolve_attachment_file_path( $attachment );

		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		return array(
			'attachment'   => $attachment,
			'file_path'    => $file_path,
			'filename'     => $this->get_attachment_download_filename( $attachment ),
			'content_type' => ! empty( $attachment['mime_type'] ) ? sanitize_text_field( $attachment['mime_type'] ) : 'application/octet-stream',
		);
	}

	/**
	 * Check whether an attachment can be exposed to the client portal.
	 *
	 * @param array<string, mixed>|int $attachment Attachment data or ID.
	 * @return bool
	 */
	public function is_client_downloadable_attachment( $attachment ) {
		if ( ! is_array( $attachment ) ) {
			$attachment = $this->get_attachment( $attachment );
		}

		if ( ! is_array( $attachment ) ) {
			return false;
		}

		if ( ! empty( $attachment['is_internal'] ) ) {
			return false;
		}

		if ( empty( $attachment['is_client_visible'] ) ) {
			return false;
		}

		return true;
	}

	public function validate_attachment_data( array $data ) {
		$errors               = new WP_Error();
		$allowed_object_types = array( 'process', 'quote', 'invoice', 'client', 'vehicle', 'paperwork', 'payment', 'general' );

		if ( ! in_array( $data['object_type'], $allowed_object_types, true ) ) {
			$errors->add( 'invalid_object_type', __( 'El tipo de objeto del documento no es valido.', 'super-mechanic' ) );
		}

		if ( empty( $data['title'] ) ) {
			$errors->add( 'missing_title', __( 'El documento requiere un titulo.', 'super-mechanic' ) );
		}

		if ( empty( $data['file_url'] ) ) {
			$errors->add( 'missing_file_url', __( 'El documento requiere una URL de archivo valida.', 'super-mechanic' ) );
		}

		if ( empty( $data['mime_type'] ) || ! in_array( $data['mime_type'], $this->get_allowed_mime_types(), true ) ) {
			$errors->add( 'invalid_mime_type', __( 'El tipo MIME del archivo no esta permitido.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['process_id'] ) && ! $this->process_service->get_process( $data['process_id'] ) ) {
			$errors->add( 'invalid_process', __( 'El proceso asociado no existe.', 'super-mechanic' ) );
		}

		$parent_business_id = $this->resolve_structural_business_id( $data );
		if ( $parent_business_id > 0 && absint( $data['business_id'] ) !== $parent_business_id ) {
			$errors->add( 'invalid_business_context', __( 'El adjunto debe pertenecer al mismo negocio que su entidad padre.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['is_internal'] ) && ! empty( $data['is_client_visible'] ) ) {
			$errors->add( 'invalid_visibility', __( 'Un documento interno no puede ser visible para el cliente.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	public function handle_upload( array $file ) {
		if ( empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'sm_attachment_file_required', __( 'Debes seleccionar un archivo valido.', 'super-mechanic' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => $this->get_upload_mime_map(),
		);
		$uploaded  = wp_handle_upload( $file, $overrides );

		if ( isset( $uploaded['error'] ) ) {
			return new WP_Error( 'sm_attachment_upload_failed', sanitize_text_field( $uploaded['error'] ) );
		}

		return array(
			'file_url'  => esc_url_raw( $uploaded['url'] ),
			'file_path' => sanitize_text_field( $uploaded['file'] ),
			'mime_type' => ! empty( $uploaded['type'] ) ? sanitize_text_field( $uploaded['type'] ) : '',
			'file_size' => file_exists( $uploaded['file'] ) ? (int) filesize( $uploaded['file'] ) : 0,
			'title'     => sanitize_text_field( wp_basename( $uploaded['file'] ) ),
		);
	}

	public function get_allowed_mime_types() {
		return array(
			'application/pdf',
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'text/plain',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'text/csv',
		);
	}

	public function get_upload_mime_map() {
		return array(
			'pdf'          => 'application/pdf',
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'txt|asc|c'    => 'text/plain',
			'doc'          => 'application/msword',
			'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls'          => 'application/vnd.ms-excel',
			'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'csv'          => 'text/csv',
		);
	}

	protected function prepare_attachment_data( array $data ) {
		$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';

		if ( '' === $title && ! empty( $data['file_path'] ) ) {
			$title = sanitize_text_field( wp_basename( $data['file_path'] ) );
		}

		return array(
			'business_id'        => isset( $data['business_id'] ) && absint( $data['business_id'] ) > 0
				? absint( $data['business_id'] )
				: $this->resolve_structural_business_id( $data ),
			'object_type'       => isset( $data['object_type'] ) ? sanitize_key( $data['object_type'] ) : 'process',
			'object_id'         => isset( $data['object_id'] ) ? absint( $data['object_id'] ) : 0,
			'process_id'        => isset( $data['process_id'] ) ? absint( $data['process_id'] ) : 0,
			'client_id'         => isset( $data['client_id'] ) ? absint( $data['client_id'] ) : 0,
			'vehicle_id'        => isset( $data['vehicle_id'] ) ? absint( $data['vehicle_id'] ) : 0,
			'attachment_type'   => isset( $data['attachment_type'] ) ? sanitize_key( $data['attachment_type'] ) : 'document',
			'title'             => $title,
			'description'       => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'file_url'          => isset( $data['file_url'] ) ? esc_url_raw( $data['file_url'] ) : '',
			'file_path'         => isset( $data['file_path'] ) ? sanitize_text_field( $data['file_path'] ) : '',
			'mime_type'         => isset( $data['mime_type'] ) ? sanitize_text_field( $data['mime_type'] ) : '',
			'file_size'         => isset( $data['file_size'] ) ? absint( $data['file_size'] ) : 0,
			'is_internal'       => ! empty( $data['is_internal'] ) ? 1 : 0,
			'is_client_visible' => ! empty( $data['is_client_visible'] ) ? 1 : 0,
			'uploaded_by'       => isset( $data['uploaded_by'] ) ? absint( $data['uploaded_by'] ) : get_current_user_id(),
		);
	}

	/**
	 * Resolve business ID from structural parent, with context fallback.
	 *
	 * @param array<string,mixed> $data Attachment payload.
	 * @return int
	 */
	protected function resolve_structural_business_id( array $data ) {
		$process_id = ! empty( $data['process_id'] ) ? absint( $data['process_id'] ) : 0;
		$object_id  = ! empty( $data['object_id'] ) ? absint( $data['object_id'] ) : 0;
		$object_type = ! empty( $data['object_type'] ) ? sanitize_key( (string) $data['object_type'] ) : '';

		if ( $process_id > 0 ) {
			$process = $this->process_service->get_process( $process_id );
			if ( is_array( $process ) && ! empty( $process['business_id'] ) ) {
				return max( 1, absint( $process['business_id'] ) );
			}
		}

		if ( 'process' === $object_type && $object_id > 0 ) {
			$process = $this->process_service->get_process( $object_id );
			if ( is_array( $process ) && ! empty( $process['business_id'] ) ) {
				return max( 1, absint( $process['business_id'] ) );
			}
		}

		if ( 'quote' === $object_type && $object_id > 0 ) {
			$quote = $this->quote_service->get_quote( $object_id );
			if ( is_array( $quote ) && ! empty( $quote['business_id'] ) ) {
				return max( 1, absint( $quote['business_id'] ) );
			}
		}

		if ( 'invoice' === $object_type && $object_id > 0 ) {
			$invoice = $this->invoice_service->get_invoice( $object_id );
			if ( is_array( $invoice ) && ! empty( $invoice['business_id'] ) ) {
				return max( 1, absint( $invoice['business_id'] ) );
			}
		}

		if ( 'payment' === $object_type && $object_id > 0 ) {
			$payment = $this->invoice_service->get_payment( $object_id );
			if ( is_array( $payment ) && ! empty( $payment['business_id'] ) ) {
				return max( 1, absint( $payment['business_id'] ) );
			}
		}

		return $this->resolve_business_id();
	}

	/**
	 * Resolve active business ID.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		return absint( $this->business_context_service->resolve_business_id() );
	}

	/**
	 * Check whether a file path stays inside the WordPress uploads directory.
	 *
	 * @param string $path Candidate local path.
	 * @return bool
	 */
	protected function is_safe_upload_path( $path ) {
		$path    = wp_normalize_path( (string) $path );
		$uploads = wp_get_upload_dir();
		$base    = ! empty( $uploads['basedir'] ) ? wp_normalize_path( trailingslashit( $uploads['basedir'] ) ) : '';

		if ( '' === $path || '' === $base ) {
			return false;
		}

		return 0 === strpos( $path, $base );
	}
}
