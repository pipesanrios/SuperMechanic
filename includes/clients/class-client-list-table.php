<?php
/**
 * Client list table.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Clients;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the clients admin table.
 */
class Client_List_Table extends \WP_List_Table {
	/**
	 * Client service.
	 *
	 * @var Client_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Client_Service $service Client service.
	 */
	public function __construct( Client_Service $service ) {
		$this->service = $service;

		parent::__construct(
			array(
				'singular' => 'sm_client',
				'plural'   => 'sm_clients',
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
			'cb'          => '<input type="checkbox" />',
			'id'          => __( 'ID', 'super-mechanic' ),
			'first_name'  => __( 'Nombre', 'super-mechanic' ),
			'last_name'   => __( 'Apellido', 'super-mechanic' ),
			'email'       => __( 'Email', 'super-mechanic' ),
			'phone'       => __( 'Teléfono', 'super-mechanic' ),
			'document_id' => __( 'Documento', 'super-mechanic' ),
			'created_at'  => __( 'Creado', 'super-mechanic' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	protected function get_sortable_columns() {
		return array(
			'id'          => array( 'id', false ),
			'first_name'  => array( 'first_name', false ),
			'last_name'   => array( 'last_name', false ),
			'email'       => array( 'email', false ),
			'phone'       => array( 'phone', false ),
			'document_id' => array( 'document_id', false ),
			'created_at'  => array( 'created_at', true ),
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
		return sprintf( '<input type="checkbox" name="client_ids[]" value="%d" />', absint( $item['id'] ) );
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
	 * Render first name column with row actions.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_first_name( $item ) {
		$edit_url = add_query_arg(
			array(
				'page'   => 'super-mechanic-clients',
				'action' => 'edit',
				'id'     => absint( $item['id'] ),
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'super-mechanic-clients',
					'action' => 'delete',
					'id'     => absint( $item['id'] ),
				),
				admin_url( 'admin.php' )
			),
			'sm_delete_client_' . absint( $item['id'] )
		);

		$actions = array(
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'super-mechanic' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Eliminar', 'super-mechanic' ) . '</a>',
		);

		return esc_html( (string) $item['first_name'] ) . $this->row_actions( $actions );
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

		$this->items = $this->service->get_clients( $args );

		$total_items = $this->service->count_clients(
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
