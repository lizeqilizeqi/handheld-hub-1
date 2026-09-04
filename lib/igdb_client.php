<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/secrets.php';

function hh_igdb_token_cache_path()
{
    return hh_app_logs_dir() . '/igdb_token.json';
}

function hh_igdb_client_id()
{
    return trim((string) hh_config_get('igdb.client_id', ''));
}

function hh_igdb_client_secret()
{
    return trim((string) hh_config_get('igdb.client_secret', ''));
}

function hh_igdb_configured()
{
    return hh_igdb_client_id() !== '' && hh_igdb_client_secret() !== '';
}

/**
 * @return array{ok:bool,access_token:string,expires_at:int,error:string}
 */
function hh_igdb_fetch_access_token($clientId = null, $clientSecret = null)
{
    $clientId = $clientId !== null ? trim((string) $clientId) : hh_igdb_client_id();
    $clientSecret = $clientSecret !== null ? trim((string) $clientSecret) : hh_igdb_client_secret();

    if ($clientId === '' || $clientSecret === '') {
        return array('ok' => false, 'access_token' => '', 'expires_at' => 0, 'error' => 'IGDB Client ID 或 Client Secret 未配置');
    }

    $url = 'https://id.twitch.tv/oauth2/token?' . http_build_query(array(
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'client_credentials',
    ));

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
    ));
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return array('ok' => false, 'access_token' => '', 'expires_at' => 0, 'error' => $curlErr !== '' ? $curlErr : 'Twitch OAuth 请求失败');
    }

    $data = json_decode((string) $body, true);
    if (!is_array($data) || empty($data['access_token'])) {
        $msg = is_array($data) && !empty($data['message']) ? (string) $data['message'] : 'HTTP ' . $code;
        return array('ok' => false, 'access_token' => '', 'expires_at' => 0, 'error' => 'Twitch OAuth 失败：' . $msg);
    }

    $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;
    $expiresAt = time() + max(60, $expiresIn - 120);

    return array(
        'ok' => true,
        'access_token' => (string) $data['access_token'],
        'expires_at' => $expiresAt,
        'error' => '',
    );
}

function hh_igdb_load_cached_token()
{
    $path = hh_igdb_token_cache_path();
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['access_token']) || empty($data['expires_at'])) {
        return null;
    }
    if ((int) $data['expires_at'] <= time()) {
        return null;
    }
    return (string) $data['access_token'];
}

function hh_igdb_store_cached_token($accessToken, $expiresAt)
{
    hh_ensure_writable_dir(hh_app_logs_dir());
    $path = hh_igdb_token_cache_path();
    $payload = json_encode(array(
        'access_token' => (string) $accessToken,
        'expires_at' => (int) $expiresAt,
    ), JSON_UNESCAPED_UNICODE);
    @file_put_contents($path, $payload, LOCK_EX);
}

function hh_igdb_clear_token_cache()
{
    $path = hh_igdb_token_cache_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

function hh_igdb_access_token($forceRefresh = false)
{
    if (!$forceRefresh) {
        $cached = hh_igdb_load_cached_token();
        if ($cached !== null) {
            return array('ok' => true, 'access_token' => $cached, 'error' => '');
        }
    }

    $r = hh_igdb_fetch_access_token();
    if (!$r['ok']) {
        return array('ok' => false, 'access_token' => '', 'error' => $r['error']);
    }

    hh_igdb_store_cached_token($r['access_token'], $r['expires_at']);
    return array('ok' => true, 'access_token' => $r['access_token'], 'error' => '');
}

/**
 * @return array{ok:bool,http_code:int,body:string,error:string}
 */
function hh_igdb_api_post($endpoint, $queryBody, $clientId = null, $accessToken = null)
{
    $clientId = $clientId !== null ? trim((string) $clientId) : hh_igdb_client_id();
    if ($accessToken === null) {
        $tok = hh_igdb_access_token();
        if (!$tok['ok']) {
            return array('ok' => false, 'http_code' => 0, 'body' => '', 'error' => $tok['error']);
        }
        $accessToken = $tok['access_token'];
    }

    $url = 'https://api.igdb.com/v4/' . ltrim((string) $endpoint, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => (string) $queryBody,
        CURLOPT_HTTPHEADER => array(
            'Client-ID: ' . $clientId,
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ),
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
    ));
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return array('ok' => false, 'http_code' => $code, 'body' => '', 'error' => $curlErr !== '' ? $curlErr : 'IGDB 请求失败');
    }
    if ($code < 200 || $code >= 300) {
        return array('ok' => false, 'http_code' => $code, 'body' => (string) $body, 'error' => 'IGDB HTTP ' . $code);
    }

    return array('ok' => true, 'http_code' => $code, 'body' => (string) $body, 'error' => '');
}

/**
 * @return array{ok:bool,message:string,samples:array<int,string>}
 */
function hh_igdb_test_connection($clientId = null, $clientSecret = null)
{
    $clientId = $clientId !== null ? trim((string) $clientId) : hh_igdb_client_id();
    $clientSecret = $clientSecret !== null ? trim((string) $clientSecret) : hh_igdb_client_secret();

    if ($clientId === '' || $clientSecret === '') {
        return array('ok' => false, 'message' => '请先填写并保存 Client ID 与 Client Secret', 'samples' => array());
    }

    $tok = hh_igdb_fetch_access_token($clientId, $clientSecret);
    if (!$tok['ok']) {
        return array('ok' => false, 'message' => $tok['error'], 'samples' => array());
    }

    $api = hh_igdb_api_post(
        'games',
        'fields name; where platforms = (18); limit 3;',
        $clientId,
        $tok['access_token']
    );
    if (!$api['ok']) {
        return array('ok' => false, 'message' => $api['error'], 'samples' => array());
    }

    $rows = json_decode($api['body'], true);
    if (!is_array($rows)) {
        return array('ok' => false, 'message' => 'IGDB 返回格式异常', 'samples' => array());
    }

    $samples = array();
    foreach ($rows as $row) {
        if (is_array($row) && !empty($row['name'])) {
            $samples[] = (string) $row['name'];
        }
    }

    $msg = '连通性正常：Twitch OAuth 成功，IGDB 已返回 NES 示例游戏';
    if ($samples !== array()) {
        $msg .= '（' . implode('、', $samples) . '）';
    }

    return array('ok' => true, 'message' => $msg, 'samples' => $samples);
}
