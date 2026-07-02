<?php

require_once __DIR__ . '/i18n.php';

function hh_admin_layout_start($active = 'dashboard')
{
    $user = hh_admin_current_user();
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . hh_h(hh_admin_t('app_title')) . ' · ' . hh_h(hh_admin_t('admin')) . '</title>';
    echo '<link rel="stylesheet" href="assets/admin.css"></head><body><div class="layout">';
    echo '<aside class="sidebar"><h2>' . hh_h(hh_admin_t('app_title')) . '</h2><nav>';
    $items = array(
        'dashboard' => array('index.php', 'dashboard'),
        'handhelds' => array('handheld.php', 'handhelds'),
        'scrape' => array('scrape.php', 'scrape'),
        'translate' => array('translate.php', 'translate'),
        'publish' => array('publish.php', 'publish'),
        'blogger' => array('blogger.php', 'blogger'),
        'deploy' => array('deploy.php', 'deploy'),
    );
    foreach ($items as $key => $it) {
        $cls = $key === $active ? ' class="active"' : '';
        echo '<a href="' . hh_h($it[0]) . '"' . $cls . '>' . hh_h(hh_admin_t($it[1])) . '</a>';
    }
    echo '</nav></aside><main class="main">';
    echo '<div class="topbar"><div><strong>' . hh_h(hh_admin_t('admin')) . '</strong></div>';
    echo '<div class="muted">' . hh_h($user['username']) . ' · <a href="logout.php">' . hh_h(hh_admin_t('logout')) . '</a></div></div>';
}

function hh_admin_layout_end()
{
    echo '</main></div></body></html>';
}
