<?php
/**
 * Export service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Export;

use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Notifications\Notification_Storage_Service;
use Super_Mechanic\Processes\Process_Service;
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
	 * Constructor.
	 *
	 * @param Client_Service|null               $client_service Client service.
	 * @param Vehicle_Service|null              $vehicle_service Vehicle service.
	 * @param Process_Service|null              $process_service Process service.
	 * @param Business_Membership_Service|null  $membership_service Membership service.
	 * @param Notification_Storage_Service|null $notification_storage_service Notification storage service.
	 */
	public function __construct(
		Client_Service $client_service = null,
		Vehicle_Service $vehicle_service = null,
		Process_Service $process_service = null,
		Business_Membership_Service $membership_service = null,
		Notification_Storage_Service $notification_storage_service = null
	) {
		$this->client_service               = $client_service ? $client_service : new Client_Service();
		$this->vehicle_service              = $vehicle_service ? $vehicle_service : new Vehicle_Service();
		$this->process_service              = $process_service ? $process_service : new Process_Service();
		$this->membership_service           = $membership_service ? $membership_service : new Business_Membership_Service();
		$this->notification_storage_service = $notification_storage_service ? $notification_storage_service : new Notification_Storage_Service();
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
		);
	}

	/**
	 * Export one dataset.
	 *
	 * @param string $dataset_key Dataset key.
	 * @param string $format Output format.
	 * @return array<string,mixed>
	 */
	public function export_dataset( $dataset_key, $format = 'json' ) {
		$dataset_key = sanitize_key( (string) $dataset_key );
		$format      = sanitize_key( (string) $format );

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

		$payload = $this->build_export_payload( $dataset_key );
		if ( empty( $payload['success'] ) ) {
			return $payload;
		}

		$dataset_payload = isset( $payload['payload'] ) && is_array( $payload['payload'] ) ? $payload['payload'] : array();
		$timestamp       = gmdate( 'Ymd-His' );
		$filename_base   = 'sm-export-' . $dataset_key . '-' . $timestamp;

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
	 * @param string $dataset_key Dataset key.
	 * @return array<string,mixed>
	 */
	public function build_export_payload( $dataset_key ) {
		$dataset_key = sanitize_key( (string) $dataset_key );
		$supported   = $this->get_supported_datasets();
		if ( ! isset( $supported[ $dataset_key ] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unsupported dataset.', 'super-mechanic' ),
			);
		}

		$items = array();
		switch ( $dataset_key ) {
			case 'clients':
				$items = $this->collect_clients();
				break;
			case 'vehicles':
				$items = $this->collect_vehicles();
				break;
			case 'processes':
				$items = $this->collect_processes();
				break;
			case 'memberships':
				$items = $this->collect_memberships();
				break;
			case 'notifications':
				$items = $this->collect_notifications();
				break;
		}

		return array(
			'success' => true,
			'payload' => array(
				'dataset'     => $dataset_key,
				'generated_at'=> current_time( 'mysql' ),
				'generated_by'=> absint( get_current_user_id() ),
				'count'       => count( $items ),
				'items'       => $items,
			),
		);
	}

	/**
	 * Collect clients with paginated reads.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_clients() {
		$total = max( 0, absint( $this->client_service->count_clients() ) );
		return $this->collect_paginated(
			$total,
			function ( $page, $per_page ) {
				return $this->client_service->get_clients(
					array(
						'page'     => $page,
						'per_page' => $per_page,
						'orderby'  => 'id',
						'order'    => 'ASC',
					)
				);
			}
		);
	}

	/**
	 * Collect vehicles with paginated reads.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_vehicles() {
		$total = max( 0, absint( $this->vehicle_service->count_vehicles() ) );
		return $this->collect_paginated(
			$total,
			function ( $page, $per_page ) {
				return $this->vehicle_service->get_vehicles(
					array(
						'page'     => $page,
						'per_page' => $per_page,
						'orderby'  => 'id',
						'order'    => 'ASC',
					)
				);
			}
		);
	}

	/**
	 * Collect processes with paginated reads.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_processes() {
		$total = max( 0, absint( $this->process_service->count_processes() ) );
		return $this->collect_paginated(
			$total,
			function ( $page, $per_page ) {
				return $this->process_service->get_processes(
					array(
						'page'     => $page,
						'per_page' => $per_page,
						'orderby'  => 'id',
						'order'    => 'ASC',
					)
				);
			}
		);
	}

	/**
	 * Collect memberships by iterating users.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_memberships() {
		$users       = get_users( array( 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
		$memberships = array();

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
				if ( ! is_array( $membership ) ) {
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
	 * Collect notifications with paginated reads.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function collect_notifications() {
		$notifications = array();
		$page          = 1;
		$per_page      = self::PAGE_SIZE;
		$total_pages   = 1;

		do {
			$payload = $this->notification_storage_service->get_admin_notifications( array(), $page, $per_page );
			$items   = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array();
			$total   = isset( $payload['total'] ) ? absint( $payload['total'] ) : 0;
			$total_pages = max( 1, (int) ceil( $total / $per_page ) );

			foreach ( $items as $item ) {
				if ( is_array( $item ) ) {
					$notifications[] = $item;
				}
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

		$all_rows   = array();
		$per_page   = self::PAGE_SIZE;
		$total_pages= max( 1, (int) ceil( $total_items / $per_page ) );

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
