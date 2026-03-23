<?php
/**
 * Dashboard service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Processes\Process_Derived_State_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Relations\Client_Vehicle_Repository;
use Super_Mechanic\Vehicles\Vehicle_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates role-based dashboard data.
 */
class Dashboard_Service {
	/**
	 * Client service.
	 *
	 * @var Client_Service
	 */
	protected $client_service;

	/**
	 * Vehicle service.
	 *
	 * @var Vehicle_Service
	 */
	protected $vehicle_service;

	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * Client vehicle repository.
	 *
	 * @var Client_Vehicle_Repository
	 */
	protected $client_vehicle_repository;

	/**
	 * Access control service.
	 *
	 * @var Access_Control_Service
	 */
	protected $access_control_service;
	protected $process_derived_state_service;

	/**
	 * Constructor.
	 *
	 * @param Client_Service|null            $client_service            Client service.
	 * @param Vehicle_Service|null           $vehicle_service           Vehicle service.
	 * @param Process_Service|null           $process_service           Process service.
	 * @param Client_Vehicle_Repository|null $client_vehicle_repository Client vehicle repository.
	 */
	public function __construct( Client_Service $client_service = null, Vehicle_Service $vehicle_service = null, Process_Service $process_service = null, Client_Vehicle_Repository $client_vehicle_repository = null, Access_Control_Service $access_control_service = null, Process_Derived_State_Service $process_derived_state_service = null ) {
		$this->client_service            = $client_service ? $client_service : new Client_Service();
		$this->vehicle_service           = $vehicle_service ? $vehicle_service : new Vehicle_Service();
		$this->process_service           = $process_service ? $process_service : new Process_Service();
		$this->client_vehicle_repository = $client_vehicle_repository ? $client_vehicle_repository : new Client_Vehicle_Repository();
		$this->access_control_service    = $access_control_service ? $access_control_service : new Access_Control_Service();
		$this->process_derived_state_service = $process_derived_state_service ? $process_derived_state_service : new Process_Derived_State_Service( $this->process_service );
	}

	/**
	 * Get admin KPIs.
	 *
	 * @return array<string, int>
	 */
	public function get_admin_kpis() {
		return array(
			'total_clients'   => $this->client_service->count_clients(),
			'total_vehicles'  => $this->vehicle_service->count_vehicles(),
			'total_processes' => $this->process_service->count_processes(),
			'open_processes'  => $this->count_open_processes(),
		);
	}

	/**
	 * Get recent processes.
	 *
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_processes( $limit = 10 ) {
		return $this->append_derived_state_to_processes(
			$this->process_service->get_processes(
			array(
				'per_page' => max( 1, absint( $limit ) ),
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
			)
		);
	}

	/**
	 * Get counts grouped by status.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_processes_by_status() {
		return $this->get_grouped_process_counts( 'status' );
	}

	/**
	 * Get counts grouped by type.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_processes_by_type() {
		return $this->get_grouped_process_counts( 'process_type' );
	}

	/**
	 * Get recent vehicles.
	 *
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_vehicles( $limit = 10 ) {
		return $this->vehicle_service->get_vehicles(
			array(
				'per_page' => max( 1, absint( $limit ) ),
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);
	}

	/**
	 * Get recent clients.
	 *
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent_clients( $limit = 10 ) {
		return $this->client_service->get_clients(
			array(
				'per_page' => max( 1, absint( $limit ) ),
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);
	}

	/**
	 * Get mechanic KPIs.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, int>
	 */
	public function get_mechanic_kpis( $user_id ) {
		return array(
			'active_processes'      => count( $this->get_mechanic_active_processes( $user_id, 200 ) ),
			'pending_approvals'     => count( $this->get_mechanic_pending_approvals( $user_id, 200 ) ),
			'maintenance_processes' => count( $this->get_mechanic_processes( $user_id, array( 'process_type' => 'maintenance' ), 200 ) ),
		);
	}

	/**
	 * Get active mechanic processes.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_mechanic_active_processes( $user_id, $limit = 20 ) {
		return $this->get_mechanic_processes(
			$user_id,
			array(
				'exclude_statuses' => array( 'completed', 'delivered', 'cancelled' ),
			),
			$limit
		);
	}

	/**
	 * Get mechanic pending approvals.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_mechanic_pending_approvals( $user_id, $limit = 20 ) {
		return $this->get_mechanic_processes(
			$user_id,
			array(
				'status' => 'waiting_approval',
			),
			$limit
		);
	}

	/**
	 * Get client profile data.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	public function get_client_profile_data( $user_id ) {
		$client_id = $this->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return array();
		}

		return $this->client_service->get_client( $client_id );
	}

	/**
	 * Get client vehicles.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_client_vehicles( $user_id ) {
		$client_id = $this->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return array();
		}

		return $this->client_vehicle_repository->get_by_client(
			$client_id,
			array(
				'current_only' => true,
			)
		);
	}

	/**
	 * Get client processes.
	 *
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $args    Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_client_processes( $user_id, $args = array() ) {
		$client_id = $this->get_client_id_by_user_id( $user_id );

		if ( ! $client_id ) {
			return array();
		}

		$args      = wp_parse_args(
			$args,
			array(
				'per_page' => 20,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);
		$processes = $this->process_service->get_processes(
			array(
				'client_id' => $client_id,
				'per_page'  => max( 1, absint( $args['per_page'] ) ),
				'orderby'   => sanitize_key( $args['orderby'] ),
				'order'     => sanitize_key( $args['order'] ),
			)
		);
		$vehicle_ids = array_map( 'absint', wp_list_pluck( $this->get_client_vehicles( $user_id ), 'vehicle_id' ) );

		if ( ! empty( $vehicle_ids ) ) {
			$fallback = $this->process_service->get_processes(
				array(
					'per_page' => 200,
					'orderby'  => sanitize_key( $args['orderby'] ),
					'order'    => sanitize_key( $args['order'] ),
				)
			);

			foreach ( $fallback as $process ) {
				if ( in_array( absint( $process['vehicle_id'] ), $vehicle_ids, true ) ) {
					$processes[] = $process;
				}
			}
		}

		$unique = array();
		foreach ( $processes as $process ) {
			$process_id = ! empty( $process['id'] ) ? absint( $process['id'] ) : 0;

			if ( ! $process_id || ! $this->access_control_service->user_can_access_process( $user_id, $process_id ) ) {
				continue;
			}

			$unique[ $process_id ] = $process;
		}

		return $this->append_derived_state_to_processes( array_values( $unique ) );
	}

	/**
	 * Append derived state data to a single process row.
	 *
	 * @param array<string, mixed> $process Process row.
	 * @return array<string, mixed>
	 */
	public function append_derived_state_to_process( array $process ) {
		return $this->process_derived_state_service->append_derived_state( $process );
	}

	/**
	 * Append derived state data to process rows.
	 *
	 * @param array<int, array<string, mixed>> $processes Process rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function append_derived_state_to_processes( array $processes ) {
		return $this->process_derived_state_service->append_derived_states( $processes );
	}

	/**
	 * Get client recent activity.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_client_recent_activity( $user_id, $limit = 20 ) {
		$processes   = $this->get_client_processes( $user_id, array( 'per_page' => $limit ) );
		$process_ids = array_map( 'absint', wp_list_pluck( $processes, 'id' ) );

		if ( empty( $process_ids ) ) {
			return array();
		}

		return $this->process_service->get_recent_activity_by_process_ids( $process_ids, $limit, true );
	}

	/**
	 * Resolve client ID by WP user ID.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_client_id_by_user_id( $user_id ) {
		return $this->access_control_service->get_client_id_by_user_id( $user_id );
	}

	/**
	 * Check if a client user can access a vehicle.
	 *
	 * @param int $user_id    User ID.
	 * @param int $vehicle_id Vehicle ID.
	 * @return bool
	 */
	public function user_can_access_client_vehicle( $user_id, $vehicle_id ) {
		return $this->access_control_service->user_can_access_vehicle( $user_id, $vehicle_id );
	}

	/**
	 * Check if a client user can access a process.
	 *
	 * @param int $user_id    User ID.
	 * @param int $process_id Process ID.
	 * @return bool
	 */
	public function user_can_access_client_process( $user_id, $process_id ) {
		return $this->access_control_service->user_can_access_process( $user_id, $process_id );
	}

	/**
	 * Get grouped process counts.
	 *
	 * @param string $field Field.
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_grouped_process_counts( $field ) {
		return $this->process_service->get_grouped_process_counts( $field );
	}

	/**
	 * Count open processes.
	 *
	 * @return int
	 */
	protected function count_open_processes() {
		return $this->process_service->count_open_processes();
	}

	/**
	 * Get mechanic processes.
	 *
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $args    Args.
	 * @param int                 $limit   Limit.
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_mechanic_processes( $user_id, $args = array(), $limit = 20 ) {
		return $this->process_service->get_mechanic_processes( $user_id, $args, $limit );
	}
}
