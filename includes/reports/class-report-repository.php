<?php
/**
 * Report repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Reports;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes report queries for admin reporting.
 */
class Report_Repository {
	/**
	 * Invoice statuses considered financially valid for billed metrics.
	 */
	const VALID_BILLED_INVOICE_STATUSES = array(
		'issued',
		'partially_paid',
		'paid',
		'overdue',
	);

	/**
	 * Default limit for recent report lists.
	 */
	const DEFAULT_RECENT_LIMIT = 20;

	/**
	 * Maximum limit for recent report lists and exports.
	 */
	const MAX_RECENT_LIMIT = 50;

	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Business_Context_Service|null $business_context_service Business context service.
	 */
	public function __construct( Business_Context_Service $business_context_service = null ) {
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Get process counts grouped by status.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_counts_by_status( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_process_where_clause( $filters, $params, 'p' );
		$sql     = "SELECT p.status AS label, COUNT(p.id) AS total
			FROM {$this->get_processes_table_name()} p
			{$where}
			GROUP BY p.status
			ORDER BY total DESC, p.status ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get grouped process counts by status for advanced reports.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_grouped_by_status( array $filters = array() ) {
		return $this->get_process_counts_by_status( $filters );
	}

	/**
	 * Get process counts grouped by type.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_counts_by_type( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_process_where_clause( $filters, $params, 'p' );
		$sql     = "SELECT p.process_type AS label, COUNT(p.id) AS total
			FROM {$this->get_processes_table_name()} p
			{$where}
			GROUP BY p.process_type
			ORDER BY total DESC, p.process_type ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get grouped process counts by type for advanced reports.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_grouped_by_type( array $filters = array() ) {
		return $this->get_process_counts_by_type( $filters );
	}

	/**
	 * Get process counts grouped by assigned mechanic.
	 *
	 * Criteria is explicitly based on `sm_processes.assigned_to`.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_counts_by_mechanic( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_process_where_clause( $filters, $params, 'p' );
		$sql     = "SELECT COALESCE(NULLIF(p.assigned_to, 0), 0) AS mechanic_id,
				COALESCE(u.display_name, '') AS mechanic_name,
				COUNT(p.id) AS total
			FROM {$this->get_processes_table_name()} p
			LEFT JOIN {$wpdb->users} u ON u.ID = p.assigned_to
			{$where}
			GROUP BY COALESCE(NULLIF(p.assigned_to, 0), 0), u.display_name
			ORDER BY total DESC, mechanic_name ASC, mechanic_id ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );
		$rows    = is_array( $rows ) ? $rows : array();
		$result  = array();

		foreach ( $rows as $row ) {
			$mechanic_id = absint( $row['mechanic_id'] );
			$name        = isset( $row['mechanic_name'] ) ? trim( (string) $row['mechanic_name'] ) : '';
			$label       = __( 'Sin asignar', 'super-mechanic' );

			if ( $mechanic_id > 0 ) {
				$label = '' !== $name ? $name . ' (#' . $mechanic_id . ')' : '#' . $mechanic_id;
			}

			$result[] = array(
				'mechanic_id' => $mechanic_id,
				'label'       => $label,
				'total'       => absint( $row['total'] ),
			);
		}

		return $result;
	}

	/**
	 * Get process counts grouped by client.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_counts_by_client( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_process_where_clause( $filters, $params, 'p' );
		$sql     = "SELECT COALESCE(NULLIF(p.client_id, 0), 0) AS client_id,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
				COUNT(p.id) AS total
			FROM {$this->get_processes_table_name()} p
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = p.client_id
			{$where}
			GROUP BY COALESCE(NULLIF(p.client_id, 0), 0), client_name
			ORDER BY total DESC, client_name ASC, client_id ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );
		$rows    = is_array( $rows ) ? $rows : array();
		$result  = array();

		foreach ( $rows as $row ) {
			$client_id = absint( $row['client_id'] );
			$name      = isset( $row['client_name'] ) ? trim( (string) $row['client_name'] ) : '';
			$label     = __( 'Sin asignar', 'super-mechanic' );

			if ( $client_id > 0 ) {
				$label = '' !== $name ? $name . ' (#' . $client_id . ')' : '#' . $client_id;
			}

			$result[] = array(
				'client_id' => $client_id,
				'label'     => $label,
				'total'     => absint( $row['total'] ),
			);
		}

		return $result;
	}

	/**
	 * Get process counts grouped by vehicle.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_counts_by_vehicle( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_process_where_clause( $filters, $params, 'p' );
		$sql     = "SELECT COALESCE(NULLIF(p.vehicle_id, 0), 0) AS vehicle_id,
				v.make AS vehicle_make,
				v.model AS vehicle_model,
				v.plate AS vehicle_plate,
				COUNT(p.id) AS total
			FROM {$this->get_processes_table_name()} p
			LEFT JOIN {$this->get_vehicles_table_name()} v ON v.id = p.vehicle_id
			{$where}
			GROUP BY COALESCE(NULLIF(p.vehicle_id, 0), 0), v.make, v.model, v.plate
			ORDER BY total DESC, vehicle_make ASC, vehicle_model ASC, vehicle_id ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );
		$rows    = is_array( $rows ) ? $rows : array();
		$result  = array();

		foreach ( $rows as $row ) {
			$vehicle_id = absint( $row['vehicle_id'] );
			$label      = __( 'Sin asignar', 'super-mechanic' );

			if ( $vehicle_id > 0 ) {
				$vehicle_label = $this->build_vehicle_group_label( $row );
				$label         = '' !== $vehicle_label ? $vehicle_label . ' (#' . $vehicle_id . ')' : '#' . $vehicle_id;
			}

			$result[] = array(
				'vehicle_id' => $vehicle_id,
				'label'      => $label,
				'total'      => absint( $row['total'] ),
			);
		}

		return $result;
	}

	/**
	 * Get aggregate process counts for a selected range.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<string, int>
	 */
	public function get_process_counts_comparison( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_process_where_clause( $filters, $params, 'p' );
		$sql     = "SELECT COUNT(p.id) AS total
			FROM {$this->get_processes_table_name()} p
			{$where}";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$total   = $wpdb->get_var( $query );

		return array(
			'total' => absint( $total ),
		);
	}

	/**
	 * Get recent processes with optional filters.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_processes( array $filters = array() ) {
		global $wpdb;

		$filters  = $this->normalize_filters( $filters );
		$params   = array();
		$where    = $this->build_process_where_clause( $filters, $params, 'p' );
		$params[] = $filters['limit'];
		$sql      = "SELECT p.id, p.title, p.process_type, p.status, p.created_at, p.opened_at, p.updated_at,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
				v.make AS vehicle_make,
				v.model AS vehicle_model,
				v.plate AS vehicle_plate
			FROM {$this->get_processes_table_name()} p
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = p.client_id
			LEFT JOIN {$this->get_vehicles_table_name()} v ON v.id = p.vehicle_id
			{$where}
			ORDER BY p.created_at DESC, p.id DESC
			LIMIT %d";
		$query    = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get recent maintenance rows with optional filters.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_maintenance( array $filters = array() ) {
		global $wpdb;

		$filters  = $this->normalize_filters( $filters );
		$params   = array();
		$clauses  = array();
		$date_sql = $this->build_date_range_where( 'm.created_at', $filters, $params );
		$proc_sql = $this->build_process_filter_fragments( $filters, $params, 'p' );

		if ( '' !== $date_sql ) {
			$clauses[] = $date_sql;
		}

		if ( ! empty( $proc_sql ) ) {
			$clauses = array_merge( $clauses, $proc_sql );
		}

		$where    = empty( $clauses ) ? '' : 'WHERE ' . implode( ' AND ', $clauses );
		$params[] = $filters['limit'];
		$sql      = "SELECT m.id, m.process_id, m.diagnosis, m.client_approved, m.mechanic_id, m.estimated_hours, m.created_at, m.updated_at,
				p.title AS process_title,
				p.process_type,
				p.status AS process_status,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
				v.make AS vehicle_make,
				v.model AS vehicle_model,
				v.plate AS vehicle_plate
			FROM {$this->get_maintenance_table_name()} m
			INNER JOIN {$this->get_processes_table_name()} p ON p.id = m.process_id
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = p.client_id
			LEFT JOIN {$this->get_vehicles_table_name()} v ON v.id = p.vehicle_id
			{$where}
			ORDER BY m.created_at DESC, m.id DESC
			LIMIT %d";
		$query    = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get recent clients with optional date filters.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_clients( array $filters = array() ) {
		global $wpdb;

		$filters  = $this->normalize_filters( $filters );
		$params   = array();
		$clauses  = array( 'c.business_id = %d' );
		$params[] = absint( $filters['business_id'] );
		$date_sql = $this->build_date_range_where( 'c.created_at', $filters, $params );

		if ( '' !== $date_sql ) {
			$clauses[] = $date_sql;
		}

		$where    = 'WHERE ' . implode( ' AND ', $clauses );
		$params[] = $filters['limit'];
		$sql      = "SELECT c.id, c.first_name, c.last_name, c.email, c.phone, c.status, c.created_at
                        FROM {$this->get_clients_table_name()} c
                        {$where}
                        ORDER BY c.created_at DESC, c.id DESC
			LIMIT %d";
		$query    = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get recent vehicles with optional date filters.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_vehicles( array $filters = array() ) {
		global $wpdb;

		$filters  = $this->normalize_filters( $filters );
		$params   = array();
		$clauses  = array( 'v.business_id = %d' );
		$params[] = absint( $filters['business_id'] );
		$date_sql = $this->build_date_range_where( 'v.created_at', $filters, $params );

		if ( '' !== $date_sql ) {
			$clauses[] = $date_sql;
		}

		$where    = 'WHERE ' . implode( ' AND ', $clauses );
		$params[] = $filters['limit'];
		$sql      = "SELECT v.id, v.make, v.model, v.year, v.plate, v.vin, v.created_at,
                                CONCAT_WS(' ', c.first_name, c.last_name) AS client_name
                        FROM {$this->get_vehicles_table_name()} v
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = v.client_id
			{$where}
			ORDER BY v.created_at DESC, v.id DESC
			LIMIT %d";
		$query    = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get quote counts grouped by status.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_quote_counts_by_status( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_quote_where_clause( $filters, $params, 'q' );
		$sql     = "SELECT q.status AS label, COUNT(q.id) AS total
			FROM {$this->get_quotes_table_name()} q
			{$where}
			GROUP BY q.status
			ORDER BY total DESC, q.status ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get grouped quote counts by status for advanced reports.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_quote_grouped_by_status( array $filters = array() ) {
		return $this->get_quote_counts_by_status( $filters );
	}

	/**
	 * Get aggregate quote counts for a selected range.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<string, int>
	 */
	public function get_quote_counts_comparison( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_quote_where_clause( $filters, $params, 'q' );
		$sql     = "SELECT COUNT(q.id) AS total
			FROM {$this->get_quotes_table_name()} q
			{$where}";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$total   = $wpdb->get_var( $query );

		return array(
			'total' => absint( $total ),
		);
	}

	/**
	 * Get recent quotes with optional filters.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_quotes( array $filters = array() ) {
		global $wpdb;

		$filters  = $this->normalize_filters( $filters );
		$params   = array();
		$where    = $this->build_quote_where_clause( $filters, $params, 'q' );
		$params[] = $filters['limit'];
		$sql      = "SELECT q.id, q.process_id, q.client_id, q.quote_number, q.status, q.currency, q.grand_total,
				q.approved_by_client, q.approved_at, q.rejected_at, q.created_at, q.updated_at,
				p.title AS process_title,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name
			FROM {$this->get_quotes_table_name()} q
			LEFT JOIN {$this->get_processes_table_name()} p ON p.id = q.process_id
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = q.client_id
			{$where}
			ORDER BY q.created_at DESC, q.id DESC
			LIMIT %d";
		$query    = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get invoice counts grouped by status.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_invoice_counts_by_status( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_invoice_where_clause( $filters, $params, 'i' );
		$sql     = "SELECT i.status AS label, COUNT(i.id) AS total
			FROM {$this->get_invoices_table_name()} i
			{$where}
			GROUP BY i.status
			ORDER BY total DESC, i.status ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get grouped invoice counts by status for advanced reports.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_invoice_grouped_by_status( array $filters = array() ) {
		return $this->get_invoice_counts_by_status( $filters );
	}

	/**
	 * Get aggregate invoice counts for a selected range.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<string, int>
	 */
	public function get_invoice_counts_comparison( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_invoice_where_clause( $filters, $params, 'i' );
		$sql     = "SELECT COUNT(i.id) AS total
			FROM {$this->get_invoices_table_name()} i
			{$where}";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$total   = $wpdb->get_var( $query );

		return array(
			'total' => absint( $total ),
		);
	}

	/**
	 * Get recent invoices with optional filters.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_invoices( array $filters = array() ) {
		global $wpdb;

		$filters  = $this->normalize_filters( $filters );
		$params   = array();
		$where    = $this->build_invoice_where_clause( $filters, $params, 'i' );
		$params[] = $filters['limit'];
		$payment_totals_sql = $this->get_payment_totals_subquery( $filters['business_id'] );
		$sql      = "SELECT i.id, i.process_id, i.quote_id, i.client_id, i.invoice_number, i.status, i.currency,
				i.grand_total,
				COALESCE(payment_totals.total_paid, 0) AS amount_paid,
				GREATEST(i.grand_total - COALESCE(payment_totals.total_paid, 0), 0) AS balance_due,
				i.issued_at, i.due_date, i.paid_at, i.created_at, i.updated_at,
				p.title AS process_title,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name
			FROM {$this->get_invoices_table_name()} i
			LEFT JOIN ({$payment_totals_sql}) payment_totals ON payment_totals.invoice_id = i.id
			LEFT JOIN {$this->get_processes_table_name()} p ON p.id = i.process_id
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = i.client_id
			{$where}
			ORDER BY i.created_at DESC, i.id DESC
			LIMIT %d";
		$query    = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get recent payments with optional filters.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_payments( array $filters = array() ) {
		global $wpdb;

		$filters  = $this->normalize_filters( $filters );
		$params   = array();
		$where    = $this->build_payment_where_clause( $filters, $params, 'pay', 'i' );
		$params[] = $filters['limit'];
		$sql      = "SELECT pay.id, pay.invoice_id, pay.payment_date, pay.amount, pay.payment_method, pay.reference, pay.created_at,
				i.invoice_number, i.status AS invoice_status, i.currency, i.client_id,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name
			FROM {$this->get_payments_table_name()} pay
			INNER JOIN {$this->get_invoices_table_name()} i ON i.id = pay.invoice_id
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = i.client_id
			{$where}
			ORDER BY pay.payment_date DESC, pay.id DESC
			LIMIT %d";
		$query    = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get invoice collection status counts grouped as pending, partial or paid.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_invoice_collection_counts( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_invoice_where_clause( $filters, $params, 'i' );
		$payment_totals_sql = $this->get_payment_totals_subquery( $filters['business_id'] );
		$sql     = "SELECT collection_status AS label, COUNT(*) AS total
			FROM (
				SELECT CASE
					WHEN COALESCE(payment_totals.total_paid, 0) >= i.grand_total AND i.grand_total > 0 THEN 'paid'
					WHEN COALESCE(payment_totals.total_paid, 0) > 0 AND COALESCE(payment_totals.total_paid, 0) < i.grand_total THEN 'partial'
					ELSE 'pending'
				END AS collection_status
				FROM {$this->get_invoices_table_name()} i
				LEFT JOIN ({$payment_totals_sql}) payment_totals ON payment_totals.invoice_id = i.id
				{$where}
			) invoice_collection
			GROUP BY collection_status
			ORDER BY total DESC, collection_status ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get basic income totals grouped by payment date.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_income_by_period( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_payment_where_clause( $filters, $params, 'pay', 'i' );
		$sql     = "SELECT DATE(pay.payment_date) AS period_label, i.currency, COALESCE(SUM(pay.amount), 0) AS total
			FROM {$this->get_payments_table_name()} pay
			INNER JOIN {$this->get_invoices_table_name()} i ON i.id = pay.invoice_id
			{$where}
			GROUP BY DATE(pay.payment_date), i.currency
			ORDER BY DATE(pay.payment_date) DESC, i.currency ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get total invoiced amount for the selected range.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return float
	 */
	public function get_total_invoiced_amount( array $filters = array() ) {
		return $this->get_invoiced_amounts_by_currency( $filters );
	}

	/**
	 * Get aggregate payment totals grouped by currency for comparison blocks.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<string, float>
	 */
	public function get_payment_totals_comparison( array $filters = array() ) {
		return $this->get_paid_amounts_by_currency( $filters );
	}

	/**
	 * Get total paid amount for the selected range.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return float
	 */
	public function get_total_paid_amount( array $filters = array() ) {
		return $this->get_paid_amounts_by_currency( $filters );
	}

	/**
	 * Get total outstanding balance for the selected range.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return float
	 */
	public function get_total_outstanding_balance( array $filters = array() ) {
		return $this->get_outstanding_balances_by_currency( $filters );
	}

	/**
	 * Get invoiced amounts grouped by currency for the selected range.
	 *
	 * Uses invoice `created_at` as the operational "facturado" date in 12B.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<string, float>
	 */
	public function get_invoiced_amounts_by_currency( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_invoice_where_clause( $filters, $params, 'i' );
		$where   = $this->append_valid_billed_invoice_filter_to_where( $where, 'i', $params );
		$sql     = "SELECT i.currency, COALESCE(SUM(i.grand_total), 0) AS total
			FROM {$this->get_invoices_table_name()} i
			{$where}
			GROUP BY i.currency
			ORDER BY i.currency ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return $this->extract_currency_totals( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Get invoice component totals grouped by currency.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<string, array<string, float>>
	 */
	public function get_invoice_amount_components_by_currency( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_invoice_where_clause( $filters, $params, 'i' );
		$sql     = "SELECT i.currency,
				COALESCE(SUM(i.subtotal), 0) AS subtotal_total,
				COALESCE(SUM(i.tax_total), 0) AS tax_total,
				COALESCE(SUM(i.discount_total), 0) AS discount_total,
				COALESCE(SUM(i.grand_total), 0) AS grand_total
			FROM {$this->get_invoices_table_name()} i
			{$where}
			GROUP BY i.currency
			ORDER BY i.currency ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );
		$rows    = is_array( $rows ) ? $rows : array();
		$totals  = array();

		foreach ( $rows as $row ) {
			$currency             = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD';
			$totals[ $currency ] = array(
				'subtotal'      => round( (float) $row['subtotal_total'], 2 ),
				'tax_total'     => round( (float) $row['tax_total'], 2 ),
				'discount_total' => round( (float) $row['discount_total'], 2 ),
				'grand_total'   => round( (float) $row['grand_total'], 2 ),
			);
		}

		return $totals;
	}

	/**
	 * Get paid amounts grouped by currency for the selected range.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<string, float>
	 */
	public function get_paid_amounts_by_currency( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_payment_where_clause( $filters, $params, 'pay', 'i' );
		$sql     = "SELECT i.currency, COALESCE(SUM(pay.amount), 0) AS total
			FROM {$this->get_payments_table_name()} pay
			INNER JOIN {$this->get_invoices_table_name()} i ON i.id = pay.invoice_id
			{$where}
			GROUP BY i.currency
			ORDER BY i.currency ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return $this->extract_currency_totals( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Get outstanding balances grouped by currency for the selected range.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<string, float>
	 */
	public function get_outstanding_balances_by_currency( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_invoice_where_clause( $filters, $params, 'i' );
		$where   = $this->append_valid_billed_invoice_filter_to_where( $where, 'i', $params );
		$payment_totals_sql = $this->get_payment_totals_subquery( $filters['business_id'] );
		$sql     = "SELECT i.currency, COALESCE(SUM(GREATEST(i.grand_total - COALESCE(payment_totals.total_paid, 0), 0)), 0) AS total
			FROM {$this->get_invoices_table_name()} i
			LEFT JOIN ({$payment_totals_sql}) payment_totals ON payment_totals.invoice_id = i.id
			{$where}
			GROUP BY i.currency
			ORDER BY i.currency ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return $this->extract_currency_totals( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Get invoice KPI metrics by currency.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<string, array<string, float|int>>
	 */
	public function get_invoice_kpis_by_currency( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_invoice_where_clause( $filters, $params, 'i' );
		$where   = $this->append_valid_billed_invoice_filter_to_where( $where, 'i', $params );
		$sql     = "SELECT i.currency,
				COUNT(i.id) AS invoice_count,
				COALESCE(AVG(i.grand_total), 0) AS average_ticket
			FROM {$this->get_invoices_table_name()} i
			{$where}
			GROUP BY i.currency
			ORDER BY i.currency ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );
		$rows    = is_array( $rows ) ? $rows : array();
		$result  = array();

		foreach ( $rows as $row ) {
			$currency             = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD';
			$result[ $currency ] = array(
				'invoice_count'   => absint( $row['invoice_count'] ),
				'average_ticket'  => round( (float) $row['average_ticket'], 2 ),
			);
		}

		return $result;
	}

	/**
	 * Get process open vs closed totals.
	 *
	 * @param array<string, mixed> $filters         Report filters.
	 * @param array<int, string>   $closed_statuses Closed status keys.
	 * @return array<string, int>
	 */
	public function get_open_closed_process_totals( array $filters = array(), array $closed_statuses = array() ) {
		global $wpdb;

		$filters          = $this->normalize_filters( $filters );
		$closed_statuses  = array_filter( array_map( 'sanitize_key', $closed_statuses ) );
		$where_params     = array();
		$where            = $this->build_process_where_clause( $filters, $where_params, 'p' );
		$closed_case_sql  = '0';
		$closed_params    = array();

		if ( ! empty( $closed_statuses ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $closed_statuses ), '%s' ) );
			$closed_case_sql = "CASE WHEN p.status IN ({$placeholders}) THEN 1 ELSE 0 END";
			$closed_params = array_values( $closed_statuses );
		}

		$sql   = "SELECT COUNT(p.id) AS total_processes,
				COALESCE(SUM({$closed_case_sql}), 0) AS closed_processes
			FROM {$this->get_processes_table_name()} p
			{$where}";
		$params = array_merge( $closed_params, $where_params );
		$query = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$row   = $wpdb->get_row( $query, ARRAY_A );
		$row   = is_array( $row ) ? $row : array();
		$total = isset( $row['total_processes'] ) ? absint( $row['total_processes'] ) : 0;
		$closed = isset( $row['closed_processes'] ) ? absint( $row['closed_processes'] ) : 0;

		return array(
			'total'  => $total,
			'closed' => min( $closed, $total ),
			'open'   => max( $total - $closed, 0 ),
		);
	}

	/**
	 * Get client totals for processes, billed amount, and paid amount.
	 * Metrics are aggregated in isolated subqueries to avoid double counting.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_client_summary_rows( array $filters = array() ) {
		global $wpdb;

		$filters       = $this->normalize_filters( $filters );
		$process_rows  = $this->get_process_counts_by_client( $filters );
		$inv_params    = array();
		$inv_where     = $this->build_invoice_where_clause( $filters, $inv_params, 'i' );
		$inv_where     = $this->append_valid_billed_invoice_filter_to_where( $inv_where, 'i', $inv_params );
		$inv_sql       = "SELECT i.client_id, i.currency, COALESCE(SUM(i.grand_total), 0) AS amount_total
			FROM {$this->get_invoices_table_name()} i
			{$inv_where}
			GROUP BY i.client_id, i.currency";
		$inv_query     = empty( $inv_params ) ? $inv_sql : $wpdb->prepare( $inv_sql, $inv_params );
		$invoiced_rows = $wpdb->get_results( $inv_query, ARRAY_A );
		$invoiced_rows = is_array( $invoiced_rows ) ? $invoiced_rows : array();

		$pay_params = array();
		$pay_where  = $this->build_payment_where_clause( $filters, $pay_params, 'pay', 'i' );
		$pay_where  = $this->append_valid_billed_invoice_filter_to_where( $pay_where, 'i', $pay_params );
		$pay_sql    = "SELECT i.client_id, i.currency, COALESCE(SUM(pay.amount), 0) AS amount_total
			FROM {$this->get_payments_table_name()} pay
			INNER JOIN {$this->get_invoices_table_name()} i ON i.id = pay.invoice_id
			{$pay_where}
			GROUP BY i.client_id, i.currency";
		$pay_query  = empty( $pay_params ) ? $pay_sql : $wpdb->prepare( $pay_sql, $pay_params );
		$paid_rows  = $wpdb->get_results( $pay_query, ARRAY_A );
		$paid_rows  = is_array( $paid_rows ) ? $paid_rows : array();
		$clients_sql   = "SELECT c.id, CONCAT_WS(' ', c.first_name, c.last_name) AS client_name
			FROM {$this->get_clients_table_name()} c
			WHERE c.business_id = %d";
		$client_params = array( absint( $filters['business_id'] ) );

		if ( ! empty( $filters['client_id'] ) ) {
			$clients_sql    .= ' AND c.id = %d';
			$client_params[] = absint( $filters['client_id'] );
		}

		$clients_sql .= ' ORDER BY c.first_name ASC, c.last_name ASC, c.id ASC';
		$client_rows = $wpdb->get_results( $wpdb->prepare( $clients_sql, $client_params ), ARRAY_A );
		$client_rows = is_array( $client_rows ) ? $client_rows : array();
		$labels      = array();
		$index       = array();
		$process_map = array();

		foreach ( $client_rows as $row ) {
			$client_id = absint( $row['id'] );
			if ( $client_id <= 0 ) {
				continue;
			}
			$label               = trim( (string) $row['client_name'] );
			$labels[ $client_id ] = '' !== $label ? $label . ' (#' . $client_id . ')' : '#' . $client_id;
		}

		foreach ( $process_rows as $row ) {
			$client_id = isset( $row['client_id'] ) ? absint( $row['client_id'] ) : 0;
			if ( $client_id <= 0 ) {
				continue;
			}
			$process_map[ $client_id ] = absint( $row['total'] );
			if ( ! isset( $labels[ $client_id ] ) ) {
				$labels[ $client_id ] = isset( $row['label'] ) ? (string) $row['label'] : '#' . $client_id;
			}
		}

		foreach ( $invoiced_rows as $row ) {
			$client_id = isset( $row['client_id'] ) ? absint( $row['client_id'] ) : 0;
			if ( $client_id <= 0 ) {
				continue;
			}
			$currency = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD';
			$key      = $client_id . '|' . $currency;
			if ( ! isset( $index[ $key ] ) ) {
				$index[ $key ] = array(
					'client_id'       => $client_id,
					'label'           => isset( $labels[ $client_id ] ) ? $labels[ $client_id ] : '#' . $client_id,
					'total_processes' => isset( $process_map[ $client_id ] ) ? $process_map[ $client_id ] : 0,
					'currency'        => $currency,
					'total_billed'    => 0.0,
					'total_paid'      => 0.0,
				);
			}
			$index[ $key ]['total_billed'] = round( (float) $row['amount_total'], 2 );
		}

		foreach ( $paid_rows as $row ) {
			$client_id = isset( $row['client_id'] ) ? absint( $row['client_id'] ) : 0;
			if ( $client_id <= 0 ) {
				continue;
			}
			$currency = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD';
			$key      = $client_id . '|' . $currency;
			if ( ! isset( $index[ $key ] ) ) {
				$index[ $key ] = array(
					'client_id'       => $client_id,
					'label'           => isset( $labels[ $client_id ] ) ? $labels[ $client_id ] : '#' . $client_id,
					'total_processes' => isset( $process_map[ $client_id ] ) ? $process_map[ $client_id ] : 0,
					'currency'        => $currency,
					'total_billed'    => 0.0,
					'total_paid'      => 0.0,
				);
			}
			$index[ $key ]['total_paid'] = round( (float) $row['amount_total'], 2 );
		}

		if ( empty( $index ) ) {
			foreach ( $process_map as $client_id => $total_processes ) {
				$key = $client_id . '|USD';
				$index[ $key ] = array(
					'client_id'       => $client_id,
					'label'           => isset( $labels[ $client_id ] ) ? $labels[ $client_id ] : '#' . $client_id,
					'total_processes' => absint( $total_processes ),
					'currency'        => 'USD',
					'total_billed'    => 0.0,
					'total_paid'      => 0.0,
				);
			}
		}

		$rows = array_values( $index );
		usort(
			$rows,
			function ( $left, $right ) {
				if ( $left['total_billed'] === $right['total_billed'] ) {
					return strcmp( (string) $left['label'], (string) $right['label'] );
				}
				return $right['total_billed'] <=> $left['total_billed'];
			}
		);

		return $rows;
	}

	/**
	 * Get vehicle totals for process count and accumulated billed cost.
	 * Uses invoice-process join to keep aggregation controlled per base entity.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_vehicle_summary_rows( array $filters = array() ) {
		global $wpdb;

		$filters       = $this->normalize_filters( $filters );
		$process_rows  = $this->get_process_counts_by_vehicle( $filters );
		$params        = array();
		$where         = $this->build_invoice_where_clause( $filters, $params, 'i' );
		$where         = $this->append_valid_billed_invoice_filter_to_where( $where, 'i', $params );
		$sql           = "SELECT COALESCE(NULLIF(p.vehicle_id, 0), 0) AS vehicle_id,
				v.make AS vehicle_make,
				v.model AS vehicle_model,
				v.plate AS vehicle_plate,
				i.currency,
				COALESCE(SUM(i.grand_total), 0) AS total_billed
			FROM {$this->get_invoices_table_name()} i
			INNER JOIN {$this->get_processes_table_name()} p ON p.id = i.process_id
			LEFT JOIN {$this->get_vehicles_table_name()} v ON v.id = p.vehicle_id
			{$where}
			GROUP BY COALESCE(NULLIF(p.vehicle_id, 0), 0), v.make, v.model, v.plate, i.currency
			ORDER BY total_billed DESC, vehicle_id ASC";
		$query         = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$billed_rows   = $wpdb->get_results( $query, ARRAY_A );
		$billed_rows   = is_array( $billed_rows ) ? $billed_rows : array();
		$index         = array();
		$process_map   = array();
		$label_map     = array();

		foreach ( $process_rows as $row ) {
			$vehicle_id = isset( $row['vehicle_id'] ) ? absint( $row['vehicle_id'] ) : 0;
			if ( $vehicle_id <= 0 ) {
				continue;
			}
			$process_map[ $vehicle_id ] = absint( $row['total'] );
			$label_map[ $vehicle_id ]   = isset( $row['label'] ) ? (string) $row['label'] : '#' . $vehicle_id;
		}

		foreach ( $billed_rows as $row ) {
			$vehicle_id = isset( $row['vehicle_id'] ) ? absint( $row['vehicle_id'] ) : 0;
			if ( $vehicle_id <= 0 ) {
				continue;
			}
			$currency = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD';
			$key      = $vehicle_id . '|' . $currency;
			if ( ! isset( $index[ $key ] ) ) {
				$vehicle_label = $this->build_vehicle_group_label( $row );
				$index[ $key ] = array(
					'vehicle_id'      => $vehicle_id,
					'label'           => isset( $label_map[ $vehicle_id ] ) ? $label_map[ $vehicle_id ] : ( '' !== $vehicle_label ? $vehicle_label . ' (#' . $vehicle_id . ')' : '#' . $vehicle_id ),
					'total_processes' => isset( $process_map[ $vehicle_id ] ) ? $process_map[ $vehicle_id ] : 0,
					'currency'        => $currency,
					'accumulated_cost' => 0.0,
				);
			}
			$index[ $key ]['accumulated_cost'] = round( (float) $row['total_billed'], 2 );
		}

		if ( empty( $index ) ) {
			foreach ( $process_map as $vehicle_id => $process_total ) {
				$key = $vehicle_id . '|USD';
				$index[ $key ] = array(
					'vehicle_id'      => $vehicle_id,
					'label'           => isset( $label_map[ $vehicle_id ] ) ? $label_map[ $vehicle_id ] : '#' . $vehicle_id,
					'total_processes' => absint( $process_total ),
					'currency'        => 'USD',
					'accumulated_cost' => 0.0,
				);
			}
		}

		$rows = array_values( $index );
		usort(
			$rows,
			function ( $left, $right ) {
				if ( $left['accumulated_cost'] === $right['accumulated_cost'] ) {
					return strcmp( (string) $left['label'], (string) $right['label'] );
				}
				return $right['accumulated_cost'] <=> $left['accumulated_cost'];
			}
		);

		return $rows;
	}

	/**
	 * Normalize repository filters.
	 *
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array<string, mixed>
	 */
	protected function normalize_filters( array $filters ) {
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
				'limit'          => self::DEFAULT_RECENT_LIMIT,
			)
		);

		$filters['limit']       = min( self::MAX_RECENT_LIMIT, max( 1, absint( $filters['limit'] ) ) );
		$filters['mechanic_id'] = absint( $filters['mechanic_id'] );
		$filters['client_id']   = absint( $filters['client_id'] );
		$filters['vehicle_id']  = absint( $filters['vehicle_id'] );
		$filters['business_id'] = absint( $filters['business_id'] );

		if ( $filters['business_id'] <= 0 ) {
			$filters['business_id'] = max( 1, absint( $this->business_context_service->resolve_business_id() ) );
		}

		return $filters;
	}

	/**
	 * Build process WHERE clause.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param array<int, mixed>    $params  Query params.
	 * @param string               $alias   Table alias.
	 * @return string
	 */
	protected function build_process_where_clause( array $filters, array &$params, $alias = 'p' ) {
		$clauses  = $this->build_process_filter_fragments( $filters, $params, $alias );
		$date_sql = $this->build_date_range_where( $alias . '.created_at', $filters, $params );

		if ( '' !== $date_sql ) {
			$clauses[] = $date_sql;
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Build process filter fragments.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param array<int, mixed>    $params  Query params.
	 * @param string               $alias   Table alias.
	 * @return array<int, string>
	 */
	protected function build_process_filter_fragments( array $filters, array &$params, $alias = 'p' ) {
		$clauses = array();
		$clauses[] = $alias . '.business_id = %d';
		$params[]  = absint( $filters['business_id'] );

		if ( '' !== $filters['process_status'] ) {
			$clauses[] = $alias . '.status = %s';
			$params[]  = $filters['process_status'];
		}

		if ( '' !== $filters['process_type'] ) {
			$clauses[] = $alias . '.process_type = %s';
			$params[]  = $filters['process_type'];
		}

		if ( ! empty( $filters['mechanic_id'] ) ) {
			$clauses[] = $alias . '.assigned_to = %d';
			$params[]  = absint( $filters['mechanic_id'] );
		}

		if ( ! empty( $filters['client_id'] ) ) {
			$clauses[] = $alias . '.client_id = %d';
			$params[]  = absint( $filters['client_id'] );
		}

		if ( ! empty( $filters['vehicle_id'] ) ) {
			$clauses[] = $alias . '.vehicle_id = %d';
			$params[]  = absint( $filters['vehicle_id'] );
		}

		return $clauses;
	}

	/**
	 * Build a compact vehicle label for grouped rows.
	 *
	 * @param array<string, mixed> $row Group row.
	 * @return string
	 */
	protected function build_vehicle_group_label( array $row ) {
		$make  = ! empty( $row['vehicle_make'] ) ? (string) $row['vehicle_make'] : '';
		$model = ! empty( $row['vehicle_model'] ) ? (string) $row['vehicle_model'] : '';
		$plate = ! empty( $row['vehicle_plate'] ) ? (string) $row['vehicle_plate'] : '';
		$label = trim( $make . ' ' . $model );

		if ( '' !== $plate ) {
			$label = '' !== $label ? $label . ' - ' . $plate : $plate;
		}

		return $label;
	}

	/**
	 * Build a date range WHERE fragment for a datetime column.
	 *
	 * @param string               $column  Column name.
	 * @param array<string, mixed> $filters Filters.
	 * @param array<int, mixed>    $params  Query params.
	 * @return string
	 */
	protected function build_date_range_where( $column, array $filters, array &$params ) {
		$clauses = array();

		if ( '' !== $filters['date_from'] ) {
			$clauses[] = $column . ' >= %s';
			$params[]  = $filters['date_from'] . ' 00:00:00';
		}

		if ( '' !== $filters['date_to'] ) {
			$clauses[] = $column . ' <= %s';
			$params[]  = $filters['date_to'] . ' 23:59:59';
		}

		return implode( ' AND ', $clauses );
	}

	/**
	 * Build quote WHERE clause.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param array<int, mixed>    $params  Query params.
	 * @param string               $alias   Table alias.
	 * @return string
	 */
	protected function build_quote_where_clause( array $filters, array &$params, $alias = 'q' ) {
		$clauses = array();
		$clauses[] = $alias . '.business_id = %d';
		$params[]  = absint( $filters['business_id'] );

		if ( '' !== $filters['quote_status'] ) {
			$clauses[] = $alias . '.status = %s';
			$params[]  = $filters['quote_status'];
		}

		$date_sql = $this->build_date_range_where( $alias . '.created_at', $filters, $params );

		if ( '' !== $date_sql ) {
			$clauses[] = $date_sql;
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Build invoice WHERE clause.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param array<int, mixed>    $params  Query params.
	 * @param string               $alias   Table alias.
	 * @return string
	 */
	protected function build_invoice_where_clause( array $filters, array &$params, $alias = 'i' ) {
		$clauses = array();
		$clauses[] = $alias . '.business_id = %d';
		$params[]  = absint( $filters['business_id'] );

		if ( '' !== $filters['invoice_status'] ) {
			$clauses[] = $alias . '.status = %s';
			$params[]  = $filters['invoice_status'];
		}

		if ( '' !== $filters['currency'] ) {
			$clauses[] = $alias . '.currency = %s';
			$params[]  = strtoupper( (string) $filters['currency'] );
		}

		$date_sql = $this->build_date_range_where( $alias . '.created_at', $filters, $params );

		if ( '' !== $date_sql ) {
			$clauses[] = $date_sql;
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Append valid billed invoice statuses to an existing invoice WHERE clause.
	 *
	 * @param string            $where  Existing WHERE clause.
	 * @param string            $alias  Invoice table alias.
	 * @param array<int, mixed> $params Query params.
	 * @return string
	 */
	protected function append_valid_billed_invoice_filter_to_where( $where, $alias, array &$params ) {
		$statuses = self::VALID_BILLED_INVOICE_STATUSES;
		if ( empty( $statuses ) ) {
			return $where;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$clause       = $alias . '.status IN (' . $placeholders . ')';
		$params       = array_merge( $params, $statuses );

		if ( '' === trim( (string) $where ) ) {
			return 'WHERE ' . $clause;
		}

		return $where . ' AND ' . $clause;
	}

	/**
	 * Build payment WHERE clause.
	 *
	 * @param array<string, mixed> $filters        Filters.
	 * @param array<int, mixed>    $params         Query params.
	 * @param string               $payment_alias  Payment alias.
	 * @param string               $invoice_alias  Invoice alias.
	 * @return string
	 */
	protected function build_payment_where_clause( array $filters, array &$params, $payment_alias = 'pay', $invoice_alias = 'i' ) {
		$clauses = array();
		$clauses[] = $payment_alias . '.business_id = %d';
		$params[]  = absint( $filters['business_id'] );
		$clauses[] = $invoice_alias . '.business_id = %d';
		$params[]  = absint( $filters['business_id'] );

		if ( '' !== $filters['invoice_status'] ) {
			$clauses[] = $invoice_alias . '.status = %s';
			$params[]  = $filters['invoice_status'];
		}

		if ( '' !== $filters['currency'] ) {
			$clauses[] = $invoice_alias . '.currency = %s';
			$params[]  = strtoupper( (string) $filters['currency'] );
		}

		if ( '' !== $filters['payment_method'] ) {
			$clauses[] = $payment_alias . '.payment_method = %s';
			$params[]  = $filters['payment_method'];
		}

		$date_sql = $this->build_date_range_where( $payment_alias . '.payment_date', $filters, $params );

		if ( '' !== $date_sql ) {
			$clauses[] = $date_sql;
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Normalize grouped totals keyed by currency.
	 *
	 * @param array<int, array<string, mixed>> $rows Raw rows.
	 * @return array<string, float>
	 */
	protected function extract_currency_totals( array $rows ) {
		$totals = array();

		foreach ( $rows as $row ) {
			$currency = ! empty( $row['currency'] ) ? strtoupper( (string) $row['currency'] ) : 'USD';
			$total    = isset( $row['total'] ) ? (float) $row['total'] : 0.0;
			$totals[ $currency ] = round( $total, 2 );
		}

		return $totals;
	}

	/**
	 * Build a reusable payments aggregation subquery by invoice.
	 *
	 * @return string
	 */
	protected function get_payment_totals_subquery( $business_id ) {
		$business_id = max( 1, absint( $business_id ) );

		return "SELECT pay.invoice_id, COALESCE(SUM(pay.amount), 0) AS total_paid
                        FROM {$this->get_payments_table_name()} pay
                        WHERE pay.business_id = {$business_id}
                        GROUP BY pay.invoice_id";
	}

	/**
	 * Get advanced process metrics grouped by derived status.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_counts_by_derived_status( array $filters = array() ) {
		global $wpdb;

		$filters           = $this->normalize_filters( $filters );
		$business_id       = absint( $filters['business_id'] );
		$params            = array();
		$process_clauses   = $this->build_process_filter_fragments( $filters, $params, 'p' );
		$date_sql          = $this->build_date_range_where( 'p.created_at', $filters, $params );
		$payment_totals_sql = $this->get_payment_totals_subquery( $filters['business_id'] );

		if ( '' !== $date_sql ) {
			$process_clauses[] = $date_sql;
		}

		$derived_clause = $this->build_derived_status_filter_clause( $filters, 'derived_status', $params );

		if ( '' !== $derived_clause ) {
			$process_clauses[] = $derived_clause;
		}

		$where = empty( $process_clauses ) ? '' : 'WHERE ' . implode( ' AND ', $process_clauses );
		$sql   = "SELECT derived_status AS label, COUNT(process_id) AS total
			FROM (
				SELECT p.id AS process_id,
					CASE
						WHEN pre.delivery_ready = 1 THEN 'ready_for_delivery'
						WHEN invoice_metrics.invoice_pending_count > 0 THEN 'waiting_payment'
						WHEN quote_metrics.quote_waiting_approval_count > 0 THEN 'waiting_approval'
						WHEN p.status = 'completed' THEN 'completed'
						ELSE p.status
					END AS derived_status
				FROM {$this->get_processes_table_name()} p
				LEFT JOIN {$this->get_pre_delivery_table_name()} pre ON pre.process_id = p.id
								LEFT JOIN (
										SELECT q.process_id,
                                                SUM(CASE WHEN q.status = 'sent' THEN 1 ELSE 0 END) AS quote_waiting_approval_count
                                        FROM {$this->get_quotes_table_name()} q
										WHERE q.business_id = {$business_id}
                                        GROUP BY q.process_id
                                ) quote_metrics ON quote_metrics.process_id = p.id
                                LEFT JOIN (
                                        SELECT i.process_id,
						SUM(
							CASE
								WHEN i.status IN ('cancelled', 'refunded') THEN 0
								WHEN COALESCE(payment_totals.total_paid, 0) >= i.grand_total AND i.grand_total > 0 THEN 0
								ELSE 1
							END
                                                ) AS invoice_pending_count
                                        FROM {$this->get_invoices_table_name()} i
                                        LEFT JOIN ({$payment_totals_sql}) payment_totals ON payment_totals.invoice_id = i.id
										WHERE i.business_id = {$business_id}
                                        GROUP BY i.process_id
                                ) invoice_metrics ON invoice_metrics.process_id = p.id
                                {$where}
                        ) process_derived
			GROUP BY derived_status
			ORDER BY total DESC, derived_status ASC";
		$query = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get process matrix by type and status.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_type_status_matrix( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_process_where_clause( $filters, $params, 'p' );
		$sql     = "SELECT p.process_type, p.status, COUNT(p.id) AS total
			FROM {$this->get_processes_table_name()} p
			{$where}
			GROUP BY p.process_type, p.status
			ORDER BY p.process_type ASC, total DESC, p.status ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count finalized processes in range.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return int
	 */
	public function get_completed_process_count( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$clauses = $this->build_process_filter_fragments( $filters, $params, 'p' );
		$clauses[] = "p.status = 'completed'";
		$date_sql  = $this->build_date_range_where( 'p.completed_at', $filters, $params );

		if ( '' !== $date_sql ) {
			$clauses[] = $date_sql;
		}

		$where = 'WHERE ' . implode( ' AND ', $clauses );
		$sql   = "SELECT COUNT(p.id) AS total
			FROM {$this->get_processes_table_name()} p
			{$where}";
		$query = $wpdb->prepare( $sql, $params );

		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Count ready-for-delivery processes in range.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return int
	 */
	public function get_ready_for_delivery_count( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$clauses = $this->build_process_filter_fragments( $filters, $params, 'p' );
		$clauses[] = 'pre.delivery_ready = 1';
		$date_sql  = $this->build_date_range_where( 'pre.delivery_ready_at', $filters, $params );

		if ( '' !== $date_sql ) {
			$clauses[] = $date_sql;
		}

		$where = 'WHERE ' . implode( ' AND ', $clauses );
		$sql   = "SELECT COUNT(p.id) AS total
			FROM {$this->get_processes_table_name()} p
			INNER JOIN {$this->get_pre_delivery_table_name()} pre ON pre.process_id = p.id
			{$where}";
		$query = $wpdb->prepare( $sql, $params );

		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Get average process step transition times in hours.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_process_flow_time_summary( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$business_id = absint( $filters['business_id'] );
		$params  = array();
		$clauses = $this->build_process_filter_fragments( $filters, $params, 'p' );
		$clauses[] = "l.action_type = 'step_transition'";
		$date_sql  = $this->build_date_range_where( 'l.created_at', $filters, $params );

		if ( '' !== $date_sql ) {
			$clauses[] = $date_sql;
		}

		$where = 'WHERE ' . implode( ' AND ', $clauses );
		$sql   = "SELECT p.process_type AS label,
				COUNT(DISTINCT p.id) AS process_count,
				ROUND(AVG(step_events.transition_count), 2) AS avg_step_transitions,
				ROUND(AVG(step_events.elapsed_hours), 2) AS avg_elapsed_hours
			FROM {$this->get_processes_table_name()} p
			INNER JOIN (
                                SELECT l.process_id,
                                        COUNT(l.id) AS transition_count,
                                        (TIMESTAMPDIFF(SECOND, MIN(l.created_at), MAX(l.created_at)) / 3600) AS elapsed_hours
                                FROM {$this->get_process_step_logs_table_name()} l
                                WHERE l.action_type = 'step_transition' AND l.business_id = {$business_id}
                                GROUP BY l.process_id
                        ) step_events ON step_events.process_id = p.id
			INNER JOIN {$this->get_process_step_logs_table_name()} l ON l.process_id = p.id
			{$where}
			GROUP BY p.process_type
			ORDER BY process_count DESC, p.process_type ASC";
		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get recent aggregated activity across logs, quotes, invoices and payments.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_activity_summary( array $filters = array() ) {
		global $wpdb;

		$filters  = $this->normalize_filters( $filters );
		$business_id = absint( $filters['business_id'] );
		$params   = array( $business_id, $business_id, $business_id, $business_id );
		$date_sql = $this->build_date_range_where( 'activity_created_at', $filters, $params );
		$where    = '' !== $date_sql ? 'WHERE ' . $date_sql : '';
		$params[] = $filters['limit'];
		$sql      = "SELECT activity_type AS label, COUNT(*) AS total, MAX(activity_created_at) AS latest_created_at
                        FROM (
                                SELECT l.action_type AS activity_type, l.created_at AS activity_created_at
                                FROM {$this->get_process_step_logs_table_name()} l
                                WHERE l.customer_visible = 1 AND l.business_id = %d
                                UNION ALL
                                SELECT CONCAT('quote_', q.status) AS activity_type, q.created_at AS activity_created_at
                                FROM {$this->get_quotes_table_name()} q
                                WHERE q.business_id = %d
                                UNION ALL
                                SELECT CONCAT('invoice_', i.status) AS activity_type, i.created_at AS activity_created_at
                                FROM {$this->get_invoices_table_name()} i
                                WHERE i.business_id = %d
                                UNION ALL
                                SELECT CONCAT('payment_', pay.payment_method) AS activity_type, pay.payment_date AS activity_created_at
                                FROM {$this->get_payments_table_name()} pay
                                WHERE pay.business_id = %d
                        ) activity_feed
                        {$where}
                        GROUP BY activity_type
			ORDER BY latest_created_at DESC, total DESC
			LIMIT %d";
		$query    = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get advanced invoice aging summary.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_invoice_aging_summary( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_invoice_where_clause( $filters, $params, 'i' );
		$payment_totals_sql = $this->get_payment_totals_subquery( $filters['business_id'] );
		$sql     = "SELECT aging_label AS label, COUNT(*) AS total
			FROM (
				SELECT CASE
					WHEN i.status IN ('cancelled', 'refunded') THEN 'closed'
					WHEN COALESCE(payment_totals.total_paid, 0) >= i.grand_total AND i.grand_total > 0 THEN 'paid'
					WHEN COALESCE(payment_totals.total_paid, 0) > 0 AND COALESCE(payment_totals.total_paid, 0) < i.grand_total THEN 'partial'
					WHEN i.due_date IS NOT NULL AND i.due_date <> '' AND i.due_date < CURDATE() THEN 'overdue'
					ELSE 'pending'
				END AS aging_label
				FROM {$this->get_invoices_table_name()} i
				LEFT JOIN ({$payment_totals_sql}) payment_totals ON payment_totals.invoice_id = i.id
				{$where}
			) invoice_aging
			GROUP BY aging_label
			ORDER BY total DESC, aging_label ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get payment totals grouped by payment method and currency.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_payment_method_breakdown( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_payment_where_clause( $filters, $params, 'pay', 'i' );
		$sql     = "SELECT pay.payment_method AS label, i.currency, COUNT(pay.id) AS total, COALESCE(SUM(pay.amount), 0) AS amount_total
			FROM {$this->get_payments_table_name()} pay
			INNER JOIN {$this->get_invoices_table_name()} i ON i.id = pay.invoice_id
			{$where}
			GROUP BY pay.payment_method, i.currency
			ORDER BY amount_total DESC, pay.payment_method ASC, i.currency ASC";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get top clients by invoiced amount.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_top_clients_by_invoiced_amount( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_invoice_where_clause( $filters, $params, 'i' );
		$sql     = "SELECT i.client_id,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
				i.currency,
				COUNT(i.id) AS total,
				COALESCE(SUM(i.grand_total), 0) AS amount_total
			FROM {$this->get_invoices_table_name()} i
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = i.client_id
			{$where}
			GROUP BY i.client_id, client_name, i.currency
			ORDER BY amount_total DESC, total DESC
			LIMIT 10";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get top clients by paid amount.
	 *
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_top_clients_by_paid_amount( array $filters = array() ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$params  = array();
		$where   = $this->build_payment_where_clause( $filters, $params, 'pay', 'i' );
		$sql     = "SELECT i.client_id,
				CONCAT_WS(' ', c.first_name, c.last_name) AS client_name,
				i.currency,
				COUNT(pay.id) AS total,
				COALESCE(SUM(pay.amount), 0) AS amount_total
			FROM {$this->get_payments_table_name()} pay
			INNER JOIN {$this->get_invoices_table_name()} i ON i.id = pay.invoice_id
			LEFT JOIN {$this->get_clients_table_name()} c ON c.id = i.client_id
			{$where}
			GROUP BY i.client_id, client_name, i.currency
			ORDER BY amount_total DESC, total DESC
			LIMIT 10";
		$query   = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
		$rows    = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Build the derived status filter clause.
	 *
	 * @param array<string, mixed> $filters     Filters.
	 * @param string               $column_name Column alias.
	 * @param array<int, mixed>    $params      Query params.
	 * @return string
	 */
	protected function build_derived_status_filter_clause( array $filters, $column_name, array &$params ) {
		if ( empty( $filters['derived_status'] ) ) {
			return '';
		}

		$params[] = $filters['derived_status'];

		return $column_name . ' = %s';
	}

	/**
	 * Get processes table name.
	 *
	 * @return string
	 */
	protected function get_processes_table_name() {
		$tables = Schema::get_tables();

		return $tables['processes'];
	}

	/**
	 * Get maintenance table name.
	 *
	 * @return string
	 */
	protected function get_maintenance_table_name() {
		$tables = Schema::get_tables();

		return $tables['maintenance'];
	}

	/**
	 * Get clients table name.
	 *
	 * @return string
	 */
	protected function get_clients_table_name() {
		$tables = Schema::get_tables();

		return $tables['clients'];
	}

	/**
	 * Get vehicles table name.
	 *
	 * @return string
	 */
	protected function get_vehicles_table_name() {
		$tables = Schema::get_tables();

		return $tables['vehicles'];
	}

	/**
	 * Get quotes table name.
	 *
	 * @return string
	 */
	protected function get_quotes_table_name() {
		$tables = Schema::get_tables();

		return $tables['quotes'];
	}

	/**
	 * Get invoices table name.
	 *
	 * @return string
	 */
	protected function get_invoices_table_name() {
		$tables = Schema::get_tables();

		return $tables['invoices'];
	}

	/**
	 * Get payments table name.
	 *
	 * @return string
	 */
	protected function get_payments_table_name() {
		$tables = Schema::get_tables();

		return $tables['payments'];
	}

	/**
	 * Get process step logs table name.
	 *
	 * @return string
	 */
	protected function get_process_step_logs_table_name() {
		$tables = Schema::get_tables();

		return $tables['process_step_logs'];
	}

	/**
	 * Get pre-delivery table name.
	 *
	 * @return string
	 */
	protected function get_pre_delivery_table_name() {
		$tables = Schema::get_tables();

		return $tables['pre_delivery'];
	}
}
