<?php

require_once __DIR__ . '/feed_brand_sanitize.php';

function hh_feed_tables_exist(PDO $pdo)
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $pdo->query('SELECT 1 FROM hh_feed_sources LIMIT 1');
        $ok = true;
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

function hh_feed_slugify($title, PDO $pdo = null)
{
    $s = strtolower(trim((string) $title));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    if ($s === '') {
        $s = 'item-' . bin2hex(random_bytes(4));
    }
    if ($s !== '' && strlen($s) > 140) {
        $s = substr($s, 0, 140);
        $s = rtrim($s, '-');
    }
    if ($pdo === null || !hh_feed_tables_exist($pdo)) {
        return $s;
    }
    $base = $s;
    $n = 0;
    while (true) {
        $try = $n === 0 ? $base : $base . '-' . $n;
        $st = $pdo->prepare('SELECT id FROM hh_feed_items WHERE slug = ? LIMIT 1');
        $st->execute(array($try));
        if (!$st->fetchColumn()) {
            return $try;
        }
        $n++;
    }
}

function hh_feed_sources_list(PDO $pdo, $enabledOnly = false)
{
    if (!hh_feed_tables_exist($pdo)) {
        return array();
    }
    $sql = 'SELECT id, site_code, name, feed_url, homepage_url, source_kind, is_enabled, fetch_interval_minutes, last_fetched_at, last_error FROM hh_feed_sources';
    if ($enabledOnly) {
        $sql .= ' WHERE is_enabled = 1';
    }
    $sql .= ' ORDER BY id ASC';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function hh_feed_source_by_id(PDO $pdo, $id)
{
    $st = $pdo->prepare('SELECT * FROM hh_feed_sources WHERE id = ? LIMIT 1');
    $st->execute(array((int) $id));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hh_feed_source_kind(PDO $pdo, array $source)
{
    if (!empty($source['source_kind']) && in_array($source['source_kind'], array('rss', 'html_scrape'), true)) {
        return (string) $source['source_kind'];
    }
    $url = (string) ($source['feed_url'] ?? '');
    if (preg_match('#\.html(?:\?|$)#i', $url)) {
        return 'html_scrape';
    }
    return 'rss';
}

function hh_feed_source_save(PDO $pdo, array $data, $id = null)
{
    $kind = isset($data['source_kind']) && in_array($data['source_kind'], array('rss', 'html_scrape'), true)
        ? (string) $data['source_kind']
        : (preg_match('#\.html(?:\?|$)#i', (string) ($data['feed_url'] ?? '')) ? 'html_scrape' : 'rss');
    $fields = array(
        'site_code' => (string) ($data['site_code'] ?? 'news'),
        'name' => trim((string) ($data['name'] ?? '')),
        'feed_url' => trim((string) ($data['feed_url'] ?? '')),
        'homepage_url' => trim((string) ($data['homepage_url'] ?? '')),
        'source_kind' => $kind,
        'is_enabled' => !empty($data['is_enabled']) ? 1 : 0,
        'fetch_interval_minutes' => max(15, (int) ($data['fetch_interval_minutes'] ?? 360)),
    );
    if ($id !== null && (int) $id > 0) {
        $st = $pdo->prepare('UPDATE hh_feed_sources SET site_code=?, name=?, feed_url=?, homepage_url=?, source_kind=?, is_enabled=?, fetch_interval_minutes=? WHERE id=?');
        $st->execute(array(
            $fields['site_code'], $fields['name'], $fields['feed_url'], $fields['homepage_url'],
            $fields['source_kind'], $fields['is_enabled'], $fields['fetch_interval_minutes'], (int) $id,
        ));
        return (int) $id;
    }
    $st = $pdo->prepare('INSERT INTO hh_feed_sources (site_code, name, feed_url, homepage_url, source_kind, is_enabled, fetch_interval_minutes) VALUES (?,?,?,?,?,?,?)');
    $st->execute(array(
        $fields['site_code'], $fields['name'], $fields['feed_url'], $fields['homepage_url'],
        $fields['source_kind'], $fields['is_enabled'], $fields['fetch_interval_minutes'],
    ));
    return (int) $pdo->lastInsertId();
}

function hh_feed_source_mark_fetched(PDO $pdo, $id, $error = null)
{
    if ($error !== null && $error !== '') {
        $st = $pdo->prepare('UPDATE hh_feed_sources SET last_fetched_at = NOW(), last_error = ? WHERE id = ?');
        $st->execute(array((string) $error, (int) $id));
        return;
    }
    $st = $pdo->prepare('UPDATE hh_feed_sources SET last_fetched_at = NOW(), last_error = NULL WHERE id = ?');
    $st->execute(array((int) $id));
}

function hh_feed_sources_due(PDO $pdo)
{
    $out = array();
    foreach (hh_feed_sources_list($pdo, true) as $src) {
        $mins = (int) $src['fetch_interval_minutes'];
        if (empty($src['last_fetched_at'])) {
            $out[] = $src;
            continue;
        }
        $due = strtotime((string) $src['last_fetched_at']) + ($mins * 60);
        if ($due <= time()) {
            $out[] = $src;
        }
    }
    return $out;
}

function hh_feed_item_upsert(PDO $pdo, $sourceId, array $item)
{
    $guid = (string) ($item['guid'] ?? $item['link'] ?? '');
    $link = trim((string) ($item['link'] ?? ''));
    if ($link === '') {
        return 'skip';
    }
    $hash = hash('sha256', (int) $sourceId . '|' . $guid);
    $st = $pdo->prepare('SELECT id FROM hh_feed_items WHERE guid_hash = ? LIMIT 1');
    $st->execute(array($hash));
    if ($st->fetchColumn()) {
        return 'dup';
    }
    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
        $title = $link;
    }
    $slug = hh_feed_slugify($title, $pdo);
    $st = $pdo->prepare('INSERT INTO hh_feed_items (source_id, guid_hash, slug, title, link, summary, author, published_at, status) VALUES (?,?,?,?,?,?,?,?,"pending")');
    $st->execute(array(
        (int) $sourceId,
        $hash,
        $slug,
        $title,
        $link,
        (string) ($item['summary'] ?? ''),
        (string) ($item['author'] ?? ''),
        !empty($item['published_at']) ? (string) $item['published_at'] : null,
    ));
    return 'new';
}

function hh_feed_items_public(PDO $pdo, array $opts = array())
{
    if (!hh_feed_tables_exist($pdo)) {
        return array();
    }
    $limit = isset($opts['limit']) ? max(1, (int) $opts['limit']) : 50;
    $offset = isset($opts['offset']) ? max(0, (int) $opts['offset']) : 0;
    $st = $pdo->prepare('SELECT i.id, i.slug, i.title, i.title_zh, i.title_en, i.link, i.summary, i.summary_zh, i.summary_en, i.body_zh, i.body_en, i.image_url, i.author, i.published_at, i.updated_at, s.name AS source_name, s.homepage_url AS source_home
        FROM hh_feed_items i
        INNER JOIN hh_feed_sources s ON s.id = i.source_id
        WHERE i.status = "published"
        ORDER BY COALESCE(i.published_at, i.fetched_at) DESC, i.id DESC
        LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_feed_items_count_public(PDO $pdo)
{
    if (!hh_feed_tables_exist($pdo)) {
        return 0;
    }
    return (int) $pdo->query('SELECT COUNT(*) FROM hh_feed_items WHERE status = "published"')->fetchColumn();
}

function hh_feed_item_by_slug(PDO $pdo, $slug, $status = 'published')
{
    $st = $pdo->prepare('SELECT i.*, s.name AS source_name, s.homepage_url AS source_home
        FROM hh_feed_items i
        INNER JOIN hh_feed_sources s ON s.id = i.source_id
        WHERE i.slug = ? AND i.status = ?
        LIMIT 1');
    $st->execute(array((string) $slug, $status));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hh_feed_items_admin(PDO $pdo, $status = null, $limit = 100)
{
    $sql = 'SELECT i.id, i.slug, i.title, i.link, i.status, i.published_at, i.fetched_at, s.name AS source_name
        FROM hh_feed_items i
        INNER JOIN hh_feed_sources s ON s.id = i.source_id';
    $params = array();
    if ($status !== null && $status !== '') {
        $sql .= ' WHERE i.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY i.fetched_at DESC LIMIT ' . (int) $limit;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_feed_item_set_status(PDO $pdo, $id, $status)
{
    if (!in_array($status, array('pending', 'published', 'hidden'), true)) {
        return false;
    }
    $st = $pdo->prepare('UPDATE hh_feed_items SET status = ? WHERE id = ?');
    $st->execute(array($status, (int) $id));
    return $st->rowCount() > 0;
}

function hh_feed_strip_html($html)
{
    $html = (string) $html;
    $html = strip_tags($html);
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = preg_replace('/\s+/u', ' ', $html);
    return trim($html);
}

function hh_feed_summary_excerpt($text, $maxLen = 200)
{
    $text = hh_feed_strip_html($text);
    if ($text === '') {
        return '';
    }
    $maxLen = max(20, (int) $maxLen);
    if (mb_strlen($text) <= $maxLen) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $maxLen - 1)) . '…';
}

function hh_feed_item_title($row, $locale)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    if ($locale === 'zh') {
        if (!empty($row['title_zh'])) {
            return (string) $row['title_zh'];
        }
        return (string) ($row['title'] ?? '');
    }
    if (!empty($row['title_en'])) {
        return (string) $row['title_en'];
    }
    if (!empty($row['title_zh'])) {
        return (string) $row['title_zh'];
    }
    return (string) ($row['title'] ?? '');
}

function hh_feed_item_summary($row, $locale)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    if ($locale === 'zh') {
        $t = (string) ($row['summary_zh'] ?? $row['summary'] ?? '');
        return $t;
    }
    $t = (string) ($row['summary_en'] ?? '');
    if ($t !== '') {
        return $t;
    }
    return (string) ($row['summary_zh'] ?? $row['summary'] ?? '');
}

function hh_feed_item_body($row, $locale)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    if ($locale === 'en' && !empty($row['body_en'])) {
        return (string) $row['body_en'];
    }
    return (string) ($row['body_zh'] ?? '');
}

/** Source line on public pages — English never shows Chinese vendor label. */
function hh_feed_source_label($row, $locale)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    if (hh_feed_item_is_manual($row)) {
        return $locale === 'zh' ? '本站' : 'Old Man Hub';
    }
    $name = (string) ($row['source_name'] ?? '');
    if ($locale === 'en') {
        if (stripos($name, '快科技') !== false || stripos((string) ($row['link'] ?? ''), 'mydrivers.com') !== false) {
            return 'MyDrivers';
        }
        return $name !== '' ? $name : '';
    }
    if ($name !== '') {
        return $name;
    }
    return '快科技';
}

function hh_feed_item_image_url($row)
{
    $img = trim((string) ($row['image_url'] ?? ''));
    if ($img !== '' && strpos($img, '//') === 0) {
        return 'https:' . $img;
    }
    if ($img !== '') {
        return $img;
    }
    if ($img === '' && !empty($row['body_zh']) && preg_match('#src="([^"]+)"#i', (string) $row['body_zh'], $m)) {
        $img = $m[1];
    }
    if ($img !== '' && strpos($img, '//') === 0) {
        return 'https:' . $img;
    }
    return $img;
}

function hh_feed_item_by_id(PDO $pdo, $id)
{
    $st = $pdo->prepare('SELECT i.*, s.name AS source_name, s.homepage_url AS source_home FROM hh_feed_items i INNER JOIN hh_feed_sources s ON s.id = i.source_id WHERE i.id = ? LIMIT 1');
    $st->execute(array((int) $id));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hh_feed_item_save(PDO $pdo, $id, array $data)
{
    $st = $pdo->prepare('UPDATE hh_feed_items SET title_zh=?, title_en=?, summary_zh=?, summary_en=?, body_zh=?, body_en=?, status=?, translate_status=? WHERE id=?');
    $st->execute(array(
        (string) ($data['title_zh'] ?? ''),
        (string) ($data['title_en'] ?? ''),
        (string) ($data['summary_zh'] ?? ''),
        (string) ($data['summary_en'] ?? ''),
        (string) ($data['body_zh'] ?? ''),
        (string) ($data['body_en'] ?? ''),
        in_array($data['status'] ?? '', array('pending', 'published', 'hidden'), true) ? $data['status'] : 'pending',
        in_array($data['translate_status'] ?? '', array('pending', 'done', 'failed'), true) ? $data['translate_status'] : 'pending',
        (int) $id,
    ));
    $pdo->prepare('UPDATE hh_feed_items SET title = title_zh WHERE id = ? AND title_zh <> ""')->execute(array((int) $id));
    return $st->rowCount() > 0;
}

function hh_feed_items_admin_list(PDO $pdo, array $opts = array())
{
    if (!hh_feed_tables_exist($pdo)) {
        return array();
    }
    $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
    $limit = isset($opts['limit']) ? (int) $opts['limit'] : 150;
    $sql = 'SELECT i.id, i.slug, i.title, i.title_zh, i.title_en, i.link, i.status, i.translate_status, i.published_at, i.fetched_at, s.name AS source_name
        FROM hh_feed_items i INNER JOIN hh_feed_sources s ON s.id = i.source_id';
    $params = array();
    if ($q !== '') {
        $sql .= ' WHERE (i.title_zh LIKE ? OR i.title_en LIKE ? OR i.title LIKE ? OR i.slug LIKE ?)';
        $like = '%' . $q . '%';
        $params = array($like, $like, $like, $like);
    }
    $sql .= ' ORDER BY COALESCE(i.published_at, i.fetched_at) DESC, i.id DESC LIMIT ' . max(1, $limit);
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_feed_translate_pending_ids(PDO $pdo, $limit = 50)
{
    if (!hh_feed_tables_exist($pdo)) {
        return array();
    }
    $st = $pdo->prepare('SELECT id FROM hh_feed_items WHERE translate_status = "pending" AND TRIM(title_zh) <> "" ORDER BY COALESCE(published_at, fetched_at) DESC LIMIT ' . (int) $limit);
    $st->execute();
    return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
}

function hh_feed_publish_ready_ids(PDO $pdo, $limit = 100)
{
    if (!hh_feed_tables_exist($pdo)) {
        return array();
    }
    $st = $pdo->prepare('SELECT id FROM hh_feed_items WHERE status = "pending" AND translate_status = "done" AND TRIM(title_en) <> "" ORDER BY COALESCE(published_at, fetched_at) DESC LIMIT ' . (int) $limit);
    $st->execute();
    return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
}

function hh_feed_has_item_kind(PDO $pdo)
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (!hh_feed_tables_exist($pdo)) {
        $ok = false;
        return false;
    }
    try {
        $pdo->query('SELECT item_kind FROM hh_feed_items LIMIT 1');
        $ok = true;
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

function hh_feed_item_is_manual(array $row)
{
    return isset($row['item_kind']) && (string) $row['item_kind'] === 'manual';
}

function hh_feed_manual_source_id(PDO $pdo)
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }
    if (!hh_feed_tables_exist($pdo)) {
        return 0;
    }
    $st = $pdo->prepare('SELECT id FROM hh_feed_sources WHERE site_code = ? AND name = ? LIMIT 1');
    $st->execute(array('news', '本站原创'));
    $id = (int) $st->fetchColumn();
    if ($id > 0) {
        return $id;
    }
    $st = $pdo->prepare('INSERT INTO hh_feed_sources (site_code, name, feed_url, homepage_url, source_kind, is_enabled, fetch_interval_minutes) VALUES (?,?,?,?,?,0,10080)');
    $st->execute(array('news', '本站原创', '', 'https://www.oldmanhub.com', 'rss'));
    $id = (int) $pdo->lastInsertId();
    return $id;
}

function hh_feed_item_create_manual(PDO $pdo, array $data)
{
    require_once __DIR__ . '/news_editor.php';
    $sourceId = hh_feed_manual_source_id($pdo);
    if ($sourceId <= 0) {
        throw new RuntimeException('资讯源未就绪');
    }
    $titleZh = trim((string) ($data['title_zh'] ?? ''));
    if ($titleZh === '') {
        throw new RuntimeException('请填写标题');
    }
    $titleEn = trim((string) ($data['title_en'] ?? ''));
    $bodyZh = hh_news_editor_prepare_body((string) ($data['body_zh'] ?? ''));
    if ($bodyZh === '') {
        throw new RuntimeException('请填写正文');
    }
    $bodyEn = trim((string) ($data['body_en'] ?? ''));
    if ($bodyEn === '') {
        $bodyEn = $bodyZh;
    } else {
        $bodyEn = hh_news_editor_prepare_body($bodyEn);
    }
    $summaryZh = trim((string) ($data['summary_zh'] ?? ''));
    if ($summaryZh === '') {
        $summaryZh = hh_news_editor_excerpt($bodyZh);
    }
    $summaryEn = trim((string) ($data['summary_en'] ?? ''));
    if ($summaryEn === '') {
        $summaryEn = hh_news_editor_excerpt($bodyEn);
    }
    $slug = isset($data['slug']) && trim((string) $data['slug']) !== ''
        ? hh_feed_slugify(trim((string) $data['slug']), $pdo)
        : hh_feed_slugify($titleZh, $pdo);
    $status = in_array($data['status'] ?? '', array('pending', 'published', 'hidden'), true) ? $data['status'] : 'pending';
    $imageUrl = trim((string) ($data['image_url'] ?? ''));
    $publishedAt = !empty($data['published_at']) ? (string) $data['published_at'] : null;
    if ($status === 'published' && $publishedAt === null) {
        $publishedAt = date('Y-m-d H:i:s');
    }
    $hash = hash('sha256', 'manual|' . $slug);
    $hasKind = hh_feed_has_item_kind($pdo);
    if ($hasKind) {
        $st = $pdo->prepare('INSERT INTO hh_feed_items (source_id, item_kind, guid_hash, slug, title, title_zh, title_en, summary, summary_zh, summary_en, body_zh, body_en, link, image_url, author, published_at, status, translate_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'done\')');
        $st->execute(array(
            $sourceId, 'manual', $hash, $slug, $titleZh, $titleZh, $titleEn,
            $summaryZh, $summaryZh, $summaryEn, $bodyZh, $bodyEn, '',
            $imageUrl, 'Old Man Hub', $publishedAt, $status,
        ));
    } else {
        $st = $pdo->prepare('INSERT INTO hh_feed_items (source_id, guid_hash, slug, title, title_zh, title_en, summary, summary_zh, summary_en, body_zh, body_en, link, image_url, author, published_at, status, translate_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'done\')');
        $st->execute(array(
            $sourceId, $hash, $slug, $titleZh, $titleZh, $titleEn,
            $summaryZh, $summaryZh, $summaryEn, $bodyZh, $bodyEn, '',
            $imageUrl, 'Old Man Hub', $publishedAt, $status,
        ));
    }
    return (int) $pdo->lastInsertId();
}

function hh_feed_item_save_manual(PDO $pdo, $id, array $data)
{
    require_once __DIR__ . '/news_editor.php';
    $row = hh_feed_item_by_id($pdo, (int) $id);
    if (!$row) {
        throw new RuntimeException('资讯不存在');
    }
    $titleZh = trim((string) ($data['title_zh'] ?? ''));
    if ($titleZh === '') {
        throw new RuntimeException('请填写标题');
    }
    $titleEn = trim((string) ($data['title_en'] ?? ''));
    $bodyZh = hh_news_editor_prepare_body((string) ($data['body_zh'] ?? ''));
    if ($bodyZh === '') {
        throw new RuntimeException('请填写正文');
    }
    $bodyEn = trim((string) ($data['body_en'] ?? ''));
    if ($bodyEn === '') {
        $bodyEn = $bodyZh;
    } else {
        $bodyEn = hh_news_editor_prepare_body($bodyEn);
    }
    $summaryZh = trim((string) ($data['summary_zh'] ?? ''));
    if ($summaryZh === '') {
        $summaryZh = hh_news_editor_excerpt($bodyZh);
    }
    $summaryEn = trim((string) ($data['summary_en'] ?? ''));
    if ($summaryEn === '') {
        $summaryEn = hh_news_editor_excerpt($bodyEn);
    }
    $status = in_array($data['status'] ?? '', array('pending', 'published', 'hidden'), true) ? $data['status'] : $row['status'];
    $imageUrl = trim((string) ($data['image_url'] ?? ''));
    $publishedAt = !empty($data['published_at']) ? (string) $data['published_at'] : ($row['published_at'] ?? null);
    if ($status === 'published' && empty($publishedAt)) {
        $publishedAt = date('Y-m-d H:i:s');
    }
    $st = $pdo->prepare('UPDATE hh_feed_items SET title=?, title_zh=?, title_en=?, summary=?, summary_zh=?, summary_en=?, body_zh=?, body_en=?, image_url=?, status=?, translate_status="done", published_at=? WHERE id=?');
    $st->execute(array(
        $titleZh, $titleZh, $titleEn, $summaryZh, $summaryZh, $summaryEn,
        $bodyZh, $bodyEn, $imageUrl, $status, $publishedAt, (int) $id,
    ));
    if (hh_feed_has_item_kind($pdo)) {
        $pdo->prepare('UPDATE hh_feed_items SET item_kind = "manual" WHERE id = ?')->execute(array((int) $id));
    }
    return true;
}
