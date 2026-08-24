<?php

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/feed_repo.php';

const HH_MYDRIVERS_LIST_AC = 55;
const HH_MYDRIVERS_LIST_URL = 'https://news.mydrivers.com/zhangjiyouxi.html';
const HH_MYDRIVERS_API_BASE = 'https://blog.mydrivers.com/news/getdatelist_search.aspx';

function hh_mydrivers_http_get($url)
{
    $ua = (string) hh_config_get('scraper.user_agent', 'HandheldHubBot/1.0 (+news-scrape)');
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
    ));
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) {
        throw new RuntimeException('HTTP ' . $code . ' ' . $url);
    }
    return (string) $body;
}

function hh_mydrivers_sanitize_copy($text)
{
    return hh_feed_sanitize_zh_brand($text);
}

function hh_mydrivers_normalize_datetime($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$#', $raw, $m)) {
        $h = isset($m[4]) ? (int) $m[4] : 0;
        $i = isset($m[5]) ? (int) $m[5] : 0;
        $s = isset($m[6]) ? (int) $m[6] : 0;
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) $m[3], $h, $i, $s);
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function hh_mydrivers_abs_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }
    if (strpos($url, 'http') !== 0) {
        return 'https://news.mydrivers.com' . (strpos($url, '/') === 0 ? '' : '/') . ltrim($url, '/');
    }
    return $url;
}

function hh_mydrivers_slug_from_link($link, PDO $pdo)
{
    if (preg_match('#/(\d+)\.htm(?:\?|$)#i', (string) $link, $m)) {
        $base = 'n-' . $m[1];
        $n = 0;
        while (true) {
            $try = $n === 0 ? $base : $base . '-' . $n;
            $st = $pdo->prepare('SELECT id FROM hh_feed_items WHERE slug = ? LIMIT 1');
            $st->execute(array($try));
            if (!$st->fetchColumn()) {
                return $try;
            }
            $n++;
        }
    }
    return hh_feed_slugify('item', $pdo);
}

function hh_mydrivers_item_ts(array $item)
{
    if (empty($item['published_at'])) {
        return 0;
    }
    $ts = strtotime((string) $item['published_at']);
    return $ts ? $ts : 0;
}

/**
 * @return array<int, array{link:string,title:string,summary:string,image:string,published_at:?string}>
 */
function hh_mydrivers_parse_list_html($html)
{
    $html = (string) $html;
    $items = array();
    $seen = array();

    if (!preg_match_all(
        '#<li>\s*<div class="news_left[^"]*">[\s\S]*?data-original="([^"]+)"[\s\S]*?<div class="news_right">\s*<h3><a href="([^"]+)"[^>]*>([^<]+)</a></h3>\s*<p><a href="[^"]*">([\s\S]*?)</a></p>[\s\S]*?<span class="time[^"]*">\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2})\s*</span>#u',
        $html,
        $m,
        PREG_SET_ORDER
    )) {
        return array();
    }

    foreach ($m as $row) {
        $link = hh_mydrivers_abs_url(html_entity_decode(trim($row[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($link === '' || isset($seen[$link])) {
            continue;
        }
        $seen[$link] = true;
        $title = hh_mydrivers_sanitize_copy(html_entity_decode(strip_tags($row[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($title === '') {
            continue;
        }
        $summary = hh_mydrivers_sanitize_copy(html_entity_decode(strip_tags($row[4]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $items[] = array(
            'link' => $link,
            'title' => $title,
            'summary' => hh_feed_summary_excerpt($summary, 400),
            'image' => hh_mydrivers_abs_url(html_entity_decode(trim($row[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'published_at' => hh_mydrivers_normalize_datetime($row[5]),
        );
    }

    return $items;
}

function hh_mydrivers_api_fetch_page($page, $minid)
{
    $url = HH_MYDRIVERS_API_BASE . '?ac=' . HH_MYDRIVERS_LIST_AC
        . '&timeks=&timeend=&page=' . (int) $page . '&minid=' . (int) $minid . '&callback=NewsList';
    $raw = hh_mydrivers_http_get($url);
    if (!preg_match('/NewsList\s*\(\s*(\{.*\})\s*\)\s*;?\s*$/s', $raw, $m)) {
        throw new RuntimeException('快科技列表 API 解析失败 page=' . (int) $page);
    }
    $data = json_decode($m[1], true);
    if (!is_array($data) || empty($data['data'][0])) {
        return array('html' => '', 'minid' => -1, 'datalist' => -1);
    }
    $block = $data['data'][0];
    return array(
        'html' => (string) ($block['html'] ?? ''),
        'minid' => (int) ($block['minid'] ?? -1),
        'datalist' => (string) ($block['datalist'] ?? ''),
    );
}

/**
 * Scroll all list pages until since date or API end.
 *
 * @return array<int, array{link:string,title:string,summary:string,image:string,published_at:?string}>
 */
function hh_mydrivers_collect_list_items($sinceDate)
{
    $sinceTs = strtotime($sinceDate . ' 00:00:00');
    if (!$sinceTs) {
        $sinceTs = strtotime(date('Y') . '-01-01 00:00:00');
    }

    $merged = array();
    $seen = array();

    $addItems = function (array $batch) use (&$merged, &$seen, $sinceTs) {
        $oldest = PHP_INT_MAX;
        foreach ($batch as $it) {
            $ts = hh_mydrivers_item_ts($it);
            if ($ts > 0 && $ts < $oldest) {
                $oldest = $ts;
            }
            if ($ts > 0 && $ts < $sinceTs) {
                continue;
            }
            if (isset($seen[$it['link']])) {
                continue;
            }
            $seen[$it['link']] = true;
            $merged[] = $it;
        }
        return $oldest;
    };

    $html = hh_mydrivers_http_get(HH_MYDRIVERS_LIST_URL);
    $oldest = $addItems(hh_mydrivers_parse_list_html($html));
    if ($oldest < $sinceTs) {
        return hh_mydrivers_sort_list_items($merged);
    }

    $page = 2;
    $minid = 0;
    $maxPages = 150;
    while ($page <= $maxPages) {
        $api = hh_mydrivers_api_fetch_page($page, $minid);
        if ($api['datalist'] === '-1' || $api['datalist'] === '-2' || trim($api['html']) === '') {
            break;
        }
        if ($api['minid'] > 0) {
            $minid = (int) $api['minid'];
        }
        $batch = hh_mydrivers_parse_list_html($api['html']);
        if ($batch === array()) {
            break;
        }
        $oldest = $addItems($batch);
        if ($oldest < $sinceTs) {
            break;
        }
        $page++;
        usleep(400000);
    }

    return hh_mydrivers_sort_list_items($merged);
}

function hh_mydrivers_sort_list_items(array $items)
{
    usort($items, function ($a, $b) {
        return hh_mydrivers_item_ts($b) <=> hh_mydrivers_item_ts($a);
    });
    return $items;
}

function hh_mydrivers_sanitize_body_html($html)
{
    $html = (string) $html;
    $html = preg_replace('#<div[^>]*id="AiSummaryLink"[\s\S]*?</div>#iu', '', $html);
    $html = preg_replace('#<div[^>]*class="[^"]*zhuanzai[^"]*"[\s\S]*$#iu', '', $html);
    $html = preg_replace('#【本文结束】[\s\S]*$#u', '', $html);

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!@$dom->loadHTML('<?xml encoding="utf-8"><div id="hh-root">' . $html . '</div>')) {
        return hh_mydrivers_sanitize_copy(strip_tags($html));
    }
    $xpath = new DOMXPath($dom);
    $root = $xpath->query('//*[@id="hh-root"]')->item(0);
    if (!$root) {
        return '';
    }

    $allowed = array('p' => true, 'br' => true, 'strong' => true, 'b' => true, 'img' => true, 'a' => true, 'span' => true);

    $walker = function (DOMNode $node) use (&$walker, $dom, $allowed) {
        if ($node->nodeType === XML_TEXT_NODE) {
            $node->nodeValue = hh_mydrivers_sanitize_copy($node->nodeValue);
            return;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }
        /** @var DOMElement $el */
        $el = $node;
        $tag = strtolower($el->tagName);
        if (!isset($allowed[$tag])) {
            while ($el->firstChild) {
                $el->parentNode->insertBefore($el->firstChild, $el);
            }
            $el->parentNode->removeChild($el);
            return;
        }
        if ($tag === 'img') {
            $src = hh_mydrivers_abs_url($el->getAttribute('src'));
            if ($src === '') {
                $src = hh_mydrivers_abs_url($el->getAttribute('data-original'));
            }
            while ($el->attributes->length) {
                $el->removeAttribute($el->attributes->item(0)->name);
            }
            if ($src !== '') {
                $el->setAttribute('src', $src);
                $el->setAttribute('alt', '');
                $el->setAttribute('loading', 'lazy');
            } else {
                $el->parentNode->removeChild($el);
            }
            return;
        }
        if ($tag === 'a') {
            $href = hh_mydrivers_abs_url($el->getAttribute('href'));
            while ($el->attributes->length) {
                $el->removeAttribute($el->attributes->item(0)->name);
            }
            if ($href !== '') {
                $el->setAttribute('href', $href);
                $el->setAttribute('rel', 'noopener noreferrer');
                $el->setAttribute('target', '_blank');
            }
        }
        for ($i = $el->childNodes->length - 1; $i >= 0; $i--) {
            $walker($el->childNodes->item($i));
        }
    };

    for ($i = $root->childNodes->length - 1; $i >= 0; $i--) {
        $walker($root->childNodes->item($i));
    }

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return hh_feed_sanitize_zh_brand_html(trim($out));
}

/**
 * @return array{title:string,body_html:string,published_at:?string,lead_image:string}
 */
function hh_mydrivers_fetch_article($url)
{
    $html = hh_mydrivers_http_get(hh_mydrivers_abs_url($url));
    $title = '';
    if (preg_match('#<div class="news_bt"[^>]*id="thread_subject"[^>]*>([\s\S]*?)</div>#u', $html, $m)) {
        $title = hh_mydrivers_sanitize_copy(strip_tags($m[1]));
    }
    $pub = null;
    if (preg_match('#<div class="news_bt1_left">\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})#u', $html, $m)) {
        $pub = hh_mydrivers_normalize_datetime($m[1]);
    }
    $bodyRaw = '';
    if (preg_match('#<div class="news_info">\s*([\s\S]*?)\s*</div>\s*</div>\s*<div style="margin: auto;width: 800px#u', $html, $m)) {
        $bodyRaw = $m[1];
    } elseif (preg_match('#<div class="news_info">\s*([\s\S]*?)\s*<div style="overflow: hidden;font-size:14px;padding-top:30px;border-bottom:1px solid#u', $html, $m)) {
        $bodyRaw = $m[1];
    }
    $bodyHtml = hh_mydrivers_sanitize_body_html($bodyRaw);
    $leadImage = '';
    if (preg_match('#<img[^>]+src="([^"]+)"#i', $bodyHtml, $im)) {
        $leadImage = hh_mydrivers_abs_url($im[1]);
    }
    if ($title === '' && preg_match('#<meta property="og:title" content="([^"]+)"#u', $html, $m)) {
        $title = hh_mydrivers_sanitize_copy(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    return array(
        'title' => $title,
        'body_html' => $bodyHtml,
        'published_at' => $pub,
        'lead_image' => $leadImage,
    );
}

function hh_mydrivers_source_id(PDO $pdo)
{
    $st = $pdo->prepare('SELECT id FROM hh_feed_sources WHERE feed_url LIKE ? LIMIT 1');
    $st->execute(array('%zhangjiyouxi.html%'));
    $id = (int) $st->fetchColumn();
    if ($id > 0) {
        $pdo->prepare('UPDATE hh_feed_sources SET source_kind = "html_scrape", is_enabled = 0 WHERE id = ?')->execute(array($id));
        return $id;
    }
    return (int) hh_feed_source_save($pdo, array(
        'site_code' => 'news',
        'name' => '快科技-掌机游戏列表',
        'feed_url' => HH_MYDRIVERS_LIST_URL,
        'homepage_url' => 'https://news.mydrivers.com',
        'source_kind' => 'html_scrape',
        'is_enabled' => 0,
        'fetch_interval_minutes' => 10080,
    ));
}

function hh_mydrivers_default_since_date()
{
    return date('Y') . '-01-01';
}

/** @return 'new'|'dup'|'skip' */
function hh_feed_item_upsert_scraped(PDO $pdo, $sourceId, array $item)
{
    $link = trim((string) ($item['link'] ?? ''));
    if ($link === '') {
        return 'skip';
    }
    $hash = hash('sha256', (int) $sourceId . '|' . $link);
    $st = $pdo->prepare('SELECT id FROM hh_feed_items WHERE guid_hash = ? LIMIT 1');
    $st->execute(array($hash));
    if ($st->fetchColumn()) {
        return 'dup';
    }

    $title = hh_mydrivers_sanitize_copy(trim((string) ($item['title'] ?? '')));
    $summary = hh_mydrivers_sanitize_copy(trim((string) ($item['summary'] ?? '')));
    $bodyZh = hh_feed_sanitize_zh_brand_html((string) ($item['body_zh'] ?? ''));
    if ($summary === '' && $bodyZh !== '') {
        $summary = hh_feed_summary_excerpt(hh_feed_strip_html($bodyZh), 400);
    }
    $slug = hh_mydrivers_slug_from_link($link, $pdo);
    $pub = !empty($item['published_at']) ? (string) $item['published_at'] : null;
    $image = hh_mydrivers_abs_url((string) ($item['image'] ?? ''));

    $st = $pdo->prepare('INSERT INTO hh_feed_items (source_id, guid_hash, slug, title, title_zh, title_en, summary, summary_zh, summary_en, body_zh, body_en, link, image_url, author, published_at, status, translate_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"pending","pending")');
    $st->execute(array(
        (int) $sourceId,
        $hash,
        $slug,
        $title,
        $title,
        '',
        $summary,
        $summary,
        '',
        $bodyZh,
        '',
        $link,
        $image,
        '',
        $pub,
    ));
    return 'new';
}
