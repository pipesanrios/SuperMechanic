<?php
/**
 * Report admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Reports;

use Super_Mechanic\Helpers\Feature_Flags;
use Super_Mechanic\Helpers\Plan_Access_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the admin reports page.
 */
class Report_Admin_Controller {
	/**
	 * Report service.
	 *
	 * @var Report_Service
	 */
	protected $service;
	/**
	 * Plan access service.
	 *
	 * @var Plan_Access_Service
	 */
	protected $plan_access_service;

	/**
	 * Constructor.
	 *
	 * @param Report_Service|null      $service Report service.
	 * @param Plan_Access_Service|null $plan_access_service Plan access service.
	 */
	public function __construct( Report_Service $service = null, Plan_Access_Service $plan_access_service = null ) {
		$this->service             = $service ? $service : new Report_Service();
		$this->plan_access_service = $plan_access_service ? $plan_access_service : new Plan_Access_Service();
	}

	/**
	 * Register hooks placeholder for future extensibility.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_post_sm_export_report_csv', array( $this, 'handle_csv_export' ) );
	}

	/**
	 * Render reports page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		if ( ! $this->plan_access_service->is_feature_enabled( Feature_Flags::FEATURE_ADMIN_REPORTS ) ) {
			echo '<div class="wrap sm-admin-shell">';
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'The reports screen is currently disabled by feature flags.', 'super-mechanic' ) . '</p></div>';
			echo '</div>';
			return;
		}

		$filters          = $this->service->validate_filters( wp_unslash( $_GET ) );
		$operational_data = $this->service->get_operational_report_data( $filters );
		$financial_data   = $this->service->get_financial_report_data( $filters );
		$advanced_data    = $this->service->get_advanced_report_data( $filters );

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Reportes', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Base operational, financial, and advanced reports for internal administration.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<span class="sm-badge sm-badge-primary">' . esc_html__( 'Internal analytics', 'super-mechanic' ) . '</span>';
		echo '</div>';

		$this->render_filters_form( $filters );
		$this->render_financial_reports_section( $financial_data, $filters );
		$this->render_operational_reports_section( $operational_data, $filters );
		$this->render_advanced_reports_section( $advanced_data );
		echo '</div>';
	}

	/**
	 * Handle CSV export requests.
	 *
	 * @return void
	 */
	public function handle_csv_export() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to export reports.', 'super-mechanic' ) );
		}

		if ( ! $this->plan_access_service->is_feature_enabled( Feature_Flags::FEATURE_REPORTS_CSV_EXPORT ) ) {
			wp_die( esc_html__( 'CSV export is disabled by feature flags.', 'super-mechanic' ) );
		}

		check_admin_referer( 'sm_export_report_csv', 'sm_report_export_nonce' );

		$view    = isset( $_POST['export_view'] ) ? sanitize_key( wp_unslash( $_POST['export_view'] ) ) : '';
		$filters = $this->service->validate_filters( wp_unslash( $_POST ) );
		$export  = $this->service->prepare_csv_export( $view, $filters );

		if ( empty( $export ) ) {
			wp_die( esc_html__( 'The requested view is not available for CSV export.', 'super-mechanic' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $export['filename'] ) . '"' );

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			wp_die( esc_html__( 'No se pudo generar el archivo CSV.', 'super-mechanic' ) );
		}

		fputcsv( $output, $export['headers'] );

		foreach ( $export['rows'] as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Render financial reports section.
	 *
	 * @param array<string, mixed> $data    Section data.
	 * @param array<string, mixed> $filters Current filters.
	 * @return void
	 */
	protected function render_financial_reports_section( array $data, array $filters ) {
		echo '<hr style="margin:24px 0;" />';
		echo '<section style="margin:24px 0;">';
		echo '<h2>' . esc_html__( 'Financial reports', 'super-mechanic' ) . '</h2>';
		echo '<p>' . esc_html__( 'Base financial summary by date range and status.', 'super-mechanic' ) . '</p>';
		$this->render_financial_totals( $data['total_invoiced'], $data['total_paid'], $data['total_outstanding'] );

		echo '<h3>' . esc_html__( 'Quotes by status', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['quote_status'], __( 'Status', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'uuotes recientes', 'super-mechanic' ) . '</h3>';
		$this->render_csv_export_form( 'recent_quotes', $filters );
		$this->render_recent_quotes_table( $data['recent_quotes'] );

		echo '<h3>' . esc_html__( 'Invoices by status', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['invoice_status'], __( 'Status', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Status de cobro de invoices', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['invoice_collection_status'], __( 'Cobranza', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Agregados monetarios de invoices', 'super-mechanic' ) . '</h3>';
		$this->render_invoice_component_totals_table( $data['invoice_amount_components'] );

		echo '<h3>' . esc_html__( 'Aging simple de invoices', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['invoice_aging'], __( 'Aging', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Invoices recientes', 'super-mechanic' ) . '</h3>';
		$this->render_csv_export_form( 'recent_invoices', $filters );
		$this->render_recent_invoices_table( $data['recent_invoices'] );

		echo '<h3>' . esc_html__( 'Payments by date range (recent)', 'super-mechanic' ) . '</h3>';
		$this->render_csv_export_form( 'recent_payments', $filters );
		$this->render_recent_payments_table( $data['recent_payments'] );

		echo '<h3>' . esc_html__( 'Revenue by period', 'super-mechanic' ) . '</h3>';
		$this->render_income_by_period_table( $data['income_by_period'] );

		echo '<h3>' . esc_html__( 'Payments by method', 'super-mechanic' ) . '</h3>';
		$this->render_payment_method_breakdown_table( $data['payment_methods'] );

		echo '<h3>' . esc_html__( 'Top clients by billing', 'super-mechanic' ) . '</h3>';
		$this->render_top_clients_table( $data['top_clients_invoiced'] );

		echo '<h3>' . esc_html__( 'Top clients by collection', 'super-mechanic' ) . '</h3>';
		$this->render_top_clients_table( $data['top_clients_paid'] );
		echo '</section>';
	}

	/**
	 * Render operational reports section.
	 *
	 * @param array<string, mixed> $data    Section data.
	 * @param array<string, mixed> $filters Current filters.
	 * @return void
	 */
	protected function render_operational_reports_section( array $data, array $filters ) {
		echo '<hr style="margin:24px 0;" />';
		echo '<section style="margin:24px 0;">';
		echo '<h2>' . esc_html__( 'Operational reports', 'super-mechanic' ) . '</h2>';
		echo '<p>' . esc_html__( 'Base operational view with explicit limits for recent lists.', 'super-mechanic' ) . '</p>';

		echo '<h3>' . esc_html__( 'Processes by status', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['process_status'], __( 'Status', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Processes by type', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['process_types'], __( 'Type', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Processes by assigned mechanic', 'super-mechanic' ) . '</h3>';
		echo '<p>' . esc_html__( 'Single FASE 29 criterion: `sm_processes.assigned_to` is used to avoid mixing assignment sources.', 'super-mechanic' ) . '</p>';
		$this->render_summary_table( $data['process_mechanics'], __( 'Mechanic', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Processes by client', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['process_clients'], __( 'Client', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Processes by vehicle', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['process_vehicles'], __( 'Vehicle', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Processes by status derivado', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['derived_status'], __( 'Status derivado', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Process cross-tab by type and status', 'super-mechanic' ) . '</h3>';
		$this->render_process_type_status_matrix_table( $data['process_type_status'] );

		echo '<h3>' . esc_html__( 'Indicadores operativos avanzados', 'super-mechanic' ) . '</h3>';
		$this->render_operational_kpi_cards( $data['completed_processes'], $data['ready_for_delivery'] );

		echo '<h3>' . esc_html__( 'Basic timing by operational flow', 'super-mechanic' ) . '</h3>';
		$this->render_flow_time_summary_table( $data['flow_time_summary'] );

		echo '<h3>' . esc_html__( 'Aggregated relevant recent activity', 'super-mechanic' ) . '</h3>';
		$this->render_recent_activity_summary_table( $data['recent_activity'] );

		echo '<h3>' . esc_html__( 'Recent processes', 'super-mechanic' ) . '</h3>';
		$this->render_csv_export_form( 'recent_processes', $filters );
		$this->render_recent_processes_table( $data['recent_processes'] );

		echo '<h3>' . esc_html__( 'Mantenimientos recientes', 'super-mechanic' ) . '</h3>';
		$this->render_recent_maintenance_table( $data['recent_maintenance'] );

		echo '<h3>' . esc_html__( 'Clients recientes', 'super-mechanic' ) . '</h3>';
		$this->render_recent_clients_table( $data['recent_clients'] );

		echo '<h3>' . esc_html__( 'Vehicles recientes', 'super-mechanic' ) . '</h3>';
		$this->render_recent_vehicles_table( $data['recent_vehicles'] );
		echo '</section>';
	}

	/**
	 * Render advanced reports section.
	 *
	 * @param array<string, mixed> $data Section data.
	 * @return void
	 */
	protected function render_advanced_reports_section( array $data ) {
		$current_period  = $data['comparison_periods']['current'];
		$previous_period = $data['comparison_periods']['previous'];
		$has_previous    = ! empty( $data['comparison_periods']['has_previous'] );

		echo '<hr style="margin:24px 0;" />';
		echo '<section style="margin:24px 0;">';
		echo '<h2>' . esc_html__( 'Base advanced reports', 'super-mechanic' ) . '</h2>';
		echo '<p>' . esc_html__( 'Simple comparisons between the current period and the equivalent previous period, without charts or complex BI.', 'super-mechanic' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Current period:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->format_period_label( $current_period ) ) . ' | <strong>' . esc_html__( 'Previous period:', 'super-mechanic' ) . '</strong> ' . esc_html( $has_previous ? $this->format_period_label( $previous_period ) : __( 'N/A', 'super-mechanic' ) ) . '</p>';

		echo '<h3>' . esc_html__( 'Resumen ejecutivo base', 'super-mechanic' ) . '</h3>';
		$this->render_executive_summary( $data['executive_summary'] );

		echo '<h3>' . esc_html__( 'Range comparisons', 'super-mechanic' ) . '</h3>';
		$this->render_count_comparison_table(
			array(
				'processes' => array(
					'label' => __( 'Processes', 'super-mechanic' ),
					'data'  => $data['process_comparison'],
				),
				'quotes' => array(
					'label' => __( 'uuotes', 'super-mechanic' ),
					'data'  => $data['quote_comparison'],
				),
				'invoices' => array(
					'label' => __( 'Invoices', 'super-mechanic' ),
					'data'  => $data['invoice_comparison'],
				),
			)
		);

		echo '<h3>' . esc_html__( 'Payments by period', 'super-mechanic' ) . '</h3>';
		$this->render_currency_comparison_table( $data['payment_comparison'] );

		echo '<h3>' . esc_html__( 'Processes by status', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['process_status'], __( 'Status', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Processes by status derivado', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['process_derived_status'], __( 'Status derivado', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Processes by type', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['process_types'], __( 'Type', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Quotes by status', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['quote_status'], __( 'Status', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Invoices by status', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['invoice_status'], __( 'Status', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Aging de invoices', 'super-mechanic' ) . '</h3>';
		$this->render_summary_table( $data['invoice_aging'], __( 'Aging', 'super-mechanic' ) );

		echo '<h3>' . esc_html__( 'Payments by method', 'super-mechanic' ) . '</h3>';
		$this->render_payment_method_breakdown_table( $data['payment_methods'] );
		echo '</section>';
	}

	/**
	 * Render filters form.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return void
	 */
	protected function render_filters_form( array $filters ) {
		$status_options  = $this->service->get_process_status_options();
		$type_options    = $this->service->get_process_type_options();
		$quote_options   = $this->service->get_quote_status_options();
		$invoice_options = $this->service->get_invoice_status_options();
		$derived_options = $this->service->get_derived_status_options();
		$currency_options = $this->service->get_currency_options();
		$payment_method_options = $this->service->get_payment_method_options();
		$limit_bounds    = $this->service->get_recent_limit_bounds();

		echo '<form method="get" class="sm-card sm-filter-card sm-report-filters">';
		echo '<input type="hidden" name="page" value="super-mechanic-reports" />';
		echo '<div class="sm-grid sm-grid-two sm-report-filter-columns">';
		echo '<div class="sm-report-filter-column">';
		echo '<h3 class="sm-card-title">' . esc_html__( 'Filtros financieros', 'super-mechanic' ) . '</h3>';
		echo '<div class="sm-filter-grid">';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-date-from"><strong>' . esc_html__( 'Desde', 'super-mechanic' ) . '</strong></label><br />';
		echo '<input id="sm-report-date-from" type="date" name="date_from" value="' . esc_attr( $filters['date_from'] ) . '" />';
		echo '</div>';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-date-to"><strong>' . esc_html__( 'Hasta', 'super-mechanic' ) . '</strong></label><br />';
		echo '<input id="sm-report-date-to" type="date" name="date_to" value="' . esc_attr( $filters['date_to'] ) . '" />';
		echo '</div>';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-limit"><strong>' . esc_html__( 'Recent limit', 'super-mechanic' ) . '</strong></label><br />';
		echo '<input id="sm-report-limit" type="number" min="1" max="' . esc_attr( $limit_bounds['max'] ) . '" name="limit" value="' . esc_attr( $filters['limit'] ) . '" />';
		echo '</div>';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-quote-status"><strong>' . esc_html__( 'Status de quote', 'super-mechanic' ) . '</strong></label><br />';
		echo '<select id="sm-report-quote-status" name="quote_status">';
		echo '<option value="">' . esc_html__( 'Todos', 'super-mechanic' ) . '</option>';
		foreach ( $quote_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $filters['quote_status'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-invoice-status"><strong>' . esc_html__( 'Status de invoice', 'super-mechanic' ) . '</strong></label><br />';
		echo '<select id="sm-report-invoice-status" name="invoice_status">';
		echo '<option value="">' . esc_html__( 'Todos', 'super-mechanic' ) . '</option>';
		foreach ( $invoice_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $filters['invoice_status'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-currency"><strong>' . esc_html__( 'Currency', 'super-mechanic' ) . '</strong></label><br />';
		echo '<select id="sm-report-currency" name="currency">';
		echo '<option value="">' . esc_html__( 'Todas', 'super-mechanic' ) . '</option>';
		foreach ( $currency_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $filters['currency'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="sm-report-filter-column">';
		echo '<label for="sm-report-payment-method"><strong>' . esc_html__( 'Method de pago', 'super-mechanic' ) . '</strong></label><br />';
		echo '<select id="sm-report-payment-method" name="payment_method">';
		echo '<option value="">' . esc_html__( 'Todos', 'super-mechanic' ) . '</option>';
		foreach ( $payment_method_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $filters['payment_method'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div>';
		echo '<h3 class="sm-card-title">' . esc_html__( 'Filtros operativos', 'super-mechanic' ) . '</h3>';
		echo '<div class="sm-filter-grid">';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-process-status"><strong>' . esc_html__( 'Process status', 'super-mechanic' ) . '</strong></label><br />';
		echo '<select id="sm-report-process-status" name="process_status">';
		echo '<option value="">' . esc_html__( 'Todos', 'super-mechanic' ) . '</option>';
		foreach ( $status_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $filters['process_status'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-process-type"><strong>' . esc_html__( 'Process type', 'super-mechanic' ) . '</strong></label><br />';
		echo '<select id="sm-report-process-type" name="process_type">';
		echo '<option value="">' . esc_html__( 'Todos', 'super-mechanic' ) . '</option>';
		foreach ( $type_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $filters['process_type'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-derived-status"><strong>' . esc_html__( 'Status derivado', 'super-mechanic' ) . '</strong></label><br />';
		echo '<select id="sm-report-derived-status" name="derived_status">';
		echo '<option value="">' . esc_html__( 'Todos', 'super-mechanic' ) . '</option>';
		foreach ( $derived_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $filters['derived_status'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-mechanic-id"><strong>' . esc_html__( 'Mechanic (process ID)', 'super-mechanic' ) . '</strong></label><br />';
		echo '<input id="sm-report-mechanic-id" type="number" min="1" name="mechanic_id" value="' . esc_attr( ! empty( $filters['mechanic_id'] ) ? absint( $filters['mechanic_id'] ) : '' ) . '" />';
		echo '</div>';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-client-id"><strong>' . esc_html__( 'Client (ID)', 'super-mechanic' ) . '</strong></label><br />';
		echo '<input id="sm-report-client-id" type="number" min="1" name="client_id" value="' . esc_attr( ! empty( $filters['client_id'] ) ? absint( $filters['client_id'] ) : '' ) . '" />';
		echo '</div>';

		echo '<div class="sm-filter-field">';
		echo '<label for="sm-report-vehicle-id"><strong>' . esc_html__( 'Vehicle (ID)', 'super-mechanic' ) . '</strong></label><br />';
		echo '<input id="sm-report-vehicle-id" type="number" min="1" name="vehicle_id" value="' . esc_attr( ! empty( $filters['vehicle_id'] ) ? absint( $filters['vehicle_id'] ) : '' ) . '" />';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="sm-toolbar sm-report-filter-actions">';
		submit_button( __( 'Filtrar', 'super-mechanic' ), 'primary', '', false );
		echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=super-mechanic-reports' ) ) . '">' . esc_html__( 'Clear filters', 'super-mechanic' ) . '</a>';
		echo '</div>';

		echo '</div>';
		echo '</form>';
	}

	/**
	 * Render a CSV export form for one report view.
	 *
	 * @param string               $view    Export view key.
	 * @param array<string, mixed> $filters Current filters.
	 * @return void
	 */
	protected function render_csv_export_form( $view, array $filters ) {
		$views = $this->service->get_csv_export_views();

		if ( empty( $views[ $view ] ) ) {
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:8px 0 12px 0;">';
		echo '<input type="hidden" name="action" value="sm_export_report_csv" />';
		echo '<input type="hidden" name="export_view" value="' . esc_attr( $view ) . '" />';
		$this->render_export_filter_fields( $filters );
		wp_nonce_field( 'sm_export_report_csv', 'sm_report_export_nonce' );
		submit_button( __( 'Exportar CSV', 'super-mechanic' ), 'secondary', '', false );
		echo '</form>';
	}

	/**
	 * Render hidden filter fields for exports.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return void
	 */
	protected function render_export_filter_fields( array $filters ) {
		$keys = array(
			'date_from',
			'date_to',
			'process_status',
			'process_type',
			'derived_status',
			'quote_status',
			'invoice_status',
			'currency',
			'payment_method',
			'mechanic_id',
			'client_id',
			'vehicle_id',
			'limit',
		);

		foreach ( $keys as $key ) {
			$value = isset( $filters[ $key ] ) ? $filters[ $key ] : '';
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}
	}

	/**
	 * Render financial total cards.
	 *
	 * @param array<string, float> $total_invoiced    Total invoiced.
	 * @param array<string, float> $total_paid        Total paid.
	 * @param array<string, float> $total_outstanding Total outstanding.
	 * @return void
	 */
	protected function render_financial_totals( $total_invoiced, $total_paid, $total_outstanding ) {
		echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:16px;margin:16px 0;">';
		echo '<p style="margin-top:0;">' . esc_html__( 'Financial totals grouped by currency. In 12B, "total invoiced" uses the invoice creation date as the operational criterion. "invoice status" and "payment status" are shown separately.', 'super-mechanic' ) . '</p>';
		echo '<div style="display:flex;gap:16px;flex-wrap:wrap;">';
		$this->render_total_card( __( 'Total invoiced', 'super-mechanic' ), $total_invoiced );
		$this->render_total_card( __( 'Total paid', 'super-mechanic' ), $total_paid );
		$this->render_total_card( __( 'Outstanding balance', 'super-mechanic' ), $total_outstanding );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render a single total card.
	 *
	 * @param string               $label   Label.
	 * @param array<string, float> $amounts Amounts.
	 * @return void
	 */
	protected function render_total_card( $label, array $amounts ) {
		echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:16px;min-width:220px;">';
		echo '<div style="color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">' . esc_html( $label ) . '</div>';
		echo '<div style="margin-top:8px;">';

		if ( empty( $amounts ) ) {
			echo '<div style="font-size:24px;font-weight:600;">' . esc_html( $this->format_money_value( 0, 'USD' ) ) . '</div>';
		} else {
			foreach ( $amounts as $currency => $amount ) {
				echo '<div style="font-size:24px;font-weight:600;">' . esc_html( $this->format_money_value( $amount, $currency ) ) . '</div>';
			}
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render executive summary cards.
	 *
	 * @param array<int, array<string, mixed>> $rows Summary rows.
	 * @return void
	 */
	protected function render_executive_summary( array $rows ) {
		echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">';

		foreach ( $rows as $row ) {
			echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:16px;min-width:260px;">';
			echo '<div style="color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">' . esc_html( $row['label'] ) . '</div>';
			echo '<div style="margin-top:8px;">';

			if ( 'currency' === $row['metric_type'] ) {
				if ( empty( $row['comparison'] ) ) {
					echo '<div style="color:#50575e;">' . esc_html__( 'No comparable data for the selected period.', 'super-mechanic' ) . '</div>';
				} else {
					foreach ( $row['comparison'] as $currency => $comparison ) {
						echo '<div style="font-size:20px;font-weight:600;">' . esc_html( $this->format_money_value( $comparison['current_total'], $currency ) ) . '</div>';
						echo '<div style="color:#50575e;">' . esc_html__( 'Anterior:', 'super-mechanic' ) . ' ' . esc_html( $this->format_money_value( $comparison['previous_total'], $currency ) ) . '</div>';
						echo '<div style="color:#50575e;">' . esc_html__( 'Change:', 'super-mechanic' ) . ' ' . esc_html( $this->format_money_value( $comparison['delta'], $currency ) ) . ' (' . esc_html( $this->format_percent_value( $comparison['change_percent'] ) ) . ')</div>';
					}
				}
			} else {
				echo '<div style="font-size:24px;font-weight:600;">' . esc_html( number_format_i18n( $row['comparison']['current_total'] ) ) . '</div>';
				echo '<div style="color:#50575e;">' . esc_html__( 'Anterior:', 'super-mechanic' ) . ' ' . esc_html( number_format_i18n( $row['comparison']['previous_total'] ) ) . '</div>';
				echo '<div style="color:#50575e;">' . esc_html__( 'Change:', 'super-mechanic' ) . ' ' . esc_html( number_format_i18n( $row['comparison']['delta'] ) ) . ' (' . esc_html( $this->format_percent_value( $row['comparison']['change_percent'] ) ) . ')</div>';
			}

			echo '</div>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render count comparison table.
	 *
	 * @param array<string, array<string, mixed>> $rows Comparison rows.
	 * @return void
	 */
	protected function render_count_comparison_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Metric', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Current period', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Previous period', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Delta', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Change %', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row['label'] ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $row['data']['current_total'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $row['data']['previous_total'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $row['data']['delta'] ) ) . '</td>';
			echo '<td>' . esc_html( $this->format_percent_value( $row['data']['change_percent'] ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render currency comparison table.
	 *
	 * @param array<string, array<string, float>> $rows Comparison rows.
	 * @return void
	 */
	protected function render_currency_comparison_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Currency', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Current period', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Previous period', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Delta', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Change %', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No comparable data for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $currency => $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( strtoupper( $currency ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $row['current_total'], $currency ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $row['previous_total'], $currency ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $row['delta'], $currency ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_percent_value( $row['change_percent'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render summary table.
	 *
	 * @param array<int, array<string, mixed>> $rows         Rows.
	 * @param string                           $label_header Label header.
	 * @return void
	 */
	protected function render_summary_table( array $rows, $label_header ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html( $label_header ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="2">' . esc_html__( 'No data for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( $this->humanize_key( $row['label'] ) ) . '</td>';
				echo '<td>' . esc_html( absint( $row['total'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render recent processes table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_recent_processes_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Title', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Created', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No recent processes for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( $row['id'] ) . '</td>';
				echo '<td>' . esc_html( $row['title'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $row['process_type'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $row['status'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_vehicle_label( $row ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['client_name'] ) ? $row['client_name'] : __( 'Unassigned', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_datetime( $row['created_at'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render recent maintenance table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_recent_maintenance_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Process', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Mechanic', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estimated time', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Created', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No recent maintenance items for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( $row['id'] ) . '</td>';
				echo '<td>' . esc_html( $row['process_title'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $row['process_type'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $row['process_status'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_vehicle_label( $row ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['client_name'] ) ? $row['client_name'] : __( 'Unassigned', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['mechanic_id'] ) ? '#' . absint( $row['mechanic_id'] ) : __( 'Unassigned', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $row['estimated_hours'], 2 ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_datetime( $row['created_at'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render recent clients table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_recent_clients_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Nombre', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Email', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Phone', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Created', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No recent clients for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$name = trim( $row['first_name'] . ' ' . $row['last_name'] );
				echo '<tr>';
				echo '<td>' . esc_html( $row['id'] ) . '</td>';
				echo '<td>' . esc_html( $name ) . '</td>';
				echo '<td>' . esc_html( $row['email'] ) . '</td>';
				echo '<td>' . esc_html( $row['phone'] ) . '</td>';
				echo '<td>' . esc_html( $this->format_datetime( $row['created_at'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render recent vehicles table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_recent_vehicles_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Plate', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Created', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No recent vehicles for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( $row['id'] ) . '</td>';
				echo '<td>' . esc_html( $this->format_vehicle_label( $row ) ) . '</td>';
				echo '<td>' . esc_html( $row['plate'] ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['client_name'] ) ? $row['client_name'] : __( 'Unassigned', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_datetime( $row['created_at'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render recent quotes table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_recent_quotes_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'uuote', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Process', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Created', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No recent quotes for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( $row['id'] ) . '</td>';
				echo '<td>' . esc_html( $row['quote_number'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $row['status'] ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['process_title'] ) ? $row['process_title'] : __( 'No process', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['client_name'] ) ? $row['client_name'] : __( 'Unassigned', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $row['grand_total'], $row['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_datetime( $row['created_at'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render recent invoices table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_recent_invoices_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Invoice', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status de cobro', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Process', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Invoiced', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Paid', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Pending', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Created', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="10">' . esc_html__( 'No recent invoices for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$collection_status = 'pending';

				if ( (float) $row['balance_due'] <= 0 && (float) $row['grand_total'] > 0 ) {
					$collection_status = 'paid';
				} elseif ( (float) $row['amount_paid'] > 0 && (float) $row['balance_due'] > 0 ) {
					$collection_status = 'partial';
				}

				echo '<tr>';
				echo '<td>' . esc_html( $row['id'] ) . '</td>';
				echo '<td>' . esc_html( $row['invoice_number'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $row['status'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $collection_status ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['process_title'] ) ? $row['process_title'] : __( 'No process', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['client_name'] ) ? $row['client_name'] : __( 'Unassigned', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $row['grand_total'], $row['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $row['amount_paid'], $row['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $row['balance_due'], $row['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_datetime( $row['created_at'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render recent payments table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_recent_payments_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Invoice', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status invoice', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Amount', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Method', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Reference', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Payment date', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No recent payments for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( $row['id'] ) . '</td>';
				echo '<td>' . esc_html( $row['invoice_number'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $row['invoice_status'] ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['client_name'] ) ? $row['client_name'] : __( 'Unassigned', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $row['amount'], $row['currency'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $row['payment_method'] ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['reference'] ) ? $row['reference'] : __( 'N/D', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_datetime( $row['payment_date'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render invoice aggregate component totals.
	 *
	 * @param array<string, array<string, float>> $rows Rows keyed by currency.
	 * @return void
	 */
	protected function render_invoice_component_totals_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Currency', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Subtotal', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Impuestos', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Descuentos', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Grand total', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No invoice aggregates for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $currency => $totals ) {
				$currency = strtoupper( (string) $currency );
				$subtotal = isset( $totals['subtotal'] ) ? (float) $totals['subtotal'] : 0.0;
				$tax      = isset( $totals['tax_total'] ) ? (float) $totals['tax_total'] : 0.0;
				$discount = isset( $totals['discount_total'] ) ? (float) $totals['discount_total'] : 0.0;
				$grand    = isset( $totals['grand_total'] ) ? (float) $totals['grand_total'] : 0.0;

				echo '<tr>';
				echo '<td>' . esc_html( $currency ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $subtotal, $currency ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $tax, $currency ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $discount, $currency ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $grand, $currency ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Humanize a key.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		$labels = array(
			'pending' => __( 'Pending', 'super-mechanic' ),
			'partial' => __( 'Parcial', 'super-mechanic' ),
			'paid'    => __( 'Pagado', 'super-mechanic' ),
		);

		if ( isset( $labels[ $value ] ) ) {
			return $labels[ $value ];
		}

		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/**
	 * Render basic income by period table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_income_by_period_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Period', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Currency', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Revenue', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No revenue for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$period_label = ! empty( $row['period_label'] ) ? $row['period_label'] : __( 'N/D', 'super-mechanic' );
				$currency     = ! empty( $row['currency'] ) ? $row['currency'] : 'USD';

				echo '<tr>';
				echo '<td>' . esc_html( $period_label ) . '</td>';
				echo '<td>' . esc_html( strtoupper( $currency ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $row['total'], $currency ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render process type/status matrix table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_process_type_status_matrix_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No data for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( $this->humanize_key( $row['process_type'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $row['status'] ) ) . '</td>';
				echo '<td>' . esc_html( absint( $row['total'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render compact KPI cards for advanced operational metrics.
	 *
	 * @param int $completed_count         Completed processes.
	 * @param int $ready_for_delivery_count Ready-for-delivery processes.
	 * @return void
	 */
	protected function render_operational_kpi_cards( $completed_count, $ready_for_delivery_count ) {
		echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">';
		echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:16px;min-width:220px;">';
		echo '<div style="color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">' . esc_html__( 'Processes finalizados', 'super-mechanic' ) . '</div>';
		echo '<div style="margin-top:8px;font-size:24px;font-weight:600;">' . esc_html( number_format_i18n( absint( $completed_count ) ) ) . '</div>';
		echo '</div>';
		echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:16px;min-width:220px;">';
		echo '<div style="color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">' . esc_html__( 'Listos para entrega', 'super-mechanic' ) . '</div>';
		echo '<div style="margin-top:8px;font-size:24px;font-weight:600;">' . esc_html( number_format_i18n( absint( $ready_for_delivery_count ) ) ) . '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render basic flow time summary table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_flow_time_summary_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Process type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Measured processes', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Average transitions', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Average time', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'Not enough data to calculate times for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( $this->humanize_key( $row['label'] ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( absint( $row['process_count'] ) ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $row['avg_step_transitions'], 2 ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $row['avg_elapsed_hours'], 2 ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render recent activity summary table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_recent_activity_summary_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Activity', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Last occurrence', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No aggregated activity for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( $this->humanize_key( $row['label'] ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( absint( $row['total'] ) ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_datetime( $row['latest_created_at'] ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render payment breakdown table by method and currency.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_payment_method_breakdown_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Method', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Currency', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Payments', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Amount total', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No payments for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$currency = ! empty( $row['currency'] ) ? $row['currency'] : 'USD';
				echo '<tr>';
				echo '<td>' . esc_html( $this->humanize_key( $row['label'] ) ) . '</td>';
				echo '<td>' . esc_html( strtoupper( $currency ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( absint( $row['total'] ) ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $row['amount_total'], $currency ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render top clients table.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_top_clients_table( array $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Currency', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Operations', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Amount total', 'super-mechanic' ) . '</th></tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No highlighted clients for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$currency = ! empty( $row['currency'] ) ? $row['currency'] : 'USD';
				$name     = ! empty( $row['client_name'] ) ? $row['client_name'] : __( 'Unassigned', 'super-mechanic' );
				echo '<tr>';
				echo '<td>' . esc_html( $name ) . '</td>';
				echo '<td>' . esc_html( strtoupper( $currency ) ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( absint( $row['total'] ) ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_money_value( $row['amount_total'], $currency ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Format a vehicle label from report rows.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return string
	 */
	protected function format_vehicle_label( array $row ) {
		$make  = ! empty( $row['vehicle_make'] ) ? $row['vehicle_make'] : ( ! empty( $row['make'] ) ? $row['make'] : '' );
		$model = ! empty( $row['vehicle_model'] ) ? $row['vehicle_model'] : ( ! empty( $row['model'] ) ? $row['model'] : '' );
		$plate = ! empty( $row['vehicle_plate'] ) ? $row['vehicle_plate'] : ( ! empty( $row['plate'] ) ? $row['plate'] : '' );
		$label = trim( $make . ' ' . $model );

		if ( '' !== $plate ) {
			$label .= ' - ' . $plate;
		}

		return '' !== trim( $label ) ? $label : __( 'Unidentified vehicle', 'super-mechanic' );
	}

	/**
	 * Format a datetime value.
	 *
	 * @param string $value Datetime value.
	 * @return string
	 */
	protected function format_datetime( $value ) {
		if ( empty( $value ) ) {
			return __( 'N/D', 'super-mechanic' );
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? (string) $value : wp_date( 'Y-m-d H:i', $timestamp );
	}

	/**
	 * Format a money value.
	 *
	 * @param mixed       $amount Amount.
	 * @param string|null $currency Currency.
	 * @return string
	 */
	protected function format_money_value( $amount, $currency = null ) {
		$formatted = number_format_i18n( (float) $amount, 2 );

		return $currency ? $formatted . ' ' . strtoupper( (string) $currency ) : $formatted . ' USD';
	}

	/**
	 * Format a percent value.
	 *
	 * @param float|int|null $value Percent value.
	 * @return string
	 */
	protected function format_percent_value( $value ) {
		if ( null === $value ) {
			return __( 'N/A', 'super-mechanic' );
		}

		return number_format_i18n( (float) $value, 2 ) . '%';
	}

	/**
	 * Format a period label for the advanced report block.
	 *
	 * @param array<string, mixed> $period Period filters.
	 * @return string
	 */
	protected function format_period_label( array $period ) {
		$date_from = ! empty( $period['date_from'] ) ? (string) $period['date_from'] : '';
		$date_to   = ! empty( $period['date_to'] ) ? (string) $period['date_to'] : '';

		if ( '' !== $date_from && '' !== $date_to ) {
			return $date_from . ' - ' . $date_to;
		}

		if ( '' !== $date_from ) {
			return sprintf( __( 'Desde %s', 'super-mechanic' ), $date_from );
		}

		if ( '' !== $date_to ) {
			return sprintf( __( 'Hasta %s', 'super-mechanic' ), $date_to );
		}

		return __( 'No date range', 'super-mechanic' );
	}
}






