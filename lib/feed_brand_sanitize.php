<?php

function hh_feed_sanitize_zh_brand($text)
{
    $text = (string) $text;
    $text = str_replace(array('快科技', '快科技 '), '', $text);
    $text = preg_replace('/\d{1,2}月\d{1,2}日消息[，,：:\s]*/u', '', $text);
    $text = preg_replace('/^\s*[，,：:\-–—\s]+/u', '', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function hh_feed_sanitize_zh_brand_html($html)
{
    $html = (string) $html;
    if ($html === '') {
        return '';
    }
    $html = str_replace('快科技', '', $html);
    $html = preg_replace('/\d{1,2}月\d{1,2}日消息[，,：:\s]*/u', '', $html);
    $html = preg_replace('/(<p[^>]*>)\s*[，,：:\s]+/iu', '$1', $html);
    return trim($html);
}

function hh_feed_sanitize_en_brand($text)
{
    $text = (string) $text;
    $text = preg_replace('/\b(Kuai\s*Technology|Kuaikeji|KuaiKeji|kuaikeji)\b[,\s:]*/iu', '', $text);
    $text = preg_replace('/\bMyDrivers\b[,\s:]*/iu', '', $text);
    $text = preg_replace(
        '/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2}(?:st|nd|rd|th)?\s+(?:news|message)[,\s:]*/iu',
        '',
        $text
    );
    $text = preg_replace('/^\s*[,\s:–—-]+/u', '', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function hh_feed_sanitize_en_brand_html($html)
{
    $html = (string) $html;
    if ($html === '') {
        return '';
    }
    $html = preg_replace('/\b(Kuai\s*Technology|Kuaikeji|KuaiKeji|kuaikeji)\b[,\s:]*/iu', '', $html);
    $html = preg_replace('/\bMyDrivers\b[,\s:]*/iu', '', $html);
    $html = preg_replace(
        '/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2}(?:st|nd|rd|th)?\s+(?:news|message)[,\s:]*/iu',
        '',
        $html
    );
    $html = preg_replace('/(<p[^>]*>)\s*[,\s:–—-]+/iu', '$1', $html);
    return trim($html);
}
