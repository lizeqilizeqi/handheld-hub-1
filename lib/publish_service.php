<?php

require_once __DIR__ . '/handheld_repo.php';

function hh_publish_set_status(PDO $pdo, array $ids, $status)
{
    $allowed = array('draft', 'review', 'published');
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('无效的状态');
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
        return $v > 0;
    })));
    if ($ids === array()) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare('UPDATE hh_handhelds SET status = ? WHERE id IN (' . $placeholders . ')');
    $st->execute(array_merge(array($status), $ids));
    return $st->rowCount();
}

function hh_publish_handhelds(PDO $pdo, array $ids)
{
    return hh_publish_set_status($pdo, $ids, 'published');
}

function hh_unpublish_handhelds(PDO $pdo, array $ids)
{
    return hh_publish_set_status($pdo, $ids, 'draft');
}

function hh_publish_ready_where_sql()
{
    return array(
        "h.status <> 'published'",
        "TRIM(COALESCE(cz.body_html, '')) <> ''",
        "(TRIM(COALESCE(ce.title, '')) <> '' OR TRIM(COALESCE(ce.body_html, '')) <> '')",
        "ce.review_status IN ('ai_draft', 'human_approved')",
    );
}

/** IDs: unpublished + has zh body + EN translated (ai_draft or human_approved). */
function hh_publish_ready_handheld_ids(PDO $pdo)
{
    $where = hh_publish_ready_where_sql();
    $st = $pdo->query(
        'SELECT h.id FROM hh_handhelds h
         INNER JOIN hh_handheld_content cz ON cz.handheld_id = h.id AND cz.locale = "zh"
         INNER JOIN hh_handheld_content ce ON ce.handheld_id = h.id AND ce.locale = "en"
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY h.release_date DESC, h.id DESC'
    );
    $ids = array();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ids[] = (int) $row['id'];
    }
    return $ids;
}

function hh_publish_ready_count(PDO $pdo)
{
    $where = hh_publish_ready_where_sql();
    $st = $pdo->query(
        'SELECT COUNT(*) AS c FROM hh_handhelds h
         INNER JOIN hh_handheld_content cz ON cz.handheld_id = h.id AND cz.locale = "zh"
         INNER JOIN hh_handheld_content ce ON ce.handheld_id = h.id AND ce.locale = "en"
         WHERE ' . implode(' AND ', $where)
    );
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) $row['c'] : 0;
}

/** @return array{ready:bool,warnings:array<int,string>,zh_ok:bool,en_ok:bool} */
function hh_publish_readiness(PDO $pdo, $handheldId)
{
    $h = hh_handheld_by_id($pdo, (int) $handheldId);
    if (!$h) {
        return array('ready' => false, 'warnings' => array('掌机不存在'), 'zh_ok' => false, 'en_ok' => false);
    }

    $zh = hh_handheld_content($pdo, (int) $handheldId, 'zh');
    $en = hh_handheld_content($pdo, (int) $handheldId, 'en');
    $warnings = array();

    $zhOk = $zh && (!empty($zh['title']) || !empty($zh['body_html']));
    $enOk = $en && (!empty($en['title']) || !empty($en['body_html']));

    if (!$zhOk) {
        $warnings[] = '缺少中文正文，建议先抓取或编辑后再发布。';
    }
    if (!$enOk) {
        $warnings[] = '尚无英文内容，/en/ 页面会回退显示中文。';
    }
    if ($en && ($en['review_status'] ?? '') !== 'human_approved' && $enOk) {
        $warnings[] = '英文尚未标记「人工已通过」，建议翻译后审核再发布。';
    }

    return array(
        'ready' => $zhOk,
        'warnings' => $warnings,
        'zh_ok' => $zhOk,
        'en_ok' => $enOk,
    );
}

function hh_publish_list(PDO $pdo, $filters = array())
{
    $where = array('1=1');
    $params = array();

    if (!empty($filters['brand'])) {
        $where[] = 'h.brand = ?';
        $params[] = (string) $filters['brand'];
    }
    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        if ($filters['status'] === 'ready') {
            foreach (hh_publish_ready_where_sql() as $clause) {
                $where[] = $clause;
            }
        } else {
            $where[] = 'h.status = ?';
            $params[] = (string) $filters['status'];
        }
    }
    if (!empty($filters['q'])) {
        $where[] = '(h.name_zh LIKE ? OR h.name_en LIKE ? OR h.slug LIKE ? OR h.brand LIKE ?)';
        $q = '%' . (string) $filters['q'] . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    $limit = isset($filters['limit']) ? max(1, min(100, (int) $filters['limit'])) : 50;
    $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

    $sql = 'SELECT h.*, (
        SELECT path FROM hh_handheld_images i WHERE i.handheld_id = h.id AND i.is_cover = 1 ORDER BY i.sort_order ASC LIMIT 1
    ) AS cover_path,
    cz.review_status AS review_zh,
    ce.review_status AS review_en,
    (cz.title <> "" OR cz.body_html <> "") AS has_zh,
    (ce.title <> "" OR ce.body_html <> "") AS has_en
    FROM hh_handhelds h
    LEFT JOIN hh_handheld_content cz ON cz.handheld_id = h.id AND cz.locale = "zh"
    LEFT JOIN hh_handheld_content ce ON ce.handheld_id = h.id AND ce.locale = "en"
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY h.release_date DESC, h.id DESC
    LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_publish_list_count(PDO $pdo, $filters = array())
{
    $where = array('1=1');
    $params = array();

    if (!empty($filters['brand'])) {
        $where[] = 'h.brand = ?';
        $params[] = (string) $filters['brand'];
    }
    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        if ($filters['status'] === 'ready') {
            foreach (hh_publish_ready_where_sql() as $clause) {
                $where[] = $clause;
            }
        } else {
            $where[] = 'h.status = ?';
            $params[] = (string) $filters['status'];
        }
    }
    if (!empty($filters['q'])) {
        $where[] = '(h.name_zh LIKE ? OR h.name_en LIKE ? OR h.slug LIKE ? OR h.brand LIKE ?)';
        $q = '%' . (string) $filters['q'] . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    $sql = 'SELECT COUNT(*) AS c FROM hh_handhelds h
        LEFT JOIN hh_handheld_content cz ON cz.handheld_id = h.id AND cz.locale = "zh"
        LEFT JOIN hh_handheld_content ce ON ce.handheld_id = h.id AND ce.locale = "en"
        WHERE ' . implode(' AND ', $where);

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) $row['c'] : 0;
}
