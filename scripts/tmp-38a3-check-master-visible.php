<?php
$c=file_get_contents('scripts/tmp-38a3-settings-page-after-generate.html');
if (preg_match('/Master password \(showing once\):<\/strong>\s*<code>([^<]+)<\/code>/i',$c,$m)) {
  echo 'VISIBLE|' . $m[1];
} else {
  echo 'VISIBLE|NO';
}
