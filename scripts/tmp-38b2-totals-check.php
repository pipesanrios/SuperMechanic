<?php
require 'c:/xampp/htdocs/SuperMechanic/wp-load.php';

use Super_Mechanic\Invoices\Invoice_Item_Repository;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Quotes\Quote_Item_Repository;
use Super_Mechanic\Quotes\Quote_Service;

function sm38b2_out( $ok, $details = array() ) {
	return array(
		'ok'      => (bool) $ok,
		'details' => $details,
	);
}

$report = array(
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

$quote_service   = new Quote_Service();
$invoice_service = new Invoice_Service();

global $wpdb;
$process_table = $wpdb->prefix . 'sm_processes';
$process       = $wpdb->get_row( "SELECT * FROM {$process_table} ORDER BY id DESC LIMIT 1", ARRAY_A );
if ( ! $process ) {
	$report['errors'][] = 'process_missing';
	echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit( 1 );
}

$process_id = (int) $process['id'];
$client_id  = isset( $process['client_id'] ) ? (int) $process['client_id'] : 0;

$woo_options = $quote_service->get_woo_product_options( 5 );
$woo_active  = class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' ) && ! empty( $woo_options );

$report['checks']['woo_runtime_state'] = sm38b2_out(
	true,
	array(
		'woo_active'      => $woo_active,
		'woo_option_count'=> is_array( $woo_options ) ? count( $woo_options ) : 0,
	)
);

// Scenario A: quote manual item_type=manual should normalize to custom and totals formula.
$quote_id = $quote_service->create_quote( array( 'process_id' => $process_id ) );
if ( is_wp_error( $quote_id ) ) {
	$report['errors'][] = 'quote_create_failed:' . $quote_id->get_error_message();
	echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit( 1 );
}

$manual_item_id = $quote_service->add_quote_item(
	(int) $quote_id,
	array(
		'item_type'  => 'manual',
		'label'      => 'Manual test item',
		'quantity'   => 2,
		'unit_price' => 10,
	)
);
if ( is_wp_error( $manual_item_id ) ) {
	$report['errors'][] = 'quote_manual_item_failed:' . $manual_item_id->get_error_message();
	echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit( 1 );
}

$quote_item_repo = new Quote_Item_Repository();
$manual_item     = $quote_item_repo->get_by_id( (int) $manual_item_id );
$quote_row       = $quote_service->get_quote( (int) $quote_id );
$report['checks']['quote_manual_normalization'] = sm38b2_out(
	is_array( $manual_item )
	&& 'custom' === (string) $manual_item['item_type']
	&& 20.0 === (float) $manual_item['line_total']
	&& is_array( $quote_row )
	&& 20.0 === (float) $quote_row['subtotal']
	&& 20.0 === (float) $quote_row['grand_total'],
	array(
		'item_type'   => is_array( $manual_item ) ? (string) $manual_item['item_type'] : null,
		'line_total'  => is_array( $manual_item ) ? (float) $manual_item['line_total'] : null,
		'subtotal'    => is_array( $quote_row ) ? (float) $quote_row['subtotal'] : null,
		'grand_total' => is_array( $quote_row ) ? (float) $quote_row['grand_total'] : null,
	)
);

// Scenario B: quote mixed (manual + woo when available).
if ( $woo_active ) {
	$product = $woo_options[0];
	$woo_item_id = $quote_service->add_quote_item(
		(int) $quote_id,
		array(
			'item_type'      => 'woo_product',
			'woo_product_id' => (int) $product['id'],
			'label'          => 'will_be_snapshot',
			'quantity'       => 1,
			'unit_price'     => 999,
		)
	);

	if ( is_wp_error( $woo_item_id ) ) {
		$report['errors'][] = 'quote_woo_item_failed:' . $woo_item_id->get_error_message();
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit( 1 );
	}

	$quote_row = $quote_service->get_quote( (int) $quote_id );
	$expected  = round( 20 + (float) $product['unit_price'], 2 );
	$report['checks']['quote_mixed_totals'] = sm38b2_out(
		is_array( $quote_row ) && $expected === (float) $quote_row['subtotal'],
		array(
			'expected_subtotal' => $expected,
			'subtotal'          => is_array( $quote_row ) ? (float) $quote_row['subtotal'] : null,
		)
	);
} else {
	$report['checks']['quote_mixed_totals'] = sm38b2_out(
		true,
		array( 'skipped_reason' => 'woo_inactive_or_no_products' )
	);
}

// Scenario C: sanitize legacy inconsistent line_total in quote.
$tampered = $quote_item_repo->update(
	(int) $manual_item_id,
	array(
		'line_total' => 999.99,
		'item_type'  => 'manual',
	)
);
$quote_service->recalculate_totals( (int) $quote_id );
$manual_item_after = $quote_item_repo->get_by_id( (int) $manual_item_id );
$report['checks']['quote_legacy_sanitized'] = sm38b2_out(
	$tampered
	&& is_array( $manual_item_after )
	&& 'custom' === (string) $manual_item_after['item_type']
	&& 20.0 === (float) $manual_item_after['line_total'],
	array(
		'item_type_after'  => is_array( $manual_item_after ) ? (string) $manual_item_after['item_type'] : null,
		'line_total_after' => is_array( $manual_item_after ) ? (float) $manual_item_after['line_total'] : null,
	)
);

// Scenario D: invoice manual + mixed and legacy sanitize.
$invoice_id = $invoice_service->create_invoice(
	array(
		'process_id' => $process_id,
		'client_id'  => $client_id,
		'status'     => 'draft',
	)
);
if ( is_wp_error( $invoice_id ) ) {
	$report['errors'][] = 'invoice_create_failed:' . $invoice_id->get_error_message();
	echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit( 1 );
}

$invoice_manual_item_id = $invoice_service->add_invoice_item(
	(int) $invoice_id,
	array(
		'item_type'  => 'manual',
		'label'      => 'Manual invoice item',
		'quantity'   => 3,
		'unit_price' => 15,
	)
);
if ( is_wp_error( $invoice_manual_item_id ) ) {
	$report['errors'][] = 'invoice_manual_item_failed:' . $invoice_manual_item_id->get_error_message();
	echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit( 1 );
}

$invoice_item_repo = new Invoice_Item_Repository();
$invoice_manual    = $invoice_item_repo->get_by_id( (int) $invoice_manual_item_id );
$invoice_row       = $invoice_service->get_invoice( (int) $invoice_id );
$report['checks']['invoice_manual_normalization'] = sm38b2_out(
	is_array( $invoice_manual )
	&& 'custom' === (string) $invoice_manual['item_type']
	&& 45.0 === (float) $invoice_manual['line_total']
	&& is_array( $invoice_row )
	&& 45.0 === (float) $invoice_row['subtotal']
	&& 45.0 === (float) $invoice_row['grand_total'],
	array(
		'item_type'   => is_array( $invoice_manual ) ? (string) $invoice_manual['item_type'] : null,
		'line_total'  => is_array( $invoice_manual ) ? (float) $invoice_manual['line_total'] : null,
		'subtotal'    => is_array( $invoice_row ) ? (float) $invoice_row['subtotal'] : null,
		'grand_total' => is_array( $invoice_row ) ? (float) $invoice_row['grand_total'] : null,
	)
);

if ( $woo_active ) {
	$product = $woo_options[0];
	$invoice_woo_item_id = $invoice_service->add_invoice_item(
		(int) $invoice_id,
		array(
			'item_type'      => 'woo_product',
			'woo_product_id' => (int) $product['id'],
			'label'          => 'will_be_snapshot',
			'quantity'       => 1,
			'unit_price'     => 999,
		)
	);
	if ( is_wp_error( $invoice_woo_item_id ) ) {
		$report['errors'][] = 'invoice_woo_item_failed:' . $invoice_woo_item_id->get_error_message();
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit( 1 );
	}
	$invoice_row = $invoice_service->get_invoice( (int) $invoice_id );
	$expected    = round( 45 + (float) $product['unit_price'], 2 );
	$report['checks']['invoice_mixed_totals'] = sm38b2_out(
		is_array( $invoice_row ) && $expected === (float) $invoice_row['subtotal'],
		array(
			'expected_subtotal' => $expected,
			'subtotal'          => is_array( $invoice_row ) ? (float) $invoice_row['subtotal'] : null,
		)
	);
} else {
	$report['checks']['invoice_mixed_totals'] = sm38b2_out(
		true,
		array( 'skipped_reason' => 'woo_inactive_or_no_products' )
	);
}

$tampered_invoice = $invoice_item_repo->update(
	(int) $invoice_manual_item_id,
	array(
		'line_total' => 777.77,
		'item_type'  => 'manual',
	)
);
$invoice_service->recalculate_totals( (int) $invoice_id );
$invoice_manual_after = $invoice_item_repo->get_by_id( (int) $invoice_manual_item_id );
$report['checks']['invoice_legacy_sanitized'] = sm38b2_out(
	$tampered_invoice
	&& is_array( $invoice_manual_after )
	&& 'custom' === (string) $invoice_manual_after['item_type']
	&& 45.0 === (float) $invoice_manual_after['line_total'],
	array(
		'item_type_after'  => is_array( $invoice_manual_after ) ? (string) $invoice_manual_after['item_type'] : null,
		'line_total_after' => is_array( $invoice_manual_after ) ? (float) $invoice_manual_after['line_total'] : null,
	)
);

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
