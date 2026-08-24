<?php

require_once __DIR__ . '/blogger_batch.php';

function hh_blogger_batch_pid_path($jobId)
{
    return hh_app_logs_dir() . '/blogger-batch-' . (int) $jobId . '.pid';
}

function hh_blogger_batch_log_path($jobId)
{
    return hh_app_logs_dir() . '/blogger-batch-' . (int) $jobId . '.log';
}

function hh_blogger_batch_is_pid_alive($pid)
{
    $pid = (int) $pid;
    if ($pid <= 0) {
        return false;
    }
    if (function_exists('posix_kill')) {
        return @posix_kill($pid, 0);
    }
    return is_dir('/proc/' . $pid);
}

function hh_blogger_batch_get_running_job(PDO $pdo)
{
    $st = $pdo->query('SELECT * FROM hh_blogger_batch_jobs WHERE status = "running" ORDER BY id DESC LIMIT 1');
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hh_blogger_batch_recover_stale_jobs(PDO $pdo)
{
    $st = $pdo->query('SELECT id, started_at FROM hh_blogger_batch_jobs WHERE status = "running"');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int) $row['id'];
        $pidFile = hh_blogger_batch_pid_path($id);
        $pid = is_file($pidFile) ? (int) trim((string) file_get_contents($pidFile)) : 0;
        if ($pid > 0 && hh_blogger_batch_is_pid_alive($pid)) {
            continue;
        }
        $started = !empty($row['started_at']) ? strtotime((string) $row['started_at']) : 0;
        $ageSec = $started > 0 ? max(0, time() - $started) : 9999;
        if ($ageSec >= 120 || ($pid <= 0 && $ageSec >= 30)) {
            hh_blogger_batch_job_finish($pdo, $id, array(
                'status' => 'failed',
                'message' => '任务中断（页面超时或后台进程未运行）。请重新批量发布，已成功项会跳过或更新。',
            ));
            hh_blogger_batch_log($pdo, $id, 'error', 0, '', '任务中断');
            @unlink($pidFile);
        }
    }
}

function hh_blogger_batch_spawn_background($jobId)
{
    hh_ensure_writable_dir(hh_app_logs_dir());
    $log = hh_blogger_batch_log_path($jobId);
    $pidFile = hh_blogger_batch_pid_path($jobId);
    $php = hh_cli_php_binary();
    $script = HH_ROOT . '/bin/blogger_batch.php';

    $cmd = implode(' ', array(
        'cd ' . escapeshellarg(HH_ROOT),
        '&&',
        escapeshellarg($php),
        escapeshellarg($script),
        '--job-id=' . (int) $jobId,
        '>> ' . escapeshellarg($log) . ' 2>&1 & echo $!',
    ));

    $pid = trim((string) shell_exec($cmd));
    if ($pid === '' || !ctype_digit($pid)) {
        $tail = is_file($log) ? trim((string) file_get_contents($log)) : '';
        throw new RuntimeException('无法启动后台 Blogger 发布进程' . ($tail !== '' ? '：' . $tail : ''));
    }
    if (file_put_contents($pidFile, $pid . "\n") === false) {
        throw new RuntimeException('无法写入 PID 文件（storage/app/logs 权限不足）');
    }
    usleep(400000);
    if (!hh_blogger_batch_is_pid_alive((int) $pid)) {
        $tail = is_file($log) ? trim((string) file_get_contents($log)) : '';
        @unlink($pidFile);
        throw new RuntimeException('后台 Blogger 发布进程启动失败' . ($tail !== '' ? '：' . $tail : ''));
    }
    return (int) $pid;
}

function hh_blogger_batch_queue(PDO $pdo, array $ids, array $options = array())
{
    hh_blogger_batch_recover_stale_jobs($pdo);

    $running = hh_blogger_batch_get_running_job($pdo);
    if ($running) {
        throw new RuntimeException('已有 Blogger 批量任务 #' . (int) $running['id'] . ' 正在运行，请等待完成或刷新页面。');
    }

    $ids = hh_blogger_sort_handheld_ids_for_publish($pdo, $ids);
    if ($ids === array()) {
        throw new RuntimeException('没有可发布的掌机');
    }

    $jobId = hh_blogger_batch_job_create($pdo, $ids, $options);
    hh_blogger_batch_log($pdo, $jobId, 'info', 0, '', '任务已创建，正在启动后台进程…');
    try {
        hh_blogger_batch_spawn_background($jobId);
    } catch (Throwable $e) {
        hh_blogger_batch_log($pdo, $jobId, 'error', 0, '', $e->getMessage());
        hh_blogger_batch_job_finish($pdo, $jobId, array(
            'status' => 'failed',
            'message' => $e->getMessage(),
        ));
        throw $e;
    }
    hh_blogger_batch_log($pdo, $jobId, 'info', 0, '', '后台进程已启动');
    return $jobId;
}

function hh_blogger_batch_job_should_continue(PDO $pdo, $jobId)
{
    $job = hh_blogger_batch_job_by_id($pdo, (int) $jobId);
    return $job && ($job['status'] ?? '') === 'running';
}

function hh_blogger_batch_cancel_job(PDO $pdo, $jobId = 0)
{
    $jobId = (int) $jobId;
    if ($jobId > 0) {
        $job = hh_blogger_batch_job_by_id($pdo, $jobId);
    } else {
        $job = hh_blogger_batch_get_running_job($pdo);
        $jobId = $job ? (int) $job['id'] : 0;
    }
    if (!$job || ($job['status'] ?? '') !== 'running') {
        return false;
    }

    $pidFile = hh_blogger_batch_pid_path($jobId);
    $pid = is_file($pidFile) ? (int) trim((string) file_get_contents($pidFile)) : 0;
    if ($pid > 0 && function_exists('posix_kill')) {
        @posix_kill($pid, 15);
        usleep(300000);
        if (hh_blogger_batch_is_pid_alive($pid)) {
            @posix_kill($pid, 9);
        }
    }

    hh_blogger_batch_job_finish($pdo, $jobId, array(
        'status' => 'failed',
        'current_index' => (int) ($job['current_index'] ?? 0),
        'ok_count' => (int) ($job['ok_count'] ?? 0),
        'fail_count' => (int) ($job['fail_count'] ?? 0),
        'message' => '任务已手动停止',
    ));
    hh_blogger_batch_log($pdo, $jobId, 'error', 0, '', '任务已手动停止');
    @unlink($pidFile);
    return true;
}

function hh_blogger_batch_cancel_running_job(PDO $pdo)
{
    return hh_blogger_batch_cancel_job($pdo, 0);
}

function hh_blogger_batch_execute_job($jobId)
{
    $pdo = hh_pdo();
    $jobId = (int) $jobId;

    register_shutdown_function(function () use ($jobId) {
        @unlink(hh_blogger_batch_pid_path($jobId));
    });

    return hh_blogger_batch_job_execute($pdo, $jobId);
}
