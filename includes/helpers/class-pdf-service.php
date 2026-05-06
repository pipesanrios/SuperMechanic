<?php
/**
 * PDF service helper.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Quotes\Quote_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates PDF generation and streaming.
 */
class PDF_Service {
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
	 * @param Invoice_Service|null $invoice_service Invoice service.
	 * @param Quote_Service|null   $quote_service   Quote service.
	 */
	public function __construct( Invoice_Service $invoice_service = null, Quote_Service $quote_service = null ) {
		$this->invoice_service = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->quote_service   = $quote_service ? $quote_service : new Quote_Service();
	}

	/**
	 * Check whether a supported PDF engine is available.
	 *
	 * @return bool
	 */
	public function can_generate_pdf() {
		return $this->maybe_load_embedded_pdf_engine()
			|| class_exists( '\Dompdf\Dompdf' )
			|| class_exists( '\Mpdf\Mpdf' )
			|| class_exists( '\TCPDF' );
	}

	/**
	 * Generate invoice PDF content.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return array<string, string>|WP_Error
	 */
	public function generate_invoice_pdf( $invoice_id ) {
		return $this->generate_document_pdf( 'invoice_pdf', $invoice_id );
	}

	/**
	 * Generate quote PDF content.
	 *
	 * @param int $quote_id Quote ID.
	 * @return array<string, string>|WP_Error
	 */
	public function generate_quote_pdf( $quote_id ) {
		return $this->generate_document_pdf( 'quote_pdf', $quote_id );
	}

	/**
	 * Generate payment receipt PDF content.
	 *
	 * @param int $payment_id Payment ID.
	 * @return array<string, string>|WP_Error
	 */
	public function generate_payment_receipt_pdf( $payment_id ) {
		return $this->generate_document_pdf( 'payment_receipt', $payment_id );
	}

	/**
	 * Generate PDF content for a supported document type.
	 *
	 * @param string $type Document type.
	 * @param int    $id   Document ID.
	 * @return array<string, string>|WP_Error
	 */
	public function generate_document_pdf( $type, $id ) {
		$type = sanitize_key( $type );
		$id   = absint( $id );

		if ( ! $this->can_generate_pdf() ) {
			return new WP_Error(
				'sm_pdf_engine_unavailable',
				__( 'No hay una libreria PDF instalada. La integracion queda lista para conectar Dompdf, mPDF o TCPDF.', 'super-mechanic' )
			);
		}

		$filename = $this->get_document_filename( $type, $id );

		if ( is_wp_error( $filename ) ) {
			return $filename;
		}

		$html = $this->render_document_html( $type, $id );

		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$content = $this->generate_pdf_binary( $html, $filename );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		return array(
			'filename' => $filename,
			'content'  => $content,
		);
	}

	/**
	 * Render invoice HTML for PDF output.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return string
	 */
	public function render_invoice_html( $invoice_id ) {
		$html = $this->render_document_html( 'invoice_pdf', $invoice_id );

		if ( is_wp_error( $html ) ) {
			return '<p>' . esc_html( $html->get_error_message() ) . '</p>';
		}

		return $html;
	}

	/**
	 * Render quote HTML for PDF output.
	 *
	 * @param int $quote_id Quote ID.
	 * @return string
	 */
	public function render_quote_html( $quote_id ) {
		$html = $this->render_document_html( 'quote_pdf', $quote_id );

		if ( is_wp_error( $html ) ) {
			return '<p>' . esc_html( $html->get_error_message() ) . '</p>';
		}

		return $html;
	}

	/**
	 * Render HTML for a supported PDF document.
	 *
	 * @param string $type Document type.
	 * @param int    $id   Document ID.
	 * @return string|WP_Error
	 */
	public function render_document_html( $type, $id ) {
		$type = sanitize_key( $type );
		$id   = absint( $id );

		switch ( $type ) {
			case 'invoice_pdf':
				$context = $this->invoice_service->get_invoice_print_context( $id );

				if ( is_wp_error( $context ) ) {
					return $context;
				}

				return $this->invoice_service->render_invoice_printable_html( $context );

			case 'quote_pdf':
				$context = $this->quote_service->get_quote_print_context( $id );

				if ( is_wp_error( $context ) ) {
					return $context;
				}

				return $this->quote_service->render_quote_printable_html( $context );

			case 'payment_receipt':
				$context = $this->invoice_service->get_payment_receipt_context( $id );

				if ( is_wp_error( $context ) ) {
					return $context;
				}

				return $this->invoice_service->render_payment_receipt_html( $context );
		}

		return new WP_Error( 'sm_pdf_document_invalid_type', __( 'El tipo de documento PDF no es compatible.', 'super-mechanic' ) );
	}

	/**
	 * Resolve filename for a supported PDF document.
	 *
	 * @param string $type Document type.
	 * @param int    $id   Document ID.
	 * @return string|WP_Error
	 */
	public function get_document_filename( $type, $id ) {
		$type = sanitize_key( $type );
		$id   = absint( $id );

		switch ( $type ) {
			case 'invoice_pdf':
				return $this->invoice_service->get_invoice_pdf_filename( $id );

			case 'quote_pdf':
				return $this->quote_service->get_quote_pdf_filename( $id );

			case 'payment_receipt':
				return $this->invoice_service->get_payment_receipt_pdf_filename( $id );
		}

		return new WP_Error( 'sm_pdf_document_invalid_type', __( 'El tipo de documento PDF no es compatible.', 'super-mechanic' ) );
	}

	/**
	 * Stream a generated PDF to the browser.
	 *
	 * @param string $filename File name.
	 * @param string $content  PDF binary content.
	 * @return void
	 */
	public function stream_pdf( $filename, $content ) {
		if ( headers_sent() ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $content ) );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Generate binary PDF content using the available engine.
	 *
	 * @param string $html     Rendered HTML.
	 * @param string $filename File name.
	 * @return string|WP_Error
	 */
	protected function generate_pdf_binary( $html, $filename ) {
		$this->maybe_load_embedded_pdf_engine();

		if ( class_exists( '\Dompdf\Dompdf' ) ) {
			return $this->generate_with_dompdf( $html );
		}

		if ( class_exists( '\Mpdf\Mpdf' ) ) {
			return $this->generate_with_mpdf( $html );
		}

		if ( class_exists( '\TCPDF' ) ) {
			return $this->generate_with_tcpdf( $html, $filename );
		}

		return new WP_Error(
			'sm_pdf_engine_missing',
			__( 'No se detecto un motor PDF compatible.', 'super-mechanic' )
		);
	}

	/**
	 * Load the bundled PDF engine when available.
	 *
	 * @return bool
	 */
	protected function maybe_load_embedded_pdf_engine() {
		if ( class_exists( '\TCPDF' ) ) {
			return true;
		}

		foreach ( $this->get_pdf_engine_candidates() as $candidate ) {
			if ( ! file_exists( $candidate ) ) {
				continue;
			}

			require_once $candidate;
			if ( class_exists( '\TCPDF' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get local PDF engine loader candidates.
	 *
	 * @return array<int, string>
	 */
	protected function get_pdf_engine_candidates() {
		return array(
			SM_PLUGIN_PATH . 'includes/libs/pdf/tcpdf/tcpdf.php',
			SM_PLUGIN_PATH . 'vendor/tecnickcom/tcpdf/tcpdf.php',
			SM_PLUGIN_PATH . 'vendor/autoload.php',
		);
	}

	/**
	 * Generate PDF with Dompdf.
	 *
	 * @param string $html Rendered HTML.
	 * @return string|WP_Error
	 */
	protected function generate_with_dompdf( $html ) {
		try {
			$options = null;

			if ( class_exists( '\Dompdf\Options' ) ) {
				$options = new \Dompdf\Options();
				$options->set( 'isRemoteEnabled', true );
			}

			$dompdf = $options ? new \Dompdf\Dompdf( $options ) : new \Dompdf\Dompdf();
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();

			return $dompdf->output();
		} catch ( \Exception $exception ) {
			return new WP_Error( 'sm_pdf_dompdf_failed', $exception->getMessage() );
		}
	}

	/**
	 * Generate PDF with mPDF.
	 *
	 * @param string $html Rendered HTML.
	 * @return string|WP_Error
	 */
	protected function generate_with_mpdf( $html ) {
		try {
			$mpdf = new \Mpdf\Mpdf();
			$mpdf->WriteHTML( $html );

			return $mpdf->Output( '', 'S' );
		} catch ( \Exception $exception ) {
			return new WP_Error( 'sm_pdf_mpdf_failed', $exception->getMessage() );
		}
	}

	/**
	 * Generate PDF with TCPDF.
	 *
	 * @param string $html     Rendered HTML.
	 * @param string $filename File name.
	 * @return string|WP_Error
	 */
	protected function generate_with_tcpdf( $html, $filename ) {
		try {
			$pdf = new \TCPDF();
			$pdf->SetCreator( 'Super Mechanic' );
			$pdf->SetAuthor( wp_strip_all_tags( get_bloginfo( 'name' ) ) );
			$pdf->SetTitle( sanitize_text_field( $filename ) );
			$pdf->SetMargins( 12, 12, 12 );
			$pdf->SetAutoPageBreak( true, 12 );
			$pdf->AddPage();
			$pdf->writeHTML( $html, true, false, true, false, '' );

			return $pdf->Output( $filename, 'S' );
		} catch ( \Exception $exception ) {
			return new WP_Error( 'sm_pdf_tcpdf_failed', $exception->getMessage() );
		}
	}
}
