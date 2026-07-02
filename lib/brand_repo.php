<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_client.php';
require_once __DIR__ . '/scraper/detail_scraper.php';

function hh_brand_slug_from_logo_url($logoUrl)
{
    if (preg_match('#/brand/([^/.]+)#i', (string) $logoUrl, $m)) {
        return strtolower((string) $m[1]);
    }
    return '';
}

function hh_brand_logo_file_aliases()
{
    return array(
        'ktpocket' => array('KTR'),
        'rp' => array('Retroid Pocket', 'Retroid'),
        'one-netbook' => array('One Netbook', 'OneXPlayer', 'ONEXPLAYER', 'One XPlayer'),
        'ayn' => array('AYN'),
        'ayaneo' => array('AYANEO', 'Ayaneo'),
        'aokzoe' => array('AOKZOE'),
        'anbernic' => array('Anbernic'),
        'gpd' => array('GPD'),
        'gkd' => array('GKD'),
        'miyoo' => array('Miyoo'),
        'powkiddy' => array('PowKiddy', 'POWKIDDY'),
        'trimui' => array('TRIMUI', 'Trimui'),
        'asus' => array('ASUS', 'Asus', 'ROG'),
        'lenovo' => array('Lenovo'),
        'logitech' => array('Logitech'),
        'nintendo' => array('Nintendo'),
        'sony' => array('Sony'),
        'valve' => array('Valve'),
        'sega' => array('Sega'),
        'msi' => array('MSI'),
        'mangmi' => array('Mangmi', 'MANGMI'),
        'gamemt' => array('GAMEMT', 'Gamemt'),
        'abxylute' => array('Abxylute', 'ABXYLUTE'),
        'zpg' => array('ZPG'),
        'orange-pi' => array('Orange Pi', 'OrangePi'),
        'clockworkpi' => array('Clockwork Pi', 'ClockworkPi'),
        'gameforce' => array('GameForce', 'GAME FORCE'),
        'gemei' => array('Gemei', 'GEMEI'),
        'jxd' => array('JXD'),
        'dingoo' => array('Dingo', 'Dingoo'),
        'bandai' => array('Bandai', 'BANDAI'),
        'analogue' => array('Analogue'),
        'experimentalpi' => array('Experimental Pi', 'ExperimentalPi'),
        'panic' => array('Panic'),
        'pimax' => array('Pimax', 'PIMAX'),
        'sjgam' => array('SJGAM', 'SJGame'),
        'subor' => array('Subor', 'SUBOR'),
        'tencent' => array('Tencent'),
        'tjd' => array('TJD'),
    );
}

function hh_brand_by_name(PDO $pdo, $name)
{
    $st = $pdo->prepare('SELECT * FROM hh_brands WHERE name = ? LIMIT 1');
    $st->execute(array((string) $name));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }
    $st = $pdo->prepare('SELECT * FROM hh_brands WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $st->execute(array((string) $name));
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hh_brand_logo_map(PDO $pdo, array $brandNames)
{
    $names = array_values(array_unique(array_filter(array_map('trim', $brandNames))));
    if ($names === array()) {
        return array();
    }

    $all = $pdo->query('SELECT name, logo_path FROM hh_brands WHERE logo_path <> ""')->fetchAll(PDO::FETCH_ASSOC);
    $byLower = array();
    foreach ($all as $row) {
        $byLower[strtolower((string) $row['name'])] = $row;
    }

    $map = array();
    foreach ($names as $name) {
        $st = $pdo->prepare('SELECT name, logo_path FROM hh_brands WHERE name = ? AND logo_path <> "" LIMIT 1');
        $st->execute(array($name));
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $key = strtolower($name);
            if (isset($byLower[$key])) {
                $row = $byLower[$key];
            }
        }
        if ($row && !empty($row['logo_path'])) {
            $map[$name] = hh_image_public_url((string) $row['logo_path']);
        }
    }
    return $map;
}

function hh_brand_download_logo($logoUrl, $slug)
{
    $logoUrl = trim((string) $logoUrl);
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $slug);
    if ($logoUrl === '' || $slug === '') {
        return '';
    }

    $dir = HH_STORAGE_FS . '/brands';
    hh_ensure_dir($dir);

    $ext = 'webp';
    if (preg_match('#\.(png|webp|gif|jpe?g)#i', $logoUrl, $m)) {
        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
    }

    $dest = $dir . '/' . $slug . '.' . $ext;
    if (is_file($dest) && filesize($dest) > 0) {
        return 'brands/' . $slug . '.' . $ext;
    }

    $dl = hh_http_download($logoUrl, $dest);
    if (!$dl['ok']) {
        return '';
    }
    return 'brands/' . $slug . '.' . $ext;
}

function hh_brand_upsert(PDO $pdo, $name, $logoUrl)
{
    $name = trim((string) $name);
    $logoUrl = trim((string) $logoUrl);
    if ($name === '' || $logoUrl === '') {
        return null;
    }

    $slug = hh_brand_slug_from_logo_url($logoUrl);
    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    }

    $existing = hh_brand_by_name($pdo, $name);
    $logoPath = '';
    if (!$existing || (string) $existing['logo_url'] !== $logoUrl || empty($existing['logo_path'])) {
        $logoPath = hh_brand_download_logo($logoUrl, $slug);
    } elseif ($existing) {
        $logoPath = (string) $existing['logo_path'];
    }

    if ($existing) {
        $pdo->prepare('UPDATE hh_brands SET slug=?, logo_path=?, logo_url=? WHERE id=?')
            ->execute(array($slug, $logoPath, $logoUrl, (int) $existing['id']));
        return (int) $existing['id'];
    }

    $pdo->prepare('INSERT INTO hh_brands (name, slug, logo_path, logo_url) VALUES (?,?,?,?)')
        ->execute(array($name, $slug, $logoPath, $logoUrl));
    return (int) $pdo->lastInsertId();
}

/** @return array<int, array{name:string,logo_url:string}> */
function hh_scraper_parse_brand_catalog($html)
{
    $brands = array();
    if (!preg_match_all(
        '#<img class="brand-logo-dropdown" src="([^"]+)"[^>]*>\s*([^<]+)</a>#u',
        (string) $html,
        $m,
        PREG_SET_ORDER
    )) {
        return $brands;
    }

    foreach ($m as $match) {
        $name = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($name === '' || $name === '查看全部品牌') {
            continue;
        }
        $brands[] = array(
            'name' => $name,
            'logo_url' => hh_scraper_normalize_url($match[1]),
        );
    }
    return $brands;
}

function hh_brand_names_for_logo_slug(PDO $pdo, $fileSlug, $logoUrl)
{
    $names = array();
    $aliases = hh_brand_logo_file_aliases();
    if (isset($aliases[$fileSlug])) {
        $names = array_merge($names, $aliases[$fileSlug]);
    }

    $st = $pdo->query('SELECT DISTINCT brand FROM hh_handhelds WHERE brand <> ""');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $brand = trim((string) $row['brand']);
        if ($brand === '') {
            continue;
        }
        $norm = strtolower(preg_replace('/[^a-z0-9]+/i', '', $brand));
        $slugNorm = strtolower(preg_replace('/[^a-z0-9]+/i', '', $fileSlug));
        if ($norm !== '' && ($norm === $slugNorm || strpos($norm, $slugNorm) === 0 || strpos($slugNorm, $norm) === 0)) {
            $names[] = $brand;
        }
    }

    if ($names === array()) {
        $names[] = ucwords(str_replace(array('-', '_'), ' ', $fileSlug));
    }

    return array_values(array_unique($names));
}

function hh_brand_sync_catalog(PDO $pdo)
{
    require_once __DIR__ . '/handheld_repo.php';

    $first = hh_scraper_fetch_list(1);
    if (!$first['ok']) {
        throw new RuntimeException('无法获取掌机圈列表页：' . ($first['error'] ?? 'HTTP error'));
    }

    $logoUrls = array();
    foreach (hh_scraper_extract_brand_logo_urls($first['body']) as $url) {
        $logoUrls[$url] = true;
    }

    // Nav dropdown (name + logo)
    foreach (hh_scraper_parse_brand_catalog($first['body']) as $row) {
        hh_brand_upsert($pdo, $row['name'], $row['logo_url']);
        $logoUrls[$row['logo_url']] = true;
    }

    // All list pages: slug => logo, matched to DB brand names
    $slugLogos = hh_scraper_fetch_all_list_brand_logos();
    $st = $pdo->query('SELECT slug, brand FROM hh_handhelds WHERE brand <> ""');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $slug = (string) $row['slug'];
        $brand = trim((string) $row['brand']);
        if ($brand === '' || !isset($slugLogos[$slug])) {
            continue;
        }
        $logoUrl = $slugLogos[$slug];
        hh_brand_upsert($pdo, $brand, $logoUrl);
        $logoUrls[$logoUrl] = true;
    }

    // Re-fetch all list pages HTML for any logos missed
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
        foreach (hh_scraper_extract_brand_logo_urls($html) as $url) {
            $logoUrls[$url] = true;
        }
    }

    // "All brands" search page often lists every brand logo
    $allUrl = hh_scraper_base_url() . '/handhelds-search?query=1_0-2_0-3_0-4_0-5_0-6_0-7_0-8_0';
    $allRes = hh_http_get($allUrl);
    if ($allRes['ok']) {
        foreach (hh_scraper_extract_brand_logo_urls($allRes['body']) as $url) {
            $logoUrls[$url] = true;
        }
    }

    // Download remaining logos and map to brand names
    foreach (array_keys($logoUrls) as $logoUrl) {
        $fileSlug = hh_brand_slug_from_logo_url($logoUrl);
        if ($fileSlug === '') {
            continue;
        }
        foreach (hh_brand_names_for_logo_slug($pdo, $fileSlug, $logoUrl) as $name) {
            hh_brand_upsert($pdo, $name, $logoUrl);
        }
        usleep(150000);
    }

    $st = $pdo->query('SELECT COUNT(*) AS c FROM hh_brands WHERE logo_path <> ""');
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) $row['c'] : 0;
}
