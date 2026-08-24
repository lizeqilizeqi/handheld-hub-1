<?php

require_once __DIR__ . '/bootstrap.php';

function hh_news_editor_storage_rel($filename)
{
    return 'news/' . date('Y/m') . '/' . $filename;
}

function hh_news_editor_public_url($relativePath)
{
    $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
    return rtrim(hh_storage_web(), '/') . '/' . $relativePath;
}

function hh_news_editor_save_binary($binary, $ext)
{
    $ext = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $ext));
    if (!in_array($ext, array('jpg', 'jpeg', 'png', 'webp', 'gif'), true)) {
        throw new RuntimeException('不支持的图片格式');
    }
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $rel = hh_news_editor_storage_rel($name);
    $fs = rtrim(hh_storage_fs(), '/\\') . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $dir = dirname($fs);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('无法创建图片目录');
    }
    if (file_put_contents($fs, $binary) === false) {
        throw new RuntimeException('图片保存失败');
    }
    return $rel;
}

function hh_news_editor_upload_from_request(array $file)
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('未收到上传文件');
    }
    if (!empty($file['error'])) {
        throw new RuntimeException('上传错误 ' . (int) $file['error']);
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info) {
        throw new RuntimeException('不是有效图片');
    }
    $map = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif');
    $ext = isset($map[$info[2]]) ? $map[$info[2]] : 'jpg';
    $binary = file_get_contents($file['tmp_name']);
    if ($binary === false || strlen($binary) > 8 * 1024 * 1024) {
        throw new RuntimeException('图片过大（最大 8MB）');
    }
    $rel = hh_news_editor_save_binary($binary, $ext);
    return hh_news_editor_public_url($rel);
}

function hh_news_editor_upload_base64($dataUrl)
{
    if (!preg_match('#^data:image/(png|jpe?g|webp|gif);base64,(.+)$#i', (string) $dataUrl, $m)) {
        throw new RuntimeException('无效的 base64 图片');
    }
    $ext = strtolower($m[1]);
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    $binary = base64_decode($m[2], true);
    if ($binary === false || strlen($binary) > 8 * 1024 * 1024) {
        throw new RuntimeException('图片过大或无效');
    }
    $rel = hh_news_editor_save_binary($binary, $ext);
    return hh_news_editor_public_url($rel);
}

function hh_news_editor_is_local_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return false;
    }
    $prefix = rtrim(hh_storage_web(), '/');
    if (strpos($url, $prefix . '/') === 0) {
        return true;
    }
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
    if ($host !== '' && preg_match('#^https?://' . preg_quote($host, '#') . preg_quote($prefix, '#') . '/#i', $url)) {
        return true;
    }
    return false;
}

function hh_news_editor_local_rel_from_url($url)
{
    $prefix = rtrim(hh_storage_web(), '/');
    if (strpos($url, $prefix . '/') === 0) {
        return ltrim(substr($url, strlen($prefix)), '/');
    }
    return '';
}

function hh_news_editor_fetch_remote_image($url)
{
    $url = trim((string) $url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return '';
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'HandheldHubNewsEditor/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ));
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400 || strlen($body) > 8 * 1024 * 1024) {
        return '';
    }
    $info = @getimagesizefromstring($body);
    if (!$info) {
        return '';
    }
    $map = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif');
    $ext = isset($map[$info[2]]) ? $map[$info[2]] : 'jpg';
    try {
        $rel = hh_news_editor_save_binary($body, $ext);
        return hh_news_editor_public_url($rel);
    } catch (Throwable $e) {
        return '';
    }
}

function hh_news_editor_rehost_images($html)
{
    $html = (string) $html;
    if ($html === '' || stripos($html, '<img') === false) {
        return $html;
    }
    return preg_replace_callback('#<img\b[^>]*\ssrc=(["\'])([^"\']+)\1#i', function ($m) {
        $src = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (hh_news_editor_is_local_url($src)) {
            return $m[0];
        }
        if (stripos($src, 'data:image/') === 0) {
            try {
                $local = hh_news_editor_upload_base64($src);
                return str_replace($m[2], $local, $m[0]);
            } catch (Throwable $e) {
                return '';
            }
        }
        $local = hh_news_editor_fetch_remote_image($src);
        if ($local === '') {
            return '';
        }
        return str_replace($m[2], $local, $m[0]);
    }, $html);
}

function hh_news_editor_sanitize_html($html)
{
    $html = (string) $html;
    if (trim($html) === '') {
        return '';
    }
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!@$dom->loadHTML('<?xml encoding="utf-8"><div id="hh-news-root">' . $html . '</div>')) {
        return '';
    }
    $xpath = new DOMXPath($dom);
    $root = $xpath->query('//*[@id="hh-news-root"]')->item(0);
    if (!$root) {
        return '';
    }
    $allowed = array(
        'p' => true, 'br' => true, 'strong' => true, 'b' => true, 'em' => true, 'i' => true,
        'u' => true, 'h2' => true, 'h3' => true, 'ul' => true, 'ol' => true, 'li' => true,
        'blockquote' => true, 'a' => true, 'img' => true, 'span' => true,
    );

    $walker = function (DOMNode $node) use (&$walker, $dom, $allowed) {
        if ($node->nodeType === XML_TEXT_NODE) {
            return;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }
        /** @var DOMElement $el */
        $el = $node;
        $tag = strtolower($el->tagName);
        if (!isset($allowed[$tag])) {
            while ($el->firstChild) {
                $el->parentNode->insertBefore($el->firstChild, $el);
            }
            $el->parentNode->removeChild($el);
            return;
        }
        if ($tag === 'img') {
            $src = trim($el->getAttribute('src'));
            while ($el->attributes->length) {
                $el->removeAttribute($el->attributes->item(0)->name);
            }
            if ($src === '' || (!hh_news_editor_is_local_url($src) && !preg_match('#^https?://#i', $src))) {
                $el->parentNode->removeChild($el);
                return;
            }
            $el->setAttribute('src', $src);
            $el->setAttribute('alt', '');
            $el->setAttribute('loading', 'lazy');
            return;
        }
        if ($tag === 'a') {
            $href = trim($el->getAttribute('href'));
            while ($el->attributes->length) {
                $el->removeAttribute($el->attributes->item(0)->name);
            }
            if ($href !== '' && preg_match('#^https?://#i', $href)) {
                $el->setAttribute('href', $href);
                $el->setAttribute('rel', 'noopener noreferrer');
                $el->setAttribute('target', '_blank');
            }
        }
        for ($i = $el->childNodes->length - 1; $i >= 0; $i--) {
            $walker($el->childNodes->item($i));
        }
    };

    for ($i = $root->childNodes->length - 1; $i >= 0; $i--) {
        $walker($root->childNodes->item($i));
    }

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return trim($out);
}

function hh_news_editor_prepare_body($html)
{
    $html = hh_news_editor_rehost_images($html);
    return hh_news_editor_sanitize_html($html);
}

function hh_news_editor_excerpt($html, $maxLen = 220)
{
    require_once __DIR__ . '/feed_repo.php';
    return hh_feed_summary_excerpt(hh_feed_strip_html($html), $maxLen);
}
