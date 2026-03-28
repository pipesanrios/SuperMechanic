<?php
/**
 * Report service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Reports;

use Super_Mechanic\Processes\Process_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles report filter validation and report orchestration.
 */
class Report_Service {
	/**
	 * Supported CSV export views.
	 *
	 * @var array<int, string>
	 */
	const CSV_EXPORT_VIEWS = array(
		'recent_processes',
		'recent_quotes',
		'recent_invoices',
		'recent_payments',
	);

	/**
	 * Repository instance.
	 *
	 * @var Report_Repository
	 */
	protected $repository;

	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * Constructor.
	 *
	 * @param Report_Repository|null $repository      Report repository.
	 * @param Process_Service|null   $process_service Process service.
	 */
	public function __construct( Report_Repository $repository = null, Process_Service $process_service = null ) {
		$this->repository      = $repository ? $repository : new Report_Repository();
		$this->process_service = $process_service ? $process_service : new Process_Service();
	}

	/**
	 * Get report filters normalized and validated.
	 *
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array<string, mixed>
	 */
	public function validate_filters( array $filters ) {
		$filters = wp_parse_args(
			$filters,
			array(
				'date_from'      => '',
				'date_to'        => '',
				'process_status' => '',
				'process_type'   => '',
				'derived_status' => '',
				'quote_status'   => '',
				'invoice_status' => '',
				'currency'       => '',
				'payment_method' => '',
				'mechanic_id'    => 0,
				'client_id'      => 0,
				'vehicle_id'     => 0,
				'limit'          => Report_Repository::DEFAULT_RECENT_LIMIT,
			)
		);

		$date_from = $this->normalize_date_value( $filters['date_from'] );
		$date_to   = $this->normalize_date_value( $filters['date_to'] );

		if ( '' !== $date_from && '' !== $date_to && strtotime( $date_from ) > strtotime( $date_to ) ) {
			$temp      = $date_from;
			$date_from = $date_to;
			$date_to   = $temp;
		}

		$status_options = array_keys( $this->get_process_status_options() );
		$type_options   = array_keys( $this->get_process_type_options() );
		$derived_options = array_keys( $this->get_derived_status_options() );
		$quote_options  = array_keys( $this->get_quote_status_options() );
		$invoice_options = array_keys( $this->get_invoice_status_options() );
		$currency_options = array_keys( $this->get_currency_options() );
		$payment_method_options = array_keys( $this->get_payment_method_options() );
		$process_status = sanitize_key( $filters['process_status'] );
		$process_type   = sanitize_key( $filters['process_type'] );
		$derived_status = sanitize_key( $filters['derived_status'] );
		$quote_status   = sanitize_key( $filters['quote_status'] );
		$invoice_status = sanitize_key( $filters['invoice_status'] );
		$currency       = strtoupper( sanitize_text_field( (string) $filters['currency'] ) );
		$payment_method = sanitize_key( $filters['payment_method'] );
		$mechanic_id    = absint( $filters['mechanic_id'] );
		$client_id      = absint( $filters['client_id'] );
		$vehicle_id     = absint( $filters['vehicle_id'] );

		if ( ! in_array( $process_status, $status_options, true ) ) {
			$process_status = '';
		}

		if ( ! in_array( $process_type, $type_options, true ) ) {
			$process_type = '';
		}

		if ( ! in_array( $derived_status, $derived_options, true ) ) {
			$derived_status = '';
		}

		if ( ! in_array( $quote_status, $quote_options, true ) ) {
			$quote_status = '';
		}

		if ( ! in_array( $invoice_status, $invoice_options, true ) ) {
			$invoice_status = '';
		}

		if ( ! in_array( $currency, $currency_options, true ) ) {
			$currency = '';
		}

		if ( ! in_array( $payment_method, $payment_method_options, true ) ) {
			$payment_method = '';
		}

		return array(
			'date_from'      => $date_from,
			'date_to'        => $date_to,
			'process_status' => $process_status,
			'process_type'   => $process_type,
			'derived_status' => $derived_status,
			'quote_status'   => $quote_status,
			'invoice_status' => $invoice_status,
			'currency'       => $currency,
			'payment_method' => $payment_method,
			'mechanic_id'    => $mechanic_id,
			'client_id'      => $client_id,
			'vehicle_id'     => $vehicle_id,
			'limit'          => min( Report_Repository::MAX_RECENT_LIMIT, max( 1, absint( $filters['limit'] ) ) ),
		);
	}

	/**
	 * Get configured recent list bounds for the admin UI.
	 *
	 * @return array<string, int>
	 */
	public function get_recent_limit_bounds() {
		return array(
			'default' => Report_Repository::DEFAULT_RECENT_LIMIT,
			'max'     => Report_Repository::MAX_RECENT_LIMIT,
		);
	}

	/**
	 * Get normalized filters for operational reports.
	 *
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array<string, mixed>
	 */
	public function get_operational_filters( array $filters ) {
		$filters = $this->get_normalized_filters( $filters );

		$filters['quote_status']   = '';
		$filters['invoice_status'] = '';
		$filters['currency']       = '';
		$filters['payment_method'] = '';

		return $filters;
	}

	/**
	 * Get normalized filters for financial reports.
	 *
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array<string, mixed>
	 */
	public function get_financial_filters( array $filters ) {
		$filters = $this->get_normalized_filters( $filters );

		$filters['process_status'] = '';
		$filters['process_type']   = '';
		$filters['derived_status'] = '';
		$filters['mechanic_id']    = 0;
		$filters['client_id']      = 0;
		$filters['vehicle_id']     = 0;

		return $filters;
	}

	/**
	 * Get processes grouped by status.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_status_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_process_counts_by_status( $filters );
	}

	/**
	 * Get processes grouped by type.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_type_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_process_counts_by_type( $filters );
	}

	/**
	 * Get recent processes report.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_processes_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_recent_processes( $filters );
	}

	/**
	 * Get recent maintenance report.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_maintenance_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_recent_maintenance( $filters );
	}

	/**
	 * Get recent clients report.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_clients_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_recent_clients( $filters );
	}

	/**
	 * Get recent vehicles report.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_vehicles_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_recent_vehicles( $filters );
	}

	/**
	 * Get quotes grouped by status.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_quote_status_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_quote_counts_by_status( $filters );
	}

	/**
	 * Get recent quotes report.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_quotes_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_recent_quotes( $filters );
	}

	/**
	 * Get invoices grouped by status.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_invoice_status_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_invoice_counts_by_status( $filters );
	}

	/**
	 * Get recent invoices report.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_invoices_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_recent_invoices( $filters );
	}

	/**
	 * Get recent payments report.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_payments_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_recent_payments( $filters );
	}

	/**
	 * Get total invoiced report.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<string, float>
	 */
	public function get_total_invoiced_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_invoiced_amounts_by_currency( $filters );
	}

	/**
	 * Get total paid report.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<string, float>
	 */
	public function get_total_paid_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_paid_amounts_by_currency( $filters );
	}

	/**
	 * Get total outstanding report.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array<string, float>
	 */
	public function get_total_outstanding_report( array $filters = array() ) {
		$filters = $this->get_normalized_filters( $filters );

		return $this->repository->get_outstanding_balances_by_currency( $filters );
	}

	/**
	 * Get all operational report datasets for the admin UI.
	 *
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array<string, mixed>
	 */
	public function get_operational_report_data( array $filters = array() ) {
		$filters = $this->get_operational_filters( $filters );

		return array(
			'filters'              => $filters,
			'process_status'       => $this->repository->get_process_counts_by_status( $filters ),
			'process_types'        => $this->repository->get_process_counts_by_type( $filters ),
			'process_mechanics'    => $this->repository->get_process_counts_by_mechanic( $filters ),
			'process_clients'      => $this->repository->get_process_counts_by_client( $filters ),
			'process_vehicles'     => $this->repository->get_process_counts_by_vehicle( $filters ),
			'derived_status'       => $this->repository->get_process_counts_by_derived_status( $filters ),
			'process_type_status'  => $this->build_process_type_status_matrix_rows( $this->repository->get_process_type_status_matrix( $filters ) ),
			'completed_processes'  => $this->repository->get_completed_process_count( $filters ),
			'ready_for_delivery'   => $this->repository->get_ready_for_delivery_count( $filters ),
			'flow_time_summary'    => $this->repository->get_process_flow_time_summary( $filters ),
			'recent_activity'      => $this->repository->get_recent_activity_summary( $filters ),
			'recent_processes'     => $this->repository->get_recent_processes( $filters ),
			'recent_maintenance'   => $this->repository->get_recent_maintenance( $filters ),
			'recent_clients'       => $this->repository->get_recent_clients( $filters ),
			'recent_vehicles'      => $this->repository->get_recent_vehicles( $filters ),
		);
	}

	/**
	 * Get all financial report datasets for the admin UI.
	 *
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array<string, mixed>
	 */
	public function get_financial_report_data( array $filters = array() ) {
		$filters = $this->get_financial_filters( $filters );

		return array(
			'filters'                   => $filters,
			'quote_status'              => $this->repository->get_quote_counts_by_status( $filters ),
			'recent_quotes'             => $this->repository->get_recent_quotes( $filters ),
			'invoice_status'            => $this->repository->get_invoice_counts_by_status( $filters ),
			'invoice_collection_status' => $this->repository->get_invoice_collection_counts( $filters ),
			'invoice_aging'            => $this->repository->get_invoice_aging_summary( $filters ),
			'recent_invoices'          => $this->repository->get_recent_invoices( $filters ),
			'recent_payments'          => $this->repository->get_recent_payments( $filters ),
			'income_by_period'         => $this->repository->get_income_by_period( $filters ),
			'payment_methods'          => $this->repository->get_payment_method_breakdown( $filters ),
			'top_clients_invoiced'     => $this->repository->get_top_clients_by_invoiced_amount( $filters ),
			'top_clients_paid'         => $this->repository->get_top_clients_by_paid_amount( $filters ),
			'total_invoiced'           => $this->repository->get_invoiced_amounts_by_currency( $filters ),
			'total_paid'               => $this->repository->get_paid_amounts_by_currency( $filters ),
			'total_outstanding'        => $this->repository->get_outstanding_balances_by_currency( $filters ),
			'invoice_amount_components' => $this->repository->get_invoice_amount_components_by_currency( $filters ),
		);
	}

	/**
	 * Get advanced report datasets for the admin UI.
	 *
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array<string, mixed>
	 */
	public function get_advanced_report_data( array $filters = array() ) {
		$filters            = $this->get_normalized_filters( $filters );
		$comparison_periods = $this->build_comparison_periods( $filters );
		$current_filters    = $comparison_periods['current'];
		$previous_filters   = $comparison_periods['previous'];
		$has_previous       = ! empty( $comparison_periods['has_previous'] );

		$process_current = $this->repository->get_process_counts_comparison( $current_filters );
		$process_previous = $has_previous ? $this->repository->get_process_counts_comparison( $previous_filters ) : array( 'total' => 0 );
		$quote_current   = $this->repository->get_quote_counts_comparison( $current_filters );
		$quote_previous  = $has_previous ? $this->repository->get_quote_counts_comparison( $previous_filters ) : array( 'total' => 0 );
		$invoice_current = $this->repository->get_invoice_counts_comparison( $current_filters );
		$invoice_previous = $has_previous ? $this->repository->get_invoice_counts_comparison( $previous_filters ) : array( 'total' => 0 );
		$payment_current = $this->repository->get_payment_totals_comparison( $current_filters );
		$payment_previous = $has_previous ? $this->repository->get_payment_totals_comparison( $previous_filters ) : array();

		return array(
			'filters'                => $filters,
			'comparison_periods'     => $comparison_periods,
			'executive_summary'      => $this->build_executive_summary(
				$process_current,
				$process_previous,
				$quote_current,
				$quote_previous,
				$invoice_current,
				$invoice_previous,
				$payment_current,
				$payment_previous
			),
			'process_comparison'     => $this->build_count_comparison_block( $process_current, $process_previous ),
			'quote_comparison'       => $this->build_count_comparison_block( $quote_current, $quote_previous ),
			'invoice_comparison'     => $this->build_count_comparison_block( $invoice_current, $invoice_previous ),
			'payment_comparison'     => $this->build_currency_comparison_block( $payment_current, $payment_previous ),
			'process_status'         => $this->repository->get_process_grouped_by_status( $current_filters ),
			'process_derived_status' => $this->repository->get_process_counts_by_derived_status( $current_filters ),
			'process_types'          => $this->repository->get_process_grouped_by_type( $current_filters ),
			'quote_status'           => $this->repository->get_quote_grouped_by_status( $current_filters ),
			'invoice_status'         => $this->repository->get_invoice_grouped_by_status( $current_filters ),
			'invoice_aging'          => $this->repository->get_invoice_aging_summary( $current_filters ),
			'payment_methods'        => $this->repository->get_payment_method_breakdown( $current_filters ),
		);
	}

	/**
	 * Get supported CSV export views and labels.
	 *
	 * @return array<string, string>
	 */
	public function get_csv_export_views() {
		return array(
			'recent_processes' => __( 'Procesos recientes', 'super-mechanic' ),
			'recent_quotes'    => __( 'Quotes recientes', 'super-mechanic' ),
			'recent_invoices'  => __( 'Invoices recientes', 'super-mechanic' ),
			'recent_payments'  => __( 'Payments recientes', 'super-mechanic' ),
		);
	}

	/**
	 * Prepare a CSV export payload for a supported report view.
	 *
	 * @param string               $view    Requested export view.
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array<string, mixed>|null
	 */
	public function prepare_csv_export( $view, array $filters = array() ) {
		$view = sanitize_key( $view );

		if ( ! in_array( $view, self::CSV_EXPORT_VIEWS, true ) ) {
			return null;
		}

		$filters = $this->get_normalized_filters( $filters );

		switch ( $view ) {
			case 'recent_processes':
				$rows = $this->repository->get_recent_processes( $this->get_operational_filters( $filters ) );

				return array(
					'filename' => $this->build_csv_filename( $view ),
					'headers'  => array( 'ID', 'Title', 'Process Type', 'Status', 'Vehicle', 'Client', 'Created At' ),
					'rows'     => $this->map_recent_processes_export_rows( $rows ),
				);

			case 'recent_quotes':
				$rows = $this->repository->get_recent_quotes( $this->get_financial_filters( $filters ) );

				return array(
					'filename' => $this->build_csv_filename( $view ),
					'headers'  => array( 'ID', 'Quote Number', 'Status', 'Process', 'Client', 'Currency', 'Grand Total', 'Created At' ),
					'rows'     => $this->map_recent_quotes_export_rows( $rows ),
				);

			case 'recent_invoices':
				$rows = $this->repository->get_recent_invoices( $this->get_financial_filters( $filters ) );

				return array(
					'filename' => $this->build_csv_filename( $view ),
					'headers'  => array( 'ID', 'Invoice Number', 'Status', 'Process', 'Client', 'Currency', 'Grand Total', 'Amount Paid', 'Balance Due', 'Created At' ),
					'rows'     => $this->map_recent_invoices_export_rows( $rows ),
				);

			case 'recent_payments':
				$rows = $this->repository->get_recent_payments( $this->get_financial_filters( $filters ) );

				return array(
					'filename' => $this->build_csv_filename( $view ),
					'headers'  => array( 'ID', 'Invoice Number', 'Invoice Status', 'Client', 'Currency', 'Amount', 'Payment Method', 'Reference', 'Payment Date' ),
					'rows'     => $this->map_recent_payments_export_rows( $rows ),
				);
		}

		return null;
	}

	/**
	 * Get process status options.
	 *
	 * @return array<string, string>
	 */
	public function get_process_status_options() {
		return $this->process_service->get_status_options();
	}

	/**
	 * Get process type options.
	 *
	 * @return array<string, string>
	 */
	public function get_process_type_options() {
		return $this->process_service->get_process_type_options();
	}

	/**
	 * Get quote status options.
	 *
	 * @return array<string, string>
	 */
	public function get_quote_status_options() {
		return array(
			'draft'     => __( 'Draft', 'super-mechanic' ),
			'sent'      => __( 'Sent', 'super-mechanic' ),
			'approved'  => __( 'Approved', 'super-mechanic' ),
			'rejected'  => __( 'Rejected', 'super-mechanic' ),
			'expired'   => __( 'Expired', 'super-mechanic' ),
			'cancelled' => __( 'Cancelled', 'super-mechanic' ),
		);
	}

	/**
	 * Get invoice status options.
	 *
	 * @return array<string, string>
	 */
	public function get_invoice_status_options() {
		return array(
			'draft'          => __( 'Draft', 'super-mechanic' ),
			'issued'         => __( 'Issued', 'super-mechanic' ),
			'partially_paid' => __( 'Partially paid', 'super-mechanic' ),
			'paid'           => __( 'Paid', 'super-mechanic' ),
			'overdue'        => __( 'Overdue', 'super-mechanic' ),
			'cancelled'      => __( 'Cancelled', 'super-mechanic' ),
			'refunded'       => __( 'Refunded', 'super-mechanic' ),
		);
	}

	/**
	 * Get derived process status options.
	 *
	 * @return array<string, string>
	 */
	public function get_derived_status_options() {
		return array(
			'waiting_approval'  => __( 'Waiting approval', 'super-mechanic' ),
			'waiting_payment'   => __( 'Waiting payment', 'super-mechanic' ),
			'ready_for_delivery' => __( 'Ready for delivery', 'super-mechanic' ),
			'completed'         => __( 'Completed', 'super-mechanic' ),
			'open'              => __( 'Open', 'super-mechanic' ),
			'in_progress'       => __( 'In progress', 'super-mechanic' ),
			'on_hold'           => __( 'On hold', 'super-mechanic' ),
			'cancelled'         => __( 'Cancelled', 'super-mechanic' ),
		);
	}

	/**
	 * Get supported currency options from known operational defaults.
	 *
	 * @return array<string, string>
	 */
	public function get_currency_options() {
		return array(
			'USD' => 'USD',
			'EUR' => 'EUR',
			'MXN' => 'MXN',
		);
	}

	/**
	 * Get supported payment method options.
	 *
	 * @return array<string, string>
	 */
	public function get_payment_method_options() {
		return array(
			'cash'     => __( 'Cash', 'super-mechanic' ),
			'transfer' => __( 'Transfer', 'super-mechanic' ),
			'card'     => __( 'Card', 'super-mechanic' ),
			'check'    => __( 'Check', 'super-mechanic' ),
			'other'    => __( 'Other', 'super-mechanic' ),
		);
	}

	/**
	 * Normalize a date input to Y-m-d.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function normalize_date_value( $value ) {
		$value = sanitize_text_field( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? '' : gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Normalize filters once per public report request.
	 *
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array<string, mixed>
	 */
	protected function get_normalized_filters( array $filters ) {
		return $this->validate_filters( $filters );
	}

	/**
	 * Build current and previous comparison periods.
	 *
	 * @param array<string, mixed> $filters Normalized filters.
	 * @return array<string, array<string, mixed>>
	 */
	protected function build_comparison_periods( array $filters ) {
		$current               = $filters;
		$current['limit']      = Report_Repository::MAX_RECENT_LIMIT;
		$previous              = $current;
		$previous['date_from'] = '';
		$previous['date_to']   = '';
		$has_previous          = false;

		if ( '' !== $current['date_from'] && '' !== $current['date_to'] ) {
			$from_timestamp = strtotime( $current['date_from'] . ' 00:00:00' );
			$to_timestamp   = strtotime( $current['date_to'] . ' 23:59:59' );
			$period_days    = max( 1, (int) floor( ( $to_timestamp - $from_timestamp ) / DAY_IN_SECONDS ) + 1 );
			$previous_end   = strtotime( '-1 day', $from_timestamp );
			$previous_start = strtotime( '-' . ( $period_days - 1 ) . ' days', $previous_end );

			$previous['date_from'] = gmdate( 'Y-m-d', $previous_start );
			$previous['date_to']   = gmdate( 'Y-m-d', $previous_end );
			$has_previous          = true;
		}

		return array(
			'current'      => $current,
			'previous'     => $previous,
			'has_previous' => $has_previous,
		);
	}

	/**
	 * Build a simple count comparison block.
	 *
	 * @param array<string, int> $current  Current period values.
	 * @param array<string, int> $previous Previous period values.
	 * @return array<string, mixed>
	 */
	protected function build_count_comparison_block( array $current, array $previous ) {
		$current_total  = isset( $current['total'] ) ? absint( $current['total'] ) : 0;
		$previous_total = isset( $previous['total'] ) ? absint( $previous['total'] ) : 0;

		return array(
			'current_total'  => $current_total,
			'previous_total' => $previous_total,
			'delta'          => $current_total - $previous_total,
			'change_percent' => $this->calculate_percent_change( $current_total, $previous_total ),
		);
	}

	/**
	 * Build a currency comparison block keyed by currency.
	 *
	 * @param array<string, float> $current  Current totals.
	 * @param array<string, float> $previous Previous totals.
	 * @return array<string, array<string, float>>
	 */
	protected function build_currency_comparison_block( array $current, array $previous ) {
		$currencies = array_unique( array_merge( array_keys( $current ), array_keys( $previous ) ) );
		sort( $currencies );
		$rows = array();

		foreach ( $currencies as $currency ) {
			$current_total  = isset( $current[ $currency ] ) ? (float) $current[ $currency ] : 0.0;
			$previous_total = isset( $previous[ $currency ] ) ? (float) $previous[ $currency ] : 0.0;

			$rows[ $currency ] = array(
				'current_total'  => round( $current_total, 2 ),
				'previous_total' => round( $previous_total, 2 ),
				'delta'          => round( $current_total - $previous_total, 2 ),
				'change_percent' => $this->calculate_percent_change( $current_total, $previous_total ),
			);
		}

		return $rows;
	}

	/**
	 * Build a base executive summary.
	 *
	 * @param array<string, int>   $process_current  Current process summary.
	 * @param array<string, int>   $process_previous Previous process summary.
	 * @param array<string, int>   $quote_current    Current quote summary.
	 * @param array<string, int>   $quote_previous   Previous quote summary.
	 * @param array<string, int>   $invoice_current  Current invoice summary.
	 * @param array<string, int>   $invoice_previous Previous invoice summary.
	 * @param array<string, float> $payment_current  Current payment totals.
	 * @param array<string, float> $payment_previous Previous payment totals.
	 * @return array<int, array<string, mixed>>
	 */
	protected function build_executive_summary(
		array $process_current,
		array $process_previous,
		array $quote_current,
		array $quote_previous,
		array $invoice_current,
		array $invoice_previous,
		array $payment_current,
		array $payment_previous
	) {
		return array(
			array(
				'label'          => __( 'Procesos del período', 'super-mechanic' ),
				'metric_type'    => 'count',
				'comparison'     => $this->build_count_comparison_block( $process_current, $process_previous ),
			),
			array(
				'label'          => __( 'Quotes del período', 'super-mechanic' ),
				'metric_type'    => 'count',
				'comparison'     => $this->build_count_comparison_block( $quote_current, $quote_previous ),
			),
			array(
				'label'          => __( 'Invoices del período', 'super-mechanic' ),
				'metric_type'    => 'count',
				'comparison'     => $this->build_count_comparison_block( $invoice_current, $invoice_previous ),
			),
			array(
				'label'          => __( 'Payments cobrados del período', 'super-mechanic' ),
				'metric_type'    => 'currency',
				'comparison'     => $this->build_currency_comparison_block( $payment_current, $payment_previous ),
			),
		);
	}

	/**
	 * Calculate percent change between current and previous values.
	 *
	 * @param float|int $current  Current value.
	 * @param float|int $previous Previous value.
	 * @return float|null
	 */
	protected function calculate_percent_change( $current, $previous ) {
		$current  = (float) $current;
		$previous = (float) $previous;

		if ( 0.0 === $previous ) {
			return null;
		}

		return round( ( ( $current - $previous ) / $previous ) * 100, 2 );
	}

	/**
	 * Build a CSV filename for the requested view.
	 *
	 * @param string $view View key.
	 * @return string
	 */
	protected function build_csv_filename( $view ) {
		return sprintf( 'sm-%s-%s.csv', sanitize_key( $view ), gmdate( 'Ymd-His' ) );
	}

	/**
	 * Convert recent processes rows into CSV rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Raw rows.
	 * @return array<int, array<int, string>>
	 */
	protected function map_recent_processes_export_rows( array $rows ) {
		$export_rows = array();

		foreach ( $rows as $row ) {
			$export_rows[] = array(
				(string) $row['id'],
				(string) $row['title'],
				(string) $row['process_type'],
				(string) $row['status'],
				$this->build_vehicle_label( $row ),
				! empty( $row['client_name'] ) ? (string) $row['client_name'] : '',
				! empty( $row['created_at'] ) ? (string) $row['created_at'] : '',
			);
		}

		return $export_rows;
	}

	/**
	 * Convert recent quotes rows into CSV rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Raw rows.
	 * @return array<int, array<int, string>>
	 */
	protected function map_recent_quotes_export_rows( array $rows ) {
		$export_rows = array();

		foreach ( $rows as $row ) {
			$export_rows[] = array(
				(string) $row['id'],
				(string) $row['quote_number'],
				(string) $row['status'],
				! empty( $row['process_title'] ) ? (string) $row['process_title'] : '',
				! empty( $row['client_name'] ) ? (string) $row['client_name'] : '',
				! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD',
				$this->format_decimal_for_export( $row['grand_total'] ),
				! empty( $row['created_at'] ) ? (string) $row['created_at'] : '',
			);
		}

		return $export_rows;
	}

	/**
	 * Convert recent invoices rows into CSV rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Raw rows.
	 * @return array<int, array<int, string>>
	 */
	protected function map_recent_invoices_export_rows( array $rows ) {
		$export_rows = array();

		foreach ( $rows as $row ) {
			$export_rows[] = array(
				(string) $row['id'],
				(string) $row['invoice_number'],
				(string) $row['status'],
				! empty( $row['process_title'] ) ? (string) $row['process_title'] : '',
				! empty( $row['client_name'] ) ? (string) $row['client_name'] : '',
				! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD',
				$this->format_decimal_for_export( $row['grand_total'] ),
				$this->format_decimal_for_export( $row['amount_paid'] ),
				$this->format_decimal_for_export( $row['balance_due'] ),
				! empty( $row['created_at'] ) ? (string) $row['created_at'] : '',
			);
		}

		return $export_rows;
	}

	/**
	 * Convert recent payments rows into CSV rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Raw rows.
	 * @return array<int, array<int, string>>
	 */
	protected function map_recent_payments_export_rows( array $rows ) {
		$export_rows = array();

		foreach ( $rows as $row ) {
			$export_rows[] = array(
				(string) $row['id'],
				(string) $row['invoice_number'],
				(string) $row['invoice_status'],
				! empty( $row['client_name'] ) ? (string) $row['client_name'] : '',
				! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD',
				$this->format_decimal_for_export( $row['amount'] ),
				! empty( $row['payment_method'] ) ? (string) $row['payment_method'] : '',
				! empty( $row['reference'] ) ? (string) $row['reference'] : '',
				! empty( $row['payment_date'] ) ? (string) $row['payment_date'] : '',
			);
		}

		return $export_rows;
	}

	/**
	 * Build a compact vehicle label for exports.
	 *
	 * @param array<string, mixed> $row Report row.
	 * @return string
	 */
	protected function build_vehicle_label( array $row ) {
		$make  = ! empty( $row['vehicle_make'] ) ? (string) $row['vehicle_make'] : ( ! empty( $row['make'] ) ? (string) $row['make'] : '' );
		$model = ! empty( $row['vehicle_model'] ) ? (string) $row['vehicle_model'] : ( ! empty( $row['model'] ) ? (string) $row['model'] : '' );
		$plate = ! empty( $row['vehicle_plate'] ) ? (string) $row['vehicle_plate'] : ( ! empty( $row['plate'] ) ? (string) $row['plate'] : '' );
		$label = trim( $make . ' ' . $model );

		if ( '' !== $plate ) {
			$label = '' !== $label ? $label . ' - ' . $plate : $plate;
		}

		return $label;
	}

	/**
	 * Format a decimal value for CSV export.
	 *
	 * @param mixed $value Raw numeric value.
	 * @return string
	 */
	protected function format_decimal_for_export( $value ) {
		return number_format( (float) $value, 2, '.', '' );
	}

	/**
	 * Build UI-friendly rows from the process type/status matrix.
	 *
	 * @param array<int, array<string, mixed>> $rows Raw rows.
	 * @return array<int, array<string, mixed>>
	 */
	protected function build_process_type_status_matrix_rows( array $rows ) {
		$formatted = array();

		foreach ( $rows as $row ) {
			$formatted[] = array(
				'process_type' => isset( $row['process_type'] ) ? (string) $row['process_type'] : '',
				'status'       => isset( $row['status'] ) ? (string) $row['status'] : '',
				'total'        => isset( $row['total'] ) ? absint( $row['total'] ) : 0,
			);
		}

		return $formatted;
	}
}
