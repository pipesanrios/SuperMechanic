<?php
/**
 * Export service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Export;

use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Invoices\Payment_Repository;
use Super_Mechanic\Notifications\Notification_Storage_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;
use Super_Mechanic\Users\Business_Membership_Service;
use Super_Mechanic\Vehicles\Vehicle_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles dataset export payloads and formatting.
 */
class Export_Service {
	/**
	 * Maximum rows fetched per page.
	 *
	 * @var int
	 */
	const PAGE_SIZE = 250;

	/**
	 * Supported export ranges.
	 *
	 * @var array<string,string>
	 */
	const SUPPORTED_RANGES = array(
		'7d'  => 'Last 7 days',
		'30d' => 'Last 30 days',
		'90d' => 'Last 90 days',
		'all' => 'All time',
	);

	/**
	 * Client service dependency.
	 *
	 * @var Client_Service
	 */
	protected $client_service;

	/**
	 * Vehicle service dependency.
	 *
	 * @var Vehicle_Service
	 */
	protected $vehicle_service;

	/**
	 * Process service dependency.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * Membership service dependency.
	 *
	 * @var Business_Membership_Service
	 */
	protected $membership_service;

	/**
	 * Notification storage dependency.
	 *
	 * @var Notification_Storage_Service
	 */
	protected $notification_storage_service;

	/**
	 * Quote service dependency.
	 *
	 * @var Quote_Service
	 */
	protected $quote_service;

	/**
	 * Invoice service dependency.
	 *
	 * @var Invoice_Service
	 */
	protected $invoice_service;

	/**
	 * Payment repository dependency.
	 *
	 * @var Payment_Repository
	 */
	protected $payment_repository;

	/**
	 * Business context dependency.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Client_Service|null               $client_service Client service.
	 * @param Vehicle_Service|null              $vehicle_service Vehicle service.
	 * @param Process_Service|null              $process_service Process service.
	 * @param Business_Membership_Service|null  $membership_service Membership service.
	 * @param Notification_Storage_Service|null $notification_storage_service Notification storage service.
	 * @param Quote_Service|null                $quote_service Quote service.
	 * @param Invoice_Service|null              $invoice_service Invoice service.
	 * @param Payment_Repository|null           $payment_repository Payment repository.
	 * @param Business_Context_Service|null     $business_context_service Business context service.
	 */
	public function __construct(
		Client_Service $client_service = null,
		Vehicle_Service $vehicle_service = null,
		Process_Service $process_service = null,
		Business_Membership_Service $membership_service = null,
		Notification_Storage_Service $notification_storage_service = null,
		Quote_Service $quote_service = null,
		Invoice_Service $invoice_service = null,
		Payment_Repository $payment_repository = null,
		Business_Context_Service $business_context_service = null
	) {
		$this->client_service               = $client_service ? $client_service : new Client_Service();
		$this->vehicle_service              = $vehicle_service ? $vehicle_service : new Vehicle_Service();
		$this->process_service              = $process_service ? $process_service : new Process_Service();
		$this->membership_service           = $membership_service ? $membership_service : new Business_Membership_Service();
		$this->notification_storage_service = $notification_storage_service ? $notification_storage_service : new Notification_Storage_Service();
		$this->quote_service                = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service              = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->payment_repository           = $payment_repository ? $payment_repository : new Payment_Repository();
		$this->business_context_service     = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Get supported dataset keys and labels.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_supported_datasets() {
		return array(
			'clients'       => array(
				'label'       => __( 'Clients', 'super-mechanic' ),
				'description' => __( 'Client records in current operational scope.', 'super-mechanic' ),
			),
			'vehicles'      => array(
				'label'       => __( 'Vehicles', 'super-mechanic' ),
				'description' => __( 'Vehicle records in current operational scope.', 'super-mechanic' ),
			),
			'processes'     => array(
				'label'       => __( 'Processes', 'super-mechanic' ),
				'description' => __( 'Process records in current operational scope.', 'super-mechanic' ),
			),
			'memberships'   => array(
				'label'       => __( 'Memberships', 'super-mechanic' ),
				'description' => __( 'Business membership assignments by user.', 'super-mechanic' ),
			),
			'notifications' => array(
				'label'       => __( 'Notifications', 'super-mechanic' ),
				'description' => __( 'Persistent internal notifications.', 'super-mechanic' ),
			),
			'quotes'        => array(
				'label'       => __( 'Quotes', 'super-mechanic' ),
				'description' => __( 'Commercial quote records.', 'super-mechanic' ),
			),
			'invoices'      => array(
				'label'       => __( 'Invoices', 'super-mechanic' ),
				'description' => __( 'Invoice records for billing analysis.', 'super-mechanic' ),
			),
			'payments'      => array(
				'label'       => __( 'Payments', 'super-mechanic' ),
				'description' => __( 'Payment records with invoice context.', 'super-mechanic' ),
			),
		);
	}

	/**
	 * Get supported ranges.
	 *
	 * @return array<string,string>
	 */
	public function get_supported_ranges() {
		return self::SUPPORTED_RANGES;
	}

	/**
	 * Export one dataset.
	 *
	 * @param string $dataset_key Dataset key.
	 * @param string $format      Output format.
	 * @param string $range       Date range.
	 * @param int    $business_id Optional business ID.
	 * @return array<string,mixed>
	 */
	public function export_dataset( $dataset_key, $format = 'json', $range = '30d', $business_id = 0 ) {
		$dataset_key = sanitize_key( (string) $dataset_key );
		$format      = sanitize_key( (string) $format );
		$filters     = $this->normalize_export_filters( $range, $business_id );

		if ( ! isset( $this->get_supported_datasets()[ $dataset_key ] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unsupported dataset.', 'super-mechanic' ),
			);
		}

		if ( ! in_array( $format, array( 'json', 'csv' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unsupported export format.', 'super-mechanic' ),
			);
		}

		$payload = $this->build_export_payload( $dataset_key, $filters );
		if ( empty( $payload['success'] ) ) {
			return $payload;
		}

		$dataset_payload = isset( $payload['payload'] ) && is_array( $payload['payload'] ) ? $payload['payload'] : array();
		$timestamp       = gmdate( 'Ymd-His' );
		$filename_base   = sprintf(
			'sm-export-%s-%s-b%d-%s',
			sanitize_key( $dataset_key ),
			sanitize_key( $filters['range'] ),
			absint( $filters['business_id'] ),
			$timestamp
		);

		if ( 'csv' === $format ) {
			$items = isset( $dataset_payload['items'] ) && is_array( $dataset_payload['items'] ) ? $dataset_payload['items'] : array();
			$csv   = $this->convert_items_to_csv( $items );

			return array(
				'success'  => true,
				'filename' => $filename_base . '.csv',
				'mime'     => 'text/csv; charset=utf-8',
				'content'  => $csv,
				'format'   => 'csv',
			);
		}

		return array(
			'success'  => true,
			'filename' => $filename_base . '.json',
			'mime'     => 'application/json; charset=utf-8',
			'content'  => wp_json_encode( $dataset_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			'format'   => 'json',
		);
	}

	/**
	 * Build one dataset payload.
	 *
	 * @param string              $dataset_key Dataset key.
	 * @param array<string,mixed> $filters     Normalized filters.
	 * @return array<string,mixed>
	 */
	public function build_export_payload( $dataset_key, array $filters = array() ) {
		$dataset_key = sanitize_key( (string) $dataset_key );
		$supported   = $this->get_supported_datasets();
		if ( ! isset( $supported[ $dataset_key ] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unsupported dataset.', 'super-mechanic' ),
			);
		}

		$filters = wp_parse_args(
			$filters,
			$this->normalize_export_filters( '30d', 0 )
		);

		$items = array();
		switch ( $dataset_key ) {
			case 'clients':
				$items = $this->collect_clients( $filters );
				break;
			case 'vehicles':
				$items = $this->collect_vehicles( $filters );
				break;
			case 'processes':
				$items = $this->collect_processes( $filters );
				break;
			case 'memberships':
				$items = $this->collect_memberships( $filters );
				break;
			case 'notifications':
				$items = $this->collect_notifications( $filters );
				break;
			case 'quotes':
				$items = $this->collect_quotes( $filters );
				break;
			case 'invoices':
				$items = $this->collect_invoices( $filters );
				break;
			case 'payments':
				$items = $this->collect_payments( $filters );
				break;
		}

		return array(
			'success' => true,
			'payload' => array(
				'dataset'      => $dataset_key,
				'filters'      => array(
					'range'                 => $filters['range'],
					'range_label'           => isset( self::SUPPORTED_RANGES[ $filters['range'] ] ) ? self::SUPPORTED_RANGES[ $filters['range'] ] : self::SUPPORTED_RANGES['30d'],
					'requested_business_id' => absint( $filters['requested_business_id'] ),
					'business_id'           => absint( $filters['business_id'] ),
					'date_from'             => (string) $filters['date_from'],
					'date_to'               => (string) $filters['date_to'],
				),
				'generated_at' => current_time( 'mysql' ),
				'generated_by' => absint( get_current_user_id() ),
				'count'        => count( $items ),
				'items'        => $items,
			),
		);
	}

	/**
	 * Collect clients with paginated reads.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_clients( array $filters ) {
		$base_args = $this->build_common_query_args( $filters, 'id', 'ASC' );
		$total     = max( 0, absint( $this->client_service->count_clients( $base_args ) ) );

		return $this->collect_paginated(
			$total,
			function ( $page, $per_page ) use ( $base_args ) {
				$args             = $base_args;
				$args['page']     = $page;
				$args['per_page'] = $per_page;

				return $this->client_service->get_clients( $args );
			}
		);
	}

	/**
	 * Collect vehicles with paginated reads.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_vehicles( array $filters ) {
		$base_args = $this->build_common_query_args( $filters, 'id', 'ASC' );
		$total     = max( 0, absint( $this->vehicle_service->count_vehicles( $base_args ) ) );

		return $this->collect_paginated(
			$total,
			function ( $page, $per_page ) use ( $base_args ) {
				$args             = $base_args;
				$args['page']     = $page;
				$args['per_page'] = $per_page;

				return $this->vehicle_service->get_vehicles( $args );
			}
		);
	}

	/**
	 * Collect processes with paginated reads.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_processes( array $filters ) {
		$base_args = $this->build_common_query_args( $filters, 'id', 'ASC' );
		$total     = max( 0, absint( $this->process_service->count_processes( $base_args ) ) );

		return $this->collect_paginated(
			$total,
			function ( $page, $per_page ) use ( $base_args ) {
				$args             = $base_args;
				$args['page']     = $page;
				$args['per_page'] = $per_page;

				return $this->process_service->get_processes( $args );
			}
		);
	}

	/**
	 * Collect quotes with paginated reads.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_quotes( array $filters ) {
		$base_args = $this->build_common_query_args( $filters, 'created_at', 'ASC' );
		$total     = max( 0, absint( $this->quote_service->count_quotes( $base_args ) ) );

		return $this->collect_paginated(
			$total,
			function ( $page, $per_page ) use ( $base_args ) {
				$args             = $base_args;
				$args['page']     = $page;
				$args['per_page'] = $per_page;

				return $this->quote_service->get_quotes( $args );
			}
		);
	}

	/**
	 * Collect invoices with paginated reads.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_invoices( array $filters ) {
		$base_args = $this->build_common_query_args( $filters, 'created_at', 'ASC' );
		$total     = max( 0, absint( $this->invoice_service->count_invoices( $base_args ) ) );

		return $this->collect_paginated(
			$total,
			function ( $page, $per_page ) use ( $base_args ) {
				$args             = $base_args;
				$args['page']     = $page;
				$args['per_page'] = $per_page;

				return $this->invoice_service->get_invoices( $args );
			}
		);
	}

	/**
	 * Collect payments with paginated reads.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_payments( array $filters ) {
		$base_args = $this->build_common_query_args( $filters, 'payment_date', 'ASC' );
		$total     = max( 0, absint( $this->payment_repository->count_all( $base_args ) ) );

		return $this->collect_paginated(
			$total,
			function ( $page, $per_page ) use ( $base_args ) {
				$args             = $base_args;
				$args['page']     = $page;
				$args['per_page'] = $per_page;

				return $this->payment_repository->get_all( $args );
			}
		);
	}

	/**
	 * Collect memberships.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_memberships( array $filters ) {
		$memberships = array();
		$business_id = absint( $filters['business_id'] );

		if ( $business_id > 0 ) {
			$business_memberships = $this->membership_service->get_business_members( $business_id );
			foreach ( $business_memberships as $membership ) {
				if ( ! is_array( $membership ) || ! $this->membership_matches_range( $membership, $filters ) ) {
					continue;
				}

				$user_id = isset( $membership['user_id'] ) ? absint( $membership['user_id'] ) : 0;
				$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

				$membership['user_display_name'] = $user ? sanitize_text_field( (string) $user->display_name ) : '';
				$membership['user_email']        = $user ? sanitize_email( (string) $user->user_email ) : '';
				$memberships[]                   = $membership;
			}

			return $memberships;
		}

		$users = get_users( array( 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
		foreach ( $users as $user ) {
			$user_id = isset( $user->ID ) ? absint( $user->ID ) : 0;
			if ( $user_id <= 0 ) {
				continue;
			}

			$user_memberships = $this->membership_service->get_user_memberships( $user_id );
			if ( empty( $user_memberships ) ) {
				continue;
			}

			$user_label = isset( $user->display_name ) ? sanitize_text_field( (string) $user->display_name ) : '';
			$user_email = isset( $user->user_email ) ? sanitize_email( (string) $user->user_email ) : '';

			foreach ( $user_memberships as $membership ) {
				if ( ! is_array( $membership ) || ! $this->membership_matches_range( $membership, $filters ) ) {
					continue;
				}

				$membership['user_display_name'] = $user_label;
				$membership['user_email']        = $user_email;
				$memberships[]                   = $membership;
			}
		}

		return $memberships;
	}

	/**
	 * Collect notifications with paginated reads and filter projection.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_notifications( array $filters ) {
		$notifications   = array();
		$page            = 1;
		$per_page        = self::PAGE_SIZE;
		$total_pages     = 1;
		$allowed_user_ids = $this->resolve_business_user_ids( absint( $filters['business_id'] ) );

		do {
			$payload = $this->notification_storage_service->get_admin_notifications( array(), $page, $per_page );
			$items   = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array();
			$total   = isset( $payload['total'] ) ? absint( $payload['total'] ) : 0;
			$total_pages = max( 1, (int) ceil( $total / $per_page ) );

			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				if ( ! $this->notification_matches_business_scope( $item, $allowed_user_ids ) ) {
					continue;
				}

				if ( ! $this->datetime_matches_range( isset( $item['created_at'] ) ? (string) $item['created_at'] : '', $filters ) ) {
					continue;
				}

				$notifications[] = $item;
			}

			++$page;
		} while ( $page <= $total_pages );

		return $notifications;
	}

	/**
	 * Collect generic paginated dataset rows.
	 *
	 * @param int      $total_items Total rows.
	 * @param callable $reader Reader callback: fn(int $page, int $per_page): array.
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_paginated( $total_items, callable $reader ) {
		$total_items = max( 0, absint( $total_items ) );
		if ( 0 === $total_items ) {
			return array();
		}

		$all_rows    = array();
		$per_page    = self::PAGE_SIZE;
		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );

		for ( $page = 1; $page <= $total_pages; $page++ ) {
			$rows = call_user_func( $reader, $page, $per_page );
			if ( ! is_array( $rows ) ) {
				continue;
			}
			foreach ( $rows as $row ) {
				if ( is_array( $row ) ) {
					$all_rows[] = $row;
				}
			}
		}

		return $all_rows;
	}

	/**
	 * Normalize export filters.
	 *
	 * @param string $range Range.
	 * @param int    $business_id Business ID.
	 * @return array<string,mixed>
	 */
	protected function normalize_export_filters( $range, $business_id ) {
		$range                = sanitize_key( (string) $range );
		$requested_business_id = absint( $business_id );

		if ( ! isset( self::SUPPORTED_RANGES[ $range ] ) ) {
			$range = '30d';
		}

		if ( $requested_business_id > 0 ) {
			$business_id = absint( $this->business_context_service->normalize_business_id( $requested_business_id ) );
		} else {
			$business_id = absint( $this->business_context_service->resolve_business_id() );
		}

		$dates = $this->build_range_dates( $range );

		return array(
			'range'                 => $range,
			'requested_business_id' => $requested_business_id,
			'business_id'           => $business_id,
			'date_from'             => $dates['date_from'],
			'date_to'               => $dates['date_to'],
		);
	}

	/**
	 * Build date limits for one range.
	 *
	 * @param string $range Range.
	 * @return array<string,string>
	 */
	protected function build_range_dates( $range ) {
		$range = sanitize_key( (string) $range );
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

		$now = time();

		return array(
			'date_from' => gmdate( 'Y-m-d', $now - ( $days * DAY_IN_SECONDS ) ),
			'date_to'   => gmdate( 'Y-m-d', $now ),
		);
	}

	/**
	 * Build list args for service/repository listing.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @param string              $orderby Order by.
	 * @param string              $order Order.
	 * @return array<string,mixed>
	 */
	protected function build_common_query_args( array $filters, $orderby = 'id', $order = 'ASC' ) {
		return array(
			'business_id' => absint( $filters['business_id'] ),
			'date_from'   => sanitize_text_field( (string) $filters['date_from'] ),
			'date_to'     => sanitize_text_field( (string) $filters['date_to'] ),
			'orderby'     => sanitize_key( (string) $orderby ),
			'order'       => 'ASC' === strtoupper( (string) $order ) ? 'ASC' : 'DESC',
		);
	}

	/**
	 * Resolve allowed user IDs for one business.
	 *
	 * @param int $business_id Business ID.
	 * @return array<int,int>
	 */
	protected function resolve_business_user_ids( $business_id ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return array();
		}

		$memberships = $this->membership_service->get_business_members( $business_id );
		$user_ids    = array();

		foreach ( $memberships as $membership ) {
			if ( ! is_array( $membership ) ) {
				continue;
			}
			$user_id = isset( $membership['user_id'] ) ? absint( $membership['user_id'] ) : 0;
			if ( $user_id > 0 ) {
				$user_ids[ $user_id ] = $user_id;
			}
		}

		return array_values( $user_ids );
	}

	/**
	 * Check notification against resolved business scope.
	 *
	 * @param array<string,mixed> $item Notification row.
	 * @param array<int,int>      $allowed_user_ids Allowed users for business.
	 * @return bool
	 */
	protected function notification_matches_business_scope( array $item, array $allowed_user_ids ) {
		if ( empty( $allowed_user_ids ) ) {
			return true;
		}

		$user_id = isset( $item['user_id'] ) ? absint( $item['user_id'] ) : 0;
		if ( $user_id <= 0 ) {
			return false;
		}

		return in_array( $user_id, $allowed_user_ids, true );
	}

	/**
	 * Check membership date against range.
	 *
	 * @param array<string,mixed> $membership Membership row.
	 * @param array<string,mixed> $filters Filters.
	 * @return bool
	 */
	protected function membership_matches_range( array $membership, array $filters ) {
		return $this->datetime_matches_range( isset( $membership['created_at'] ) ? (string) $membership['created_at'] : '', $filters );
	}

	/**
	 * Check if datetime value fits current range.
	 *
	 * @param string              $datetime Datetime string.
	 * @param array<string,mixed> $filters Filters.
	 * @return bool
	 */
	protected function datetime_matches_range( $datetime, array $filters ) {
		if ( 'all' === (string) $filters['range'] ) {
			return true;
		}

		$timestamp = strtotime( (string) $datetime );
		if ( false === $timestamp ) {
			return false;
		}

		$from = isset( $filters['date_from'] ) ? (string) $filters['date_from'] : '';
		$to   = isset( $filters['date_to'] ) ? (string) $filters['date_to'] : '';

		if ( '' !== $from ) {
			$from_ts = strtotime( $from . ' 00:00:00' );
			if ( false !== $from_ts && $timestamp < $from_ts ) {
				return false;
			}
		}

		if ( '' !== $to ) {
			$to_ts = strtotime( $to . ' 23:59:59' );
			if ( false !== $to_ts && $timestamp > $to_ts ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Convert item list to CSV string.
	 *
	 * @param array<int,array<string,mixed>> $items Dataset items.
	 * @return string
	 */
	protected function convert_items_to_csv( array $items ) {
		if ( empty( $items ) ) {
			return '';
		}

		$headers = array();
		foreach ( $items as $item ) {
			foreach ( array_keys( $item ) as $key ) {
				$key = (string) $key;
				if ( ! in_array( $key, $headers, true ) ) {
					$headers[] = $key;
				}
			}
		}

		$handle = fopen( 'php://temp', 'r+' );
		if ( false === $handle ) {
			return '';
		}

		fputcsv( $handle, $headers );
		foreach ( $items as $item ) {
			$row = array();
			foreach ( $headers as $header ) {
				$value = isset( $item[ $header ] ) ? $item[ $header ] : '';
				$row[] = is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value );
			}
			fputcsv( $handle, $row );
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		return false === $csv ? '' : (string) $csv;
	}
}
