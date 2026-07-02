<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/handheld_repo.php';

function hh_public_locale($locale)
{
    return in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
}

function hh_public_set_locale_cookie($locale)
{
    $locale = hh_public_locale($locale);
    if (headers_sent()) {
        return;
    }
    setcookie('hh_locale', $locale, time() + 365 * 86400, '/', '', false, false);
}

function hh_public_preferred_locale()
{
    if (!empty($_COOKIE['hh_locale']) && in_array($_COOKIE['hh_locale'], array('en', 'zh'), true)) {
        return (string) $_COOKIE['hh_locale'];
    }
    $default = (string) hh_config_get('app.default_locale', 'en');
    return hh_public_locale($default);
}

function hh_public_ui($locale, $key)
{
    $locale = hh_public_locale($locale);
    $all = array(
        'en' => array(
            'site_name' => 'Handheld Hub',
            'nav_handhelds' => 'Handhelds',
            'nav_lang_other' => '中文',
            'footer' => 'Handheld gaming encyclopedia — English / 中文',
            'spec_caption' => 'Specifications',
            'blogger_read' => 'Read full article on Blogger',
            'blogger_read_zh' => 'Chinese article on Blogger',
            'blogger_read_en' => 'English article on Blogger',
            'no_handhelds' => 'No published handhelds yet.',
            'sort_hint' => 'Sorted by release date, newest first',
            'all_brands' => 'All brands',
            'release_date_label' => 'Release date: ',
            'pagination_prev' => 'Previous',
            'pagination_next' => 'Next',
            'pagination_page' => 'Page %d / %d',
            'pagination_total' => '%d handhelds',
            'pagination_range' => 'Showing %d–%d of %d',
        ),
        'zh' => array(
            'site_name' => '掌机百科',
            'nav_handhelds' => '掌机列表',
            'nav_lang_other' => 'English',
            'footer' => '掌上游戏设备百科 — 中文 / English',
            'spec_caption' => '硬件参数',
            'blogger_read' => '在 Blogger 阅读全文',
            'blogger_read_zh' => 'Blogger 中文文章',
            'blogger_read_en' => 'Blogger 英文文章',
            'no_handhelds' => '暂无已发布掌机。',
            'sort_hint' => '按发布时间排序，最新在前',
            'all_brands' => '全部品牌',
            'release_date_label' => '发布日期：',
            'pagination_prev' => '上一页',
            'pagination_next' => '下一页',
            'pagination_page' => '第 %d / %d 页',
            'pagination_total' => '共 %d 台',
            'pagination_range' => '显示第 %d–%d 条，共 %d 台',
        ),
    );
    return isset($all[$locale][$key]) ? $all[$locale][$key] : $key;
}

function hh_public_lang_switch_html($locale, $switchPath, $switchQuery = '')
{
    $locale = hh_public_locale($locale);
    $options = array(
        'en' => array('code' => 'EN', 'label' => 'English'),
        'zh' => array('code' => '中文', 'label' => '中文'),
    );
    $current = $options[$locale]['code'];
    $html = '<details class="lang-dropdown">';
    $html .= '<summary class="lang-dropdown-toggle" aria-label="Language">';
    $html .= '<span class="lang-current">' . hh_h($current) . '</span>';
    $html .= '<span class="lang-caret" aria-hidden="true"></span>';
    $html .= '</summary>';
    $html .= '<div class="lang-dropdown-menu" role="menu">';
    foreach ($options as $loc => $opt) {
        $href = '/' . $loc . '/' . $switchPath . $switchQuery;
        $cls = 'lang-option' . ($loc === $locale ? ' is-active' : '');
        $html .= '<a class="' . $cls . '" href="' . hh_h($href) . '" role="menuitem" hreflang="' . hh_h($loc) . '">';
        $html .= hh_h($opt['label']);
        $html .= '</a>';
    }
    $html .= '</div></details>';
    return $html;
}

function hh_public_layout_start($locale, $title, $meta = array())
{
    $locale = hh_public_locale($locale);
    hh_public_set_locale_cookie($locale);
    $path = isset($meta['path']) ? (string) $meta['path'] : '';
    $uiSite = hh_public_ui($locale, 'site_name');
    $switchPath = ($path !== '' ? $path : 'handhelds');
    $switchQuery = '';
    if (!empty($_GET)) {
        $qs = $_GET;
        unset($qs['locale'], $qs['slug']);
        if ($qs) {
            $switchQuery = '?' . http_build_query($qs);
        }
    }

    echo '<!DOCTYPE html><html lang="' . hh_h($locale) . '"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . hh_h($title) . ' · ' . hh_h($uiSite) . '</title>';
    if (!empty($meta['description'])) {
        echo '<meta name="description" content="' . hh_h($meta['description']) . '">';
    }
    if (!empty($meta['canonical'])) {
        echo '<link rel="canonical" href="' . hh_h($meta['canonical']) . '">';
    }
    if ($path !== '') {
        echo '<link rel="alternate" hreflang="en" href="' . hh_h(hh_public_url('en/' . $path)) . '">';
        echo '<link rel="alternate" hreflang="zh" href="' . hh_h(hh_public_url('zh/' . $path)) . '">';
        echo '<link rel="alternate" hreflang="x-default" href="' . hh_h(hh_public_url('en/' . $path)) . '">';
    }
    if (!empty($meta['og_image'])) {
        echo '<meta property="og:image" content="' . hh_h($meta['og_image']) . '">';
    }
    echo '<link rel="stylesheet" href="/assets/style.css?v=' . (int) @filemtime(dirname(__DIR__) . '/public/assets/style.css') . '"></head><body>';
    echo '<header class="site-header"><div class="wrap header-inner">';
    echo '<a class="logo" href="/' . hh_h($locale) . '/handhelds">' . hh_h($uiSite) . '</a>';
    echo '<nav class="site-nav">';
    echo '<a href="/' . hh_h($locale) . '/handhelds">' . hh_h(hh_public_ui($locale, 'nav_handhelds')) . '</a>';
    echo hh_public_lang_switch_html($locale, $switchPath, $switchQuery);
    echo '</nav></div></header><main class="wrap">';
}

function hh_public_layout_end($locale = 'en')
{
    echo '</main><footer class="site-footer"><div class="wrap"><p>' . hh_h(hh_public_ui($locale, 'footer')) . '</p></div></footer></body></html>';
}

function hh_public_list_name($handheld)
{
    return $handheld['name_zh'] ?: $handheld['slug'];
}

/** Short device name (list cards, alt text). Not the Blogger/article headline. */
function hh_public_display_name($handheld, $locale)
{
    unset($locale);
    return hh_public_list_name($handheld);
}

function hh_public_content_for($pdo, $handheldId, $locale)
{
    $c = hh_handheld_content($pdo, $handheldId, $locale);
    if ($c && (!empty($c['title']) || !empty($c['body_html']))) {
        return $c;
    }
    return hh_handheld_content($pdo, $handheldId, $locale === 'en' ? 'zh' : 'en');
}

function hh_public_page_title($locale, $page)
{
    if ($page === 'handhelds') {
        return $locale === 'zh' ? '掌机列表' : 'Handhelds';
    }
    return $page;
}
