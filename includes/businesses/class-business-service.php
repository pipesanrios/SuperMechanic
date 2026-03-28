<?php
/**
 * Business service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Businesses;

defined( 'ABSPATH' ) || exit;

/**
 * Business domain service.
 */
class Business_Service {
	/**
	 * Repository.
	 *
	 * @var Business_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Business_Repository|null $repository Repository.
	 */
	public function __construct( Business_Repository $repository = null ) {
		$this->repository = $repository ? $repository : new Business_Repository();
	}

	/**
	 * List businesses.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_businesses( array $args = array() ) {
		return $this->repository->get_businesses( $args );
	}

	/**
	 * Count businesses.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return int
	 */
	public function count_businesses( array $args = array() ) {
		return $this->repository->count_businesses( $args );
	}

	/**
	 * Get business by id.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>|null
	 */
	public function get_business( $business_id ) {
		return $this->repository->get_by_id( $business_id );
	}

	/**
	 * Create business.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return int|\WP_Error
	 */
	public function create_business( array $data ) {
		$payload = $this->normalize_payload( $data );

		$validation = $this->validate_payload( $payload );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$existing = $this->repository->get_by_slug( $payload['slug'] );
		if ( is_array( $existing ) ) {
			return new \WP_Error( 'sm_business_slug_exists', __( 'El slug del negocio ya existe.', 'super-mechanic' ) );
		}

		$business_id = $this->repository->insert( $payload );
		if ( $business_id <= 0 ) {
			return new \WP_Error( 'sm_business_create_failed', __( 'No se pudo crear el negocio.', 'super-mechanic' ) );
		}

		if ( ! empty( $payload['is_default'] ) ) {
			$this->set_default_business( $business_id );
		}

		return $business_id;
	}

	/**
	 * Update business.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $data        Data.
	 * @return bool|\WP_Error
	 */
	public function update_business( $business_id, array $data ) {
		$business_id = absint( $business_id );
		$current     = $this->repository->get_by_id( $business_id );

		if ( ! is_array( $current ) ) {
			return new \WP_Error( 'sm_business_not_found', __( 'El negocio no existe.', 'super-mechanic' ) );
		}

		$payload = $this->normalize_payload( array_merge( $current, $data ) );
		$validation = $this->validate_payload( $payload );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$existing = $this->repository->get_by_slug( $payload['slug'] );
		if ( is_array( $existing ) && absint( $existing['id'] ) !== $business_id ) {
			return new \WP_Error( 'sm_business_slug_exists', __( 'El slug del negocio ya existe.', 'super-mechanic' ) );
		}

		if ( 1 === absint( $current['is_default'] ) && 'inactive' === $payload['status'] ) {
			return new \WP_Error( 'sm_business_default_inactive', __( 'No se puede desactivar el negocio por defecto.', 'super-mechanic' ) );
		}

		$updated = $this->repository->update( $business_id, $payload );
		if ( ! $updated ) {
			return new \WP_Error( 'sm_business_update_failed', __( 'No se pudo actualizar el negocio.', 'super-mechanic' ) );
		}

		if ( ! empty( $payload['is_default'] ) ) {
			$this->set_default_business( $business_id );
		}

		return true;
	}

	/**
	 * Delete business.
	 *
	 * @param int $business_id Business ID.
	 * @return bool|\WP_Error
	 */
	public function delete_business( $business_id ) {
		$business_id = absint( $business_id );
		$current     = $this->repository->get_by_id( $business_id );

		if ( ! is_array( $current ) ) {
			return new \WP_Error( 'sm_business_not_found', __( 'El negocio no existe.', 'super-mechanic' ) );
		}

		if ( 1 === absint( $current['is_default'] ) || 1 === $business_id ) {
			return new \WP_Error( 'sm_business_delete_default', __( 'No se puede eliminar el negocio por defecto.', 'super-mechanic' ) );
		}

		$deleted = $this->repository->delete( $business_id );
		if ( ! $deleted ) {
			return new \WP_Error( 'sm_business_delete_failed', __( 'No se pudo eliminar el negocio.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Ensure default business exists.
	 *
	 * @param string $name Default name.
	 * @return bool
	 */
	public function ensure_default_business( $name ) {
		return $this->repository->ensure_default_business( $name );
	}

	/**
	 * Get default business id.
	 *
	 * @return int
	 */
	public function get_default_business_id() {
		return $this->repository->get_default_business_id();
	}

	/**
	 * Resolve valid active business id.
	 *
	 * @param int $business_id Business ID.
	 * @return int
	 */
	public function resolve_valid_business_id( $business_id ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return 0;
		}

		$business = $this->repository->get_by_id( $business_id );
		if ( ! is_array( $business ) ) {
			return 0;
		}

		return 'active' === (string) $business['status'] ? $business_id : 0;
	}

	/**
	 * Set one business as default.
	 *
	 * @param int $business_id Business ID.
	 * @return void
	 */
	public function set_default_business( $business_id ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return;
		}

		$all = $this->repository->get_businesses(
			array(
				'page'     => 1,
				'per_page' => 200,
			)
		);
		foreach ( $all as $row ) {
			$row_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $row_id <= 0 ) {
				continue;
			}

			$this->repository->update(
				$row_id,
				array(
					'is_default' => $row_id === $business_id ? 1 : 0,
				)
			);
		}
	}

	/**
	 * Normalize payload.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array<string,mixed>
	 */
	protected function normalize_payload( array $data ) {
		$slug = sanitize_key( isset( $data['slug'] ) ? (string) $data['slug'] : '' );
		if ( '' === $slug && ! empty( $data['name'] ) ) {
			$slug = sanitize_key( (string) $data['name'] );
		}

		return array(
			'slug'                        => $slug,
			'name'                        => sanitize_text_field( isset( $data['name'] ) ? (string) $data['name'] : '' ),
			'status'                      => in_array( isset( $data['status'] ) ? (string) $data['status'] : 'active', array( 'active', 'inactive' ), true ) ? (string) $data['status'] : 'active',
			'is_default'                  => ! empty( $data['is_default'] ) ? 1 : 0,
			'timezone'                    => sanitize_text_field( isset( $data['timezone'] ) ? (string) $data['timezone'] : 'UTC' ),
			'currency'                    => strtoupper( sanitize_text_field( isset( $data['currency'] ) ? (string) $data['currency'] : 'USD' ) ),
			'branding_logo_attachment_id' => isset( $data['branding_logo_attachment_id'] ) ? absint( $data['branding_logo_attachment_id'] ) : 0,
			'primary_color'               => sanitize_text_field( isset( $data['primary_color'] ) ? (string) $data['primary_color'] : '' ),
		);
	}

	/**
	 * Validate payload.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return true|\WP_Error
	 */
	protected function validate_payload( array $payload ) {
		if ( '' === $payload['name'] ) {
			return new \WP_Error( 'sm_business_name_required', __( 'El nombre del negocio es obligatorio.', 'super-mechanic' ) );
		}

		if ( '' === $payload['slug'] ) {
			return new \WP_Error( 'sm_business_slug_required', __( 'El slug del negocio es obligatorio.', 'super-mechanic' ) );
		}

		return true;
	}
}

