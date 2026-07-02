<?php

require_once __DIR__ . '/handheld_repo.php';
require_once __DIR__ . '/deepseek.php';

function hh_translate_handheld_to_en(PDO $pdo, $id, $verifiedUrls = array())
{
    $h = hh_handheld_by_id($pdo, (int) $id);
    if (!$h) {
        throw new RuntimeException('未找到掌机 #' . (int) $id);
    }
    $zh = hh_handheld_content($pdo, (int) $id, 'zh');
    if (!$zh || trim((string) ($zh['body_html'] ?? '')) === '') {
        throw new RuntimeException($h['name_zh'] . '：缺少中文正文，请先抓取');
    }

    $specs = hh_handheld_specs($pdo, (int) $id, 'zh');
    $en = hh_deepseek_translate_handheld($h, $specs, $zh, $verifiedUrls);

    hh_handheld_save_content($pdo, (int) $id, 'en', array_merge($en, array(
        'review_status' => 'ai_draft',
        'verified_urls' => $verifiedUrls,
    )));

    if (!empty($en['specs_en'])) {
        hh_handheld_save_specs_en($pdo, (int) $id, $en['specs_en']);
    }
    if (!empty($en['name_en'])) {
        $pdo->prepare('UPDATE hh_handhelds SET name_en=? WHERE id=?')->execute(array((string) $en['name_en'], (int) $id));
    }

    return array('handheld' => $h, 'en' => $en);
}

function hh_translate_pending_handheld_ids(PDO $pdo, $includeDraft = false)
{
    $statuses = array('pending');
    if ($includeDraft) {
        $statuses[] = 'ai_draft';
    }
    $ph = implode(',', array_fill(0, count($statuses), '?'));
    $st = $pdo->prepare(
        "SELECT h.id FROM hh_handhelds h
         INNER JOIN hh_handheld_content zh ON zh.handheld_id = h.id AND zh.locale = 'zh'
         LEFT JOIN hh_handheld_content en ON en.handheld_id = h.id AND en.locale = 'en'
         WHERE TRIM(COALESCE(zh.body_html, '')) <> ''
         AND (en.id IS NULL OR en.review_status IN ($ph))
         ORDER BY h.release_date DESC, h.id DESC"
    );
    $st->execute($statuses);
    $ids = array();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ids[] = (int) $row['id'];
    }
    return $ids;
}

/** @return array{pending:int,ai_draft:int,human_approved:int,no_zh:int} */
function hh_translate_review_counts(PDO $pdo)
{
    $st = $pdo->query(
        "SELECT
            SUM(CASE WHEN TRIM(COALESCE(zh.body_html, '')) = '' THEN 1 ELSE 0 END) AS no_zh,
            SUM(CASE WHEN TRIM(COALESCE(zh.body_html, '')) <> '' AND (en.id IS NULL OR en.review_status = 'pending') THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN en.review_status = 'ai_draft' THEN 1 ELSE 0 END) AS ai_draft,
            SUM(CASE WHEN en.review_status = 'human_approved' THEN 1 ELSE 0 END) AS human_approved
         FROM hh_handhelds h
         LEFT JOIN hh_handheld_content zh ON zh.handheld_id = h.id AND zh.locale = 'zh'
         LEFT JOIN hh_handheld_content en ON en.handheld_id = h.id AND en.locale = 'en'"
    );
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: array();
    return array(
        'pending' => (int) ($row['pending'] ?? 0),
        'ai_draft' => (int) ($row['ai_draft'] ?? 0),
        'human_approved' => (int) ($row['human_approved'] ?? 0),
        'no_zh' => (int) ($row['no_zh'] ?? 0),
    );
}

function hh_translate_page_list(PDO $pdo, $filters = array())
{
    $where = array('1=1');
    $params = array();

    if (!empty($filters['brand'])) {
        $where[] = 'h.brand = ?';
        $params[] = (string) $filters['brand'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(h.name_zh LIKE ? OR h.name_en LIKE ? OR h.slug LIKE ? OR h.brand LIKE ?)';
        $q = '%' . (string) $filters['q'] . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    $review = isset($filters['review']) ? (string) $filters['review'] : 'all';
    if ($review === 'pending') {
        $where[] = "TRIM(COALESCE(zh.body_html, '')) <> ''";
        $where[] = "(en.id IS NULL OR en.review_status = 'pending')";
    } elseif ($review === 'ai_draft') {
        $where[] = "en.review_status = 'ai_draft'";
    } elseif ($review === 'human_approved') {
        $where[] = "en.review_status = 'human_approved'";
    }

    $limit = isset($filters['limit']) ? max(1, min(100, (int) $filters['limit'])) : 50;
    $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

    $sql = 'SELECT h.*, en.review_status AS review_en
        FROM hh_handhelds h
        LEFT JOIN hh_handheld_content zh ON zh.handheld_id = h.id AND zh.locale = "zh"
        LEFT JOIN hh_handheld_content en ON en.handheld_id = h.id AND en.locale = "en"
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY h.release_date DESC, h.id DESC
        LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_translate_page_count(PDO $pdo, $filters = array())
{
    $where = array('1=1');
    $params = array();

    if (!empty($filters['brand'])) {
        $where[] = 'h.brand = ?';
        $params[] = (string) $filters['brand'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(h.name_zh LIKE ? OR h.name_en LIKE ? OR h.slug LIKE ? OR h.brand LIKE ?)';
        $q = '%' . (string) $filters['q'] . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    $review = isset($filters['review']) ? (string) $filters['review'] : 'all';
    if ($review === 'pending') {
        $where[] = "TRIM(COALESCE(zh.body_html, '')) <> ''";
        $where[] = "(en.id IS NULL OR en.review_status = 'pending')";
    } elseif ($review === 'ai_draft') {
        $where[] = "en.review_status = 'ai_draft'";
    } elseif ($review === 'human_approved') {
        $where[] = "en.review_status = 'human_approved'";
    }

    $sql = 'SELECT COUNT(*) AS c FROM hh_handhelds h
        LEFT JOIN hh_handheld_content zh ON zh.handheld_id = h.id AND zh.locale = "zh"
        LEFT JOIN hh_handheld_content en ON en.handheld_id = h.id AND en.locale = "en"
        WHERE ' . implode(' AND ', $where);

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) $row['c'] : 0;
}

function hh_translate_job_create(PDO $pdo, $ids)
{
    $json = json_encode(array_values($ids), JSON_UNESCAPED_UNICODE);
    $pdo->prepare(
        'INSERT INTO hh_translate_jobs (status, total_count, handheld_ids, message, started_at)
         VALUES ("running", ?, ?, "任务启动中…", NOW())'
    )->execute(array(count($ids), $json));
    return (int) $pdo->lastInsertId();
}

function hh_translate_job_by_id(PDO $pdo, $jobId)
{
    $st = $pdo->prepare('SELECT * FROM hh_translate_jobs WHERE id = ? LIMIT 1');
    $st->execute(array((int) $jobId));
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hh_translate_job_update(PDO $pdo, $jobId, $fields)
{
    $pdo->prepare(
        'UPDATE hh_translate_jobs SET current_index=?, ok_count=?, fail_count=?, message=?
         WHERE id=? AND status="running"'
    )->execute(array(
        (int) ($fields['current_index'] ?? 0),
        (int) ($fields['ok_count'] ?? 0),
        (int) ($fields['fail_count'] ?? 0),
        isset($fields['message']) ? (string) $fields['message'] : '',
        (int) $jobId,
    ));
}

function hh_translate_job_finish(PDO $pdo, $jobId, $fields)
{
    $pdo->prepare(
        'UPDATE hh_translate_jobs SET status=?, current_index=?, ok_count=?, fail_count=?,
         message=?, finished_at=NOW() WHERE id=?'
    )->execute(array(
        isset($fields['status']) ? (string) $fields['status'] : 'done',
        (int) ($fields['current_index'] ?? 0),
        (int) ($fields['ok_count'] ?? 0),
        (int) ($fields['fail_count'] ?? 0),
        isset($fields['message']) ? (string) $fields['message'] : '',
        (int) $jobId,
    ));
}

function hh_translate_log(PDO $pdo, $jobId, $level, $handheldId, $slug, $message)
{
    $pdo->prepare(
        'INSERT INTO hh_translate_logs (job_id, handheld_id, slug, level, message) VALUES (?,?,?,?,?)'
    )->execute(array(
        (int) $jobId,
        $handheldId ? (int) $handheldId : null,
        (string) $slug,
        (string) $level,
        (string) $message,
    ));
}

function hh_translate_logs_poll(PDO $pdo, $jobId, $afterId = 0, $limit = 120)
{
    $jobId = (int) $jobId;
    $afterId = (int) $afterId;
    $limit = max(1, min(200, (int) $limit));
    if ($afterId > 0) {
        $st = $pdo->prepare('SELECT * FROM hh_translate_logs WHERE job_id = ? AND id > ? ORDER BY id ASC LIMIT ' . $limit);
        $st->execute(array($jobId, $afterId));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $st = $pdo->prepare('SELECT * FROM hh_translate_logs WHERE job_id = ? ORDER BY id DESC LIMIT ' . $limit);
    $st->execute(array($jobId));
    return array_reverse($st->fetchAll(PDO::FETCH_ASSOC));
}

function hh_translate_jobs_recent(PDO $pdo, $limit = 10)
{
    $st = $pdo->prepare('SELECT * FROM hh_translate_jobs ORDER BY id DESC LIMIT ?');
    $st->bindValue(1, (int) $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hh_translate_batch_job(PDO $pdo, $jobId)
{
    @set_time_limit(0);
    $job = hh_translate_job_by_id($pdo, $jobId);
    if (!$job) {
        throw new RuntimeException('翻译任务不存在');
    }

    $ids = json_decode((string) $job['handheld_ids'], true);
    if (!is_array($ids)) {
        $ids = array();
    }
    $total = count($ids);
    $okCount = 0;
    $failCount = 0;
    $current = 0;

    hh_translate_log($pdo, $jobId, 'info', 0, '', '批量翻译开始，共 ' . $total . ' 台');
    hh_translate_job_update($pdo, $jobId, array(
        'current_index' => 0,
        'ok_count' => 0,
        'fail_count' => 0,
        'message' => '共 ' . $total . ' 台待翻译',
    ));

    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        $current++;
        $h = hh_handheld_by_id($pdo, $id);
        $name = $h ? ($h['name_zh'] ?: $h['slug']) : '#' . $id;
        $slug = $h ? (string) $h['slug'] : '';

        hh_translate_log($pdo, $jobId, 'fetch', $id, $slug, '正在翻译 [' . $current . '/' . $total . '] ' . $name);
        hh_translate_job_update($pdo, $jobId, array(
            'current_index' => $current,
            'ok_count' => $okCount,
            'fail_count' => $failCount,
            'message' => '正在翻译 ' . $current . '/' . $total . '：' . $name,
        ));

        try {
            hh_translate_handheld_to_en($pdo, $id, array());
            $okCount++;
            hh_translate_log($pdo, $jobId, 'ok', $id, $slug, '完成：' . $name);
        } catch (Throwable $e) {
            $failCount++;
            hh_translate_log($pdo, $jobId, 'error', $id, $slug, '失败：' . $e->getMessage());
        }

        if ($current < $total) {
            usleep(1200000);
        }
    }

    $message = sprintf('完成：成功 %d，失败 %d', $okCount, $failCount);
    hh_translate_log($pdo, $jobId, 'info', 0, '', $message);
    hh_translate_job_finish($pdo, $jobId, array(
        'status' => 'done',
        'current_index' => $current,
        'ok_count' => $okCount,
        'fail_count' => $failCount,
        'message' => $message,
    ));

    return array(
        'ok_count' => $okCount,
        'fail_count' => $failCount,
        'total_count' => $total,
    );
}

function hh_translate_batch_summary_message($job)
{
    if (!$job) {
        return '';
    }
    $ok = (int) $job['ok_count'];
    $fail = (int) $job['fail_count'];
    $msg = '批量翻译完成：成功 ' . $ok . ' 台';
    if ($fail > 0) {
        $msg .= '，失败 ' . $fail . ' 台';
    }
    $msg .= '。请逐台预览并标记「人工已通过」。';
    return $msg;
}
