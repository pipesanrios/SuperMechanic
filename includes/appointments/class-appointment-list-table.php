<?php
/**
 * Appointment list table.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Appointments;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders appointments admin table.
 */
class Appointment_List_Table extends \WP_List_Table {
	/**
	 * Service.
	 *
	 * @var Appointment_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Appointment_Service $service Service.
	 */
	public function __construct( Appointment_Service $service ) {
		$this->service = $service;

		parent::__construct(
			array(
				'singular' => 'sm_appointment',
				'plural'   => 'sm_appointments',
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
			'cb'                 => '<input type="checkbox" />',
			'id'                 => __( 'ID', 'super-mechanic' ),
			'appointment_date'   => __( 'Fecha', 'super-mechanic' ),
			'start_at'           => __( 'Inicio', 'super-mechanic' ),
			'client_name'        => __( 'Cliente', 'super-mechanic' ),
			'vehicle_label'      => __( 'Vehiculo', 'super-mechanic' ),
			'process_id'         => __( 'Proceso', 'super-mechanic' ),
			'mechanic_name'      => __( 'Mecanico', 'super-mechanic' ),
			'appointment_status' => __( 'Estado', 'super-mechanic' ),
			'updated_at'         => __( 'Actualizado', 'super-mechanic' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array<string,array<int,string|bool>>
	 */
	protected function get_sortable_columns() {
		return array(
			'id'                 => array( 'id', false ),
			'appointment_date'   => array( 'appointment_date', true ),
			'start_at'           => array( 'start_at', true ),
			'assigned_to'        => array( 'assigned_to', false ),
			'appointment_status' => array( 'appointment_status', false ),
			'updated_at'         => array( 'updated_at', false ),
		);
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions() {
		return array(
			'bulk-delete' => __( 'Eliminar', 'super-mechanic' ),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string,mixed> $item Row item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="appointment_ids[]" value="%d" />', absint( $item['id'] ) );
	}

	/**
	 * ID column with row actions.
	 *
	 * @param array<string,mixed> $item Row item.
	 * @return string
	 */
	protected function column_id( $item ) {
		$id         = absint( $item['id'] );
		$edit_url   = add_query_arg(
			array(
				'page'   => 'super-mechanic-appointments',
				'action' => 'edit',
				'id'     => $id,
			),
			admin_url( 'admin.php' )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'super-mechanic-appointments',
					'action' => 'delete',
					'id'     => $id,
				),
				admin_url( 'admin.php' )
			),
			'sm_delete_appointment_' . $id
		);
		$actions    = array(
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'super-mechanic' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Eliminar', 'super-mechanic' ) . '</a>',
		);

		return '<strong>#' . esc_html( $id ) . '</strong>' . $this->row_actions( $actions );
	}

	/**
	 * Vehicle label column.
	 *
	 * @param array<string,mixed> $item Row item.
	 * @return string
	 */
	protected function column_vehicle_label( $item ) {
		$label = trim(
			sprintf(
				'%s %s',
				isset( $item['vehicle_make'] ) ? (string) $item['vehicle_make'] : '',
				isset( $item['vehicle_model'] ) ? (string) $item['vehicle_model'] : ''
			)
		);

		if ( ! empty( $item['vehicle_plate'] ) ) {
			$label .= ' - ' . (string) $item['vehicle_plate'];
		} elseif ( ! empty( $item['vehicle_vin'] ) ) {
			$label .= ' - ' . (string) $item['vehicle_vin'];
		}

		return esc_html( '' !== trim( $label ) ? $label : __( 'Vehiculo sin identificar', 'super-mechanic' ) );
	}

	/**
	 * Process column.
	 *
	 * @param array<string,mixed> $item Row item.
	 * @return string
	 */
	protected function column_process_id( $item ) {
		$process_id = isset( $item['process_id'] ) ? absint( $item['process_id'] ) : 0;
		if ( $process_id <= 0 ) {
			return esc_html__( 'Sin proceso', 'super-mechanic' );
		}

		$label = ! empty( $item['process_title'] ) ? (string) $item['process_title'] : '';
		$text  = '#' . $process_id;
		if ( '' !== $label ) {
			$text .= ' - ' . $label;
		}

		return esc_html( $text );
	}

	/**
	 * Status column.
	 *
	 * @param array<string,mixed> $item Row item.
	 * @return string
	 */
	protected function column_appointment_status( $item ) {
		return esc_html( ucwords( str_replace( '_', ' ', (string) $item['appointment_status'] ) ) );
	}

	/**
	 * Default column.
	 *
	 * @param array<string,mixed> $item        Row item.
	 * @param string              $column_name Column name.
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
		$per_page           = 20;
		$search             = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$appointment_status = isset( $_REQUEST['filter_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['filter_status'] ) ) : '';
		$assigned_to        = isset( $_REQUEST['filter_assigned_to'] ) ? absint( wp_unslash( $_REQUEST['filter_assigned_to'] ) ) : 0;
		$date_from          = isset( $_REQUEST['filter_date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_date_from'] ) ) : '';
		$date_to            = isset( $_REQUEST['filter_date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_date_to'] ) ) : '';
		$orderby            = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'start_at';
		$order              = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';
		$paged              = $this->get_pagenum();
		$args               = array(
			'search'             => $search,
			'appointment_status' => $appointment_status,
			'assigned_to'        => $assigned_to,
			'date_from'          => $date_from,
			'date_to'            => $date_to,
			'orderby'            => $orderby,
			'order'              => $order,
			'page'               => $paged,
			'per_page'           => $per_page,
		);

		$this->items = $this->service->get_appointments( $args );

		$total_items = $this->service->count_appointments(
			array(
				'search'             => $search,
				'appointment_status' => $appointment_status,
				'assigned_to'        => $assigned_to,
				'date_from'          => $date_from,
				'date_to'            => $date_to,
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
