<?php
/**
 * Process list table.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Processes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the processes admin table.
 */
class Process_List_Table extends \WP_List_Table {
	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Process_Service $service Process service.
	 */
	public function __construct( Process_Service $service ) {
		$this->service = $service;

		parent::__construct(
			array(
				'singular' => 'sm_process',
				'plural'   => 'sm_processes',
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
			'cb'           => '<input type="checkbox" />',
			'id'           => __( 'ID', 'super-mechanic' ),
			'title'        => __( 'Title', 'super-mechanic' ),
			'process_type' => __( 'Tipo', 'super-mechanic' ),
			'status'       => __( 'Estado', 'super-mechanic' ),
			'vehicle'      => __( 'Vehicle', 'super-mechanic' ),
			'client'       => __( 'Cliente', 'super-mechanic' ),
			'opened_at'    => __( 'Apertura', 'super-mechanic' ),
			'due_date'     => __( 'Objetivo', 'super-mechanic' ),
			'created_at'   => __( 'Creado', 'super-mechanic' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	protected function get_sortable_columns() {
		return array(
			'id'           => array( 'id', false ),
			'title'        => array( 'title', false ),
			'process_type' => array( 'process_type', false ),
			'status'       => array( 'status', false ),
			'vehicle'      => array( 'vehicle', false ),
			'client'       => array( 'client', false ),
			'opened_at'    => array( 'opened_at', false ),
			'due_date'     => array( 'due_date', false ),
			'created_at'   => array( 'created_at', true ),
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
		return sprintf( '<input type="checkbox" name="process_ids[]" value="%d" />', absint( $item['id'] ) );
	}

	/**
	 * Render the title column with row actions.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_title( $item ) {
		$edit_url = add_query_arg(
			array(
				'page'   => 'super-mechanic-processes',
				'action' => 'edit',
				'id'     => absint( $item['id'] ),
			),
			admin_url( 'admin.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'super-mechanic-processes',
					'action' => 'delete',
					'id'     => absint( $item['id'] ),
				),
				admin_url( 'admin.php' )
			),
			'sm_delete_process_' . absint( $item['id'] )
		);

		$actions = array(
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'super-mechanic' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Eliminar', 'super-mechanic' ) . '</a>',
		);

		$output  = '<strong>' . esc_html( (string) $item['title'] ) . '</strong>';
		$output .= '<div class="sm-list-meta">#' . esc_html( (string) $item['id'] ) . '</div>';

		return $output . $this->row_actions( $actions );
	}

	/**
	 * Render process type column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_process_type( $item ) {
		$value = isset( $item['process_type'] ) ? (string) $item['process_type'] : '';

		return $this->render_badge( ucwords( str_replace( '_', ' ', $value ) ), 'primary' );
	}

	/**
	 * Render status column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_status( $item ) {
		$status = isset( $item['status'] ) ? (string) $item['status'] : '';
		$tone   = 'neutral';

		if ( in_array( $status, array( 'completed', 'paid', 'approved' ), true ) ) {
			$tone = 'success';
		} elseif ( in_array( $status, array( 'in_progress', 'active', 'open', 'issued' ), true ) ) {
			$tone = 'primary';
		} elseif ( in_array( $status, array( 'pending', 'draft', 'sent' ), true ) ) {
			$tone = 'warning';
		} elseif ( in_array( $status, array( 'cancelled', 'rejected', 'overdue', 'archived' ), true ) ) {
			$tone = 'danger';
		}

		$badge = $this->render_badge( ucwords( str_replace( '_', ' ', $status ) ), $tone );

		$quick_actions = $this->build_quick_status_actions( $item, $status );
		if ( empty( $quick_actions ) ) {
			return $badge;
		}

		return $badge . $this->row_actions( $quick_actions, true );
	}

	/**
	 * Render vehicle column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_vehicle( $item ) {
		$label = trim( sprintf( '%s %s', isset( $item['vehicle_make'] ) ? $item['vehicle_make'] : '', isset( $item['vehicle_model'] ) ? $item['vehicle_model'] : '' ) );

		if ( ! empty( $item['vehicle_plate'] ) ) {
			$label .= ' - ' . $item['vehicle_plate'];
		} elseif ( ! empty( $item['vehicle_vin'] ) ) {
			$label .= ' - ' . $item['vehicle_vin'];
		}

		return esc_html( '' !== trim( $label ) ? trim( $label ) : __( 'Unidentified vehicle', 'super-mechanic' ) );
	}

	/**
	 * Render client column.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return string
	 */
	protected function column_client( $item ) {
		$client_name = isset( $item['client_name'] ) ? trim( (string) $item['client_name'] ) : '';

		return esc_html( '' !== $client_name ? $client_name : __( 'Sin asignar', 'super-mechanic' ) );
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
	 * Render shared badge markup.
	 *
	 * @param string $label Badge label.
	 * @param string $tone  Badge tone.
	 * @return string
	 */
	protected function render_badge( $label, $tone ) {
		return '<span class="sm-badge sm-badge-' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Build quick status actions for a process row.
	 *
	 * @param array<string, mixed> $item           Process row.
	 * @param string               $current_status Current status.
	 * @return array<string, string>
	 */
	protected function build_quick_status_actions( $item, $current_status ) {
		$process_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;

		if ( $process_id <= 0 ) {
			return array();
		}

		$status_options = $this->service->get_status_options();
		$allowed_quick  = array( 'pending', 'in_progress', 'completed', 'cancelled' );
		$actions        = array();

		foreach ( $allowed_quick as $status_key ) {
			if ( $status_key === $current_status || ! isset( $status_options[ $status_key ] ) ) {
				continue;
			}

			$target_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'   => 'super-mechanic-processes',
						'action' => 'quick_status',
						'id'     => $process_id,
						'status' => $status_key,
					),
					admin_url( 'admin.php' )
				),
				'sm_quick_process_status_' . $process_id . '_' . $status_key
			);

			$actions[ 'status_' . $status_key ] = '<a href="' . esc_url( $target_url ) . '">' . esc_html( sprintf( __( 'Marcar: %s', 'super-mechanic' ), $status_options[ $status_key ] ) ) . '</a>';
		}

		return $actions;
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
		$status   = isset( $_REQUEST['filter_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_status'] ) ) : '';
		$type     = isset( $_REQUEST['filter_process_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_process_type'] ) ) : '';

		$args = array(
			'search'       => $search,
			'process_type' => $type,
			'status'       => $status,
			'orderby'      => $orderby,
			'order'        => $order,
			'page'         => $paged,
			'per_page'     => $per_page,
		);

		$this->items = $this->service->get_processes( $args );

		$total_items = $this->service->count_processes(
			array(
				'search'       => $search,
				'process_type' => $type,
				'status'       => $status,
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
