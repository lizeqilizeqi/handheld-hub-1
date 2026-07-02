<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_client.php';

function hh_deepseek_translate_handheld($handheld, $specs, $zhContent, $verifiedUrls = array())
{
    $apiKey = (string) hh_config_get('deepseek.api_key', '');
    if ($apiKey === '') {
        throw new RuntimeException('DeepSeek API key not configured. 请在翻译页点击「配置 API Key」填写。');
    }

    $specText = json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $zhBody = isset($zhContent['body_html']) ? strip_tags((string) $zhContent['body_html']) : '';
    $verified = count($verifiedUrls) > 0 ? implode("\n", $verifiedUrls) : 'none provided';

    $system = 'You are a handheld gaming device editor writing for an English-speaking audience. '
        . 'Produce original, SEO-friendly HTML content (use h2, p, ul — no full document wrapper). '
        . 'Keep factual specs accurate. Include: overview, key specs highlights, who it is for, and buying notes. '
        . 'Do not invent prices or release dates not present in the source specs.';

    $user = "Translate and rewrite this handheld for English readers.\n\n"
        . "Device: {$handheld['name_zh']} ({$handheld['brand']})\n"
        . "Slug: {$handheld['slug']}\n"
        . "Verified reference URLs:\n{$verified}\n\n"
        . "Specifications JSON:\n{$specText}\n\n"
        . "Chinese source excerpt:\n" . mb_substr($zhBody, 0, 4000, 'UTF-8') . "\n\n"
        . "Return JSON with keys: title, summary, meta_description, body_html, specs_en, name_en\n"
        . "title is the full SEO article headline for Blogger (e.g. \"Anbernic RG Rotate Review: ...\").\n"
        . "name_en is ONLY the short English product name (e.g. \"RG Rotate\"), same role as the Chinese device name — never a review headline.\n"
        . "specs_en must be an object: English labels mapped to English values for every spec field in the source JSON (translate keys and values faithfully).";

    $payload = array(
        'model' => (string) hh_config_get('deepseek.model', 'deepseek-chat'),
        'messages' => array(
            array('role' => 'system', 'content' => $system),
            array('role' => 'user', 'content' => $user),
        ),
        'response_format' => array('type' => 'json_object'),
        'temperature' => 0.4,
    );

    $res = hh_http_post_json(
        (string) hh_config_get('deepseek.api_url', 'https://api.deepseek.com/chat/completions'),
        $payload,
        array('Authorization: Bearer ' . $apiKey)
    );

    if (!$res['ok']) {
        throw new RuntimeException('DeepSeek API error HTTP ' . $res['http_code'] . ': ' . mb_substr($res['body'], 0, 500));
    }

    $json = json_decode($res['body'], true);
    if (!is_array($json) || empty($json['choices'][0]['message']['content'])) {
        throw new RuntimeException('Invalid DeepSeek response');
    }

    $content = json_decode((string) $json['choices'][0]['message']['content'], true);
    if (!is_array($content)) {
        throw new RuntimeException('DeepSeek did not return valid JSON content');
    }

    return array(
        'title' => isset($content['title']) ? (string) $content['title'] : ($handheld['name_en'] ?: $handheld['name_zh']),
        'name_en' => isset($content['name_en']) ? trim((string) $content['name_en']) : '',
        'summary' => isset($content['summary']) ? (string) $content['summary'] : '',
        'meta_description' => isset($content['meta_description']) ? (string) $content['meta_description'] : '',
        'body_html' => isset($content['body_html']) ? (string) $content['body_html'] : '',
        'specs_en' => (isset($content['specs_en']) && is_array($content['specs_en'])) ? $content['specs_en'] : array(),
    );
}
