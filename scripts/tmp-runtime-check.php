<?php
error_reporting(E_ALL);
require 'c:/xampp/htdocs/SuperMechanic/wp-load.php';
global $wpdb;
if (!isset($wpdb)) { echo "NO_WPDB\n"; exit(1); }
$tables = [
 'clients' => $wpdb->prefix.'sm_clients',
 'vehicles' => $wpdb->prefix.'sm_vehicles',
 'relations' => $wpdb->prefix.'sm_client_vehicles',
 'processes' => $wpdb->prefix.'sm_processes',
 'businesses' => $wpdb->prefix.'sm_businesses'
];
foreach ($tables as $k=>$t){
  $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
  echo $k.":".($exists ? "OK" : "MISS")."\n";
}
?>
