<?php
/**
 * Copy to config.local.php and fill in secrets. Do not commit config.local.php.
 */
return array(
    'app' => array(
        'name' => 'Handheld Hub',
        'base_url' => 'http://localhost:8080',
        'default_locale' => 'en',
        'timezone' => 'UTC',
    ),
    'mysql' => array(
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=handheld_hub;charset=utf8mb4',
        'user' => 'handheld',
        'pass' => 'handheld',
    ),
    'storage' => array(
        'fs_root' => __DIR__ . '/storage/handhelds',
        'web_prefix' => '/storage/handhelds',
    ),
    'scraper' => array(
        'base_url' => 'https://zhangjiquan.com',
        'delay_ms' => 1200,
        'user_agent' => 'HandheldHubBot/1.0 (+local-dev; contact=admin)',
        'max_retries' => 3,
    ),
    'deepseek' => array(
        'api_key' => '',
        'api_url' => 'https://api.deepseek.com/chat/completions',
        'model' => 'deepseek-chat',
    ),
    'blogger' => array(
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => 'http://localhost:8080/admin/blogger_oauth.php',
        'blog_id' => '',
        'refresh_token' => '',
    ),
    'admin' => array(
        'session_name' => 'HHADMINSESSID',
        'max_fail' => 5,
        'lock_seconds' => 900,
    ),
);
