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
		add_action( 'admin_init', array( $this, 'maybe_handle_actions' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Handle direct payment actions.
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! $this->is_payments_screen() || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage payments.', 'super-mechanic' ) );
		}

		$operation = isset( $_POST['sm_payment_finance_operation'] ) ? sanitize_key( wp_unslash( $_POST['sm_payment_finance_operation'] ) ) : '';

		if ( 'add_payment' !== $operation ) {
			return;
		}

		check_admin_referer( 'sm_finance_add_payment', 'sm_finance_add_payment_nonce' );

		$invoice_id = isset( $_POST['invoice_id'] ) ? absint( wp_unslash( $_POST['invoice_id'] ) ) : 0;
		$result     = $this->invoice_service->add_payment(
			$invoice_id,
			array(
				'payment_date'   => isset( $_POST['payment_date'] ) ? wp_unslash( $_POST['payment_date'] ) : '',
				'amount'         => isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : '',
				'payment_method' => isset( $_POST['payment_method'] ) ? wp_unslash( $_POST['payment_method'] ) : 'cash',
				'reference'      => isset( $_POST['reference'] ) ? wp_unslash( $_POST['reference'] ) : '',
				'notes'          => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			set_transient( $this->get_error_transient_key(), $result->get_error_messages(), MINUTE_IN_SECONDS );
			$this->redirect( array( 'sm_notice' => 'payment_error' ) );
		}

		$this->redirect( array( 'sm_notice' => 'payment_added' ) );
	}

	/**
	 * Render notices for direct payment actions.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! $this->is_payments_screen() ) {
			return;
		}

		$notice = isset( $_GET['sm_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_notice'] ) ) : '';

		if ( 'payment_added' === $notice ) {
			echo '<div class="notice notice-success is-dismissible sm-notice-card"><p>' . esc_html__( 'Payment registered successfully from the finance center.', 'super-mechanic' ) . '</p></div>';
		}

		if ( 'payment_error' === $notice ) {
			$messages = get_transient( $this->get_error_transient_key() );
			delete_transient( $this->get_error_transient_key() );

			if ( is_array( $messages ) ) {
				foreach ( $messages as $message ) {
					echo '<div class="notice notice-error is-dismissible sm-notice-card"><p>' . esc_html( $message ) . '</p></div>';
				}
			}
		}
	}

	/**
	 * Render payments finance page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_processes' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		$list_table = new Payment_Finance_List_Table( $this->payment_repository, $this->invoice_service, $this->download_service );
		$list_table->prepare_items();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Finance Center - Payments', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Dedicated panel for recorded payments, invoice-payment traceability, and secure receipts.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<a class="button button-primary" href="' . esc_url( add_query_arg( array( 'page' => 'super-mechanic-financial-invoices' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Open invoices', 'super-mechanic' ) . '</a>';
		echo '<a class="button button-secondary" href="' . esc_url( add_query_arg( array( 'page' => 'super-mechanic-processes' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Open processes', 'super-mechanic' ) . '</a>';
		echo '<span class="sm-badge sm-badge-primary">' . esc_html__( 'Finance', 'super-mechanic' ) . '</span>';
		echo '</div>';
		echo '</div>';

		$this->render_direct_register_form();
		$this->render_filter_form( $list_table );

		echo '<div class="sm-card sm-section">';
		echo '<div class="sm-table-wrap sm-list-table-wrap">';
		$list_table->display();
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render direct payment registration form.
	 *
	 * @return void
	 */
	protected function render_direct_register_form() {
		$invoices = $this->invoice_service->get_invoices(
			array(
				'per_page' => 100,
				'page'     => 1,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		echo '<form method="post" class="sm-card sm-section" style="margin-bottom:16px;">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Register direct payment', 'super-mechanic' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Register payments without entering through processes, preserving validation and persistence via Invoice/Payment Service.', 'super-mechanic' ) . '</p>';
		echo '<input type="hidden" name="sm_payment_finance_operation" value="add_payment" />';
		wp_nonce_field( 'sm_finance_add_payment', 'sm_finance_add_payment_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="sm-finance-invoice-id">' . esc_html__( 'Invoice', 'super-mechanic' ) . '</label></th><td><select id="sm-finance-invoice-id" name="invoice_id" required>';
		echo '<option value="0">' . esc_html__( 'Select an invoice', 'super-mechanic' ) . '</option>';

		foreach ( $invoices as $invoice ) {
			$invoice_id = isset( $invoice['id'] ) ? absint( $invoice['id'] ) : 0;
			if ( $invoice_id <= 0 ) {
				continue;
			}

			$invoice_number = ! empty( $invoice['invoice_number'] ) ? (string) $invoice['invoice_number'] : '#' . $invoice_id;
			$client_name    = ! empty( $invoice['client_name'] ) ? (string) $invoice['client_name'] : __( 'Unidentified client', 'super-mechanic' );
			$label          = sprintf( '%s — %s', $invoice_number, $client_name );

			echo '<option value="' . esc_attr( $invoice_id ) . '">' . esc_html( $label ) . '</option>';
		}

		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="sm-finance-payment-date">' . esc_html__( 'Payment date', 'super-mechanic' ) . '</label></th><td><input type="datetime-local" id="sm-finance-payment-date" name="payment_date" value="' . esc_attr( gmdate( 'Y-m-d\TH:i' ) ) . '" required /></td></tr>';
		echo '<tr><th scope="row"><label for="sm-finance-payment-amount">' . esc_html__( 'Amount', 'super-mechanic' ) . '</label></th><td><input type="number" step="0.01" min="0.01" id="sm-finance-payment-amount" name="amount" class="small-text" required /></td></tr>';
		echo '<tr><th scope="row"><label for="sm-finance-payment-method">' . esc_html__( 'Method', 'super-mechanic' ) . '</label></th><td><select id="sm-finance-payment-method" name="payment_method">';
		foreach ( $this->get_payment_method_options() as $method_key => $method_label ) {
			echo '<option value="' . esc_attr( $method_key ) . '">' . esc_html( $method_label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="sm-finance-payment-reference">' . esc_html__( 'Reference', 'super-mechanic' ) . '</label></th><td><input type="text" id="sm-finance-payment-reference" name="reference" class="regular-text" /></td></tr>';
		echo '<tr><th scope="row"><label for="sm-finance-payment-notes">' . esc_html__( 'Notes', 'super-mechanic' ) . '</label></th><td><textarea id="sm-finance-payment-notes" name="notes" class="large-text" rows="3"></textarea></td></tr>';
		echo '</table>';
		submit_button( __( 'Register payment', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</form>';
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
			''         => __( 'All methods', 'super-mechanic' ),
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
		echo '<label for="sm-finance-payment-method" class="screen-reader-text">' . esc_html__( 'Filter by method', 'super-mechanic' ) . '</label>';
		echo '<select id="sm-finance-payment-method" name="filter_payment_method">';

		foreach ( $methods as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current_method, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select>';
		echo '<label for="sm-finance-payment-date-from" class="screen-reader-text">' . esc_html__( 'Date from', 'super-mechanic' ) . '</label>';
		echo '<input id="sm-finance-payment-date-from" type="date" name="date_from" value="' . esc_attr( $date_from ) . '" />';
		echo '<label for="sm-finance-payment-date-to" class="screen-reader-text">' . esc_html__( 'Date to', 'super-mechanic' ) . '</label>';
		echo '<input id="sm-finance-payment-date-to" type="date" name="date_to" value="' . esc_attr( $date_to ) . '" />';
		submit_button( __( 'Filter', 'super-mechanic' ), 'secondary', 'filter_action', false );
		echo '</div>';
		$list_table->search_box( __( 'Search payments', 'super-mechanic' ), 'sm-finance-payments' );
		echo '</form>';
	}

	/**
	 * Get payment method options.
	 *
	 * @return array<string,string>
	 */
	protected function get_payment_method_options() {
		return array(
			'cash'     => __( 'Cash', 'super-mechanic' ),
			'transfer' => __( 'Transfer', 'super-mechanic' ),
			'card'     => __( 'Card', 'super-mechanic' ),
			'check'    => __( 'Check', 'super-mechanic' ),
			'other'    => __( 'Other', 'super-mechanic' ),
		);
	}

	/**
	 * Redirect helper.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return void
	 */
	protected function redirect( array $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'super-mechanic-financial-payments' ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Check if current screen belongs to payments center.
	 *
	 * @return bool
	 */
	protected function is_payments_screen() {
		return isset( $_GET['page'] ) && 'super-mechanic-financial-payments' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Build transient key for finance payment errors.
	 *
	 * @return string
	 */
	protected function get_error_transient_key() {
		return 'sm_finance_payment_errors_' . get_current_user_id();
	}
}
