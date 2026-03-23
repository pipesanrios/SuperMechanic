<?php
/**
 * Flow service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Flows;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles flow business rules.
 */
class Flow_Service {
	/**
	 * Allowed process types.
	 *
	 * @var array<int, string>
	 */
	protected $allowed_process_types = array(
		'maintenance',
		'pre_delivery',
		'paperwork',
	);

	/**
	 * Flow repository.
	 *
	 * @var Flow_Repository
	 */
	protected $repository;

	/**
	 * Flow step repository.
	 *
	 * @var Flow_Step_Repository
	 */
	protected $step_repository;

	/**
	 * Constructor.
	 *
	 * @param Flow_Repository|null      $repository      Flow repository.
	 * @param Flow_Step_Repository|null $step_repository Step repository.
	 */
	public function __construct( Flow_Repository $repository = null, Flow_Step_Repository $step_repository = null ) {
		$this->repository      = $repository ? $repository : new Flow_Repository();
		$this->step_repository = $step_repository ? $step_repository : new Flow_Step_Repository();
	}

	/**
	 * Create a flow.
	 *
	 * @param array<string, mixed> $data Flow data.
	 * @return int|WP_Error
	 */
	public function create_flow( array $data ) {
		$data  = $this->prepare_flow_data( $data );
		$valid = $this->validate_flow_data( $data, false );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $this->repository->insert( $this->format_flow_data_for_storage( $data ) );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_flow_insert_failed', __( 'No fue posible crear el flujo.', 'super-mechanic' ) );
		}

		return $inserted;
	}

	/**
	 * Update a flow.
	 *
	 * @param int                  $id   Flow ID.
	 * @param array<string, mixed> $data Flow data.
	 * @return bool|WP_Error
	 */
	public function update_flow( $id, array $data ) {
		$id = absint( $id );

		if ( ! $id || ! $this->repository->get_by_id( $id ) ) {
			return new WP_Error( 'sm_flow_not_found', __( 'El flujo no existe.', 'super-mechanic' ) );
		}

		$data  = $this->prepare_flow_data( $data );
		$valid = $this->validate_flow_data( $data, true, $id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$updated = $this->repository->update( $id, $this->format_flow_data_for_storage( $data ) );

		if ( ! $updated ) {
			return new WP_Error( 'sm_flow_update_failed', __( 'No fue posible actualizar el flujo.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Delete a flow and its steps.
	 *
	 * @param int $id Flow ID.
	 * @return bool|WP_Error
	 */
	public function delete_flow( $id ) {
		$id = absint( $id );

		if ( ! $id || ! $this->repository->get_by_id( $id ) ) {
			return new WP_Error( 'sm_flow_not_found', __( 'El flujo no existe.', 'super-mechanic' ) );
		}

		$this->step_repository->delete_by_flow_id( $id );

		if ( ! $this->repository->delete( $id ) ) {
			return new WP_Error( 'sm_flow_delete_failed', __( 'No fue posible eliminar el flujo.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Get a flow.
	 *
	 * @param int $id Flow ID.
	 * @return array<string, mixed>|null
	 */
	public function get_flow( $id ) {
		return $this->repository->get_by_id( $id );
	}

	/**
	 * Get flows.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_flows( array $args = array() ) {
		return $this->repository->get_all( $args );
	}

	/**
	 * Count flows.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_flows( array $args = array() ) {
		return $this->repository->count_all( $args );
	}

	/**
	 * Validate flow data.
	 *
	 * @param array<string, mixed> $data      Flow data.
	 * @param bool                 $is_update Whether updating.
	 * @param int                  $flow_id   Flow ID.
	 * @return true|WP_Error
	 */
	public function validate_flow_data( array $data, $is_update = false, $flow_id = 0 ) {
		$errors = new WP_Error();

		if ( empty( $data['name'] ) ) {
			$errors->add( 'name_required', __( 'El nombre del flujo es obligatorio.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['process_type'], $this->allowed_process_types, true ) ) {
			$errors->add( 'invalid_process_type', __( 'El tipo de proceso no es válido.', 'super-mechanic' ) );
		}

		$existing = $this->repository->get_by_slug( $data['slug'] );
		if ( $existing && absint( $existing['id'] ) !== absint( $flow_id ) ) {
			$errors->add( 'duplicate_slug', __( 'Ya existe un flujo con esta combinación de nombre y tipo.', 'super-mechanic' ) );
		}

		if ( $is_update && $flow_id <= 0 ) {
			$errors->add( 'invalid_flow_id', __( 'El identificador del flujo no es válido.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Get available process types.
	 *
	 * @return array<string, string>
	 */
	public function get_process_type_options() {
		return array(
			'maintenance'  => __( 'Mantenimiento', 'super-mechanic' ),
			'pre_delivery' => __( 'Pre-entrega', 'super-mechanic' ),
			'paperwork'    => __( 'Trámites', 'super-mechanic' ),
		);
	}

	/**
	 * Get flows by process type.
	 *
	 * @param string $process_type Process type.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_flows_by_process_type( $process_type ) {
		return $this->repository->get_by_process_type( $process_type );
	}

	/**
	 * Get first active flow by process type.
	 *
	 * @param string $process_type Process type.
	 * @return array<string, mixed>|null
	 */
	public function get_flow_for_process_type( $process_type ) {
		$process_type = sanitize_key( $process_type );
		$flows        = $this->repository->get_all(
			array(
				'process_type' => $process_type,
				'is_active'    => 1,
				'per_page'     => 200,
				'orderby'      => 'name',
				'order'        => 'ASC',
			)
		);

		if ( empty( $flows ) ) {
			return null;
		}

		foreach ( $flows as $flow ) {
			if ( ! empty( $flow['is_default'] ) ) {
				return $flow;
			}
		}

		return $flows[0];
	}

	/**
	 * Resolve the applicable active flow for a process type.
	 *
	 * @param string $process_type Process type.
	 * @return array<string, mixed>|WP_Error
	 */
	public function resolve_flow_for_process_type( $process_type ) {
		$process_type = sanitize_key( $process_type );

		if ( ! in_array( $process_type, $this->allowed_process_types, true ) ) {
			return new WP_Error( 'sm_invalid_process_type', __( 'El tipo de proceso no es valido para resolver un flujo.', 'super-mechanic' ) );
		}

		$flow = $this->get_flow_for_process_type( $process_type );

		if ( empty( $flow ) ) {
			return new WP_Error( 'sm_process_flow_not_found', __( 'No existe un flujo activo para el tipo de proceso seleccionado.', 'super-mechanic' ) );
		}

		return $flow;
	}

	/**
	 * Prepare flow data.
	 *
	 * @param array<string, mixed> $data Flow data.
	 * @return array<string, mixed>
	 */
	protected function prepare_flow_data( array $data ) {
		$name         = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$process_type = isset( $data['process_type'] ) ? sanitize_key( $data['process_type'] ) : '';

		return array(
			'name'         => $name,
			'process_type' => $process_type,
			'slug'         => sanitize_title( $process_type . '-' . $name ),
			'description'  => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'is_active'    => ! empty( $data['is_active'] ) ? 1 : 0,
		);
	}

	/**
	 * Format flow data for storage.
	 *
	 * @param array<string, mixed> $data Flow data.
	 * @return array<string, mixed>
	 */
	protected function format_flow_data_for_storage( array $data ) {
		return array(
			'name'         => $data['name'],
			'slug'         => $data['slug'],
			'flow_type'    => $data['process_type'],
			'process_type' => $data['process_type'],
			'description'  => $data['description'],
			'is_default'   => 0,
			'is_active'    => $data['is_active'],
		);
	}
}
