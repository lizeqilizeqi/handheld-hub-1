<?php

function hh_admin_t($key)
{
    static $map = null;
    if ($map === null) {
        $map = array(
            'app_title' => '掌机百科',
            'admin' => '管理后台',
            'logout' => '退出',
            'dashboard' => '概览',
            'handhelds' => '掌机列表',
            'scrape' => '抓取同步',
            'translate' => '翻译',
            'publish' => '独立站发布',
            'blogger' => 'Blogger 发布',
            'deploy' => '服务器部署',
            'sites' => '站点 / Hub',
            'sites_hint' => '对外子域与 Hub 入口一览。运行 migration_007 后可在数据库维护板块文案。',
            'sites_migration_hint' => '尚未检测到 hh_sites 表：请在服务器执行 sql/migration_007_hub_sites.sql，或暂时使用 config.local.php 中的 app.sites。',
            'sites_code' => '代码',
            'sites_name' => '名称',
            'sites_hosts' => '域名',
            'sites_hub_sections' => 'Hub 板块（数据库）',
            'sites_seo' => 'SEO / GEO',
            'sites_title_en' => '标题 (EN)',
            'sites_title_zh' => '标题 (中文)',
            'sites_desc_en' => '描述 (EN)',
            'sites_desc_zh' => '描述 (中文)',
            'sites_badge' => '徽章',
            'sites_sort' => '排序',
            'sites_visible' => '在 Hub 首页显示',
            'sites_sections_saved' => '已保存 %d 个板块。',
            'sites_csrf_error' => '表单已过期，请刷新后重试。',
            'games' => '复古游戏',
            'games_list_hint' => '与掌机列表相同：仅查看与编辑已抓取/入库的条目；新数据来自「抓取同步」（游戏源待配置）。',
            'games_empty' => '暂无游戏条目。抓取源配置后可在「抓取同步」选择「复古游戏」渠道。',
            'games_hint' => 'game.oldmanhub.com 目录条目（元数据 + 外链，不含 ROM）。',
            'games_migration_hint' => '请执行 sql/migration_008_game_entries.sql。',
            'games_new' => '新建条目',
            'games_preview' => '打开前台目录',
            'games_saved' => '已保存游戏条目。',
            'games_slug' => 'Slug',
            'games_platform' => '平台',
            'games_title_en' => '标题 (EN)',
            'games_title_zh' => '标题 (中文)',
            'games_summary_en' => '摘要 (EN)',
            'games_summary_zh' => '摘要 (中文)',
            'games_body_en' => '正文 (EN)',
            'games_body_zh' => '正文 (中文)',
            'games_external_url' => '外链 URL',
            'games_list' => '全部条目',
            'feeds' => '资讯列表',
            'feeds_list_hint' => '本站原创资讯：在「资讯发布」撰写图文，发布后在此管理；仅 status=published 的条目显示在前台。',
            'feeds_empty' => '暂无资讯。请点击「资讯发布」撰写首篇文章。',
            'feeds_original' => '原文',
            'news_publish' => '资讯发布',
            'news_publish_new' => '新建资讯',
            'news_publish_hint' => '支持富文本排版；可从网页或 Word 复制图文，剪贴板图片会自动上传到本站存储。',
            'news_publish_btn' => '发布',
            'news_save_draft' => '保存草稿',
            'news_publish_done' => '资讯已保存。',
            'news_draft_saved' => '草稿已保存。',
            'news_title' => '标题',
            'news_cover' => '封面图',
            'news_cover_empty' => '未设置封面',
            'news_cover_pick' => '上传封面',
            'news_cover_clear' => '清除封面',
            'news_body' => '正文',
            'news_summary_optional' => '摘要（可选）',
            'news_paste_hint' => '可直接粘贴含文字与图片的内容；图片会自动转存到本站（Ctrl+V）。',
            'news_deleted' => '已删除资讯。',
            'delete' => '删除',
            'hub_modules' => 'Hub 首页模块',
            'hub_modules_hint' => '关闭后该模块不在 Hub 首页卡片与顶栏导航中显示（子站 URL 仍可直链访问）。',
            'hub_module_handhelds' => '掌机百科',
            'hub_module_games' => '怀旧游戏',
            'hub_module_news' => '资讯精选',
            'hub_modules_saved' => '模块显示状态已更新。',
            'feeds_hint' => '为 news.oldmanhub.com 配置 RSS/Atom 源；新条目默认 pending，需审核发布。',
            'feeds_migration_hint' => '请执行 sql/migration_009_feeds.sql 与 migration_011_news_scrape_bilingual.sql。',
            'feeds_cron_hint' => '服务器 Cron 示例（每 6 小时）：',
            'feeds_sources' => '订阅源',
            'feeds_source_name' => '名称',
            'feeds_feed_url' => 'Feed URL',
            'feeds_home_url' => '站点首页',
            'feeds_interval' => '抓取间隔（分钟）',
            'feeds_enabled' => '启用',
            'feeds_new_source' => '新建源',
            'feeds_source_saved' => '已保存订阅源。',
            'feeds_fetch_now' => '立即抓取',
            'feeds_fetch_done' => '抓取完成：新增 %d，重复 %d。',
            'feeds_last_fetch' => '上次抓取',
            'feeds_items' => '条目',
            'feeds_item_title' => '标题',
            'feeds_publish' => '发布',
            'feeds_hide' => '隐藏',
            'feeds_item_updated' => '已更新条目状态。',
            'feeds_preview' => '打开 news 前台',
            'preview' => '预览',
            'edit' => '编辑',
            'save' => '保存',
            'back' => '返回',
            'search' => '搜索',
            'brand' => '品牌',
            'all_brands' => '全部品牌',
            'name' => '名称',
            'release' => '发布日期',
            'screen' => '屏幕',
            'status' => '状态',
            'overview' => '数据概览',
            'total' => '掌机总数',
            'published_count' => '已发布',
            'run_scrape' => '执行抓取',
            'recent_jobs' => '最近抓取任务',
            'latest_handhelds' => '最新掌机',
            'latest_handhelds_hint' => '仅显示最新 10 条（按发布日期），完整列表见',
            'handheld_list_link' => '掌机列表',
            'draft_count' => '草稿',
            'pending_translate' => '待翻译',
            'translate_ai_draft' => '已翻译（待审核）',
            'translate_human_approved' => '人工已通过',
            'translate_stats_hint' => '「待翻译」= 尚无英文；「已翻译（待审核）」= DeepSeek 已生成，不算待翻译。',
            'job_id' => '任务 ID',
            'job_type' => '类型',
            'job_result' => '结果',
            'new' => '新增',
            'updated' => '更新',
            'failed' => '失败',
            'logs' => '日志',
            'level' => '级别',
            'time' => '时间',
            'slug' => '标识',
            'source' => '来源',
            'scraped_at' => '抓取时间',
            'specs' => '硬件参数',
            'images' => '图片',
            'zh_content' => '中文内容（抓取）',
            'en_content' => '英文内容',
            'cover' => '封面',
            'mode' => '模式',
            'incremental' => '增量（仅更新有变化的）',
            'full' => '全量（重新抓取全部）',
            'single_slug' => '单个标识（可选）',
            'start_scrape' => '开始抓取',
            'scrape_hint' => '数据源：掌机圈 zhangjiquan.com，请求间隔约 1.2 秒，图片保存到本地。',
            'translate_list' => '待翻译掌机',
            'en_review' => '英文审核',
            'generate_en' => '用 DeepSeek 生成英文',
            'verified_urls' => '核对链接（官网、Wikipedia，每行一个）',
            'back_edit' => '返回编辑',
            'login' => '登录',
            'username' => '账号',
            'password' => '密码',
            'login_hint' => '默认账号 admin / password，首次登录后请修改密码。',
            'saved' => '已保存。',
            'not_found' => '未找到该记录。',
            'back_list' => '← 返回列表',
            'translate_status' => '翻译状态',
            'scrape_channel' => '抓取渠道',
            'scrape_channel_handheld' => '掌机（zhangjiquan）',
            'scrape_channel_news' => '资讯（快科技·掌机游戏列表）',
            'scrape_channel_game' => '复古游戏（源未配置）',
            'scrape_news_since' => '资讯起始日期',
            'scrape_news_since_hint' => '默认当年 1 月 1 日：列表会滚动加载直到遇到更早的文章为止；已入库链接自动跳过。',
            'preview_title' => '抓取预览',
            'open_source' => '打开掌机圈原文',
            'actions' => '操作',
            'translate_how_title' => '翻译如何实现？',
            'publish_how_title' => '独立站发布说明',
            'publish_stats' => '发布统计',
            'publish_stats_hint' => '仅「已发布」状态会出现在前台 /zh/ 与 /en/ 站点。',
            'publish_public_links' => '前台入口',
            'publish_preview' => '预览',
            'publish_back_list' => '返回列表',
            'publish_to_site' => '发布到独立站',
            'publish_unpublish' => '撤回为草稿',
            'publish_live_hint' => '已发布，可打开前台链接查看：',
            'publish_draft_hint' => '当前为草稿，发布后前台可见。',
            'publish_list_title' => '掌机发布列表',
            'publish_filter' => '筛选',
            'publish_batch' => '批量发布',
            'publish_batch_all_ready' => '一键发布全部已翻译',
            'publish_ready_count' => '待发布（已翻译）',
            'publish_batch_unpublish' => '批量撤回草稿',
            'publish_batch_hint' => '「一键发布全部已翻译」不限当前页，仅含未发布且英文已翻译的掌机。',
        );
    }
    return isset($map[$key]) ? $map[$key] : $key;
}

function hh_admin_status_label($status)
{
    $map = array(
        'draft' => '草稿',
        'review' => '审核中',
        'published' => '已发布',
        'pending' => '待处理',
        'running' => '运行中',
        'done' => '完成',
        'failed' => '失败',
        'incremental' => '增量',
        'full' => '全量',
        'single' => '单台',
    );
    return isset($map[$status]) ? $map[$status] : $status;
}

function hh_admin_review_label($status)
{
    $map = array(
        'pending' => '待翻译',
        'ai_draft' => '已翻译（待审核）',
        'human_approved' => '人工已通过',
    );
    return isset($map[$status]) ? $map[$status] : $status;
}

function hh_admin_preview_link($id, $class = '')
{
    $cls = $class !== '' ? ' class="' . hh_h($class) . '"' : '';
    return '<a href="preview.php?id=' . (int) $id . '"' . $cls . '>' . hh_h(hh_admin_t('preview')) . '</a>';
}

function hh_admin_action_links($id)
{
    echo hh_admin_preview_link($id);
    echo ' · <a href="handheld.php?id=' . (int) $id . '">' . hh_h(hh_admin_t('edit')) . '</a>';
}

function hh_admin_game_action_links($id, $slug)
{
    if (!function_exists('hh_site_public_url')) {
        require_once dirname(__DIR__) . '/lib/site_context.php';
    }
    $preview = hh_site_public_url('en/game/' . $slug, 'game');
    echo '<a href="' . hh_h($preview) . '" target="_blank" rel="noopener">' . hh_h(hh_admin_t('preview')) . '</a>';
    echo ' · <a href="games.php?id=' . (int) $id . '">' . hh_h(hh_admin_t('edit')) . '</a>';
}

function hh_admin_news_action_links($id, $slug)
{
    if (!function_exists('hh_site_public_url')) {
        require_once dirname(__DIR__) . '/lib/site_context.php';
    }
    $preview = hh_site_public_url('en/news/' . $slug, 'news');
    echo '<a href="' . hh_h($preview) . '" target="_blank" rel="noopener">' . hh_h(hh_admin_t('preview')) . '</a>';
    echo ' · <a href="news_publish.php?id=' . (int) $id . '">' . hh_h(hh_admin_t('edit')) . '</a>';
}

function hh_scrape_channel_unit($job)
{
    $ch = isset($job['channel']) ? (string) $job['channel'] : 'handheld';
    if ($ch === 'news') {
        return '条';
    }
    if ($ch === 'game') {
        return '条';
    }
    return '台';
}
