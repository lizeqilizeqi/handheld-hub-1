<?php

require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/blogger.php';

hh_bootstrap();
$pdo = hh_pdo();

$id = isset($argv[1]) ? (int) $argv[1] : 0;
if ($id <= 0) {
    fwrite(STDERR, "Usage: php scripts/republish-handheld-blogger.php <handheld_id>\n");
    exit(1);
}

try {
    $posts = hh_blogger_publish_locales($pdo, $id, array('zh', 'en'), array(
        'labels' => array('handheld', 'gaming'),
    ));
    foreach ($posts as $loc => $post) {
        echo $loc . ': ' . ($post['url'] ?? $post['id']) . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
