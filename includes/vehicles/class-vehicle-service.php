<?php
/**
 * Vehicle service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Vehicles;

use Super_Mechanic\Clients\Client_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles vehicle business rules.
 */
class Vehicle_Service {
	/**
	 * Vehicle repository.
	 *
	 * @var Vehicle_Repository
	 */
	protected $repository;

	/**
	 * Client service.
	 *
	 * @var Client_Service
	 */
	protected $client_service;

	/**
	 * Constructor.
	 *
	 * @param Vehicle_Repository|null $repository     Vehicle repository.
	 * @param Client_Service|null     $client_service Client service.
	 */
	public function __construct( Vehicle_Repository $repository = null, Client_Service $client_service = null ) {
		$this->repository     = $repository ? $repository : new Vehicle_Repository();
		$this->client_service = $client_service ? $client_service : new Client_Service();
	}

	/**
	 * Create a vehicle.
	 *
	 * @param array<string, mixed> $data Vehicle data.
	 * @return int|WP_Error
	 */
	public function create_vehicle( array $data ) {
		$data  = $this->normalize_vehicle_data( $data );
		$valid = $this->validate_vehicle_data( $data, false );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $this->repository->insert( $data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_vehicle_insert_failed', __( 'No fue posible crear el vehículo.', 'super-mechanic' ) );
		}

		return $inserted;
	}

	/**
	 * Update a vehicle.
	 *
	 * @param int                  $id   Vehicle ID.
	 * @param array<string, mixed> $data Vehicle data.
	 * @return bool|WP_Error
	 */
	public function update_vehicle( $id, array $data ) {
		$id = absint( $id );
		if ( ! $id || ! $this->repository->get_by_id( $id ) ) {
			return new WP_Error( 'sm_vehicle_not_found', __( 'El vehículo no existe.', 'super-mechanic' ) );
		}

		$data  = $this->normalize_vehicle_data( $data );
		$valid = $this->validate_vehicle_data( $data, true, $id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$updated = $this->repository->update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error( 'sm_vehicle_update_failed', __( 'No fue posible actualizar el vehículo.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Delete a vehicle.
	 *
	 * @param int $id Vehicle ID.
	 * @return bool|WP_Error
	 */
	public function delete_vehicle( $id ) {
		$id = absint( $id );

		if ( ! $id || ! $this->repository->get_by_id( $id ) ) {
			return new WP_Error( 'sm_vehicle_not_found', __( 'El vehículo no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->delete( $id ) ) {
			return new WP_Error( 'sm_vehicle_delete_failed', __( 'No fue posible eliminar el vehículo.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Get a vehicle.
	 *
	 * @param int $id Vehicle ID.
	 * @return array<string, mixed>|null
	 */
	public function get_vehicle( $id ) {
		$vehicle = $this->repository->get_by_id( $id );

		if ( ! is_array( $vehicle ) ) {
			return null;
		}

		if ( isset( $vehicle['make'] ) && ! isset( $vehicle['brand'] ) ) {
			$vehicle['brand'] = $vehicle['make'];
		}

		return $vehicle;
	}

	/**
	 * Get vehicles.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_vehicles( array $args = array() ) {
		return $this->repository->get_all( $args );
	}

	/**
	 * Count vehicles.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_vehicles( array $args = array() ) {
		return $this->repository->count_all( $args );
	}

	/**
	 * Validate vehicle data.
	 *
	 * @param array<string, mixed> $data       Vehicle data.
	 * @param bool                 $is_update  Whether updating.
	 * @param int                  $vehicle_id Vehicle ID.
	 * @return true|WP_Error
	 */
	public function validate_vehicle_data( array $data, $is_update = false, $vehicle_id = 0 ) {
		$errors = new WP_Error();

		if ( empty( $data['make'] ) ) {
			$errors->add( 'brand_required', __( 'La marca es obligatoria.', 'super-mechanic' ) );
		}

		if ( empty( $data['model'] ) ) {
			$errors->add( 'model_required', __( 'El modelo es obligatorio.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['year'] ) ) {
			$year         = absint( $data['year'] );
			$current_year = (int) gmdate( 'Y' ) + 1;

			if ( $year < 1900 || $year > $current_year ) {
				$errors->add( 'invalid_year', __( 'El año del vehículo no es válido.', 'super-mechanic' ) );
			}
		}

		if ( $data['client_id'] > 0 && ! $this->client_service->get_client( $data['client_id'] ) ) {
			$errors->add( 'invalid_client', __( 'El cliente seleccionado no existe.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['vin'] ) && $this->is_duplicate_vin( $data['vin'], $vehicle_id ) ) {
			$errors->add( 'duplicate_vin', __( 'Ya existe un vehículo con este VIN.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['plate'] ) && $this->is_duplicate_plate( $data['plate'], $vehicle_id ) ) {
			$errors->add( 'duplicate_plate', __( 'Ya existe un vehículo con esta placa.', 'super-mechanic' ) );
		}

		if ( $is_update && $vehicle_id <= 0 ) {
			$errors->add( 'invalid_vehicle_id', __( 'El identificador del vehículo no es válido.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Get clients for the vehicle selector.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_client_options() {
		return $this->client_service->get_clients(
			array(
				'per_page' => 200,
				'orderby'  => 'first_name',
				'order'    => 'ASC',
			)
		);
	}

	/**
	 * Normalize vehicle input.
	 *
	 * @param array<string, mixed> $data Raw vehicle data.
	 * @return array<string, mixed>
	 */
	protected function normalize_vehicle_data( array $data ) {
		$year = isset( $data['year'] ) ? absint( $data['year'] ) : 0;

		return array(
			'client_id' => isset( $data['client_id'] ) ? absint( $data['client_id'] ) : 0,
			'type'      => 'vehicle',
			'make'      => isset( $data['brand'] ) ? sanitize_text_field( $data['brand'] ) : '',
			'model'     => isset( $data['model'] ) ? sanitize_text_field( $data['model'] ) : '',
			'year'      => $year > 0 ? $year : null,
			'vin'       => isset( $data['vin'] ) ? strtoupper( sanitize_text_field( $data['vin'] ) ) : '',
			'plate'     => isset( $data['plate'] ) ? strtoupper( sanitize_text_field( $data['plate'] ) ) : '',
			'color'     => isset( $data['color'] ) ? sanitize_text_field( $data['color'] ) : '',
			'notes'     => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'status'    => 'active',
		);
	}

	/**
	 * Check duplicate VIN.
	 *
	 * @param string $vin        VIN.
	 * @param int    $vehicle_id Vehicle ID.
	 * @return bool
	 */
	protected function is_duplicate_vin( $vin, $vehicle_id ) {
		$matches = $this->repository->get_all(
			array(
				'exact_vin'  => $vin,
				'exclude_id' => absint( $vehicle_id ),
				'per_page'   => 1,
			)
		);

		return ! empty( $matches );
	}

	/**
	 * Check duplicate plate.
	 *
	 * @param string $plate      Plate.
	 * @param int    $vehicle_id Vehicle ID.
	 * @return bool
	 */
	protected function is_duplicate_plate( $plate, $vehicle_id ) {
		$matches = $this->repository->get_all(
			array(
				'exact_plate' => $plate,
				'exclude_id'  => absint( $vehicle_id ),
				'per_page'    => 1,
			)
		);

		return ! empty( $matches );
	}
}
