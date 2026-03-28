<?php
/**
 * Invoice finance admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Helpers\Download_Service;
use Super_Mechanic\Helpers\PDF_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders dedicated admin finance page for invoices.
 */
class Invoice_Finance_Admin_Controller {
	/**
	 * Invoice service.
	 *
	 * @var Invoice_Service
	 */
	protected $service;

	/**
	 * PDF service.
	 *
	 * @var PDF_Service
	 */
	protected $pdf_service;

	/**
	 * Download service.
	 *
	 * @var Download_Service
	 */
	protected $download_service;

	/**
	 * Constructor.
	 *
	 * @param Invoice_Service|null  $service          Invoice service.
	 * @param PDF_Service|null      $pdf_service      PDF service.
	 * @param Download_Service|null $download_service Download service.
	 */
	public function __construct( Invoice_Service $service = null, PDF_Service $pdf_service = null, Download_Service $download_service = null ) {
		$this->service          = $service ? $service : new Invoice_Service();
		$this->pdf_service      = $pdf_service ? $pdf_service : new PDF_Service( $this->service );
		$this->download_service = $download_service ? $download_service : new Download_Service();
	}

	/**
	 * Register hooks placeholder for parity with other controllers.
	 *
	 * @return void
	 */
	public function register_hooks() {
	}

	/**
	 * Render invoices finance page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'super-mechanic' ) );
		}

		$list_table = new Invoice_Finance_List_Table( $this->service, $this->pdf_service, $this->download_service );
		$list_table->prepare_items();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Centro financiero - Invoices', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Panel dedicado para controlar facturación, estado de cobro y acciones financieras por invoice.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<span class="sm-badge sm-badge-primary">' . esc_html__( 'Finance', 'super-mechanic' ) . '</span>';
		echo '</div>';

		$this->render_filter_form( $list_table );

		echo '<div class="sm-card sm-section">';
		echo '<div class="sm-table-wrap sm-list-table-wrap">';
		$list_table->display();
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render filters form.
	 *
	 * @param Invoice_Finance_List_Table $list_table List table.
	 * @return void
	 */
	protected function render_filter_form( Invoice_Finance_List_Table $list_table ) {
		$current_status = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : '';
		$status_options = array(
			''               => __( 'Todos los estados', 'super-mechanic' ),
			'draft'          => __( 'Draft', 'super-mechanic' ),
			'issued'         => __( 'Issued', 'super-mechanic' ),
			'partially_paid' => __( 'Partially paid', 'super-mechanic' ),
			'paid'           => __( 'Paid', 'super-mechanic' ),
			'overdue'        => __( 'Overdue', 'super-mechanic' ),
			'cancelled'      => __( 'Cancelled', 'super-mechanic' ),
			'refunded'       => __( 'Refunded', 'super-mechanic' ),
		);

		echo '<form method="get" class="sm-card sm-section" style="margin-bottom:16px;">';
		echo '<input type="hidden" name="page" value="super-mechanic-financial-invoices" />';
		echo '<div class="sm-inline-filters">';
		echo '<label for="sm-finance-invoice-status" class="screen-reader-text">' . esc_html__( 'Filtrar por estado', 'super-mechanic' ) . '</label>';
		echo '<select id="sm-finance-invoice-status" name="filter_status">';

		foreach ( $status_options as $status => $label ) {
			echo '<option value="' . esc_attr( $status ) . '" ' . selected( $current_status, $status, false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select>';
		submit_button( __( 'Filtrar', 'super-mechanic' ), 'secondary', 'filter_action', false );
		echo '</div>';
		$list_table->search_box( __( 'Buscar invoices', 'super-mechanic' ), 'sm-finance-invoices' );
		echo '</form>';
	}
}
