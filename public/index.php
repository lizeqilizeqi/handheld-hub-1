<?php
/**
 * Front controller for PHP built-in server:
 * php -S localhost:8080 -t public public/index.php
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/config.php';
hh_bootstrap();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim((string) $uri, '/');
if ($uri === '') {
    $uri = '/';
}

if ($uri === '/') {
    require_once dirname(__DIR__) . '/lib/public_layout.php';
    $locale = hh_public_preferred_locale();
    header('Location: /' . $locale . '/handhelds', true, 302);
    exit;
}

if (preg_match('#^/i/(.+)$#', $uri, $m)) {
    hh_serve_storage_file($m[1]);
}

if (preg_match('#^/storage/handhelds/(.+)$#', $uri, $m)) {
    hh_serve_storage_file($m[1]);
}

if (preg_match('#^/(en|zh)/handhelds$#', $uri, $m)) {
    $_GET['locale'] = $m[1];
    require __DIR__ . '/handhelds.php';
    exit;
}

if (preg_match('#^/(en|zh)/handheld/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    $_GET['locale'] = $m[1];
    $_GET['slug'] = $m[2];
    require __DIR__ . '/handheld.php';
    exit;
}

if ($uri === '/en' || $uri === '/zh') {
    header('Location: ' . $uri . '/handhelds', true, 302);
    exit;
}

http_response_code(404);
echo '404 Not Found';
