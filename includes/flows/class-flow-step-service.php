<?php
/**
 * Flow step service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Flows;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles flow step business rules.
 */
class Flow_Step_Service {
	/**
	 * Flow step repository.
	 *
	 * @var Flow_Step_Repository
	 */
	protected $repository;

	/**
	 * Flow service.
	 *
	 * @var Flow_Service
	 */
	protected $flow_service;

	/**
	 * Transaction repository.
	 *
	 * @var Flow_Transaction_Repository
	 */
	protected $transaction_repository;

	/**
	 * Constructor.
	 *
	 * @param Flow_Step_Repository|null $repository   Step repository.
	 * @param Flow_Service|null         $flow_service Flow service.
	 */
	public function __construct( Flow_Step_Repository $repository = null, Flow_Service $flow_service = null, Flow_Transaction_Repository $transaction_repository = null ) {
		$this->repository             = $repository ? $repository : new Flow_Step_Repository();
		$this->flow_service           = $flow_service ? $flow_service : new Flow_Service();
		$this->transaction_repository = $transaction_repository ? $transaction_repository : new Flow_Transaction_Repository();
	}

	/**
	 * Create a step.
	 *
	 * @param array<string, mixed> $data Step data.
	 * @return int|WP_Error
	 */
	public function create_step( array $data ) {
		$data  = $this->prepare_step_data( $data, false );
		$valid = $this->validate_step_data( $data, false );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( $data['is_initial'] ) {
			$this->clear_initial_step( $data['flow_id'] );
		}

		$inserted = $this->repository->insert( $this->format_step_data_for_storage( $data ) );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_flow_step_insert_failed', __( 'No fue posible crear el paso.', 'super-mechanic' ) );
		}

		$this->normalize_step_order( $data['flow_id'] );

		return $inserted;
	}

	/**
	 * Update a step.
	 *
	 * @param int                  $id   Step ID.
	 * @param array<string, mixed> $data Step data.
	 * @return bool|WP_Error
	 */
	public function update_step( $id, array $data ) {
		$id       = absint( $id );
		$existing = $this->repository->get_by_id( $id );

		if ( ! $existing ) {
			return new WP_Error( 'sm_flow_step_not_found', __( 'El paso no existe.', 'super-mechanic' ) );
		}

		$data  = $this->prepare_step_data( array_merge( $existing, $data ), true );
		$valid = $this->validate_step_data( $data, true, $id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( $data['is_initial'] ) {
			$this->clear_initial_step( $data['flow_id'], $id );
		}

		$updated = $this->repository->update( $id, $this->format_step_data_for_storage( $data ) );

		if ( ! $updated ) {
			return new WP_Error( 'sm_flow_step_update_failed', __( 'No fue posible actualizar el paso.', 'super-mechanic' ) );
		}

		$this->normalize_step_order( $data['flow_id'] );

		return true;
	}

	/**
	 * Delete a step.
	 *
	 * @param int $id Step ID.
	 * @return bool|WP_Error
	 */
	public function delete_step( $id ) {
		$id   = absint( $id );
		$step = $this->repository->get_by_id( $id );

		if ( ! $step ) {
			return new WP_Error( 'sm_flow_step_not_found', __( 'El paso no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->delete( $id ) ) {
			return new WP_Error( 'sm_flow_step_delete_failed', __( 'No fue posible eliminar el paso.', 'super-mechanic' ) );
		}

		$this->normalize_step_order( absint( $step['flow_id'] ) );

		return true;
	}

	/**
	 * Get a step.
	 *
	 * @param int $id Step ID.
	 * @return array<string, mixed>|null
	 */
	public function get_step( $id ) {
		return $this->repository->get_by_id( $id );
	}

	/**
	 * Get ordered steps by flow.
	 *
	 * @param int  $flow_id     Flow ID.
	 * @param bool $only_active Only active steps.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_steps_by_flow( $flow_id, $only_active = false ) {
		return $this->repository->get_by_flow_id( $flow_id, $only_active );
	}

	/**
	 * Reorder steps inside a flow.
	 *
	 * @param int          $flow_id           Flow ID.
	 * @param array<int,int> $ordered_step_ids Ordered step IDs.
	 * @return bool|WP_Error
	 */
	public function reorder_steps( $flow_id, array $ordered_step_ids ) {
		$flow_id = absint( $flow_id );

		if ( ! $this->flow_service->get_flow( $flow_id ) ) {
			return new WP_Error( 'sm_flow_not_found', __( 'El flujo no existe.', 'super-mechanic' ) );
		}

		return $this->transaction_repository->run_in_transaction(
			function () use ( $flow_id, $ordered_step_ids ) {
				$position = 1;

				foreach ( $ordered_step_ids as $step_id ) {
					$step = $this->repository->get_by_id( $step_id );
					if ( ! $step || absint( $step['flow_id'] ) !== $flow_id ) {
						continue;
					}

					if ( ! $this->repository->update(
						$step_id,
						array(
							'step_order' => $position,
						)
					) ) {
						return new WP_Error( 'sm_flow_step_reorder_failed', __( 'No fue posible reordenar los pasos del flujo.', 'super-mechanic' ) );
					}

					++$position;
				}

				$this->normalize_step_order( $flow_id );

				return true;
			}
		);
	}

	/**
	 * Validate step data.
	 *
	 * @param array<string, mixed> $data      Step data.
	 * @param bool                 $is_update Whether updating.
	 * @param int                  $step_id   Step ID.
	 * @return true|WP_Error
	 */
	public function validate_step_data( array $data, $is_update = false, $step_id = 0 ) {
		$errors = new WP_Error();

		if ( empty( $data['flow_id'] ) || ! $this->flow_service->get_flow( $data['flow_id'] ) ) {
			$errors->add( 'invalid_flow', __( 'Debes seleccionar un flujo válido.', 'super-mechanic' ) );
		}

		if ( empty( $data['step_key'] ) ) {
			$errors->add( 'step_key_required', __( 'La clave técnica del paso es obligatoria.', 'super-mechanic' ) );
		}

		if ( empty( $data['step_label'] ) ) {
			$errors->add( 'step_label_required', __( 'La etiqueta del paso es obligatoria.', 'super-mechanic' ) );
		}

		$existing = $this->repository->get_step_by_key( $data['flow_id'], $data['step_key'] );
		if ( $existing && absint( $existing['id'] ) !== absint( $step_id ) ) {
			$errors->add( 'duplicate_step_key', __( 'Ya existe un paso con esa clave dentro del flujo.', 'super-mechanic' ) );
		}

		if ( $data['is_initial'] && ! $data['is_active'] ) {
			$errors->add( 'initial_inactive', __( 'El paso inicial debe estar activo.', 'super-mechanic' ) );
		}

		if ( $is_update && $step_id <= 0 ) {
			$errors->add( 'invalid_step_id', __( 'El identificador del paso no es válido.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Get the initial step for a flow.
	 *
	 * @param int $flow_id Flow ID.
	 * @return array<string, mixed>|null
	 */
	public function get_initial_step( $flow_id ) {
		return $this->repository->get_initial_step( $flow_id );
	}

	/**
	 * Resolve the first valid step for a flow.
	 *
	 * Prefer the explicit initial step. If it does not exist, fall back to the
	 * first active step by configured order.
	 *
	 * @param int $flow_id Flow ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function resolve_initial_step( $flow_id ) {
		$flow_id = absint( $flow_id );

		if ( ! $flow_id || ! $this->flow_service->get_flow( $flow_id ) ) {
			return new WP_Error( 'sm_flow_not_found', __( 'No existe un flujo valido para resolver el paso inicial.', 'super-mechanic' ) );
		}

		$step = $this->repository->get_initial_step( $flow_id );

		if ( ! empty( $step ) && ! empty( $step['is_active'] ) ) {
			return $step;
		}

		$steps = $this->repository->get_by_flow_id( $flow_id, true );

		if ( empty( $steps ) ) {
			return new WP_Error( 'sm_flow_steps_not_found', __( 'El flujo seleccionado no tiene pasos activos disponibles.', 'super-mechanic' ) );
		}

		return $steps[0];
	}

	/**
	 * Get a step by technical key.
	 *
	 * @param int    $flow_id  Flow ID.
	 * @param string $step_key Step key.
	 * @return array<string, mixed>|null
	 */
	public function get_step_by_key( $flow_id, $step_key ) {
		return $this->repository->get_step_by_key( $flow_id, sanitize_key( $step_key ) );
	}

	/**
	 * Check whether a status belongs to a flow.
	 *
	 * @param int    $flow_id  Flow ID.
	 * @param string $step_key Step key.
	 * @return bool
	 */
	public function status_belongs_to_flow( $flow_id, $step_key ) {
		return null !== $this->get_step_by_key( $flow_id, $step_key );
	}

	/**
	 * Get the next active step in the configured order.
	 *
	 * @param int $flow_id Flow ID.
	 * @param int $step_id Current step ID.
	 * @return array<string, mixed>|null
	 */
	public function get_next_active_step( $flow_id, $step_id ) {
		$steps = $this->repository->get_by_flow_id( $flow_id, true );

		foreach ( $steps as $index => $step ) {
			if ( absint( $step['id'] ) === absint( $step_id ) ) {
				return isset( $steps[ $index + 1 ] ) ? $steps[ $index + 1 ] : null;
			}
		}

		return null;
	}

	/**
	 * Get the previous active step in the configured order.
	 *
	 * @param int $flow_id Flow ID.
	 * @param int $step_id Current step ID.
	 * @return array<string, mixed>|null
	 */
	public function get_previous_active_step( $flow_id, $step_id ) {
		$steps = $this->repository->get_by_flow_id( $flow_id, true );

		foreach ( $steps as $index => $step ) {
			if ( absint( $step['id'] ) === absint( $step_id ) ) {
				return isset( $steps[ $index - 1 ] ) ? $steps[ $index - 1 ] : null;
			}
		}

		return null;
	}

	/**
	 * Determine whether a step is marked as final inside its flow.
	 *
	 * @param int $flow_id Flow ID.
	 * @param int $step_id Step ID.
	 * @return bool
	 */
	public function is_final_step( $flow_id, $step_id ) {
		$step = $this->repository->get_by_flow_and_id( $flow_id, $step_id );

		return ! empty( $step ) && ! empty( $step['is_final'] ) && ! empty( $step['is_active'] );
	}

	/**
	 * Validate a linear transition between active steps of a flow.
	 *
	 * The current model has no explicit transition graph, so the allowed moves
	 * are limited to the immediately previous or next active step.
	 *
	 * @param int $flow_id      Flow ID.
	 * @param int $from_step_id Current step ID.
	 * @param int $to_step_id   Target step ID.
	 * @return true|WP_Error
	 */
	public function validate_step_transition( $flow_id, $from_step_id, $to_step_id ) {
		$flow_id      = absint( $flow_id );
		$from_step_id = absint( $from_step_id );
		$to_step_id   = absint( $to_step_id );

		if ( ! $flow_id || ! $this->flow_service->get_flow( $flow_id ) ) {
			return new WP_Error( 'sm_flow_not_found', __( 'No existe un flujo valido para validar la transicion.', 'super-mechanic' ) );
		}

		if ( ! $to_step_id ) {
			return new WP_Error( 'sm_flow_step_transition_invalid', __( 'Debes indicar un paso destino valido.', 'super-mechanic' ) );
		}

		if ( $from_step_id === $to_step_id ) {
			return true;
		}

		$to_step = $this->repository->get_by_flow_and_id( $flow_id, $to_step_id );

		if ( ! $to_step || empty( $to_step['is_active'] ) ) {
			return new WP_Error( 'sm_flow_step_transition_invalid_target', __( 'El paso destino no pertenece al flujo activo configurado.', 'super-mechanic' ) );
		}

		if ( ! $from_step_id ) {
			$initial_step = $this->resolve_initial_step( $flow_id );

			if ( is_wp_error( $initial_step ) ) {
				return $initial_step;
			}

			if ( absint( $initial_step['id'] ) !== $to_step_id ) {
				return new WP_Error( 'sm_flow_step_transition_invalid_start', __( 'El proceso solo puede iniciar en el paso inicial configurado del flujo.', 'super-mechanic' ) );
			}

			return true;
		}

		$from_step = $this->repository->get_by_flow_and_id( $flow_id, $from_step_id );

		if ( ! $from_step || empty( $from_step['is_active'] ) ) {
			return new WP_Error( 'sm_flow_step_transition_invalid_source', __( 'El paso actual del proceso no es valido dentro del flujo activo.', 'super-mechanic' ) );
		}

		$next_step     = $this->get_next_active_step( $flow_id, $from_step_id );
		$previous_step = $this->get_previous_active_step( $flow_id, $from_step_id );

		if ( ! empty( $next_step ) && absint( $next_step['id'] ) === $to_step_id ) {
			return true;
		}

		if ( ! empty( $previous_step ) && absint( $previous_step['id'] ) === $to_step_id ) {
			return true;
		}

		return new WP_Error( 'sm_flow_step_transition_not_allowed', __( 'La transicion indicada no es valida para el orden actual del flujo.', 'super-mechanic' ) );
	}

	/**
	 * Prepare step data.
	 *
	 * @param array<string, mixed> $data      Step data.
	 * @param bool                 $is_update Whether updating.
	 * @return array<string, mixed>
	 */
	protected function prepare_step_data( array $data, $is_update ) {
		$flow_id    = isset( $data['flow_id'] ) ? absint( $data['flow_id'] ) : 0;
		$step_order = isset( $data['step_order'] ) ? absint( $data['step_order'] ) : 0;

		if ( $step_order <= 0 ) {
			$step_order = count( $this->repository->get_by_flow_id( $flow_id ) ) + 1;
		}

		return array(
			'flow_id'           => $flow_id,
			'step_key'          => isset( $data['step_key'] ) ? sanitize_key( $data['step_key'] ) : '',
			'step_label'        => isset( $data['step_label'] ) ? sanitize_text_field( $data['step_label'] ) : '',
			'step_order'        => $step_order,
			'is_initial'        => ! empty( $data['is_initial'] ) ? 1 : 0,
			'is_final'          => ! empty( $data['is_final'] ) ? 1 : 0,
			'requires_approval' => ! empty( $data['requires_approval'] ) ? 1 : 0,
			'requires_note'     => ! empty( $data['requires_note'] ) ? 1 : 0,
			'is_active'         => ! empty( $data['is_active'] ) ? 1 : 0,
		);
	}

	/**
	 * Format step data for storage.
	 *
	 * @param array<string, mixed> $data Step data.
	 * @return array<string, mixed>
	 */
	protected function format_step_data_for_storage( array $data ) {
		return array(
			'flow_id'           => $data['flow_id'],
			'step_key'          => $data['step_key'],
			'step_label'        => $data['step_label'],
			'step_order'        => $data['step_order'],
			'step_type'         => 'standard',
			'is_required'       => 0,
			'is_initial'        => $data['is_initial'],
			'is_final'          => $data['is_final'],
			'requires_approval' => $data['requires_approval'],
			'requires_note'     => $data['requires_note'],
			'is_active'         => $data['is_active'],
			'metadata'          => '',
		);
	}

	/**
	 * Clear current initial step.
	 *
	 * @param int $flow_id         Flow ID.
	 * @param int $exclude_step_id Step ID to exclude.
	 * @return void
	 */
	protected function clear_initial_step( $flow_id, $exclude_step_id = 0 ) {
		$initial = $this->repository->get_initial_step( $flow_id );

		if ( $initial && absint( $initial['id'] ) !== absint( $exclude_step_id ) ) {
			$this->repository->update(
				absint( $initial['id'] ),
				array(
					'is_initial' => 0,
				)
			);
		}
	}

	/**
	 * Normalize step order sequence.
	 *
	 * @param int $flow_id Flow ID.
	 * @return void
	 */
	protected function normalize_step_order( $flow_id ) {
		$steps    = $this->repository->get_by_flow_id( $flow_id );
		$position = 1;

		foreach ( $steps as $step ) {
			if ( absint( $step['step_order'] ) !== $position ) {
				$this->repository->update(
					absint( $step['id'] ),
					array(
						'step_order' => $position,
					)
				);
			}
			++$position;
		}
	}
}
