<?php

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/handheld_repo.php';
require_once __DIR__ . '/scraper_service.php';

function hh_scraper_log_path($jobId)
{
    return hh_app_logs_dir() . '/scrape-job-' . (int) $jobId . '.log';
}

function hh_scraper_pid_path($jobId)
{
    return hh_app_logs_dir() . '/scrape-job-' . (int) $jobId . '.pid';
}

function hh_scraper_is_pid_alive($pid)
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

function hh_scraper_get_running_job(PDO $pdo)
{
    $st = $pdo->query('SELECT * FROM hh_scrape_jobs WHERE status = "running" ORDER BY id DESC LIMIT 1');
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hh_scraper_recover_stale_jobs(PDO $pdo)
{
    $st = $pdo->query('SELECT id, started_at FROM hh_scrape_jobs WHERE status = "running"');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int) $row['id'];
        $pidFile = hh_scraper_pid_path($id);
        $pid = is_file($pidFile) ? (int) trim((string) file_get_contents($pidFile)) : 0;
        if ($pid > 0 && hh_scraper_is_pid_alive($pid)) {
            continue;
        }
        $started = !empty($row['started_at']) ? strtotime((string) $row['started_at']) : 0;
        $ageSec = $started > 0 ? max(0, time() - $started) : 9999;
        if ($ageSec >= 45 || ($pid <= 0 && $ageSec >= 20)) {
            hh_scrape_job_finish($pdo, $id, array(
                'status' => 'failed',
                'message' => '任务中断（可能因页面同步抓取超时）。请重新启动后台抓取。',
            ));
            @unlink($pidFile);
        }
    }
}

function hh_scraper_spawn_background($jobId, $mode, $singleSlug = null)
{
    hh_ensure_writable_dir(hh_app_logs_dir());
    $log = hh_scraper_log_path($jobId);
    $pidFile = hh_scraper_pid_path($jobId);
    $php = hh_cli_php_binary();
    $script = HH_ROOT . '/bin/scrape.php';

    $parts = array(
        'cd ' . escapeshellarg(HH_ROOT),
        '&&',
        escapeshellarg($php),
        escapeshellarg($script),
        '--job-id=' . (int) $jobId,
        '--mode=' . escapeshellarg($mode === 'full' ? 'full' : 'incremental'),
    );
    if ($singleSlug !== null && $singleSlug !== '') {
        $parts[] = '--slug=' . escapeshellarg($singleSlug);
    }
    $parts[] = '>> ' . escapeshellarg($log) . ' 2>&1 & echo $!';
    $cmd = implode(' ', $parts);

    $pid = trim((string) shell_exec($cmd));
    if ($pid === '' || !ctype_digit($pid)) {
        throw new RuntimeException('无法启动后台抓取进程，请改用命令行：docker compose exec web php bin/scrape.php');
    }
    file_put_contents($pidFile, $pid);
    return (int) $pid;
}

function hh_scraper_queue_job($mode, $singleSlug = null)
{
    $pdo = hh_pdo();
    hh_scraper_recover_stale_jobs($pdo);

    $running = hh_scraper_get_running_job($pdo);
    if ($running) {
        throw new RuntimeException('已有抓取任务 #' . (int) $running['id'] . ' 正在运行，请等待完成后再启动。');
    }

    $jobType = ($singleSlug !== null && $singleSlug !== '') ? 'single' : $mode;
    $jobId = hh_scrape_job_create($pdo, $jobType);
    hh_scraper_spawn_background($jobId, $mode, $singleSlug);
    return $jobId;
}

function hh_scraper_execute_job($jobId, $mode = 'incremental', $singleSlug = null)
{
    $pdo = hh_pdo();
    $jobId = (int) $jobId;
    if ($jobId <= 0) {
        throw new RuntimeException('Invalid job id');
    }

    register_shutdown_function(function () use ($pdo, $jobId) {
        $err = error_get_last();
        if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
            hh_scrape_job_finish($pdo, $jobId, array(
                'status' => 'failed',
                'message' => '进程异常退出',
            ));
        }
        @unlink(hh_scraper_pid_path($jobId));
    });

    $svc = new HhScraperService($pdo, $jobId);
    return $svc->run($mode, $singleSlug);
}

function hh_scraper_run_job($mode = 'incremental', $singleSlug = null)
{
    $pdo = hh_pdo();
    $jobId = hh_scrape_job_create($pdo, $singleSlug ? 'single' : $mode);
    $stats = hh_scraper_execute_job($jobId, $mode, $singleSlug);
    return array('job_id' => $jobId, 'stats' => $stats);
}
