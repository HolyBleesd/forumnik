<?php
require_once __DIR__ . '/functions.php';
$_SESSION['banned_user_id'] = 3;  // твій user_id
redirect(SITE_URL . '/banned.php');