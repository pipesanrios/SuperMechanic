<?php
/**
 * Appointment admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Appointments;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Handles appointment admin flows.
 */
class Appointment_Admin_Controller {
	/**
	 * Internal REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'super-mechanic/v1';

	/**
	 * Service.
	 *
	 * @var Appointment_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Appointment_Service|null $service Service.
	 */
	public function __construct( Appointment_Service $service = null ) {
		$this->service = $service ? $service : new Appointment_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_handle_actions' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Register REST hooks for calendar endpoints.
	 *
	 * @return void
	 */
	public function register_rest_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Handle actions.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_appointments_screen() ) {
			return;
		}

		$this->ensure_permissions();
		$this->handle_actions();
	}

	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render_page() {
		$this->ensure_permissions();

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( 'new' === $action ) {
			$this->render_form_page();
			return;
		}

		if ( 'edit' === $action ) {
			$appointment = $this->service->get_appointment( $id );
			if ( ! is_array( $appointment ) ) {
				wp_die( esc_html__( 'The requested appointment does not exist.', 'super-mechanic' ) );
			}

			$this->render_form_page( $appointment, true );
			return;
		}

		$this->render_list_page();
	}

	/**
	 * Render operational calendar page.
	 *
	 * @return void
	 */
	public function render_calendar_page() {
		$this->ensure_permissions();

		echo '<div class="wrap sm-admin-shell sm-calendar-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Calendar', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Visual operational appointment management by day, week, and month.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ) . '" class="button button-primary">' . esc_html__( 'New appointment', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';

		echo '<div class="sm-card sm-section sm-calendar-controls">';
		echo '<div class="sm-calendar-controls-grid">';
		echo '<div class="sm-calendar-controls-status">';
		echo '<label for="sm-calendar-status-select"><strong>' . esc_html__( 'Quick status change', 'super-mechanic' ) . '</strong></label>';
		echo '<select id="sm-calendar-status-select">';
		foreach ( $this->service->get_status_options() as $status_key => $status_label ) {
			echo '<option value="' . esc_attr( $status_key ) . '">' . esc_html( $status_label ) . '</option>';
		}
		echo '</select>';
		echo '<button type="button" class="button button-secondary" id="sm-calendar-status-update" disabled>' . esc_html__( 'Update status', 'super-mechanic' ) . '</button>';
		echo '</div>';
		echo '<div class="sm-calendar-selection">';
		echo '<p id="sm-calendar-selected-title">' . esc_html__( 'Select an appointment in the calendar to update its status.', 'super-mechanic' ) . '</p>';
		echo '<p class="description" id="sm-calendar-feedback"></p>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="sm-card sm-section sm-calendar-card">';
		echo '<div id="sm-appointments-calendar" class="sm-appointments-calendar"></div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! $this->is_appointments_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		$count  = isset( $_GET['deleted_count'] ) ? absint( $_GET['deleted_count'] ) : 0;

		if ( 'created' === $notice ) {
			$this->render_notice( __( 'Appointment created successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'updated' === $notice ) {
			$this->render_notice( __( 'Appointment updated successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'deleted' === $notice ) {
			$this->render_notice( __( 'Appointment deleted successfully.', 'super-mechanic' ), 'success' );
		}

		if ( 'bulk_deleted' === $notice ) {
			$this->render_notice( sprintf( __( '%d appointments deleted successfully.', 'super-mechanic' ), $count ), 'success' );
		}

		if ( 'error' === $notice ) {
			$messages = get_transient( $this->get_error_transient_key() );
			delete_transient( $this->get_error_transient_key() );
			if ( is_array( $messages ) ) {
				foreach ( $messages as $message ) {
					$this->render_notice( $message, 'error' );
				}
			}
		}
	}

	/**
	 * Render list.
	 *
	 * @return void
	 */
	protected function render_list_page() {
		$list_table = new Appointment_List_Table( $this->service );
		$list_table->prepare_items();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Appointments', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Manage the workshop base schedule with filters by date, mechanic, and status.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ) . '" class="button button-primary">' . esc_html__( 'New appointment', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';

		$this->render_filter_form();

		echo '<div class="sm-card sm-section">';
		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="super-mechanic-appointments" />';
		wp_nonce_field( 'sm_bulk_delete_appointments', 'sm_bulk_delete_nonce' );
		$list_table->search_box( __( 'Search appointments', 'super-mechanic' ), 'sm-appointments' );
		echo '<div class="sm-table-wrap sm-list-table-wrap">';
		$list_table->display();
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render form.
	 *
	 * @param array<string,mixed> $appointment Appointment data.
	 * @param bool                $is_edit     Edit mode.
	 * @return void
	 */
	protected function render_form_page( array $appointment = array(), $is_edit = false ) {
		$defaults = array(
			'id'                 => 0,
			'client_id'          => 0,
			'vehicle_id'         => 0,
			'process_id'         => 0,
			'assigned_to'        => 0,
			'appointment_status' => 'scheduled',
			'appointment_date'   => '',
			'start_at'           => '',
			'notes'              => '',
		);

		if ( ! $is_edit ) {
			$prefill = $this->get_form_prefill_from_query();
			if ( ! empty( $prefill ) ) {
				$appointment = array_merge( $appointment, $prefill );
			}
		}

		$stored = get_transient( $this->get_form_transient_key() );
		if ( is_array( $stored ) ) {
			$appointment = array_merge( $appointment, $stored );
			delete_transient( $this->get_form_transient_key() );
		}

		$appointment = wp_parse_args( $appointment, $defaults );
		$title       = $is_edit ? __( 'Edit appointment', 'super-mechanic' ) : __( 'New appointment', 'super-mechanic' );
		$clients     = $this->service->get_client_options();
		$vehicles    = $this->service->get_vehicle_options();
		$mechanics   = $this->service->get_mechanic_options();
		$statuses    = $this->service->get_status_options();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Appointment operational base: client, vehicle, mechanic, status, and schedule.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url() ) . '" class="button button-secondary">' . esc_html__( 'Back to list', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';

		echo '<div class="sm-card sm-form-card">';
		echo '<form method="post" action="' . esc_url( $this->get_page_url( $is_edit ? array( 'action' => 'edit', 'id' => absint( $appointment['id'] ) ) : array( 'action' => 'new' ) ) ) . '">';
		wp_nonce_field( 'sm_save_appointment', 'sm_appointment_nonce' );
		echo '<input type="hidden" name="sm_appointment_operation" value="' . esc_attr( $is_edit ? 'update' : 'create' ) . '" />';
		echo '<input type="hidden" name="appointment_id" value="' . esc_attr( absint( $appointment['id'] ) ) . '" />';
		echo '<table class="form-table" role="presentation">';
		$this->render_client_select_field( $appointment['client_id'], $clients );
		$this->render_vehicle_select_field( $appointment['vehicle_id'], $vehicles );
		$this->render_number_field( 'process_id', __( 'Process (optional)', 'super-mechanic' ), $appointment['process_id'] );
		$this->render_mechanic_select_field( $appointment['assigned_to'], $mechanics );
		$this->render_select_field( 'appointment_status', __( 'Status', 'super-mechanic' ), $appointment['appointment_status'], $statuses );
		$this->render_date_field( 'appointment_date', __( 'Appointment date', 'super-mechanic' ), $appointment['appointment_date'] );
		$this->render_datetime_field( 'start_at', __( 'Start date and time', 'super-mechanic' ), $appointment['start_at'] );
		$this->render_textarea_field( 'notes', __( 'Notas', 'super-mechanic' ), $appointment['notes'] );
		echo '</table>';
		echo '<div class="sm-form-actions">';
		submit_button( $is_edit ? __( 'Update appointment', 'super-mechanic' ) : __( 'Create appointment', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Handle incoming actions.
	 *
	 * @return void
	 */
	protected function handle_actions() {
		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			$operation   = isset( $_POST['sm_appointment_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_appointment_operation'] ) ) : '';
			$bulk_action = $this->get_bulk_action();

			if ( 'create' === $operation || 'update' === $operation ) {
				$this->handle_save_action( 'update' === $operation );
			}

			if ( 'bulk-delete' === $bulk_action ) {
				$this->handle_bulk_delete_action();
			}
		}

		if ( isset( $_GET['action'] ) && 'delete' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$this->handle_delete_action();
		}
	}

	/**
	 * Handle save.
	 *
	 * @param bool $is_update Update mode.
	 * @return void
	 */
	protected function handle_save_action( $is_update ) {
		check_admin_referer( 'sm_save_appointment', 'sm_appointment_nonce' );

		$appointment_id = isset( $_POST['appointment_id'] ) ? absint( wp_unslash( $_POST['appointment_id'] ) ) : 0;
		$data           = array(
			'client_id'          => isset( $_POST['client_id'] ) ? wp_unslash( $_POST['client_id'] ) : 0,
			'vehicle_id'         => isset( $_POST['vehicle_id'] ) ? wp_unslash( $_POST['vehicle_id'] ) : 0,
			'process_id'         => isset( $_POST['process_id'] ) ? wp_unslash( $_POST['process_id'] ) : 0,
			'assigned_to'        => isset( $_POST['assigned_to'] ) ? wp_unslash( $_POST['assigned_to'] ) : 0,
			'appointment_status' => isset( $_POST['appointment_status'] ) ? wp_unslash( $_POST['appointment_status'] ) : 'scheduled',
			'appointment_date'   => isset( $_POST['appointment_date'] ) ? wp_unslash( $_POST['appointment_date'] ) : '',
			'start_at'           => isset( $_POST['start_at'] ) ? wp_unslash( $_POST['start_at'] ) : '',
			'notes'              => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
		);

		$result = $is_update ? $this->service->update_appointment( $appointment_id, $data ) : $this->service->create_appointment( $data );

		if ( is_wp_error( $result ) ) {
			$this->store_form_state( $data );
			$this->store_errors( $result );
			$this->redirect(
				$is_update
					? array(
						'action'    => 'edit',
						'id'        => $appointment_id,
						'sm_notice' => 'error',
					)
					: array(
						'action'    => 'new',
						'sm_notice' => 'error',
					)
			);
		}

		$this->redirect( array( 'sm_notice' => $is_update ? 'updated' : 'created' ) );
	}

	/**
	 * Handle delete.
	 *
	 * @return void
	 */
	protected function handle_delete_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'sm_delete_appointment_' . $id );

		$result = $this->service->delete_appointment( $id );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$this->redirect( array( 'sm_notice' => 'deleted' ) );
	}

	/**
	 * Handle bulk delete.
	 *
	 * @return void
	 */
	protected function handle_bulk_delete_action() {
		check_admin_referer( 'sm_bulk_delete_appointments', 'sm_bulk_delete_nonce' );

		$ids = isset( $_POST['appointment_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['appointment_ids'] ) ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			$this->store_errors( new WP_Error( 'sm_no_appointments_selected', __( 'Select at least one appointment to delete.', 'super-mechanic' ) ) );
			$this->redirect( array( 'sm_notice' => 'error' ) );
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			$result = $this->service->delete_appointment( $id );
			if ( ! is_wp_error( $result ) ) {
				++$deleted;
			}
		}

		$this->redirect(
			array(
				'sm_notice'     => 'bulk_deleted',
				'deleted_count' => $deleted,
			)
		);
	}

	/**
	 * Render filters.
	 *
	 * @return void
	 */
	protected function render_filter_form() {
		$statuses    = $this->service->get_status_options();
		$mechanics   = $this->service->get_mechanic_options();
		$status      = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : '';
		$assigned_to = isset( $_GET['filter_assigned_to'] ) ? absint( wp_unslash( $_GET['filter_assigned_to'] ) ) : 0;
		$date_from   = isset( $_GET['filter_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_from'] ) ) : '';
		$date_to     = isset( $_GET['filter_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_to'] ) ) : '';

		echo '<div class="sm-card sm-filter-card sm-section">';
		echo '<form method="get" class="sm-inline-filters">';
		echo '<input type="hidden" name="page" value="super-mechanic-appointments" />';

		echo '<label for="filter_status" class="screen-reader-text">' . esc_html__( 'Filter by status', 'super-mechanic' ) . '</label>';
		echo '<select id="filter_status" name="filter_status">';
		echo '<option value="">' . esc_html__( 'Todos los estados', 'super-mechanic' ) . '</option>';
		foreach ( $statuses as $status_key => $status_label ) {
			echo '<option value="' . esc_attr( $status_key ) . '" ' . selected( $status, $status_key, false ) . '>' . esc_html( $status_label ) . '</option>';
		}
		echo '</select>';

		echo '<label for="filter_assigned_to" class="screen-reader-text">' . esc_html__( 'Filter by mechanic', 'super-mechanic' ) . '</label>';
		echo '<select id="filter_assigned_to" name="filter_assigned_to">';
		echo '<option value="0">' . esc_html__( 'Todos los mecanicos', 'super-mechanic' ) . '</option>';
		foreach ( $mechanics as $mechanic ) {
			echo '<option value="' . esc_attr( absint( $mechanic['id'] ) ) . '" ' . selected( $assigned_to, absint( $mechanic['id'] ), false ) . '>' . esc_html( $mechanic['display_name'] ) . '</option>';
		}
		echo '</select>';

		echo '<label for="filter_date_from" class="screen-reader-text">' . esc_html__( 'From date', 'super-mechanic' ) . '</label>';
		echo '<input type="date" id="filter_date_from" name="filter_date_from" value="' . esc_attr( $date_from ) . '" />';

		echo '<label for="filter_date_to" class="screen-reader-text">' . esc_html__( 'To date', 'super-mechanic' ) . '</label>';
		echo '<input type="date" id="filter_date_to" name="filter_date_to" value="' . esc_attr( $date_to ) . '" />';

		submit_button( __( 'Filtrar', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '<a class="button button-link" href="' . esc_url( $this->get_page_url() ) . '">' . esc_html__( 'Clear', 'super-mechanic' ) . '</a>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Register internal REST routes for admin calendar.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			$this->namespace,
			'/admin/appointments/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_calendar_events' ),
				'permission_callback' => array( $this, 'check_calendar_permission' ),
				'args'                => array(
					'start' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/appointments/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_calendar_appointment_status' ),
				'permission_callback' => array( $this, 'check_calendar_permission' ),
				'args'                => array(
					'status' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/admin/appointments/(?P<id>\d+)/reschedule',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_calendar_appointment_schedule' ),
				'permission_callback' => array( $this, 'check_calendar_permission' ),
				'args'                => array(
					'start_at' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check permissions for calendar endpoints.
	 *
	 * @return true|WP_Error
	 */
	public function check_calendar_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'sm_rest_login_required', __( 'Debe iniciar sesion para acceder al calendario.', 'super-mechanic' ), array( 'status' => 401 ) );
		}

		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			return new WP_Error( 'sm_rest_forbidden', __( 'You do not have permission to manage appointments.', 'super-mechanic' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Return calendar event payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_calendar_events( WP_REST_Request $request ) {
		$start        = sanitize_text_field( (string) $request->get_param( 'start' ) );
		$end          = sanitize_text_field( (string) $request->get_param( 'end' ) );
		$appointments = $this->service->get_appointments_for_calendar( $start, $end );
		$events       = array();

		foreach ( $appointments as $appointment ) {
			$event = $this->map_calendar_event_payload( $appointment );
			if ( ! empty( $event ) ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Update one appointment status from calendar.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_calendar_appointment_status( WP_REST_Request $request ) {
		$appointment_id = absint( $request->get_param( 'id' ) );
		$status         = sanitize_key( (string) $request->get_param( 'status' ) );

		if ( $appointment_id <= 0 ) {
			return new WP_Error( 'sm_appointment_not_found', __( 'The appointment does not exist.', 'super-mechanic' ), array( 'status' => 404 ) );
		}

		$result = $this->service->update_appointment_status_from_calendar( $appointment_id, $status );
		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$appointment = $this->service->get_appointment( $appointment_id );
		if ( ! is_array( $appointment ) ) {
			return new WP_Error( 'sm_appointment_not_found', __( 'The appointment does not exist.', 'super-mechanic' ), array( 'status' => 404 ) );
		}

		return $this->map_calendar_event_payload( $appointment );
	}

	/**
	 * Update one appointment schedule from calendar drag/drop.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_calendar_appointment_schedule( WP_REST_Request $request ) {
		$appointment_id = absint( $request->get_param( 'id' ) );
		$start_at       = sanitize_text_field( (string) $request->get_param( 'start_at' ) );

		if ( $appointment_id <= 0 ) {
			return new WP_Error( 'sm_appointment_not_found', __( 'The appointment does not exist.', 'super-mechanic' ), array( 'status' => 404 ) );
		}

		$result = $this->service->update_appointment_schedule_from_calendar( $appointment_id, $start_at );
		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$appointment = $this->service->get_appointment( $appointment_id );
		if ( ! is_array( $appointment ) ) {
			return new WP_Error( 'sm_appointment_not_found', __( 'The appointment does not exist.', 'super-mechanic' ), array( 'status' => 404 ) );
		}

		return $this->map_calendar_event_payload( $appointment );
	}

	/**
	 * Build stable FullCalendar event payload.
	 *
	 * @param array<string,mixed> $appointment Appointment row.
	 * @return array<string,mixed>
	 */
	protected function map_calendar_event_payload( array $appointment ) {
		$appointment_id = isset( $appointment['id'] ) ? absint( $appointment['id'] ) : 0;
		$start_at       = isset( $appointment['start_at'] ) ? sanitize_text_field( (string) $appointment['start_at'] ) : '';

		if ( $appointment_id <= 0 || '' === $start_at ) {
			return array();
		}

		$start_ts = strtotime( $start_at );
		if ( false === $start_ts ) {
			return array();
		}

		$end_ts = strtotime( '+1 hour', $start_ts );
		$status = isset( $appointment['appointment_status'] ) ? sanitize_key( (string) $appointment['appointment_status'] ) : 'scheduled';
		$client = isset( $appointment['client_name'] ) ? sanitize_text_field( (string) $appointment['client_name'] ) : '';
		$vehicle = $this->build_vehicle_label( $appointment );
		$title_parts = array_filter(
			array(
				gmdate( 'H:i', $start_ts ),
				$client,
				$vehicle,
			)
		);
		$title       = implode( ' - ', $title_parts );
		$colors      = $this->get_status_colors( $status );

		return array(
			'id'            => (string) $appointment_id,
			'title'         => '' !== $title ? $title : '#' . $appointment_id,
			'start'         => gmdate( 'c', $start_ts ),
			'end'           => gmdate( 'c', false !== $end_ts ? $end_ts : $start_ts ),
			'url'           => $this->get_page_url(
				array(
					'action' => 'edit',
					'id'     => $appointment_id,
				)
			),
			'backgroundColor' => $colors['background'],
			'borderColor'     => $colors['border'],
			'textColor'       => $colors['text'],
			'extendedProps' => array(
				'appointment_status' => $status,
				'client_name'        => $client,
				'vehicle_label'      => $vehicle,
				'mechanic_name'      => isset( $appointment['mechanic_name'] ) ? sanitize_text_field( (string) $appointment['mechanic_name'] ) : '',
				'process_id'         => isset( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
			),
		);
	}

	/**
	 * Build one calendar vehicle label from appointment row.
	 *
	 * @param array<string,mixed> $appointment Appointment row.
	 * @return string
	 */
	protected function build_vehicle_label( array $appointment ) {
		$label = trim(
			sprintf(
				'%s %s',
				isset( $appointment['vehicle_make'] ) ? sanitize_text_field( (string) $appointment['vehicle_make'] ) : '',
				isset( $appointment['vehicle_model'] ) ? sanitize_text_field( (string) $appointment['vehicle_model'] ) : ''
			)
		);

		$plate = isset( $appointment['vehicle_plate'] ) ? sanitize_text_field( (string) $appointment['vehicle_plate'] ) : '';
		$vin   = isset( $appointment['vehicle_vin'] ) ? sanitize_text_field( (string) $appointment['vehicle_vin'] ) : '';
		if ( '' !== $plate ) {
			$label .= ' - ' . $plate;
		} elseif ( '' !== $vin ) {
			$label .= ' - ' . $vin;
		}

		return trim( $label );
	}

	/**
	 * Resolve status colors aligned with existing admin badge system.
	 *
	 * @param string $status Appointment status.
	 * @return array<string,string>
	 */
	protected function get_status_colors( $status ) {
		$palette = array(
			'scheduled'   => array(
				'background' => '#e7f0ff',
				'border'     => '#bfd4ff',
				'text'       => '#185fbe',
			),
			'confirmed'   => array(
				'background' => '#e7f7ef',
				'border'     => '#bee7d0',
				'text'       => '#166d53',
			),
			'in_progress' => array(
				'background' => '#fff5df',
				'border'     => '#f0db9f',
				'text'       => '#956617',
			),
			'completed'   => array(
				'background' => '#edf2fa',
				'border'     => '#d8e1ef',
				'text'       => '#42526e',
			),
			'cancelled'   => array(
				'background' => '#fde9ea',
				'border'     => '#f2c0c4',
				'text'       => '#a43640',
			),
		);

		return isset( $palette[ $status ] ) ? $palette[ $status ] : $palette['scheduled'];
	}

	/**
	 * Render client select field.
	 *
	 * @param int                      $selected Selected ID.
	 * @param array<int,array<string,mixed>> $clients Clients.
	 * @return void
	 */
	protected function render_client_select_field( $selected, array $clients ) {
		echo '<tr>';
		echo '<th scope="row"><label for="client_id">' . esc_html__( 'Client', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="client_id" id="client_id" required>';
		echo '<option value="0">' . esc_html__( 'Select a client', 'super-mechanic' ) . '</option>';
		foreach ( $clients as $client ) {
			$label = trim( sprintf( '%s %s', isset( $client['first_name'] ) ? $client['first_name'] : '', isset( $client['last_name'] ) ? $client['last_name'] : '' ) );
			if ( '' === $label && ! empty( $client['email'] ) ) {
				$label = (string) $client['email'];
			}
			echo '<option value="' . esc_attr( absint( $client['id'] ) ) . '" ' . selected( absint( $selected ), absint( $client['id'] ), false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';
	}

	/**
	 * Render vehicle select field.
	 *
	 * @param int                           $selected Selected ID.
	 * @param array<int,array<string,mixed>> $vehicles Vehicles.
	 * @return void
	 */
	protected function render_vehicle_select_field( $selected, array $vehicles ) {
		echo '<tr>';
		echo '<th scope="row"><label for="vehicle_id">' . esc_html__( 'Vehicle', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="vehicle_id" id="vehicle_id" required>';
		echo '<option value="0">' . esc_html__( 'Select a vehicle', 'super-mechanic' ) . '</option>';
		foreach ( $vehicles as $vehicle ) {
			$label = trim( sprintf( '%s %s', isset( $vehicle['make'] ) ? $vehicle['make'] : '', isset( $vehicle['model'] ) ? $vehicle['model'] : '' ) );
			if ( ! empty( $vehicle['plate'] ) ) {
				$label .= ' - ' . $vehicle['plate'];
			} elseif ( ! empty( $vehicle['vin'] ) ) {
				$label .= ' - ' . $vehicle['vin'];
			}
			echo '<option value="' . esc_attr( absint( $vehicle['id'] ) ) . '" ' . selected( absint( $selected ), absint( $vehicle['id'] ), false ) . '>' . esc_html( '' !== trim( $label ) ? $label : __( 'Unidentified vehicle', 'super-mechanic' ) ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';
	}

	/**
	 * Render mechanic select.
	 *
	 * @param int                           $selected  Selected ID.
	 * @param array<int,array<string,mixed>> $mechanics Mechanics.
	 * @return void
	 */
	protected function render_mechanic_select_field( $selected, array $mechanics ) {
		echo '<tr>';
		echo '<th scope="row"><label for="assigned_to">' . esc_html__( 'Assigned mechanic', 'super-mechanic' ) . '</label></th>';
		echo '<td><select name="assigned_to" id="assigned_to" required>';
		echo '<option value="0">' . esc_html__( 'Select a mechanic', 'super-mechanic' ) . '</option>';
		foreach ( $mechanics as $mechanic ) {
			echo '<option value="' . esc_attr( absint( $mechanic['id'] ) ) . '" ' . selected( absint( $selected ), absint( $mechanic['id'] ), false ) . '>' . esc_html( $mechanic['display_name'] ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';
	}

	/**
	 * Render select field.
	 *
	 * @param string              $name     Field name.
	 * @param string              $label    Label.
	 * @param string              $selected Selected value.
	 * @param array<string,string> $options  Options.
	 * @return void
	 */
	protected function render_select_field( $name, $label, $selected, array $options ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $option_label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';
	}

	/**
	 * Render date field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	protected function render_date_field( $name, $label, $value ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="date" id="' . esc_attr( $name ) . '" value="' . esc_attr( $this->format_date_for_input( $value ) ) . '" required /></td>';
		echo '</tr>';
	}

	/**
	 * Render datetime field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	protected function render_datetime_field( $name, $label, $value ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="datetime-local" id="' . esc_attr( $name ) . '" value="' . esc_attr( $this->format_datetime_for_input( $value ) ) . '" class="regular-text" required /></td>';
		echo '</tr>';
	}

	/**
	 * Render number field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param mixed  $value Value.
	 * @return void
	 */
	protected function render_number_field( $name, $label, $value ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input name="' . esc_attr( $name ) . '" type="number" min="0" id="' . esc_attr( $name ) . '" value="' . esc_attr( absint( $value ) ) . '" class="regular-text" /></td>';
		echo '</tr>';
	}

	/**
	 * Render textarea field.
	 *
	 * @param string $name  Name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	protected function render_textarea_field( $name, $label, $value ) {
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="large-text" rows="5">' . esc_textarea( $value ) . '</textarea></td>';
		echo '</tr>';
	}

	/**
	 * Render admin notice.
	 *
	 * @param string $message Message.
	 * @param string $type    Type.
	 * @return void
	 */
	protected function render_notice( $message, $type ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible sm-notice-card"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Ensure capability.
	 *
	 * @return void
	 */
	protected function ensure_permissions() {
		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage appointments.', 'super-mechanic' ) );
		}
	}

	/**
	 * Get current bulk action.
	 *
	 * @return string
	 */
	protected function get_bulk_action() {
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		if ( '-1' === $action ) {
			$action = isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '';
		}

		return $action;
	}

	/**
	 * Store form state.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return void
	 */
	protected function store_form_state( array $data ) {
		set_transient( $this->get_form_transient_key(), $data, MINUTE_IN_SECONDS );
	}

	/**
	 * Store errors.
	 *
	 * @param WP_Error $error Error.
	 * @return void
	 */
	protected function store_errors( WP_Error $error ) {
		set_transient( $this->get_error_transient_key(), $error->get_error_messages(), MINUTE_IN_SECONDS );
	}

	/**
	 * Redirect page.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return void
	 */
	protected function redirect( array $args = array() ) {
		wp_safe_redirect( $this->get_page_url( $args ) );
		exit;
	}

	/**
	 * Get page url.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return string
	 */
	protected function get_page_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'super-mechanic-appointments' ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Check current screen.
	 *
	 * @return bool
	 */
	protected function is_appointments_screen() {
		return isset( $_GET['page'] ) && 'super-mechanic-appointments' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Error transient key.
	 *
	 * @return string
	 */
	protected function get_error_transient_key() {
		return 'sm_appointment_errors_' . get_current_user_id();
	}

	/**
	 * Form transient key.
	 *
	 * @return string
	 */
	protected function get_form_transient_key() {
		return 'sm_appointment_form_' . get_current_user_id();
	}

	/**
	 * Build safe form prefill values from query args.
	 *
	 * @return array<string,string>
	 */
	protected function get_form_prefill_from_query() {
		$prefill = array();

		if ( isset( $_GET['appointment_date'] ) ) {
			$date = $this->format_date_for_input( sanitize_text_field( wp_unslash( $_GET['appointment_date'] ) ) );
			if ( '' !== $date ) {
				$prefill['appointment_date'] = $date;
			}
		}

		if ( isset( $_GET['start_at'] ) ) {
			$start_at = $this->format_datetime_for_input( sanitize_text_field( wp_unslash( $_GET['start_at'] ) ) );
			if ( '' !== $start_at ) {
				$prefill['start_at'] = $start_at;
			}
		}

		return $prefill;
	}

	/**
	 * Format datetime for input.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function format_datetime_for_input( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$timestamp = strtotime( (string) $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d\TH:i', $timestamp );
	}

	/**
	 * Format date for input.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	protected function format_date_for_input( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$timestamp = strtotime( (string) $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}
}




