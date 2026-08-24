<?php

require_once __DIR__ . '/bootstrap.php';

hh_admin_require_login_json();

require_once dirname(__DIR__) . '/lib/blogger_batch.php';
require_once dirname(__DIR__) . '/lib/blogger_runner.php';

$pdo = hh_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $action = isset($input['action']) ? (string) $input['action'] : '';
    if ($action === 'cancel') {
        $jobId = isset($input['job_id']) ? (int) $input['job_id'] : 0;
        $token = isset($input['csrf']) ? (string) $input['csrf'] : '';
        $sessCsrf = isset($_SESSION['hh_blogger_csrf']) ? (string) $_SESSION['hh_blogger_csrf'] : '';
        if ($sessCsrf === '' || !hash_equals($sessCsrf, $token)) {
            hh_json_response(array('ok' => false, 'message' => '会话已过期，请刷新页面后重试'), 403);
        }
        if ($jobId <= 0) {
            hh_json_response(array('ok' => false, 'message' => '缺少 job_id'), 400);
        }
        if (!hh_blogger_batch_cancel_job($pdo, $jobId)) {
            hh_json_response(array('ok' => false, 'message' => '任务未在运行或不存在'), 400);
        }
        $job = hh_blogger_batch_job_by_id($pdo, $jobId);
        hh_json_response(array(
            'ok' => true,
            'message' => '已停止 Blogger 批量发布',
            'job' => $job ? array(
                'id' => (int) $job['id'],
                'status' => (string) $job['status'],
                'message' => (string) ($job['message'] ?? ''),
            ) : null,
        ));
    }
    hh_json_response(array('ok' => false, 'message' => '未知操作'), 400);
}

session_write_close();

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
$afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : 0;

if ($jobId <= 0) {
    hh_json_response(array('ok' => false, 'message' => '缺少 job_id'), 400);
}

$job = hh_blogger_batch_job_by_id($pdo, $jobId);
if (!$job) {
    hh_json_response(array('ok' => false, 'message' => '任务不存在'), 404);
}

$logs = hh_blogger_batch_logs_poll($pdo, $jobId, $afterId, 120);
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
