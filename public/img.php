<?php
/**
 * Public image endpoint — real PHP file, no Apache rewrite needed.
 * Example: /img.php?f=rg-rotate/cover.webp
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/config.php';
hh_bootstrap();

$path = isset($_GET['f']) ? (string) $_GET['f'] : '';
hh_serve_storage_file($path);
