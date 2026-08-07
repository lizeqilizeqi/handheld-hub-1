<?php

function hh_vertical_ui($siteCode, $locale, $key)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    $all = array(
        'game' => array(
            'en' => array(
                'site_name' => 'Retro Games',
                'tagline' => 'Classic platforms and titles — curated directory in preparation.',
                'lead' => 'We are building a compliance-first catalog of retro games: metadata, official links, and editorial picks — not ROM hosting.',
                'badge_soon' => 'Coming soon',
                'back_hub' => '← Old Man Hub',
            ),
            'zh' => array(
                'site_name' => '怀旧游戏',
                'tagline' => '经典平台与游戏目录筹备中。',
                'lead' => '本站将提供合规向的游戏资料、官方链接与编辑推荐，不提供 ROM 下载或侵权资源。',
                'badge_soon' => '筹备中',
                'back_hub' => '← 老男人 Hub',
            ),
        ),
    );
    if (!isset($all[$siteCode][$locale][$key])) {
        return $key;
    }
    return $all[$siteCode][$locale][$key];
}
