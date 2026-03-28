<?php
/**
 * Payment finance admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Invoices;

use Super_Mechanic\Helpers\Download_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders dedicated admin finance page for payments.
 */
class Payment_Finance_Admin_Controller {
	/**
	 * Invoice service.
	 *
	 * @var Invoice_Service
	 */
	protected $invoice_service;

	/**
	 * Payment repository.
	 *
	 * @var Payment_Repository
	 */
	protected $payment_repository;

	/**
	 * Download service.
	 *
	 * @var Download_Service
	 */
	protected $download_service;

	/**
	 * Constructor.
	 *
	 * @param Invoice_Service|null    $invoice_service    Invoice service.
	 * @param Payment_Repository|null $payment_repository Payment repository.
	 * @param Download_Service|null   $download_service   Download service.
	 */
	public function __construct( Invoice_Service $invoice_service = null, Payment_Repository $payment_repository = null, Download_Service $download_service = null ) {
		$this->invoice_service    = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->payment_repository = $payment_repository ? $payment_repository : new Payment_Repository();
		$this->download_service   = $download_service ? $download_service : new Download_Service();
	}

	/**
	 * Register hooks placeholder for parity with other controllers.
	 *
	 * @return void
	 */
	public function register_hooks() {
	}

	/**
	 * Render payments finance page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'super-mechanic' ) );
		}

		$list_table = new Payment_Finance_List_Table( $this->payment_repository, $this->invoice_service, $this->download_service );
		$list_table->prepare_items();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Centro financiero - Payments', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Panel dedicado para pagos registrados, trazabilidad invoice ↔ payment y comprobantes seguros.', 'super-mechanic' ) . '</p>';
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
	 * @param Payment_Finance_List_Table $list_table List table.
	 * @return void
	 */
	protected function render_filter_form( Payment_Finance_List_Table $list_table ) {
		$current_method = isset( $_GET['filter_payment_method'] ) ? sanitize_key( wp_unslash( $_GET['filter_payment_method'] ) ) : '';
		$methods        = array(
			''         => __( 'Todos los metodos', 'super-mechanic' ),
			'cash'     => __( 'Cash', 'super-mechanic' ),
			'transfer' => __( 'Transfer', 'super-mechanic' ),
			'card'     => __( 'Card', 'super-mechanic' ),
			'check'    => __( 'Check', 'super-mechanic' ),
			'other'    => __( 'Other', 'super-mechanic' ),
		);
		$date_from      = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to        = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

		echo '<form method="get" class="sm-card sm-section" style="margin-bottom:16px;">';
		echo '<input type="hidden" name="page" value="super-mechanic-financial-payments" />';
		echo '<div class="sm-inline-filters">';
		echo '<label for="sm-finance-payment-method" class="screen-reader-text">' . esc_html__( 'Filtrar por metodo', 'super-mechanic' ) . '</label>';
		echo '<select id="sm-finance-payment-method" name="filter_payment_method">';

		foreach ( $methods as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current_method, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select>';
		echo '<label for="sm-finance-payment-date-from" class="screen-reader-text">' . esc_html__( 'Fecha desde', 'super-mechanic' ) . '</label>';
		echo '<input id="sm-finance-payment-date-from" type="date" name="date_from" value="' . esc_attr( $date_from ) . '" />';
		echo '<label for="sm-finance-payment-date-to" class="screen-reader-text">' . esc_html__( 'Fecha hasta', 'super-mechanic' ) . '</label>';
		echo '<input id="sm-finance-payment-date-to" type="date" name="date_to" value="' . esc_attr( $date_to ) . '" />';
		submit_button( __( 'Filtrar', 'super-mechanic' ), 'secondary', 'filter_action', false );
		echo '</div>';
		$list_table->search_box( __( 'Buscar payments', 'super-mechanic' ), 'sm-finance-payments' );
		echo '</form>';
	}
}
