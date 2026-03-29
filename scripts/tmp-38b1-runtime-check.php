<?php
require 'c:/xampp/htdocs/SuperMechanic/wp-load.php';
if ( file_exists( ABSPATH . 'wp-admin/includes/template.php' ) ) { require_once ABSPATH . 'wp-admin/includes/template.php'; }

use Super_Mechanic\Helpers\PDF_Service;
use Super_Mechanic\Invoices\Invoice_Admin_Controller;
use Super_Mechanic\Invoices\Invoice_Item_Repository;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Maintenance\Maintenance_Admin_Controller;
use Super_Mechanic\Maintenance\Maintenance_Part_Repository;
use Super_Mechanic\Maintenance\Maintenance_Service;
use Super_Mechanic\Quotes\Quote_Admin_Controller;
use Super_Mechanic\Quotes\Quote_Item_Repository;
use Super_Mechanic\Quotes\Quote_Service;

function sm38b1_result( $ok, $details = array() ) {
	return array(
		'ok'      => (bool) $ok,
		'details' => $details,
	);
}

$mode = isset( $argv[1] ) ? strtolower( (string) $argv[1] ) : 'active';

$report = array(
	'mode'      => $mode,
	'timestamp' => gmdate( 'c' ),
	'checks'    => array(),
	'errors'    => array(),
);

$admin = get_user_by( 'login', 'sm_runtime_38a3' );
if ( ! $admin ) {
	$report['errors'][] = 'runtime_admin_missing';
	echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit( 1 );
}

wp_set_current_user( (int) $admin->ID );

$quote_service       = new Quote_Service();
$invoice_service     = new Invoice_Service();
$maintenance_service = new Maintenance_Service();
$pdf_service         = new PDF_Service( $invoice_service, $quote_service );

$quote_controller       = new Quote_Admin_Controller( $quote_service, $invoice_service, $pdf_service );
$invoice_controller     = new Invoice_Admin_Controller( $invoice_service, $pdf_service );
$maintenance_controller = new Maintenance_Admin_Controller( $maintenance_service );

$woo_active_runtime = class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' );
$woo_options_quote  = $quote_service->get_woo_product_options( 5 );
$woo_options_invoice = $invoice_service->get_woo_product_options( 5 );
$woo_options_maintenance = $maintenance_service->get_woo_product_options( 5 );

$report['checks']['woo_runtime'] = sm38b1_result(
	$woo_active_runtime,
	array(
		'quote_options'       => count( $woo_options_quote ),
		'invoice_options'     => count( $woo_options_invoice ),
		'maintenance_options' => count( $woo_options_maintenance ),
	)
);

global $wpdb;
$process_table = $wpdb->prefix . 'sm_processes';
$process       = $wpdb->get_row(
	"SELECT * FROM {$process_table} WHERE process_type = 'maintenance' ORDER BY id DESC LIMIT 1",
	ARRAY_A
);

if ( ! $process ) {
	$process = $wpdb->get_row( "SELECT * FROM {$process_table} ORDER BY id DESC LIMIT 1", ARRAY_A );
}

if ( ! $process ) {
	$report['errors'][] = 'process_missing';
	echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit( 1 );
}

$process_id = (int) $process['id'];

ob_start();
$quote_controller->render_process_panel( $process );
$quote_html = (string) ob_get_clean();

ob_start();
$invoice_controller->render_process_panel( $process );
$invoice_html = (string) ob_get_clean();

ob_start();
$maintenance_controller->render_process_panel( $process );
$maintenance_html = (string) ob_get_clean();

$has_quote_selector       = false !== strpos( $quote_html, 'id="sm_quote_woo_product"' );
$has_invoice_selector     = false !== strpos( $invoice_html, 'id="sm_invoice_woo_product"' );
$has_maintenance_selector = false !== strpos( $maintenance_html, 'id="sm_maintenance_woo_product"' );

$expect_selector = 'active' === $mode;
$report['checks']['selector_visibility'] = sm38b1_result(
	$has_quote_selector === $expect_selector
	&& $has_invoice_selector === $expect_selector
	&& $has_maintenance_selector === $expect_selector,
	array(
		'expect'                => $expect_selector,
		'quote_selector'        => $has_quote_selector,
		'invoice_selector'      => $has_invoice_selector,
		'maintenance_selector'  => $has_maintenance_selector,
	)
);

if ( $expect_selector ) {
	$product = isset( $woo_options_quote[0] ) ? $woo_options_quote[0] : null;
	if ( ! is_array( $product ) || empty( $product['id'] ) ) {
		$report['errors'][] = 'woo_product_missing_for_active_mode';
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit( 1 );
	}

	$product_id    = (int) $product['id'];
	$product_name  = (string) $product['name'];
	$product_price = (float) $product['unit_price'];

	$quote_id = $quote_service->create_quote(
		array(
			'process_id' => $process_id,
		)
	);
	if ( is_wp_error( $quote_id ) ) {
		$report['errors'][] = 'quote_create_failed:' . $quote_id->get_error_message();
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit( 1 );
	}

	$quote_item_id = $quote_service->add_quote_item(
		(int) $quote_id,
		array(
			'item_type'      => 'woo_product',
			'reference_id'   => 0,
			'woo_product_id' => $product_id,
			'label'          => 'should_be_overwritten',
			'quantity'       => 1,
			'unit_price'     => 0.01,
		)
	);
	if ( is_wp_error( $quote_item_id ) ) {
		$report['errors'][] = 'quote_item_create_failed:' . $quote_item_id->get_error_message();
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit( 1 );
	}

	$quote_item_repo = new Quote_Item_Repository();
	$quote_item      = $quote_item_repo->get_by_id( (int) $quote_item_id );
	$report['checks']['quote_snapshot_persist'] = sm38b1_result(
		is_array( $quote_item )
		&& 'woo_product' === (string) $quote_item['item_type']
		&& (int) $quote_item['reference_id'] === $product_id
		&& (string) $quote_item['label'] === $product_name
		&& (float) $quote_item['unit_price'] === (float) $product_price,
		array(
			'quote_id'      => (int) $quote_id,
			'item_id'       => (int) $quote_item_id,
			'item_type'     => is_array( $quote_item ) ? $quote_item['item_type'] : null,
			'reference_id'  => is_array( $quote_item ) ? (int) $quote_item['reference_id'] : null,
			'label'         => is_array( $quote_item ) ? (string) $quote_item['label'] : null,
			'unit_price'    => is_array( $quote_item ) ? (float) $quote_item['unit_price'] : null,
			'expected_id'   => $product_id,
			'expected_name' => $product_name,
			'expected_price'=> (float) $product_price,
		)
	);

	$invoice_id = $invoice_service->create_invoice(
		array(
			'process_id' => $process_id,
			'client_id'  => isset( $process['client_id'] ) ? (int) $process['client_id'] : 0,
			'status'     => 'draft',
		)
	);
	if ( is_wp_error( $invoice_id ) ) {
		$report['errors'][] = 'invoice_create_failed:' . $invoice_id->get_error_message();
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit( 1 );
	}

	$invoice_item_id = $invoice_service->add_invoice_item(
		(int) $invoice_id,
		array(
			'item_type'      => 'woo_product',
			'reference_id'   => 0,
			'woo_product_id' => $product_id,
			'label'          => 'should_be_overwritten',
			'quantity'       => 1,
			'unit_price'     => 0.01,
		)
	);
	if ( is_wp_error( $invoice_item_id ) ) {
		$report['errors'][] = 'invoice_item_create_failed:' . $invoice_item_id->get_error_message();
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit( 1 );
	}

	$invoice_item_repo = new Invoice_Item_Repository();
	$invoice_item      = $invoice_item_repo->get_by_id( (int) $invoice_item_id );
	$report['checks']['invoice_snapshot_persist'] = sm38b1_result(
		is_array( $invoice_item )
		&& 'woo_product' === (string) $invoice_item['item_type']
		&& (int) $invoice_item['reference_id'] === $product_id
		&& (string) $invoice_item['label'] === $product_name
		&& (float) $invoice_item['unit_price'] === (float) $product_price,
		array(
			'invoice_id'    => (int) $invoice_id,
			'item_id'       => (int) $invoice_item_id,
			'item_type'     => is_array( $invoice_item ) ? $invoice_item['item_type'] : null,
			'reference_id'  => is_array( $invoice_item ) ? (int) $invoice_item['reference_id'] : null,
			'label'         => is_array( $invoice_item ) ? (string) $invoice_item['label'] : null,
			'unit_price'    => is_array( $invoice_item ) ? (float) $invoice_item['unit_price'] : null,
			'expected_id'   => $product_id,
			'expected_name' => $product_name,
			'expected_price'=> (float) $product_price,
		)
	);

	$maintenance = $maintenance_service->create_maintenance( $process_id );
	if ( is_wp_error( $maintenance ) || ! is_array( $maintenance ) || empty( $maintenance['id'] ) ) {
		$report['errors'][] = 'maintenance_create_failed';
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit( 1 );
	}

	$part_id = $maintenance_service->add_part(
		(int) $maintenance['id'],
		array(
			'part_name'      => 'manual_name',
			'quantity'       => 1,
			'unit_price'     => 0.01,
			'woo_product_id' => $product_id,
		)
	);
	if ( is_wp_error( $part_id ) ) {
		$report['errors'][] = 'maintenance_part_create_failed:' . $part_id->get_error_message();
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit( 1 );
	}

	$part_repo = new Maintenance_Part_Repository();
	$part      = null;
	$parts     = $part_repo->get_by_maintenance_id( (int) $maintenance['id'] );
	if ( is_array( $parts ) ) {
		foreach ( $parts as $candidate ) {
			if ( isset( $candidate['id'] ) && (int) $candidate['id'] === (int) $part_id ) {
				$part = $candidate;
				break;
			}
		}
	}
	$report['checks']['maintenance_manual_autofill'] = sm38b1_result(
		is_array( $part )
		&& (string) $part['part_name'] === $product_name
		&& (float) $part['unit_price'] === (float) $product_price,
		array(
			'part_id'         => (int) $part_id,
			'part_name'       => is_array( $part ) ? (string) $part['part_name'] : null,
			'unit_price'      => is_array( $part ) ? (float) $part['unit_price'] : null,
			'expected_name'   => $product_name,
			'expected_price'  => (float) $product_price,
		)
	);

	$quote    = $quote_service->get_quote( (int) $quote_id );
	$invoice  = $invoice_service->get_invoice( (int) $invoice_id );
	$report['checks']['totals_no_regression'] = sm38b1_result(
		is_array( $quote ) && is_array( $invoice )
		&& isset( $quote['grand_total'], $invoice['grand_total'] )
		&& (float) $quote['grand_total'] >= 0
		&& (float) $invoice['grand_total'] >= 0,
		array(
			'quote_grand_total'   => is_array( $quote ) ? (float) $quote['grand_total'] : null,
			'invoice_grand_total' => is_array( $invoice ) ? (float) $invoice['grand_total'] : null,
		)
	);
} else {
	$report['checks']['manual_flow_unchanged_baseline'] = sm38b1_result(
		! $has_quote_selector && ! $has_invoice_selector && ! $has_maintenance_selector,
		array(
			'quote_selector'       => $has_quote_selector,
			'invoice_selector'     => $has_invoice_selector,
			'maintenance_selector' => $has_maintenance_selector,
		)
	);
}

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );


