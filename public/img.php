<?php
/**
 * Public image endpoint — minimal, no DB, no bootstrap.
 * Example: /img.php?f=rg-rotate/cover.webp
 */
require_once dirname(__DIR__) . '/lib/config.php';

$path = isset($_GET['f']) ? (string) $_GET['f'] : '';
$path = ltrim(str_replace('\\', '/', $path), '/');
$path = preg_replace('#\.\.+#', '', $path);

$root = hh_storage_fs();
$file = $root . '/' . $path;

if ($path === '' || !is_file($file)) {
    http_response_code(404);
    exit('not found');
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$types = array(
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
);
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=86400');
readfile($file);
