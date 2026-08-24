<?php

require_once __DIR__ . '/deepseek.php';
require_once __DIR__ . '/feed_repo.php';
require_once __DIR__ . '/feed_body_html.php';
require_once __DIR__ . '/game_repo.php';
require_once __DIR__ . '/scraper/mydrivers_news.php';

function hh_content_translate_feed_item(PDO $pdo, $id)
{
    $row = hh_feed_item_by_id($pdo, (int) $id);
    if (!$row) {
        throw new RuntimeException('资讯不存在');
    }
    $bodyZhHtml = hh_feed_sanitize_zh_brand_html(hh_mydrivers_sanitize_body_html((string) ($row['body_zh'] ?? '')));
    $titleZh = hh_feed_sanitize_zh_brand((string) ($row['title_zh'] ?: $row['title']));
    $summaryZh = hh_feed_sanitize_zh_brand((string) ($row['summary_zh'] ?: $row['summary'] ?? ''));
    $bodyForApi = hh_feed_body_html_for_translate_api($bodyZhHtml, 14000);

    $payload = hh_content_deepseek_json(
        'Translate Chinese retro-gaming news to English. Input body_zh is HTML: translate only human-readable Chinese text inside tags. Copy every <img> tag exactly (same src, attributes). Do NOT start with site names (快科技, Kuai Technology, Kuaikeji, MyDrivers) or date lead-ins. Return JSON: title_en, summary_en, body_en. body_en must be HTML using p, br, strong, b, img, a, span.',
        "title_zh:\n{$titleZh}\n\nsummary_zh:\n{$summaryZh}\n\nbody_zh HTML:\n{$bodyForApi}"
    );
    $bodyEnRaw = hh_mydrivers_sanitize_body_html((string) ($payload['body_en'] ?? ''));
    $bodyEn = hh_feed_sanitize_en_brand_html(
        hh_feed_body_en_merge_imgs_from_zh($bodyZhHtml, $bodyEnRaw)
    );
    hh_feed_item_save($pdo, (int) $id, array(
        'title_zh' => $titleZh,
        'title_en' => hh_feed_sanitize_en_brand((string) ($payload['title_en'] ?? '')),
        'summary_zh' => $summaryZh,
        'summary_en' => hh_feed_sanitize_en_brand((string) ($payload['summary_en'] ?? '')),
        'body_zh' => $bodyZhHtml,
        'body_en' => $bodyEn,
        'status' => $row['status'],
        'translate_status' => 'done',
    ));
    return true;
}

function hh_content_translate_game_entry(PDO $pdo, $id)
{
    $row = hh_game_by_id($pdo, (int) $id);
    if (!$row) {
        throw new RuntimeException('游戏条目不存在');
    }
    $titleZh = (string) ($row['title_zh'] ?: $row['title_en']);
    $summaryZh = (string) ($row['summary_zh'] ?? '');
    $bodyZh = (string) ($row['body_zh'] ?? '');
    $payload = hh_content_deepseek_json(
        'Translate retro game catalog metadata to English. Return JSON: title_en, summary_en, body_en.',
        "title_zh: {$titleZh}\nsummary_zh: {$summaryZh}\nbody_zh: " . mb_substr($bodyZh, 0, 3000)
    );
    hh_game_save($pdo, array(
        'slug' => $row['slug'],
        'platform' => $row['platform'],
        'title_en' => (string) ($payload['title_en'] ?? $row['title_en']),
        'title_zh' => $titleZh,
        'summary_en' => (string) ($payload['summary_en'] ?? ''),
        'summary_zh' => $summaryZh,
        'body_en' => (string) ($payload['body_en'] ?? ''),
        'body_zh' => $bodyZh,
        'external_url' => $row['external_url'],
        'status' => $row['status'],
        'sort_order' => $row['sort_order'],
    ), (int) $id);
    return true;
}

function hh_content_translate_feed_batch(PDO $pdo, $limit = 20)
{
    $ids = hh_feed_translate_pending_ids($pdo, $limit);
    $ok = 0;
    $fail = 0;
    foreach ($ids as $id) {
        try {
            hh_content_translate_feed_item($pdo, $id);
            $ok++;
        } catch (Throwable $e) {
            $fail++;
            $pdo->prepare('UPDATE hh_feed_items SET translate_status = "failed" WHERE id = ?')->execute(array($id));
        }
    }
    return array('ok' => $ok, 'fail' => $fail, 'total' => count($ids));
}

function hh_content_translate_game_batch(PDO $pdo, $limit = 20)
{
    if (!hh_game_table_exists($pdo)) {
        return array('ok' => 0, 'fail' => 0, 'total' => 0);
    }
    $st = $pdo->query('SELECT id FROM hh_game_entries WHERE TRIM(title_zh) <> "" AND (TRIM(title_en) = "" OR TRIM(summary_en) = "") ORDER BY updated_at DESC LIMIT ' . (int) $limit);
    $ids = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
    $ok = 0;
    $fail = 0;
    foreach ($ids as $id) {
        try {
            hh_content_translate_game_entry($pdo, $id);
            $ok++;
        } catch (Throwable $e) {
            $fail++;
        }
    }
    return array('ok' => $ok, 'fail' => $fail, 'total' => count($ids));
}

function hh_content_deepseek_json($systemExtra, $user)
{
    $apiKey = (string) hh_config_get('deepseek.api_key', '');
    if ($apiKey === '') {
        throw new RuntimeException('DeepSeek API key not configured');
    }
    require_once __DIR__ . '/http_client.php';
    $payload = array(
        'model' => (string) hh_config_get('deepseek.model', 'deepseek-chat'),
        'messages' => array(
            array('role' => 'system', 'content' => $systemExtra),
            array('role' => 'user', 'content' => $user),
        ),
        'response_format' => array('type' => 'json_object'),
        'temperature' => 0.3,
    );
    $res = hh_http_post_json(
        (string) hh_config_get('deepseek.api_url', 'https://api.deepseek.com/chat/completions'),
        $payload,
        array('Authorization: Bearer ' . $apiKey)
    );
    if (!$res['ok']) {
        throw new RuntimeException('DeepSeek HTTP ' . $res['http_code']);
    }
    $data = json_decode($res['body'], true);
    $text = $data['choices'][0]['message']['content'] ?? '';
    $out = json_decode($text, true);
    if (!is_array($out)) {
        throw new RuntimeException('DeepSeek JSON parse failed');
    }
    return $out;
}
