<?php
/**
 * Pre-delivery service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Pre_Delivery;

use Super_Mechanic\Processes\Process_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles pre-delivery business rules.
 */
class Pre_Delivery_Service {
	/**
	 * Repository.
	 *
	 * @var Pre_Delivery_Repository
	 */
	protected $repository;

	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * Constructor.
	 *
	 * @param Pre_Delivery_Repository|null $repository      Repository.
	 * @param Process_Service|null         $process_service Process service.
	 */
	public function __construct( Pre_Delivery_Repository $repository = null, Process_Service $process_service = null ) {
		$this->repository      = $repository ? $repository : new Pre_Delivery_Repository();
		$this->process_service = $process_service ? $process_service : new Process_Service();
	}

	/**
	 * Ensure row exists for pre-delivery process.
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

		if ( 'pre_delivery' !== $process['process_type'] ) {
			return new WP_Error( 'sm_not_pre_delivery_process', __( 'El proceso no corresponde a pre-entrega.', 'super-mechanic' ) );
		}

		$inserted = $this->repository->create(
			array(
				'process_id'                => $process_id,
				'insurance_required'        => 0,
				'insurance_completed'       => 0,
				'insurance_completed_at'    => null,
				'plate_required'            => 0,
				'plate_completed'           => 0,
				'plate_completed_at'        => null,
				'final_review_required'     => 0,
				'final_review_completed'    => 0,
				'final_review_completed_at' => null,
				'delivery_ready'            => 0,
				'delivery_ready_at'         => null,
				'assigned_user_id'          => 0,
				'notes'                     => '',
			)
		);

		if ( false === $inserted ) {
			return new WP_Error( 'sm_pre_delivery_insert_failed', __( 'No fue posible crear el registro de pre-entrega.', 'super-mechanic' ) );
		}

		return $this->repository->get_by_process_id( $process_id );
	}

	/**
	 * Get row by process.
	 *
	 * @param int $process_id Process ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_by_process( $process_id ) {
		return $this->ensure_record( $process_id );
	}

	/**
	 * Save pre-delivery data.
	 *
	 * @param int                  $process_id Process ID.
	 * @param array<string, mixed> $data       Data.
	 * @return bool|WP_Error
	 */
	public function save_pre_delivery( $process_id, array $data ) {
		$row = $this->ensure_record( $process_id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$prepared = $this->prepare_data( $data );
		$valid    = $this->validate_data( $prepared );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$prepared['insurance_completed_at']    = $this->resolve_toggle_datetime( ! empty( $row['insurance_completed_at'] ) ? $row['insurance_completed_at'] : null, $prepared['insurance_completed'] );
		$prepared['plate_completed_at']        = $this->resolve_toggle_datetime( ! empty( $row['plate_completed_at'] ) ? $row['plate_completed_at'] : null, $prepared['plate_completed'] );
		$prepared['final_review_completed_at'] = $this->resolve_toggle_datetime( ! empty( $row['final_review_completed_at'] ) ? $row['final_review_completed_at'] : null, $prepared['final_review_completed'] );
		$prepared['delivery_ready_at']         = $this->resolve_toggle_datetime( ! empty( $row['delivery_ready_at'] ) ? $row['delivery_ready_at'] : null, $prepared['delivery_ready'] );

		if ( ! $this->repository->update_by_process_id( $process_id, $prepared ) ) {
			return new WP_Error( 'sm_pre_delivery_update_failed', __( 'No fue posible guardar la pre-entrega.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Mark insurance completed.
	 *
	 * @param int $process_id Process ID.
	 * @return bool|WP_Error
	 */
	public function mark_insurance_completed( $process_id ) {
		return $this->save_pre_delivery( $process_id, array( 'insurance_completed' => 1 ) );
	}

	/**
	 * Mark plate completed.
	 *
	 * @param int $process_id Process ID.
	 * @return bool|WP_Error
	 */
	public function mark_plate_completed( $process_id ) {
		return $this->save_pre_delivery( $process_id, array( 'plate_completed' => 1 ) );
	}

	/**
	 * Mark final review completed.
	 *
	 * @param int $process_id Process ID.
	 * @return bool|WP_Error
	 */
	public function mark_final_review_completed( $process_id ) {
		return $this->save_pre_delivery( $process_id, array( 'final_review_completed' => 1 ) );
	}

	/**
	 * Mark delivery ready.
	 *
	 * @param int $process_id Process ID.
	 * @return bool|WP_Error
	 */
	public function mark_delivery_ready( $process_id ) {
		return $this->save_pre_delivery( $process_id, array( 'delivery_ready' => 1 ) );
	}

	/**
	 * Validate data.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return true|WP_Error
	 */
	public function validate_data( array $data ) {
		$errors = new WP_Error();

		if ( $data['assigned_user_id'] > 0 && ! get_user_by( 'id', $data['assigned_user_id'] ) ) {
			$errors->add( 'invalid_assigned_user', __( 'El responsable asignado no existe.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Prepare data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function prepare_data( array $data ) {
		return array(
			'insurance_required'     => ! empty( $data['insurance_required'] ) ? 1 : 0,
			'insurance_completed'    => ! empty( $data['insurance_completed'] ) ? 1 : 0,
			'plate_required'         => ! empty( $data['plate_required'] ) ? 1 : 0,
			'plate_completed'        => ! empty( $data['plate_completed'] ) ? 1 : 0,
			'final_review_required'  => ! empty( $data['final_review_required'] ) ? 1 : 0,
			'final_review_completed' => ! empty( $data['final_review_completed'] ) ? 1 : 0,
			'delivery_ready'         => ! empty( $data['delivery_ready'] ) ? 1 : 0,
			'assigned_user_id'       => isset( $data['assigned_user_id'] ) ? absint( $data['assigned_user_id'] ) : 0,
			'notes'                  => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
		);
	}

	/**
	 * Resolve completion datetime.
	 *
	 * @param string|null $current   Current datetime.
	 * @param int         $completed Completion flag.
	 * @return string|null
	 */
	protected function resolve_toggle_datetime( $current, $completed ) {
		if ( $completed ) {
			return $current ? $current : current_time( 'mysql' );
		}

		return null;
	}
}
