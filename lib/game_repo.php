<?php

function hh_game_table_exists(PDO $pdo)
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $pdo->query('SELECT 1 FROM hh_game_entries LIMIT 1');
        $ok = true;
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

function hh_game_slugify($title)
{
    $s = strtolower(trim((string) $title));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? $s : 'game-' . bin2hex(random_bytes(3));
}

function hh_game_list(PDO $pdo, array $opts = array())
{
    if (!hh_game_table_exists($pdo)) {
        return array();
    }
    $status = isset($opts['status']) ? (string) $opts['status'] : 'published';
    $limit = isset($opts['limit']) ? max(1, (int) $opts['limit']) : 100;
    $offset = isset($opts['offset']) ? max(0, (int) $opts['offset']) : 0;
    $platform = isset($opts['platform']) ? trim((string) $opts['platform']) : '';

    $sql = 'SELECT id, slug, platform, title_en, title_zh, summary_en, summary_zh, external_url, status, sort_order, published_at, updated_at FROM hh_game_entries WHERE status = ?';
    $params = array($status);
    if ($platform !== '') {
        $sql .= ' AND platform = ?';
        $params[] = $platform;
    }
    $sql .= ' ORDER BY sort_order ASC, published_at DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_game_count(PDO $pdo, $status = 'published', $platform = '')
{
    if (!hh_game_table_exists($pdo)) {
        return 0;
    }
    $sql = 'SELECT COUNT(*) FROM hh_game_entries WHERE status = ?';
    $params = array($status);
    if ($platform !== '') {
        $sql .= ' AND platform = ?';
        $params[] = $platform;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (int) $st->fetchColumn();
}

function hh_game_by_slug(PDO $pdo, $slug, $status = 'published')
{
    if (!hh_game_table_exists($pdo)) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM hh_game_entries WHERE slug = ? AND status = ? LIMIT 1');
    $st->execute(array((string) $slug, $status));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hh_game_by_id(PDO $pdo, $id)
{
    $st = $pdo->prepare('SELECT * FROM hh_game_entries WHERE id = ? LIMIT 1');
    $st->execute(array((int) $id));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hh_game_platforms(PDO $pdo, $status = 'published')
{
    if (!hh_game_table_exists($pdo)) {
        return array();
    }
    $st = $pdo->prepare('SELECT DISTINCT platform FROM hh_game_entries WHERE status = ? AND platform <> "" ORDER BY platform ASC');
    $st->execute(array($status));
    return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'platform');
}

function hh_game_title($row, $locale)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    if ($locale === 'zh' && !empty($row['title_zh'])) {
        return (string) $row['title_zh'];
    }
    return (string) ($row['title_en'] ?? '');
}

function hh_game_summary($row, $locale)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    if ($locale === 'zh' && !empty($row['summary_zh'])) {
        return (string) $row['summary_zh'];
    }
    return (string) ($row['summary_en'] ?? '');
}

function hh_game_body($row, $locale)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    if ($locale === 'zh' && !empty($row['body_zh'])) {
        return (string) $row['body_zh'];
    }
    return (string) ($row['body_en'] ?? '');
}

function hh_game_save(PDO $pdo, array $data, $id = null)
{
    $slug = trim((string) ($data['slug'] ?? ''));
    if ($slug === '') {
        $slug = hh_game_slugify($data['title_en'] ?? 'game');
    }
    $fields = array(
        'slug' => $slug,
        'platform' => trim((string) ($data['platform'] ?? '')),
        'title_en' => trim((string) ($data['title_en'] ?? '')),
        'title_zh' => trim((string) ($data['title_zh'] ?? '')),
        'summary_en' => (string) ($data['summary_en'] ?? ''),
        'summary_zh' => (string) ($data['summary_zh'] ?? ''),
        'body_en' => (string) ($data['body_en'] ?? ''),
        'body_zh' => (string) ($data['body_zh'] ?? ''),
        'external_url' => trim((string) ($data['external_url'] ?? '')),
        'status' => ($data['status'] ?? '') === 'published' ? 'published' : 'draft',
        'sort_order' => (int) ($data['sort_order'] ?? 0),
        'published_at' => !empty($data['published_at']) ? (string) $data['published_at'] : null,
    );
    if ($fields['status'] === 'published' && $fields['published_at'] === null) {
        $fields['published_at'] = date('Y-m-d H:i:s');
    }

    if ($id !== null && (int) $id > 0) {
        $st = $pdo->prepare('UPDATE hh_game_entries SET slug=?, platform=?, title_en=?, title_zh=?, summary_en=?, summary_zh=?, body_en=?, body_zh=?, external_url=?, status=?, sort_order=?, published_at=? WHERE id=?');
        $st->execute(array(
            $fields['slug'], $fields['platform'], $fields['title_en'], $fields['title_zh'],
            $fields['summary_en'], $fields['summary_zh'], $fields['body_en'], $fields['body_zh'],
            $fields['external_url'], $fields['status'], $fields['sort_order'], $fields['published_at'],
            (int) $id,
        ));
        return (int) $id;
    }

    $st = $pdo->prepare('INSERT INTO hh_game_entries (slug, platform, title_en, title_zh, summary_en, summary_zh, body_en, body_zh, external_url, status, sort_order, published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute(array(
        $fields['slug'], $fields['platform'], $fields['title_en'], $fields['title_zh'],
        $fields['summary_en'], $fields['summary_zh'], $fields['body_en'], $fields['body_zh'],
        $fields['external_url'], $fields['status'], $fields['sort_order'], $fields['published_at'],
    ));
    return (int) $pdo->lastInsertId();
}

function hh_game_admin_list(PDO $pdo, $limit = 200)
{
    if (!hh_game_table_exists($pdo)) {
        return array();
    }
    $st = $pdo->query('SELECT id, slug, platform, title_en, title_zh, status, sort_order, updated_at FROM hh_game_entries ORDER BY updated_at DESC LIMIT ' . (int) $limit);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** draft + zh title + en title/summary ready */
function hh_game_publish_ready_ids(PDO $pdo, $limit = 100)
{
    if (!hh_game_table_exists($pdo)) {
        return array();
    }
    $st = $pdo->prepare('SELECT id FROM hh_game_entries WHERE status = "draft" AND TRIM(title_zh) <> "" AND TRIM(title_en) <> "" AND TRIM(summary_en) <> "" ORDER BY updated_at DESC LIMIT ' . (int) $limit);
    $st->execute();
    return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
}
