<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/translate_service.php';

function hh_translate_pid_path($jobId)
{
    return hh_app_logs_dir() . '/translate-job-' . (int) $jobId . '.pid';
}

function hh_translate_log_path($jobId)
{
    return hh_app_logs_dir() . '/translate-job-' . (int) $jobId . '.log';
}

function hh_translate_is_pid_alive($pid)
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

function hh_translate_get_running_job(PDO $pdo)
{
    $st = $pdo->query('SELECT * FROM hh_translate_jobs WHERE status = "running" ORDER BY id DESC LIMIT 1');
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hh_translate_recover_stale_jobs(PDO $pdo)
{
    $st = $pdo->query('SELECT id, started_at FROM hh_translate_jobs WHERE status = "running"');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int) $row['id'];
        $pidFile = hh_translate_pid_path($id);
        $pid = is_file($pidFile) ? (int) trim((string) file_get_contents($pidFile)) : 0;
        if ($pid > 0 && hh_translate_is_pid_alive($pid)) {
            continue;
        }
        $started = !empty($row['started_at']) ? strtotime((string) $row['started_at']) : 0;
        $ageSec = $started > 0 ? max(0, time() - $started) : 9999;
        if ($ageSec >= 45 || ($pid <= 0 && $ageSec >= 20)) {
            hh_translate_job_finish($pdo, $id, array(
                'status' => 'failed',
                'message' => '任务未正常启动或已中断，请重新批量翻译。',
            ));
            hh_translate_log($pdo, $id, 'error', 0, '', '任务中断（后台进程未运行）');
            @unlink($pidFile);
        }
    }
}

function hh_translate_spawn_background($jobId)
{
    hh_ensure_writable_dir(hh_app_logs_dir());
    $log = hh_translate_log_path($jobId);
    $pidFile = hh_translate_pid_path($jobId);
    $php = hh_cli_php_binary();
    $script = HH_ROOT . '/bin/translate_batch.php';

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
        throw new RuntimeException('无法启动后台翻译进程' . ($tail !== '' ? '：' . $tail : ''));
    }
    if (file_put_contents($pidFile, $pid . "\n") === false) {
        throw new RuntimeException('无法写入 PID 文件（storage/app/logs 权限不足）');
    }
    usleep(400000);
    if (!hh_translate_is_pid_alive((int) $pid)) {
        $tail = is_file($log) ? trim((string) file_get_contents($log)) : '';
        @unlink($pidFile);
        throw new RuntimeException('后台翻译进程启动失败' . ($tail !== '' ? '：' . $tail : ''));
    }
    return (int) $pid;
}

function hh_translate_queue_batch(PDO $pdo, $ids)
{
    hh_translate_recover_stale_jobs($pdo);

    $running = hh_translate_get_running_job($pdo);
    if ($running) {
        throw new RuntimeException('已有翻译任务 #' . (int) $running['id'] . ' 正在运行，请等待完成或刷新页面自动清理卡住的任务。');
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
        return $v > 0;
    })));
    if (count($ids) === 0) {
        throw new RuntimeException('没有可翻译的掌机');
    }

    $jobId = hh_translate_job_create($pdo, $ids);
    hh_translate_log($pdo, $jobId, 'info', 0, '', '任务已创建，正在启动后台进程…');
    try {
        hh_translate_spawn_background($jobId);
    } catch (Throwable $e) {
        hh_translate_log($pdo, $jobId, 'error', 0, '', $e->getMessage());
        hh_translate_job_finish($pdo, $jobId, array(
            'status' => 'failed',
            'message' => $e->getMessage(),
        ));
        throw $e;
    }
    hh_translate_log($pdo, $jobId, 'info', 0, '', '后台进程已启动');
    return $jobId;
}

function hh_translate_cancel_running_job(PDO $pdo)
{
    $running = hh_translate_get_running_job($pdo);
    if (!$running) {
        return false;
    }
    $id = (int) $running['id'];
    $pidFile = hh_translate_pid_path($id);
    $pid = is_file($pidFile) ? (int) trim((string) file_get_contents($pidFile)) : 0;
    if ($pid > 0 && function_exists('posix_kill')) {
        @posix_kill($pid, 15);
    }
    hh_translate_job_finish($pdo, $id, array(
        'status' => 'failed',
        'message' => '任务已手动取消',
    ));
    hh_translate_log($pdo, $id, 'error', 0, '', '任务已手动取消');
    @unlink($pidFile);
    return true;
}

function hh_translate_execute_job($jobId)
{
    $pdo = hh_pdo();
    $jobId = (int) $jobId;

    register_shutdown_function(function () use ($jobId) {
        @unlink(hh_translate_pid_path($jobId));
    });

    return hh_translate_batch_job($pdo, $jobId);
}
