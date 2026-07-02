#!/usr/bin/env php
<?php
/**
 * CLI scraper — php bin/scrape.php [--mode=incremental|full] [--slug=rg-rotate] [--job-id=123]
 */
$root = dirname(__DIR__);
require_once $root . '/lib/bootstrap.php';
require_once $root . '/lib/scraper/scraper_service.php';
require_once $root . '/lib/scraper/scraper_runner.php';

hh_bootstrap();

$mode = 'incremental';
$slug = null;
$jobId = 0;
foreach ($argv as $arg) {
    if (strpos($arg, '--mode=') === 0) {
        $mode = substr($arg, 7);
    } elseif (strpos($arg, '--slug=') === 0) {
        $slug = substr($arg, 7);
    } elseif (strpos($arg, '--job-id=') === 0) {
        $jobId = (int) substr($arg, 9);
    }
}

try {
    if ($jobId > 0) {
        $stats = hh_scraper_execute_job($jobId, $mode === 'full' ? 'full' : 'incremental', $slug);
        echo "Job #{$jobId} done\n";
    } else {
        $result = hh_scraper_run_job($mode === 'full' ? 'full' : 'incremental', $slug);
        $jobId = $result['job_id'];
        $stats = $result['stats'];
        echo "Job #{$jobId} done\n";
    }
    echo "found={$stats['items_found']} new={$stats['items_new']} updated={$stats['items_updated']} failed={$stats['items_failed']}\n";
    exit($stats['items_failed'] > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}
