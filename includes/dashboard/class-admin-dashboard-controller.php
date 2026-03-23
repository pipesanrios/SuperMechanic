<?php
/**
 * Admin dashboard controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the admin dashboard.
 */
class Admin_Dashboard_Controller {
	/**
	 * Dashboard service.
	 *
	 * @var Dashboard_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Dashboard_Service|null $service Dashboard service.
	 */
	public function __construct( Dashboard_Service $service = null ) {
		$this->service = $service ? $service : new Dashboard_Service();
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'super-mechanic' ) );
		}

		$kpis            = $this->service->get_admin_kpis();
		$process_status  = $this->service->get_processes_by_status();
		$process_types   = $this->service->get_processes_by_type();
		$recent_processes = $this->service->get_recent_processes( 10 );
		$recent_vehicles = $this->service->get_recent_vehicles( 10 );
		$recent_clients  = $this->service->get_recent_clients( 10 );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Dashboard', 'super-mechanic' ) . '</h1>';
		echo '<div class="notice notice-info"><p>' . esc_html__( 'Resumen operativo general del sistema.', 'super-mechanic' ) . '</p></div>';
		echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">';
		$this->render_kpi_card( __( 'Clientes', 'super-mechanic' ), $kpis['total_clients'] );
		$this->render_kpi_card( __( 'Vehículos', 'super-mechanic' ), $kpis['total_vehicles'] );
		$this->render_kpi_card( __( 'Procesos', 'super-mechanic' ), $kpis['total_processes'] );
		$this->render_kpi_card( __( 'Procesos abiertos', 'super-mechanic' ), $kpis['open_processes'] );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Procesos por estado', 'super-mechanic' ) . '</h2>';
		$this->render_simple_summary_table( $process_status, __( 'Estado', 'super-mechanic' ) );

		echo '<h2>' . esc_html__( 'Procesos por tipo', 'super-mechanic' ) . '</h2>';
		$this->render_simple_summary_table( $process_types, __( 'Tipo', 'super-mechanic' ) );

		echo '<h2>' . esc_html__( 'Últimos procesos', 'super-mechanic' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Título', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehículo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cliente', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_processes ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay procesos recientes.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recent_processes as $process ) {
				echo '<tr>';
				echo '<td>' . esc_html( $process['id'] ) . '</td>';
				echo '<td>' . esc_html( $process['title'] ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $process['process_type'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->humanize_key( $process['status'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_vehicle_label( $process ) ) . '</td>';
				echo '<td>' . esc_html( $process['client_name'] ? $process['client_name'] : __( 'Sin asignar', 'super-mechanic' ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Últimos vehículos', 'super-mechanic' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Vehículo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Placa', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cliente', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_vehicles ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No hay vehículos recientes.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recent_vehicles as $vehicle ) {
				echo '<tr><td>' . esc_html( $vehicle['id'] ) . '</td><td>' . esc_html( $this->format_vehicle_label( $vehicle ) ) . '</td><td>' . esc_html( $vehicle['plate'] ) . '</td><td>' . esc_html( ! empty( $vehicle['client_name'] ) ? $vehicle['client_name'] : __( 'Sin asignar', 'super-mechanic' ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Últimos clientes', 'super-mechanic' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Nombre', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Email', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Teléfono', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_clients ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No hay clientes recientes.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recent_clients as $client ) {
				$name = trim( $client['first_name'] . ' ' . $client['last_name'] );
				echo '<tr><td>' . esc_html( $client['id'] ) . '</td><td>' . esc_html( $name ) . '</td><td>' . esc_html( $client['email'] ) . '</td><td>' . esc_html( $client['phone'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	protected function render_kpi_card( $label, $value ) {
		echo '<div class="postbox" style="min-width:180px;padding:16px;">';
		echo '<h2 style="margin:0 0 8px;">' . esc_html( $label ) . '</h2>';
		echo '<p style="font-size:24px;margin:0;">' . esc_html( $value ) . '</p>';
		echo '</div>';
	}

	protected function render_simple_summary_table( $rows, $label_header ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html( $label_header ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="2">' . esc_html__( 'Sin datos.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr><td>' . esc_html( $this->humanize_key( $row['label'] ) ) . '</td><td>' . esc_html( $row['total'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';
	}

	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	protected function format_vehicle_label( $vehicle ) {
		$make  = ! empty( $vehicle['brand'] ) ? $vehicle['brand'] : ( ! empty( $vehicle['make'] ) ? $vehicle['make'] : ( ! empty( $vehicle['vehicle_make'] ) ? $vehicle['vehicle_make'] : '' ) );
		$model = ! empty( $vehicle['model'] ) ? $vehicle['model'] : ( ! empty( $vehicle['vehicle_model'] ) ? $vehicle['vehicle_model'] : '' );
		$plate = ! empty( $vehicle['plate'] ) ? $vehicle['plate'] : ( ! empty( $vehicle['vehicle_plate'] ) ? $vehicle['vehicle_plate'] : '' );
		$label = trim( $make . ' ' . $model );
		if ( $plate ) {
			$label .= ' - ' . $plate;
		}

		return $label ? $label : __( 'Vehículo sin identificar', 'super-mechanic' );
	}
}
