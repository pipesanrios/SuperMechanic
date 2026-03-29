<?php
$before = json_decode(file_get_contents(__DIR__ . '/tmp-38a3-db-snapshot-before.json'), true);
$after  = json_decode(file_get_contents(__DIR__ . '/tmp-38a3-db-snapshot-after.json'), true);

if (!is_array($before) || !is_array($after)) {
    echo "ERROR|snapshot-parse\n";
    exit(1);
}

echo 'CORE_USERS|' . $before['core_counts']['wp_users'] . '|' . $after['core_counts']['wp_users'] . PHP_EOL;
echo 'CORE_POSTS|' . $before['core_counts']['wp_posts'] . '|' . $after['core_counts']['wp_posts'] . PHP_EOL;
echo 'CORE_OPTIONS|' . $before['core_counts']['wp_options'] . '|' . $after['core_counts']['wp_options'] . PHP_EOL;

echo 'SM_TABLES|' . $before['sm_table_count'] . '|' . $after['sm_table_count'] . PHP_EOL;

echo 'DEFAULT_BUSINESS_BEFORE|' . json_encode($before['default_business']) . PHP_EOL;
echo 'DEFAULT_BUSINESS_AFTER|' . json_encode($after['default_business']) . PHP_EOL;

$nonzero_after = 0;
$rows_after_total = 0;
$changed_tables = 0;

foreach ($before['sm_counts'] as $table => $bCount) {
    $aCount = isset($after['sm_counts'][$table]) ? (int) $after['sm_counts'][$table] : -1;
    if ($aCount !== (int) $bCount) {
        $changed_tables++;
    }
    if ($aCount > 0) {
        $nonzero_after++;
        $rows_after_total += $aCount;
    }
}

echo 'SM_CHANGED_TABLES|' . $changed_tables . PHP_EOL;
echo 'SM_NONZERO_AFTER|' . $nonzero_after . PHP_EOL;
echo 'SM_ROWS_TOTAL_AFTER|' . $rows_after_total . PHP_EOL;

foreach ($after['sm_counts'] as $table => $count) {
    if ($count > 0) {
        echo 'SM_NONZERO|' . $table . '|' . $count . PHP_EOL;
    }
}
