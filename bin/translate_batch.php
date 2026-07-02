#!/usr/bin/env php
<?php
/**
 * CLI batch translate — php bin/translate_batch.php --job-id=1
 */
$root = dirname(__DIR__);
require_once $root . '/lib/bootstrap.php';
require_once $root . '/lib/translate_service.php';
require_once $root . '/lib/translate_runner.php';

hh_bootstrap();

$jobId = 0;
foreach ($argv as $arg) {
    if (strpos($arg, '--job-id=') === 0) {
        $jobId = (int) substr($arg, 9);
    }
}

if ($jobId <= 0) {
    fwrite(STDERR, "Usage: php bin/translate_batch.php --job-id=N\n");
    exit(2);
}

try {
    $result = hh_translate_execute_job($jobId);
    echo "Job #{$jobId} done ok={$result['ok_count']} fail={$result['fail_count']}\n";
    exit($result['fail_count'] > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}
