<?php
/**
 * Business repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Businesses;

use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates SQL for business entities.
 */
class Business_Repository {
	/**
	 * Get businesses with optional filters and pagination.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_businesses( array $args = array() ) {
		global $wpdb;

		$args   = $this->normalize_query_args( $args );
		$params = array();
		$where  = $this->build_where_clause( $args, $params );
		$order  = $this->build_order_clause( $args );

		$params[] = $args['per_page'];
		$params[] = ( $args['page'] - 1 ) * $args['per_page'];

		$sql = "SELECT id, slug, name, status, is_default, timezone, currency, branding_logo_attachment_id, primary_color, created_at, updated_at
			FROM {$this->get_table_name()}
			{$where}
			{$order}
			LIMIT %d OFFSET %d";

		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count businesses by filters.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return int
	 */
	public function count_businesses( array $args = array() ) {
		global $wpdb;

		$args   = $this->normalize_query_args( $args );
		$params = array();
		$where  = $this->build_where_clause( $args, $params );
		$sql    = "SELECT COUNT(id) FROM {$this->get_table_name()} {$where}";
		$query  = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );

		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Get business by id.
	 *
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>|null
	 */
	public function get_by_id( $business_id ) {
		global $wpdb;

		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return null;
		}

		$sql = "SELECT id, slug, name, status, is_default, timezone, currency, branding_logo_attachment_id, primary_color, created_at, updated_at
			FROM {$this->get_table_name()}
			WHERE id = %d
			LIMIT 1";
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $business_id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get business by slug.
	 *
	 * @param string $slug Business slug.
	 * @return array<string,mixed>|null
	 */
	public function get_by_slug( $slug ) {
		global $wpdb;

		$slug = sanitize_key( (string) $slug );
		if ( '' === $slug ) {
			return null;
		}

		$sql = "SELECT id, slug, name, status, is_default, timezone, currency, branding_logo_attachment_id, primary_color, created_at, updated_at
			FROM {$this->get_table_name()}
			WHERE slug = %s
			LIMIT 1";
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $slug ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert business.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return int
	 */
	public function insert( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->get_table_name(),
			$this->normalize_write_data( $data, true ),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return false === $inserted ? 0 : absint( $wpdb->insert_id );
	}

	/**
	 * Update business.
	 *
	 * @param int                 $business_id Business ID.
	 * @param array<string,mixed> $data        Data.
	 * @return bool
	 */
	public function update( $business_id, array $data ) {
		global $wpdb;

		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return false;
		}

		$updated = $wpdb->update(
			$this->get_table_name(),
			$this->normalize_write_data( $data, false ),
			array( 'id' => $business_id ),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Delete business.
	 *
	 * @param int $business_id Business ID.
	 * @return bool
	 */
	public function delete( $business_id ) {
		global $wpdb;

		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return false;
		}

		$deleted = $wpdb->delete(
			$this->get_table_name(),
			array( 'id' => $business_id ),
			array( '%d' )
		);

		return false !== $deleted;
	}

	/**
	 * Ensure single default business idempotently.
	 *
	 * @param string $name Default business name.
	 * @return bool
	 */
	public function ensure_default_business( $name ) {
		global $wpdb;

		$name = '' !== trim( (string) $name ) ? sanitize_text_field( (string) $name ) : 'Super Mechanic';
		$now  = current_time( 'mysql', true );
		$sql  = $wpdb->prepare(
			"INSERT INTO {$this->get_table_name()} (id, slug, name, status, is_default, timezone, currency, created_at, updated_at)
			VALUES (1, %s, %s, 'active', 1, 'UTC', 'USD', %s, %s)
			ON DUPLICATE KEY UPDATE
				slug = VALUES(slug),
				name = CASE WHEN name IS NULL OR name = '' THEN VALUES(name) ELSE name END,
				status = 'active',
				is_default = 1,
				updated_at = VALUES(updated_at)",
			'default',
			$name,
			$now,
			$now
		);

		return false !== $wpdb->query( $sql );
	}

	/**
	 * Get default business id.
	 *
	 * @return int
	 */
	public function get_default_business_id() {
		global $wpdb;

		$sql = "SELECT id FROM {$this->get_table_name()} WHERE is_default = 1 ORDER BY id ASC LIMIT 1";
		$id  = absint( $wpdb->get_var( $sql ) );

		return $id > 0 ? $id : 1;
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	protected function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['businesses'];
	}

	/**
	 * Normalize query args.
	 *
	 * @param array<string,mixed> $args Raw args.
	 * @return array<string,mixed>
	 */
	protected function normalize_query_args( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => '',
				'orderby'  => 'id',
				'order'    => 'DESC',
				'page'     => 1,
				'per_page' => 20,
			)
		);

		$args['search']   = sanitize_text_field( (string) $args['search'] );
		$args['status']   = sanitize_key( (string) $args['status'] );
		$args['orderby']  = sanitize_key( (string) $args['orderby'] );
		$args['order']    = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$args['page']     = max( 1, absint( $args['page'] ) );
		$args['per_page'] = max( 1, min( 100, absint( $args['per_page'] ) ) );

		return $args;
	}

	/**
	 * Build where clause.
	 *
	 * @param array<string,mixed> $args   Args.
	 * @param array<int,mixed>    $params Params.
	 * @return string
	 */
	protected function build_where_clause( array $args, array &$params ) {
		$clauses = array();

		if ( '' !== $args['search'] ) {
			$like      = '%' . $this->escape_like( $args['search'] ) . '%';
			$clauses[] = '(name LIKE %s OR slug LIKE %s)';
			$params[]  = $like;
			$params[]  = $like;
		}

		if ( in_array( $args['status'], array( 'active', 'inactive' ), true ) ) {
			$clauses[] = 'status = %s';
			$params[]  = $args['status'];
		}

		return empty( $clauses ) ? '' : 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Build order clause.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return string
	 */
	protected function build_order_clause( array $args ) {
		$allowed = array( 'id', 'name', 'slug', 'status', 'currency', 'timezone', 'updated_at', 'created_at' );
		$orderby = in_array( $args['orderby'], $allowed, true ) ? $args['orderby'] : 'id';

		return "ORDER BY {$orderby} {$args['order']}";
	}

	/**
	 * Normalize write data.
	 *
	 * @param array<string,mixed> $data      Raw data.
	 * @param bool                $is_insert True on insert.
	 * @return array<string,mixed>
	 */
	protected function normalize_write_data( array $data, $is_insert ) {
		$payload = array(
			'slug'                       => sanitize_key( isset( $data['slug'] ) ? (string) $data['slug'] : '' ),
			'name'                       => sanitize_text_field( isset( $data['name'] ) ? (string) $data['name'] : '' ),
			'status'                     => in_array( isset( $data['status'] ) ? (string) $data['status'] : 'active', array( 'active', 'inactive' ), true ) ? (string) $data['status'] : 'active',
			'is_default'                 => ! empty( $data['is_default'] ) ? 1 : 0,
			'timezone'                   => sanitize_text_field( isset( $data['timezone'] ) ? (string) $data['timezone'] : 'UTC' ),
			'currency'                   => sanitize_text_field( isset( $data['currency'] ) ? (string) $data['currency'] : 'USD' ),
			'branding_logo_attachment_id' => isset( $data['branding_logo_attachment_id'] ) ? absint( $data['branding_logo_attachment_id'] ) : 0,
			'primary_color'              => sanitize_text_field( isset( $data['primary_color'] ) ? (string) $data['primary_color'] : '' ),
			'updated_at'                 => current_time( 'mysql', true ),
		);

		if ( $payload['branding_logo_attachment_id'] <= 0 ) {
			$payload['branding_logo_attachment_id'] = null;
		}

		if ( $is_insert ) {
			$payload['created_at'] = $payload['updated_at'];
		}

		return $payload;
	}

	/**
	 * Escape text for LIKE.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function escape_like( $value ) {
		global $wpdb;

		return $wpdb->esc_like( $value );
	}
}

