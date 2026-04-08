<?php
/**
 * Reporting repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Reporting;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Provides aggregated reporting metrics.
 */
class Reporting_Repository {
	/**
	 * Supported range options.
	 *
	 * @var array<int, string>
	 */
	const SUPPORTED_RANGES = array( '7d', '30d', '90d', 'all' );

	/**
	 * Get aggregated metrics for one business and range.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $range       Range key.
	 * @return array<string, float|int>
	 */
	public function get_reporting_metrics( $business_id = 0, $range = '30d' ) {
		$business_id = absint( $business_id );
		$bounds      = $this->get_date_bounds_for_range( $range );

		return $this->get_reporting_metrics_for_period(
			$business_id,
			isset( $bounds['date_from'] ) ? (string) $bounds['date_from'] : '',
			isset( $bounds['date_to'] ) ? (string) $bounds['date_to'] : ''
		);
	}

	/**
	 * Get aggregated metrics for one business and explicit period.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $date_from   Start date in Y-m-d.
	 * @param string $date_to     End date in Y-m-d.
	 * @return array<string, float|int>
	 */
	public function get_reporting_metrics_for_period( $business_id = 0, $date_from = '', $date_to = '' ) {
		$business_id = absint( $business_id );
		$date_from   = $this->normalize_date( $date_from );
		$date_to     = $this->normalize_date( $date_to );

		$payment_metrics = $this->get_payment_metrics( $business_id, $date_from, $date_to );
		$process_metrics = $this->get_process_metrics( $business_id, $date_from, $date_to );
		$client_metrics  = $this->get_active_client_count( $business_id, $date_from, $date_to );
		$quote_metrics   = $this->get_quote_metrics( $business_id, $date_from, $date_to );
		$invoice_metrics = $this->get_invoice_count( $business_id, $date_from, $date_to );

		$total_revenue = isset( $payment_metrics['total_revenue'] ) ? (float) $payment_metrics['total_revenue'] : 0.0;
		$total_payments_count = isset( $payment_metrics['total_payments_count'] ) ? absint( $payment_metrics['total_payments_count'] ) : 0;
		$quotes_count         = isset( $quote_metrics['quotes_count'] ) ? absint( $quote_metrics['quotes_count'] ) : 0;
		$converted_quotes     = isset( $quote_metrics['converted_quotes_count'] ) ? absint( $quote_metrics['converted_quotes_count'] ) : 0;

		return array(
			'total_revenue'                       => round( $total_revenue, 2 ),
			'total_payments_count'                => $total_payments_count,
			'active_processes'                    => isset( $process_metrics['active_processes'] ) ? absint( $process_metrics['active_processes'] ) : 0,
			'completed_processes'                 => isset( $process_metrics['completed_processes'] ) ? absint( $process_metrics['completed_processes'] ) : 0,
			'active_clients'                      => $client_metrics,
			'average_ticket'                      => $total_payments_count > 0 ? round( $total_revenue / $total_payments_count, 2 ) : 0.0,
			'quotes_count'                        => $quotes_count,
			'invoices_count'                      => $invoice_metrics,
			'quote_to_invoice_conversion_rate'    => $quotes_count > 0 ? round( ( $converted_quotes / $quotes_count ) * 100, 2 ) : 0.0,
		);
	}

	/**
	 * Get payment aggregates.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $date_from   Start date in Y-m-d.
	 * @param string $date_to     End date in Y-m-d.
	 * @return array<string, mixed>
	 */
	protected function get_payment_metrics( $business_id, $date_from, $date_to ) {
		global $wpdb;

		$tables   = Schema::get_tables();
		$payments = isset( $tables['payments'] ) ? (string) $tables['payments'] : '';

		if ( '' === $payments ) {
			return array(
				'total_revenue'        => 0.0,
				'total_payments_count' => 0,
			);
		}

		$params   = array( $business_id );
		$date_sql = $this->build_date_where_clause( 'pay.payment_date', $date_from, $date_to, $params );

		$sql = "SELECT
				COUNT(pay.id) AS total_payments_count,
				COALESCE(SUM(pay.amount), 0) AS total_revenue
			FROM {$payments} pay
			WHERE pay.business_id = %d{$date_sql}";

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return is_array( $row ) ? $row : array();
	}

	/**
	 * Get process aggregates.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $date_from   Start date in Y-m-d.
	 * @param string $date_to     End date in Y-m-d.
	 * @return array<string, mixed>
	 */
	protected function get_process_metrics( $business_id, $date_from, $date_to ) {
		global $wpdb;

		$tables    = Schema::get_tables();
		$processes = isset( $tables['processes'] ) ? (string) $tables['processes'] : '';

		if ( '' === $processes ) {
			return array(
				'active_processes'    => 0,
				'completed_processes' => 0,
			);
		}

		$params   = array( $business_id );
		$date_sql = $this->build_date_where_clause( 'p.created_at', $date_from, $date_to, $params );

		$sql = "SELECT
				SUM(CASE WHEN p.status NOT IN ('completed', 'delivered', 'cancelled') THEN 1 ELSE 0 END) AS active_processes,
				SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed_processes
			FROM {$processes} p
			WHERE p.business_id = %d{$date_sql}";

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return is_array( $row ) ? $row : array();
	}

	/**
	 * Get active client count.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $date_from   Start date in Y-m-d.
	 * @param string $date_to     End date in Y-m-d.
	 * @return int
	 */
	protected function get_active_client_count( $business_id, $date_from, $date_to ) {
		global $wpdb;

		$tables  = Schema::get_tables();
		$clients = isset( $tables['clients'] ) ? (string) $tables['clients'] : '';

		if ( '' === $clients ) {
			return 0;
		}

		$params   = array( $business_id );
		$date_sql = $this->build_date_where_clause( 'c.created_at', $date_from, $date_to, $params );
		$params[] = 'active';

		$sql = "SELECT COUNT(c.id)
			FROM {$clients} c
			WHERE c.business_id = %d{$date_sql}
			AND c.status = %s";

		return absint( $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) );
	}

	/**
	 * Get quote aggregates and conversion base.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $date_from   Start date in Y-m-d.
	 * @param string $date_to     End date in Y-m-d.
	 * @return array<string, mixed>
	 */
	protected function get_quote_metrics( $business_id, $date_from, $date_to ) {
		global $wpdb;

		$tables   = Schema::get_tables();
		$quotes   = isset( $tables['quotes'] ) ? (string) $tables['quotes'] : '';
		$invoices = isset( $tables['invoices'] ) ? (string) $tables['invoices'] : '';

		if ( '' === $quotes || '' === $invoices ) {
			return array(
				'quotes_count'           => 0,
				'converted_quotes_count' => 0,
			);
		}

		$params   = array( $business_id );
		$date_sql = $this->build_date_where_clause( 'q.created_at', $date_from, $date_to, $params );

		$sql = "SELECT
				COUNT(q.id) AS quotes_count,
				COUNT(DISTINCT CASE WHEN i.id IS NOT NULL THEN q.id END) AS converted_quotes_count
			FROM {$quotes} q
			LEFT JOIN {$invoices} i
				ON i.quote_id = q.id
				AND i.business_id = q.business_id
			WHERE q.business_id = %d{$date_sql}";

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return is_array( $row ) ? $row : array();
	}

	/**
	 * Get invoice count.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $date_from   Start date in Y-m-d.
	 * @param string $date_to     End date in Y-m-d.
	 * @return int
	 */
	protected function get_invoice_count( $business_id, $date_from, $date_to ) {
		global $wpdb;

		$tables   = Schema::get_tables();
		$invoices = isset( $tables['invoices'] ) ? (string) $tables['invoices'] : '';

		if ( '' === $invoices ) {
			return 0;
		}

		$params   = array( $business_id );
		$date_sql = $this->build_date_where_clause( 'i.created_at', $date_from, $date_to, $params );

		$sql = "SELECT COUNT(i.id)
			FROM {$invoices} i
			WHERE i.business_id = %d{$date_sql}";

		return absint( $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) );
	}

	/**
	 * Build date WHERE fragment for one period.
	 *
	 * @param string       $column SQL column.
	 * @param string       $date_from Start date in Y-m-d.
	 * @param string       $date_to End date in Y-m-d.
	 * @param array<mixed> &$params Query params.
	 * @return string
	 */
	protected function build_date_where_clause( $column, $date_from, $date_to, array &$params ) {
		$date_from = $this->normalize_date( $date_from );
		$date_to   = $this->normalize_date( $date_to );
		$sql       = '';

		if ( '' !== $date_from ) {
			$params[] = $date_from . ' 00:00:00';
			$sql     .= " AND {$column} >= %s";
		}

		if ( '' !== $date_to ) {
			$params[] = $date_to . ' 23:59:59';
			$sql     .= " AND {$column} <= %s";
		}

		return $sql;
	}

	/**
	 * Resolve date bounds for one range key.
	 *
	 * @param string $range Range key.
	 * @return array<string, string>
	 */
	public function get_date_bounds_for_range( $range ) {
		$range = $this->normalize_range( $range );
		if ( 'all' === $range ) {
			return array(
				'date_from' => '',
				'date_to'   => '',
			);
		}

		$days = 30;
		if ( '7d' === $range ) {
			$days = 7;
		} elseif ( '90d' === $range ) {
			$days = 90;
		}

		return array(
			'date_from' => gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) ),
			'date_to'   => gmdate( 'Y-m-d' ),
		);
	}

	/**
	 * Normalize range.
	 *
	 * @param string $range Raw range.
	 * @return string
	 */
	protected function normalize_range( $range ) {
		$range = sanitize_key( (string) $range );
		return in_array( $range, self::SUPPORTED_RANGES, true ) ? $range : '30d';
	}

	/**
	 * Normalize date value to Y-m-d.
	 *
	 * @param string $date Raw date.
	 * @return string
	 */
	protected function normalize_date( $date ) {
		$date = sanitize_text_field( (string) $date );
		if ( '' === $date ) {
			return '';
		}

		$timestamp = strtotime( $date );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}
}
