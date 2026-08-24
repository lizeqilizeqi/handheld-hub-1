<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/news_editor.php';

hh_admin_require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'error' => 'Method not allowed'));
    exit;
}

try {
    if (!empty($_FILES['image']['tmp_name'])) {
        $url = hh_news_editor_upload_from_request($_FILES['image']);
        echo json_encode(array('ok' => true, 'url' => $url));
        exit;
    }
    $raw = file_get_contents('php://input');
    $payload = json_decode((string) $raw, true);
    if (is_array($payload) && !empty($payload['dataUrl'])) {
        $url = hh_news_editor_upload_base64((string) $payload['dataUrl']);
        echo json_encode(array('ok' => true, 'url' => $url));
        exit;
    }
    throw new RuntimeException('未收到图片');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
}
