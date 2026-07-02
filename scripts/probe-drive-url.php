<?php
require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/blogger.php';

hh_bootstrap();
$pdo = hh_pdo();
$token = hh_blogger_get_access_token($pdo);
$fileId = $argv[1] ?? '1XOVupOIElvhoqHM6Lg8i0t4rgi2uYqpa';

$url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
    . '?fields=id,name,mimeType,webContentLink,webViewLink,thumbnailLink,iconLink,hasThumbnail,permissions';
$ch = curl_init($url);
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $token),
));
$body = curl_exec($ch);
curl_close($ch);
echo $body . "\n\n";

$urls = array(
    'uc?id=' => 'https://drive.google.com/uc?id=' . rawurlencode($fileId),
    'export=view' => 'https://drive.google.com/uc?export=view&id=' . rawurlencode($fileId),
    'thumbnail' => 'https://drive.google.com/thumbnail?id=' . rawurlencode($fileId) . '&sz=w1000',
    'lh3' => 'https://lh3.googleusercontent.com/d/' . $fileId . '=s1600',
);
foreach ($urls as $label => $testUrl) {
    $ch = curl_init($testUrl);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
    ));
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    echo "$label: HTTP $code type=$ctype final=" . substr($final, 0, 120) . "\n";
}
