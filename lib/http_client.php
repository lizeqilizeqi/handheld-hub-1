<?php

require_once __DIR__ . '/bootstrap.php';

function hh_http_get($url, $options = array())
{
    $cfg = hh_config_get('scraper', array());
    $ua = isset($options['user_agent']) ? (string) $options['user_agent'] : (string) ($cfg['user_agent'] ?? 'HandheldHubBot/1.0');
    $maxRetries = isset($options['max_retries']) ? (int) $options['max_retries'] : (int) ($cfg['max_retries'] ?? 3);
    $delayMs = isset($options['delay_ms']) ? (int) $options['delay_ms'] : (int) ($cfg['delay_ms'] ?? 1200);

    $lastErr = '';
    for ($i = 0; $i < $maxRetries; $i++) {
        if ($i > 0) {
            usleep($delayMs * 1000);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
            ),
            CURLOPT_USERAGENT => $ua,
        ));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body !== false && $code >= 200 && $code < 300) {
            return array('ok' => true, 'body' => (string) $body, 'http_code' => $code);
        }
        $lastErr = $err !== '' ? $err : 'HTTP ' . $code;
    }
    return array('ok' => false, 'body' => '', 'http_code' => 0, 'error' => $lastErr);
}

function hh_http_download($url, $destPath)
{
    $dir = dirname($destPath);
    hh_ensure_dir($dir);

    $ch = curl_init($url);
    $fp = fopen($destPath, 'wb');
    if ($fp === false) {
        curl_close($ch);
        return array('ok' => false, 'error' => 'Cannot write file');
    }
    curl_setopt_array($ch, array(
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_USERAGENT => (string) hh_config_get('scraper.user_agent', 'HandheldHubBot/1.0'),
    ));
    $ok = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $code < 200 || $code >= 300) {
        @unlink($destPath);
        return array('ok' => false, 'error' => $err !== '' ? $err : 'HTTP ' . $code);
    }
    return array('ok' => true);
}

function hh_http_post_json($url, $payload, $headers = array())
{
    $ch = curl_init($url);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $hdr = array_merge(array('Content-Type: application/json'), $headers);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => $hdr,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
    ));
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return array('ok' => false, 'http_code' => $code, 'error' => $err);
    }
    return array('ok' => $code >= 200 && $code < 300, 'http_code' => $code, 'body' => (string) $body);
}

function hh_http_post_form($url, $fields, $headers = array())
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
    ));
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return array('ok' => false, 'http_code' => $code, 'error' => $err);
    }
    return array('ok' => $code >= 200 && $code < 300, 'http_code' => $code, 'body' => (string) $body);
}
