<?php
require_once __DIR__ . '/bootstrap.php';
hh_admin_session_start();
hh_admin_logout();
header('Location: login.php', true, 302);
exit;
