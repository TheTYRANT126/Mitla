<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->logout();

redirect(SITE_URL . '/admin/login.php');