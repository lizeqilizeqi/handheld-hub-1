<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/brand_repo.php';

hh_bootstrap();
$pdo = hh_pdo();

try {
    $n = hh_brand_sync_catalog($pdo);
    echo "Synced brand logos: {$n} brands with logo files in database.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
