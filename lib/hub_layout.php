<?php

require_once dirname(__DIR__) . '/lib/public_layout.php';
require_once dirname(__DIR__) . '/lib/site_context.php';
require_once dirname(__DIR__) . '/lib/site_repo.php';

function hh_hub_ui($locale, $key)
{
    $locale = hh_public_locale($locale);
    $all = array(
        'en' => array(
            'site_name' => 'Old Man Hub',
            'tagline' => 'Your gateway to retro games, handheld lore, and curated picks for grown-up players.',
            'nav_home' => 'Home',
            'nav_handhelds' => 'Handhelds',
            'nav_games' => 'Retro games',
            'nav_news' => 'News',
            'nav_about' => 'About',
            'nav_contact' => 'Contact',
            'section_handhelds_title' => 'Handheld encyclopedia',
            'section_handhelds_desc' => 'Specs, release timelines, and editorial guides for portable consoles.',
            'section_games_title' => 'Retro games',
            'section_games_desc' => 'Classic titles, platforms, and nostalgia — directory coming soon.',
            'section_news_title' => 'News & picks',
            'section_news_desc' => 'Original articles and editorials from Old Man Hub.',
            'badge_live' => 'Live',
            'badge_soon' => 'Coming soon',
            'cta_enter' => 'Enter',
            'cta_learn' => 'Learn more',
            'footer_tagline' => 'Old Man Hub — retro games, handhelds, and curated joy / 中文',
            'footer_copyright' => '© %s Old Man Hub. All rights reserved.',
            'footer_about' => 'About',
            'footer_privacy' => 'Privacy',
            'footer_contact' => 'Contact',
            'handhelds_latest' => 'Latest on Handhelds',
            'handhelds_more' => 'Open handhelds site →',
        ),
        'zh' => array(
            'site_name' => '老男人 Hub',
            'tagline' => '怀旧游戏、掌机资料与各类资讯的中转站——给懂行的玩家。',
            'nav_home' => '首页',
            'nav_handhelds' => '掌机百科',
            'nav_games' => '怀旧游戏',
            'nav_news' => '资讯',
            'nav_about' => '关于',
            'nav_contact' => '联系',
            'section_handhelds_title' => '掌机百科',
            'section_handhelds_desc' => '收录掌机规格、发布时间与英文介绍，独立子站持续更新。',
            'section_games_title' => '怀旧游戏',
            'section_games_desc' => '经典平台与游戏目录筹备中，先做合规资料与外链聚合。',
            'section_news_title' => '资讯精选',
            'section_news_desc' => '本站原创资讯与掌机圈短文，Editor 后台发布。',
            'badge_live' => '已上线',
            'badge_soon' => '筹备中',
            'cta_enter' => '进入',
            'cta_learn' => '了解更多',
            'footer_tagline' => '老男人 Hub — 怀旧 · 掌机 · 精选资讯 / English',
            'footer_copyright' => '© %s 老男人 Hub。保留所有权利。',
            'footer_about' => '关于我们',
            'footer_privacy' => '隐私政策',
            'footer_contact' => '联系我们',
            'handhelds_latest' => '掌机站最新',
            'handhelds_more' => '打开掌机子站 →',
        ),
    );
    return isset($all[$locale][$key]) ? $all[$locale][$key] : $key;
}

function hh_hub_layout_start($locale, $title, $meta = array())
{
    $locale = hh_public_locale($locale);
    hh_public_set_locale_cookie($locale);
    $path = isset($meta['path']) ? (string) $meta['path'] : '';
    $uiSite = hh_hub_ui($locale, 'site_name');
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
    $ogImage = !empty($meta['og_image']) ? (string) $meta['og_image'] : hh_site_public_url('assets/og-default.svg', 'hub');
    $ogType = !empty($meta['og_type']) ? (string) $meta['og_type'] : 'website';

    echo '<!DOCTYPE html><html lang="' . hh_h($locale) . '"><head><meta charset="utf-8">';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
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
        echo '<link rel="alternate" hreflang="en" href="' . hh_h(hh_site_public_url($enPath, 'hub')) . '">';
        echo '<link rel="alternate" hreflang="zh" href="' . hh_h(hh_site_public_url($zhPath, 'hub')) . '">';
        echo '<link rel="alternate" hreflang="x-default" href="' . hh_h(hh_site_public_url($enPath, 'hub')) . '">';
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
    echo '<meta name="twitter:card" content="summary_large_image">';
    echo '<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">';
    echo '<link rel="stylesheet" href="/assets/style.css?v=' . (int) @filemtime(dirname(__DIR__) . '/public/assets/style.css') . '">';

    if (!empty($meta['json_ld'])) {
        echo '<script type="application/ld+json">' . $meta['json_ld'] . '</script>';
    }

    echo '</head><body class="hub-site">';
    $pdo = hh_pdo();
    $hubModules = hh_hub_modules_visible_map($pdo);
    $handheldsHome = hh_site_public_url($locale, 'handhelds');
    echo '<header class="site-header"><div class="wrap header-inner">';
    echo '<a class="logo" href="/' . hh_h($locale) . '">' . hh_h($uiSite) . '</a>';
    echo '<nav class="site-nav" aria-label="Main">';
    echo '<a href="/' . hh_h($locale) . '">' . hh_h(hh_hub_ui($locale, 'nav_home')) . '</a>';
    if (!empty($hubModules['handhelds'])) {
        echo '<a href="' . hh_h($handheldsHome) . '">' . hh_h(hh_hub_ui($locale, 'nav_handhelds')) . '</a>';
    }
    if (!empty($hubModules['game'])) {
        echo '<a href="' . hh_h(hh_site_public_url($locale, 'game')) . '">' . hh_h(hh_hub_ui($locale, 'nav_games')) . '</a>';
    }
    if (!empty($hubModules['news'])) {
        echo '<a href="' . hh_h(hh_site_public_url($locale, 'news')) . '">' . hh_h(hh_hub_ui($locale, 'nav_news')) . '</a>';
    }
    echo '<a href="/' . hh_h($locale) . '/about">' . hh_h(hh_hub_ui($locale, 'nav_about')) . '</a>';
    echo '<a href="/' . hh_h($locale) . '/contact">' . hh_h(hh_hub_ui($locale, 'nav_contact')) . '</a>';
    echo hh_public_lang_switch_html($locale, $switchPath, $switchQuery);
    echo '</nav></div></header><main class="wrap">';
}

function hh_hub_layout_end($locale = 'en')
{
    $locale = hh_public_locale($locale);
    $year = date('Y');
    echo '</main>';
    echo '<footer class="site-footer"><div class="wrap footer-inner">';
    echo '<nav class="footer-nav" aria-label="Footer">';
    echo '<a href="/' . hh_h($locale) . '/about">' . hh_h(hh_hub_ui($locale, 'footer_about')) . '</a>';
    echo '<a href="/' . hh_h($locale) . '/privacy">' . hh_h(hh_hub_ui($locale, 'footer_privacy')) . '</a>';
    echo '<a href="/' . hh_h($locale) . '/contact">' . hh_h(hh_hub_ui($locale, 'footer_contact')) . '</a>';
    echo '<a href="' . hh_h(hh_site_public_url($locale, 'handhelds')) . '">' . hh_h(hh_hub_ui($locale, 'nav_handhelds')) . '</a>';
    echo '</nav>';
    echo '<p>' . hh_h(hh_hub_ui($locale, 'footer_tagline')) . '</p>';
    echo '<p class="footer-copy">' . hh_h(sprintf(hh_hub_ui($locale, 'footer_copyright'), $year)) . '</p>';
    echo '</div>';
    echo '<script src="/assets/site.js?v=' . (int) @filemtime(dirname(__DIR__) . '/public/assets/site.js') . '" defer></script>';
    echo '</body></html>';
}
