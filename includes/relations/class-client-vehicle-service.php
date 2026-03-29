<?php
/**
 * Client vehicle relation service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Relations;

use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Vehicles\Vehicle_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles client vehicle relation business rules.
 */
class Client_Vehicle_Service {
	/**
	 * Relation repository.
	 *
	 * @var Client_Vehicle_Repository
	 */
	protected $repository;

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
	 * Transaction repository.
	 *
	 * @var Client_Vehicle_Transaction_Repository
	 */
	protected $transaction_repository;
	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Client_Vehicle_Repository|null $repository      Relation repository.
	 * @param Client_Service|null            $client_service  Client service.
	 * @param Vehicle_Service|null           $vehicle_service Vehicle service.
	 */
	public function __construct( Client_Vehicle_Repository $repository = null, Client_Service $client_service = null, Vehicle_Service $vehicle_service = null, Client_Vehicle_Transaction_Repository $transaction_repository = null, Business_Context_Service $business_context_service = null ) {
		$this->repository               = $repository ? $repository : new Client_Vehicle_Repository();
		$this->client_service           = $client_service ? $client_service : new Client_Service();
		$this->vehicle_service          = $vehicle_service ? $vehicle_service : new Vehicle_Service();
		$this->transaction_repository   = $transaction_repository ? $transaction_repository : new Client_Vehicle_Transaction_Repository();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Assign a vehicle to a client.
	 *
	 * @param int                 $client_id Client ID.
	 * @param int                 $vehicle_id Vehicle ID.
	 * @param array<string, mixed> $args      Relation args.
	 * @return int|WP_Error
	 */
	public function assign_vehicle_to_client( $client_id, $vehicle_id, $args = array() ) {
		$client_id  = absint( $client_id );
		$vehicle_id = absint( $vehicle_id );
		$args       = wp_parse_args(
			$args,
			array(
				'ownership_type'      => 'owner',
				'start_date'          => current_time( 'Y-m-d' ),
				'end_date'            => null,
				'is_primary'          => true,
				'replace_primary'     => true,
			)
		);

		$validation = $this->validate_relation( $client_id, $vehicle_id, $args['ownership_type'] );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( $args['is_primary'] && $args['replace_primary'] ) {
			$this->end_active_primary_relations( $vehicle_id, $args['start_date'] );
		}

		$client      = $this->client_service->get_client( $client_id );
		$vehicle     = $this->vehicle_service->get_vehicle( $vehicle_id );
		$business_id = $this->resolve_business_id();

		if ( is_array( $client ) && ! empty( $client['business_id'] ) ) {
			$business_id = absint( $client['business_id'] );
		}

		if ( is_array( $vehicle ) && ! empty( $vehicle['business_id'] ) ) {
			$vehicle_business_id = absint( $vehicle['business_id'] );

			if ( $vehicle_business_id > 0 && $vehicle_business_id !== $business_id ) {
				return new WP_Error( 'sm_relation_business_mismatch', __( 'Cliente y vehículo deben pertenecer al mismo negocio.', 'super-mechanic' ) );
			}

			$business_id = $vehicle_business_id > 0 ? $vehicle_business_id : $business_id;
		}

		$relation_id = $this->repository->create_relation(
			array(
				'business_id'    => max( 1, absint( $business_id ) ),
				'client_id'      => $client_id,
				'vehicle_id'     => $vehicle_id,
				'ownership_type' => sanitize_text_field( $args['ownership_type'] ),
				'start_date'     => $args['start_date'],
				'end_date'       => $args['end_date'],
				'is_primary'     => $args['is_primary'] ? 1 : 0,
			)
		);

		if ( false === $relation_id ) {
			return new WP_Error( 'sm_relation_create_failed', __( 'No fue posible crear la relación cliente-vehículo.', 'super-mechanic' ) );
		}

		if ( $args['is_primary'] ) {
			$this->repository->sync_vehicle_primary_client( $vehicle_id, $client_id );
		}

		return $relation_id;
	}

	/**
	 * Transfer a vehicle to a new client.
	 *
	 * @param int                 $vehicle_id Vehicle ID.
	 * @param int                 $from_client_id Previous client ID.
	 * @param int                 $to_client_id New client ID.
	 * @param array<string, mixed> $args       Transfer args.
	 * @return int|WP_Error
	 */
	public function transfer_vehicle( $vehicle_id, $from_client_id, $to_client_id, $args = array() ) {
		$vehicle_id     = absint( $vehicle_id );
		$from_client_id = absint( $from_client_id );
		$to_client_id   = absint( $to_client_id );
		$args           = wp_parse_args(
			$args,
			array(
				'transfer_date'       => current_time( 'Y-m-d' ),
				'ownership_type'      => 'owner',
			)
		);

		$current_owner = $this->repository->get_current_owner( $vehicle_id );
		if ( empty( $current_owner ) ) {
			return new WP_Error( 'sm_relation_no_current_owner', __( 'El vehículo no tiene propietario actual registrado.', 'super-mechanic' ) );
		}

		if ( absint( $current_owner['client_id'] ) !== $from_client_id ) {
			return new WP_Error( 'sm_relation_owner_mismatch', __( 'El cliente origen no coincide con el propietario actual.', 'super-mechanic' ) );
		}

		return $this->transaction_repository->run_in_transaction(
			function () use ( $args, $current_owner, $to_client_id, $vehicle_id ) {
				if ( ! $this->repository->end_relation( absint( $current_owner['id'] ), $args['transfer_date'] ) ) {
					return new WP_Error( 'sm_relation_end_failed', __( 'No fue posible finalizar la relación anterior.', 'super-mechanic' ) );
				}

				return $this->assign_vehicle_to_client(
					$to_client_id,
					$vehicle_id,
					array(
						'ownership_type'  => $args['ownership_type'],
						'start_date'      => $args['transfer_date'],
						'is_primary'      => true,
						'replace_primary' => false,
					)
				);
			}
		);
	}

	/**
	 * Get all clients for a vehicle.
	 *
	 * @param int                 $vehicle_id Vehicle ID.
	 * @param array<string, mixed> $args      Query args.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public function get_vehicle_clients( $vehicle_id, $args = array() ) {
		$vehicle_id = absint( $vehicle_id );
		if ( ! $vehicle_id || ! $this->vehicle_service->get_vehicle( $vehicle_id ) ) {
			return new WP_Error( 'sm_vehicle_not_found', __( 'El vehículo no existe.', 'super-mechanic' ) );
		}

		return $this->repository->get_by_vehicle( $vehicle_id, $args );
	}

	/**
	 * Get all vehicles for a client.
	 *
	 * @param int                 $client_id Client ID.
	 * @param array<string, mixed> $args     Query args.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public function get_client_vehicles( $client_id, $args = array() ) {
		$client_id = absint( $client_id );
		if ( ! $client_id || ! $this->client_service->get_client( $client_id ) ) {
			return new WP_Error( 'sm_client_not_found', __( 'El cliente no existe.', 'super-mechanic' ) );
		}

		$relations = $this->repository->get_by_client( $client_id, $args );
		$indexed   = array();

		if ( is_array( $relations ) ) {
			foreach ( $relations as $relation ) {
				$vehicle_id = isset( $relation['vehicle_id'] ) ? absint( $relation['vehicle_id'] ) : 0;
				if ( $vehicle_id <= 0 ) {
					continue;
				}

				$indexed[ $vehicle_id ] = $relation;
			}
		}

		$per_page = isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : 200;
		$vehicles = $this->vehicle_service->get_vehicles(
			array(
				'client_id' => $client_id,
				'per_page'  => max( $per_page, 200 ),
				'page'      => 1,
				'orderby'   => 'created_at',
				'order'     => 'DESC',
			)
		);

		if ( is_array( $vehicles ) ) {
			foreach ( $vehicles as $vehicle ) {
				$vehicle_id = isset( $vehicle['id'] ) ? absint( $vehicle['id'] ) : 0;
				if ( $vehicle_id <= 0 || isset( $indexed[ $vehicle_id ] ) ) {
					continue;
				}

				$indexed[ $vehicle_id ] = array(
					'id'             => 0,
					'business_id'    => isset( $vehicle['business_id'] ) ? absint( $vehicle['business_id'] ) : $this->resolve_business_id(),
					'client_id'      => $client_id,
					'vehicle_id'     => $vehicle_id,
					'ownership_type' => 'owner',
					'start_date'     => null,
					'end_date'       => null,
					'is_primary'     => 1,
					'make'           => isset( $vehicle['make'] ) ? (string) $vehicle['make'] : '',
					'model'          => isset( $vehicle['model'] ) ? (string) $vehicle['model'] : '',
					'plate'          => isset( $vehicle['plate'] ) ? (string) $vehicle['plate'] : '',
					'vin'            => isset( $vehicle['vin'] ) ? (string) $vehicle['vin'] : '',
				);
			}
		}

		return array_values( $indexed );
	}

	/**
	 * Validate relation endpoints.
	 *
	 * @param int    $client_id      Client ID.
	 * @param int    $vehicle_id     Vehicle ID.
	 * @param string $ownership_type Ownership type.
	 * @return true|WP_Error
	 */
	protected function validate_relation( $client_id, $vehicle_id, $ownership_type ) {
		$errors = new WP_Error();

		if ( ! $client_id || ! $this->client_service->get_client( $client_id ) ) {
			$errors->add( 'sm_relation_invalid_client', __( 'El cliente indicado no existe.', 'super-mechanic' ) );
		}

		if ( ! $vehicle_id || ! $this->vehicle_service->get_vehicle( $vehicle_id ) ) {
			$errors->add( 'sm_relation_invalid_vehicle', __( 'El vehículo indicado no existe.', 'super-mechanic' ) );
		}

		$client  = $this->client_service->get_client( $client_id );
		$vehicle = $this->vehicle_service->get_vehicle( $vehicle_id );

		if ( is_array( $client ) && is_array( $vehicle ) && ! empty( $client['business_id'] ) && ! empty( $vehicle['business_id'] ) && absint( $client['business_id'] ) !== absint( $vehicle['business_id'] ) ) {
			$errors->add( 'sm_relation_business_mismatch', __( 'Cliente y vehículo deben pertenecer al mismo negocio.', 'super-mechanic' ) );
		}

		if ( '' === trim( (string) $ownership_type ) ) {
			$errors->add( 'sm_relation_invalid_type', __( 'El tipo de relación es obligatorio.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * End current primary relations for a vehicle.
	 *
	 * @param int    $vehicle_id Vehicle ID.
	 * @param string $end_date   End date.
	 * @return void
	 */
	protected function end_active_primary_relations( $vehicle_id, $end_date ) {
		$relations = $this->repository->get_active_relations_by_vehicle( $vehicle_id );

		foreach ( $relations as $relation ) {
			if ( ! empty( $relation['is_primary'] ) ) {
				$this->repository->end_relation( absint( $relation['id'] ), $end_date );
			}
		}
	}

	/**
	 * Resolve active business ID.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		return absint( $this->business_context_service->resolve_business_id() );
	}
}
