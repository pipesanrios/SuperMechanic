<?php
require 'c:/xampp/htdocs/SuperMechanic/wp-load.php';

$mode = isset( $argv[1] ) ? strtolower( (string) $argv[1] ) : 'status';
$backup_option = 'sm_tmp_38b1_active_plugins_backup';
$woo_plugin = 'woocommerce/woocommerce.php';
$woo_file = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';

$current = get_option( 'active_plugins', array() );
if ( ! is_array( $current ) ) {
	$current = array();
}

if ( 'backup' === $mode ) {
	update_option( $backup_option, $current, false );
	echo "BACKUP_OK\n";
	exit( 0 );
}

if ( 'restore' === $mode ) {
	$backup = get_option( $backup_option, null );
	if ( is_array( $backup ) ) {
		update_option( 'active_plugins', $backup, false );
		echo "RESTORE_OK\n";
		exit( 0 );
	}
	echo "RESTORE_MISSING\n";
	exit( 1 );
}

if ( 'on' === $mode ) {
	if ( ! file_exists( $woo_file ) ) {
		echo "WOO_PLUGIN_FILE_MISSING\n";
		exit( 2 );
	}
	if ( ! in_array( $woo_plugin, $current, true ) ) {
		$current[] = $woo_plugin;
		update_option( 'active_plugins', array_values( $current ), false );
	}
	echo "ON_OK\n";
	exit( 0 );
}

if ( 'off' === $mode ) {
	$current = array_values(
		array_filter(
			$current,
			function( $plugin ) use ( $woo_plugin ) {
				return $plugin !== $woo_plugin;
			}
		)
	);
	update_option( 'active_plugins', $current, false );
	echo "OFF_OK\n";
	exit( 0 );
}

echo wp_json_encode(
	array(
		'woo_plugin_file_exists' => file_exists( $woo_file ),
		'woo_active_in_option'   => in_array( $woo_plugin, $current, true ),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
