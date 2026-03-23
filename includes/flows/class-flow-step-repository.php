<?php
/**
 * Flow step repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Flows;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Handles flow step persistence.
 */
class Flow_Step_Repository {
	/**
	 * Get the flow steps table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['flow_steps'];
	}

	/**
	 * Get a step by ID.
	 *
	 * @param int $id Step ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;

		$query  = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE id = %d LIMIT 1",
			absint( $id )
		);
		$result = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Get steps by flow ID.
	 *
	 * @param int  $flow_id     Flow ID.
	 * @param bool $only_active Whether to include only active steps.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_flow_id( $flow_id, $only_active = false ) {
		global $wpdb;

		$where = $only_active ? 'AND is_active = 1' : '';
		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE flow_id = %d {$where} ORDER BY step_order ASC, id ASC",
			absint( $flow_id )
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert a step.
	 *
	 * @param array<string, mixed> $data Step data.
	 * @return int|false
	 */
	public function insert( $data ) {
		global $wpdb;

		$now                = current_time( 'mysql' );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$result = $wpdb->insert( $this->get_table_name(), $data, $this->get_formats_for_data( $data ) );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a step.
	 *
	 * @param int                  $id   Step ID.
	 * @param array<string, mixed> $data Step data.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->get_table_name(),
			$data,
			array( 'id' => absint( $id ) ),
			$this->get_formats_for_data( $data ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a step.
	 *
	 * @param int $id Step ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete( $this->get_table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Delete all steps by flow ID.
	 *
	 * @param int $flow_id Flow ID.
	 * @return bool
	 */
	public function delete_by_flow_id( $flow_id ) {
		global $wpdb;

		$result = $wpdb->delete( $this->get_table_name(), array( 'flow_id' => absint( $flow_id ) ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Get the initial step for a flow.
	 *
	 * @param int $flow_id Flow ID.
	 * @return array<string, mixed>|null
	 */
	public function get_initial_step( $flow_id ) {
		global $wpdb;

		$query  = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE flow_id = %d AND is_initial = 1 ORDER BY step_order ASC, id ASC LIMIT 1",
			absint( $flow_id )
		);
		$result = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Get a step by key inside a flow.
	 *
	 * @param int    $flow_id  Flow ID.
	 * @param string $step_key Step key.
	 * @return array<string, mixed>|null
	 */
	public function get_step_by_key( $flow_id, $step_key ) {
		global $wpdb;

		$query  = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE flow_id = %d AND step_key = %s LIMIT 1",
			absint( $flow_id ),
			$step_key
		);
		$result = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Get a step by ID constrained to a flow.
	 *
	 * @param int $flow_id Flow ID.
	 * @param int $step_id Step ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_flow_and_id( $flow_id, $step_id ) {
		global $wpdb;

		$query  = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE flow_id = %d AND id = %d LIMIT 1",
			absint( $flow_id ),
			absint( $step_id )
		);
		$result = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Build formats for the provided keys.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( $data ) {
		$format_map = array(
			'flow_id'            => '%d',
			'step_key'           => '%s',
			'step_label'         => '%s',
			'step_order'         => '%d',
			'step_type'          => '%s',
			'is_required'        => '%d',
			'is_initial'         => '%d',
			'is_final'           => '%d',
			'requires_approval'  => '%d',
			'requires_note'      => '%d',
			'is_active'          => '%d',
			'metadata'           => '%s',
			'created_at'         => '%s',
			'updated_at'         => '%s',
		);
		$formats    = array();

		foreach ( array_keys( $data ) as $key ) {
			if ( isset( $format_map[ $key ] ) ) {
				$formats[] = $format_map[ $key ];
			}
		}

		return $formats;
	}
}
