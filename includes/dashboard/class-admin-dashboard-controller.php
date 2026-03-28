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

		$kpis             = $this->service->get_admin_kpis();
		$process_status   = $this->service->get_processes_by_status();
		$process_types    = $this->service->get_processes_by_type();
		$recent_processes = $this->service->get_recent_processes( 10 );
		$recent_vehicles  = $this->service->get_recent_vehicles( 10 );
		$recent_clients   = $this->service->get_recent_clients( 10 );

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Dashboard', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Vista general del sistema con foco en operación, carga actual y actividad reciente.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<span class="sm-badge sm-badge-primary">' . esc_html__( 'Centro operativo', 'super-mechanic' ) . '</span>';
		echo '</div>';

		echo '<div class="sm-notice-card"><strong>' . esc_html__( 'Resumen en vivo', 'super-mechanic' ) . '</strong><p class="sm-card-copy">' . esc_html__( 'Las métricas se calculan sobre la operación actual sin alterar los flujos existentes.', 'super-mechanic' ) . '</p></div>';

		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card( __( 'Clientes', 'super-mechanic' ), $kpis['total_clients'], __( 'Base total registrada', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-clients' ) );
		$this->render_kpi_card( __( 'Vehículos', 'super-mechanic' ), $kpis['total_vehicles'], __( 'Activos en seguimiento', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-vehicles' ) );
		$this->render_kpi_card( __( 'Procesos', 'super-mechanic' ), $kpis['total_processes'], __( 'Carga histórica consolidada', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-processes' ) );
		$this->render_kpi_card( __( 'Procesos abiertos', 'super-mechanic' ), $kpis['open_processes'], __( 'Carga operativa inmediata', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-processes', array( 'filter_status' => 'open' ) ) );
		echo '</div>';

		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Procesos por estado', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html( count( $process_status ) ) . ' ' . esc_html__( 'grupos', 'super-mechanic' ) . '</span></div>';
		$this->render_simple_summary_table( $process_status, __( 'Estado', 'super-mechanic' ), 'status' );
		echo '</section>';

		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Procesos por tipo', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html( count( $process_types ) ) . ' ' . esc_html__( 'grupos', 'super-mechanic' ) . '</span></div>';
		$this->render_simple_summary_table( $process_types, __( 'Tipo', 'super-mechanic' ), 'process_type' );
		echo '</section>';
		echo '</div>';

		echo '<section class="sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Últimos procesos', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html__( 'Prioridad alta', 'super-mechanic' ) . '</span></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Título', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Tipo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Estado', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehículo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cliente', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_processes ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No hay procesos recientes.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recent_processes as $process ) {
				echo '<tr>';
				echo '<td>' . esc_html( $process['id'] ) . '</td>';
				echo '<td><a href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-processes', array( 'action' => 'edit', 'id' => absint( $process['id'] ) ) ) ) . '">' . esc_html( $process['title'] ) . '</a></td>';
				echo '<td>' . esc_html( $this->humanize_key( $process['process_type'] ) ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_status_badge( $process['status'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_vehicle_label( $process ) ) . '</td>';
				echo '<td>' . esc_html( $process['client_name'] ? $process['client_name'] : __( 'Sin asignar', 'super-mechanic' ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Últimos vehículos', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Vehículo', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Placa', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Cliente', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_vehicles ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No hay vehículos recientes.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recent_vehicles as $vehicle ) {
				echo '<tr><td>' . esc_html( $vehicle['id'] ) . '</td><td><a href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-vehicles', array( 'action' => 'view', 'id' => absint( $vehicle['id'] ) ) ) ) . '">' . esc_html( $this->format_vehicle_label( $vehicle ) ) . '</a></td><td>' . esc_html( $vehicle['plate'] ) . '</td><td>' . esc_html( ! empty( $vehicle['client_name'] ) ? $vehicle['client_name'] : __( 'Sin asignar', 'super-mechanic' ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-card">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Últimos clientes', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Nombre', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Email', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Teléfono', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_clients ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No hay clientes recientes.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recent_clients as $client ) {
				$name = trim( $client['first_name'] . ' ' . $client['last_name'] );
				echo '<tr><td>' . esc_html( $client['id'] ) . '</td><td><a href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-clients', array( 'action' => 'view', 'id' => absint( $client['id'] ) ) ) ) . '">' . esc_html( $name ) . '</a></td><td>' . esc_html( $client['email'] ) . '</td><td>' . esc_html( $client['phone'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render a KPI card.
	 *
	 * @param string     $label    Card label.
	 * @param string|int $value    Card value.
	 * @param string     $footnote Optional footnote.
	 * @return void
	 */
	protected function render_kpi_card( $label, $value, $footnote = '', $url = '' ) {
		$tag = '' !== $url ? 'a' : 'article';
		echo '<' . $tag . ' class="sm-card sm-kpi-card"' . ( '' !== $url ? ' href="' . esc_url( $url ) . '" style="text-decoration:none;color:inherit;"' : '' ) . '>';
		echo '<span class="sm-kpi-label">' . esc_html( $label ) . '</span>';
		echo '<strong class="sm-kpi-value">' . esc_html( $value ) . '</strong>';
		if ( '' !== $footnote ) {
			echo '<p class="sm-kpi-footnote">' . esc_html( $footnote ) . '</p>';
		}
		echo '</' . $tag . '>';
	}

	/**
	 * Render a compact status badge.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	protected function render_status_badge( $status ) {
		$class = 'sm-badge sm-badge-neutral';

		if ( in_array( $status, array( 'open', 'in_progress', 'active' ), true ) ) {
			$class = 'sm-badge sm-badge-primary';
		} elseif ( in_array( $status, array( 'completed', 'paid', 'ready_for_delivery' ), true ) ) {
			$class = 'sm-badge sm-badge-success';
		} elseif ( in_array( $status, array( 'pending', 'draft', 'sent' ), true ) ) {
			$class = 'sm-badge sm-badge-warning';
		} elseif ( in_array( $status, array( 'cancelled', 'rejected', 'overdue' ), true ) ) {
			$class = 'sm-badge sm-badge-danger';
		}

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $this->humanize_key( $status ) ) . '</span>';
	}

	/**
	 * Render a compact summary table.
	 *
	 * @param array  $rows         Summary rows.
	 * @param string $label_header Column header.
	 * @return void
	 */
	protected function render_simple_summary_table( $rows, $label_header, $filter_key = '' ) {
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html( $label_header ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="2">' . esc_html__( 'Sin datos.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$label_markup = wp_kses_post( $this->render_status_badge( $row['label'] ) );
				if ( '' !== $filter_key ) {
					$label_markup = '<a href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-processes', array( 'filter_' . $filter_key => $row['label'] ) ) ) . '">' . $label_markup . '</a>';
				}
				echo '<tr><td>' . $label_markup . '</td><td>' . esc_html( $row['total'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Build admin page URLs.
	 *
	 * @param string               $page_slug Page slug.
	 * @param array<string, mixed> $args      Extra args.
	 * @return string
	 */
	protected function get_admin_page_url( $page_slug, $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => $page_slug,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Humanize an internal key.
	 *
	 * @param string $value Raw key.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		return ucwords( str_replace( '_', ' ', (string) $value ) );
	}

	/**
	 * Format a vehicle label.
	 *
	 * @param array<string, mixed> $vehicle Vehicle-like row.
	 * @return string
	 */
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
