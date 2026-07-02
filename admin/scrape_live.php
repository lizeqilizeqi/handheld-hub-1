<?php

require_once __DIR__ . '/bootstrap.php';

hh_admin_require_login_json();
session_write_close();

require_once dirname(__DIR__) . '/lib/handheld_repo.php';

$pdo = hh_pdo();
$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
$afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : 0;

if ($jobId <= 0) {
    hh_json_response(array('ok' => false, 'message' => '缺少 job_id'), 400);
}

$job = hh_scrape_job_by_id($pdo, $jobId);
if (!$job) {
    hh_json_response(array('ok' => false, 'message' => '任务不存在'), 404);
}

$logs = hh_scrape_logs_poll($pdo, $jobId, $afterId, 120);
$lastId = $afterId;
foreach ($logs as $row) {
    $lastId = max($lastId, (int) $row['id']);
}

hh_json_response(array(
    'ok' => true,
    'job' => array(
        'id' => (int) $job['id'],
        'status' => (string) $job['status'],
        'job_type' => (string) $job['job_type'],
        'items_found' => (int) $job['items_found'],
        'current_page' => (int) $job['current_page'],
        'items_new' => (int) $job['items_new'],
        'items_updated' => (int) $job['items_updated'],
        'items_failed' => (int) $job['items_failed'],
        'message' => (string) ($job['message'] ?? ''),
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
