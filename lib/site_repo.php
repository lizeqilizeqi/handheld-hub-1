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

/** @return array<string, bool> site_code => visible on hub home + hub nav */
function hh_hub_modules_visible_map(PDO $pdo)
{
    $defaults = array('handhelds' => true, 'game' => true, 'news' => true);
    if (!hh_sites_table_exists($pdo)) {
        return $defaults;
    }
    try {
        $st = $pdo->query('SELECT site_code, is_visible FROM hh_hub_sections');
    } catch (Throwable $e) {
        return $defaults;
    }
    $map = $defaults;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = (string) $row['site_code'];
        if (isset($map[$code])) {
            $map[$code] = !empty($row['is_visible']);
        }
    }
    return $map;
}

function hh_hub_module_is_visible(PDO $pdo, $siteCode)
{
    $map = hh_hub_modules_visible_map($pdo);
    return !empty($map[$siteCode]);
}

function hh_hub_section_set_visible(PDO $pdo, $siteCode, $visible)
{
    if (!hh_sites_table_exists($pdo)) {
        return false;
    }
    $st = $pdo->prepare('UPDATE hh_hub_sections SET is_visible = ? WHERE site_code = ?');
    $st->execute(array($visible ? 1 : 0, (string) $siteCode));
    return $st->rowCount() > 0;
}

function hh_hub_sections_admin_list(PDO $pdo)
{
    if (!hh_sites_table_exists($pdo)) {
        return array();
    }
    try {
        $st = $pdo->query('SELECT id, site_code, title_en, title_zh, desc_en, desc_zh, entry_path, badge, sort_order, is_visible FROM hh_hub_sections ORDER BY sort_order ASC, id ASC');
    } catch (Throwable $e) {
        return array();
    }
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_hub_section_save(PDO $pdo, $id, array $data)
{
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }
    $st = $pdo->prepare('UPDATE hh_hub_sections SET title_en = ?, title_zh = ?, desc_en = ?, desc_zh = ?, badge = ?, sort_order = ?, is_visible = ? WHERE id = ?');
    $st->execute(array(
        (string) ($data['title_en'] ?? ''),
        (string) ($data['title_zh'] ?? ''),
        (string) ($data['desc_en'] ?? ''),
        (string) ($data['desc_zh'] ?? ''),
        in_array($data['badge'] ?? '', array('live', 'soon'), true) ? $data['badge'] : 'soon',
        (int) ($data['sort_order'] ?? 0),
        !empty($data['is_visible']) ? 1 : 0,
        $id,
    ));
    return $st->rowCount() > 0;
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

/** @return array<string, array{title:string,desc:string}> */
function hh_hub_section_ui_keys()
{
    return array(
        'handhelds' => array('title' => 'section_handhelds_title', 'desc' => 'section_handhelds_desc'),
        'game' => array('title' => 'section_games_title', 'desc' => 'section_games_desc'),
        'news' => array('title' => 'section_news_title', 'desc' => 'section_news_desc'),
    );
}

function hh_hub_section_field_text($locale, $siteCode, $field, $dbValue)
{
    require_once __DIR__ . '/hub_layout.php';
    $keys = hh_hub_section_ui_keys();
    if (isset($keys[$siteCode][$field])) {
        return hh_hub_ui($locale, $keys[$siteCode][$field]);
    }
    return trim((string) $dbValue);
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
            'title' => hh_hub_section_field_text($locale, $siteCode, 'title', $locale === 'zh' ? (string) $r['title_zh'] : (string) $r['title_en']),
            'desc' => hh_hub_section_field_text($locale, $siteCode, 'desc', $locale === 'zh' ? (string) ($r['desc_zh'] ?? '') : (string) ($r['desc_en'] ?? '')),
            'badge' => $badge,
            'url' => $url,
        );
    }
    return $out;
}
