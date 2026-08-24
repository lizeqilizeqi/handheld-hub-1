<?php

require_once __DIR__ . '/feed_repo.php';
require_once __DIR__ . '/config.php';

function hh_feed_http_get($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        throw new InvalidArgumentException('Empty feed URL');
    }
    $ua = (string) hh_config_get('scraper.user_agent', 'HandheldHubBot/1.0 (+feed-fetch)');
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_SSL_VERIFYPEER => true,
        ));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            throw new RuntimeException('HTTP ' . $code . ' fetching ' . $url);
        }
        return (string) $body;
    }
    $ctx = stream_context_create(array(
        'http' => array(
            'timeout' => 45,
            'user_agent' => $ua,
            'follow_location' => 1,
        ),
    ));
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        throw new RuntimeException('Failed to fetch ' . $url);
    }
    return (string) $body;
}

function hh_feed_parse_xml($xmlString)
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($xml === false) {
        throw new RuntimeException('Invalid RSS/Atom XML');
    }
    $items = array();
    $root = strtolower($xml->getName());
    if ($root === 'rss') {
        foreach ($xml->channel->item as $item) {
            $items[] = hh_feed_parse_rss_item($item);
        }
        return $items;
    }
    if ($root === 'feed') {
        $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        foreach ($xml->entry as $entry) {
            $items[] = hh_feed_parse_atom_entry($entry);
        }
        return $items;
    }
    throw new RuntimeException('Unsupported feed root: ' . $root);
}

function hh_feed_parse_rss_item(SimpleXMLElement $item)
{
    $link = '';
    if (isset($item->link)) {
        $link = trim((string) $item->link);
    }
    $guid = isset($item->guid) ? trim((string) $item->guid) : $link;
    $summary = '';
    if (isset($item->description)) {
        $summary = hh_feed_summary_excerpt((string) $item->description);
    } elseif (isset($item->children('content', true)->encoded)) {
        $summary = hh_feed_summary_excerpt((string) $item->children('content', true)->encoded);
    }
    $pub = null;
    if (isset($item->pubDate)) {
        $ts = strtotime((string) $item->pubDate);
        if ($ts) {
            $pub = date('Y-m-d H:i:s', $ts);
        }
    }
    return array(
        'guid' => $guid,
        'link' => $link,
        'title' => trim((string) ($item->title ?? '')),
        'summary' => $summary,
        'author' => trim((string) ($item->author ?? '')),
        'published_at' => $pub,
    );
}

function hh_feed_parse_atom_entry(SimpleXMLElement $entry)
{
    $link = '';
    foreach ($entry->link as $ln) {
        $rel = isset($ln['rel']) ? (string) $ln['rel'] : 'alternate';
        if ($rel === 'alternate' || $rel === '') {
            $href = isset($ln['href']) ? (string) $ln['href'] : '';
            if ($href !== '') {
                $link = $href;
                break;
            }
        }
    }
    $guid = isset($entry->id) ? trim((string) $entry->id) : $link;
    $summary = '';
    if (isset($entry->summary)) {
        $summary = hh_feed_summary_excerpt((string) $entry->summary);
    } elseif (isset($entry->content)) {
        $summary = hh_feed_summary_excerpt((string) $entry->content);
    }
    $pub = null;
    if (isset($entry->published)) {
        $ts = strtotime((string) $entry->published);
        if ($ts) {
            $pub = date('Y-m-d H:i:s', $ts);
        }
    } elseif (isset($entry->updated)) {
        $ts = strtotime((string) $entry->updated);
        if ($ts) {
            $pub = date('Y-m-d H:i:s', $ts);
        }
    }
    $author = '';
    if (isset($entry->author->name)) {
        $author = trim((string) $entry->author->name);
    }
    return array(
        'guid' => $guid,
        'link' => $link,
        'title' => trim((string) ($entry->title ?? '')),
        'summary' => $summary,
        'author' => $author,
        'published_at' => $pub,
    );
}

function hh_feed_fetch_source(PDO $pdo, array $source)
{
    $id = (int) $source['id'];
    $stats = array('new' => 0, 'dup' => 0, 'skip' => 0);
    if (hh_feed_source_kind($pdo, $source) === 'html_scrape') {
        return $stats;
    }
    try {
        $body = hh_feed_http_get($source['feed_url']);
        $items = hh_feed_parse_xml($body);
        foreach ($items as $item) {
            $r = hh_feed_item_upsert($pdo, $id, $item);
            if (isset($stats[$r])) {
                $stats[$r]++;
            }
        }
        hh_feed_source_mark_fetched($pdo, $id, null);
    } catch (Throwable $e) {
        hh_feed_source_mark_fetched($pdo, $id, $e->getMessage());
        throw $e;
    }
    return $stats;
}

function hh_feed_fetch_due(PDO $pdo, $forceAll = false)
{
    $sources = $forceAll ? hh_feed_sources_list($pdo, true) : hh_feed_sources_due($pdo);
    $totals = array('sources' => 0, 'new' => 0, 'dup' => 0, 'skip' => 0, 'errors' => 0);
    foreach ($sources as $src) {
        $totals['sources']++;
        try {
            $s = hh_feed_fetch_source($pdo, $src);
            $totals['new'] += $s['new'];
            $totals['dup'] += $s['dup'];
            $totals['skip'] += $s['skip'];
        } catch (Throwable $e) {
            $totals['errors']++;
        }
    }
    return $totals;
}
