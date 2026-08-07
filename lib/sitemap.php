<?php

require_once __DIR__ . '/site_context.php';

function hh_sitemap_xml(PDO $pdo)
{
    if (function_exists('hh_site_is_hub') && hh_site_is_hub()) {
        return hh_sitemap_hub_xml();
    }
    return hh_sitemap_handhelds_xml($pdo);
}

function hh_sitemap_hub_xml()
{
    require_once __DIR__ . '/public_pages.php';
    $urls = array();
    $now = date('c');
    foreach (array('en', 'zh') as $locale) {
        $urls[] = array('loc' => hh_site_public_url($locale, 'hub'), 'lastmod' => $now, 'priority' => '1.0');
        foreach (hh_public_static_pages() as $slug) {
            $urls[] = array(
                'loc' => hh_site_public_url($locale . '/' . $slug, 'hub'),
                'lastmod' => $now,
                'priority' => '0.5',
            );
        }
    }
    return hh_sitemap_build_urlset($urls);
}

function hh_sitemap_handhelds_xml(PDO $pdo)
{
    require_once __DIR__ . '/handheld_repo.php';
    require_once __DIR__ . '/public_pages.php';

    $urls = array();
    $now = date('c');

    foreach (array('en', 'zh') as $locale) {
        $urls[] = array('loc' => hh_site_public_url($locale, 'handhelds'), 'lastmod' => $now, 'priority' => '1.0');
        $urls[] = array('loc' => hh_site_public_url($locale . '/handhelds', 'handhelds'), 'lastmod' => $now, 'priority' => '0.9');
        foreach (hh_public_static_pages() as $slug) {
            $urls[] = array('loc' => hh_site_public_url($locale . '/' . $slug, 'handhelds'), 'lastmod' => $now, 'priority' => '0.5');
        }
    }

    $st = $pdo->query('SELECT slug, updated_at FROM hh_handhelds WHERE status = "published" ORDER BY id ASC');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $slug = (string) $row['slug'];
        $last = !empty($row['updated_at']) ? date('c', strtotime((string) $row['updated_at'])) : $now;
        foreach (array('en', 'zh') as $locale) {
            $urls[] = array(
                'loc' => hh_site_public_url($locale . '/handheld/' . $slug, 'handhelds'),
                'lastmod' => $last,
                'priority' => '0.8',
            );
        }
    }

    return hh_sitemap_build_urlset($urls);
}

function hh_sitemap_build_urlset(array $urls)
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        $xml .= '  <url><loc>' . hh_h($u['loc']) . '</loc>';
        if (!empty($u['lastmod'])) {
            $xml .= '<lastmod>' . hh_h($u['lastmod']) . '</lastmod>';
        }
        if (!empty($u['priority'])) {
            $xml .= '<priority>' . hh_h($u['priority']) . '</priority>';
        }
        $xml .= '</url>' . "\n";
    }
    $xml .= '</urlset>';
    return $xml;
}

function hh_robots_txt()
{
    $base = hh_site_public_url('', hh_site_code());
    $lines = "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /storage/\n\n";
    if (hh_site_is_hub()) {
        $lines .= 'Sitemap: ' . $base . "/sitemap-index.xml\n";
    }
    $lines .= 'Sitemap: ' . $base . "/sitemap.xml\n";
    if (hh_site_is_hub()) {
        $lines .= 'Sitemap: ' . hh_site_public_url('sitemap.xml', 'handhelds') . "\n";
    }
    return $lines;
}
