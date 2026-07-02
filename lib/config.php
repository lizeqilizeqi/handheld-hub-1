<?php

function hh_config()
{
    static $cfg = null;
    if (!empty($GLOBALS['hh_config_force_reload'])) {
        $cfg = null;
        $GLOBALS['hh_config_force_reload'] = false;
    }
    if ($cfg !== null) {
        return $cfg;
    }
    $local = dirname(__DIR__) . '/config.local.php';
    $example = dirname(__DIR__) . '/config.example.php';
    if (is_file($local)) {
        $cfg = require $local;
    } elseif (is_file($example)) {
        $cfg = require $example;
    } else {
        throw new RuntimeException('config.local.php or config.example.php missing');
    }
    if (!is_array($cfg)) {
        throw new RuntimeException('config must return array');
    }
    if (is_file(dirname(__DIR__) . '/lib/secrets.php')) {
        require_once dirname(__DIR__) . '/lib/secrets.php';
        $cfg = hh_config_merge_secrets($cfg);
    }
    return $cfg;
}

function hh_config_get($path, $default = null)
{
    $cfg = hh_config();
    $parts = explode('.', (string) $path);
    $cur = $cfg;
    foreach ($parts as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) {
            return $default;
        }
        $cur = $cur[$p];
    }
    return $cur;
}

function hh_root_dir()
{
    return dirname(__DIR__);
}

function hh_storage_fs()
{
    $custom = hh_config_get('storage.fs_root');
    if ($custom) {
        return rtrim((string) $custom, '/\\');
    }
    return hh_root_dir() . '/storage/handhelds';
}

function hh_storage_web()
{
    return rtrim((string) hh_config_get('storage.web_prefix', '/storage/handhelds'), '/');
}

/** Serve a file from storage/handhelds and exit (used by /i/ and legacy /storage routes). */
function hh_serve_storage_file($relativePath)
{
    $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
    $relativePath = preg_replace('#\.\.+#', '', $relativePath);
    if ($relativePath === '') {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    $file = hh_storage_fs() . '/' . $relativePath;
    if (!is_file($file)) {
        http_response_code(404);
        echo 'Not found';
        exit;
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
    exit;
}

/** Writable dir for scrape/translate job logs and pid files (not mixed with image assets). */
function hh_app_logs_dir()
{
    $custom = hh_config_get('storage.logs_dir');
    if ($custom) {
        return rtrim((string) $custom, '/\\');
    }
    return hh_root_dir() . '/storage/app/logs';
}

function hh_ensure_writable_dir($dir)
{
    hh_ensure_dir($dir);
    if (!is_writable($dir)) {
        @chmod($dir, 0777);
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('目录不可写：' . $dir);
    }
}

/** CLI php binary for background jobs (Apache SAPI has empty or apache2 PHP_BINARY). */
function hh_cli_php_binary()
{
    if (defined('PHP_BINARY')) {
        $bin = (string) PHP_BINARY;
        if ($bin !== '' && basename($bin) !== 'apache2' && is_executable($bin)) {
            return $bin;
        }
    }
    foreach (array('/usr/local/bin/php', '/usr/bin/php') as $candidate) {
        if (is_executable($candidate)) {
            return $candidate;
        }
    }
    return 'php';
}

function hh_base_url()
{
    return rtrim((string) hh_config_get('app.base_url', ''), '/');
}

function hh_public_url($path = '')
{
    $base = hh_base_url();
    $path = ltrim((string) $path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function hh_ensure_dir($dir)
{
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory: ' . $dir);
        }
    }
}

function hh_slugify($text)
{
    $text = strtolower(trim((string) $text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-');
}

function hh_h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function hh_json_response($data, $code = 200)
{
    if (!headers_sent()) {
        http_response_code((int) $code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
