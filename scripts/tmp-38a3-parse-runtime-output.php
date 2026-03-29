<?php
$files = array(
  'scripts/tmp-38a3-settings-page.html',
  'scripts/tmp-38a3-generate-response.txt'
);
foreach ($files as $f) {
  if (!file_exists($f)) { continue; }
  $c = file_get_contents($f);
  echo "FILE|$f\n";
  if (preg_match('/name="sm_db_security_generate_nonce"\s+value="([^"]+)"/i', $c, $m)) { echo "NONCE_GENERATE|{$m[1]}\n"; }
  if (preg_match('/name="sm_db_security_export_nonce"\s+value="([^"]+)"/i', $c, $m)) { echo "NONCE_EXPORT|{$m[1]}\n"; }
  if (preg_match('/name="sm_db_security_reset_nonce"\s+value="([^"]+)"/i', $c, $m)) { echo "NONCE_RESET|{$m[1]}\n"; }
  if (preg_match('/sm_db_master_token=([a-z0-9]+)/i', $c, $m)) { echo "MASTER_TOKEN|{$m[1]}\n"; }
  if (preg_match('/Master password \(showing once\):<\/strong>\s*<code>([^<]+)<\/code>/i', $c, $m)) { echo "MASTER_ONCE|{$m[1]}\n"; }
  if (preg_match('/sm_db_notice=([a-z]+)/i', $c, $m)) { echo "NOTICE|{$m[1]}\n"; }
}
