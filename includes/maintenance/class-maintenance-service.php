<?php
/**
 * Maintenance service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Maintenance;

use Super_Mechanic\Processes\Process_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles maintenance business rules.
 */
class Maintenance_Service {
	protected $repository;
	protected $part_repository;
	protected $labor_repository;
	protected $process_service;

	public function __construct( Maintenance_Repository $repository = null, Maintenance_Part_Repository $part_repository = null, Maintenance_Labor_Repository $labor_repository = null, Process_Service $process_service = null ) {
		$this->repository       = $repository ? $repository : new Maintenance_Repository();
		$this->part_repository  = $part_repository ? $part_repository : new Maintenance_Part_Repository();
		$this->labor_repository = $labor_repository ? $labor_repository : new Maintenance_Labor_Repository();
		$this->process_service  = $process_service ? $process_service : new Process_Service();
	}

	public function create_maintenance( $process_id ) {
		$process_id = absint( $process_id );
		$existing   = $this->repository->get_by_process_id( $process_id );

		if ( $existing ) {
			return $existing;
		}

		$process = $this->process_service->get_process( $process_id );
		if ( ! $process ) {
			return new WP_Error( 'sm_process_not_found', __( 'El proceso no existe.', 'super-mechanic' ) );
		}
		if ( 'maintenance' !== $process['process_type'] ) {
			return new WP_Error( 'sm_not_maintenance_process', __( 'El proceso no corresponde al módulo de mantenimiento.', 'super-mechanic' ) );
		}

		$inserted = $this->repository->insert(
			array(
				'process_id'      => $process_id,
				'diagnosis'       => '',
				'client_approved' => 0,
				'approved_at'     => null,
				'mechanic_id'     => 0,
				'estimated_hours' => 0,
			)
		);

		if ( false === $inserted ) {
			return new WP_Error( 'sm_maintenance_insert_failed', __( 'No fue posible crear el registro de mantenimiento.', 'super-mechanic' ) );
		}

		return $this->repository->get_by_id( $inserted );
	}

	public function get_maintenance_by_process( $process_id ) {
		return $this->repository->get_by_process_id( $process_id );
	}

	public function update_maintenance( $process_id, array $data ) {
		$maintenance = $this->create_maintenance( $process_id );
		if ( is_wp_error( $maintenance ) ) {
			return $maintenance;
		}

		$prepared = array(
			'diagnosis'       => isset( $data['diagnosis'] ) ? sanitize_textarea_field( $data['diagnosis'] ) : '',
			'client_approved' => ! empty( $data['client_approved'] ) ? 1 : 0,
			'approved_at'     => isset( $data['approved_at'] ) ? $this->normalize_datetime_value( $data['approved_at'] ) : null,
			'mechanic_id'     => isset( $data['mechanic_id'] ) ? absint( $data['mechanic_id'] ) : 0,
			'estimated_hours' => isset( $data['estimated_hours'] ) ? $this->normalize_decimal( $data['estimated_hours'] ) : 0,
		);

		if ( $prepared['mechanic_id'] > 0 && ! get_user_by( 'id', $prepared['mechanic_id'] ) ) {
			return new WP_Error( 'sm_invalid_mechanic', __( 'El mecánico seleccionado no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->update( absint( $maintenance['id'] ), $prepared ) ) {
			return new WP_Error( 'sm_maintenance_update_failed', __( 'No fue posible actualizar los datos de mantenimiento.', 'super-mechanic' ) );
		}

		return true;
	}

	public function add_part( $maintenance_id, array $data ) {
		$maintenance_id = absint( $maintenance_id );
		$maintenance    = $this->repository->get_by_id( $maintenance_id );

		if ( ! $maintenance ) {
			return new WP_Error( 'sm_maintenance_not_found', __( 'El mantenimiento no existe.', 'super-mechanic' ) );
		}

		$quantity   = $this->normalize_decimal( isset( $data['quantity'] ) ? $data['quantity'] : 0 );
		$unit_price = $this->normalize_decimal( isset( $data['unit_price'] ) ? $data['unit_price'] : 0 );
		$part_name  = isset( $data['part_name'] ) ? sanitize_text_field( $data['part_name'] ) : '';

		if ( '' === $part_name ) {
			return new WP_Error( 'sm_part_name_required', __( 'El nombre del repuesto es obligatorio.', 'super-mechanic' ) );
		}
		if ( $quantity <= 0 ) {
			return new WP_Error( 'sm_invalid_part_quantity', __( 'La cantidad del repuesto debe ser mayor que cero.', 'super-mechanic' ) );
		}

		$inserted = $this->part_repository->insert(
			array(
				'maintenance_id' => $maintenance_id,
				'part_name'      => $part_name,
				'quantity'       => $quantity,
				'unit_price'     => $unit_price,
				'total_price'    => round( $quantity * $unit_price, 2 ),
				'notes'          => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			)
		);

		if ( false === $inserted ) {
			return new WP_Error( 'sm_part_insert_failed', __( 'No fue posible agregar el repuesto.', 'super-mechanic' ) );
		}

		return $inserted;
	}

	public function remove_part( $part_id ) {
		if ( ! $this->part_repository->delete( $part_id ) ) {
			return new WP_Error( 'sm_part_delete_failed', __( 'No fue posible eliminar el repuesto.', 'super-mechanic' ) );
		}

		return true;
	}

	public function add_labor( $maintenance_id, array $data ) {
		$maintenance_id = absint( $maintenance_id );
		$maintenance    = $this->repository->get_by_id( $maintenance_id );

		if ( ! $maintenance ) {
			return new WP_Error( 'sm_maintenance_not_found', __( 'El mantenimiento no existe.', 'super-mechanic' ) );
		}

		$description = isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : '';
		$hours       = $this->normalize_decimal( isset( $data['hours'] ) ? $data['hours'] : 0 );
		$hour_rate   = $this->normalize_decimal( isset( $data['hour_rate'] ) ? $data['hour_rate'] : 0 );

		if ( '' === $description ) {
			return new WP_Error( 'sm_labor_description_required', __( 'La descripción de mano de obra es obligatoria.', 'super-mechanic' ) );
		}
		if ( $hours <= 0 ) {
			return new WP_Error( 'sm_invalid_labor_hours', __( 'Las horas de mano de obra deben ser mayores que cero.', 'super-mechanic' ) );
		}

		$inserted = $this->labor_repository->insert(
			array(
				'maintenance_id' => $maintenance_id,
				'description'    => $description,
				'hours'          => $hours,
				'hour_rate'      => $hour_rate,
				'total_price'    => round( $hours * $hour_rate, 2 ),
			)
		);

		if ( false === $inserted ) {
			return new WP_Error( 'sm_labor_insert_failed', __( 'No fue posible agregar la mano de obra.', 'super-mechanic' ) );
		}

		return $inserted;
	}

	public function remove_labor( $labor_id ) {
		if ( ! $this->labor_repository->delete( $labor_id ) ) {
			return new WP_Error( 'sm_labor_delete_failed', __( 'No fue posible eliminar la mano de obra.', 'super-mechanic' ) );
		}

		return true;
	}

	public function get_parts( $maintenance_id ) {
		return $this->part_repository->get_by_maintenance_id( $maintenance_id );
	}

	public function get_labor( $maintenance_id ) {
		return $this->labor_repository->get_by_maintenance_id( $maintenance_id );
	}

	public function calculate_total_parts( $maintenance_id ) {
		$total = 0.0;
		foreach ( $this->get_parts( $maintenance_id ) as $part ) {
			$total += (float) $part['total_price'];
		}

		return round( $total, 2 );
	}

	public function calculate_total_labor( $maintenance_id ) {
		$total = 0.0;
		foreach ( $this->get_labor( $maintenance_id ) as $row ) {
			$total += (float) $row['total_price'];
		}

		return round( $total, 2 );
	}

	public function calculate_total_service( $maintenance_id ) {
		return round( $this->calculate_total_parts( $maintenance_id ) + $this->calculate_total_labor( $maintenance_id ), 2 );
	}

	public function get_quote_source_data( $process_id ) {
		$maintenance = $this->create_maintenance( $process_id );
		if ( is_wp_error( $maintenance ) ) {
			return $maintenance;
		}

		$maintenance_id = absint( $maintenance['id'] );

		return array(
			'maintenance' => $maintenance,
			'parts'       => $this->get_parts( $maintenance_id ),
			'labor'       => $this->get_labor( $maintenance_id ),
			'parts_total' => $this->calculate_total_parts( $maintenance_id ),
			'labor_total' => $this->calculate_total_labor( $maintenance_id ),
			'grand_total' => $this->calculate_total_service( $maintenance_id ),
		);
	}

	protected function normalize_decimal( $value ) {
		return round( (float) str_replace( ',', '.', (string) $value ), 2 );
	}

	protected function normalize_datetime_value( $value ) {
		$value = sanitize_text_field( $value );
		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );
		return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
