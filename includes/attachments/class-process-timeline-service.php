<?php
/**
 * Process timeline service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Attachments;

use Super_Mechanic\Communication\Comment_Service;
use Super_Mechanic\Communication\Notification_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a consolidated timeline for a process.
 */
class Process_Timeline_Service {
	protected $process_service;
	protected $attachment_service;
	protected $quote_service;
	protected $invoice_service;
	protected $comment_service;
	protected $notification_service;

	public function __construct( Process_Service $process_service = null, Attachment_Service $attachment_service = null, Quote_Service $quote_service = null, Invoice_Service $invoice_service = null, Comment_Service $comment_service = null, Notification_Service $notification_service = null ) {
		$this->process_service      = $process_service ? $process_service : new Process_Service();
		$this->attachment_service   = $attachment_service ? $attachment_service : new Attachment_Service();
		$this->quote_service        = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service      = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->comment_service      = $comment_service ? $comment_service : new Comment_Service();
		$this->notification_service = $notification_service ? $notification_service : new Notification_Service();
	}

	public function get_process_timeline( $process_id, $client_context = false ) {
		$process_id = absint( $process_id );
		$events     = array();

		foreach ( $this->process_service->get_process_step_logs( $process_id, 200 ) as $log ) {
			if ( $client_context && empty( $log['customer_visible'] ) ) {
				continue;
			}

			$events[] = array(
				'event_type'  => 'process_log',
				'event_label' => ! empty( $log['message'] ) ? $log['message'] : __( 'Actualizacion del proceso', 'super-mechanic' ),
				'event_date'  => $log['created_at'],
				'metadata'    => array(
					'action_type' => $log['action_type'],
				),
			);
		}

		$attachments = $client_context
			? $this->attachment_service->get_client_visible_process_attachments( $process_id )
			: $this->attachment_service->get_process_attachments( $process_id, array( 'per_page' => 200 ) );

		foreach ( $attachments as $attachment ) {
			$events[] = array(
				'event_type'  => 'document_uploaded',
				'event_label' => sprintf( __( 'Documento adjunto: %s', 'super-mechanic' ), $attachment['title'] ),
				'event_date'  => $attachment['created_at'],
				'metadata'    => array(
					'attachment_id'   => absint( $attachment['id'] ),
					'attachment_type' => $attachment['attachment_type'],
					'mime_type'       => $attachment['mime_type'],
				),
			);
		}

		$comments = $client_context ? $this->comment_service->get_client_visible_process_comments( $process_id ) : $this->comment_service->get_process_comments( $process_id );
		foreach ( $comments as $comment ) {
			$events[] = array(
				'event_type'  => 'comment_added',
				'event_label' => $this->get_comment_label( $comment ),
				'event_date'  => $comment['created_at'],
				'metadata'    => array(
					'comment_id'   => absint( $comment['id'] ),
					'comment_type' => $comment['comment_type'],
				),
			);
		}

		foreach ( $this->quote_service->get_quotes( array( 'process_id' => $process_id, 'per_page' => 100 ) ) as $quote ) {
			$events[] = array(
				'event_type'  => $this->get_quote_event_type( $quote['status'] ),
				'event_label' => $this->get_quote_event_label( $quote ),
				'event_date'  => ! empty( $quote['updated_at'] ) ? $quote['updated_at'] : $quote['created_at'],
				'metadata'    => array(
					'quote_id'    => absint( $quote['id'] ),
					'grand_total' => $quote['grand_total'],
					'currency'    => $quote['currency'],
				),
			);
		}

		foreach ( $this->invoice_service->get_invoices( array( 'process_id' => $process_id, 'per_page' => 100 ) ) as $invoice ) {
			$payment_summary = $this->invoice_service->get_invoice_payment_summary( absint( $invoice['id'] ) );
			$events[] = array(
				'event_type'  => $this->get_invoice_event_type( $invoice, $payment_summary ),
				'event_label' => $this->get_invoice_event_label( $invoice, $payment_summary ),
				'event_date'  => ! empty( $invoice['issued_at'] ) ? $invoice['issued_at'] : ( ! empty( $invoice['updated_at'] ) ? $invoice['updated_at'] : $invoice['created_at'] ),
				'metadata'    => array(
					'invoice_id'  => absint( $invoice['id'] ),
					'grand_total' => $invoice['grand_total'],
					'currency'    => $invoice['currency'],
				),
			);

			foreach ( $this->invoice_service->get_payments( absint( $invoice['id'] ) ) as $payment ) {
				$events[] = array(
					'event_type'  => 'payment_registered',
					'event_label' => sprintf( __( 'Pago registrado en factura %s', 'super-mechanic' ), $invoice['invoice_number'] ),
					'event_date'  => $payment['payment_date'],
					'metadata'    => array(
						'invoice_id'     => absint( $invoice['id'] ),
						'payment_id'     => absint( $payment['id'] ),
						'amount'         => $payment['amount'],
						'payment_method' => $payment['payment_method'],
					),
				);
			}
		}

		usort(
			$events,
			static function ( $left, $right ) {
				return strtotime( (string) $right['event_date'] ) <=> strtotime( (string) $left['event_date'] );
			}
		);

		return $events;
	}

	protected function get_comment_label( $comment ) {
		$prefix_map = array(
			'internal_note'   => __( 'Nota interna', 'super-mechanic' ),
			'client_message'  => __( 'Mensaje del cliente', 'super-mechanic' ),
			'staff_reply'     => __( 'Respuesta del equipo', 'super-mechanic' ),
			'system_note'     => __( 'Nota del sistema', 'super-mechanic' ),
		);
		$prefix     = isset( $prefix_map[ $comment['comment_type'] ] ) ? $prefix_map[ $comment['comment_type'] ] : __( 'Comentario', 'super-mechanic' );
		$content    = wp_trim_words( wp_strip_all_tags( (string) $comment['content'] ), 16, '...' );

		return sprintf( '%s: %s', $prefix, $content );
	}

	protected function get_quote_event_type( $status ) {
		switch ( $status ) {
			case 'draft':
				return 'quote_created';
			case 'sent':
				return 'quote_sent';
			case 'approved':
				return 'quote_approved';
			case 'rejected':
				return 'quote_rejected';
			case 'cancelled':
				return 'quote_cancelled';
			default:
				return 'quote';
		}
	}

	protected function get_quote_event_label( $quote ) {
		switch ( $quote['status'] ) {
			case 'draft':
				return sprintf( __( 'Cotizacion %s creada', 'super-mechanic' ), $quote['quote_number'] );
			case 'sent':
				return sprintf( __( 'Cotizacion %s enviada', 'super-mechanic' ), $quote['quote_number'] );
			case 'approved':
				return sprintf( __( 'Cotizacion %s aprobada', 'super-mechanic' ), $quote['quote_number'] );
			case 'rejected':
				return sprintf( __( 'Cotizacion %s rechazada', 'super-mechanic' ), $quote['quote_number'] );
			case 'cancelled':
				return sprintf( __( 'Cotizacion %s cancelada', 'super-mechanic' ), $quote['quote_number'] );
			default:
				return sprintf( __( 'Cotizacion %s (%s)', 'super-mechanic' ), $quote['quote_number'], $quote['status'] );
		}
	}

	protected function get_invoice_event_type( $invoice, $payment_summary ) {
		$collection_status = is_array( $payment_summary ) && ! empty( $payment_summary['payment_status'] ) ? sanitize_key( $payment_summary['payment_status'] ) : '';

		if ( 'paid' === $collection_status ) {
			return 'invoice_paid';
		}

		if ( 'partial' === $collection_status ) {
			return 'invoice_partially_paid';
		}

		switch ( $invoice['status'] ) {
			case 'draft':
				return 'invoice_created';
			case 'issued':
				return 'invoice_issued';
			case 'paid':
				return 'invoice_paid';
			case 'partially_paid':
				return 'invoice_partially_paid';
			case 'overdue':
				return 'invoice_overdue';
			case 'cancelled':
				return 'invoice_cancelled';
			case 'refunded':
				return 'invoice_refunded';
			default:
				return 'invoice';
		}
	}

	protected function get_invoice_event_label( $invoice, $payment_summary ) {
		$collection_status = is_array( $payment_summary ) && ! empty( $payment_summary['payment_status'] ) ? sanitize_key( $payment_summary['payment_status'] ) : '';
		$status            = isset( $invoice['status'] ) ? $invoice['status'] : '';

		if ( 'paid' === $collection_status ) {
			return sprintf( __( 'Factura %s pagada', 'super-mechanic' ), $invoice['invoice_number'] );
		}

		if ( 'partial' === $collection_status ) {
			return sprintf( __( 'Factura %s con pago parcial', 'super-mechanic' ), $invoice['invoice_number'] );
		}

		switch ( $status ) {
			case 'draft':
				return sprintf( __( 'Factura %s creada', 'super-mechanic' ), $invoice['invoice_number'] );
			case 'issued':
				return sprintf( __( 'Factura %s emitida', 'super-mechanic' ), $invoice['invoice_number'] );
			case 'paid':
				return sprintf( __( 'Factura %s pagada', 'super-mechanic' ), $invoice['invoice_number'] );
			case 'partially_paid':
				return sprintf( __( 'Factura %s con pago parcial', 'super-mechanic' ), $invoice['invoice_number'] );
			case 'overdue':
				return sprintf( __( 'Factura %s vencida', 'super-mechanic' ), $invoice['invoice_number'] );
			case 'cancelled':
				return sprintf( __( 'Factura %s cancelada', 'super-mechanic' ), $invoice['invoice_number'] );
			case 'refunded':
				return sprintf( __( 'Factura %s reembolsada', 'super-mechanic' ), $invoice['invoice_number'] );
			default:
				return sprintf( __( 'Factura %s (%s)', 'super-mechanic' ), $invoice['invoice_number'], $invoice['status'] );
		}
	}
}
