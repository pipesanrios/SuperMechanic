<?php
/**
 * Vehicle catalog service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Vehicles;

use Super_Mechanic\Helpers\Business_Context_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Business-scoped reusable vehicle catalog service.
 */
class Vehicle_Catalog_Service {
	/**
	 * Repository.
	 *
	 * @var Vehicle_Catalog_Repository
	 */
	protected $repository;

	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Vehicle_Catalog_Repository|null $repository               Repository.
	 * @param Business_Context_Service|null   $business_context_service Business context service.
	 */
	public function __construct( Vehicle_Catalog_Repository $repository = null, Business_Context_Service $business_context_service = null ) {
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->repository               = $repository ? $repository : new Vehicle_Catalog_Repository( $this->business_context_service );
	}

	/**
	 * Create catalog vehicle.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int|WP_Error
	 */
	public function create_catalog_vehicle( array $data ) {
		$payload    = $this->normalize_payload( $data, false );
		$validation = $this->validate_payload( $payload, false );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$catalog_id = $this->repository->insert( $payload );
		if ( $catalog_id <= 0 ) {
			return new WP_Error( 'sm_vehicle_catalog_create_failed', __( 'No se pudo crear el vehículo del catálogo.', 'super-mechanic' ) );
		}

		return $catalog_id;
	}

	/**
	 * Update catalog vehicle.
	 *
	 * @param int                  $catalog_id Catalog ID.
	 * @param array<string, mixed> $data       Data.
	 * @return bool|WP_Error
	 */
	public function update_catalog_vehicle( $catalog_id, array $data ) {
		$catalog_id = absint( $catalog_id );
		$business_id = isset( $data['business_id'] ) ? $this->normalize_business_id( $data['business_id'], true ) : $this->resolve_business_id();
		$current    = $this->repository->get_by_id( $catalog_id, $business_id );

		if ( ! is_array( $current ) ) {
			return new WP_Error( 'sm_vehicle_catalog_not_found', __( 'El vehículo del catálogo no existe.', 'super-mechanic' ) );
		}

		$payload    = $this->normalize_payload( array_merge( $current, $data ), true );
		$validation = $this->validate_payload( $payload, true, $catalog_id );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( ! $this->repository->update( $catalog_id, $payload ) ) {
			return new WP_Error( 'sm_vehicle_catalog_update_failed', __( 'No se pudo actualizar el vehículo del catálogo.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Get catalog vehicle.
	 *
	 * @param int $catalog_id  Catalog ID.
	 * @param int $business_id Business ID.
	 * @return array<string, mixed>|null
	 */
	public function get_catalog_vehicle( $catalog_id, $business_id = 0 ) {
		return $this->repository->get_by_id( absint( $catalog_id ), $this->normalize_business_id( $business_id, true ) );
	}

	/**
	 * List catalog vehicles.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_catalog_vehicles( array $args = array() ) {
		$args['business_id'] = isset( $args['business_id'] ) ? $this->normalize_business_id( $args['business_id'], true ) : $this->resolve_business_id();

		return $this->repository->get_all( $args );
	}

	/**
	 * Count catalog vehicles.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_catalog_vehicles( array $args = array() ) {
		$args['business_id'] = isset( $args['business_id'] ) ? $this->normalize_business_id( $args['business_id'], true ) : $this->resolve_business_id();

		return $this->repository->count_all( $args );
	}

	/**
	 * Deactivate catalog vehicle.
	 *
	 * @param int $catalog_id  Catalog ID.
	 * @param int $business_id Business ID.
	 * @return bool|WP_Error
	 */
	public function deactivate_catalog_vehicle( $catalog_id, $business_id = 0 ) {
		$catalog_id  = absint( $catalog_id );
		$business_id = $this->normalize_business_id( $business_id, true );

		if ( ! $this->repository->get_by_id( $catalog_id, $business_id ) ) {
			return new WP_Error( 'sm_vehicle_catalog_not_found', __( 'El vehículo del catálogo no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->deactivate( $catalog_id, $business_id ) ) {
			return new WP_Error( 'sm_vehicle_catalog_deactivate_failed', __( 'No se pudo desactivar el vehículo del catálogo.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Normalize payload.
	 *
	 * @param array<string, mixed> $data      Raw data.
	 * @param bool                 $is_update Whether updating.
	 * @return array<string, mixed>
	 */
	protected function normalize_payload( array $data, $is_update ) {
		$business_id = isset( $data['business_id'] ) ? $this->normalize_business_id( $data['business_id'], true ) : $this->resolve_business_id();
		$year        = isset( $data['year'] ) ? absint( $data['year'] ) : 0;

		return array(
			'business_id'  => $business_id,
			'make'         => isset( $data['make'] ) ? sanitize_text_field( (string) $data['make'] ) : '',
			'model'        => isset( $data['model'] ) ? sanitize_text_field( (string) $data['model'] ) : '',
			'year'         => $year > 0 ? $year : null,
			'trim_version' => isset( $data['trim_version'] ) ? sanitize_text_field( (string) $data['trim_version'] ) : ( isset( $data['trim'] ) ? sanitize_text_field( (string) $data['trim'] ) : '' ),
			'body_type'    => isset( $data['body_type'] ) ? sanitize_text_field( (string) $data['body_type'] ) : '',
			'fuel_type'    => isset( $data['fuel_type'] ) ? sanitize_text_field( (string) $data['fuel_type'] ) : '',
			'transmission' => isset( $data['transmission'] ) ? sanitize_text_field( (string) $data['transmission'] ) : '',
			'engine'       => isset( $data['engine'] ) ? sanitize_text_field( (string) $data['engine'] ) : '',
			'notes'        => isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '',
			'status'       => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'active',
		);
	}

	/**
	 * Validate payload.
	 *
	 * @param array<string, mixed> $payload    Payload.
	 * @param bool                 $is_update  Whether updating.
	 * @param int                  $catalog_id Catalog ID.
	 * @return true|WP_Error
	 */
	protected function validate_payload( array $payload, $is_update, $catalog_id = 0 ) {
		$errors = new WP_Error();

		if ( empty( $payload['business_id'] ) ) {
			$errors->add( 'business_required', __( 'El negocio es obligatorio.', 'super-mechanic' ) );
		}

		if ( empty( $payload['make'] ) ) {
			$errors->add( 'make_required', __( 'La marca es obligatoria.', 'super-mechanic' ) );
		}

		if ( empty( $payload['model'] ) ) {
			$errors->add( 'model_required', __( 'El modelo es obligatorio.', 'super-mechanic' ) );
		}

		if ( ! empty( $payload['year'] ) ) {
			$year         = absint( $payload['year'] );
			$current_year = (int) gmdate( 'Y' ) + 1;

			if ( $year < 1900 || $year > $current_year ) {
				$errors->add( 'invalid_year', __( 'El año del vehículo no es válido.', 'super-mechanic' ) );
			}
		}

		if ( ! in_array( (string) $payload['status'], array( 'active', 'inactive' ), true ) ) {
			$errors->add( 'invalid_status', __( 'El estado del vehículo del catálogo no es válido.', 'super-mechanic' ) );
		}

		if ( $is_update && absint( $catalog_id ) <= 0 ) {
			$errors->add( 'invalid_catalog_id', __( 'El identificador del vehículo del catálogo no es válido.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Normalize business ID.
	 *
	 * @param int $business_id Business ID.
	 * @return int
	 */
	protected function normalize_business_id( $business_id, $fallback_to_active = false ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return $fallback_to_active ? $this->resolve_business_id() : 0;
		}

		$normalized = absint( $this->business_context_service->normalize_business_id( $business_id ) );

		return $normalized === $business_id ? $business_id : 0;
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
