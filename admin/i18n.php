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
            'not_found' => '未找到该掌机。',
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
