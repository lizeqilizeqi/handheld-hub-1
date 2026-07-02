<?php

function hh_secrets_file()
{
    return dirname(__DIR__) . '/config.secrets.php';
}

function hh_secrets_load()
{
    $path = hh_secrets_file();
    if (!is_file($path)) {
        return array();
    }
    $data = require $path;
    return is_array($data) ? $data : array();
}

/**
 * @return array{ok:bool,message:string}
 */
function hh_secrets_write(array $secrets)
{
    $path = hh_secrets_file();
    $content = "<?php\n/**\n * 敏感配置 — 由后台自动写入，请勿提交到 Git。\n */\nreturn " . var_export($secrets, true) . ";\n";

    if (file_put_contents($path, $content, LOCK_EX) === false) {
        return array('ok' => false, 'message' => '无法写入 config.secrets.php，请检查目录权限');
    }

    hh_config_clear_cache();
    return array('ok' => true, 'message' => '已保存');
}

function hh_config_clear_cache()
{
    $GLOBALS['hh_config_force_reload'] = true;
}

function hh_secret_mask($value, $visibleHead = 4, $visibleTail = 4)
{
    $value = (string) $value;
    if ($value === '') {
        return '';
    }
    $len = strlen($value);
    if ($len <= $visibleHead + $visibleTail) {
        return '****';
    }
    return substr($value, 0, $visibleHead) . str_repeat('*', min(20, $len - $visibleHead - $visibleTail)) . substr($value, -$visibleTail);
}

function hh_deepseek_api_key()
{
    $key = (string) hh_config_get('deepseek.api_key', '');
    return trim($key);
}

function hh_deepseek_api_key_configured()
{
    return hh_deepseek_api_key() !== '';
}

function hh_deepseek_api_key_masked()
{
    return hh_secret_mask(hh_deepseek_api_key());
}

/**
 * @return array{ok:bool,message:string}
 */
function hh_deepseek_save_api_key($apiKey)
{
    $apiKey = trim((string) $apiKey);
    if ($apiKey === '') {
        return array('ok' => false, 'message' => 'API Key 不能为空');
    }
    if (strlen($apiKey) < 8) {
        return array('ok' => false, 'message' => 'API Key 格式似乎不正确');
    }

    $secrets = hh_secrets_load();
    if (!isset($secrets['deepseek']) || !is_array($secrets['deepseek'])) {
        $secrets['deepseek'] = array();
    }
    $secrets['deepseek']['api_key'] = $apiKey;

    $r = hh_secrets_write($secrets);
    if ($r['ok']) {
        $r['message'] = 'DeepSeek API Key 已保存';
    }
    return $r;
}

function hh_blogger_default_redirect_uri()
{
    return rtrim((string) hh_base_url(), '/') . '/admin/blogger_oauth.php';
}

function hh_blogger_config_get($key)
{
    return trim((string) hh_config_get('blogger.' . $key, ''));
}

function hh_blogger_oauth_configured()
{
    return hh_blogger_config_get('client_id') !== ''
        && hh_blogger_config_get('client_secret') !== ''
        && hh_blogger_config_get('redirect_uri') !== ''
        && hh_blogger_config_get('blog_id') !== '';
}

function hh_blogger_config_display()
{
    return array(
        'client_id' => hh_blogger_config_get('client_id'),
        'client_secret_masked' => hh_secret_mask(hh_blogger_config_get('client_secret')),
        'redirect_uri' => hh_blogger_config_get('redirect_uri') ?: hh_blogger_default_redirect_uri(),
        'blog_id' => hh_blogger_config_get('blog_id'),
    );
}

/**
 * @return array{ok:bool,message:string}
 */
function hh_blogger_save_config($clientId, $clientSecret, $redirectUri, $blogId)
{
    $clientId = trim((string) $clientId);
    $clientSecret = trim((string) $clientSecret);
    $redirectUri = trim((string) $redirectUri);
    $blogId = trim((string) $blogId);

    if ($clientId === '') {
        return array('ok' => false, 'message' => 'Client ID 不能为空');
    }
    if ($redirectUri === '') {
        return array('ok' => false, 'message' => 'Redirect URI 不能为空');
    }
    if ($blogId === '') {
        return array('ok' => false, 'message' => 'Blog ID 不能为空');
    }

    $secrets = hh_secrets_load();
    $existingSecret = '';
    if (!empty($secrets['blogger']['client_secret'])) {
        $existingSecret = (string) $secrets['blogger']['client_secret'];
    } elseif (hh_blogger_config_get('client_secret') !== '') {
        $existingSecret = hh_blogger_config_get('client_secret');
    }

    if ($clientSecret === '') {
        if ($existingSecret === '') {
            return array('ok' => false, 'message' => 'Client Secret 不能为空');
        }
        $clientSecret = $existingSecret;
    }

    if (!isset($secrets['blogger']) || !is_array($secrets['blogger'])) {
        $secrets['blogger'] = array();
    }
    $secrets['blogger']['client_id'] = $clientId;
    $secrets['blogger']['client_secret'] = $clientSecret;
    $secrets['blogger']['redirect_uri'] = $redirectUri;
    $secrets['blogger']['blog_id'] = $blogId;

    $r = hh_secrets_write($secrets);
    if ($r['ok']) {
        $r['message'] = 'Blogger OAuth 配置已保存';
    }
    return $r;
}

function hh_config_merge_secrets($cfg)
{
    $secrets = hh_secrets_load();

    if (!empty($secrets['deepseek']['api_key'])) {
        if (!isset($cfg['deepseek']) || !is_array($cfg['deepseek'])) {
            $cfg['deepseek'] = array();
        }
        $cfg['deepseek']['api_key'] = (string) $secrets['deepseek']['api_key'];
    }

    if (!empty($secrets['blogger']) && is_array($secrets['blogger'])) {
        if (!isset($cfg['blogger']) || !is_array($cfg['blogger'])) {
            $cfg['blogger'] = array();
        }
        foreach (array('client_id', 'client_secret', 'redirect_uri', 'blog_id') as $k) {
            if (!empty($secrets['blogger'][$k])) {
                $cfg['blogger'][$k] = (string) $secrets['blogger'][$k];
            }
        }
    }

    return $cfg;
}
