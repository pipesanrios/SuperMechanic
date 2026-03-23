<?php
/**
 * Paperwork service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Paperwork;

use Super_Mechanic\Processes\Process_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles paperwork business rules.
 */
class Paperwork_Service {
	/**
	 * Allowed statuses.
	 *
	 * @var array<int, string>
	 */
	protected $allowed_statuses = array(
		'pending',
		'in_progress',
		'waiting_documents',
		'submitted',
		'completed',
		'cancelled',
	);

	/**
	 * Repository.
	 *
	 * @var Paperwork_Repository
	 */
	protected $repository;

	/**
	 * Item repository.
	 *
	 * @var Paperwork_Item_Repository
	 */
	protected $item_repository;

	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * Constructor.
	 *
	 * @param Paperwork_Repository|null      $repository      Repository.
	 * @param Paperwork_Item_Repository|null $item_repository Item repository.
	 * @param Process_Service|null           $process_service Process service.
	 */
	public function __construct( Paperwork_Repository $repository = null, Paperwork_Item_Repository $item_repository = null, Process_Service $process_service = null ) {
		$this->repository      = $repository ? $repository : new Paperwork_Repository();
		$this->item_repository = $item_repository ? $item_repository : new Paperwork_Item_Repository();
		$this->process_service = $process_service ? $process_service : new Process_Service();
	}

	/**
	 * Ensure row exists.
	 *
	 * @param int $process_id Process ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function ensure_record( $process_id ) {
		$process_id = absint( $process_id );
		$existing   = $this->repository->get_by_process_id( $process_id );

		if ( $existing ) {
			return $existing;
		}

		$process = $this->process_service->get_process( $process_id );
		if ( ! $process ) {
			return new WP_Error( 'sm_process_not_found', __( 'El proceso no existe.', 'super-mechanic' ) );
		}

		if ( 'paperwork' !== $process['process_type'] ) {
			return new WP_Error( 'sm_not_paperwork_process', __( 'El proceso no corresponde a trámites.', 'super-mechanic' ) );
		}

		$inserted = $this->repository->create(
			array(
				'process_id'       => $process_id,
				'paperwork_type'   => '',
				'target_date'      => null,
				'completed_date'   => null,
				'assigned_user_id' => 0,
				'status'           => 'pending',
				'notes'            => '',
			)
		);

		if ( false === $inserted ) {
			return new WP_Error( 'sm_paperwork_insert_failed', __( 'No fue posible crear el registro de trámite.', 'super-mechanic' ) );
		}

		return $this->repository->get_by_process_id( $process_id );
	}

	/**
	 * Get by process.
	 *
	 * @param int $process_id Process ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_by_process( $process_id ) {
		return $this->ensure_record( $process_id );
	}

	/**
	 * Save paperwork data.
	 *
	 * @param int                  $process_id Process ID.
	 * @param array<string, mixed> $data       Data.
	 * @return bool|WP_Error
	 */
	public function save_paperwork( $process_id, array $data ) {
		$row = $this->ensure_record( $process_id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$prepared = $this->prepare_paperwork_data( $data );
		$valid    = $this->validate_paperwork_data( $prepared );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! $this->repository->update_by_process_id( $process_id, $prepared ) ) {
			return new WP_Error( 'sm_paperwork_update_failed', __( 'No fue posible guardar el trámite.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Add item.
	 *
	 * @param int                  $paperwork_id Paperwork ID.
	 * @param array<string, mixed> $data         Item data.
	 * @return int|WP_Error
	 */
	public function add_item( $paperwork_id, array $data ) {
		$prepared = $this->prepare_item_data( $data );
		$prepared['paperwork_id'] = absint( $paperwork_id );
		$valid = $this->validate_item_data( $prepared );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $this->item_repository->insert( $prepared );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_paperwork_item_insert_failed', __( 'No fue posible agregar el ítem del trámite.', 'super-mechanic' ) );
		}

		return $inserted;
	}

	/**
	 * Update item.
	 *
	 * @param int                  $item_id Item ID.
	 * @param array<string, mixed> $data    Data.
	 * @return bool|WP_Error
	 */
	public function update_item( $item_id, array $data ) {
		$prepared = $this->prepare_item_data( $data );
		$valid    = $this->validate_item_data( $prepared );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! $this->item_repository->update( $item_id, $prepared ) ) {
			return new WP_Error( 'sm_paperwork_item_update_failed', __( 'No fue posible actualizar el ítem del trámite.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Delete item.
	 *
	 * @param int $item_id Item ID.
	 * @return bool|WP_Error
	 */
	public function delete_item( $item_id ) {
		if ( ! $this->item_repository->delete( $item_id ) ) {
			return new WP_Error( 'sm_paperwork_item_delete_failed', __( 'No fue posible eliminar el ítem del trámite.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Get items.
	 *
	 * @param int $paperwork_id Paperwork ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_items( $paperwork_id ) {
		return $this->item_repository->get_by_paperwork_id( $paperwork_id );
	}

	/**
	 * Validate paperwork data.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return true|WP_Error
	 */
	public function validate_paperwork_data( array $data ) {
		$errors = new WP_Error();

		if ( $data['assigned_user_id'] > 0 && ! get_user_by( 'id', $data['assigned_user_id'] ) ) {
			$errors->add( 'invalid_assigned_user', __( 'El responsable asignado no existe.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['status'], $this->allowed_statuses, true ) ) {
			$errors->add( 'invalid_status', __( 'El estado administrativo no es válido.', 'super-mechanic' ) );
		}

		if ( $data['target_date'] && ! strtotime( $data['target_date'] ) ) {
			$errors->add( 'invalid_target_date', __( 'La fecha objetivo no es válida.', 'super-mechanic' ) );
		}

		if ( $data['completed_date'] && ! strtotime( $data['completed_date'] ) ) {
			$errors->add( 'invalid_completed_date', __( 'La fecha completada no es válida.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Validate item data.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return true|WP_Error
	 */
	public function validate_item_data( array $data ) {
		$errors = new WP_Error();

		if ( empty( $data['item_key'] ) ) {
			$errors->add( 'item_key_required', __( 'La clave del ítem es obligatoria.', 'super-mechanic' ) );
		}

		if ( empty( $data['item_label'] ) ) {
			$errors->add( 'item_label_required', __( 'La etiqueta del ítem es obligatoria.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Get allowed statuses.
	 *
	 * @return array<string, string>
	 */
	public function get_status_options() {
		return array(
			'pending'           => __( 'Pendiente', 'super-mechanic' ),
			'in_progress'       => __( 'En progreso', 'super-mechanic' ),
			'waiting_documents' => __( 'Esperando documentos', 'super-mechanic' ),
			'submitted'         => __( 'Enviado', 'super-mechanic' ),
			'completed'         => __( 'Completado', 'super-mechanic' ),
			'cancelled'         => __( 'Cancelado', 'super-mechanic' ),
		);
	}

	/**
	 * Prepare paperwork data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function prepare_paperwork_data( array $data ) {
		return array(
			'paperwork_type'   => isset( $data['paperwork_type'] ) ? sanitize_key( $data['paperwork_type'] ) : '',
			'target_date'      => isset( $data['target_date'] ) ? $this->normalize_date( $data['target_date'] ) : null,
			'completed_date'   => isset( $data['completed_date'] ) ? $this->normalize_date( $data['completed_date'] ) : null,
			'assigned_user_id' => isset( $data['assigned_user_id'] ) ? absint( $data['assigned_user_id'] ) : 0,
			'status'           => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'pending',
			'notes'            => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
		);
	}

	/**
	 * Prepare item data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function prepare_item_data( array $data ) {
		$is_completed = ! empty( $data['is_completed'] ) ? 1 : 0;

		return array(
			'paperwork_id' => isset( $data['paperwork_id'] ) ? absint( $data['paperwork_id'] ) : 0,
			'item_key'     => isset( $data['item_key'] ) ? sanitize_key( $data['item_key'] ) : '',
			'item_label'   => isset( $data['item_label'] ) ? sanitize_text_field( $data['item_label'] ) : '',
			'is_required'  => ! empty( $data['is_required'] ) ? 1 : 0,
			'is_completed' => $is_completed,
			'completed_at' => $is_completed ? current_time( 'mysql' ) : null,
			'notes'        => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'sort_order'   => isset( $data['sort_order'] ) ? absint( $data['sort_order'] ) : 0,
		);
	}

	/**
	 * Normalize date.
	 *
	 * @param string $value Value.
	 * @return string|null
	 */
	protected function normalize_date( $value ) {
		$value = sanitize_text_field( $value );
		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? null : gmdate( 'Y-m-d', $timestamp );
	}
}
