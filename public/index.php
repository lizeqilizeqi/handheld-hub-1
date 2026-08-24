<?php

/** Front controller — image routes skip bootstrap (avoid log-dir 500). */

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/site_context.php';

hh_site_apply_legacy_redirects();
hh_site_apply_hub_www_redirect();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim((string) $uri, '/');
if ($uri === '') {
    $uri = '/';
}

if ($uri === '/robots.txt') {
    require_once dirname(__DIR__) . '/lib/bootstrap.php';
    require_once dirname(__DIR__) . '/lib/sitemap.php';
    hh_bootstrap();
    header('Content-Type: text/plain; charset=utf-8');
    echo hh_robots_txt();
    exit;
}

if ($uri === '/sitemap.xml') {
    require_once dirname(__DIR__) . '/lib/bootstrap.php';
    require_once dirname(__DIR__) . '/lib/sitemap.php';
    hh_bootstrap();
    $pdo = hh_pdo();
    header('Content-Type: application/xml; charset=utf-8');
    echo hh_sitemap_xml($pdo);
    exit;
}

if ($uri === '/sitemap-index.xml') {
    require_once dirname(__DIR__) . '/lib/bootstrap.php';
    require_once dirname(__DIR__) . '/lib/sitemap.php';
    require_once dirname(__DIR__) . '/lib/hub_seo.php';
    hh_bootstrap();
    if (!hh_site_is_hub()) {
        header('Location: ' . hh_site_public_url('sitemap.xml', hh_site_code()), true, 302);
        exit;
    }
    $pdo = hh_pdo();
    header('Content-Type: application/xml; charset=utf-8');
    echo hh_sitemap_index_xml($pdo);
    exit;
}

if ($uri === '/llms.txt') {
    require_once dirname(__DIR__) . '/lib/bootstrap.php';
    require_once dirname(__DIR__) . '/lib/hub_seo.php';
    hh_bootstrap();
    if (!hh_site_is_hub()) {
        http_response_code(404);
        echo '404 Not Found';
        exit;
    }
    $pdo = hh_pdo();
    header('Content-Type: text/plain; charset=utf-8');
    echo hh_llms_txt($pdo);
    exit;
}

if (preg_match('#^/storage/handhelds/(.+)$#', $uri, $m)) {
    hh_serve_storage_file($m[1]);
}

if (preg_match('#^/i/(.+)$#', $uri, $m)) {
    hh_serve_storage_file($m[1]);
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';
hh_bootstrap();

if ($uri === '/') {
    require_once dirname(__DIR__) . '/lib/public_layout.php';
    $locale = hh_public_preferred_locale();
    header('Location: /' . $locale, true, 302);
    exit;
}

$site = hh_site_code();

if ($site === 'hub') {
    if (preg_match('#^/(en|zh)/handhelds$#', $uri, $m)) {
        hh_site_redirect_to_handhelds($m[1] . '/handhelds');
    }
    if (preg_match('#^/(en|zh)/handheld/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
        hh_site_redirect_to_handhelds($m[1] . '/handheld/' . $m[2]);
    }

    if (preg_match('#^/(en|zh)$#', $uri, $m)) {
        $_GET['locale'] = $m[1];
        require __DIR__ . '/hub_home.php';
        exit;
    }

    if (preg_match('#^/(en|zh)/(about|privacy|contact|terms)$#', $uri, $m)) {
        $_GET['locale'] = $m[1];
        $_GET['slug'] = $m[2];
        $_GET['hub_layout'] = '1';
        require __DIR__ . '/page.php';
        exit;
    }

    http_response_code(404);
    echo '404 Not Found';
    exit;
}

if ($site === 'handhelds') {
    if (preg_match('#^/(en|zh)$#', $uri, $m)) {
        $_GET['locale'] = $m[1];
        require __DIR__ . '/home.php';
        exit;
    }

    if (preg_match('#^/(en|zh)/handhelds$#', $uri, $m)) {
        $_GET['locale'] = $m[1];
        require __DIR__ . '/handhelds.php';
        exit;
    }

    if (preg_match('#^/(en|zh)/handheld/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
        $_GET['locale'] = $m[1];
        $_GET['slug'] = $m[2];
        require __DIR__ . '/handheld.php';
        exit;
    }

    if (preg_match('#^/(en|zh)/(about|privacy|contact|terms)$#', $uri, $m)) {
        $_GET['locale'] = $m[1];
        $_GET['slug'] = $m[2];
        require __DIR__ . '/page.php';
        exit;
    }

    http_response_code(404);
    echo '404 Not Found';
    exit;
}

if (in_array($site, hh_vertical_site_codes(), true)) {
    if ($site === 'game') {
        if (preg_match('#^/(en|zh)/games$#', $uri, $m)) {
            $_GET['locale'] = $m[1];
            require __DIR__ . '/game_games.php';
            exit;
        }
        if (preg_match('#^/(en|zh)/game/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
            $_GET['locale'] = $m[1];
            $_GET['slug'] = $m[2];
            require __DIR__ . '/game_detail.php';
            exit;
        }
        if (preg_match('#^/(en|zh)$#', $uri, $m)) {
            $_GET['locale'] = $m[1];
            require __DIR__ . '/game_home.php';
            exit;
        }
    } else {
        if (preg_match('#^/(en|zh)/news$#', $uri, $m)) {
            $_GET['locale'] = $m[1];
            require __DIR__ . '/news_list.php';
            exit;
        }
        if (preg_match('#^/(en|zh)/news/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
            $_GET['locale'] = $m[1];
            $_GET['slug'] = $m[2];
            require __DIR__ . '/news_detail.php';
            exit;
        }
        if (preg_match('#^/(en|zh)$#', $uri, $m)) {
            $_GET['locale'] = $m[1];
            require __DIR__ . '/news_home.php';
            exit;
        }
    }

    http_response_code(404);
    echo '404 Not Found';
    exit;
}

http_response_code(404);
echo '404 Not Found';
