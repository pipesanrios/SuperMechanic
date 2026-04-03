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
	 * Workload service.
	 *
	 * @var Workload_Service
	 */
	protected $workload_service;

	/**
	 * Constructor.
	 *
	 * @param Dashboard_Service|null $service Dashboard service.
	 * @param Workload_Service|null  $workload_service Workload service.
	 */
	public function __construct( Dashboard_Service $service = null, Workload_Service $workload_service = null ) {
		$this->service          = $service ? $service : new Dashboard_Service();
		$this->workload_service = $workload_service ? $workload_service : new Workload_Service();
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		$kpis             = $this->service->get_admin_kpis();
		$process_status   = $this->service->get_processes_by_status();
		$process_types    = $this->service->get_processes_by_type();
		$recent_processes = $this->service->get_recent_processes( 10 );
		$recent_vehicles  = $this->service->get_recent_vehicles( 10 );
		$recent_clients   = $this->service->get_recent_clients( 10 );
		$today_appointments = $this->service->get_today_appointments( 8 );
		$upcoming_appointments = $this->service->get_upcoming_appointments( 7, 8 );
		$selected_workload_user_id = isset( $_GET['workload_user_id'] ) ? absint( wp_unslash( $_GET['workload_user_id'] ) ) : get_current_user_id();
		if ( $selected_workload_user_id <= 0 ) {
			$selected_workload_user_id = get_current_user_id();
		}
		$workload = $this->workload_service->get_user_workload(
			$selected_workload_user_id,
			array(
				'upcoming_days'    => 7,
				'max_scan'         => 250,
				'limit_per_bucket' => 12,
			)
		);
		$global_summary = $this->workload_service->get_global_operational_summary(
			isset( $workload['meta']['business_id'] ) ? absint( $workload['meta']['business_id'] ) : 0
		);
		$automation_flags = $this->workload_service->get_operational_automation_flags(
			isset( $workload['meta']['business_id'] ) ? absint( $workload['meta']['business_id'] ) : 0,
			$selected_workload_user_id
		);
		$escalation_state = $this->workload_service->get_operational_escalation_state(
			isset( $workload['meta']['business_id'] ) ? absint( $workload['meta']['business_id'] ) : 0,
			$selected_workload_user_id
		);
		$operational_recommendations = $this->workload_service->get_operational_recommendations(
			isset( $workload['meta']['business_id'] ) ? absint( $workload['meta']['business_id'] ) : 0,
			$selected_workload_user_id
		);
		$operational_assignments = $this->workload_service->get_operational_assignments(
			isset( $workload['meta']['business_id'] ) ? absint( $workload['meta']['business_id'] ) : 0
		);
		$automation_console = $this->workload_service->get_operational_automation_console(
			isset( $workload['meta']['business_id'] ) ? absint( $workload['meta']['business_id'] ) : 0,
			$selected_workload_user_id
		);

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Dashboard', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'System overview focused on operations, current workload, and recent activity.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<span class="sm-badge sm-badge-primary">' . esc_html__( 'Operations hub', 'super-mechanic' ) . '</span>';
		echo '</div>';

		echo '<div class="sm-notice-card"><strong>' . esc_html__( 'Live summary', 'super-mechanic' ) . '</strong><p class="sm-card-copy">' . esc_html__( 'Metrics are calculated from current operations without altering existing flows.', 'super-mechanic' ) . '</p></div>';
		$this->render_global_operational_summary( $global_summary );
		$this->render_operational_escalation_state( $escalation_state );
		$this->render_operational_automation_flags( $automation_flags );
		$this->render_operational_recommendations( $operational_recommendations );
		$this->render_operational_assignments( $operational_assignments );
		$this->render_operational_automation_console( $automation_console );

		echo '<section class="sm-card sm-section sm-quick-actions-card">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Quick actions', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Operational shortcuts', 'super-mechanic' ) . '</span></div>';
		echo '<div class="sm-page-actions">';
		echo '<a class="button button-primary" href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-processes', array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Create process', 'super-mechanic' ) . '</a>';
		echo '<a class="button button-secondary" href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-processes', array( 'filter_process_type' => 'maintenance' ) ) ) . '">' . esc_html__( 'Open maintenance', 'super-mechanic' ) . '</a>';
		echo '<a class="button button-secondary" href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-processes', array( 'action' => 'new', 'process_type' => 'maintenance' ) ) ) . '">' . esc_html__( 'Create quote', 'super-mechanic' ) . '</a>';
		echo '<a class="button button-secondary" href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-financial-invoices' ) ) . '">' . esc_html__( 'Create invoice', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Quote and invoice actions open the fastest operational route in current architecture (process tab or finance center).', 'super-mechanic' ) . '</p>';
		echo '</section>';
		$this->render_workload_section( $workload );

		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card( __( 'Clients', 'super-mechanic' ), $kpis['total_clients'], __( 'Total registered base', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-clients' ) );
		$this->render_kpi_card( __( 'Vehicles', 'super-mechanic' ), $kpis['total_vehicles'], __( 'Active in tracking', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-vehicles' ) );
		$this->render_kpi_card( __( 'Processes', 'super-mechanic' ), $kpis['total_processes'], __( 'Consolidated historical workload', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-processes' ) );
		$this->render_kpi_card( __( 'Open processes', 'super-mechanic' ), $kpis['open_processes'], __( 'Immediate operational workload', 'super-mechanic' ), $this->get_admin_page_url( 'super-mechanic-processes', array( 'filter_status' => 'open' ) ) );
		echo '</div>';

		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Processes by status', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html( count( $process_status ) ) . ' ' . esc_html__( 'groups', 'super-mechanic' ) . '</span></div>';
		$this->render_simple_summary_table( $process_status, __( 'Status', 'super-mechanic' ), 'status' );
		echo '</section>';

		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Processes by type', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html( count( $process_types ) ) . ' ' . esc_html__( 'groups', 'super-mechanic' ) . '</span></div>';
		$this->render_simple_summary_table( $process_types, __( 'Type', 'super-mechanic' ), 'process_type' );
		echo '</section>';
		echo '</div>';

		echo '<section class="sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Latest processes', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html__( 'High priority', 'super-mechanic' ) . '</span></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Title', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Actions', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_processes ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No recent processes found.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recent_processes as $process ) {
				$process_id = absint( $process['id'] );
				echo '<tr>';
				echo '<td>' . esc_html( $process_id ) . '</td>';
				echo '<td><a href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-processes', array( 'action' => 'edit', 'id' => $process_id ) ) ) . '">' . esc_html( $process['title'] ) . '</a></td>';
				echo '<td>' . esc_html( $this->humanize_key( $process['process_type'] ) ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_status_badge( $process['status'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_vehicle_label( $process ) ) . '</td>';
				echo '<td>' . esc_html( $process['client_name'] ? $process['client_name'] : __( 'Unassigned', 'super-mechanic' ) ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_process_quick_links( $process_id, isset( $process['process_type'] ) ? (string) $process['process_type'] : '' ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Today appointments', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html( count( $today_appointments ) ) . '</span></div>';
		$this->render_appointments_table( $today_appointments, __( 'No appointments for today.', 'super-mechanic' ) );
		echo '</section>';

		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Upcoming appointments', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( '7 days', 'super-mechanic' ) . '</span></div>';
		$this->render_appointments_table( $upcoming_appointments, __( 'No upcoming appointments in the next 7 days.', 'super-mechanic' ) );
		echo '</section>';
		echo '</div>';

		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Latest vehicles', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Plate', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_vehicles ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No recent vehicles found.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recent_vehicles as $vehicle ) {
				echo '<tr><td>' . esc_html( $vehicle['id'] ) . '</td><td><a href="' . esc_url( $this->get_admin_page_url( 'super-mechanic-vehicles', array( 'action' => 'view', 'id' => absint( $vehicle['id'] ) ) ) ) . '">' . esc_html( $this->format_vehicle_label( $vehicle ) ) . '</a></td><td>' . esc_html( $vehicle['plate'] ) . '</td><td>' . esc_html( ! empty( $vehicle['client_name'] ) ? $vehicle['client_name'] : __( 'Unassigned', 'super-mechanic' ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-card">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Latest clients', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>ID</th><th>' . esc_html__( 'Name', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Email', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Phone', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent_clients ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No recent clients found.', 'super-mechanic' ) . '</td></tr>';
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
	 * Render a KrI card.
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
			echo '<tr><td colspan="2">' . esc_html__( 'No data.', 'super-mechanic' ) . '</td></tr>';
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
	 * Render compact appointments table.
	 *
	 * @param array<int, array<string, mixed>> $rows Appointment rows.
	 * @param string                            $empty_message Empty state.
	 * @return void
	 */
	protected function render_appointments_table( $rows, $empty_message ) {
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Time', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Client', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Mechanic', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Action', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="6">' . esc_html( $empty_message ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$start_label = $this->format_datetime_label( isset( $row['start_at'] ) ? (string) $row['start_at'] : '' );
				$client      = ! empty( $row['client_name'] ) ? (string) $row['client_name'] : __( 'No client', 'super-mechanic' );
				$mechanic    = ! empty( $row['mechanic_name'] ) ? (string) $row['mechanic_name'] : __( 'Unassigned mechanic', 'super-mechanic' );
				$status      = isset( $row['appointment_status'] ) ? (string) $row['appointment_status'] : '';
				$detail_url  = $this->get_admin_page_url(
					'super-mechanic-appointments',
					array(
						'action' => 'edit',
						'id'     => absint( isset( $row['id'] ) ? $row['id'] : 0 ),
					)
				);

				echo '<tr>';
				echo '<td>' . esc_html( $start_label ) . '</td>';
				echo '<td>' . esc_html( $client ) . '</td>';
				echo '<td>' . esc_html( $this->format_vehicle_label( $row ) ) . '</td>';
				echo '<td>' . esc_html( $mechanic ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_status_badge( $status ) ) . '</td>';
				echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html__( 'View appointment', 'super-mechanic' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render quick links for a process row.
	 *
	 * @param int    $process_id Process ID.
	 * @param string $process_type Process type.
	 * @return string
	 */
	protected function render_process_quick_links( $process_id, $process_type ) {
		$links = array();

		if ( 'maintenance' === $process_type ) {
			$links[] = '<a href="' . esc_url( $this->get_process_tab_url( $process_id, 'maintenance' ) ) . '">' . esc_html__( 'Maintenance', 'super-mechanic' ) . '</a>';
			$links[] = '<a href="' . esc_url( $this->get_process_tab_url( $process_id, 'quote' ) ) . '">' . esc_html__( 'Quote', 'super-mechanic' ) . '</a>';
		}

		$links[] = '<a href="' . esc_url( $this->get_process_tab_url( $process_id, 'invoice' ) ) . '">' . esc_html__( 'Invoice', 'super-mechanic' ) . '</a>';

		return implode( ' | ', $links );
	}

	/**
	 * Build process edit URL for a specific tab.
	 *
	 * @param int    $process_id Process ID.
	 * @param string $tab Process tab.
	 * @return string
	 */
	protected function get_process_tab_url( $process_id, $tab ) {
		return $this->get_admin_page_url(
			'super-mechanic-processes',
			array(
				'action' => 'edit',
				'id'     => absint( $process_id ),
				'tab'    => sanitize_key( $tab ),
			)
		);
	}

	/**
	 * Build admin page URLs.
	 *
	 * @param string               $page_slug rage slug.
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

		return $label ? $label : __( 'Unidentified vehicle', 'super-mechanic' );
	}

	/**
	 * Format datetime into operational label.
	 *
	 * @param string $value Datetime value.
	 * @return string
	 */
	protected function format_datetime_label( $value ) {
		if ( '' === $value ) {
			return __( 'No time', 'super-mechanic' );
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return $value;
		}

		return wp_date( 'Y-m-d H:i', $timestamp );
	}

	/**
	 * Render global operational summary section.
	 *
	 * @param array<string,int> $summary Global summary payload.
	 * @return void
	 */
	protected function render_global_operational_summary( array $summary ) {
		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Resumen Operativo Global', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Global', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Business-level aggregated metrics for operational load and critical points.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Pending CRM tasks', 'super-mechanic' ),
			isset( $summary['tasks_pending_total'] ) ? absint( $summary['tasks_pending_total'] ) : 0,
			__( 'Open workload in CRM', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Overdue CRM tasks', 'super-mechanic' ),
			isset( $summary['tasks_overdue_total'] ) ? absint( $summary['tasks_overdue_total'] ) : 0,
			__( 'Immediate attention required', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Active operational signals', 'super-mechanic' ),
			isset( $summary['alerts_active_total'] ) ? absint( $summary['alerts_active_total'] ) : 0,
			__( 'Pipeline-equivalent critical/attention signals', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Active processes', 'super-mechanic' ),
			isset( $summary['processes_active_total'] ) ? absint( $summary['processes_active_total'] ) : 0,
			__( 'Operational pipeline currently open', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Upcoming appointments', 'super-mechanic' ),
			isset( $summary['appointments_upcoming_total'] ) ? absint( $summary['appointments_upcoming_total'] ) : 0,
			__( 'Near-term scheduled work', 'super-mechanic' )
		);
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render workload section.
	 *
	 * @param array<string,mixed> $workload Workload payload.
	 * @return void
	 */
	protected function render_workload_section( array $workload ) {
		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Mi trabajo (workload operativo)', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html__( 'Por usuario', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Vista consolidada de tareas, alertas persistidas, procesos activos y citas próximas.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_workload_bucket_table(
			__( 'Critical', 'super-mechanic' ),
			isset( $workload['critical'] ) && is_array( $workload['critical'] ) ? $workload['critical'] : array(),
			__( 'No critical items.', 'super-mechanic' )
		);
		$this->render_workload_bucket_table(
			__( 'Warning', 'super-mechanic' ),
			isset( $workload['warning'] ) && is_array( $workload['warning'] ) ? $workload['warning'] : array(),
			__( 'No warning items.', 'super-mechanic' )
		);
		$this->render_workload_bucket_table(
			__( 'Normal', 'super-mechanic' ),
			isset( $workload['normal'] ) && is_array( $workload['normal'] ) ? $workload['normal'] : array(),
			__( 'No normal items.', 'super-mechanic' )
		);
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render automation flags section.
	 *
	 * @param array<string,mixed> $automation_flags Automation flags payload.
	 * @return void
	 */
	protected function render_operational_automation_flags( array $automation_flags ) {
		$flags        = isset( $automation_flags['flags'] ) && is_array( $automation_flags['flags'] ) ? $automation_flags['flags'] : array();
		$summary      = isset( $automation_flags['summary'] ) && is_array( $automation_flags['summary'] ) ? $automation_flags['summary'] : array();
		$active_count = isset( $summary['active_flags'] ) ? absint( $summary['active_flags'] ) : 0;
		$global_state = isset( $summary['global_state'] ) ? sanitize_key( (string) $summary['global_state'] ) : 'stable';
		$state_label  = 'elevated' === $global_state ? __( 'Elevated', 'super-mechanic' ) : ( 'attention' === $global_state ? __( 'Attention', 'super-mechanic' ) : __( 'Stable', 'super-mechanic' ) );
		$state_badge  = 'elevated' === $global_state ? 'sm-badge sm-badge-danger' : ( 'attention' === $global_state ? 'sm-badge sm-badge-warning' : 'sm-badge sm-badge-success' );

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Automatización operativa interna', 'super-mechanic' ) . '</h2><span class="' . esc_attr( $state_badge ) . '">' . esc_html( $state_label ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Rule-based internal flags generated from existing operational signals (no external automation).', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Active internal flags', 'super-mechanic' ),
			$active_count,
			__( 'Automatic operational suggestions', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Critical flags', 'super-mechanic' ),
			isset( $summary['critical_flags'] ) ? absint( $summary['critical_flags'] ) : 0,
			__( 'Need immediate attention', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Warning flags', 'super-mechanic' ),
			isset( $summary['warning_flags'] ) ? absint( $summary['warning_flags'] ) : 0,
			__( 'Monitor and rebalance load', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Rule', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Current value', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Threshold', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $flags ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No internal rules available.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $flags as $flag ) {
				$message   = isset( $flag['message'] ) ? sanitize_text_field( (string) $flag['message'] ) : __( 'Operational rule', 'super-mechanic' );
				$is_active = ! empty( $flag['active'] );
				$value     = isset( $flag['value'] ) ? absint( $flag['value'] ) : 0;
				$threshold = isset( $flag['threshold'] ) ? absint( $flag['threshold'] ) : 0;
				$level     = isset( $flag['level'] ) ? sanitize_key( (string) $flag['level'] ) : 'normal';
				$badge     = $is_active ? $this->render_workload_priority_badge( $level ) : '<span class="sm-badge sm-badge-success">' . esc_html__( 'OK', 'super-mechanic' ) . '</span>';
				echo '<tr>';
				echo '<td>' . esc_html( $message ) . '</td>';
				echo '<td>' . wp_kses_post( $badge ) . '</td>';
				echo '<td>' . esc_html( $value ) . '</td>';
				echo '<td>' . esc_html( $threshold ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render escalation state section.
	 *
	 * @param array<string,mixed> $escalation_state Escalation payload.
	 * @return void
	 */
	protected function render_operational_escalation_state( array $escalation_state ) {
		$global_level = isset( $escalation_state['global_level'] ) ? sanitize_key( (string) $escalation_state['global_level'] ) : 'normal';
		$blocking     = isset( $escalation_state['blocking_flags'] ) && is_array( $escalation_state['blocking_flags'] ) ? $escalation_state['blocking_flags'] : array();
		$user_sat     = isset( $escalation_state['user_saturation'] ) && is_array( $escalation_state['user_saturation'] ) ? $escalation_state['user_saturation'] : array();
		$badge_class  = 'sm-badge sm-badge-success';
		$badge_label  = __( 'Normal', 'super-mechanic' );

		if ( 'critical' === $global_level ) {
			$badge_class = 'sm-badge sm-badge-danger';
			$badge_label = __( 'Critical', 'super-mechanic' );
		} elseif ( 'warning' === $global_level ) {
			$badge_class = 'sm-badge sm-badge-warning';
			$badge_label = __( 'Warning', 'super-mechanic' );
		}

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Escalamiento Operativo', 'super-mechanic' ) . '</h2><span class="' . esc_attr( $badge_class ) . '">' . esc_html( $badge_label ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Consolidated escalation layer for critical blockers and saturation conditions.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Blocking flags', 'super-mechanic' ),
			count( $blocking ),
			__( 'Active operational blockers', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Critical workload', 'super-mechanic' ),
			isset( $escalation_state['critical_workload_count'] ) ? absint( $escalation_state['critical_workload_count'] ) : 0,
			__( 'Prioritized workload items', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Warning workload', 'super-mechanic' ),
			isset( $escalation_state['warning_workload_count'] ) ? absint( $escalation_state['warning_workload_count'] ) : 0,
			__( 'Follow-up workload items', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'User saturation', 'super-mechanic' ),
			! empty( $user_sat['is_saturated'] ) ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ),
			__( 'Critical load pressure per user', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Blocking condition', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Value', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Threshold', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $blocking ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No active operational blockers.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $blocking as $flag ) {
				$message   = isset( $flag['message'] ) ? sanitize_text_field( (string) $flag['message'] ) : __( 'Operational blocker', 'super-mechanic' );
				$level     = isset( $flag['level'] ) ? sanitize_key( (string) $flag['level'] ) : 'warning';
				$value     = isset( $flag['value'] ) ? absint( $flag['value'] ) : 0;
				$threshold = isset( $flag['threshold'] ) ? absint( $flag['threshold'] ) : 0;
				echo '<tr>';
				echo '<td>' . esc_html( $message ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $level ) ) . '</td>';
				echo '<td>' . esc_html( $value ) . '</td>';
				echo '<td>' . esc_html( $threshold ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render intelligent recommendations section.
	 *
	 * @param array<string,mixed> $payload Recommendations payload.
	 * @return void
	 */
	protected function render_operational_recommendations( array $payload ) {
		$recommendations = isset( $payload['recommendations'] ) && is_array( $payload['recommendations'] ) ? $payload['recommendations'] : array();
		$summary         = isset( $payload['summary'] ) && is_array( $payload['summary'] ) ? $payload['summary'] : array();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Sugerencias Inteligentes', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-primary">' . esc_html__( 'Recommendations', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Suggested next actions based on workload, escalation, and SLA signals.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Total recommendations', 'super-mechanic' ),
			isset( $summary['total'] ) ? absint( $summary['total'] ) : 0,
			__( 'Suggested operational actions', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Critical recommendations', 'super-mechanic' ),
			isset( $summary['critical'] ) ? absint( $summary['critical'] ) : 0,
			__( 'Immediate interventions', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Warning recommendations', 'super-mechanic' ),
			isset( $summary['warning'] ) ? absint( $summary['warning'] ) : 0,
			__( 'Priority follow-up actions', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Recommendation', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Message', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Action hint', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $recommendations ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No recommendations at this time.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $recommendations as $recommendation ) {
				$title       = isset( $recommendation['title'] ) ? sanitize_text_field( (string) $recommendation['title'] ) : __( 'Operational recommendation', 'super-mechanic' );
				$level       = isset( $recommendation['level'] ) ? sanitize_key( (string) $recommendation['level'] ) : 'warning';
				$message     = isset( $recommendation['message'] ) ? sanitize_text_field( (string) $recommendation['message'] ) : '';
				$action_hint = isset( $recommendation['action_hint'] ) ? sanitize_text_field( (string) $recommendation['action_hint'] ) : '';
				echo '<tr>';
				echo '<td>' . esc_html( $title ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $level ) ) . '</td>';
				echo '<td>' . esc_html( $message ) . '</td>';
				echo '<td>' . esc_html( $action_hint ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render operational assignment suggestions.
	 *
	 * @param array<string,mixed> $payload Assignment payload.
	 * @return void
	 */
	protected function render_operational_assignments( array $payload ) {
		$overloaded  = isset( $payload['overloaded_users'] ) && is_array( $payload['overloaded_users'] ) ? $payload['overloaded_users'] : array();
		$available   = isset( $payload['available_users'] ) && is_array( $payload['available_users'] ) ? $payload['available_users'] : array();
		$assignments = isset( $payload['assignments'] ) && is_array( $payload['assignments'] ) ? $payload['assignments'] : array();
		$summary     = isset( $payload['summary'] ) && is_array( $payload['summary'] ) ? $payload['summary'] : array();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Asignación Operativa', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html__( 'Suggested only', 'super-mechanic' ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Load balancing proposals without applying any real assignment changes.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Overloaded users', 'super-mechanic' ),
			isset( $summary['overloaded_users'] ) ? absint( $summary['overloaded_users'] ) : 0,
			__( 'Users with high critical load', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Available users', 'super-mechanic' ),
			isset( $summary['available_users'] ) ? absint( $summary['available_users'] ) : 0,
			__( 'Users with low operational load', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Assignment proposals', 'super-mechanic' ),
			isset( $summary['proposals'] ) ? absint( $summary['proposals'] ) : 0,
			__( 'Suggested workload redistribution', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-grid sm-grid-two">';
		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h3>' . esc_html__( 'Saturated users', 'super-mechanic' ) . '</h3></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'User', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Critical', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Warning', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $overloaded ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No saturated users detected.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $overloaded as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( isset( $row['display_name'] ) ? (string) $row['display_name'] : '' ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['critical'] ) ? $row['critical'] : 0 ) ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['warning'] ) ? $row['warning'] : 0 ) ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['total'] ) ? $row['total'] : 0 ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';

		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h3>' . esc_html__( 'Available users', 'super-mechanic' ) . '</h3></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'User', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Critical', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Warning', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Total', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $available ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No available users detected.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $available as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( isset( $row['display_name'] ) ? (string) $row['display_name'] : '' ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['critical'] ) ? $row['critical'] : 0 ) ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['warning'] ) ? $row['warning'] : 0 ) ) . '</td>';
				echo '<td>' . esc_html( absint( isset( $row['total'] ) ? $row['total'] : 0 ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
		echo '</div>';

		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'From', 'super-mechanic' ) . '</th><th>' . esc_html__( 'To', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Reason', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Delta', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Level', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $assignments ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No redistribution proposals right now.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $assignments as $proposal ) {
				$from_name = isset( $proposal['from_name'] ) ? sanitize_text_field( (string) $proposal['from_name'] ) : '';
				$to_name   = isset( $proposal['to_name'] ) ? sanitize_text_field( (string) $proposal['to_name'] ) : '';
				$reason    = isset( $proposal['reason'] ) ? sanitize_key( (string) $proposal['reason'] ) : 'saturation_balance';
				$delta     = isset( $proposal['workload_delta'] ) ? absint( $proposal['workload_delta'] ) : 0;
				$level     = isset( $proposal['level'] ) ? sanitize_key( (string) $proposal['level'] ) : 'warning';
				echo '<tr>';
				echo '<td>' . esc_html( $from_name ) . '</td>';
				echo '<td>' . esc_html( $to_name ) . '</td>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $reason ) ) ) . '</td>';
				echo '<td>' . esc_html( $delta ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $level ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render centralized automation console.
	 *
	 * @param array<string,mixed> $payload Automation console payload.
	 * @return void
	 */
	protected function render_operational_automation_console( array $payload ) {
		$status          = isset( $payload['system_status'] ) && is_array( $payload['system_status'] ) ? $payload['system_status'] : array();
		$flags           = isset( $payload['flags'] ) && is_array( $payload['flags'] ) ? $payload['flags'] : array();
		$escalation      = isset( $payload['escalation'] ) && is_array( $payload['escalation'] ) ? $payload['escalation'] : array();
		$recommendations = isset( $payload['recommendations'] ) && is_array( $payload['recommendations'] ) ? $payload['recommendations'] : array();
		$assignments     = isset( $payload['assignments'] ) && is_array( $payload['assignments'] ) ? $payload['assignments'] : array();

		$global_level = isset( $status['global_level'] ) ? sanitize_key( (string) $status['global_level'] ) : 'normal';
		$badge_class  = 'sm-badge sm-badge-success';
		$badge_label  = __( 'Normal', 'super-mechanic' );
		if ( 'critical' === $global_level ) {
			$badge_class = 'sm-badge sm-badge-danger';
			$badge_label = __( 'Critical', 'super-mechanic' );
		} elseif ( 'warning' === $global_level ) {
			$badge_class = 'sm-badge sm-badge-warning';
			$badge_label = __( 'Warning', 'super-mechanic' );
		}

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Consola de Automatización', 'super-mechanic' ) . '</h2><span class="' . esc_attr( $badge_class ) . '">' . esc_html( $badge_label ) . '</span></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Centralized read-only overview of automatic operational layers.', 'super-mechanic' ) . '</p>';
		echo '<div class="sm-grid sm-grid-cards">';
		$this->render_kpi_card(
			__( 'Active flags', 'super-mechanic' ),
			isset( $status['active_flags'] ) ? absint( $status['active_flags'] ) : 0,
			__( 'From automation flags layer', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Blocking flags', 'super-mechanic' ),
			isset( $status['blocking_flags'] ) ? absint( $status['blocking_flags'] ) : 0,
			__( 'From escalation layer', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Recommendations', 'super-mechanic' ),
			isset( $recommendations['summary']['total'] ) ? absint( $recommendations['summary']['total'] ) : 0,
			__( 'Suggested next actions', 'super-mechanic' )
		);
		$this->render_kpi_card(
			__( 'Assignment proposals', 'super-mechanic' ),
			isset( $assignments['summary']['proposals'] ) ? absint( $assignments['summary']['proposals'] ) : 0,
			__( 'Suggested redistribution only', 'super-mechanic' )
		);
		echo '</div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Layer', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Main count', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		echo '<tr><td>' . esc_html__( 'Flags', 'super-mechanic' ) . '</td><td>' . esc_html__( 'Active', 'super-mechanic' ) . '</td><td>' . esc_html( isset( $flags['summary']['active_flags'] ) ? absint( $flags['summary']['active_flags'] ) : 0 ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Escalation', 'super-mechanic' ) . '</td><td>' . esc_html( ucfirst( $global_level ) ) . '</td><td>' . esc_html( isset( $escalation['blocking_flags'] ) && is_array( $escalation['blocking_flags'] ) ? count( $escalation['blocking_flags'] ) : 0 ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Recommendations', 'super-mechanic' ) . '</td><td>' . esc_html__( 'Generated', 'super-mechanic' ) . '</td><td>' . esc_html( isset( $recommendations['summary']['total'] ) ? absint( $recommendations['summary']['total'] ) : 0 ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Assignments', 'super-mechanic' ) . '</td><td>' . esc_html__( 'Suggested', 'super-mechanic' ) . '</td><td>' . esc_html( isset( $assignments['summary']['proposals'] ) ? absint( $assignments['summary']['proposals'] ) : 0 ) . '</td></tr>';
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render one workload bucket table.
	 *
	 * @param string                            $title Bucket title.
	 * @param array<int,array<string,mixed>>    $items Bucket items.
	 * @param string                            $empty_message Empty message.
	 * @return void
	 */
	protected function render_workload_bucket_table( $title, array $items, $empty_message ) {
		echo '<section class="sm-card sm-card-muted">';
		echo '<div class="sm-section-heading"><h3>' . esc_html( $title ) . '</h3><span class="sm-badge sm-badge-neutral">' . esc_html( count( $items ) ) . '</span></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr><th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Title', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Date', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Priority', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		if ( empty( $items ) ) {
			echo '<tr><td colspan="4">' . esc_html( $empty_message ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				$type      = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : 'task';
				$title     = isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : __( 'Work item', 'super-mechanic' );
				$url       = isset( $item['url'] ) ? esc_url_raw( (string) $item['url'] ) : '';
				$date      = isset( $item['date'] ) ? (string) $item['date'] : '';
				$priority  = isset( $item['priority'] ) ? sanitize_key( (string) $item['priority'] ) : 'normal';
				echo '<tr>';
				echo '<td>' . esc_html( ucfirst( $type ) ) . '</td>';
				if ( '' !== $url ) {
					echo '<td><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></td>';
				} else {
					echo '<td>' . esc_html( $title ) . '</td>';
				}
				echo '<td>' . esc_html( $this->format_datetime_label( $date ) ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_workload_priority_badge( $priority ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render workload priority badge.
	 *
	 * @param string $priority Priority key.
	 * @return string
	 */
	protected function render_workload_priority_badge( $priority ) {
		$priority = sanitize_key( (string) $priority );
		$class    = 'sm-badge sm-badge-neutral';

		if ( 'critical' === $priority ) {
			$class = 'sm-badge sm-badge-danger';
		} elseif ( 'warning' === $priority ) {
			$class = 'sm-badge sm-badge-warning';
		} elseif ( 'normal' === $priority ) {
			$class = 'sm-badge sm-badge-success';
		}

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( ucfirst( $priority ) ) . '</span>';
	}
}




