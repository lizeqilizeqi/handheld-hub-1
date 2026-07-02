<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_client.php';
require_once __DIR__ . '/handheld_repo.php';

function hh_blogger_oauth_authorize_url()
{
    $clientId = (string) hh_config_get('blogger.client_id', '');
    $redirect = (string) hh_config_get('blogger.redirect_uri', '');
    if ($clientId === '' || $redirect === '') {
        throw new RuntimeException('Blogger OAuth client_id / redirect_uri not configured');
    }
    $params = array(
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope' => hh_blogger_oauth_scopes(),
        'access_type' => 'offline',
        'prompt' => 'consent',
    );
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function hh_blogger_exchange_code(PDO $pdo, $code)
{
    $clientId = (string) hh_config_get('blogger.client_id', '');
    $clientSecret = (string) hh_config_get('blogger.client_secret', '');
    $redirect = (string) hh_config_get('blogger.redirect_uri', '');
    $res = hh_http_post_form('https://oauth2.googleapis.com/token', array(
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirect,
        'grant_type' => 'authorization_code',
    ));
    if (!$res['ok']) {
        throw new RuntimeException('OAuth token exchange failed: ' . mb_substr($res['body'], 0, 500));
    }
    $json = json_decode($res['body'], true);
    if (!is_array($json) || empty($json['refresh_token'])) {
        throw new RuntimeException('No refresh_token returned. Revoke app access and authorize again with prompt=consent.');
    }
    $pdo->prepare(
        'INSERT INTO hh_blogger_oauth (id, refresh_token, access_token, token_expires_at) VALUES (1, ?, ?, ?)
         ON DUPLICATE KEY UPDATE refresh_token=VALUES(refresh_token), access_token=VALUES(access_token),
         token_expires_at=VALUES(token_expires_at), updated_at=NOW()'
    )->execute(array(
        (string) $json['refresh_token'],
        isset($json['access_token']) ? (string) $json['access_token'] : null,
        isset($json['expires_in']) ? date('Y-m-d H:i:s', time() + (int) $json['expires_in']) : null,
    ));
    return true;
}

function hh_blogger_get_access_token(PDO $pdo)
{
    $st = $pdo->query('SELECT refresh_token, access_token, token_expires_at FROM hh_blogger_oauth WHERE id = 1 LIMIT 1');
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['refresh_token'])) {
        throw new RuntimeException('Blogger not authorized. Complete OAuth first.');
    }
    $expires = !empty($row['token_expires_at']) ? strtotime((string) $row['token_expires_at']) : 0;
    if (!empty($row['access_token']) && $expires > time() + 60) {
        return (string) $row['access_token'];
    }

    $res = hh_http_post_form('https://oauth2.googleapis.com/token', array(
        'client_id' => (string) hh_config_get('blogger.client_id', ''),
        'client_secret' => (string) hh_config_get('blogger.client_secret', ''),
        'refresh_token' => (string) $row['refresh_token'],
        'grant_type' => 'refresh_token',
    ));
    if (!$res['ok']) {
        throw new RuntimeException('Token refresh failed: ' . mb_substr($res['body'], 0, 500));
    }
    $json = json_decode($res['body'], true);
    if (!is_array($json) || empty($json['access_token'])) {
        throw new RuntimeException('Invalid refresh response');
    }
    $pdo->prepare('UPDATE hh_blogger_oauth SET access_token=?, token_expires_at=? WHERE id=1')->execute(array(
        (string) $json['access_token'],
        isset($json['expires_in']) ? date('Y-m-d H:i:s', time() + (int) $json['expires_in']) : null,
    ));
    return (string) $json['access_token'];
}

function hh_blogger_is_connected(PDO $pdo)
{
    $st = $pdo->query('SELECT refresh_token FROM hh_blogger_oauth WHERE id = 1 LIMIT 1');
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row && !empty($row['refresh_token']);
}

function hh_blogger_build_post_html(PDO $pdo, $handheld, $content, $specs, $images, $locale = 'en', array &$urlCache = array())
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    $title = $content['title'] ?? ($locale === 'en' ? ($handheld['name_en'] ?: $handheld['name_zh']) : $handheld['name_zh']);
    $urlCache = array();
    $coverImg = hh_handheld_cover_image_from_list($images);
    $cover = '';
    if ($coverImg && !empty($coverImg['path'])) {
        $coverSrc = hh_blogger_publish_image_url($pdo, $coverImg, $urlCache);
        if ($coverSrc !== '') {
            $cover = '<p><img src="' . hh_h($coverSrc) . '" alt="' . hh_h($title) . '" style="max-width:100%;height:auto;"></p>';
        }
    }
    $specHtml = hh_specs_table_html($specs, $locale);
    $siteUrl = hh_public_url($locale . '/handheld/' . rawurlencode($handheld['slug']));
    if ($locale === 'zh') {
        $footer = '<hr><p><em>完整参数与更新请见独立站：<a href="' . hh_h($siteUrl) . '">' . hh_h($siteUrl) . '</a></em></p>';
    } else {
        $footer = '<hr><p><em>Full specs and updates on our site: <a href="' . hh_h($siteUrl) . '">' . hh_h($siteUrl) . '</a></em></p>';
    }
    $bodyHtml = hh_blogger_prepare_body_html(isset($content['body_html']) ? (string) $content['body_html'] : '');
    return $cover . $bodyHtml . $specHtml . $footer;
}

function hh_blogger_scrape_source_hosts()
{
    static $hosts = null;
    if ($hosts !== null) {
        return $hosts;
    }
    $hosts = array('zhangjiquan.com', 'upload.zhangjiquan.com', 'www.zhangjiquan.com');
    $base = (string) hh_config_get('scraper.base_url', '');
    if ($base !== '') {
        $h = strtolower((string) parse_url($base, PHP_URL_HOST));
        if ($h !== '') {
            $hosts[] = $h;
            if (strpos($h, 'www.') === 0) {
                $hosts[] = substr($h, 4);
            }
        }
    }
    return array_values(array_unique(array_filter($hosts)));
}

function hh_blogger_url_host($url)
{
    return strtolower((string) parse_url((string) $url, PHP_URL_HOST));
}

function hh_blogger_is_scrape_source_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return false;
    }
    if (strpos($url, '/storage/handhelds') !== false) {
        return false;
    }
    $host = hh_blogger_url_host($url);
    if ($host === '') {
        return strpos($url, 'zhangjiquan') !== false;
    }
    foreach (hh_blogger_scrape_source_hosts() as $blocked) {
        if ($host === $blocked || substr($host, -strlen('.' . $blocked)) === '.' . $blocked) {
            return true;
        }
    }
    return false;
}

function hh_blogger_is_localhost_url($url)
{
    $host = hh_blogger_url_host($url);
    return in_array($host, array('localhost', '127.0.0.1', '::1'), true);
}

function hh_blogger_can_use_own_site_image_urls()
{
    $base = (string) hh_base_url();
    if (!preg_match('#^https://#i', $base)) {
        return false;
    }
    return !hh_blogger_is_localhost_url($base);
}

function hh_blogger_image_fs_path($path)
{
    $path = ltrim(str_replace('\\', '/', (string) $path), '/');
    if (strpos($path, 'storage/handhelds/') === 0) {
        return hh_root_dir() . '/' . $path;
    }
    return rtrim(hh_storage_fs(), '/\\') . '/' . $path;
}

function hh_blogger_image_mime($fsPath)
{
    $ext = strtolower(pathinfo($fsPath, PATHINFO_EXTENSION));
    $map = array(
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    );
    if (isset($map[$ext])) {
        return $map[$ext];
    }
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($fsPath);
        if (is_string($mime) && $mime !== '') {
            return $mime;
        }
    }
    return 'image/jpeg';
}

function hh_blogger_oauth_scopes()
{
    return implode(' ', array(
        'https://www.googleapis.com/auth/blogger',
        'https://www.googleapis.com/auth/drive.file',
    ));
}

function hh_blogger_drive_api(PDO $pdo, $method, $url, $body = null, array $headers = array())
{
    $token = hh_blogger_get_access_token($pdo);
    $ch = curl_init($url);
    $hdr = array_merge(array('Authorization: Bearer ' . $token), $headers);
    $method = strtoupper($method);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $hdr,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
    );
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
    } else {
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
    }
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $respBody = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($respBody === false) {
        return array('ok' => false, 'http_code' => $code, 'body' => '', 'error' => $curlErr);
    }
    return array(
        'ok' => $code >= 200 && $code < 300,
        'http_code' => $code,
        'body' => (string) $respBody,
        'error' => $curlErr,
    );
}

function hh_blogger_drive_folder_id(PDO $pdo)
{
    static $cached = null;
    if ($cached !== null && $cached !== '') {
        return $cached;
    }

    $folderName = 'Handheld Hub';
    $q = rawurlencode("name='Handheld Hub' and mimeType='application/vnd.google-apps.folder' and trashed=false");
    $list = hh_blogger_drive_api(
        $pdo,
        'GET',
        'https://www.googleapis.com/drive/v3/files?q=' . $q . '&fields=files(id)&spaces=drive&pageSize=1'
    );
    if (!$list['ok']) {
        $msg = hh_blogger_drive_error_message($list);
        throw new RuntimeException('Google Drive 文件夹查询失败：' . $msg);
    }
    $json = json_decode($list['body'], true);
    if (!empty($json['files'][0]['id'])) {
        $cached = (string) $json['files'][0]['id'];
        return $cached;
    }

    $create = hh_blogger_drive_api(
        $pdo,
        'POST',
        'https://www.googleapis.com/drive/v3/files?fields=id',
        json_encode(array(
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ), JSON_UNESCAPED_UNICODE),
        array('Content-Type: application/json')
    );
    if (!$create['ok'] || empty(json_decode($create['body'], true)['id'])) {
        $msg = hh_blogger_drive_error_message($create);
        throw new RuntimeException('Google Drive 文件夹创建失败：' . $msg);
    }
    $cached = (string) json_decode($create['body'], true)['id'];
    return $cached;
}

function hh_blogger_drive_error_message(array $res)
{
    $json = json_decode($res['body'] ?? '', true);
    $detail = '';
    if (is_array($json)) {
        if (!empty($json['error']['message'])) {
            $detail = (string) $json['error']['message'];
        }
        if (!empty($json['error']['errors'][0]['reason'])) {
            $detail .= ($detail !== '' ? ' ' : '') . '(' . $json['error']['errors'][0]['reason'] . ')';
        }
    }
    if ($detail === '') {
        $detail = 'HTTP ' . (int) ($res['http_code'] ?? 0);
        if (!empty($res['error'])) {
            $detail .= ' (' . (string) $res['error'] . ')';
        }
        if (!empty($res['body'])) {
            $detail .= ': ' . mb_substr((string) $res['body'], 0, 200);
        }
    }
    if ((int) ($res['http_code'] ?? 0) === 0) {
        $detail .= '。多为 Docker 容器无法访问 Google API，或 OAuth 尚未重新授权 Drive。';
    }
    if ((int) ($res['http_code'] ?? 0) === 403 && stripos($detail, 'insufficient') !== false) {
        $detail .= '。请在后台 Blogger 页面点击「重新连接 Google」以授权 Google Drive 图片上传。';
    }
    return $detail;
}

function hh_blogger_drive_embed_url($fileId, $maxWidth = 1600)
{
    $fileId = trim((string) $fileId);
    if ($fileId === '') {
        return '';
    }
    $size = max(400, min(4096, (int) $maxWidth));
    return 'https://lh3.googleusercontent.com/d/' . rawurlencode($fileId) . '=s' . $size;
}

/**
 * Convert webp to jpeg for broader embed support (Blogger, thumbnails).
 *
 * @return array{path:string,mime:string,name:string,temp:bool}
 */
function hh_blogger_prepare_image_for_drive($fsPath)
{
    $base = array(
        'path' => $fsPath,
        'mime' => hh_blogger_image_mime($fsPath),
        'name' => basename($fsPath),
        'temp' => false,
    );
    $ext = strtolower(pathinfo($fsPath, PATHINFO_EXTENSION));
    if ($ext !== 'webp' || !function_exists('imagecreatefromwebp') || !function_exists('imagejpeg')) {
        return $base;
    }
    $img = @imagecreatefromwebp($fsPath);
    if ($img === false) {
        return $base;
    }
    $tmp = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'hh-drive-' . bin2hex(random_bytes(8)) . '.jpg';
    if (!@imagejpeg($img, $tmp, 90)) {
        imagedestroy($img);
        return $base;
    }
    imagedestroy($img);
    return array(
        'path' => $tmp,
        'mime' => 'image/jpeg',
        'name' => pathinfo($fsPath, PATHINFO_FILENAME) . '.jpg',
        'temp' => true,
    );
}

/**
 * Upload local image via Google Drive (public link). Google disabled third-party upload-image.g.
 *
 * @return string|null public HTTPS URL on success
 */
function hh_blogger_upload_local_image_to_google(PDO $pdo, $fsPath)
{
    if (!is_file($fsPath) || !is_readable($fsPath)) {
        $GLOBALS['hh_blogger_last_upload_error'] = 'local file missing or unreadable';
        return null;
    }

    try {
        $folderId = hh_blogger_drive_folder_id($pdo);
    } catch (Throwable $e) {
        $GLOBALS['hh_blogger_last_upload_error'] = $e->getMessage();
        return null;
    }

    $prepared = hh_blogger_prepare_image_for_drive($fsPath);
    $uploadPath = $prepared['path'];
    $mime = $prepared['mime'];
    $fileName = $prepared['name'];
    $boundary = 'hh_' . bin2hex(random_bytes(12));
    $metadata = json_encode(array(
        'name' => $fileName,
        'parents' => array($folderId),
    ), JSON_UNESCAPED_UNICODE);
    $binary = file_get_contents($uploadPath);
    if ($binary === false || $binary === '') {
        if (!empty($prepared['temp'])) {
            @unlink($uploadPath);
        }
        $GLOBALS['hh_blogger_last_upload_error'] = 'cannot read local image';
        return null;
    }

    $multipart = "--{$boundary}\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . $metadata . "\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: {$mime}\r\n\r\n"
        . $binary . "\r\n"
        . "--{$boundary}--";

    $upload = hh_blogger_drive_api(
        $pdo,
        'POST',
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id',
        $multipart,
        array('Content-Type: multipart/related; boundary=' . $boundary)
    );
    if (!empty($prepared['temp'])) {
        @unlink($uploadPath);
    }
    if (!$upload['ok']) {
        $GLOBALS['hh_blogger_last_upload_error'] = hh_blogger_drive_error_message($upload);
        return null;
    }
    $fileJson = json_decode($upload['body'], true);
    $fileId = isset($fileJson['id']) ? (string) $fileJson['id'] : '';
    if ($fileId === '') {
        $GLOBALS['hh_blogger_last_upload_error'] = 'Drive upload returned no file id';
        return null;
    }

    $perm = hh_blogger_drive_api(
        $pdo,
        'POST',
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '/permissions',
        json_encode(array('role' => 'reader', 'type' => 'anyone'), JSON_UNESCAPED_UNICODE),
        array('Content-Type: application/json')
    );
    if (!$perm['ok']) {
        $GLOBALS['hh_blogger_last_upload_error'] = 'Drive permission failed: ' . hh_blogger_drive_error_message($perm);
        return null;
    }

    return hh_blogger_drive_embed_url($fileId);
}

function hh_blogger_parse_uploaded_image_url($body)
{
    $body = trim($body);
    if ($body === '') {
        return null;
    }
    $json = json_decode($body, true);
    if (is_array($json)) {
        foreach (array('url', 'imageUrl', 'image_url') as $k) {
            if (!empty($json[$k]) && preg_match('#^https://#i', (string) $json[$k])) {
                return (string) $json[$k];
            }
        }
    }
    if (preg_match('#https?://(?:[\d]+\.)?bp\.blogspot\.com/[^\s"\'<>]+#i', $body, $m)) {
        return $m[0];
    }
    if (preg_match('#https?://(?:lh\d+\.)?googleusercontent\.com/[^\s"\'<>]+#i', $body, $m)) {
        return $m[0];
    }
    if (preg_match('#^https://#i', $body)) {
        return $body;
    }
    return null;
}

/**
 * Resolve image URL for Blogger: local file → Google upload → (optional) our public site. Never scrape source CDN.
 */
function hh_blogger_publish_image_url(PDO $pdo, $imageRow, array &$urlCache = array())
{
    if (!$imageRow || empty($imageRow['path'])) {
        return '';
    }
    $path = (string) $imageRow['path'];
    if (isset($urlCache[$path])) {
        return $urlCache[$path];
    }
    $fs = hh_blogger_image_fs_path($path);
    if (!is_file($fs) || !is_readable($fs)) {
        throw new RuntimeException('Blogger 发布缺少本地图片文件：' . $path . '（请重新抓取以下载到 storage/handhelds）');
    }

    $googleUrl = hh_blogger_upload_local_image_to_google($pdo, $fs);
    if ($googleUrl !== null && $googleUrl !== '') {
        $urlCache[$path] = $googleUrl;
        return $googleUrl;
    }

    if (hh_blogger_can_use_own_site_image_urls()) {
        $own = hh_image_public_url($path);
        $urlCache[$path] = $own;
        return $own;
    }

    $detail = isset($GLOBALS['hh_blogger_last_upload_error']) ? (string) $GLOBALS['hh_blogger_last_upload_error'] : '';
    throw new RuntimeException(
        '无法将封面图上传到 Google（Blogger 不支持 base64 内嵌）。'
        . ($detail !== '' ? ' ' . $detail : '')
        . ' 请在后台点击「重新连接 Google」授权 Drive 后重试；生产环境也可配置公网 HTTPS 的 app.base_url。'
    );
}

/** Strip scrape-source images/links from body; Blogger uses cover uploaded separately. */
function hh_blogger_prepare_body_html($html)
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML(
        '<?xml encoding="UTF-8"><div id="hh-blogger-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    $xpath = new DOMXPath($doc);

    $imgs = $xpath->query('//img');
    if ($imgs) {
        for ($i = $imgs->length - 1; $i >= 0; $i--) {
            $img = $imgs->item($i);
            if ($img && $img->parentNode) {
                $img->parentNode->removeChild($img);
            }
        }
    }

    $links = $xpath->query('//a[@href]');
    if ($links) {
        foreach ($links as $a) {
            $href = trim($a->getAttribute('href'));
            if (hh_blogger_is_scrape_source_url($href)) {
                $a->removeAttribute('href');
                $a->removeAttribute('target');
                $a->removeAttribute('rel');
            }
        }
    }

    $root = $doc->getElementById('hh-blogger-root');
    if (!$root) {
        return preg_replace('/<img\b[^>]*>/i', '', $html);
    }
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

function hh_handheld_cover_image_from_list($images)
{
    foreach ($images as $img) {
        if (!empty($img['is_cover'])) {
            return $img;
        }
    }
    return count($images) > 0 ? $images[0] : null;
}

function hh_blogger_validate_content_for_publish(PDO $pdo, $handheldId, $locale)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    $content = hh_handheld_content($pdo, (int) $handheldId, $locale);
    if (!$content || trim((string) ($content['body_html'] ?? '')) === '') {
        throw new RuntimeException('缺少 ' . $locale . ' 正文，请先编辑或翻译');
    }
    if ($locale === 'zh') {
        return;
    }
    $h = hh_handheld_by_id($pdo, (int) $handheldId);
    if (!$h || ($h['status'] ?? '') !== 'published') {
        throw new RuntimeException('请先发布到独立站（独立站已发布即视为英文可发 Blogger）');
    }
}

function hh_blogger_publish_locales(PDO $pdo, $handheldId, $locales, $options = array())
{
    $results = array();
    foreach ($locales as $locale) {
        $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
        hh_blogger_validate_content_for_publish($pdo, $handheldId, $locale);
        $opts = $options;
        if (!empty($opts['labels']) && is_array($opts['labels'])) {
            $opts['labels'][] = $locale === 'zh' ? 'chinese' : 'english';
        }
        $results[$locale] = hh_blogger_create_or_update_post($pdo, $handheldId, $locale, $opts);
    }
    hh_blogger_try_mark_handheld_published($pdo, (int) $handheldId);
    return $results;
}

function hh_blogger_create_or_update_post(PDO $pdo, $handheldId, $locale = 'en', $options = array())
{
    $h = hh_handheld_by_id($pdo, $handheldId);
    if (!$h) {
        throw new RuntimeException('Handheld not found');
    }
    $content = hh_handheld_content($pdo, $handheldId, $locale);
    if (!$content) {
        throw new RuntimeException('No ' . $locale . ' content');
    }
    $specs = hh_handheld_specs($pdo, $handheldId, $locale);
    $images = hh_handheld_images($pdo, $handheldId);
    $blogId = (string) hh_config_get('blogger.blog_id', '');
    if ($blogId === '') {
        throw new RuntimeException('blogger.blog_id not configured');
    }

    $token = hh_blogger_get_access_token($pdo);
    $urlCache = array();
    $html = hh_blogger_build_post_html($pdo, $h, $content, $specs, $images, $locale, $urlCache);
    $title = $content['title'] ?: ($locale === 'zh' ? ($h['name_zh'] ?: $h['slug']) : ($h['name_en'] ?: $h['name_zh']));

    $payload = array(
        'kind' => 'blogger#post',
        'blog' => array('id' => $blogId),
        'title' => $title,
        'content' => $html,
    );
    $coverImg = hh_handheld_cover_image_from_list($images);
    if ($coverImg && !empty($coverImg['path']) && !empty($urlCache[(string) $coverImg['path']])) {
        $payload['images'] = array(array('url' => (string) $urlCache[(string) $coverImg['path']]));
    }
    if (!empty($options['labels']) && is_array($options['labels'])) {
        $payload['labels'] = array_values($options['labels']);
    }

    $existing = hh_blogger_post_row($pdo, $handheldId, $locale);
    $isDraft = !empty($options['draft']);
    if ($isDraft) {
        $payload['status'] = 'draft';
    }

    if ($existing && !empty($existing['blogger_post_id'])) {
        $url = 'https://www.googleapis.com/blogger/v3/blogs/' . rawurlencode($blogId) . '/posts/' . rawurlencode($existing['blogger_post_id']);
        $method = 'PUT';
    } else {
        $url = 'https://www.googleapis.com/blogger/v3/blogs/' . rawurlencode($blogId) . '/posts/';
        if (!$isDraft) {
            $url .= '?isDraft=false';
        }
        $method = 'POST';
    }

    $json = hh_blogger_api_request($method, $url, $token, $payload);

    if (!$json && $existing && !empty($existing['blogger_post_id']) && $method === 'PUT') {
        hh_blogger_post_save($pdo, $handheldId, $locale, array(
            'blogger_post_id' => '',
            'blogger_url' => '',
            'sync_status' => 'draft',
            'last_error' => null,
        ));
        $url = 'https://www.googleapis.com/blogger/v3/blogs/' . rawurlencode($blogId) . '/posts/';
        if (!$isDraft) {
            $url .= '?isDraft=false';
        }
        $json = hh_blogger_api_request('POST', $url, $token, $payload);
    }

    if (!is_array($json) || empty($json['id'])) {
        throw new RuntimeException('Invalid Blogger response');
    }

    if (!$isDraft) {
        $apiStatus = isset($json['status']) ? (string) $json['status'] : '';
        if (in_array($apiStatus, array('DRAFT', 'SOFT_TRASHED'), true)) {
            $published = hh_blogger_publish_post($pdo, (string) $json['id'], null);
            if (is_array($published) && !empty($published['id'])) {
                $json = $published;
            }
        }
    }

    $apiStatus = isset($json['status']) ? (string) $json['status'] : '';
    if (!$isDraft && $apiStatus !== 'LIVE' && $apiStatus !== 'SCHEDULED') {
        hh_blogger_post_save($pdo, $handheldId, $locale, array(
            'blogger_post_id' => (string) $json['id'],
            'blogger_url' => isset($json['url']) ? (string) $json['url'] : '',
            'sync_status' => 'error',
            'last_error' => 'Blogger status=' . $apiStatus . '（未公开可见，可能在回收站）',
        ));
        throw new RuntimeException('Blogger 文章未成功公开（status=' . $apiStatus . '），请检查 Blogger 后台回收站');
    }

    $syncStatus = $isDraft ? 'draft' : ($apiStatus === 'SCHEDULED' ? 'scheduled' : 'published');
    if (!empty($options['scheduled_at']) && !$isDraft) {
        $syncStatus = 'scheduled';
    }

    hh_blogger_post_save($pdo, $handheldId, $locale, array(
        'blogger_post_id' => (string) $json['id'],
        'blogger_url' => isset($json['url']) ? (string) $json['url'] : '',
        'sync_status' => $syncStatus,
        'scheduled_at' => !empty($options['scheduled_at']) ? $options['scheduled_at'] : null,
        'published_at' => ($syncStatus === 'published') ? date('Y-m-d H:i:s') : null,
        'last_error' => null,
    ));

    if (!empty($options['scheduled_at']) && !empty($json['id'])) {
        hh_blogger_publish_post($pdo, (string) $json['id'], (string) $options['scheduled_at']);
    }

    return $json;
}

/**
 * @return array|null decoded JSON on 2xx, null on 404
 */
function hh_blogger_api_request($method, $url, $token, array $payload)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ),
        CURLOPT_TIMEOUT => 120,
    ));
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Blogger API request failed');
    }
    if ($code === 404) {
        return null;
    }
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException('Blogger API HTTP ' . $code . ': ' . mb_substr((string) $body, 0, 500));
    }
    $json = json_decode((string) $body, true);
    return is_array($json) ? $json : null;
}

function hh_blogger_publish_post(PDO $pdo, $postId, $publishDate = null)
{
    $blogId = (string) hh_config_get('blogger.blog_id', '');
    $token = hh_blogger_get_access_token($pdo);
    $url = 'https://www.googleapis.com/blogger/v3/blogs/' . rawurlencode($blogId) . '/posts/' . rawurlencode($postId) . '/publish';
    if ($publishDate) {
        $url .= '?publishDate=' . rawurlencode($publishDate);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $token),
        CURLOPT_TIMEOUT => 60,
    ));
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) {
        throw new RuntimeException('Publish failed HTTP ' . $code);
    }
    return json_decode((string) $body, true);
}

function hh_blogger_list_user_blogs(PDO $pdo)
{
    $token = hh_blogger_get_access_token($pdo);
    $url = 'https://www.googleapis.com/blogger/v3/users/self/blogs';
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $token),
    ));
    $body = curl_exec($ch);
    curl_close($ch);
    $json = json_decode((string) $body, true);
    return is_array($json) && !empty($json['items']) ? $json['items'] : array();
}

function hh_blogger_ready_where_sql()
{
    return array(
        "h.status = 'published'",
        "TRIM(COALESCE(cz.body_html, '')) <> ''",
        "TRIM(COALESCE(ce.body_html, '')) <> ''",
    );
}

function hh_blogger_list_where_sql($bloggerFilter = 'all')
{
    $where = hh_blogger_ready_where_sql();
    if ($bloggerFilter === 'pending') {
        $where[] = "h.blogger_mark = 'none'";
    } elseif ($bloggerFilter === 'published') {
        $where[] = "h.blogger_mark = 'published'";
    }
    return $where;
}

function hh_blogger_ready_handheld_ids(PDO $pdo, $bloggerFilter = 'all')
{
    $where = hh_blogger_list_where_sql($bloggerFilter);
    $sql = 'SELECT h.id
        FROM hh_handhelds h
        INNER JOIN hh_handheld_content cz ON cz.handheld_id = h.id AND cz.locale = \'zh\'
        INNER JOIN hh_handheld_content ce ON ce.handheld_id = h.id AND ce.locale = \'en\'
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY h.release_date DESC, h.id DESC';
    $st = $pdo->query($sql);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function hh_blogger_ready_count(PDO $pdo)
{
    $where = hh_blogger_ready_where_sql();
    $sql = 'SELECT COUNT(*)
        FROM hh_handhelds h
        INNER JOIN hh_handheld_content cz ON cz.handheld_id = h.id AND cz.locale = \'zh\'
        INNER JOIN hh_handheld_content ce ON ce.handheld_id = h.id AND ce.locale = \'en\'
        WHERE ' . implode(' AND ', $where);
    return (int) $pdo->query($sql)->fetchColumn();
}

function hh_blogger_pending_count(PDO $pdo)
{
    $where = hh_blogger_list_where_sql('pending');
    $sql = 'SELECT COUNT(*)
        FROM hh_handhelds h
        INNER JOIN hh_handheld_content cz ON cz.handheld_id = h.id AND cz.locale = \'zh\'
        INNER JOIN hh_handheld_content ce ON ce.handheld_id = h.id AND ce.locale = \'en\'
        WHERE ' . implode(' AND ', $where);
    return (int) $pdo->query($sql)->fetchColumn();
}

function hh_blogger_published_mark_count(PDO $pdo)
{
    $where = hh_blogger_list_where_sql('published');
    $sql = 'SELECT COUNT(*)
        FROM hh_handhelds h
        INNER JOIN hh_handheld_content cz ON cz.handheld_id = h.id AND cz.locale = \'zh\'
        INNER JOIN hh_handheld_content ce ON ce.handheld_id = h.id AND ce.locale = \'en\'
        WHERE ' . implode(' AND ', $where);
    return (int) $pdo->query($sql)->fetchColumn();
}

function hh_blogger_mark_label($mark)
{
    return ($mark ?? '') === 'published' ? '已 Blogger 发布' : '未 Blogger 发布';
}

function hh_blogger_mark_published(PDO $pdo, $handheldId)
{
    $pdo->prepare('UPDATE hh_handhelds SET blogger_mark = ? WHERE id = ?')->execute(array('published', (int) $handheldId));
}

function hh_blogger_mark_reset(PDO $pdo, $handheldId)
{
    $pdo->prepare('UPDATE hh_handhelds SET blogger_mark = ? WHERE id = ?')->execute(array('none', (int) $handheldId));
}

function hh_blogger_try_mark_handheld_published(PDO $pdo, $handheldId)
{
    $bpZ = hh_blogger_post_row($pdo, (int) $handheldId, 'zh');
    $bpE = hh_blogger_post_row($pdo, (int) $handheldId, 'en');
    if ($bpZ && ($bpZ['sync_status'] ?? '') === 'published' && $bpE && ($bpE['sync_status'] ?? '') === 'published') {
        hh_blogger_mark_published($pdo, (int) $handheldId);
    }
}

/**
 * @return array{ok:int,fail:int,errors:array<int,string>}
 */
function hh_blogger_publish_batch(PDO $pdo, array $ids, $options = array())
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
        return $v > 0;
    })));
    $ok = 0;
    $fail = 0;
    $errors = array();

    foreach ($ids as $id) {
        try {
            hh_blogger_publish_locales($pdo, $id, array('zh', 'en'), $options);
            $ok++;
        } catch (Throwable $e) {
            $fail++;
            $errors[$id] = $e->getMessage();
        }
        usleep(400000);
    }

    return array('ok' => $ok, 'fail' => $fail, 'errors' => $errors);
}
