<?php
require 'c:/xampp/htdocs/SuperMechanic/wp-load.php';
$users = get_users(array(
    'role__in' => array('administrator', 'sm_admin'),
    'fields' => array('ID', 'user_login', 'user_email'),
));
foreach ($users as $u) {
    echo $u->ID . '|' . $u->user_login . '|' . $u->user_email . PHP_EOL;
}
