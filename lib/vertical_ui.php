<?php

function hh_vertical_ui($siteCode, $locale, $key)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    $all = array(
        'game' => array(
            'en' => array(
                'kicker' => 'GAME',
                'site_name' => 'Game Hub',
                'tagline' => 'Classic games across genres — in preparation.',
                'lead' => 'This vertical is under preparation. We will publish original English and Chinese guides for classic games once compliance and sourcing are ready.',
                'panel_status_title' => 'Current status',
                'panel_status_body' => 'Placeholder online. Content catalog, reviews, and listings are not public yet.',
                'panel_compliance_title' => 'Compliance notes',
                'panel_compliance_body' => 'We only plan to publish original commentary and lawfully licensed or public-domain materials. No pirated ROMs, cracked software, or unauthorized copyrighted game files will be hosted.',
                'panel_next_title' => 'What comes next',
                'panel_next_body' => 'Editorial standards, metadata schema, and bilingual article templates — then curated classic-game entries.',
                'back_hub' => 'Back to Old Man Hub',
                'nav_handhelds' => 'Handhelds',
                'footer_note' => 'Game Hub — part of Old Man Hub',
            ),
            'zh' => array(
                'kicker' => 'GAME',
                'site_name' => '游戏百科',
                'tagline' => '各类型经典游戏 — 筹备中。',
                'lead' => '本栏目筹备中。待合规与资料就绪后，将发布中英双语的经典游戏指南与介绍。',
                'panel_status_title' => '当前状态',
                'panel_status_body' => '占位页已上线，游戏目录与正文尚未公开。',
                'panel_compliance_title' => '合规说明',
                'panel_compliance_body' => '仅计划发布原创评论及合法授权或公有领域材料，不提供盗版 ROM、破解软件或未授权游戏文件。',
                'panel_next_title' => '后续计划',
                'panel_next_body' => '编辑规范、元数据结构与双语模板完成后，逐步上线精选条目。',
                'back_hub' => '返回老男人 Hub',
                'nav_handhelds' => '掌机',
                'footer_note' => '游戏百科 — Oldman Hub 栏目',
            ),
        ),
        'news' => array(
            'en' => array(
                'kicker' => 'NEWS',
                'site_name' => 'News & Picks',
                'tagline' => 'Handheld gaming news — summaries with links to originals.',
                'lead' => 'Listings appear after admin workflow: scrape → translate → publish. Empty until then.',
                'panel_status_title' => 'Current status',
                'panel_status_body' => 'Placeholder only. No automated crawl jobs are exposed on the public site yet.',
                'panel_compliance_title' => 'Sources & attribution',
                'panel_compliance_body' => 'Aggregated items will link to original publishers with clear bylines. We do not republish full articles without permission.',
                'panel_next_title' => 'What comes next',
                'panel_next_body' => 'Source registry in admin, bilingual summaries, and hub cross-links to Handhelds and Game verticals.',
                'back_hub' => 'Back to Old Man Hub',
                'nav_handhelds' => 'Handhelds',
                'footer_note' => 'News & Picks — part of Old Man Hub',
            ),
            'zh' => array(
                'kicker' => 'NEWS',
                'site_name' => '资讯精选',
                'tagline' => '掌机游戏资讯 — 摘要 + 原文链接。',
                'lead' => '公开列表需在后台完成：抓取 → 翻译 → 独立站发布；未发布前此处为空。',
                'panel_status_title' => '当前状态',
                'panel_status_body' => '仅占位，公开站点尚未启用自动抓取任务。',
                'panel_compliance_title' => '来源与署名',
                'panel_compliance_body' => '聚合条目将指向原文并保留出处，未经授权不全文转载。',
                'panel_next_title' => '后续计划',
                'panel_next_body' => '后台来源登记、双语摘要，以及与掌机/游戏栏目的交叉链接。',
                'back_hub' => '返回老男人 Hub',
                'nav_handhelds' => '掌机',
                'footer_note' => '资讯精选 — Oldman Hub 栏目',
            ),
        ),
    );
    if (!isset($all[$siteCode][$locale][$key])) {
        return $key;
    }
    return $all[$siteCode][$locale][$key];
}
