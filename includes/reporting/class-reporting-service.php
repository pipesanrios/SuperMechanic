<?php
/**
 * Reporting service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Reporting;

use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates reporting metrics for admin UI.
 */
class Reporting_Service {
	/**
	 * Metrics that support current-vs-previous comparison.
	 *
	 * @var array<int, string>
	 */
	const COMPARABLE_METRICS = array(
		'total_revenue',
		'total_payments_count',
		'active_processes',
		'completed_processes',
		'active_clients',
		'quotes_count',
		'invoices_count',
	);

	/**
	 * Supported ranges.
	 *
	 * @var array<string, string>
	 */
	const SUPPORTED_RANGES = array(
		'7d'  => 'Last 7 days',
		'30d' => 'Last 30 days',
		'90d' => 'Last 90 days',
		'all' => 'All time',
	);

	/**
	 * Repository dependency.
	 *
	 * @var Reporting_Repository
	 */
	protected $repository;

	/**
	 * Business context dependency.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Reporting_Repository|null    $repository Repository dependency.
	 * @param Business_Context_Service|null $business_context_service Business context dependency.
	 */
	public function __construct( Reporting_Repository $repository = null, Business_Context_Service $business_context_service = null ) {
		$this->repository               = $repository ? $repository : new Reporting_Repository();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Get reporting summary payload.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $range       Range key.
	 * @return array<string, mixed>
	 */
	public function get_reporting_summary( $business_id = 0, $range = '30d' ) {
		$business_id = $this->resolve_business_id( $business_id );
		$range       = $this->normalize_range( $range );
		$metrics     = $this->repository->get_reporting_metrics( $business_id, $range );

		return array(
			'business_id' => $business_id,
			'range'       => $range,
			'range_label' => $this->get_range_label( $range ),
			'generated_at' => current_time( 'mysql' ),
			'metrics'     => $metrics,
		);
	}

	/**
	 * Get reporting metrics only.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $range       Range key.
	 * @return array<string, float|int>
	 */
	public function get_reporting_metrics( $business_id = 0, $range = '30d' ) {
		$summary = $this->get_reporting_summary( $business_id, $range );

		return isset( $summary['metrics'] ) && is_array( $summary['metrics'] ) ? $summary['metrics'] : array();
	}

	/**
	 * Get current-vs-previous period comparison payload.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $range       Range key.
	 * @return array<string, mixed>
	 */
	public function get_reporting_comparison( $business_id = 0, $range = '30d' ) {
		$business_id = $this->resolve_business_id( $business_id );
		$range       = $this->normalize_range( $range );
		$current     = $this->repository->get_reporting_metrics( $business_id, $range );
		$periods     = $this->resolve_comparison_periods( $range );

		$previous = array();
		if ( ! empty( $periods['comparison_available'] ) ) {
			$previous = $this->repository->get_reporting_metrics_for_period(
				$business_id,
				isset( $periods['previous']['date_from'] ) ? (string) $periods['previous']['date_from'] : '',
				isset( $periods['previous']['date_to'] ) ? (string) $periods['previous']['date_to'] : ''
			);
		}

		return array(
			'business_id'          => $business_id,
			'range'                => $range,
			'comparison_available' => ! empty( $periods['comparison_available'] ),
			'current_period'       => isset( $periods['current'] ) ? $periods['current'] : array(),
			'previous_period'      => isset( $periods['previous'] ) ? $periods['previous'] : array(),
			'metrics'              => $this->build_comparison_metrics( $current, $previous ),
		);
	}

	/**
	 * Get supported ranges for UI selector.
	 *
	 * @return array<string, string>
	 */
	public function get_supported_ranges() {
		return self::SUPPORTED_RANGES;
	}

	/**
	 * Normalize range key.
	 *
	 * @param string $range Raw range.
	 * @return string
	 */
	protected function normalize_range( $range ) {
		$range = sanitize_key( (string) $range );

		return isset( self::SUPPORTED_RANGES[ $range ] ) ? $range : '30d';
	}

	/**
	 * Resolve tenant-safe business ID.
	 *
	 * @param int $business_id Raw business ID.
	 * @return int
	 */
	protected function resolve_business_id( $business_id ) {
		$business_id = absint( $business_id );

		if ( $business_id > 0 ) {
			return absint( $this->business_context_service->normalize_business_id( $business_id ) );
		}

		return absint( $this->business_context_service->resolve_business_id() );
	}

	/**
	 * Get human-readable label for range.
	 *
	 * @param string $range Range key.
	 * @return string
	 */
	protected function get_range_label( $range ) {
		$range = $this->normalize_range( $range );

		return isset( self::SUPPORTED_RANGES[ $range ] ) ? (string) self::SUPPORTED_RANGES[ $range ] : (string) self::SUPPORTED_RANGES['30d'];
	}

	/**
	 * Build comparison rows for comparable metrics.
	 *
	 * @param array<string, mixed> $current_metrics  Current metrics.
	 * @param array<string, mixed> $previous_metrics Previous metrics.
	 * @return array<string, array<string, mixed>>
	 */
	protected function build_comparison_metrics( array $current_metrics, array $previous_metrics ) {
		$rows = array();

		foreach ( self::COMPARABLE_METRICS as $metric_key ) {
			$current_value  = isset( $current_metrics[ $metric_key ] ) ? (float) $current_metrics[ $metric_key ] : 0.0;
			$previous_value = isset( $previous_metrics[ $metric_key ] ) ? (float) $previous_metrics[ $metric_key ] : 0.0;
			$delta          = $current_value - $previous_value;
			$delta_percent  = null;
			if ( 0.0 !== $previous_value ) {
				$delta_percent = round( ( $delta / $previous_value ) * 100, 2 );
			}

			$trend = 'stable';
			if ( $delta > 0 ) {
				$trend = 'up';
			} elseif ( $delta < 0 ) {
				$trend = 'down';
			}

			$rows[ $metric_key ] = array(
				'current'       => round( $current_value, 2 ),
				'previous'      => round( $previous_value, 2 ),
				'delta'         => round( $delta, 2 ),
				'delta_percent' => $delta_percent,
				'trend'         => $trend,
			);
		}

		return $rows;
	}

	/**
	 * Resolve current and previous period bounds for comparison.
	 *
	 * @param string $range Range key.
	 * @return array<string, mixed>
	 */
	protected function resolve_comparison_periods( $range ) {
		$current = $this->repository->get_date_bounds_for_range( $range );
		$range   = $this->normalize_range( $range );

		if ( 'all' === $range ) {
			return array(
				'comparison_available' => false,
				'current'              => $current,
				'previous'             => array(
					'date_from' => '',
					'date_to'   => '',
				),
			);
		}

		$current_from_ts = strtotime( (string) $current['date_from'] . ' 00:00:00' );
		$current_to_ts   = strtotime( (string) $current['date_to'] . ' 23:59:59' );
		if ( false === $current_from_ts || false === $current_to_ts || $current_to_ts < $current_from_ts ) {
			return array(
				'comparison_available' => false,
				'current'              => $current,
				'previous'             => array(
					'date_from' => '',
					'date_to'   => '',
				),
			);
		}

		$period_days       = max( 1, (int) floor( ( $current_to_ts - $current_from_ts ) / DAY_IN_SECONDS ) + 1 );
		$previous_end_ts   = strtotime( '-1 day', $current_from_ts );
		$previous_start_ts = strtotime( '-' . ( $period_days - 1 ) . ' days', $previous_end_ts );

		return array(
			'comparison_available' => false !== $previous_start_ts && false !== $previous_end_ts,
			'current'              => $current,
			'previous'             => array(
				'date_from' => false !== $previous_start_ts ? gmdate( 'Y-m-d', $previous_start_ts ) : '',
				'date_to'   => false !== $previous_end_ts ? gmdate( 'Y-m-d', $previous_end_ts ) : '',
			),
		);
	}
}
