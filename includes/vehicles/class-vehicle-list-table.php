<?php
/**
 * Vehicle list table.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Vehicles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the vehicles admin table.
 */
class Vehicle_List_Table extends \WP_List_Table {
	/**
	 * Vehicle service.
	 *
	 * @var Vehicle_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Vehicle_Service $service Vehicle service.
	 */
	public function __construct( Vehicle_Service $service ) {
		$this->service = $service;

		parent::__construct(
			array(
				'singular' => 'sm_vehicle',
				'plural'   => 'sm_vehicles',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'id'         => __( 'ID', 'super-mechanic' ),
			'client'     => __( 'Cliente', 'super-mechanic' ),
			'vin'        => __( 'VIN', 'super-mechanic' ),
			'plate'      => __( 'Placa', 'super-mechanic' ),
			'brand'      => __( 'Marca', 'super-mechanic' ),
			'model'      => __( 'Modelo', 'super-mechanic' ),
			'year'       => __( 'Year', 'super-mechanic' ),
			'color'      => __( 'Color', 'super-mechanic' ),
			'created_at' => __( 'Creado', 'super-mechanic' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	protected function get_sortable_columns() {
		return array(
			'id'         => array( 'id', false ),
			'client'     => array( 'client', false ),
			'vin'        => array( 'vin', false ),
			'plate'      => array( 'plate', false ),
			'brand'      => array( 'brand', false ),
			'model'      => array( 'model', false ),
			'year'       => array( 'year', false ),
			'color'      => array( 'color', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'bulk-delete' => __( 'Eliminar', 'super-mechanic' ),
		);
	}

	/**
	 * Render checkbox column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="vehicle_ids[]" value="%d" />', absint( $item['id'] ) );
	}

	/**
	 * Render client column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_client( $item ) {
		$client_name = isset( $item['client_name'] ) ? trim( (string) $item['client_name'] ) : '';

		if ( '' === $client_name || 0 === absint( $item['client_id'] ) ) {
			return esc_html__( 'Sin asignar', 'super-mechanic' );
		}

		return esc_html( $client_name );
	}

	/**
	 * Render brand column with row actions.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_brand( $item ) {
		$view_url = add_query_arg(
			array(
				'page'   => 'super-mechanic-vehicles',
				'action' => 'view',
				'id'     => absint( $item['id'] ),
			),
			admin_url( 'admin.php' )
		);

		$edit_url = add_query_arg(
			array(
				'page'   => 'super-mechanic-vehicles',
				'action' => 'edit',
				'id'     => absint( $item['id'] ),
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'super-mechanic-vehicles',
					'action' => 'delete',
					'id'     => absint( $item['id'] ),
				),
				admin_url( 'admin.php' )
			),
			'sm_delete_vehicle_' . absint( $item['id'] )
		);

		$actions = array(
			'view'   => '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'Ver', 'super-mechanic' ) . '</a>',
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'super-mechanic' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Eliminar', 'super-mechanic' ) . '</a>',
		);

		$model  = isset( $item['model'] ) ? trim( (string) $item['model'] ) : '';
		$plate  = isset( $item['plate'] ) ? trim( (string) $item['plate'] ) : '';
		$detail = '' !== $plate ? $plate : ( isset( $item['vin'] ) ? trim( (string) $item['vin'] ) : '' );

		$output  = '<strong>' . esc_html( (string) $item['brand'] ) . '</strong>';
		$output .= '<div class="sm-list-meta">' . esc_html( trim( $model . ( '' !== $detail ? ' · ' . $detail : '' ) ) ) . '</div>';

		return $output . $this->row_actions( $actions );
	}

	/**
	 * Render default column.
	 *
	 * @param array<string, mixed> $item        Row item.
	 * @param string               $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		$value = isset( $item[ $column_name ] ) ? $item[ $column_name ] : '';

		return esc_html( (string) $value );
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page = 20;
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order    = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';
		$paged    = $this->get_pagenum();

		$args = array(
			'search'   => $search,
			'orderby'  => $orderby,
			'order'    => $order,
			'page'     => $paged,
			'per_page' => $per_page,
		);

		$this->items = $this->service->get_vehicles( $args );

		$total_items = $this->service->count_vehicles(
			array(
				'search' => $search,
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}
}
