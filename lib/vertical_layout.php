<?php

require_once __DIR__ . '/public_layout.php';
require_once __DIR__ . '/vertical_ui.php';
require_once __DIR__ . '/site_context.php';

function hh_vertical_layout_start($siteCode, $locale, $title, $meta = array())
{
    $locale = hh_public_locale($locale);
    hh_public_set_locale_cookie($locale);
    $path = isset($meta['path']) ? (string) $meta['path'] : '';
    $uiSite = hh_vertical_ui($siteCode, $locale, 'site_name');
    $switchPath = $path;
    $switchQuery = '';
    if (!empty($_GET)) {
        $qs = $_GET;
        unset($qs['locale'], $qs['slug'], $qs['page']);
        if ($qs) {
            $switchQuery = '?' . http_build_query($qs);
        }
    }

    $canonical = !empty($meta['canonical']) ? (string) $meta['canonical'] : '';
    $description = !empty($meta['description']) ? (string) $meta['description'] : '';
    $ogImage = !empty($meta['og_image']) ? (string) $meta['og_image'] : hh_site_public_url('assets/og-default.svg', $siteCode);
    $ogType = !empty($meta['og_type']) ? (string) $meta['og_type'] : 'website';

    echo '<!DOCTYPE html><html lang="' . hh_h($locale) . '"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . hh_h($title) . ' · ' . hh_h($uiSite) . '</title>';
    if ($description !== '') {
        echo '<meta name="description" content="' . hh_h($description) . '">';
    }
    echo '<meta name="robots" content="index,follow">';
    if ($canonical !== '') {
        echo '<link rel="canonical" href="' . hh_h($canonical) . '">';
    }
    if ($path !== '' || !empty($meta['hreflang_home'])) {
        $enPath = $path !== '' ? 'en/' . $path : 'en';
        $zhPath = $path !== '' ? 'zh/' . $path : 'zh';
        echo '<link rel="alternate" hreflang="en" href="' . hh_h(hh_site_public_url($enPath, $siteCode)) . '">';
        echo '<link rel="alternate" hreflang="zh" href="' . hh_h(hh_site_public_url($zhPath, $siteCode)) . '">';
        echo '<link rel="alternate" hreflang="x-default" href="' . hh_h(hh_site_public_url($enPath, $siteCode)) . '">';
    }
    echo '<meta property="og:site_name" content="' . hh_h($uiSite) . '">';
    echo '<meta property="og:title" content="' . hh_h($title) . '">';
    if ($description !== '') {
        echo '<meta property="og:description" content="' . hh_h($description) . '">';
    }
    if ($canonical !== '') {
        echo '<meta property="og:url" content="' . hh_h($canonical) . '">';
    }
    echo '<meta property="og:type" content="' . hh_h($ogType) . '">';
    echo '<meta property="og:image" content="' . hh_h($ogImage) . '">';
    echo '<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">';
    echo '<link rel="stylesheet" href="/assets/style.css?v=' . (int) @filemtime(dirname(__DIR__) . '/public/assets/style.css') . '">';
    echo '</head><body>';

    $hubHome = hh_site_public_url($locale, 'hub');
    echo '<header class="site-header"><div class="wrap header-inner">';
    echo '<a class="logo" href="/' . hh_h($locale) . '">' . hh_h($uiSite) . '</a>';
    echo '<nav class="site-nav" aria-label="Main">';
    echo '<a href="' . hh_h($hubHome) . '">' . hh_h(hh_vertical_ui($siteCode, $locale, 'back_hub')) . '</a>';
    echo '<a href="/' . hh_h($locale) . '">' . hh_h($locale === 'zh' ? '首页' : 'Home') . '</a>';
    echo hh_public_lang_switch_html($locale, $switchPath, $switchQuery);
    echo '</nav></div></header><main class="wrap">';
}

function hh_vertical_layout_end($siteCode, $locale = 'en')
{
    $locale = hh_public_locale($locale);
    $year = date('Y');
    $uiSite = hh_vertical_ui($siteCode, $locale, 'site_name');
    echo '</main>';
    echo '<footer class="site-footer"><div class="wrap footer-inner">';
    echo '<p class="footer-tagline">' . hh_h(hh_vertical_ui($siteCode, $locale, 'tagline')) . '</p>';
    echo '<p class="footer-copy">© ' . (int) $year . ' ' . hh_h($uiSite) . ' · ';
    echo '<a href="' . hh_h(hh_site_public_url($locale, 'hub')) . '">Old Man Hub</a></p>';
    echo '</div></footer>';
    echo '<script src="/assets/site.js?v=' . (int) @filemtime(dirname(__DIR__) . '/public/assets/site.js') . '" defer></script>';
    echo '</body></html>';
}
