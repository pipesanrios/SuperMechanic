<?php
require 'c:/xampp/htdocs/SuperMechanic/wp-load.php';
$username = 'sm_runtime_38a3';
$password = 'SmRuntime!38A3#2026';
$email = 'sm-runtime-38a3@example.com';
$user = get_user_by('login', $username);
if (!$user) {
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        echo 'ERROR|' . $user_id->get_error_message();
        exit(1);
    }
    $user = get_user_by('id', $user_id);
}
$user->set_role('administrator');
wp_set_password($password, $user->ID);
echo 'OK|' . $user->ID . '|' . $username . '|' . $password;
