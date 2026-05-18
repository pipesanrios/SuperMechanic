<?php
/**
 * Vehicle catalog admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Businesses\Business_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Vehicles\Vehicle_Catalog_Import_Service;
use Super_Mechanic\Vehicles\Vehicle_Catalog_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles the vehicle catalog admin UI.
 */
class Vehicle_Catalog_Admin_Controller {
	/**
	 * Vehicle catalog service.
	 *
	 * @var Vehicle_Catalog_Service
	 */
	protected $catalog_service;

	/**
	 * Vehicle catalog import service.
	 *
	 * @var Vehicle_Catalog_Import_Service
	 */
	protected $catalog_import_service;

	/**
	 * Business service.
	 *
	 * @var Business_Service
	 */
	protected $business_service;

	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Vehicle_Catalog_Service|null        $catalog_service          Catalog service.
	 * @param Business_Service|null               $business_service         Business service.
	 * @param Business_Context_Service|null       $business_context_service Business context service.
	 * @param Vehicle_Catalog_Import_Service|null $catalog_import_service   Catalog import service.
	 */
	public function __construct( Vehicle_Catalog_Service $catalog_service = null, Business_Service $business_service = null, Business_Context_Service $business_context_service = null, Vehicle_Catalog_Import_Service $catalog_import_service = null ) {
		$this->business_service         = $business_service ? $business_service : new Business_Service();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service( null, $this->business_service );
		$this->catalog_service          = $catalog_service ? $catalog_service : new Vehicle_Catalog_Service( null, $this->business_context_service );
		$this->catalog_import_service   = $catalog_import_service ? $catalog_import_service : new Vehicle_Catalog_Import_Service( $this->catalog_service, $this->business_context_service );
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
	 * Process actions before page output.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_catalog_screen() ) {
			return;
		}

		$this->ensure_permissions();

		if ( 'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
			$operation = isset( $_POST['sm_vehicle_catalog_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_vehicle_catalog_operation'] ) ) : '';
			if ( 'create' === $operation || 'update' === $operation ) {
				$this->handle_save_action( 'update' === $operation );
			}

			if ( 'import_dry_run' === $operation ) {
				$this->handle_import_dry_run_action();
			}

			if ( 'import_confirm' === $operation ) {
				$this->handle_import_confirm_action();
			}
		}

		if ( isset( $_GET['action'] ) && 'deactivate' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$this->handle_deactivate_action();
		}
	}

	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render_page() {
		$this->ensure_permissions();

		$action     = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$catalog_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

		if ( 'new' === $action ) {
			$this->render_form_page();
			return;
		}

		if ( 'edit' === $action ) {
			$business_id = isset( $_GET['business_id'] ) ? absint( wp_unslash( $_GET['business_id'] ) ) : 0;
			$catalog     = $this->catalog_service->get_catalog_vehicle( $catalog_id, $business_id );

			if ( ! is_array( $catalog ) ) {
				wp_die( esc_html__( 'The requested catalog vehicle does not exist.', 'super-mechanic' ) );
			}

			$this->render_form_page( $catalog, true );
			return;
		}

		$this->render_list_page();
	}

	/**
	 * Render admin notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! $this->is_catalog_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';
		if ( '' === $notice ) {
			return;
		}

		$messages = array(
			'created'        => __( 'Catalog vehicle created successfully.', 'super-mechanic' ),
			'updated'        => __( 'Catalog vehicle updated successfully.', 'super-mechanic' ),
			'deactivated'    => __( 'Catalog vehicle deactivated successfully.', 'super-mechanic' ),
			'import_preview' => __( 'CSV dry-run completed. Review the preview before confirming import.', 'super-mechanic' ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			$this->render_notice( $messages[ $notice ], 'success' );
			return;
		}

		if ( 'error' === $notice ) {
			$messages = get_transient( $this->get_error_transient_key() );
			delete_transient( $this->get_error_transient_key() );
			if ( is_array( $messages ) ) {
				foreach ( $messages as $message ) {
					$this->render_notice( (string) $message, 'error' );
				}
			}
		}

		if ( 'imported' === $notice ) {
			$result = get_transient( $this->get_import_result_transient_key() );
			delete_transient( $this->get_import_result_transient_key() );
			$count = is_array( $result ) && isset( $result['imported_rows'] ) ? absint( $result['imported_rows'] ) : 0;
			$this->render_notice(
				sprintf(
					/* translators: %d: imported row count. */
					__( 'CSV import completed. Imported records: %d.', 'super-mechanic' ),
					$count
				),
				'success'
			);
		}
	}

	/**
	 * Render list page.
	 *
	 * @return void
	 */
	protected function render_list_page() {
		$business_id = $this->get_current_business_filter();
		$status      = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page        = max( 1, isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1 );
		$per_page    = 20;
		$args        = array(
			'business_id' => $business_id,
			'status'      => in_array( $status, array( 'active', 'inactive' ), true ) ? $status : '',
			'search'      => $search,
			'page'        => $page,
			'per_page'    => $per_page,
			'orderby'     => 'created_at',
			'order'       => 'DESC',
		);
		$records     = $this->catalog_service->list_catalog_vehicles( $args );
		$total       = $this->catalog_service->count_catalog_vehicles( $args );
		$businesses  = $this->get_business_options();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Vehicle Catalog', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Manage reusable business-scoped vehicle catalog records.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'action' => 'new', 'business_id' => $business_id ) ) ) . '" class="button button-primary">' . esc_html__( 'Create catalog vehicle', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';

		$this->render_filters( $businesses, $business_id, $status, $search );
		$this->render_import_section( $businesses, $business_id );
		$this->render_table( $records, $businesses );
		$this->render_pagination( $total, $per_page, $page, $business_id, $status, $search );
		echo '</div>';
	}

	/**
	 * Render form page.
	 *
	 * @param array<string,mixed> $catalog Catalog record.
	 * @param bool                $is_edit Whether editing.
	 * @return void
	 */
	protected function render_form_page( $catalog = array(), $is_edit = false ) {
		$defaults = array(
			'id'           => 0,
			'business_id'  => $this->get_current_business_filter(),
			'make'         => '',
			'model'        => '',
			'year'         => '',
			'trim_version' => '',
			'body_type'    => '',
			'fuel_type'    => '',
			'transmission' => '',
			'engine'       => '',
			'notes'        => '',
			'status'       => 'active',
		);
		$stored   = get_transient( $this->get_form_transient_key() );
		if ( is_array( $stored ) ) {
			$catalog = array_merge( $catalog, $stored );
			delete_transient( $this->get_form_transient_key() );
		}
		$catalog    = wp_parse_args( $catalog, $defaults );
		$businesses = $this->get_business_options();
		$title      = $is_edit ? __( 'Edit catalog vehicle', 'super-mechanic' ) : __( 'Create catalog vehicle', 'super-mechanic' );

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Catalog records are reusable templates only; this page does not create customer vehicles.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a href="' . esc_url( $this->get_page_url( array( 'business_id' => absint( $catalog['business_id'] ) ) ) ) . '" class="button button-secondary">' . esc_html__( 'Back to catalog', 'super-mechanic' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="sm-card sm-form-card">';
		echo '<form method="post" action="' . esc_url( $this->get_page_url( $is_edit ? array( 'action' => 'edit', 'id' => absint( $catalog['id'] ), 'business_id' => absint( $catalog['business_id'] ) ) : array( 'action' => 'new', 'business_id' => absint( $catalog['business_id'] ) ) ) ) . '">';
		wp_nonce_field( 'sm_save_vehicle_catalog', 'sm_vehicle_catalog_nonce' );
		echo '<input type="hidden" name="sm_vehicle_catalog_operation" value="' . esc_attr( $is_edit ? 'update' : 'create' ) . '" />';
		echo '<input type="hidden" name="catalog_id" value="' . esc_attr( (string) absint( $catalog['id'] ) ) . '" />';
		echo '<table class="form-table" role="presentation">';
		$this->render_business_select_row( absint( $catalog['business_id'] ), $businesses );
		$this->render_text_row( 'make', __( 'Make', 'super-mechanic' ), (string) $catalog['make'], true );
		$this->render_text_row( 'model', __( 'Model', 'super-mechanic' ), (string) $catalog['model'], true );
		$this->render_year_row( $catalog['year'] );
		$this->render_text_row( 'trim_version', __( 'Trim/Version', 'super-mechanic' ), (string) $catalog['trim_version'], false );
		$this->render_text_row( 'body_type', __( 'Body Type', 'super-mechanic' ), (string) $catalog['body_type'], false );
		$this->render_text_row( 'fuel_type', __( 'Fuel', 'super-mechanic' ), (string) $catalog['fuel_type'], false );
		$this->render_text_row( 'transmission', __( 'Transmission', 'super-mechanic' ), (string) $catalog['transmission'], false );
		$this->render_text_row( 'engine', __( 'Engine', 'super-mechanic' ), (string) $catalog['engine'], false );
		$this->render_textarea_row( 'notes', __( 'Notes', 'super-mechanic' ), (string) $catalog['notes'] );
		$this->render_status_row( (string) $catalog['status'] );
		echo '</table>';
		echo '<div class="sm-form-actions">';
		submit_button( $is_edit ? __( 'Update catalog vehicle', 'super-mechanic' ) : __( 'Create catalog vehicle', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Handle create/update.
	 *
	 * @param bool $is_update Whether update.
	 * @return void
	 */
	protected function handle_save_action( $is_update ) {
		check_admin_referer( 'sm_save_vehicle_catalog', 'sm_vehicle_catalog_nonce' );

		$catalog_id = isset( $_POST['catalog_id'] ) ? absint( wp_unslash( $_POST['catalog_id'] ) ) : 0;
		$data       = $this->read_payload_from_post();
		$result     = $is_update ? $this->catalog_service->update_catalog_vehicle( $catalog_id, $data ) : $this->catalog_service->create_catalog_vehicle( $data );

		if ( is_wp_error( $result ) ) {
			$this->store_form_state( $data );
			$this->store_errors( $result );
			$this->redirect(
				$is_update
					? array(
						'action'      => 'edit',
						'id'          => $catalog_id,
						'business_id' => absint( $data['business_id'] ),
						'sm_notice'   => 'error',
					)
					: array(
						'action'      => 'new',
						'business_id' => absint( $data['business_id'] ),
						'sm_notice'   => 'error',
					)
			);
		}

		$this->redirect(
			array(
				'business_id' => absint( $data['business_id'] ),
				'sm_notice'   => $is_update ? 'updated' : 'created',
			)
		);
	}

	/**
	 * Handle deactivate action.
	 *
	 * @return void
	 */
	protected function handle_deactivate_action() {
		$catalog_id  = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$business_id = isset( $_GET['business_id'] ) ? absint( wp_unslash( $_GET['business_id'] ) ) : 0;
		check_admin_referer( 'sm_deactivate_vehicle_catalog_' . $catalog_id );

		$result = $this->catalog_service->deactivate_catalog_vehicle( $catalog_id, $business_id );
		if ( is_wp_error( $result ) ) {
			$this->store_errors( $result );
			$this->redirect(
				array(
					'business_id' => $business_id,
					'sm_notice'   => 'error',
				)
			);
		}

		$this->redirect(
			array(
				'business_id' => $business_id,
				'sm_notice'   => 'deactivated',
			)
		);
	}

	/**
	 * Handle CSV dry-run action.
	 *
	 * @return void
	 */
	protected function handle_import_dry_run_action() {
		check_admin_referer( 'sm_vehicle_catalog_import', 'sm_vehicle_catalog_import_nonce' );

		$business_id = isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0;
		if ( ! $this->user_can_access_business( $business_id ) ) {
			$this->store_plain_errors( array( __( 'No puedes importar catálogo para el negocio seleccionado.', 'super-mechanic' ) ) );
			$this->redirect(
				array(
					'business_id' => $business_id,
					'sm_notice'   => 'error',
				)
			);
		}

		$file_path = $this->get_uploaded_csv_file_path();
		if ( is_wp_error( $file_path ) ) {
			$this->store_errors( $file_path );
			$this->redirect(
				array(
					'business_id' => $business_id,
					'sm_notice'   => 'error',
				)
			);
		}

		$report = $this->catalog_import_service->dry_run( $file_path, $business_id );
		if ( is_wp_error( $report ) ) {
			$this->store_errors( $report );
			$this->redirect(
				array(
					'business_id' => $business_id,
					'sm_notice'   => 'error',
				)
			);
		}

		set_transient( $this->get_import_transient_key(), $report, 20 * MINUTE_IN_SECONDS );
		$this->redirect(
			array(
				'business_id' => $business_id,
				'sm_notice'   => 'import_preview',
			)
		);
	}

	/**
	 * Handle confirmed CSV import action.
	 *
	 * @return void
	 */
	protected function handle_import_confirm_action() {
		check_admin_referer( 'sm_vehicle_catalog_import_confirm', 'sm_vehicle_catalog_import_confirm_nonce' );

		$business_id = isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0;
		if ( ! $this->user_can_access_business( $business_id ) ) {
			$this->store_plain_errors( array( __( 'No puedes importar catálogo para el negocio seleccionado.', 'super-mechanic' ) ) );
			$this->redirect(
				array(
					'business_id' => $business_id,
					'sm_notice'   => 'error',
				)
			);
		}

		$preview = get_transient( $this->get_import_transient_key() );
		if ( ! is_array( $preview ) || absint( $preview['business_id'] ?? 0 ) !== $business_id || empty( $preview['valid_records'] ) || ! is_array( $preview['valid_records'] ) ) {
			$this->store_plain_errors( array( __( 'No hay una vista previa CSV válida para confirmar.', 'super-mechanic' ) ) );
			$this->redirect(
				array(
					'business_id' => $business_id,
					'sm_notice'   => 'error',
				)
			);
		}

		$result = $this->catalog_import_service->import_rows( $preview['valid_records'], $business_id );
		delete_transient( $this->get_import_transient_key() );
		set_transient( $this->get_import_result_transient_key(), $result, MINUTE_IN_SECONDS );

		if ( ! empty( $result['import_errors'] ) && empty( $result['imported_rows'] ) ) {
			$this->store_import_errors( $result['import_errors'] );
			$this->redirect(
				array(
					'business_id' => $business_id,
					'sm_notice'   => 'error',
				)
			);
		}

		$this->redirect(
			array(
				'business_id' => $business_id,
				'sm_notice'   => 'imported',
			)
		);
	}

	/**
	 * Read payload from POST.
	 *
	 * @return array<string,mixed>
	 */
	protected function read_payload_from_post() {
		return array(
			'business_id'  => isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0,
			'make'         => isset( $_POST['make'] ) ? sanitize_text_field( wp_unslash( $_POST['make'] ) ) : '',
			'model'        => isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '',
			'year'         => isset( $_POST['year'] ) ? absint( wp_unslash( $_POST['year'] ) ) : 0,
			'trim_version' => isset( $_POST['trim_version'] ) ? sanitize_text_field( wp_unslash( $_POST['trim_version'] ) ) : '',
			'body_type'    => isset( $_POST['body_type'] ) ? sanitize_text_field( wp_unslash( $_POST['body_type'] ) ) : '',
			'fuel_type'    => isset( $_POST['fuel_type'] ) ? sanitize_text_field( wp_unslash( $_POST['fuel_type'] ) ) : '',
			'transmission' => isset( $_POST['transmission'] ) ? sanitize_text_field( wp_unslash( $_POST['transmission'] ) ) : '',
			'engine'       => isset( $_POST['engine'] ) ? sanitize_text_field( wp_unslash( $_POST['engine'] ) ) : '',
			'notes'        => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			'status'       => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active',
		);
	}

	/**
	 * Render filters.
	 *
	 * @param array<int,array<string,mixed>> $businesses  Business rows.
	 * @param int                            $business_id Business ID.
	 * @param string                         $status      Status.
	 * @param string                         $search      Search.
	 * @return void
	 */
	protected function render_filters( array $businesses, $business_id, $status, $search ) {
		echo '<div class="sm-card sm-filter-card sm-section">';
		echo '<form method="get" class="sm-filter-grid">';
		echo '<input type="hidden" name="page" value="super-mechanic-vehicle-catalog" />';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Business', 'super-mechanic' ) . '</span><select name="business_id">';
		foreach ( $businesses as $business ) {
			$row_id = isset( $business['id'] ) ? absint( $business['id'] ) : 0;
			if ( $row_id <= 0 ) {
				continue;
			}
			if ( ! $this->user_can_access_business( $row_id ) ) {
				continue;
			}
			echo '<option value="' . esc_attr( (string) $row_id ) . '"' . selected( absint( $business_id ), $row_id, false ) . '>' . esc_html( $this->format_business_label( $business ) ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Status', 'super-mechanic' ) . '</span><select name="status">';
		echo '<option value="">' . esc_html__( 'All statuses', 'super-mechanic' ) . '</option>';
		echo '<option value="active"' . selected( $status, 'active', false ) . '>' . esc_html__( 'Active', 'super-mechanic' ) . '</option>';
		echo '<option value="inactive"' . selected( $status, 'inactive', false ) . '>' . esc_html__( 'Inactive', 'super-mechanic' ) . '</option>';
		echo '</select></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Search', 'super-mechanic' ) . '</span><input type="search" name="s" value="' . esc_attr( $search ) . '" /></label>';
		echo '<div class="sm-form-actions"><button type="submit" class="button button-secondary">' . esc_html__( 'Filter', 'super-mechanic' ) . '</button></div>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render CSV import section.
	 *
	 * @param array<int,array<string,mixed>> $businesses  Business rows.
	 * @param int                            $business_id Business ID.
	 * @return void
	 */
	protected function render_import_section( array $businesses, $business_id ) {
		$preview = get_transient( $this->get_import_transient_key() );

		echo '<div class="sm-card sm-section">';
		echo '<h2>' . esc_html__( 'CSV import', 'super-mechanic' ) . '</h2>';
		echo '<p>' . esc_html__( 'Import reusable vehicle catalog records only. Required columns: make, model, year.', 'super-mechanic' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $this->get_page_url( array( 'business_id' => absint( $business_id ) ) ) ) . '">';
		wp_nonce_field( 'sm_vehicle_catalog_import', 'sm_vehicle_catalog_import_nonce' );
		echo '<input type="hidden" name="sm_vehicle_catalog_operation" value="import_dry_run" />';
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="sm_csv_business_id">' . esc_html__( 'Business', 'super-mechanic' ) . '</label></th><td>';
		echo '<select name="business_id" id="sm_csv_business_id" required>';
		foreach ( $businesses as $business ) {
			$row_id = isset( $business['id'] ) ? absint( $business['id'] ) : 0;
			if ( $row_id <= 0 || ! $this->user_can_access_business( $row_id ) ) {
				continue;
			}
			echo '<option value="' . esc_attr( (string) $row_id ) . '"' . selected( absint( $business_id ), $row_id, false ) . '>' . esc_html( $this->format_business_label( $business ) ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="sm_vehicle_catalog_csv">' . esc_html__( 'CSV file', 'super-mechanic' ) . '</label></th><td>';
		echo '<input type="file" id="sm_vehicle_catalog_csv" name="sm_vehicle_catalog_csv" accept=".csv,text/csv" required />';
		echo '<p class="description">' . esc_html__( 'Optional columns: trim_version, body_type, fuel_type, transmission, engine, notes, status.', 'super-mechanic' ) . '</p>';
		echo '</td></tr>';
		echo '</table>';
		submit_button( __( 'Dry-run CSV', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';

		if ( is_array( $preview ) && absint( $preview['business_id'] ?? 0 ) === absint( $business_id ) ) {
			$this->render_import_preview( $preview, $business_id );
		}

		echo '</div>';
	}

	/**
	 * Render import dry-run preview.
	 *
	 * @param array<string,mixed> $preview     Preview report.
	 * @param int                 $business_id Business ID.
	 * @return void
	 */
	protected function render_import_preview( array $preview, $business_id ) {
		echo '<hr />';
		echo '<h3>' . esc_html__( 'Dry-run result', 'super-mechanic' ) . '</h3>';
		echo '<p>';
		echo esc_html(
			sprintf(
				/* translators: 1: total rows, 2: valid rows, 3: invalid rows. */
				__( 'Total rows: %1$d. Valid rows: %2$d. Invalid rows: %3$d.', 'super-mechanic' ),
				absint( $preview['total_rows'] ?? 0 ),
				absint( $preview['valid_rows'] ?? 0 ),
				absint( $preview['invalid_rows'] ?? 0 )
			)
		);
		echo '</p>';

		$this->render_import_errors( $preview );
		$this->render_import_preview_rows( isset( $preview['preview_rows'] ) && is_array( $preview['preview_rows'] ) ? $preview['preview_rows'] : array() );

		if ( ! empty( $preview['valid_records'] ) ) {
			echo '<form method="post" action="' . esc_url( $this->get_page_url( array( 'business_id' => absint( $business_id ) ) ) ) . '">';
			wp_nonce_field( 'sm_vehicle_catalog_import_confirm', 'sm_vehicle_catalog_import_confirm_nonce' );
			echo '<input type="hidden" name="sm_vehicle_catalog_operation" value="import_confirm" />';
			echo '<input type="hidden" name="business_id" value="' . esc_attr( (string) absint( $business_id ) ) . '" />';
			submit_button( __( 'Import valid rows', 'super-mechanic' ), 'primary', 'submit', false );
			echo '</form>';
		}
	}

	/**
	 * Render import validation errors.
	 *
	 * @param array<string,mixed> $preview Preview report.
	 * @return void
	 */
	protected function render_import_errors( array $preview ) {
		$header_errors = isset( $preview['header_errors'] ) && is_array( $preview['header_errors'] ) ? $preview['header_errors'] : array();
		$row_errors    = isset( $preview['row_errors'] ) && is_array( $preview['row_errors'] ) ? $preview['row_errors'] : array();

		if ( empty( $header_errors ) && empty( $row_errors ) ) {
			return;
		}

		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Validation errors', 'super-mechanic' ) . '</strong></p>';
		if ( ! empty( $header_errors ) ) {
			echo '<ul>';
			foreach ( $header_errors as $message ) {
				echo '<li>' . esc_html( (string) $message ) . '</li>';
			}
			echo '</ul>';
		}

		if ( ! empty( $row_errors ) ) {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Row', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Errors', 'super-mechanic' ) . '</th></tr></thead><tbody>';
			foreach ( $row_errors as $row_error ) {
				$messages = isset( $row_error['errors'] ) && is_array( $row_error['errors'] ) ? $row_error['errors'] : array();
				echo '<tr><td>' . esc_html( (string) absint( $row_error['row_number'] ?? 0 ) ) . '</td><td>' . esc_html( implode( ' ', array_map( 'strval', $messages ) ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	/**
	 * Render valid preview rows.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @return void
	 */
	protected function render_import_preview_rows( array $rows ) {
		if ( empty( $rows ) ) {
			return;
		}

		$columns = array( 'make', 'model', 'year', 'trim_version', 'body_type', 'fuel_type', 'transmission', 'engine', 'status' );
		echo '<h4>' . esc_html__( 'Preview rows', 'super-mechanic' ) . '</h4>';
		echo '<div class="sm-table-wrap"><table class="widefat striped"><thead><tr>';
		foreach ( $columns as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $columns as $column ) {
				echo '<td>' . esc_html( isset( $row[ $column ] ) ? (string) $row[ $column ] : '' ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render table.
	 *
	 * @param array<int,array<string,mixed>> $records    Catalog records.
	 * @param array<int,array<string,mixed>> $businesses Business rows.
	 * @return void
	 */
	protected function render_table( array $records, array $businesses ) {
		$business_labels = $this->build_business_labels( $businesses );
		echo '<div class="sm-card sm-section">';
		echo '<div class="sm-table-wrap">';
		echo '<table class="sm-table widefat striped">';
		echo '<thead><tr>';
		$columns = array( 'ID', 'Business', 'Make', 'Model', 'Year', 'Trim/Version', 'Body Type', 'Fuel', 'Transmission', 'Status', 'Actions' );
		foreach ( $columns as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( empty( $records ) ) {
			echo '<tr><td colspan="11">' . esc_html__( 'No catalog vehicles found for the selected filters.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $records as $record ) {
				$this->render_table_row( $record, $business_labels );
			}
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render table row.
	 *
	 * @param array<string,mixed> $record          Record.
	 * @param array<int,string>   $business_labels Business labels.
	 * @return void
	 */
	protected function render_table_row( array $record, array $business_labels ) {
		$catalog_id  = isset( $record['id'] ) ? absint( $record['id'] ) : 0;
		$business_id = isset( $record['business_id'] ) ? absint( $record['business_id'] ) : 0;
		$status      = isset( $record['status'] ) ? sanitize_key( (string) $record['status'] ) : '';
		$edit_url    = $this->get_page_url(
			array(
				'action'      => 'edit',
				'id'          => $catalog_id,
				'business_id' => $business_id,
			)
		);
		$deactivate_url = wp_nonce_url(
			$this->get_page_url(
				array(
					'action'      => 'deactivate',
					'id'          => $catalog_id,
					'business_id' => $business_id,
				)
			),
			'sm_deactivate_vehicle_catalog_' . $catalog_id
		);

		echo '<tr>';
		echo '<td>' . esc_html( (string) $catalog_id ) . '</td>';
		echo '<td>' . esc_html( isset( $business_labels[ $business_id ] ) ? $business_labels[ $business_id ] : sprintf( 'Business #%d', $business_id ) ) . '</td>';
		echo '<td><strong>' . esc_html( isset( $record['make'] ) ? (string) $record['make'] : '' ) . '</strong></td>';
		echo '<td>' . esc_html( isset( $record['model'] ) ? (string) $record['model'] : '' ) . '</td>';
		echo '<td>' . esc_html( ! empty( $record['year'] ) ? (string) absint( $record['year'] ) : '-' ) . '</td>';
		echo '<td>' . esc_html( ! empty( $record['trim_version'] ) ? (string) $record['trim_version'] : '-' ) . '</td>';
		echo '<td>' . esc_html( ! empty( $record['body_type'] ) ? (string) $record['body_type'] : '-' ) . '</td>';
		echo '<td>' . esc_html( ! empty( $record['fuel_type'] ) ? (string) $record['fuel_type'] : '-' ) . '</td>';
		echo '<td>' . esc_html( ! empty( $record['transmission'] ) ? (string) $record['transmission'] : '-' ) . '</td>';
		echo '<td>' . wp_kses_post( $this->render_status_badge( $status ) ) . '</td>';
		echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'super-mechanic' ) . '</a>';
		if ( 'active' === $status ) {
			echo ' | <a href="' . esc_url( $deactivate_url ) . '">' . esc_html__( 'Deactivate', 'super-mechanic' ) . '</a>';
		}
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Render pagination links.
	 *
	 * @param int    $total       Total records.
	 * @param int    $per_page    Per page.
	 * @param int    $page        Current page.
	 * @param int    $business_id Business ID.
	 * @param string $status      Status.
	 * @param string $search      Search.
	 * @return void
	 */
	protected function render_pagination( $total, $per_page, $page, $business_id, $status, $search ) {
		$total_pages = (int) ceil( max( 0, absint( $total ) ) / max( 1, absint( $per_page ) ) );
		if ( $total_pages <= 1 ) {
			return;
		}

		$base_args = array(
			'business_id' => absint( $business_id ),
			'status'      => sanitize_key( $status ),
			's'           => sanitize_text_field( $search ),
		);

		echo '<div class="tablenav"><div class="tablenav-pages">';
		for ( $index = 1; $index <= $total_pages; $index++ ) {
			$url   = $this->get_page_url( array_merge( $base_args, array( 'paged' => $index ) ) );
			$class = $index === absint( $page ) ? ' class="page-numbers current"' : ' class="page-numbers"';
			echo '<a' . $class . ' href="' . esc_url( $url ) . '">' . esc_html( (string) $index ) . '</a> ';
		}
		echo '</div></div>';
	}

	/**
	 * Render business select row.
	 *
	 * @param int                            $selected_business_id Selected business.
	 * @param array<int,array<string,mixed>> $businesses           Business rows.
	 * @return void
	 */
	protected function render_business_select_row( $selected_business_id, array $businesses ) {
		echo '<tr><th scope="row"><label for="business_id">' . esc_html__( 'Business', 'super-mechanic' ) . '</label></th><td>';
		echo '<select name="business_id" id="business_id" required>';
		foreach ( $businesses as $business ) {
			$business_id = isset( $business['id'] ) ? absint( $business['id'] ) : 0;
			if ( $business_id <= 0 ) {
				continue;
			}
			if ( ! $this->user_can_access_business( $business_id ) ) {
				continue;
			}
			echo '<option value="' . esc_attr( (string) $business_id ) . '"' . selected( absint( $selected_business_id ), $business_id, false ) . '>' . esc_html( $this->format_business_label( $business ) ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	/**
	 * Render text row.
	 *
	 * @param string $name     Field name.
	 * @param string $label    Label.
	 * @param string $value    Value.
	 * @param bool   $required Required.
	 * @return void
	 */
	protected function render_text_row( $name, $label, $value, $required ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . ' />';
		echo '</td></tr>';
	}

	/**
	 * Render year row.
	 *
	 * @param mixed $value Value.
	 * @return void
	 */
	protected function render_year_row( $value ) {
		echo '<tr><th scope="row"><label for="year">' . esc_html__( 'Year', 'super-mechanic' ) . '</label></th><td>';
		echo '<input type="number" class="small-text" id="year" name="year" value="' . esc_attr( ! empty( $value ) ? (string) absint( $value ) : '' ) . '" min="1900" max="' . esc_attr( (string) ( (int) gmdate( 'Y' ) + 1 ) ) . '" />';
		echo '</td></tr>';
	}

	/**
	 * Render textarea row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	protected function render_textarea_row( $name, $label, $value ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<textarea class="large-text" rows="5" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
		echo '</td></tr>';
	}

	/**
	 * Render status row.
	 *
	 * @param string $status Status.
	 * @return void
	 */
	protected function render_status_row( $status ) {
		echo '<tr><th scope="row"><label for="status">' . esc_html__( 'Status', 'super-mechanic' ) . '</label></th><td>';
		echo '<select id="status" name="status">';
		echo '<option value="active"' . selected( $status, 'active', false ) . '>' . esc_html__( 'Active', 'super-mechanic' ) . '</option>';
		echo '<option value="inactive"' . selected( $status, 'inactive', false ) . '>' . esc_html__( 'Inactive', 'super-mechanic' ) . '</option>';
		echo '</select></td></tr>';
	}

	/**
	 * Get business options.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_business_options() {
		$businesses = $this->business_service->get_businesses(
			array(
				'status'   => 'active',
				'per_page' => 200,
				'page'     => 1,
				'orderby'  => 'name',
				'order'    => 'ASC',
			)
		);

		if ( ! is_array( $businesses ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$businesses,
				function ( $business ) {
					$business_id = is_array( $business ) && isset( $business['id'] ) ? absint( $business['id'] ) : 0;
					return $business_id > 0 && $this->user_can_access_business( $business_id );
				}
			)
		);
	}

	/**
	 * Build business labels map.
	 *
	 * @param array<int,array<string,mixed>> $businesses Business rows.
	 * @return array<int,string>
	 */
	protected function build_business_labels( array $businesses ) {
		$labels = array();
		foreach ( $businesses as $business ) {
			$business_id = isset( $business['id'] ) ? absint( $business['id'] ) : 0;
			if ( $business_id > 0 ) {
				$labels[ $business_id ] = $this->format_business_label( $business );
			}
		}

		return $labels;
	}

	/**
	 * Format business label.
	 *
	 * @param array<string,mixed> $business Business row.
	 * @return string
	 */
	protected function format_business_label( array $business ) {
		$business_id = isset( $business['id'] ) ? absint( $business['id'] ) : 0;
		$name        = isset( $business['name'] ) ? trim( (string) $business['name'] ) : '';

		return trim( '#' . $business_id . ' ' . $name );
	}

	/**
	 * Render status badge.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	protected function render_status_badge( $status ) {
		if ( 'active' === $status ) {
			return '<span class="sm-badge sm-badge-success">' . esc_html__( 'Active', 'super-mechanic' ) . '</span>';
		}

		return '<span class="sm-badge sm-badge-neutral">' . esc_html__( 'Inactive', 'super-mechanic' ) . '</span>';
	}

	/**
	 * Current business filter.
	 *
	 * @return int
	 */
	protected function get_current_business_filter() {
		$requested = isset( $_GET['business_id'] ) ? absint( wp_unslash( $_GET['business_id'] ) ) : 0;
		if ( $requested > 0 ) {
			$valid = $this->business_service->resolve_valid_business_id( $requested );
			if ( $valid > 0 && $this->user_can_access_business( $valid ) ) {
				return $valid;
			}
		}

		return absint( $this->business_context_service->resolve_business_id() );
	}

	/**
	 * Whether current user can access a business.
	 *
	 * @param int $business_id Business ID.
	 * @return bool
	 */
	protected function user_can_access_business( $business_id ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return false;
		}

		$allowed_ids = $this->business_context_service->get_user_allowed_business_ids( get_current_user_id() );
		if ( empty( $allowed_ids ) ) {
			return true;
		}

		return in_array( $business_id, array_map( 'absint', $allowed_ids ), true );
	}

	/**
	 * Ensure permission.
	 *
	 * @return void
	 */
	protected function ensure_permissions() {
		if ( ! current_user_can( 'sm_manage_vehicles' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage the vehicle catalog.', 'super-mechanic' ) );
		}
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
	 * Store plain error messages.
	 *
	 * @param string[] $messages Messages.
	 * @return void
	 */
	protected function store_plain_errors( array $messages ) {
		set_transient( $this->get_error_transient_key(), $messages, MINUTE_IN_SECONDS );
	}

	/**
	 * Store import errors.
	 *
	 * @param array<int,array<string,mixed>> $errors Import errors.
	 * @return void
	 */
	protected function store_import_errors( array $errors ) {
		$messages = array();
		foreach ( $errors as $error ) {
			$row        = isset( $error['row_number'] ) ? absint( $error['row_number'] ) : 0;
			$row_errors = isset( $error['errors'] ) && is_array( $error['errors'] ) ? $error['errors'] : array();
			$messages[] = sprintf(
				/* translators: 1: CSV row number, 2: errors. */
				__( 'CSV row %1$d: %2$s', 'super-mechanic' ),
				$row,
				implode( ' ', array_map( 'strval', $row_errors ) )
			);
		}

		$this->store_plain_errors( $messages );
	}

	/**
	 * Get uploaded CSV temp file path after security checks.
	 *
	 * @return string|WP_Error
	 */
	protected function get_uploaded_csv_file_path() {
		if ( empty( $_FILES['sm_vehicle_catalog_csv'] ) || ! is_array( $_FILES['sm_vehicle_catalog_csv'] ) ) {
			return new WP_Error( 'sm_vehicle_catalog_csv_missing', __( 'Debes subir un archivo CSV.', 'super-mechanic' ) );
		}

		$file = $_FILES['sm_vehicle_catalog_csv'];
		$name = isset( $file['name'] ) ? sanitize_file_name( (string) wp_unslash( $file['name'] ) ) : '';
		$tmp  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$error = isset( $file['error'] ) ? absint( $file['error'] ) : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'sm_vehicle_catalog_csv_upload_error', __( 'No se pudo subir el archivo CSV.', 'super-mechanic' ) );
		}

		if ( 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'sm_vehicle_catalog_csv_invalid_type', __( 'El archivo debe tener extensión .csv.', 'super-mechanic' ) );
		}

		if ( '' === $tmp || ! is_uploaded_file( $tmp ) || ! is_readable( $tmp ) ) {
			return new WP_Error( 'sm_vehicle_catalog_csv_temp_unreadable', __( 'No se pudo validar el archivo CSV subido.', 'super-mechanic' ) );
		}

		return $tmp;
	}

	/**
	 * Render notice.
	 *
	 * @param string $message Message.
	 * @param string $type    Type.
	 * @return void
	 */
	protected function render_notice( $message, $type ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible sm-notice-card"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Redirect.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return void
	 */
	protected function redirect( array $args = array() ) {
		wp_safe_redirect( $this->get_page_url( $args ) );
		exit;
	}

	/**
	 * Get page URL.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return string
	 */
	protected function get_page_url( array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'super-mechanic-vehicle-catalog',
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Whether current screen is catalog screen.
	 *
	 * @return bool
	 */
	protected function is_catalog_screen() {
		return isset( $_GET['page'] ) && 'super-mechanic-vehicle-catalog' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Error transient key.
	 *
	 * @return string
	 */
	protected function get_error_transient_key() {
		return 'sm_vehicle_catalog_errors_' . get_current_user_id();
	}

	/**
	 * Form transient key.
	 *
	 * @return string
	 */
	protected function get_form_transient_key() {
		return 'sm_vehicle_catalog_form_' . get_current_user_id();
	}

	/**
	 * Import preview transient key.
	 *
	 * @return string
	 */
	protected function get_import_transient_key() {
		return 'sm_vehicle_catalog_import_' . get_current_user_id();
	}

	/**
	 * Import result transient key.
	 *
	 * @return string
	 */
	protected function get_import_result_transient_key() {
		return 'sm_vehicle_catalog_import_result_' . get_current_user_id();
	}
}
