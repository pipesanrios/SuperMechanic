<?php
/**
 * Document service helper.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

use Super_Mechanic\Attachments\Attachment_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Quotes\Quote_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates protected document resolution and access checks.
 */
class Document_Service {
	/**
	 * PDF service.
	 *
	 * @var PDF_Service
	 */
	protected $pdf_service;

	/**
	 * Attachment service.
	 *
	 * @var Attachment_Service
	 */
	protected $attachment_service;

	/**
	 * Invoice service.
	 *
	 * @var Invoice_Service
	 */
	protected $invoice_service;

	/**
	 * Quote service.
	 *
	 * @var Quote_Service
	 */
	protected $quote_service;

	/**
	 * Constructor.
	 *
	 * @param PDF_Service|null        $pdf_service        PDF service.
	 * @param Attachment_Service|null $attachment_service Attachment service.
	 * @param Invoice_Service|null    $invoice_service    Invoice service.
	 * @param Quote_Service|null      $quote_service      Quote service.
	 */
	public function __construct( PDF_Service $pdf_service = null, Attachment_Service $attachment_service = null, Invoice_Service $invoice_service = null, Quote_Service $quote_service = null ) {
		$this->invoice_service    = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->quote_service      = $quote_service ? $quote_service : new Quote_Service();
		$this->pdf_service        = $pdf_service ? $pdf_service : new PDF_Service( $this->invoice_service, $this->quote_service );
		$this->attachment_service = $attachment_service ? $attachment_service : new Attachment_Service( null, null, null, $this->quote_service, $this->invoice_service );
	}

	/**
	 * Resolve a normalized document descriptor.
	 *
	 * @param string $type Document type.
	 * @param int    $id   Document ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_document( $type, $id ) {
		$type = sanitize_key( $type );
		$id   = absint( $id );

		if ( ! $id ) {
			return new WP_Error( 'sm_document_invalid_id', __( 'El documento solicitado no es valido.', 'super-mechanic' ) );
		}

		switch ( $type ) {
			case 'invoice_pdf':
				$invoice = $this->invoice_service->get_invoice( $id );

				if ( ! $invoice ) {
					return new WP_Error( 'sm_invoice_not_found', __( 'La factura no existe.', 'super-mechanic' ) );
				}

				return array(
					'type'         => $type,
					'id'           => $id,
					'resource'     => $invoice,
					'resource_key' => 'invoice',
					'filename'     => $this->invoice_service->get_invoice_pdf_filename( $id ),
					'content_type' => 'application/pdf',
					'delivery'     => 'binary',
				);

			case 'quote_pdf':
				$quote = $this->quote_service->get_quote( $id );

				if ( ! $quote ) {
					return new WP_Error( 'sm_quote_not_found', __( 'La cotizacion no existe.', 'super-mechanic' ) );
				}

				return array(
					'type'         => $type,
					'id'           => $id,
					'resource'     => $quote,
					'resource_key' => 'quote',
					'filename'     => $this->quote_service->get_quote_pdf_filename( $id ),
					'content_type' => 'application/pdf',
					'delivery'     => 'binary',
				);

			case 'payment_receipt':
				$payment = $this->invoice_service->get_payment( $id );

				if ( ! $payment ) {
					return new WP_Error( 'sm_payment_not_found', __( 'El pago no existe.', 'super-mechanic' ) );
				}

				return array(
					'type'         => $type,
					'id'           => $id,
					'resource'     => $payment,
					'resource_key' => 'payment',
					'filename'     => $this->invoice_service->get_payment_receipt_pdf_filename( $id ),
					'content_type' => 'application/pdf',
					'delivery'     => 'binary',
				);

			case 'attachment':
				$attachment = $this->attachment_service->get_attachment( $id );

				if ( ! $attachment ) {
					return new WP_Error( 'sm_attachment_not_found', __( 'El documento adjunto no existe.', 'super-mechanic' ) );
				}

				return array(
					'type'         => $type,
					'id'           => $id,
					'resource'     => $attachment,
					'resource_key' => 'attachment',
					'filename'     => $this->attachment_service->get_attachment_download_filename( $attachment ),
					'content_type' => ! empty( $attachment['mime_type'] ) ? sanitize_text_field( $attachment['mime_type'] ) : 'application/octet-stream',
					'delivery'     => 'file',
				);
		}

		return new WP_Error( 'sm_document_invalid_type', __( 'El tipo de documento solicitado no es compatible.', 'super-mechanic' ) );
	}

	/**
	 * Check whether a document type can be downloaded right now.
	 *
	 * @param string $type Document type.
	 * @return bool
	 */
	public function can_download_document_type( $type ) {
		$type = sanitize_key( $type );

		if ( in_array( $type, array( 'invoice_pdf', 'quote_pdf', 'payment_receipt' ), true ) ) {
			return $this->pdf_service->can_generate_pdf();
		}

		return 'attachment' === $type;
	}

	/**
	 * Check whether a user can access a document.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type    Document type.
	 * @param int    $id      Document ID.
	 * @return bool
	 */
	public function user_can_access_document( $user_id, $type, $id ) {
		$user_id = absint( $user_id );
		$type    = sanitize_key( $type );
		$id      = absint( $id );

		if ( ! $user_id || ! $id ) {
			return false;
		}

		switch ( $type ) {
			case 'invoice_pdf':
				return $this->invoice_service->user_can_access_invoice( $user_id, $id );

			case 'quote_pdf':
				return $this->quote_service->user_can_access_quote( $user_id, $id );

			case 'payment_receipt':
				return $this->invoice_service->user_can_access_payment( $user_id, $id );

			case 'attachment':
				return $this->attachment_service->user_can_access_attachment( $user_id, $id );
		}

		return false;
	}

	/**
	 * Get a safe download filename.
	 *
	 * @param string $type Document type.
	 * @param int    $id   Document ID.
	 * @return string|WP_Error
	 */
	public function get_document_filename( $type, $id ) {
		$document = $this->get_document( $type, $id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		return sanitize_file_name( $document['filename'] );
	}

	/**
	 * Build the normalized response payload for a document download.
	 *
	 * @param string $type Document type.
	 * @param int    $id   Document ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function build_download_response( $type, $id ) {
		$document = $this->get_document( $type, $id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		switch ( $document['type'] ) {
			case 'invoice_pdf':
			case 'quote_pdf':
			case 'payment_receipt':
				$pdf = $this->pdf_service->generate_document_pdf( $document['type'], $document['id'] );

				if ( is_wp_error( $pdf ) ) {
					return $pdf;
				}

				return array(
					'delivery'     => 'binary',
					'filename'     => $pdf['filename'],
					'content_type' => 'application/pdf',
					'content'      => $pdf['content'],
				);

			case 'attachment':
				$download = $this->attachment_service->get_attachment_download_data( $document['id'] );

				if ( is_wp_error( $download ) ) {
					return $download;
				}

				return array(
					'delivery'     => 'file',
					'filename'     => $download['filename'],
					'content_type' => $download['content_type'],
					'file_path'    => $download['file_path'],
				);
		}

		return new WP_Error( 'sm_document_download_unsupported', __( 'No fue posible preparar la descarga del documento.', 'super-mechanic' ) );
	}

	/**
	 * Resolve the logical document affected by an automation event.
	 *
	 * This phase keeps automation non-persistent: it standardizes availability
	 * without creating attachments or redundant physical files.
	 *
	 * @param string               $event_name Event name.
	 * @param array<string, mixed> $payload    Event payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_automated_document_for_event( $event_name, array $payload = array() ) {
		$event_name = sanitize_key( $event_name );

		switch ( $event_name ) {
			case 'quote_approved':
				$quote_id = ! empty( $payload['quote_id'] ) ? absint( $payload['quote_id'] ) : 0;

				if ( ! $quote_id ) {
					return new WP_Error( 'sm_document_automation_invalid_quote', __( 'La automatizacion documental requiere una cotizacion valida.', 'super-mechanic' ) );
				}

				return $this->get_document( 'quote_pdf', $quote_id );

			case 'invoice_issued':
				$invoice_id = ! empty( $payload['invoice_id'] ) ? absint( $payload['invoice_id'] ) : 0;

				if ( ! $invoice_id ) {
					return new WP_Error( 'sm_document_automation_invalid_invoice', __( 'La automatizacion documental requiere una factura valida.', 'super-mechanic' ) );
				}

				return $this->get_document( 'invoice_pdf', $invoice_id );

			case 'invoice_paid':
				$payment_id = ! empty( $payload['payment_id'] ) ? absint( $payload['payment_id'] ) : 0;

				if ( ! $payment_id ) {
					return new WP_Error( 'sm_document_automation_invalid_payment', __( 'La automatizacion documental requiere un pago valido.', 'super-mechanic' ) );
				}

				return $this->get_document( 'payment_receipt', $payment_id );

			case 'payment_registered':
				$payment_id = ! empty( $payload['payment_id'] ) ? absint( $payload['payment_id'] ) : 0;

				if ( ! $payment_id ) {
					return new WP_Error( 'sm_document_automation_invalid_payment', __( 'La automatizacion documental requiere un pago valido.', 'super-mechanic' ) );
				}

				return $this->get_document( 'payment_receipt', $payment_id );
		}

		return new WP_Error( 'sm_document_automation_invalid_event', __( 'El evento indicado no soporta automatizacion documental.', 'super-mechanic' ) );
	}

	/**
	 * Prepare a logical automated document for the given event.
	 *
	 * @param string               $event_name Event name.
	 * @param array<string, mixed> $payload    Event payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function prepare_automated_document_for_event( $event_name, array $payload = array() ) {
		$document = $this->get_automated_document_for_event( $event_name, $payload );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		if ( ! $this->can_download_document_type( $document['type'] ) ) {
			return new WP_Error( 'sm_document_automation_unavailable', __( 'El documento automatico no esta disponible porque no existe un motor compatible para generarlo.', 'super-mechanic' ) );
		}

		return array(
			'event_name'   => sanitize_key( $event_name ),
			'is_available' => true,
			'is_persisted' => false,
			'document'     => $document,
		);
	}
}
