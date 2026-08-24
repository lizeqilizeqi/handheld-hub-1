<?php

require_once __DIR__ . '/blogger.php';

function hh_blogger_batch_job_create(PDO $pdo, array $ids, array $options = array())
{
    $ids = hh_blogger_sort_handheld_ids_for_publish($pdo, $ids);
    $json = json_encode(array_values($ids), JSON_UNESCAPED_UNICODE);
    $optsJson = $options !== array() ? json_encode($options, JSON_UNESCAPED_UNICODE) : null;
    $pdo->prepare(
        'INSERT INTO hh_blogger_batch_jobs (status, total_count, handheld_ids, options_json, message, started_at)
         VALUES ("running", ?, ?, ?, "任务启动中…", NOW())'
    )->execute(array(count($ids), $json, $optsJson));
    return (int) $pdo->lastInsertId();
}

function hh_blogger_batch_job_by_id(PDO $pdo, $jobId)
{
    $st = $pdo->prepare('SELECT * FROM hh_blogger_batch_jobs WHERE id = ? LIMIT 1');
    $st->execute(array((int) $jobId));
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hh_blogger_batch_job_update(PDO $pdo, $jobId, array $fields)
{
    $pdo->prepare(
        'UPDATE hh_blogger_batch_jobs SET current_index=?, ok_count=?, fail_count=?, message=?
         WHERE id=? AND status="running"'
    )->execute(array(
        (int) ($fields['current_index'] ?? 0),
        (int) ($fields['ok_count'] ?? 0),
        (int) ($fields['fail_count'] ?? 0),
        isset($fields['message']) ? (string) $fields['message'] : '',
        (int) $jobId,
    ));
}

function hh_blogger_batch_job_finish(PDO $pdo, $jobId, array $fields)
{
    $pdo->prepare(
        'UPDATE hh_blogger_batch_jobs SET status=?, current_index=?, ok_count=?, fail_count=?,
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

function hh_blogger_batch_log(PDO $pdo, $jobId, $level, $handheldId, $slug, $message)
{
    $pdo->prepare(
        'INSERT INTO hh_blogger_batch_logs (job_id, handheld_id, slug, level, message) VALUES (?,?,?,?,?)'
    )->execute(array(
        (int) $jobId,
        $handheldId ? (int) $handheldId : null,
        (string) $slug,
        (string) $level,
        (string) $message,
    ));
}

function hh_blogger_batch_logs_poll(PDO $pdo, $jobId, $afterId = 0, $limit = 120)
{
    $jobId = (int) $jobId;
    $afterId = (int) $afterId;
    $limit = max(1, min(200, (int) $limit));
    if ($afterId > 0) {
        $st = $pdo->prepare('SELECT * FROM hh_blogger_batch_logs WHERE job_id = ? AND id > ? ORDER BY id ASC LIMIT ' . $limit);
        $st->execute(array($jobId, $afterId));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $st = $pdo->prepare('SELECT * FROM hh_blogger_batch_logs WHERE job_id = ? ORDER BY id DESC LIMIT ' . $limit);
    $st->execute(array($jobId));
    return array_reverse($st->fetchAll(PDO::FETCH_ASSOC));
}

function hh_blogger_batch_jobs_recent(PDO $pdo, $limit = 8)
{
    $st = $pdo->prepare('SELECT * FROM hh_blogger_batch_jobs ORDER BY id DESC LIMIT ?');
    $st->bindValue(1, (int) $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @return array{ok_count:int,fail_count:int,total_count:int}
 */
function hh_blogger_batch_job_execute(PDO $pdo, $jobId)
{
    @set_time_limit(0);
    @ignore_user_abort(true);

    $job = hh_blogger_batch_job_by_id($pdo, $jobId);
    if (!$job) {
        throw new RuntimeException('Blogger 批量任务不存在');
    }

    $ids = json_decode((string) $job['handheld_ids'], true);
    if (!is_array($ids)) {
        $ids = array();
    }
    $ids = hh_blogger_sort_handheld_ids_for_publish($pdo, $ids);

    $options = array('labels' => array('handheld', 'gaming'));
    if (!empty($job['options_json'])) {
        $decoded = json_decode((string) $job['options_json'], true);
        if (is_array($decoded)) {
            $options = array_merge($options, $decoded);
        }
    }

    $total = count($ids);
    $okCount = 0;
    $failCount = 0;
    $current = 0;

    hh_blogger_batch_log($pdo, $jobId, 'info', 0, '', '批量发布开始，共 ' . $total . ' 台（按发布时间从旧到新）');
    hh_blogger_batch_job_update($pdo, $jobId, array(
        'current_index' => 0,
        'ok_count' => 0,
        'fail_count' => 0,
        'message' => '共 ' . $total . ' 台待发布',
    ));

    foreach ($ids as $id) {
        if (!hh_blogger_batch_job_should_continue($pdo, $jobId)) {
            $message = sprintf('已停止：成功 %d，失败 %d（未完成 %d 台）', $okCount, $failCount, max(0, $total - $current));
            hh_blogger_batch_log($pdo, $jobId, 'info', 0, '', $message);
            return array(
                'ok_count' => $okCount,
                'fail_count' => $failCount,
                'total_count' => $total,
            );
        }

        $id = (int) $id;
        if ($id <= 0) {
            continue;
        }
        $current++;
        $h = hh_handheld_by_id($pdo, $id);
        $name = $h ? ($h['name_en'] ?: $h['name_zh'] ?: $h['slug']) : '#' . $id;
        $slug = $h ? (string) $h['slug'] : '';

        hh_blogger_batch_log($pdo, $jobId, 'fetch', $id, $slug, '正在发布 [' . $current . '/' . $total . '] ' . $name);
        hh_blogger_batch_job_update($pdo, $jobId, array(
            'current_index' => $current,
            'ok_count' => $okCount,
            'fail_count' => $failCount,
            'message' => '正在发布 ' . $current . '/' . $total . '：' . $name,
        ));

        try {
            hh_blogger_publish_locales($pdo, $id, hh_blogger_publish_locales_default(), $options);
            $okCount++;
            hh_blogger_batch_log($pdo, $jobId, 'ok', $id, $slug, '完成：' . $name);
        } catch (Throwable $e) {
            $failCount++;
            hh_blogger_batch_log($pdo, $jobId, 'error', $id, $slug, '失败：' . $e->getMessage());
        }

        if ($current < $total) {
            usleep(800000);
        }
    }

    $message = sprintf('完成：成功 %d，失败 %d', $okCount, $failCount);
    hh_blogger_batch_log($pdo, $jobId, 'info', 0, '', $message);
    hh_blogger_batch_job_finish($pdo, $jobId, array(
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
