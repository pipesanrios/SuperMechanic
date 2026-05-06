<?php
/**
 * Reporting PDF service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Reporting;

use Super_Mechanic\Helpers\PDF_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Generates downloadable reporting PDF payloads.
 */
class Report_PDF_Service extends PDF_Service {
	/**
	 * Reporting service dependency.
	 *
	 * @var Reporting_Service
	 */
	protected $reporting_service;

	/**
	 * Constructor.
	 *
	 * @param Reporting_Service|null $reporting_service Reporting service.
	 */
	public function __construct( Reporting_Service $reporting_service = null ) {
		parent::__construct();
		$this->reporting_service = $reporting_service ? $reporting_service : new Reporting_Service();
	}

	/**
	 * Generate reporting PDF payload.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $range       Range key.
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate_reporting_pdf( $business_id = 0, $range = '30d' ) {
		if ( ! $this->can_generate_pdf() ) {
			return new WP_Error(
				'sm_reporting_pdf_engine_unavailable',
				$this->get_missing_engine_message()
			);
		}

		$summary    = $this->reporting_service->get_reporting_summary( $business_id, $range );
		$comparison = $this->reporting_service->get_reporting_comparison( $business_id, $range );
		$html       = $this->render_reporting_html( $summary, $comparison );
		$filename   = $this->build_filename(
			isset( $summary['business_id'] ) ? absint( $summary['business_id'] ) : 0,
			isset( $summary['range'] ) ? (string) $summary['range'] : '30d'
		);
		$content    = $this->generate_pdf_binary( $html, $filename );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		return array(
			'success'  => true,
			'mime'     => 'application/pdf',
			'filename' => $filename,
			'content'  => $content,
		);
	}

	/**
	 * Check if a PDF engine is available.
	 *
	 * @return bool
	 */
	public function can_generate_pdf() {
		return parent::can_generate_pdf();
	}

	/**
	 * Get active PDF engine label.
	 *
	 * @return string
	 */
	public function get_active_engine_label() {
		$this->maybe_load_embedded_pdf_engine();

		if ( class_exists( '\Dompdf\Dompdf' ) ) {
			return 'Dompdf';
		}

		if ( class_exists( '\Mpdf\Mpdf' ) ) {
			return 'mPDF';
		}

		if ( class_exists( '\TCPDF' ) ) {
			return 'TCPDF';
		}

		return '';
	}

	/**
	 * Build explicit admin fallback message when no engine exists.
	 *
	 * @return string
	 */
	public function get_missing_engine_message() {
		return __( 'PDF engine unavailable. Embedded TCPDF is missing or could not be loaded from the plugin package.', 'super-mechanic' );
	}

	/**
	 * Generate PDF binary using external engine first, then embedded engine.
	 *
	 * @param string $html     HTML content.
	 * @param string $filename Filename.
	 * @return string|WP_Error
	 */
	protected function generate_pdf_binary( $html, $filename ) {
		$this->maybe_load_embedded_pdf_engine();
		$html = $this->normalize_html_for_pdf( $html );

		if ( ! $this->can_generate_pdf() ) {
			return new WP_Error( 'sm_reporting_pdf_embedded_unavailable', $this->get_missing_engine_message() );
		}

		return parent::generate_pdf_binary( $html, $filename );
	}

	/**
	 * Generate a minimal valid PDF from reporting HTML without external dependencies.
	 *
	 * @param string $html Source HTML.
	 * @return string|WP_Error
	 */
	protected function normalize_html_for_pdf( $html ) {
		$html = (string) $html;
		if ( '' === $html ) {
			return $html;
		}

		if ( function_exists( 'mb_detect_encoding' ) && function_exists( 'mb_convert_encoding' ) ) {
			$encoding = mb_detect_encoding( $html, array( 'UTF-8', 'ISO-8859-1', 'Windows-1252' ), true );
			if ( false !== $encoding && 'UTF-8' !== $encoding ) {
				$converted = mb_convert_encoding( $html, 'UTF-8', $encoding );
				if ( false !== $converted ) {
					$html = $converted;
				}
			}
		}

		return $html;
	}

	/**
	 * Build plain text lines for embedded PDF.
	 *
	 * @param string $html Source HTML.
	 * @return array<int, string>
	 */
	protected function generate_with_tcpdf( $html, $filename ) {
		try {
			$pdf = new \TCPDF();
			$pdf->SetCreator( 'Super Mechanic' );
			$pdf->SetAuthor( 'Super Mechanic' );
			$pdf->SetTitle( sanitize_text_field( $filename ) );
			$pdf->SetMargins( 12, 12, 12 );
			$pdf->SetAutoPageBreak( true, 12 );
			$pdf->AddPage();
			$pdf->SetFont( 'dejavusans', '', 10 );
			$pdf->writeHTML( $this->normalize_html_for_pdf( $html ), true, false, true, false, '' );

			return $pdf->Output( $filename, 'S' );
		} catch ( \Exception $exception ) {
			return new WP_Error( 'sm_reporting_pdf_tcpdf_failed', $exception->getMessage() );
		}
	}

	/**
	 * Render HTML for PDF generation.
	 *
	 * @param array<string, mixed> $summary    Summary payload.
	 * @param array<string, mixed> $comparison Comparison payload.
	 * @return string
	 */
	protected function render_reporting_html( array $summary, array $comparison ) {
		$metrics          = isset( $summary['metrics'] ) && is_array( $summary['metrics'] ) ? $summary['metrics'] : array();
		$comparison_rows  = isset( $comparison['metrics'] ) && is_array( $comparison['metrics'] ) ? $comparison['metrics'] : array();
		$comparison_state = ! empty( $comparison['comparison_available'] );
		$business_id      = isset( $summary['business_id'] ) ? absint( $summary['business_id'] ) : 0;
		$range_label      = isset( $summary['range_label'] ) ? (string) $summary['range_label'] : '';
		$generated_at     = isset( $summary['generated_at'] ) ? (string) $summary['generated_at'] : current_time( 'mysql' );

		$metric_labels = array(
			'total_revenue'                    => __( 'Total Revenue', 'super-mechanic' ),
			'total_payments_count'             => __( 'Total Payments', 'super-mechanic' ),
			'active_processes'                 => __( 'Active Processes', 'super-mechanic' ),
			'completed_processes'              => __( 'Completed Processes', 'super-mechanic' ),
			'active_clients'                   => __( 'Active Clients', 'super-mechanic' ),
			'average_ticket'                   => __( 'Average Ticket', 'super-mechanic' ),
			'quotes_count'                     => __( 'Quotes', 'super-mechanic' ),
			'invoices_count'                   => __( 'Invoices', 'super-mechanic' ),
			'quote_to_invoice_conversion_rate' => __( 'Quote to Invoice Conversion', 'super-mechanic' ),
		);

		$current_label  = $this->format_period_label( isset( $comparison['current_period'] ) && is_array( $comparison['current_period'] ) ? $comparison['current_period'] : array() );
		$previous_label = $this->format_period_label( isset( $comparison['previous_period'] ) && is_array( $comparison['previous_period'] ) ? $comparison['previous_period'] : array() );

		$html  = '<html><head><meta charset="utf-8" /><style>';
		$html .= 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:10.5px;line-height:1.42;color:#1f2937;background:#ffffff;}';
		$html .= '.sm-report-wrap{padding:8px 4px;}';
		$html .= '.sm-report-header{border-bottom:3px solid #0f4fa8;margin-bottom:16px;padding-bottom:12px;}';
		$html .= '.sm-report-brand{font-size:23px;font-weight:700;color:#0f4fa8;margin:0 0 4px;}';
		$html .= '.sm-report-title{font-size:14px;font-weight:700;color:#111827;text-transform:uppercase;margin:0;}';
		$html .= '.sm-report-subtitle{font-size:10px;color:#6b7280;margin:4px 0 0;}';
		$html .= '.sm-report-meta{width:100%;border-collapse:collapse;margin:0 0 18px;}';
		$html .= '.sm-report-meta td{border:1px solid #e5e7eb;padding:8px 10px;vertical-align:top;background:#f9fafb;}';
		$html .= '.sm-report-label{display:block;font-size:8.5px;text-transform:uppercase;font-weight:700;color:#6b7280;margin-bottom:2px;}';
		$html .= '.sm-report-section{font-size:14px;font-weight:700;color:#111827;margin:20px 0 8px;padding-bottom:5px;border-bottom:1px solid #d1d5db;}';
		$html .= '.sm-report-note{font-size:9.5px;color:#6b7280;margin:0 0 8px;}';
		$html .= '.sm-report-table{width:100%;border-collapse:collapse;margin-top:8px;}';
		$html .= '.sm-report-table th{background:#eef2f7;color:#374151;border:1px solid #d1d5db;padding:8px 7px;font-size:8.5px;text-transform:uppercase;font-weight:700;}';
		$html .= '.sm-report-table td{border:1px solid #e5e7eb;padding:8px 7px;vertical-align:top;}';
		$html .= '.sm-report-table tbody tr:nth-child(even) td{background:#fbfdff;}';
		$html .= '.sm-report-metric{font-weight:700;color:#111827;}';
		$html .= '.sm-report-num{text-align:right;white-space:nowrap;font-weight:700;}';
		$html .= '.sm-report-trend{text-align:center;font-weight:700;}';
		$html .= '.up{color:#166534;}.down{color:#991b1b;}.stable{color:#374151;}';
		$html .= '.sm-report-footer{margin-top:18px;padding-top:8px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:9px;}';
		$html .= '</style></head><body><div class="sm-report-wrap">';
		$html .= '<div class="sm-report-header">';
		$html .= '<h1 class="sm-report-brand">' . esc_html__( 'Mekvort / Super Mechanic', 'super-mechanic' ) . '</h1>';
		$html .= '<p class="sm-report-title">' . esc_html__( 'Commercial & Operational Reporting', 'super-mechanic' ) . '</p>';
		$html .= '<p class="sm-report-subtitle">' . esc_html__( 'Export-ready operational snapshot for the selected business scope.', 'super-mechanic' ) . '</p>';
		$html .= '</div>';
		$html .= '<table class="sm-report-meta"><tr>';
		$html .= '<td><span class="sm-report-label">' . esc_html__( 'Generated at', 'super-mechanic' ) . '</span>' . esc_html( $generated_at ) . '</td>';
		$html .= '<td><span class="sm-report-label">' . esc_html__( 'Range', 'super-mechanic' ) . '</span>' . esc_html( $range_label ) . '</td>';
		$html .= '<td><span class="sm-report-label">' . esc_html__( 'Business scope', 'super-mechanic' ) . '</span>' . esc_html( (string) $business_id ) . '</td>';
		$html .= '</tr></table>';

		$html .= '<h2 class="sm-report-section">' . esc_html__( 'Key Metrics', 'super-mechanic' ) . '</h2>';
		$html .= '<table class="sm-report-table"><thead><tr>';
		$html .= '<th width="68%">' . esc_html__( 'Metric', 'super-mechanic' ) . '</th>';
		$html .= '<th width="32%" class="sm-report-num">' . esc_html__( 'Value', 'super-mechanic' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $metric_labels as $metric_key => $metric_label ) {
			$value = isset( $metrics[ $metric_key ] ) ? $metrics[ $metric_key ] : 0;
			$html .= '<tr>';
			$html .= '<td class="sm-report-metric">' . esc_html( $metric_label ) . '</td>';
			$html .= '<td class="sm-report-num">' . esc_html( $this->format_metric_value( $metric_key, $value ) ) . '</td>';
			$html .= '</tr>';
		}
		$html .= '</tbody></table>';

		if ( $comparison_state && ! empty( $comparison_rows ) ) {
			$html .= '<h2 class="sm-report-section">' . esc_html__( 'Period Comparison', 'super-mechanic' ) . '</h2>';
			$html .= '<p class="sm-report-note">' . esc_html( sprintf( __( 'Current: %1$s | Previous: %2$s', 'super-mechanic' ), $current_label, $previous_label ) ) . '</p>';
			$html .= '<table class="sm-report-table"><thead><tr>';
			$html .= '<th width="34%">' . esc_html__( 'Metric', 'super-mechanic' ) . '</th>';
			$html .= '<th width="17%" class="sm-report-num">' . esc_html__( 'Current', 'super-mechanic' ) . '</th>';
			$html .= '<th width="17%" class="sm-report-num">' . esc_html__( 'Previous', 'super-mechanic' ) . '</th>';
			$html .= '<th width="20%" class="sm-report-num">' . esc_html__( 'Delta', 'super-mechanic' ) . '</th>';
			$html .= '<th width="12%" class="sm-report-trend">' . esc_html__( 'Trend', 'super-mechanic' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $metric_labels as $metric_key => $metric_label ) {
				if ( ! isset( $comparison_rows[ $metric_key ] ) || ! is_array( $comparison_rows[ $metric_key ] ) ) {
					continue;
				}

				$row         = $comparison_rows[ $metric_key ];
				$trend       = isset( $row['trend'] ) ? sanitize_key( (string) $row['trend'] ) : 'stable';
				$trend_class = in_array( $trend, array( 'up', 'down' ), true ) ? $trend : 'stable';

				$html .= '<tr>';
				$html .= '<td class="sm-report-metric">' . esc_html( $metric_label ) . '</td>';
				$html .= '<td class="sm-report-num">' . esc_html( $this->format_metric_value( $metric_key, isset( $row['current'] ) ? $row['current'] : 0 ) ) . '</td>';
				$html .= '<td class="sm-report-num">' . esc_html( $this->format_metric_value( $metric_key, isset( $row['previous'] ) ? $row['previous'] : 0 ) ) . '</td>';
				$html .= '<td class="sm-report-num">' . esc_html( $this->format_delta_value( $metric_key, $row ) ) . '</td>';
				$html .= '<td class="sm-report-trend ' . esc_attr( $trend_class ) . '">' . esc_html( ucfirst( $trend_class ) ) . '</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
		}

		$html .= '<div class="sm-report-footer">' . esc_html__( 'Generated by Mekvort / Super Mechanic. Values reflect the selected reporting range and business scope at export time.', 'super-mechanic' ) . '</div>';
		$html .= '</div></body></html>';

		return $html;
	}

	/**
	 * Build stable reporting filename.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $range       Range key.
	 * @return string
	 */
	protected function build_filename( $business_id, $range ) {
		$business_id = absint( $business_id );
		$range       = sanitize_key( (string) $range );
		$date_stamp  = gmdate( 'Ymd-His' );

		return sanitize_file_name( 'sm-reporting-b' . $business_id . '-' . $range . '-' . $date_stamp . '.pdf' );
	}

	/**
	 * Format metric value.
	 *
	 * @param string    $metric_key Metric key.
	 * @param float|int $value      Value.
	 * @return string
	 */
	protected function format_metric_value( $metric_key, $value ) {
		$value = (float) $value;

		if ( in_array( $metric_key, array( 'total_revenue', 'average_ticket' ), true ) ) {
			return number_format_i18n( $value, 2 );
		}

		if ( 'quote_to_invoice_conversion_rate' === $metric_key ) {
			return number_format_i18n( $value, 2 ) . '%';
		}

		return number_format_i18n( $value, 0 );
	}

	/**
	 * Format delta value.
	 *
	 * @param string               $metric_key Metric key.
	 * @param array<string, mixed> $row        Row payload.
	 * @return string
	 */
	protected function format_delta_value( $metric_key, array $row ) {
		$delta   = isset( $row['delta'] ) ? (float) $row['delta'] : 0.0;
		$percent = isset( $row['delta_percent'] ) ? $row['delta_percent'] : null;
		$prefix  = $delta > 0 ? '+' : '';

		if ( in_array( $metric_key, array( 'total_revenue' ), true ) ) {
			$absolute = $prefix . number_format_i18n( $delta, 2 );
		} else {
			$absolute = $prefix . number_format_i18n( $delta, 0 );
		}

		if ( null === $percent ) {
			return $absolute;
		}

		$percent_value  = (float) $percent;
		$percent_prefix = $percent_value > 0 ? '+' : '';

		return sprintf( '%1$s (%2$s%3$s%%)', $absolute, $percent_prefix, number_format_i18n( $percent_value, 2 ) );
	}

	/**
	 * Format period label.
	 *
	 * @param array<string, mixed> $period Period bounds.
	 * @return string
	 */
	protected function format_period_label( array $period ) {
		$from = isset( $period['date_from'] ) ? (string) $period['date_from'] : '';
		$to   = isset( $period['date_to'] ) ? (string) $period['date_to'] : '';

		if ( '' === $from && '' === $to ) {
			return __( 'All time', 'super-mechanic' );
		}

		if ( '' !== $from && '' !== $to ) {
			return $from . ' - ' . $to;
		}

		return '' !== $from ? $from : $to;
	}

}
