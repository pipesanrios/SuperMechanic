<?php
/**
 * Autoloader bootstrap.
 *
 * @package Super_Mechanic
 */

defined( 'ABSPATH' ) || exit;

/**
 * Simple PSR-4 autoloader for the Super_Mechanic namespace.
 */
spl_autoload_register( function ( $class ) {
	$class = ltrim( $class, '\\' );

	$prefix = 'Super_Mechanic\\';
	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}

	$relative_class      = substr( $class, strlen( $prefix ) );
	$relative_class_path = str_replace( '\\', '/', $relative_class );
	$relative_class_path = strtolower( $relative_class_path );
	$relative_class_path = str_replace( '_', '-', $relative_class_path );
	// Keep the historical Pre_Delivery namespace mapped to the active predelivery folder.
	$relative_class_path = str_replace( 'pre-delivery/', 'predelivery/', $relative_class_path );
	$file                = SM_PLUGIN_PATH . 'includes/' . $relative_class_path;
	$directory           = dirname( $file );
	$class_name          = strtolower( basename( $file ) );
	$file                = $directory . '/class-' . $class_name . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );
