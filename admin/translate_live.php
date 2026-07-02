<?php

require_once __DIR__ . '/bootstrap.php';

hh_admin_require_login_json();
session_write_close();

require_once dirname(__DIR__) . '/lib/translate_service.php';

$pdo = hh_pdo();
$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
$afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : 0;

if ($jobId <= 0) {
    hh_json_response(array('ok' => false, 'message' => '缺少 job_id'), 400);
}

$job = hh_translate_job_by_id($pdo, $jobId);
if (!$job) {
    hh_json_response(array('ok' => false, 'message' => '任务不存在'), 404);
}

$logs = hh_translate_logs_poll($pdo, $jobId, $afterId, 120);
$lastId = $afterId;
foreach ($logs as $row) {
    $lastId = max($lastId, (int) $row['id']);
}

$total = max(1, (int) $job['total_count']);
$current = (int) $job['current_index'];
$percent = min(100, (int) round($current / $total * 100));
if ($job['status'] === 'done') {
    $percent = 100;
}

hh_json_response(array(
    'ok' => true,
    'job' => array(
        'id' => (int) $job['id'],
        'status' => (string) $job['status'],
        'total_count' => (int) $job['total_count'],
        'current_index' => $current,
        'ok_count' => (int) $job['ok_count'],
        'fail_count' => (int) $job['fail_count'],
        'message' => (string) ($job['message'] ?? ''),
        'percent' => $percent,
    ),
    'logs' => array_map(function ($row) {
        return array(
            'id' => (int) $row['id'],
            'level' => (string) $row['level'],
            'slug' => (string) $row['slug'],
            'message' => (string) $row['message'],
            'time' => (string) $row['created_at'],
        );
    }, $logs),
    'last_id' => $lastId,
    'running' => ($job['status'] === 'running'),
));
