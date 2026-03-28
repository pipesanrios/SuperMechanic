<?php
/**
 * Business list table.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Businesses;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders businesses list.
 */
class Business_List_Table extends \WP_List_Table {
	/**
	 * Service.
	 *
	 * @var Business_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Business_Service $service Service.
	 */
	public function __construct( Business_Service $service ) {
		$this->service = $service;

		parent::__construct(
			array(
				'singular' => 'sm_business',
				'plural'   => 'sm_businesses',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'id'         => __( 'ID', 'super-mechanic' ),
			'name'       => __( 'Nombre', 'super-mechanic' ),
			'slug'       => __( 'Slug', 'super-mechanic' ),
			'status'     => __( 'Estado', 'super-mechanic' ),
			'is_default' => __( 'Default', 'super-mechanic' ),
			'timezone'   => __( 'Timezone', 'super-mechanic' ),
			'currency'   => __( 'Moneda', 'super-mechanic' ),
			'updated_at' => __( 'Actualizado', 'super-mechanic' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string,array<int,string|bool>>
	 */
	protected function get_sortable_columns() {
		return array(
			'id'         => array( 'id', true ),
			'name'       => array( 'name', false ),
			'slug'       => array( 'slug', false ),
			'status'     => array( 'status', false ),
			'updated_at' => array( 'updated_at', false ),
		);
	}

	/**
	 * Name column with actions.
	 *
	 * @param array<string,mixed> $item Row.
	 * @return string
	 */
	public function column_name( $item ) {
		$id = absint( $item['id'] );

		$edit_url = add_query_arg(
			array(
				'page'   => 'super-mechanic-businesses',
				'action' => 'edit',
				'id'     => $id,
			),
			admin_url( 'admin.php' )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'       => 'super-mechanic-businesses',
					'action'     => 'sm_business_delete',
					'business_id' => $id,
				),
				admin_url( 'admin-post.php' )
			),
			'sm_business_delete_' . $id
		);

		$actions = array(
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'super-mechanic' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Eliminar', 'super-mechanic' ) . '</a>',
		);

		return '<strong>' . esc_html( (string) $item['name'] ) . '</strong>' . $this->row_actions( $actions );
	}

	/**
	 * Status column.
	 *
	 * @param array<string,mixed> $item Row.
	 * @return string
	 */
	public function column_status( $item ) {
		return esc_html( 'active' === (string) $item['status'] ? __( 'Activo', 'super-mechanic' ) : __( 'Inactivo', 'super-mechanic' ) );
	}

	/**
	 * Default flag column.
	 *
	 * @param array<string,mixed> $item Row.
	 * @return string
	 */
	public function column_is_default( $item ) {
		return absint( $item['is_default'] ) === 1 ? esc_html__( 'Sí', 'super-mechanic' ) : esc_html__( 'No', 'super-mechanic' );
	}

	/**
	 * Default column.
	 *
	 * @param array<string,mixed> $item Row.
	 * @param string              $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		$value = isset( $item[ $column_name ] ) ? $item[ $column_name ] : '';

		return esc_html( (string) $value );
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$status   = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'id';
		$order    = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';
		$paged    = $this->get_pagenum();
		$per_page = 20;

		$args = array(
			'search'   => $search,
			'status'   => $status,
			'orderby'  => $orderby,
			'order'    => $order,
			'page'     => $paged,
			'per_page' => $per_page,
		);

		$this->items = $this->service->get_businesses( $args );
		$total_items = $this->service->count_businesses(
			array(
				'search' => $search,
				'status' => $status,
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

