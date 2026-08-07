<?php

require_once __DIR__ . '/config.php';

function hh_sites_table_exists(PDO $pdo)
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $pdo->query('SELECT 1 FROM hh_sites LIMIT 1');
        $ok = true;
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

function hh_sites_from_config()
{
    $out = array();
    $defs = hh_config_get('app.sites', array());
    if (!is_array($defs)) {
        return $out;
    }
    foreach ($defs as $code => $row) {
        if (!is_array($row)) {
            continue;
        }
        $hosts = isset($row['hosts']) && is_array($row['hosts']) ? $row['hosts'] : array();
        $out[] = array(
            'code' => (string) $code,
            'name_en' => (string) ($row['name_en'] ?? ucfirst((string) $code)),
            'name_zh' => (string) ($row['name_zh'] ?? (string) $code),
            'base_url' => rtrim((string) ($row['base_url'] ?? ''), '/'),
            'hosts' => $hosts,
            'status' => (string) ($row['status'] ?? 'live'),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'source' => 'config',
        );
    }
    usort($out, function ($a, $b) {
        return $a['sort_order'] <=> $b['sort_order'];
    });
    return $out;
}

function hh_sites_list(PDO $pdo = null)
{
    if ($pdo !== null && hh_sites_table_exists($pdo)) {
        $st = $pdo->query('SELECT code, name_en, name_zh, base_url, hosts_json, status, sort_order FROM hh_sites ORDER BY sort_order ASC, code ASC');
        $rows = array();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $hosts = json_decode((string) $r['hosts_json'], true);
            if (!is_array($hosts)) {
                $hosts = array();
            }
            $rows[] = array(
                'code' => (string) $r['code'],
                'name_en' => (string) $r['name_en'],
                'name_zh' => (string) $r['name_zh'],
                'base_url' => rtrim((string) $r['base_url'], '/'),
                'hosts' => $hosts,
                'status' => (string) $r['status'],
                'sort_order' => (int) $r['sort_order'],
                'source' => 'db',
            );
        }
        if ($rows !== array()) {
            return $rows;
        }
    }
    return hh_sites_from_config();
}

function hh_hub_sections_list(PDO $pdo = null)
{
    if ($pdo === null || !hh_sites_table_exists($pdo)) {
        return array();
    }
    try {
        $st = $pdo->query('SELECT site_code, title_en, title_zh, desc_en, desc_zh, entry_path, badge, sort_order FROM hh_hub_sections WHERE is_visible = 1 ORDER BY sort_order ASC');
    } catch (Throwable $e) {
        return array();
    }
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_site_status_by_code(PDO $pdo, $siteCode)
{
    foreach (hh_sites_list($pdo) as $row) {
        if ($row['code'] === $siteCode) {
            return (string) $row['status'];
        }
    }
    return 'soon';
}

function hh_hub_section_public_url($locale, $siteCode, PDO $pdo)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    $status = hh_site_status_by_code($pdo, $siteCode);
    if ($status === 'hidden') {
        return '';
    }
    if ($status === 'live' || in_array($siteCode, array('game', 'news'), true)) {
        if ($siteCode === 'hub') {
            return hh_site_public_url($locale, 'hub');
        }
        $base = hh_site_base_url($siteCode);
        if ($base === '') {
            return '';
        }
        return hh_site_public_url($locale, $siteCode);
    }
    return '';
}

function hh_hub_sections_for_home(PDO $pdo, $locale)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    $rows = hh_hub_sections_list($pdo);
    if ($rows === array()) {
        require_once __DIR__ . '/hub_layout.php';
        $fallback = array(
            array('site_code' => 'handhelds', 'badge' => 'live', 'title_key' => 'section_handhelds_title', 'desc_key' => 'section_handhelds_desc'),
            array('site_code' => 'game', 'badge' => 'soon', 'title_key' => 'section_games_title', 'desc_key' => 'section_games_desc'),
            array('site_code' => 'news', 'badge' => 'soon', 'title_key' => 'section_news_title', 'desc_key' => 'section_news_desc'),
        );
        $out = array();
        foreach ($fallback as $f) {
            $url = hh_hub_section_public_url($locale, $f['site_code'], $pdo);
            if ($f['badge'] !== 'live' && $url === '') {
                $url = '';
            }
            $out[] = array(
                'site_code' => $f['site_code'],
                'title' => hh_hub_ui($locale, $f['title_key']),
                'desc' => hh_hub_ui($locale, $f['desc_key']),
                'badge' => $f['badge'],
                'url' => $url,
            );
        }
        return $out;
    }

    $out = array();
    foreach ($rows as $r) {
        $siteCode = (string) $r['site_code'];
        $badge = (string) $r['badge'];
        $url = hh_hub_section_public_url($locale, $siteCode, $pdo);
        if ($badge !== 'live' && $url === '') {
            $url = '';
        } elseif ($badge === 'live' && $url === '' && $siteCode === 'handhelds') {
            $url = hh_site_public_url($locale, 'handhelds');
        }
        $out[] = array(
            'site_code' => $siteCode,
            'title' => $locale === 'zh' ? (string) $r['title_zh'] : (string) $r['title_en'],
            'desc' => $locale === 'zh' ? (string) ($r['desc_zh'] ?? '') : (string) ($r['desc_en'] ?? ''),
            'badge' => $badge,
            'url' => $url,
        );
    }
    return $out;
}
