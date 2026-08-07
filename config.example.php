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
        'contact_email' => 'contact@oldman.dpdns.org',
        'owner_name' => 'Handheld Hub',
        'sites' => array(
            'hub' => array(
                'hosts' => array('www.oldmanhub.com', 'oldmanhub.com', 'localhost'),
                'base_url' => 'https://www.oldmanhub.com',
            ),
            'handhelds' => array(
                'hosts' => array('handhelds.oldmanhub.com', 'handhelds.localhost'),
                'base_url' => 'https://handhelds.oldmanhub.com',
            ),
            'game' => array(
                'hosts' => array('game.oldmanhub.com', 'game.localhost'),
                'base_url' => 'https://game.oldmanhub.com',
                'status' => 'soon',
            ),
            'news' => array(
                'hosts' => array('news.oldmanhub.com', 'news.localhost'),
                'base_url' => 'https://news.oldmanhub.com',
                'status' => 'soon',
            ),
        ),
        'legacy_hosts' => array(
            'oldman.dpdns.org' => 'https://www.oldmanhub.com',
            'www.oldman.dpdns.org' => 'https://www.oldmanhub.com',
        ),
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
    'adsense' => array(
        'client_id' => '',
    ),
);
