<?php
/**
 * Vehicle service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Vehicles;

use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
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
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;
	/**
	 * Vehicle catalog service.
	 *
	 * @var Vehicle_Catalog_Service
	 */
	protected $vehicle_catalog_service;

	/**
	 * Constructor.
	 *
	 * @param Vehicle_Repository|null $repository     Vehicle repository.
	 * @param Client_Service|null          $client_service           Client service.
	 * @param Business_Context_Service|null $business_context_service Business context service.
	 * @param Vehicle_Catalog_Service|null $vehicle_catalog_service  Vehicle catalog service.
	 */
	public function __construct( Vehicle_Repository $repository = null, Client_Service $client_service = null, Business_Context_Service $business_context_service = null, Vehicle_Catalog_Service $vehicle_catalog_service = null ) {
		$this->repository               = $repository ? $repository : new Vehicle_Repository();
		$this->client_service           = $client_service ? $client_service : new Client_Service();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->vehicle_catalog_service  = $vehicle_catalog_service ? $vehicle_catalog_service : new Vehicle_Catalog_Service( null, $this->business_context_service );
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
		$args = $this->normalize_list_business_scope( $args );

		return $this->repository->get_all( $args );
	}

	/**
	 * Count vehicles.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_vehicles( array $args = array() ) {
		$args = $this->normalize_list_business_scope( $args );

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

		if ( empty( $data['client_id'] ) ) {
			$errors->add( 'client_required', __( 'El cliente es obligatorio.', 'super-mechanic' ) );
		}

		if ( empty( $data['make'] ) ) {
			$errors->add( 'brand_required', __( 'La marca es obligatoria.', 'super-mechanic' ) );
		}

		if ( empty( $data['model'] ) ) {
			$errors->add( 'model_required', __( 'El modelo es obligatorio.', 'super-mechanic' ) );
		}

		if ( empty( $data['plate'] ) && empty( $data['vin'] ) ) {
			$errors->add( 'vin_required_without_plate', __( 'El VIN es obligatorio cuando la placa no está informada.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['year'] ) ) {
			$year         = absint( $data['year'] );
			$current_year = (int) gmdate( 'Y' ) + 1;

			if ( $year < 1900 || $year > $current_year ) {
				$errors->add( 'invalid_year', __( 'El año del vehículo no es válido.', 'super-mechanic' ) );
			}
		}

		if ( ! empty( $data['catalog_vehicle_id'] ) && ! $this->get_catalog_vehicle_for_business( $data['catalog_vehicle_id'], $data['business_id'] ) ) {
			$errors->add( 'invalid_catalog_vehicle', __( 'El vehículo del catálogo seleccionado no pertenece al negocio actual.', 'super-mechanic' ) );
		}

		$client = null;
		if ( $data['client_id'] > 0 ) {
			$client = $this->client_service->get_client( $data['client_id'] );
		}

		if ( $data['client_id'] > 0 && ! $client ) {
			$errors->add( 'invalid_client', __( 'El cliente seleccionado no existe.', 'super-mechanic' ) );
		}

		if ( is_array( $client ) && ! empty( $client['business_id'] ) && absint( $client['business_id'] ) !== absint( $data['business_id'] ) ) {
			$errors->add( 'invalid_business_context', __( 'El cliente y el vehículo deben pertenecer al mismo negocio.', 'super-mechanic' ) );
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
		$candidate_business_id = isset( $data['business_id'] ) ? absint( $data['business_id'] ) : 0;
		$business_id = $candidate_business_id > 0
			? $this->normalize_business_id( $candidate_business_id )
			: $this->resolve_business_id_for_client( isset( $data['client_id'] ) ? absint( $data['client_id'] ) : 0 );
		$catalog_vehicle_id = isset( $data['catalog_vehicle_id'] ) ? absint( $data['catalog_vehicle_id'] ) : 0;
		$catalog_vehicle    = $catalog_vehicle_id > 0 ? $this->get_catalog_vehicle_for_business( $catalog_vehicle_id, $business_id ) : null;

		return array(
			'business_id'        => $business_id,
			'client_id'          => isset( $data['client_id'] ) ? absint( $data['client_id'] ) : 0,
			'type'               => 'vehicle',
			'make'               => isset( $data['brand'] ) ? sanitize_text_field( $data['brand'] ) : '',
			'model'              => isset( $data['model'] ) ? sanitize_text_field( $data['model'] ) : '',
			'year'               => $year > 0 ? $year : null,
			'catalog_vehicle_id' => $catalog_vehicle_id > 0 ? $catalog_vehicle_id : null,
			'trim_version'       => $this->normalize_catalog_text_field( $data, $catalog_vehicle, 'trim_version' ),
			'body_type'          => $this->normalize_catalog_text_field( $data, $catalog_vehicle, 'body_type' ),
			'fuel_type'          => $this->normalize_catalog_text_field( $data, $catalog_vehicle, 'fuel_type' ),
			'transmission'       => $this->normalize_catalog_text_field( $data, $catalog_vehicle, 'transmission' ),
			'engine'             => $this->normalize_catalog_text_field( $data, $catalog_vehicle, 'engine' ),
			'vin'                => isset( $data['vin'] ) ? strtoupper( sanitize_text_field( $data['vin'] ) ) : '',
			'plate'              => isset( $data['plate'] ) ? strtoupper( sanitize_text_field( $data['plate'] ) ) : '',
			'color'              => isset( $data['color'] ) ? sanitize_text_field( $data['color'] ) : '',
			'mileage'            => isset( $data['mileage'] ) && '' !== (string) $data['mileage'] ? absint( $data['mileage'] ) : null,
			'notes'              => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'status'             => 'active',
		);
	}

	/**
	 * Normalize a catalog-derived technical text field.
	 *
	 * @param array<string, mixed>      $data            Raw vehicle data.
	 * @param array<string, mixed>|null $catalog_vehicle Catalog vehicle.
	 * @param string                    $field           Field name.
	 * @return string
	 */
	protected function normalize_catalog_text_field( array $data, $catalog_vehicle, $field ) {
		if ( array_key_exists( $field, $data ) ) {
			return sanitize_text_field( (string) $data[ $field ] );
		}

		if ( is_array( $catalog_vehicle ) && isset( $catalog_vehicle[ $field ] ) ) {
			return sanitize_text_field( (string) $catalog_vehicle[ $field ] );
		}

		return '';
	}

	/**
	 * Get a catalog vehicle only when it belongs to the provided business.
	 *
	 * @param int $catalog_vehicle_id Catalog vehicle ID.
	 * @param int $business_id        Business ID.
	 * @return array<string, mixed>|null
	 */
	protected function get_catalog_vehicle_for_business( $catalog_vehicle_id, $business_id ) {
		$catalog_vehicle_id = absint( $catalog_vehicle_id );
		$business_id        = absint( $business_id );

		if ( $catalog_vehicle_id <= 0 || $business_id <= 0 ) {
			return null;
		}

		return $this->vehicle_catalog_service->get_catalog_vehicle( $catalog_vehicle_id, $business_id );
	}

	/**
	 * Resolve business ID from client parent when available.
	 *
	 * @param int $client_id Client ID.
	 * @return int
	 */
	protected function resolve_business_id_for_client( $client_id ) {
		$client_id = absint( $client_id );
		$client    = $client_id > 0 ? $this->client_service->get_client( $client_id ) : null;

		if ( is_array( $client ) && ! empty( $client['business_id'] ) ) {
			return max( 1, absint( $client['business_id'] ) );
		}

		return $this->resolve_business_id();
	}

	/**
	 * Resolve active business ID.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		return absint( $this->business_context_service->resolve_business_id() );
	}

	/**
	 * Normalize explicit business filter by user tenancy scope.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	protected function normalize_list_business_scope( array $args ) {
		$candidate_business_id = isset( $args['business_id'] ) ? absint( $args['business_id'] ) : 0;
		$args['business_id']   = $candidate_business_id > 0 ? $this->normalize_business_id( $candidate_business_id ) : $this->resolve_business_id();

		return $args;
	}

	/**
	 * Normalize business ID against allowed businesses for current user.
	 *
	 * @param int $business_id Candidate business ID.
	 * @return int
	 */
	protected function normalize_business_id( $business_id ) {
		return absint( $this->business_context_service->normalize_business_id( $business_id ) );
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
