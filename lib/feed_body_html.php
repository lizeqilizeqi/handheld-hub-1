<?php

require_once __DIR__ . '/feed_brand_sanitize.php';

/**
 * @return array<int, array{type:string,html:string}>
 */
function hh_feed_body_html_segments($html)
{
    $html = (string) $html;
    if (trim($html) === '') {
        return array();
    }
    $segments = array();
    $parts = preg_split('#(<img\b[^>]*>)#iu', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $part) {
        if ($part === '' || trim($part) === '') {
            continue;
        }
        if (preg_match('#^<img\b#iu', $part)) {
            $segments[] = array('type' => 'img', 'html' => $part);
            continue;
        }
        if (preg_match_all('#<(p|h[1-6]|blockquote|li)\b[^>]*>[\s\S]*?</\1>#iu', $part, $blocks)) {
            foreach ($blocks[0] as $block) {
                $segments[] = array('type' => 'text', 'html' => $block);
            }
            continue;
        }
        $plain = trim(strip_tags($part));
        if ($plain !== '') {
            $segments[] = array('type' => 'text', 'html' => '<p>' . htmlspecialchars($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>');
        }
    }
    return $segments;
}

/** @return string[] */
function hh_feed_body_en_paragraphs($html)
{
    $html = (string) $html;
    if (trim($html) === '') {
        return array();
    }
    if (preg_match_all('#<(p|h[1-6]|blockquote|li)\b[^>]*>[\s\S]*?</\1>#iu', $html, $blocks)) {
        return $blocks[0];
    }
    $plain = trim(strip_tags($html));
    if ($plain === '') {
        return array();
    }
    return array('<p>' . htmlspecialchars($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>');
}

function hh_feed_body_en_merge_imgs_from_zh($bodyZhHtml, $bodyEnHtml)
{
    $zhSegs = hh_feed_body_html_segments($bodyZhHtml);
    $enParas = hh_feed_body_en_paragraphs($bodyEnHtml);
    if ($zhSegs === array()) {
        return $bodyEnHtml;
    }
    $imgCount = 0;
    foreach ($zhSegs as $s) {
        if ($s['type'] === 'img') {
            $imgCount++;
        }
    }
    if ($imgCount === 0) {
        return $bodyEnHtml;
    }
    if (preg_match_all('#<img\b#iu', (string) $bodyEnHtml, $m) && count($m[0]) >= $imgCount) {
        return $bodyEnHtml;
    }

    $out = '';
    $pi = 0;
    foreach ($zhSegs as $seg) {
        if ($seg['type'] === 'img') {
            $out .= $seg['html'];
            continue;
        }
        if (isset($enParas[$pi])) {
            $out .= $enParas[$pi];
            $pi++;
        }
    }
    while (isset($enParas[$pi])) {
        $out .= $enParas[$pi];
        $pi++;
    }
    return trim($out);
}

function hh_feed_body_html_for_translate_api($html, $maxLen = 14000)
{
    $html = (string) $html;
    $html = hh_feed_sanitize_zh_brand_html($html);
    if (mb_strlen($html) <= $maxLen) {
        return $html;
    }
    return mb_substr($html, 0, $maxLen);
}
