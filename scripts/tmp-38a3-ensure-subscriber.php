<?php
require dirname(__DIR__, 4) . '/wp-load.php';

$username = 'sm_runtime_38a3_sub';
$password = 'SmRuntimeSub!38A3#2026';
$email = 'sm-runtime-38a3-sub@example.test';
$user = get_user_by('login', $username);
if (!$user) {
    $id = wp_create_user($username, $password, $email);
    if (is_wp_error($id)) {
        echo 'ERROR|' . $id->get_error_message() . PHP_EOL;
        exit(1);
    }
    $user = get_user_by('id', $id);
}
$user->set_role('subscriber');
echo 'OK|' . $user->ID . '|' . $username . '|' . $password . PHP_EOL;
