<?php

/**
 * Multi-site routing: www = Hub portal, handhelds.* = handheld vertical.
 */

function hh_request_host()
{
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
    if (($pos = strpos($host, ':')) !== false) {
        $host = substr($host, 0, $pos);
    }
    return $host;
}

function hh_site_definitions()
{
    static $defs = null;
    if ($defs !== null) {
        return $defs;
    }
    $cfg = hh_config_get('app.sites', array());
    if (!is_array($cfg) || $cfg === array()) {
        $fallback = rtrim((string) hh_config_get('app.base_url', 'http://localhost:8080'), '/');
        $defs = array(
            'hub' => array(
                'code' => 'hub',
                'hosts' => array('www.oldmanhub.com', 'oldmanhub.com', 'localhost'),
                'base_url' => $fallback,
            ),
            'handhelds' => array(
                'code' => 'handhelds',
                'hosts' => array('handhelds.oldmanhub.com', 'handhelds.localhost'),
                'base_url' => $fallback,
            ),
            'game' => array(
                'code' => 'game',
                'hosts' => array('game.oldmanhub.com', 'game.localhost'),
                'base_url' => 'https://game.oldmanhub.com',
            ),
            'news' => array(
                'code' => 'news',
                'hosts' => array('news.oldmanhub.com', 'news.localhost'),
                'base_url' => 'https://news.oldmanhub.com',
            ),
        );
        return $defs;
    }
    $defs = array();
    foreach ($cfg as $code => $row) {
        if (!is_array($row)) {
            continue;
        }
        $hosts = isset($row['hosts']) ? $row['hosts'] : array();
        if (!is_array($hosts)) {
            $hosts = array();
        }
        $defs[$code] = array(
            'code' => (string) $code,
            'hosts' => array_map('strtolower', $hosts),
            'base_url' => rtrim((string) ($row['base_url'] ?? hh_config_get('app.base_url', '')), '/'),
        );
    }
    return $defs;
}

function hh_site_code()
{
    static $code = null;
    if ($code !== null) {
        return $code;
    }
    $host = hh_request_host();
    foreach (hh_site_definitions() as $def) {
        if (in_array($host, $def['hosts'], true)) {
            $code = $def['code'];
            return $code;
        }
    }
    $code = 'hub';
    return $code;
}

function hh_site_base_url($siteCode = null)
{
    if ($siteCode === null) {
        $siteCode = hh_site_code();
    }
    $defs = hh_site_definitions();
    if (isset($defs[$siteCode]['base_url']) && $defs[$siteCode]['base_url'] !== '') {
        return $defs[$siteCode]['base_url'];
    }
    return rtrim((string) hh_config_get('app.base_url', ''), '/');
}

function hh_site_public_url($path = '', $siteCode = null)
{
    $base = hh_site_base_url($siteCode);
    $path = ltrim((string) $path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function hh_site_apply_legacy_redirects()
{
    $host = hh_request_host();
    $legacy = hh_config_get('app.legacy_hosts', array());
    if (!is_array($legacy)) {
        $legacy = array();
    }
    if (!isset($legacy['oldman.dpdns.org'])) {
        $legacy['oldman.dpdns.org'] = 'https://www.oldmanhub.com';
    }
    if (!isset($legacy['www.oldman.dpdns.org'])) {
        $legacy['www.oldman.dpdns.org'] = 'https://www.oldmanhub.com';
    }
    if (isset($legacy[$host]) && $legacy[$host] !== '') {
        $target = rtrim((string) $legacy[$host], '/');
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
        header('Location: ' . $target . $uri, true, 301);
        exit;
    }
}

function hh_site_apply_hub_www_redirect()
{
    $host = hh_request_host();
    if ($host === 'oldmanhub.com') {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
        header('Location: https://www.oldmanhub.com' . $uri, true, 301);
        exit;
    }
}

function hh_site_redirect_to_handhelds($path)
{
    $path = '/' . ltrim((string) $path, '/');
    header('Location: ' . hh_site_public_url($path, 'handhelds'), true, 301);
    exit;
}

function hh_site_is_hub()
{
    return hh_site_code() === 'hub';
}

function hh_site_is_handhelds()
{
    return hh_site_code() === 'handhelds';
}

function hh_site_is_game()
{
    return hh_site_code() === 'game';
}

function hh_vertical_site_codes()
{
    return array('game', 'news');
}

function hh_site_is_vertical()
{
    return in_array(hh_site_code(), hh_vertical_site_codes(), true);
}

function hh_site_is_news()
{
    return hh_site_code() === 'news';
}
