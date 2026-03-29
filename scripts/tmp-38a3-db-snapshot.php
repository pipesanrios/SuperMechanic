<?php
require dirname(__DIR__, 4) . '/wp-load.php';

global $wpdb;

$prefix = $wpdb->prefix;
$core_tables = array($prefix . 'users', $prefix . 'posts', $prefix . 'options');
$sm_tables = $wpdb->get_col("SHOW TABLES LIKE '{$prefix}sm_%'");
sort($sm_tables);

$out = array(
    'timestamp' => current_time('mysql'),
    'core_counts' => array(),
    'sm_table_count' => count($sm_tables),
    'sm_counts' => array(),
);

foreach ($core_tables as $table) {
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    $count = $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : null;
    $out['core_counts'][$table] = $count;
}

foreach ($sm_tables as $table) {
    $out['sm_counts'][$table] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
}

$default_business = $wpdb->get_row("SELECT id, slug, name, is_default FROM {$prefix}sm_businesses WHERE is_default = 1 LIMIT 1", ARRAY_A);
$out['default_business'] = $default_business;

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
