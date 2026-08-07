<?php

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/handheld_repo.php';
require_once dirname(__DIR__) . '/lib/site_context.php';

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
            'nav_home' => 'Home',
            'nav_hub' => 'Old Man Hub',
            'nav_handhelds' => 'Handhelds',
            'nav_about' => 'About',
            'nav_contact' => 'Contact',
            'nav_lang_other' => '中文',
            'footer_tagline' => 'Handheld gaming encyclopedia — English / 中文',
            'footer_about' => 'About',
            'footer_privacy' => 'Privacy Policy',
            'footer_terms' => 'Terms of Use',
            'footer_contact' => 'Contact',
            'footer_copyright' => '© %s Handheld Hub. All rights reserved.',
            'cookie_text' => 'We use cookies for language preference and, after approval, Google AdSense ads. See our Privacy Policy.',
            'cookie_accept' => 'Accept',
            'cookie_privacy' => 'Privacy Policy',
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
            'nav_home' => '首页',
            'nav_hub' => '老男人 Hub',
            'nav_handhelds' => '掌机列表',
            'nav_about' => '关于我们',
            'nav_contact' => '联系我们',
            'nav_lang_other' => 'English',
            'footer_tagline' => '掌上游戏设备百科 — 中文 / English',
            'footer_about' => '关于我们',
            'footer_privacy' => '隐私政策',
            'footer_terms' => '使用条款',
            'footer_contact' => '联系我们',
            'footer_copyright' => '© %s 掌机百科。保留所有权利。',
            'cookie_text' => '我们使用 Cookie 记录语言偏好；获批后将使用 Google AdSense 展示广告。详见隐私政策。',
            'cookie_accept' => '接受',
            'cookie_privacy' => '隐私政策',
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
        'zh' => array('code' => '中文', 'label' => '简体中文'),
    );
    $current = $options[$locale]['code'];
    $html = '<details class="lang-dropdown">';
    $html .= '<summary class="lang-dropdown-toggle" aria-label="Language">';
    $html .= '<span class="lang-current">' . hh_h($current) . '</span>';
    $html .= '<span class="lang-caret" aria-hidden="true"></span>';
    $html .= '</summary>';
    $html .= '<ul class="lang-dropdown-menu" role="menu">';
    foreach ($options as $loc => $opt) {
        $href = '/' . $loc . ($switchPath !== '' ? '/' . $switchPath : '') . $switchQuery;
        $cls = 'lang-option' . ($loc === $locale ? ' is-active' : '');
        $html .= '<li role="none"><a class="' . $cls . '" href="' . hh_h($href) . '" role="menuitem" hreflang="' . hh_h($loc) . '">';
        $html .= hh_h($opt['label']);
        $html .= '</a></li>';
    }
    $html .= '</ul></details>';
    return $html;
}

function hh_public_layout_start($locale, $title, $meta = array())
{
    $locale = hh_public_locale($locale);
    hh_public_set_locale_cookie($locale);
    $path = isset($meta['path']) ? (string) $meta['path'] : '';
    $uiSite = hh_public_ui($locale, 'site_name');
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
    $ogImage = !empty($meta['og_image']) ? (string) $meta['og_image'] : hh_public_url('assets/og-default.svg');
    $ogType = !empty($meta['og_type']) ? (string) $meta['og_type'] : 'article';

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
    if ($path !== '' || isset($meta['hreflang_home'])) {
        $enPath = $path !== '' ? 'en/' . $path : 'en';
        $zhPath = $path !== '' ? 'zh/' . $path : 'zh';
        echo '<link rel="alternate" hreflang="en" href="' . hh_h(hh_public_url($enPath)) . '">';
        echo '<link rel="alternate" hreflang="zh" href="' . hh_h(hh_public_url($zhPath)) . '">';
        echo '<link rel="alternate" hreflang="x-default" href="' . hh_h(hh_public_url($enPath)) . '">';
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
    echo '<meta name="twitter:title" content="' . hh_h($title) . '">';
    if ($description !== '') {
        echo '<meta name="twitter:description" content="' . hh_h($description) . '">';
    }
    echo '<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">';
    echo '<link rel="stylesheet" href="/assets/style.css?v=' . (int) @filemtime(dirname(__DIR__) . '/public/assets/style.css') . '">';

    $adsenseId = trim((string) hh_config_get('adsense.client_id', ''));
    if ($adsenseId !== '' && preg_match('/^ca-pub-\d+$/', $adsenseId)) {
        echo '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . hh_h($adsenseId) . '" crossorigin="anonymous"></script>';
    }

    if (!empty($meta['json_ld'])) {
        echo '<script type="application/ld+json">' . $meta['json_ld'] . '</script>';
    }

    echo '</head><body>';
    $hubHome = function_exists('hh_site_public_url') ? hh_site_public_url($locale, 'hub') : '';
    echo '<header class="site-header"><div class="wrap header-inner">';
    echo '<a class="logo" href="/' . hh_h($locale) . '">' . hh_h($uiSite) . '</a>';
    echo '<nav class="site-nav" aria-label="Main">';
    if ($hubHome !== '' && function_exists('hh_site_is_handhelds') && hh_site_is_handhelds()) {
        echo '<a href="' . hh_h($hubHome) . '">' . hh_h(hh_public_ui($locale, 'nav_hub')) . '</a>';
    }
    echo '<a href="/' . hh_h($locale) . '">' . hh_h(hh_public_ui($locale, 'nav_home')) . '</a>';
    echo '<a href="/' . hh_h($locale) . '/handhelds">' . hh_h(hh_public_ui($locale, 'nav_handhelds')) . '</a>';
    echo '<a href="/' . hh_h($locale) . '/about">' . hh_h(hh_public_ui($locale, 'nav_about')) . '</a>';
    echo '<a href="/' . hh_h($locale) . '/contact">' . hh_h(hh_public_ui($locale, 'nav_contact')) . '</a>';
    echo hh_public_lang_switch_html($locale, $switchPath, $switchQuery);
    echo '</nav></div></header><main class="wrap">';
}

function hh_public_layout_end($locale = 'en')
{
    $locale = hh_public_locale($locale);
    $year = date('Y');
    echo '</main>';
    echo '<footer class="site-footer"><div class="wrap footer-inner">';
    echo '<nav class="footer-nav" aria-label="Footer">';
    if (function_exists('hh_site_is_handhelds') && hh_site_is_handhelds()) {
        echo '<a href="' . hh_h(hh_site_public_url($locale, 'hub')) . '">' . hh_h(hh_public_ui($locale, 'nav_hub')) . '</a>';
    }
    echo '<a href="/' . hh_h($locale) . '/about">' . hh_h(hh_public_ui($locale, 'footer_about')) . '</a>';
    echo '<a href="/' . hh_h($locale) . '/privacy">' . hh_h(hh_public_ui($locale, 'footer_privacy')) . '</a>';
    echo '<a href="/' . hh_h($locale) . '/contact">' . hh_h(hh_public_ui($locale, 'footer_contact')) . '</a>';
    echo '<a href="/' . hh_h($locale) . '/handhelds">' . hh_h(hh_public_ui($locale, 'nav_handhelds')) . '</a>';
    echo '</nav>';
    echo '<p>' . hh_h(hh_public_ui($locale, 'footer_tagline')) . '</p>';
    echo '<p class="footer-copy">' . hh_h(sprintf(hh_public_ui($locale, 'footer_copyright'), $year)) . '</p>';
    echo '</div>';
    echo '<script src="/assets/site.js?v=' . (int) @filemtime(dirname(__DIR__) . '/public/assets/site.js') . '" defer></script>';
    echo '</body></html>';
}

function hh_public_list_name($handheld)
{
    return $handheld['name_zh'] ?: $handheld['slug'];
}

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

require_once dirname(__DIR__) . '/lib/public_pages.php';
require_once dirname(__DIR__) . '/lib/brand_repo.php';

function hh_public_brand_logos_for_list(PDO $pdo, array $list)
{
    $brands = array();
    foreach ($list as $row) {
        $brands[] = (string) $row['brand'];
    }
    return hh_brand_logo_map($pdo, $brands);
}

function hh_public_render_handheld_card(PDO $pdo, $locale, array $h, array $brandLogos)
{
    $locale = hh_public_locale($locale);
    $content = hh_handheld_content($pdo, (int) $h['id'], $locale);
    $name = ($content && !empty($content['title'])) ? (string) $content['title'] : hh_public_list_name($h);
    $imgRow = hh_handheld_cover_image($pdo, (int) $h['id']);
    $img = ($imgRow && !empty($imgRow['path'])) ? hh_image_public_url($imgRow['path']) : '';
    $brandLogo = isset($brandLogos[$h['brand']]) ? $brandLogos[$h['brand']] : '';
    $screenSize = trim((string) $h['screen_size']);
    $screenRatio = trim((string) $h['screen_ratio']);
    ?>
  <article class="card">
  <a class="card-link" href="/<?php echo hh_h($locale); ?>/handheld/<?php echo hh_h($h['slug']); ?>">
    <?php if ($img): ?><figure class="card-media"><img src="<?php echo hh_h($img); ?>" alt="<?php echo hh_h($name); ?>" loading="lazy"></figure><?php endif; ?>
    <div class="card-body">
      <div class="card-title-row">
        <?php if ($brandLogo): ?>
        <img class="card-brand-logo" src="<?php echo hh_h($brandLogo); ?>" alt="<?php echo hh_h($h['brand']); ?>" loading="lazy">
        <?php endif; ?>
        <h3 class="card-title"><?php echo hh_h($name); ?></h3>
      </div>
      <div class="card-meta">
        <?php if ($screenSize !== '' || $screenRatio !== ''): ?>
        <div class="card-tags">
          <?php if ($screenSize !== ''): ?><span class="card-tag"><?php echo hh_h($screenSize); ?></span><?php endif; ?>
          <?php if ($screenRatio !== ''): ?><span class="card-tag"><?php echo hh_h($screenRatio); ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($h['release_date'])): ?>
        <div class="card-tags card-tags-release">
          <span class="card-tag"><?php echo hh_h(hh_public_ui($locale, 'release_date_label') . $h['release_date']); ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </a>
  </article>
    <?php
}
