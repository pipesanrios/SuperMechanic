<?php
/**
 * Reporting admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Reporting\Report_PDF_Service;
use Super_Mechanic\Reporting\Reporting_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders reporting admin page.
 */
class Reporting_Admin_Controller {
	/**
	 * Reporting service dependency.
	 *
	 * @var Reporting_Service
	 */
	protected $reporting_service;

	/**
	 * Reporting PDF service dependency.
	 *
	 * @var Report_PDF_Service
	 */
	protected $report_pdf_service;

	/**
	 * Constructor.
	 *
	 * @param Reporting_Service|null  $reporting_service Service dependency.
	 * @param Report_PDF_Service|null $report_pdf_service PDF service dependency.
	 */
	public function __construct( Reporting_Service $reporting_service = null, Report_PDF_Service $report_pdf_service = null ) {
		$this->reporting_service = $reporting_service ? $reporting_service : new Reporting_Service();
		$this->report_pdf_service = $report_pdf_service ? $report_pdf_service : new Report_PDF_Service( $this->reporting_service );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 109 );
		add_action( 'admin_post_sm_reporting_download_pdf', array( $this, 'handle_download_pdf' ) );
	}

	/**
	 * Register reporting submenu.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Reporting', 'super-mechanic' ),
			__( 'Reporting', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-reporting',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render reporting page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		$range       = isset( $_GET['sm_range'] ) ? sanitize_key( (string) wp_unslash( $_GET['sm_range'] ) ) : '30d';
		$business_id = isset( $_GET['business_id'] ) ? absint( wp_unslash( $_GET['business_id'] ) ) : 0;
		$summary     = $this->reporting_service->get_reporting_summary( $business_id, $range );
		$metrics     = isset( $summary['metrics'] ) && is_array( $summary['metrics'] ) ? $summary['metrics'] : array();
		$comparison  = $this->reporting_service->get_reporting_comparison( $business_id, $range );

		echo '<div class="wrap sm-admin-shell">';
		echo '<h1>' . esc_html__( 'Reporting', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Commercial and operational reporting core with real aggregated metrics.', 'super-mechanic' ) . '</p>';

		$this->render_notice();
		$this->render_pdf_engine_notice();
		$this->render_filters( $summary );
		$this->render_summary_meta( $summary );
		$this->render_metric_cards( $metrics, $comparison );
		$this->render_comparison_table( $comparison );

		echo '</div>';
	}

	/**
	 * Handle reporting PDF download.
	 *
	 * @return void
	 */
	public function handle_download_pdf() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'super-mechanic' ) );
		}

		check_admin_referer( 'sm_reporting_download_pdf' );

		$range       = isset( $_POST['sm_range'] ) ? sanitize_key( (string) wp_unslash( $_POST['sm_range'] ) ) : '30d';
		$business_id = isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0;

		if ( ! $this->report_pdf_service->can_generate_pdf() ) {
			$this->redirect_with_notice( 'error', $this->report_pdf_service->get_missing_engine_message(), $range, $business_id );
		}

		$pdf         = $this->report_pdf_service->generate_reporting_pdf( $business_id, $range );

		if ( is_wp_error( $pdf ) ) {
			$this->redirect_with_notice( 'error', $pdf->get_error_message(), $range, $business_id );
		}

		$filename = isset( $pdf['filename'] ) ? sanitize_file_name( (string) $pdf['filename'] ) : 'sm-reporting.pdf';
		$content  = isset( $pdf['content'] ) ? (string) $pdf['content'] : '';
		$mime     = isset( $pdf['mime'] ) ? sanitize_text_field( (string) $pdf['mime'] ) : 'application/pdf';

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $content ) );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Render range and business filters.
	 *
	 * @param array<string, mixed> $summary Summary payload.
	 * @return void
	 */
	protected function render_filters( array $summary ) {
		$current_range       = isset( $summary['range'] ) ? (string) $summary['range'] : '30d';
		$current_business_id = isset( $summary['business_id'] ) ? absint( $summary['business_id'] ) : 0;
		$ranges              = $this->reporting_service->get_supported_ranges();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Filters', 'super-mechanic' ) . '</h2></div>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="sm-filter-grid">';
		echo '<input type="hidden" name="page" value="super-mechanic-reporting" />';

		echo '<label class="sm-filter-field">';
		echo '<span>' . esc_html__( 'Range', 'super-mechanic' ) . '</span>';
		echo '<select name="sm_range">';
		foreach ( $ranges as $range_key => $range_label ) {
			echo '<option value="' . esc_attr( (string) $range_key ) . '"' . selected( $current_range, $range_key, false ) . '>' . esc_html( (string) $range_label ) . '</option>';
		}
		echo '</select>';
		echo '</label>';

		echo '<label class="sm-filter-field">';
		echo '<span>' . esc_html__( 'Business ID', 'super-mechanic' ) . '</span>';
		echo '<input type="number" min="0" name="business_id" value="' . esc_attr( (string) $current_business_id ) . '" />';
		echo '</label>';

		echo '<div class="sm-form-actions" style="align-self:end;">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Apply', 'super-mechanic' ) . '</button>';
		echo '</div>';

		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sm-form-actions" style="margin-top:12px;">';
		wp_nonce_field( 'sm_reporting_download_pdf' );
		echo '<input type="hidden" name="action" value="sm_reporting_download_pdf" />';
		echo '<input type="hidden" name="sm_range" value="' . esc_attr( $current_range ) . '" />';
		echo '<input type="hidden" name="business_id" value="' . esc_attr( (string) $current_business_id ) . '" />';
		if ( $this->report_pdf_service->can_generate_pdf() ) {
			echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Download PDF Report', 'super-mechanic' ) . '</button>';
		} else {
			echo '<button type="submit" class="button button-secondary" disabled="disabled" aria-disabled="true">' . esc_html__( 'Download PDF Report', 'super-mechanic' ) . '</button>';
			echo '<p class="description" style="margin:8px 0 0;">' . esc_html( $this->report_pdf_service->get_missing_engine_message() ) . '</p>';
		}
		echo '</form>';

		echo '</section>';
	}

	/**
	 * Render summary metadata.
	 *
	 * @param array<string, mixed> $summary Summary payload.
	 * @return void
	 */
	protected function render_summary_meta( array $summary ) {
		$business_id = isset( $summary['business_id'] ) ? absint( $summary['business_id'] ) : 0;
		$range_label = isset( $summary['range_label'] ) ? (string) $summary['range_label'] : '';
		$generated   = isset( $summary['generated_at'] ) ? (string) $summary['generated_at'] : '';

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-filter-grid">';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Business scope', 'super-mechanic' ) . '</label><div>' . esc_html( (string) $business_id ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Range', 'super-mechanic' ) . '</label><div>' . esc_html( $range_label ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Generated at', 'super-mechanic' ) . '</label><div>' . esc_html( $generated ) . '</div></div>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render metric cards.
	 *
	 * @param array<string, float|int> $metrics Metrics.
	 * @param array<string, mixed>     $comparison Comparison payload.
	 * @return void
	 */
	protected function render_metric_cards( array $metrics, array $comparison ) {
		$cards = array(
			'total_revenue'                    => __( 'Total Revenue', 'super-mechanic' ),
			'total_payments_count'             => __( 'Total Payments', 'super-mechanic' ),
			'average_ticket'                   => __( 'Average Ticket', 'super-mechanic' ),
			'active_clients'                   => __( 'Active Clients', 'super-mechanic' ),
			'active_processes'                 => __( 'Active Processes', 'super-mechanic' ),
			'completed_processes'              => __( 'Completed Processes', 'super-mechanic' ),
			'quotes_count'                     => __( 'Quotes', 'super-mechanic' ),
			'invoices_count'                   => __( 'Invoices', 'super-mechanic' ),
			'quote_to_invoice_conversion_rate' => __( 'Quote to Invoice Conversion', 'super-mechanic' ),
		);

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Reporting Metrics', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-grid-cards sm-grid-cards-compact">';
		$comparison_rows = isset( $comparison['metrics'] ) && is_array( $comparison['metrics'] ) ? $comparison['metrics'] : array();
		foreach ( $cards as $metric_key => $metric_label ) {
			$value = isset( $metrics[ $metric_key ] ) ? $metrics[ $metric_key ] : 0;
			$comparison_row = isset( $comparison_rows[ $metric_key ] ) && is_array( $comparison_rows[ $metric_key ] ) ? $comparison_rows[ $metric_key ] : array();
			$trend_class = isset( $comparison_row['trend'] ) ? sanitize_html_class( 'sm-trend-' . (string) $comparison_row['trend'] ) : 'sm-trend-stable';
			echo '<div class="sm-kpi-card">';
			echo '<div class="sm-kpi-label">' . esc_html( $metric_label ) . '</div>';
			echo '<div class="sm-kpi-value">' . esc_html( $this->format_metric_value( $metric_key, $value ) ) . '</div>';
			if ( ! empty( $comparison_row ) ) {
				echo '<div class="sm-report-trend-row">';
				echo '<span class="sm-badge ' . esc_attr( $trend_class ) . '">' . esc_html( $this->get_trend_label( isset( $comparison_row['trend'] ) ? (string) $comparison_row['trend'] : 'stable' ) ) . '</span>';
				echo '<span class="sm-kpi-footnote">' . esc_html( $this->format_delta_value( $metric_key, $comparison_row ) ) . '</span>';
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render comparison summary table.
	 *
	 * @param array<string, mixed> $comparison Comparison payload.
	 * @return void
	 */
	protected function render_comparison_table( array $comparison ) {
		$rows = isset( $comparison['metrics'] ) && is_array( $comparison['metrics'] ) ? $comparison['metrics'] : array();
		if ( empty( $rows ) ) {
			return;
		}

		$current_period  = isset( $comparison['current_period'] ) && is_array( $comparison['current_period'] ) ? $comparison['current_period'] : array();
		$previous_period = isset( $comparison['previous_period'] ) && is_array( $comparison['previous_period'] ) ? $comparison['previous_period'] : array();
		$current_label   = $this->format_period_label( $current_period );
		$previous_label  = $this->format_period_label( $previous_period );
		$labels          = array(
			'total_revenue'        => __( 'Total Revenue', 'super-mechanic' ),
			'total_payments_count' => __( 'Total Payments', 'super-mechanic' ),
			'active_processes'     => __( 'Active Processes', 'super-mechanic' ),
			'completed_processes'  => __( 'Completed Processes', 'super-mechanic' ),
			'active_clients'       => __( 'Active Clients', 'super-mechanic' ),
			'quotes_count'         => __( 'Quotes', 'super-mechanic' ),
			'invoices_count'       => __( 'Invoices', 'super-mechanic' ),
		);

		echo '<section class="sm-card sm-section sm-report-comparison">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Period Comparison', 'super-mechanic' ) . '</h2></div>';
		echo '<p class="sm-card-copy">' . esc_html( sprintf( __( 'Current: %1$s | Previous: %2$s', 'super-mechanic' ), $current_label, $previous_label ) ) . '</p>';
		echo '<div class="sm-table-wrap">';
		echo '<table class="sm-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Metric', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Current', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Previous', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Delta', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Trend', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $labels as $metric_key => $label ) {
			if ( ! isset( $rows[ $metric_key ] ) || ! is_array( $rows[ $metric_key ] ) ) {
				continue;
			}

			$row        = $rows[ $metric_key ];
			$trend      = isset( $row['trend'] ) ? (string) $row['trend'] : 'stable';
			$trend_class = sanitize_html_class( 'sm-trend-' . $trend );

			echo '<tr>';
			echo '<td>' . esc_html( $label ) . '</td>';
			echo '<td>' . esc_html( $this->format_metric_value( $metric_key, isset( $row['current'] ) ? $row['current'] : 0 ) ) . '</td>';
			echo '<td>' . esc_html( $this->format_metric_value( $metric_key, isset( $row['previous'] ) ? $row['previous'] : 0 ) ) . '</td>';
			echo '<td>' . esc_html( $this->format_delta_value( $metric_key, $row ) ) . '</td>';
			echo '<td><span class="sm-badge ' . esc_attr( $trend_class ) . '">' . esc_html( $this->get_trend_label( $trend ) ) . '</span></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Format value by metric type.
	 *
	 * @param string    $metric_key Metric key.
	 * @param float|int $value      Metric value.
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
	 * Format delta text by metric type.
	 *
	 * @param string               $metric_key Metric key.
	 * @param array<string, mixed> $row Comparison row.
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

		return sprintf(
			'%1$s (%2$s%3$s%%)',
			$absolute,
			$percent_prefix,
			number_format_i18n( $percent_value, 2 )
		);
	}

	/**
	 * Get translated trend label.
	 *
	 * @param string $trend Trend key.
	 * @return string
	 */
	protected function get_trend_label( $trend ) {
		if ( 'up' === $trend ) {
			return __( 'Up', 'super-mechanic' );
		}

		if ( 'down' === $trend ) {
			return __( 'Down', 'super-mechanic' );
		}

		return __( 'Stable', 'super-mechanic' );
	}

	/**
	 * Format period label for UI.
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

	/**
	 * Render PDF engine availability notice.
	 *
	 * @return void
	 */
	protected function render_pdf_engine_notice() {
		if ( $this->report_pdf_service->can_generate_pdf() ) {
			$engine = $this->report_pdf_service->get_active_engine_label();
			if ( '' === $engine ) {
				return;
			}

			echo '<div class="notice notice-info"><p>' ;
			echo esc_html( sprintf( __( 'Reporting PDF engine detected: %s', 'super-mechanic' ), $engine ) );
			echo '</p></div>' ;
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html( $this->report_pdf_service->get_missing_engine_message() ) . '</p></div>' ;
	}

	/**
	 * Render notice from query string.
	 *
	 * @return void
	 */
	protected function render_notice() {
		$type    = isset( $_GET['sm_notice_type'] ) ? sanitize_key( (string) wp_unslash( $_GET['sm_notice_type'] ) ) : '';
		$message = isset( $_GET['sm_notice_message'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['sm_notice_message'] ) ) : '';
		if ( '' === $type || '' === $message ) {
			return;
		}

		echo '<div class="notice notice-' . esc_attr( 'success' === $type ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Redirect back to reporting with notice.
	 *
	 * @param string $type        Notice type.
	 * @param string $message     Notice message.
	 * @param string $range       Range filter.
	 * @param int    $business_id Business filter.
	 * @return void
	 */
	protected function redirect_with_notice( $type, $message, $range, $business_id ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'super-mechanic-reporting',
					'sm_range'          => sanitize_key( (string) $range ),
					'business_id'       => absint( $business_id ),
					'sm_notice_type'    => sanitize_key( (string) $type ),
					'sm_notice_message' => sanitize_text_field( (string) $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}






