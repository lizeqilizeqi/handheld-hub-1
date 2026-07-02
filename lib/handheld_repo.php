<?php

require_once __DIR__ . '/bootstrap.php';

function hh_handheld_by_slug(PDO $pdo, $slug)
{
    $st = $pdo->prepare('SELECT * FROM hh_handhelds WHERE slug = ? LIMIT 1');
    $st->execute(array($slug));
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hh_handheld_by_id(PDO $pdo, $id)
{
    $st = $pdo->prepare('SELECT * FROM hh_handhelds WHERE id = ? LIMIT 1');
    $st->execute(array((int) $id));
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hh_handheld_list(PDO $pdo, $filters = array())
{
    $where = array('1=1');
    $params = array();

    if (!empty($filters['brand'])) {
        $where[] = 'brand = ?';
        $params[] = (string) $filters['brand'];
    }
    if (!empty($filters['status'])) {
        $where[] = 'status = ?';
        $params[] = (string) $filters['status'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(name_zh LIKE ? OR name_en LIKE ? OR slug LIKE ? OR brand LIKE ?)';
        $q = '%' . (string) $filters['q'] . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    $limit = isset($filters['limit']) ? max(1, min(500, (int) $filters['limit'])) : 50;
    $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

    $sql = 'SELECT h.*, (
        SELECT path FROM hh_handheld_images i WHERE i.handheld_id = h.id AND i.is_cover = 1 ORDER BY i.sort_order ASC LIMIT 1
    ) AS cover_path
    FROM hh_handhelds h
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY h.release_date DESC, h.id DESC
    LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_handheld_count(PDO $pdo, $filters = array())
{
    $where = array('1=1');
    $params = array();
    if (!empty($filters['brand'])) {
        $where[] = 'brand = ?';
        $params[] = (string) $filters['brand'];
    }
    if (!empty($filters['status'])) {
        $where[] = 'status = ?';
        $params[] = (string) $filters['status'];
    }
    $st = $pdo->prepare('SELECT COUNT(*) AS c FROM hh_handhelds WHERE ' . implode(' AND ', $where));
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) $row['c'] : 0;
}

function hh_brands(PDO $pdo)
{
    $st = $pdo->query('SELECT brand, COUNT(*) AS c FROM hh_handhelds WHERE brand <> "" GROUP BY brand ORDER BY brand ASC');
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_handheld_specs(PDO $pdo, $handheldId, $locale = 'zh')
{
    $st = $pdo->prepare('SELECT specs_json, specs_en_json FROM hh_handheld_specs WHERE handheld_id = ? LIMIT 1');
    $st->execute(array((int) $handheldId));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return array();
    }
    $dec = json_decode((string) $row['specs_json'], true);
    $zh = is_array($dec) ? $dec : array();
    if ($locale === 'zh') {
        return $zh;
    }
    if (!empty($row['specs_en_json'])) {
        $enDec = json_decode((string) $row['specs_en_json'], true);
        if (is_array($enDec) && count($enDec) > 0) {
            return $enDec;
        }
    }
    require_once __DIR__ . '/spec_i18n.php';
    return hh_specs_localize($zh, 'en');
}

function hh_handheld_content(PDO $pdo, $handheldId, $locale)
{
    $st = $pdo->prepare('SELECT * FROM hh_handheld_content WHERE handheld_id = ? AND locale = ? LIMIT 1');
    $st->execute(array((int) $handheldId, (string) $locale));
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hh_handheld_images(PDO $pdo, $handheldId)
{
    $st = $pdo->prepare('SELECT * FROM hh_handheld_images WHERE handheld_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC');
    $st->execute(array((int) $handheldId));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Prefer detail/product shot; fallback to cover thumbnail. */
function hh_handheld_detail_image(PDO $pdo, $handheldId)
{
    $images = hh_handheld_images($pdo, $handheldId);
    foreach ($images as $img) {
        if (empty($img['is_cover']) && !empty($img['path'])) {
            return $img;
        }
    }
    return count($images) > 0 ? $images[0] : null;
}

function hh_handheld_cover_image(PDO $pdo, $handheldId)
{
    $images = hh_handheld_images($pdo, $handheldId);
    foreach ($images as $img) {
        if (!empty($img['is_cover'])) {
            return $img;
        }
    }
    return count($images) > 0 ? $images[0] : null;
}

function hh_parse_release_date($text)
{
    $text = trim((string) $text);
    if ($text === '' || $text === '-') {
        return array(null, null, null);
    }
    if (preg_match('/(\d{4})\s*[\/\-年\.]\s*(\d{1,2})/u', $text, $m)) {
        $y = (int) $m[1];
        $mo = (int) $m[2];
        if ($mo >= 1 && $mo <= 12) {
            return array(sprintf('%04d-%02d-01', $y, $mo), $y, $mo);
        }
    }
    if (preg_match('/(\d{4})/u', $text, $m)) {
        $y = (int) $m[1];
        return array(sprintf('%04d-01-01', $y), $y, null);
    }
    return array(null, null, null);
}

function hh_content_hash($specs, $bodyHtml, $name, $imageKey = '')
{
    $payload = json_encode(array(
        'name' => (string) $name,
        'specs' => $specs,
        'body' => (string) $bodyHtml,
        'images' => (string) $imageKey,
    ), JSON_UNESCAPED_UNICODE);
    return hash('sha256', $payload);
}

function hh_handheld_upsert(PDO $pdo, $data)
{
    $existing = hh_handheld_by_slug($pdo, $data['slug']);
    $release = hh_parse_release_date(isset($data['release_text']) ? $data['release_text'] : '');

    $row = array(
        'slug' => (string) $data['slug'],
        'brand' => isset($data['brand']) ? (string) $data['brand'] : '',
        'name_zh' => isset($data['name_zh']) ? (string) $data['name_zh'] : '',
        'name_en' => isset($data['name_en']) ? (string) $data['name_en'] : '',
        'release_date' => $release[0],
        'release_year' => $release[1],
        'release_month' => $release[2],
        'screen_size' => isset($data['screen_size']) ? (string) $data['screen_size'] : '',
        'screen_ratio' => isset($data['screen_ratio']) ? (string) $data['screen_ratio'] : '',
        'source_url' => isset($data['source_url']) ? (string) $data['source_url'] : '',
        'source_scraped_at' => date('Y-m-d H:i:s'),
        'content_hash' => isset($data['content_hash']) ? (string) $data['content_hash'] : '',
        'status' => isset($data['status']) ? (string) $data['status'] : 'draft',
    );

    if ($existing) {
        $changed = ($existing['content_hash'] !== $row['content_hash']);
        $st = $pdo->prepare(
            'UPDATE hh_handhelds SET brand=?, name_zh=?, release_date=?, release_year=?, release_month=?,
             screen_size=?, screen_ratio=?, source_url=?, source_scraped_at=?, content_hash=?, updated_at=NOW()
             WHERE id=?'
        );
        $st->execute(array(
            $row['brand'], $row['name_zh'], $row['release_date'], $row['release_year'], $row['release_month'],
            $row['screen_size'], $row['screen_ratio'], $row['source_url'], $row['source_scraped_at'],
            $row['content_hash'], (int) $existing['id'],
        ));
        $id = (int) $existing['id'];
        return array('id' => $id, 'is_new' => false, 'changed' => $changed);
    }

    $st = $pdo->prepare(
        'INSERT INTO hh_handhelds (slug, brand, name_zh, name_en, release_date, release_year, release_month,
         screen_size, screen_ratio, source_url, source_scraped_at, content_hash, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $st->execute(array(
        $row['slug'], $row['brand'], $row['name_zh'], $row['name_en'], $row['release_date'],
        $row['release_year'], $row['release_month'], $row['screen_size'], $row['screen_ratio'],
        $row['source_url'], $row['source_scraped_at'], $row['content_hash'], $row['status'],
    ));
    return array('id' => (int) $pdo->lastInsertId(), 'is_new' => true, 'changed' => true);
}

function hh_handheld_save_specs(PDO $pdo, $handheldId, $specs)
{
    $json = json_encode($specs, JSON_UNESCAPED_UNICODE);
    $pdo->prepare(
        'INSERT INTO hh_handheld_specs (handheld_id, specs_json) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE specs_json = VALUES(specs_json), specs_en_json = NULL, updated_at = NOW()'
    )->execute(array((int) $handheldId, $json));
}

function hh_handheld_save_specs_en(PDO $pdo, $handheldId, $specs)
{
    $json = json_encode($specs, JSON_UNESCAPED_UNICODE);
    $pdo->prepare(
        'INSERT INTO hh_handheld_specs (handheld_id, specs_json, specs_en_json) VALUES (?, "{}", ?)
         ON DUPLICATE KEY UPDATE specs_en_json = VALUES(specs_en_json), updated_at = NOW()'
    )->execute(array((int) $handheldId, $json));
}

function hh_handheld_save_content(PDO $pdo, $handheldId, $locale, $fields)
{
    $verified = null;
    if (!empty($fields['verified_urls']) && is_array($fields['verified_urls'])) {
        $verified = json_encode($fields['verified_urls'], JSON_UNESCAPED_UNICODE);
    }
    $pdo->prepare(
        'INSERT INTO hh_handheld_content (handheld_id, locale, title, summary, body_html, meta_description, review_status, verified_urls)
         VALUES (?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE title=VALUES(title), summary=VALUES(summary), body_html=VALUES(body_html),
         meta_description=VALUES(meta_description), review_status=VALUES(review_status),
         verified_urls=VALUES(verified_urls), updated_at=NOW()'
    )->execute(array(
        (int) $handheldId,
        (string) $locale,
        isset($fields['title']) ? (string) $fields['title'] : '',
        isset($fields['summary']) ? (string) $fields['summary'] : '',
        isset($fields['body_html']) ? (string) $fields['body_html'] : '',
        isset($fields['meta_description']) ? (string) $fields['meta_description'] : '',
        isset($fields['review_status']) ? (string) $fields['review_status'] : 'pending',
        $verified,
    ));
}

function hh_handheld_replace_images(PDO $pdo, $handheldId, $images)
{
    $pdo->prepare('DELETE FROM hh_handheld_images WHERE handheld_id = ?')->execute(array((int) $handheldId));
    $sort = 0;
    foreach ($images as $img) {
        $pdo->prepare(
            'INSERT INTO hh_handheld_images (handheld_id, path, source_url, sort_order, is_cover, alt_text_zh, alt_text_en)
             VALUES (?,?,?,?,?,?,?)'
        )->execute(array(
            (int) $handheldId,
            (string) $img['path'],
            isset($img['source_url']) ? (string) $img['source_url'] : '',
            $sort,
            !empty($img['is_cover']) ? 1 : 0,
            isset($img['alt_text_zh']) ? (string) $img['alt_text_zh'] : '',
            isset($img['alt_text_en']) ? (string) $img['alt_text_en'] : '',
        ));
        $sort++;
    }
}

function hh_image_public_url($path)
{
    $path = ltrim(str_replace('\\', '/', (string) $path), '/');
    $path = preg_replace('#^storage/handhelds/#', '', $path);
    return hh_base_url() . '/i/' . $path;
}

function hh_specs_table_html($specs, $locale = 'en')
{
    if (!is_array($specs) || count($specs) === 0) {
        return '';
    }
    $label = $locale === 'zh' ? '硬件参数' : 'Specifications';
    $html = '<table class="spec-table"><caption>' . hh_h($label) . '</caption><tbody>';
    foreach ($specs as $k => $v) {
        if ($v === '' || $v === '-') {
            continue;
        }
        $html .= '<tr><th>' . hh_h($k) . '</th><td>' . hh_h($v) . '</td></tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

function hh_scrape_job_create(PDO $pdo, $type)
{
    $pdo->prepare('INSERT INTO hh_scrape_jobs (job_type, status, started_at) VALUES (?, "running", NOW())')
        ->execute(array((string) $type));
    return (int) $pdo->lastInsertId();
}

function hh_scrape_job_update_progress(PDO $pdo, $jobId, $fields)
{
    $pdo->prepare(
        'UPDATE hh_scrape_jobs SET total_pages=?, current_page=?, items_found=?, items_new=?,
         items_updated=?, items_failed=?, message=? WHERE id=? AND status = "running"'
    )->execute(array(
        (int) ($fields['total_pages'] ?? 0),
        (int) ($fields['current_page'] ?? 0),
        (int) ($fields['items_found'] ?? 0),
        (int) ($fields['items_new'] ?? 0),
        (int) ($fields['items_updated'] ?? 0),
        (int) ($fields['items_failed'] ?? 0),
        isset($fields['message']) ? (string) $fields['message'] : '',
        (int) $jobId,
    ));
}

function hh_scrape_job_finish(PDO $pdo, $jobId, $fields)
{
    $pdo->prepare(
        'UPDATE hh_scrape_jobs SET status=?, total_pages=?, current_page=?, items_found=?, items_new=?,
         items_updated=?, items_failed=?, message=?, finished_at=NOW() WHERE id=?'
    )->execute(array(
        isset($fields['status']) ? (string) $fields['status'] : 'done',
        (int) ($fields['total_pages'] ?? 0),
        (int) ($fields['current_page'] ?? 0),
        (int) ($fields['items_found'] ?? 0),
        (int) ($fields['items_new'] ?? 0),
        (int) ($fields['items_updated'] ?? 0),
        (int) ($fields['items_failed'] ?? 0),
        isset($fields['message']) ? (string) $fields['message'] : '',
        (int) $jobId,
    ));
}

function hh_scrape_log(PDO $pdo, $jobId, $level, $slug, $message)
{
    $pdo->prepare('INSERT INTO hh_scrape_logs (job_id, level, slug, message) VALUES (?,?,?,?)')
        ->execute(array($jobId ? (int) $jobId : null, (string) $level, (string) $slug, (string) $message));
}

function hh_scrape_logs_poll(PDO $pdo, $jobId, $afterId = 0, $limit = 120)
{
    $jobId = (int) $jobId;
    $afterId = (int) $afterId;
    $limit = max(1, min(200, (int) $limit));
    if ($afterId > 0) {
        $st = $pdo->prepare('SELECT * FROM hh_scrape_logs WHERE job_id = ? AND id > ? ORDER BY id ASC LIMIT ' . $limit);
        $st->execute(array($jobId, $afterId));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $st = $pdo->prepare('SELECT * FROM hh_scrape_logs WHERE job_id = ? ORDER BY id DESC LIMIT ' . $limit);
    $st->execute(array($jobId));
    $rows = array_reverse($st->fetchAll(PDO::FETCH_ASSOC));
    return $rows;
}

function hh_scrape_job_by_id(PDO $pdo, $jobId)
{
    $st = $pdo->prepare('SELECT * FROM hh_scrape_jobs WHERE id = ? LIMIT 1');
    $st->execute(array((int) $jobId));
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hh_scrape_jobs_recent(PDO $pdo, $limit = 20)
{
    $st = $pdo->prepare('SELECT * FROM hh_scrape_jobs ORDER BY id DESC LIMIT ?');
    $st->bindValue(1, (int) $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_blogger_post_row(PDO $pdo, $handheldId, $locale = 'en')
{
    $st = $pdo->prepare('SELECT * FROM hh_blogger_posts WHERE handheld_id = ? AND locale = ? LIMIT 1');
    $st->execute(array((int) $handheldId, (string) $locale));
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hh_blogger_post_save(PDO $pdo, $handheldId, $locale, $fields)
{
    $pdo->prepare(
        'INSERT INTO hh_blogger_posts (handheld_id, locale, blogger_post_id, blogger_url, sync_status, scheduled_at, published_at, last_error)
         VALUES (?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE blogger_post_id=VALUES(blogger_post_id), blogger_url=VALUES(blogger_url),
         sync_status=VALUES(sync_status), scheduled_at=VALUES(scheduled_at), published_at=VALUES(published_at),
         last_error=VALUES(last_error), updated_at=NOW()'
    )->execute(array(
        (int) $handheldId,
        (string) $locale,
        isset($fields['blogger_post_id']) ? (string) $fields['blogger_post_id'] : '',
        isset($fields['blogger_url']) ? (string) $fields['blogger_url'] : '',
        isset($fields['sync_status']) ? (string) $fields['sync_status'] : 'draft',
        isset($fields['scheduled_at']) ? $fields['scheduled_at'] : null,
        isset($fields['published_at']) ? $fields['published_at'] : null,
        isset($fields['last_error']) ? (string) $fields['last_error'] : null,
    ));
}
