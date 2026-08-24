#!/usr/bin/env php
<?php
/**
 * Legacy RSS/Atom fetch — only sources with source_kind=rss and is_enabled=1.
 * News from 快科技 uses 抓取同步 (bin/scrape.php --channel=news), not this script.
 */
$root = dirname(__DIR__);
require_once $root . '/lib/bootstrap.php';
require_once $root . '/lib/feed_fetcher.php';

hh_bootstrap();
$pdo = hh_pdo();

if (!hh_feed_tables_exist($pdo)) {
    fwrite(STDERR, "Run sql/migration_009_feeds.sql first.\n");
    exit(2);
}

$force = in_array('--force', $argv, true);
$totals = hh_feed_fetch_due($pdo, $force);

echo 'sources=' . $totals['sources']
    . ' new=' . $totals['new']
    . ' dup=' . $totals['dup']
    . ' skip=' . $totals['skip']
    . ' errors=' . $totals['errors'] . PHP_EOL;

exit($totals['errors'] > 0 ? 1 : 0);
