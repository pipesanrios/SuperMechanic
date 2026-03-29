<?php
$f = 'scripts/tmp-38a3-export-success.json';
$c = file_get_contents($f);
$d = json_decode($c, true);
if (!is_array($d)) { echo "JSON_INVALID\n"; exit(1); }
$tables = isset($d['tables']) && is_array($d['tables']) ? $d['tables'] : array();
$count = count($tables);
$names = array();
$outside = array();
foreach ($tables as $k => $info) {
  $t = isset($info['table']) ? (string)$info['table'] : '';
  $names[] = $t;
  if ('' !== $t && false === strpos($t, 'sm_')) {
    $outside[] = $t;
  }
}
sort($names);
echo 'TABLE_COUNT|' . $count . PHP_EOL;
echo 'FIRST_TABLE|' . ($names[0] ?? '') . PHP_EOL;
echo 'LAST_TABLE|' . ($names[count($names)-1] ?? '') . PHP_EOL;
echo 'OUTSIDE_COUNT|' . count($outside) . PHP_EOL;
if (!empty($outside)) { echo 'OUTSIDE|' . implode(',', $outside) . PHP_EOL; }
$keys = array('users','usermeta','posts','postmeta','options','terms','term_taxonomy');
$hits = array();
foreach ($keys as $needle) {
  foreach ($names as $t) {
    if (false !== strpos($t, $needle)) { $hits[] = $t; }
  }
}
echo 'CORE_HITS|' . count($hits) . PHP_EOL;
if (!empty($hits)) { echo 'CORE_TABLES|' . implode(',', array_unique($hits)) . PHP_EOL; }
