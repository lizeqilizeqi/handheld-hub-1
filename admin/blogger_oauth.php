<?php
require_once __DIR__ . '/bootstrap.php';
hh_admin_session_start();
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/blogger.php';

$code = isset($_GET['code']) ? (string) $_GET['code'] : '';
if ($code === '') {
    header('HTTP/400');
    echo 'Missing code';
    exit;
}
try {
    hh_blogger_exchange_code(hh_pdo(), $code);
    header('Location: blogger.php?oauth=ok', true, 302);
} catch (Throwable $e) {
    header('Location: blogger.php?oauth=err&msg=' . rawurlencode($e->getMessage()), true, 302);
}
exit;
