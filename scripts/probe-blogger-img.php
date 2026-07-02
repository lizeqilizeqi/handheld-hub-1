<?php
require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/blogger.php';

hh_bootstrap();
$pdo = hh_pdo();
$blogId = (string) hh_config_get('blogger.blog_id', '');
$token = hh_blogger_get_access_token($pdo);

foreach (array('7798027547066556154', '248626789642925710') as $postId) {
    $url = 'https://www.googleapis.com/blogger/v3/blogs/' . rawurlencode($blogId) . '/posts/' . rawurlencode($postId);
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $token),
    ));
    $body = curl_exec($ch);
    curl_close($ch);
    $json = json_decode((string) $body, true);
    echo "=== $postId " . ($json['title'] ?? '') . " ===\n";
    if (!empty($json['images'])) {
        echo 'images field: ' . json_encode($json['images']) . "\n";
    }
    preg_match_all('/<img[^>]+>/i', $json['content'] ?? '', $m);
    echo 'img tags: ' . count($m[0]) . "\n";
    foreach ($m[0] as $tag) {
        echo $tag . "\n";
        if (preg_match('/src="([^"]+)"/', $tag, $sm)) {
            $src = $sm[1];
            echo '  src len=' . strlen($src) . ' head=' . substr($src, 0, 120) . "\n";
        }
    }
    echo "\n";
}

$imgs = hh_handheld_images($pdo, 1);
echo "local images: " . json_encode($imgs) . "\n";
$fs = hh_blogger_image_fs_path($imgs[0]['path']);
echo "fs exists: " . (is_file($fs) ? 'yes' : 'no') . " path=$fs\n";
$url = hh_blogger_upload_local_image_to_google($pdo, $fs);
echo "google upload: " . ($url ? substr($url, 0, 200) : ('FAILED ' . ($GLOBALS['hh_blogger_last_upload_error'] ?? ''))) . "\n";
