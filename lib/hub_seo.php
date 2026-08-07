<?php

require_once __DIR__ . '/site_context.php';
require_once __DIR__ . '/site_repo.php';
require_once __DIR__ . '/public_pages.php';

function hh_llms_txt(PDO $pdo = null)
{
    $hub = hh_site_public_url('', 'hub');
    $handhelds = hh_site_public_url('', 'handhelds');
    $contact = (string) hh_config_get('app.contact_email', '');
    $owner = (string) hh_config_get('app.owner_name', 'Old Man Hub');

    $lines = array(
        '# Old Man Hub / 老男人 Hub',
        '',
        '> Bilingual hub for retro games, handheld encyclopedia, and curated news.',
        '> 怀旧游戏、掌机百科与资讯精选的中转站（English + 中文）。',
        '',
        '## Primary URLs',
        '- Hub (portal): ' . hh_site_public_url('en', 'hub'),
        '- Hub (中文): ' . hh_site_public_url('zh', 'hub'),
        '- Handhelds: ' . hh_site_public_url('en', 'handhelds'),
        '- Handhelds (中文): ' . hh_site_public_url('zh', 'handhelds'),
        '',
        '## Policies',
        '- About: ' . hh_site_public_url('en/about', 'hub'),
        '- Privacy: ' . hh_site_public_url('en/privacy', 'hub'),
        '- Contact: ' . hh_site_public_url('en/contact', 'hub'),
        '',
        '## Sitemaps',
        '- ' . $hub . '/sitemap-index.xml',
        '- ' . $handhelds . '/sitemap.xml',
        '',
        '## Contact',
        '- ' . ($contact !== '' ? $contact : '(see /en/contact)'),
        '- Owner: ' . $owner,
        '',
        '## Crawling',
        'Allow indexing of public /en and /zh pages. Do not index /admin/.',
    );

    if ($pdo !== null) {
        foreach (hh_sites_list($pdo) as $site) {
            if ($site['code'] === 'hub' || $site['code'] === 'handhelds') {
                continue;
            }
            if ($site['status'] !== 'live') {
                continue;
            }
            $lines[] = '- ' . $site['name_en'] . ': ' . $site['base_url'];
        }
    }

    return implode("\n", $lines) . "\n";
}

function hh_sitemap_index_xml(PDO $pdo)
{
    $now = date('c');
    $entries = array(
        array('loc' => hh_site_public_url('sitemap.xml', 'hub'), 'lastmod' => $now),
        array('loc' => hh_site_public_url('sitemap.xml', 'handhelds'), 'lastmod' => $now),
    );
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as $e) {
        $xml .= '  <sitemap><loc>' . hh_h($e['loc']) . '</loc>';
        if (!empty($e['lastmod'])) {
            $xml .= '<lastmod>' . hh_h($e['lastmod']) . '</lastmod>';
        }
        $xml .= '</sitemap>' . "\n";
    }
    $xml .= '</sitemapindex>';
    return $xml;
}
