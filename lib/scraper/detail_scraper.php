<?php

require_once dirname(__DIR__) . '/http_client.php';

function hh_scraper_base_url()
{
    return rtrim((string) hh_config_get('scraper.base_url', 'https://zhangjiquan.com'), '/');
}

function hh_scraper_normalize_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }
    if (strpos($url, '/') === 0) {
        return hh_scraper_base_url() . $url;
    }
    return $url;
}

function hh_scraper_parse_list_page($html)
{
    $items = array();
    $seen = array();

    // Primary: card blocks with data-url + data-src (list cover image)
    if (preg_match_all(
        '#<div class="card" data-url="([a-zA-Z0-9_-]+)"[^>]*data-src="([^"]+)"#',
        $html,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $match) {
            $slug = (string) $match[1];
            if (isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $items[] = array(
                'slug' => $slug,
                'url' => hh_scraper_base_url() . '/handheld/' . $slug,
                'cover_url' => hh_scraper_normalize_url($match[2]),
            );
        }
    }

    // Enrich with brand logo from card body
    if (preg_match_all(
        '#<div class="card" data-url="([a-zA-Z0-9_-]+)"[^>]*>[\s\S]*?<img class="brand-logo" src="([^"]+)"#',
        $html,
        $logoMatches,
        PREG_SET_ORDER
    )) {
        $logoBySlug = array();
        foreach ($logoMatches as $lm) {
            $logoBySlug[(string) $lm[1]] = hh_scraper_normalize_url($lm[2]);
        }
        foreach ($items as $i => $item) {
            if (isset($logoBySlug[$item['slug']])) {
                $items[$i]['brand_logo_url'] = $logoBySlug[$item['slug']];
            }
        }
    }

    // Fallback: handheld links without card metadata
    if (preg_match_all('#href="(/handheld/([a-zA-Z0-9_-]+))"#', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $slug = (string) $match[2];
            if (isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $cover = '';
            $pattern = '#href="' . preg_quote($match[1], '#') . '"[^>]*>[\s\S]{0,1200}?data-original="([^"]+)"#';
            if (preg_match($pattern, $html, $cm)) {
                $cover = hh_scraper_normalize_url($cm[1]);
            }
            $items[] = array(
                'slug' => $slug,
                'url' => hh_scraper_base_url() . $match[1],
                'cover_url' => $cover,
            );
        }
    }

    return $items;
}

/** @return array<string,string> slug => brand logo URL */
function hh_scraper_fetch_all_list_brand_logos()
{
    $first = hh_scraper_fetch_list(1);
    if (!$first['ok']) {
        throw new RuntimeException('List page 1 failed: ' . ($first['error'] ?? 'unknown'));
    }
    $maxPage = hh_scraper_detect_max_page($first['body']);
    $logos = array();

    for ($p = 1; $p <= $maxPage; $p++) {
        $html = ($p === 1) ? $first['body'] : null;
        if ($html === null) {
            $res = hh_scraper_fetch_list($p);
            if (!$res['ok']) {
                continue;
            }
            $html = $res['body'];
        }
        foreach (hh_scraper_parse_list_page($html) as $item) {
            if (!empty($item['brand_logo_url'])) {
                $logos[(string) $item['slug']] = (string) $item['brand_logo_url'];
            }
        }
        if ($p > 1) {
            usleep(400000);
        }
    }
    return $logos;
}

/** @return array<int,string> unique normalized brand logo URLs */
function hh_scraper_extract_brand_logo_urls($html)
{
    $urls = array();
    if (preg_match_all(
        '#(?:https?:)?//upload\.zhangjiquan\.com/images/brand/[a-zA-Z0-9_-]+\.(?:webp|png|jpe?g|gif)#i',
        (string) $html,
        $m
    )) {
        foreach ($m[0] as $url) {
            $urls[hh_scraper_normalize_url($url)] = true;
        }
    }
    return array_keys($urls);
}

function hh_scraper_detect_max_page($html)
{
    if (preg_match_all('#/handhelds\?page=(\d+)#', $html, $m)) {
        $max = 1;
        foreach ($m[1] as $p) {
            $max = max($max, (int) $p);
        }
        return $max;
    }
    return 1;
}

function hh_scraper_fetch_list($page = 1)
{
    $url = hh_scraper_base_url() . '/handhelds?page=' . max(1, (int) $page);
    return hh_http_get($url);
}

function hh_scraper_fetch_all_slugs()
{
    $first = hh_scraper_fetch_list(1);
    if (!$first['ok']) {
        throw new RuntimeException('List page 1 failed: ' . ($first['error'] ?? 'unknown'));
    }
    $maxPage = hh_scraper_detect_max_page($first['body']);
    $seen = array();
    foreach (hh_scraper_parse_list_page($first['body']) as $it) {
        $seen[$it['slug']] = $it;
    }

    for ($p = 2; $p <= $maxPage; $p++) {
        $res = hh_scraper_fetch_list($p);
        if (!$res['ok']) {
            continue;
        }
        foreach (hh_scraper_parse_list_page($res['body']) as $it) {
            if (!isset($seen[$it['slug']])) {
                $seen[$it['slug']] = $it;
            }
        }
    }
    return array_values($seen);
}

function hh_scraper_lookup_list_cover($slug)
{
    $first = hh_scraper_fetch_list(1);
    if (!$first['ok']) {
        return '';
    }
    $maxPage = hh_scraper_detect_max_page($first['body']);
    for ($p = 1; $p <= $maxPage; $p++) {
        $html = ($p === 1) ? $first['body'] : null;
        if ($html === null) {
            $res = hh_scraper_fetch_list($p);
            if (!$res['ok']) {
                continue;
            }
            $html = $res['body'];
        }
        foreach (hh_scraper_parse_list_page($html) as $it) {
            if ($it['slug'] === $slug && !empty($it['cover_url'])) {
                return $it['cover_url'];
            }
        }
    }
    return '';
}

function hh_dom_load_html($html)
{
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    return $doc;
}

/**
 * Detail page main product image only (exclude sidebar related handhelds).
 */
function hh_scraper_parse_detail_main_image($html, $slug)
{
    // device-thumb lazyload: data-original is the real image
    if (preg_match(
        '#<img[^>]+class="[^"]*device-thumb[^"]*"[^>]+data-original="([^"]+)"#i',
        $html,
        $m
    )) {
        return hh_scraper_normalize_url($m[1]);
    }
    if (preg_match(
        '#<img[^>]+data-original="([^"]+)"[^>]+class="[^"]*device-thumb[^"]*"#i',
        $html,
        $m
    )) {
        return hh_scraper_normalize_url($m[1]);
    }

    // Fallback: device image path matching slug
    $slugEsc = preg_quote($slug, '#');
    if (preg_match(
        '#data-original="(https://upload\.zhangjiquan\.com/images/device/' . $slugEsc . '\.[^"]+)"#i',
        $html,
        $m
    )) {
        return hh_scraper_normalize_url($m[1]);
    }

    return '';
}

function hh_scraper_parse_detail($html, $slug, $url)
{
    $doc = hh_dom_load_html($html);
    $xpath = new DOMXPath($doc);

    $name = '';
    $h1 = $xpath->query('//h1');
    if ($h1 && $h1->length > 0) {
        $name = trim($h1->item(0)->textContent);
    }

    $specs = array();
    $rows = $xpath->query('//table//tr');
    if ($rows) {
        foreach ($rows as $tr) {
            $cells = $tr->getElementsByTagName('td');
            if ($cells->length >= 2) {
                $k = trim(preg_replace('/\s+/u', ' ', $cells->item(0)->textContent));
                $v = trim(preg_replace('/\s+/u', ' ', $cells->item(1)->textContent));
                if ($k !== '' && $v !== '' && $k !== $v) {
                    $specs[$k] = $v;
                }
            }
            $ths = $tr->getElementsByTagName('th');
            if ($ths->length >= 1 && $cells->length >= 1) {
                $k = trim(preg_replace('/\s+/u', ' ', $ths->item(0)->textContent));
                $v = trim(preg_replace('/\s+/u', ' ', $cells->item(0)->textContent));
                if ($k !== '' && $v !== '') {
                    $specs[$k] = $v;
                }
            }
        }
    }

    $brand = isset($specs['品牌']) ? $specs['品牌'] : (isset($specs['Brand']) ? $specs['Brand'] : '');
    $releaseText = isset($specs['发布时间']) ? $specs['发布时间'] : '';
    $screenSize = isset($specs['屏幕尺寸']) ? $specs['屏幕尺寸'] : '';
    $screenRatio = isset($specs['屏幕比例']) ? $specs['屏幕比例'] : '';

    if ($name === '' && isset($specs['名称'])) {
        $name = $specs['名称'];
    }

    $detailImageUrl = hh_scraper_parse_detail_main_image($html, $slug);

    $bodyHtml = '';
    $article = $xpath->query('//article');
    if ($article && $article->length > 0) {
        $bodyHtml = hh_dom_inner_html($doc, $article->item(0));
    } else {
        $content = $xpath->query('//*[contains(@class,"content") or contains(@class,"detail")]');
        if ($content && $content->length > 0) {
            $bodyHtml = hh_dom_inner_html($doc, $content->item(0));
        }
    }

    return array(
        'slug' => $slug,
        'source_url' => $url,
        'name_zh' => $name,
        'brand' => $brand,
        'release_text' => $releaseText,
        'screen_size' => $screenSize,
        'screen_ratio' => $screenRatio,
        'specs' => $specs,
        'body_html_zh' => $bodyHtml,
        'detail_image_url' => $detailImageUrl,
    );
}

function hh_dom_inner_html(DOMDocument $doc, DOMNode $node)
{
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $doc->saveHTML($child);
    }
    return $html;
}

function hh_scraper_fetch_detail($slug)
{
    $url = hh_scraper_base_url() . '/handheld/' . rawurlencode($slug);
    $res = hh_http_get($url);
    if (!$res['ok']) {
        throw new RuntimeException('Detail fetch failed for ' . $slug . ': ' . ($res['error'] ?? 'HTTP error'));
    }
    return hh_scraper_parse_detail($res['body'], $slug, $url);
}

function hh_scraper_guess_ext($url)
{
    if (preg_match('#\.(png|webp|gif|jpeg|jpg)#i', $url, $m)) {
        $ext = strtolower($m[1]);
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }
    return 'webp';
}

/**
 * Download list cover + detail main image (max 2 files).
 *
 * @return array<int, array{path:string,source_url:string,is_cover:bool,alt_text_zh:string,alt_text_en:string}>
 */
function hh_scraper_download_images($slug, $coverUrl, $detailUrl)
{
    $dir = HH_STORAGE_FS . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
    hh_ensure_dir($dir);
    $safeSlug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
    $saved = array();
    $delay = (int) hh_config_get('scraper.delay_ms', 1200);

    $coverUrl = hh_scraper_normalize_url($coverUrl);
    $detailUrl = hh_scraper_normalize_url($detailUrl);

    if ($coverUrl !== '') {
        $ext = hh_scraper_guess_ext($coverUrl);
        $dest = $dir . '/cover.' . $ext;
        $dl = hh_http_download($coverUrl, $dest);
        if ($dl['ok']) {
            $saved[] = array(
                'path' => $safeSlug . '/cover.' . $ext,
                'source_url' => $coverUrl,
                'is_cover' => true,
                'alt_text_zh' => $slug . ' 封面',
                'alt_text_en' => $slug . ' cover',
            );
        }
        usleep($delay * 1000);
    }

    if ($detailUrl !== '' && $detailUrl !== $coverUrl) {
        $ext = hh_scraper_guess_ext($detailUrl);
        $dest = $dir . '/device.' . $ext;
        $dl = hh_http_download($detailUrl, $dest);
        if ($dl['ok']) {
            $saved[] = array(
                'path' => $safeSlug . '/device.' . $ext,
                'source_url' => $detailUrl,
                'is_cover' => false,
                'alt_text_zh' => $slug . ' 产品图',
                'alt_text_en' => $slug . ' product',
            );
        }
    } elseif ($detailUrl !== '' && $coverUrl === '' && count($saved) === 0) {
        $ext = hh_scraper_guess_ext($detailUrl);
        $dest = $dir . '/cover.' . $ext;
        $dl = hh_http_download($detailUrl, $dest);
        if ($dl['ok']) {
            $saved[] = array(
                'path' => $safeSlug . '/cover.' . $ext,
                'source_url' => $detailUrl,
                'is_cover' => true,
                'alt_text_zh' => $slug . ' 封面',
                'alt_text_en' => $slug . ' cover',
            );
        }
    }

    return $saved;
}
