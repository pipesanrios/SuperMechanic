<?php
/**
 * Report service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Reports;

use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Helpers\Settings_Service;
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
		'financial_base',
		'operational_base',
		'client_summary',
		'vehicle_summary',
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
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;
	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Constructor.
	 *
	 * @param Report_Repository|null $repository      Report repository.
	 * @param Process_Service|null   $process_service Process service.
	 */
	public function __construct( Report_Repository $repository = null, Process_Service $process_service = null, Business_Context_Service $business_context_service = null, Settings_Service $settings_service = null ) {
		$this->repository               = $repository ? $repository : new Report_Repository();
		$this->process_service          = $process_service ? $process_service : new Process_Service();
		$this->settings_service         = $settings_service ? $settings_service : new Settings_Service();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service( $this->settings_service );
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
				'business_id'    => 0,
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
		$business_id    = absint( $filters['business_id'] );

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

		if ( $business_id > 0 ) {
			$business_id = absint( $this->business_context_service->normalize_business_id( $business_id ) );
		} else {
			$business_id = absint( $this->business_context_service->resolve_business_id() );
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
			'business_id'    => $business_id,
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
		$closed_statuses = $this->get_closed_process_statuses();

		return array(
			'filters'              => $filters,
			'process_status'       => $this->repository->get_process_counts_by_status( $filters ),
			'process_types'        => $this->repository->get_process_counts_by_type( $filters ),
			'process_mechanics'    => $this->repository->get_process_counts_by_mechanic( $filters ),
			'process_clients'      => $this->repository->get_process_counts_by_client( $filters ),
			'process_vehicles'     => $this->repository->get_process_counts_by_vehicle( $filters ),
			'open_closed_processes' => $this->repository->get_open_closed_process_totals( $filters, $closed_statuses ),
			'closed_statuses'      => $closed_statuses,
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
	 * Build actionable KPI and control-block data.
	 *
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array<string, mixed>
	 */
	public function get_actionable_kpis_data( array $filters = array() ) {
		$operational_filters = $this->get_operational_filters( $filters );
		$financial_filters   = $this->get_financial_filters( $filters );
		$closed_statuses     = $this->get_closed_process_statuses();
		$open_closed         = $this->repository->get_open_closed_process_totals( $operational_filters, $closed_statuses );
		$invoice_aging       = $this->repository->get_invoice_aging_summary( $financial_filters );
		$outstanding         = $this->repository->get_outstanding_balances_by_currency( $financial_filters );
		$recent_payments     = $this->repository->get_recent_payments( $financial_filters );
		$invoice_kpis        = $this->repository->get_invoice_kpis_by_currency( $financial_filters );
		$top_clients         = $this->repository->get_top_clients_by_invoiced_amount( $financial_filters );
		$top_vehicles        = $this->repository->get_vehicle_summary_rows( $financial_filters );
		$process_status      = $this->repository->get_process_counts_by_status( $operational_filters );
		$process_types       = $this->repository->get_process_counts_by_type( $operational_filters );

		return array(
			'filters'             => array(
				'business_id' => $financial_filters['business_id'],
				'date_from'   => $financial_filters['date_from'],
				'date_to'     => $financial_filters['date_to'],
			),
			'kpis'                => array(
				'open_processes'      => isset( $open_closed['open'] ) ? absint( $open_closed['open'] ) : 0,
				'closed_processes'    => isset( $open_closed['closed'] ) ? absint( $open_closed['closed'] ) : 0,
				'overdue_invoices'    => $this->extract_total_by_label( $invoice_aging, 'overdue' ),
				'total_outstanding'   => $outstanding,
				'recent_payments'     => $this->summarize_recent_payments( $recent_payments ),
				'average_ticket'      => $this->extract_average_ticket_by_currency( $invoice_kpis ),
			),
			'blocks'              => array(
				'requires_attention' => array(
					'overdue_invoices' => $this->extract_total_by_label( $invoice_aging, 'overdue' ),
					'open_processes'   => isset( $open_closed['open'] ) ? absint( $open_closed['open'] ) : 0,
				),
				'pending_collection' => $outstanding,
				'most_activity'      => array(
					'top_clients'  => array_slice( is_array( $top_clients ) ? $top_clients : array(), 0, 5 ),
					'top_vehicles' => array_slice( is_array( $top_vehicles ) ? $top_vehicles : array(), 0, 5 ),
				),
				'top_billing'        => array_slice( is_array( $top_clients ) ? $top_clients : array(), 0, 5 ),
				'critical_states'    => array(
					'process_status' => array_slice( is_array( $process_status ) ? $process_status : array(), 0, 6 ),
					'process_types'  => array_slice( is_array( $process_types ) ? $process_types : array(), 0, 6 ),
				),
			),
			'operational_load'    => array(
				'by_status' => array_slice( is_array( $process_status ) ? $process_status : array(), 0, 8 ),
				'by_type'   => array_slice( is_array( $process_types ) ? $process_types : array(), 0, 8 ),
			),
			'closed_statuses'     => $closed_statuses,
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
			'invoice_kpis'             => $this->repository->get_invoice_kpis_by_currency( $filters ),
			'client_summary'           => $this->repository->get_client_summary_rows( $filters ),
			'vehicle_summary'          => $this->repository->get_vehicle_summary_rows( $filters ),
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
			'financial_base'  => __( 'Financial base', 'super-mechanic' ),
			'operational_base' => __( 'Operational base', 'super-mechanic' ),
			'client_summary'  => __( 'Client summary', 'super-mechanic' ),
			'vehicle_summary' => __( 'Vehicle summary', 'super-mechanic' ),
			'recent_processes' => __( 'Recent processes', 'super-mechanic' ),
			'recent_quotes'    => __( 'Recent quotes', 'super-mechanic' ),
			'recent_invoices'  => __( 'Recent invoices', 'super-mechanic' ),
			'recent_payments'  => __( 'Recent payments', 'super-mechanic' ),
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
			case 'financial_base':
				$financial_data = $this->get_financial_report_data( $this->get_financial_filters( $filters ) );

				return array(
					'filename' => $this->build_csv_filename( $view ),
					'headers'  => array( 'Metric', 'Currency', 'Value' ),
					'rows'     => $this->map_financial_base_export_rows( $financial_data ),
				);

			case 'operational_base':
				$operational_data = $this->get_operational_report_data( $this->get_operational_filters( $filters ) );

				return array(
					'filename' => $this->build_csv_filename( $view ),
					'headers'  => array( 'Section', 'Label', 'Total' ),
					'rows'     => $this->map_operational_base_export_rows( $operational_data ),
				);

			case 'client_summary':
				$financial_data = $this->get_financial_report_data( $this->get_financial_filters( $filters ) );

				return array(
					'filename' => $this->build_csv_filename( $view ),
					'headers'  => array( 'Client', 'Processes', 'Currency', 'Billed', 'Paid' ),
					'rows'     => $this->map_client_summary_export_rows( isset( $financial_data['client_summary'] ) ? $financial_data['client_summary'] : array() ),
				);

			case 'vehicle_summary':
				$financial_data = $this->get_financial_report_data( $this->get_financial_filters( $filters ) );

				return array(
					'filename' => $this->build_csv_filename( $view ),
					'headers'  => array( 'Vehicle', 'Processes', 'Currency', 'Accumulated Cost' ),
					'rows'     => $this->map_vehicle_summary_export_rows( isset( $financial_data['vehicle_summary'] ) ? $financial_data['vehicle_summary'] : array() ),
				);

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
	 * Convert financial base data into CSV rows.
	 *
	 * @param array<string, mixed> $data Financial report data.
	 * @return array<int, array<int, string>>
	 */
	protected function map_financial_base_export_rows( array $data ) {
		$rows       = array();
		$currencies = array_unique(
			array_merge(
				array_keys( isset( $data['total_invoiced'] ) && is_array( $data['total_invoiced'] ) ? $data['total_invoiced'] : array() ),
				array_keys( isset( $data['total_paid'] ) && is_array( $data['total_paid'] ) ? $data['total_paid'] : array() ),
				array_keys( isset( $data['total_outstanding'] ) && is_array( $data['total_outstanding'] ) ? $data['total_outstanding'] : array() ),
				array_keys( isset( $data['invoice_kpis'] ) && is_array( $data['invoice_kpis'] ) ? $data['invoice_kpis'] : array() )
			)
		);

		if ( empty( $currencies ) ) {
			$currencies = array( 'USD' );
		}

		sort( $currencies );

		foreach ( $currencies as $currency ) {
			$currency_code = strtoupper( (string) $currency );
			$kpi_row       = isset( $data['invoice_kpis'][ $currency ] ) && is_array( $data['invoice_kpis'][ $currency ] ) ? $data['invoice_kpis'][ $currency ] : array();
			$invoice_count = isset( $kpi_row['invoice_count'] ) ? absint( $kpi_row['invoice_count'] ) : 0;
			$avg_ticket    = isset( $kpi_row['average_ticket'] ) ? (float) $kpi_row['average_ticket'] : 0.0;

			$rows[] = array( 'Total billed', $currency_code, $this->format_decimal_for_export( isset( $data['total_invoiced'][ $currency ] ) ? $data['total_invoiced'][ $currency ] : 0 ) );
			$rows[] = array( 'Total paid', $currency_code, $this->format_decimal_for_export( isset( $data['total_paid'][ $currency ] ) ? $data['total_paid'][ $currency ] : 0 ) );
			$rows[] = array( 'Pending', $currency_code, $this->format_decimal_for_export( isset( $data['total_outstanding'][ $currency ] ) ? $data['total_outstanding'][ $currency ] : 0 ) );
			$rows[] = array( 'Invoices', $currency_code, (string) $invoice_count );
			$rows[] = array( 'Average ticket', $currency_code, $this->format_decimal_for_export( $avg_ticket ) );
		}

		return $rows;
	}

	/**
	 * Convert operational base data into CSV rows.
	 *
	 * @param array<string, mixed> $data Operational report data.
	 * @return array<int, array<int, string>>
	 */
	protected function map_operational_base_export_rows( array $data ) {
		$rows = array();

		if ( isset( $data['open_closed_processes'] ) && is_array( $data['open_closed_processes'] ) ) {
			$rows[] = array( 'Open vs closed', 'Open', (string) absint( isset( $data['open_closed_processes']['open'] ) ? $data['open_closed_processes']['open'] : 0 ) );
			$rows[] = array( 'Open vs closed', 'Closed', (string) absint( isset( $data['open_closed_processes']['closed'] ) ? $data['open_closed_processes']['closed'] : 0 ) );
			$rows[] = array( 'Open vs closed', 'Total', (string) absint( isset( $data['open_closed_processes']['total'] ) ? $data['open_closed_processes']['total'] : 0 ) );
		}

		if ( isset( $data['process_status'] ) && is_array( $data['process_status'] ) ) {
			foreach ( $data['process_status'] as $row ) {
				$rows[] = array(
					'Processes by status',
					isset( $row['label'] ) ? (string) $row['label'] : '',
					(string) absint( isset( $row['total'] ) ? $row['total'] : 0 ),
				);
			}
		}

		if ( isset( $data['process_types'] ) && is_array( $data['process_types'] ) ) {
			foreach ( $data['process_types'] as $row ) {
				$rows[] = array(
					'Processes by type',
					isset( $row['label'] ) ? (string) $row['label'] : '',
					(string) absint( isset( $row['total'] ) ? $row['total'] : 0 ),
				);
			}
		}

		return $rows;
	}

	/**
	 * Convert client summary rows into CSV rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Summary rows.
	 * @return array<int, array<int, string>>
	 */
	protected function map_client_summary_export_rows( array $rows ) {
		$export_rows = array();

		foreach ( $rows as $row ) {
			$currency      = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD';
			$export_rows[] = array(
				isset( $row['label'] ) ? (string) $row['label'] : '',
				(string) absint( isset( $row['total_processes'] ) ? $row['total_processes'] : 0 ),
				$currency,
				$this->format_decimal_for_export( isset( $row['total_billed'] ) ? $row['total_billed'] : 0 ),
				$this->format_decimal_for_export( isset( $row['total_paid'] ) ? $row['total_paid'] : 0 ),
			);
		}

		return $export_rows;
	}

	/**
	 * Convert vehicle summary rows into CSV rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Summary rows.
	 * @return array<int, array<int, string>>
	 */
	protected function map_vehicle_summary_export_rows( array $rows ) {
		$export_rows = array();

		foreach ( $rows as $row ) {
			$currency      = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD';
			$export_rows[] = array(
				isset( $row['label'] ) ? (string) $row['label'] : '',
				(string) absint( isset( $row['total_processes'] ) ? $row['total_processes'] : 0 ),
				$currency,
				$this->format_decimal_for_export( isset( $row['accumulated_cost'] ) ? $row['accumulated_cost'] : 0 ),
			);
		}

		return $export_rows;
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
		return $this->settings_service->get_supported_currencies();
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
	 * Resolve closed process statuses using stable service mapping.
	 *
	 * @return array<int, string>
	 */
	protected function get_closed_process_statuses() {
		$closed_statuses = array();

		foreach ( array_keys( $this->get_process_status_options() ) as $status_key ) {
			if ( ! $this->process_service->is_active_status( $status_key ) ) {
				$closed_statuses[] = $status_key;
			}
		}

		return $closed_statuses;
	}

	/**
	 * Extract grouped total from summary rows by label.
	 *
	 * @param array<int, array<string, mixed>> $rows  Summary rows.
	 * @param string                            $label Target label.
	 * @return int
	 */
	protected function extract_total_by_label( array $rows, $label ) {
		$target = strtolower( (string) $label );

		foreach ( $rows as $row ) {
			if ( strtolower( (string) ( isset( $row['label'] ) ? $row['label'] : '' ) ) === $target ) {
				return absint( isset( $row['total'] ) ? $row['total'] : 0 );
			}
		}

		return 0;
	}

	/**
	 * Summarize recent payments rows by currency.
	 *
	 * @param array<int, array<string, mixed>> $rows Payment rows.
	 * @return array<string, mixed>
	 */
	protected function summarize_recent_payments( array $rows ) {
		$totals = array();

		foreach ( $rows as $row ) {
			$currency = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD';
			$amount   = isset( $row['amount'] ) ? (float) $row['amount'] : 0.0;

			if ( ! isset( $totals[ $currency ] ) ) {
				$totals[ $currency ] = 0.0;
			}

			$totals[ $currency ] += $amount;
		}

		foreach ( $totals as $currency => $total ) {
			$totals[ $currency ] = round( (float) $total, 2 );
		}

		return array(
			'count'  => count( $rows ),
			'totals' => $totals,
			'rows'   => array_slice( $rows, 0, 5 ),
		);
	}

	/**
	 * Extract average ticket per currency.
	 *
	 * @param array<string, array<string, mixed>> $invoice_kpis KPI rows keyed by currency.
	 * @return array<string, float>
	 */
	protected function extract_average_ticket_by_currency( array $invoice_kpis ) {
		$result = array();

		foreach ( $invoice_kpis as $currency => $row ) {
			$result[ strtoupper( (string) $currency ) ] = isset( $row['average_ticket'] ) ? round( (float) $row['average_ticket'], 2 ) : 0.0;
		}

		return $result;
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
				'label'          => __( 'Processes in period', 'super-mechanic' ),
				'metric_type'    => 'count',
				'comparison'     => $this->build_count_comparison_block( $process_current, $process_previous ),
			),
			array(
				'label'          => __( 'Quotes in period', 'super-mechanic' ),
				'metric_type'    => 'count',
				'comparison'     => $this->build_count_comparison_block( $quote_current, $quote_previous ),
			),
			array(
				'label'          => __( 'Invoices in period', 'super-mechanic' ),
				'metric_type'    => 'count',
				'comparison'     => $this->build_count_comparison_block( $invoice_current, $invoice_previous ),
			),
			array(
				'label'          => __( 'Collected payments in period', 'super-mechanic' ),
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
